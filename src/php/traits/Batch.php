<?php

declare(strict_types=1);

trait LinkDigest_Batch {

    /**
     * Publish multiple links as individual blog posts.
     *
     * @since 1.0.0
     * @param mixed $link_ids Array of link post IDs.
     * @param bool $as_draft Whether to create as drafts instead of published.
     * @return array Result array with success count, failed count, and messages.
     */
    public function batchPublishLinks(mixed $link_ids, bool $as_draft = false): array {
        $success_count = 0;
        $failed_count = 0;
        $messages = array();

        if (empty($link_ids) || !is_array($link_ids)) {
            return array(
                'success' => 0,
                'failed' => 0,
                'messages' => array(__('No links to publish.', 'linkdigest'))
            );
        }

        foreach ($link_ids as $link_id) {
            $result = $this->createBlogPost($link_id, $as_draft);

            if ($result['success']) {
                $success_count++;
            } else {
                $failed_count++;
                $link = get_post($link_id);
                $messages[] = sprintf(
                    /* translators: 1: link title, 2: error message */
                    __('Failed to publish "%1$s": %2$s', 'linkdigest'),
                    $link ? $link->post_title : '#' . $link_id,
                    $result['message']
                );
            }
        }

        return array(
            'success' => $success_count,
            'failed' => $failed_count,
            'messages' => $messages
        );
    }

    /**
     * Create a digest post from multiple links.
     *
     * @since 1.0.0
     * @param mixed $link_ids Array of link post IDs.
     * @param string $post_title The digest post title.
     * @param bool $as_draft Whether to create as draft instead of published.
     * @param string $mode The scheduling mode that triggered this ('manual', 'daily', etc).
     * @return array Result array with success status, post_id, link count, and message.
     */
    public function createDigestPost(mixed $link_ids, string $post_title, bool $as_draft = false, string $mode = 'manual', int $author_id = 0): array {
        if (empty($link_ids) || !is_array($link_ids)) {
            return array('success' => false, 'post_id' => 0, 'message' => __('No links to publish.', 'linkdigest'), 'error_code' => 'no_links');
        }

        // Prime caches: 4 queries instead of ~5×N in the batch publishing path
        get_posts([
            'post__in'               => $link_ids,
            'posts_per_page'         => -1,
            'post_type'              => 'any',
            'post_status'            => 'any',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        update_meta_cache('post', $link_ids);
        update_object_term_cache($link_ids, 'linkdigest_category');
        update_object_term_cache($link_ids, 'linkdigest_tag');

        if (empty($post_title)) {
            $next = (int) get_option('linkdigest_digest_count', 0) + 1;
            /* translators: %d: digest issue number, e.g. 1, 2, 3 */
            $post_title = sprintf(__('Links #%d', 'linkdigest'), $next);
        }

        [$links_by_category, $uncategorized_links, $published_count] = $this->groupLinksByCategory($link_ids);

        if ($published_count === 0) {
            return array('success' => false, 'post_id' => 0, 'message' => __('No valid links to publish.', 'linkdigest'), 'error_code' => 'no_valid_links');
        }

        return $this->executeDigestInsertion($post_title, $as_draft, $links_by_category, $uncategorized_links, $published_count, $link_ids, $mode, $author_id);
    }

    /**
     * Execute the digest post insertion and metadata assignment.
     *
     * @since 1.0.0
     * @param string $post_title The digest post title.
     * @param bool $as_draft Whether to create as draft.
     * @param array $links_by_category Links grouped by category.
     * @param array $uncategorized_links Links without a category.
     * @param int $count Total count of links.
     * @param array $link_ids All link post IDs.
     * @param string $mode The scheduling mode that triggered this.
     * @param int $author_id Optional post author user ID.
     * @return array Result array with success status, post_id, link_count, and message.
     */
    private function executeDigestInsertion(string $post_title, bool $as_draft, array $links_by_category, array $uncategorized_links, int $count, array $link_ids, string $mode = 'manual', int $author_id = 0): array {
        // post_type 'post': the digest is a normal blog post, not a linkdigest CPT entry.
        $post_args = array(
            'post_title'   => $post_title,
            'post_content' => $this->buildDigestContent($links_by_category, $uncategorized_links),
            'post_status'  => $as_draft ? 'draft' : 'publish',
            'post_type'    => 'post',
        );
        if ($author_id > 0) {
            $post_args['post_author'] = $author_id;
        }
        $args    = apply_filters('linkdigest_digest_post_args', $post_args, $link_ids, $mode);
        $post_id = wp_insert_post($args);

        if (is_wp_error($post_id) || !$post_id) {
            return array('success' => false, 'post_id' => 0, 'message' => __('Failed to create digest post.', 'linkdigest'), 'error_code' => 'insert_failed');
        }

        update_option('linkdigest_digest_count', (int) get_option('linkdigest_digest_count', 0) + 1);

        $this->assignDigestCategories($post_id, $links_by_category);
        $this->assignDigestTags($post_id, $link_ids);
        $this->markLinksAsPublished($link_ids, $post_id, $as_draft);

        return array(
            'success'    => true,
            'post_id'    => $post_id,
            'link_count' => $count,
            /* translators: %d: number of links */
            'message'    => sprintf(__('Digest post created successfully with %d link(s).', 'linkdigest'), $count),
        );
    }

    private function groupLinksByCategory(array $link_ids): array {
        $links_by_category  = array();
        $uncategorized_links = array();
        $count = 0;

        foreach ($link_ids as $link_id) {
            $link = get_post($link_id);
            if (!$link || $link->post_type !== 'linkdigest') {
                continue;
            }
            $cats = get_the_terms($link_id, 'linkdigest_category');
            if ($cats && !is_wp_error($cats)) {
                $primary = $cats[0];
                if (!isset($links_by_category[$primary->slug])) {
                    $links_by_category[$primary->slug] = array('term' => $primary, 'links' => array());
                }
                $links_by_category[$primary->slug]['links'][] = $link_id;
            } else {
                $uncategorized_links[] = $link_id;
            }
            $count++;
        }

        return [$links_by_category, $uncategorized_links, $count];
    }

    /**
     * Build HTML content for a digest post.
     *
     * @since 1.0.0
     * @param array $links_by_category Links grouped by category.
     * @param array $uncategorized_links Links without a category.
     * @return string The formatted digest content HTML.
     */
    private function buildDigestContent(array $links_by_category, array $uncategorized_links): string {
        $content               = '';
        $has_item_template     = $this->getTemplatePostId('digest_item') !== null;
        $has_group_template    = $this->getTemplatePostId('digest_group') !== null;

        $render_group_heading = function(string $name) use (&$content, $has_group_template) {
            if ($has_group_template) {
                $heading = $this->renderTemplate('digest_group', array('category' => $name));
                if (!empty($heading)) {
                    $content .= $heading . "\n\n";
                    return;
                }
            }
            $content .= '<h2>' . esc_html($name) . "</h2>\n\n";
        };

        $render_list = function(array $ids) use (&$content, $has_item_template) {
            $tag = isset($GLOBALS['linkdigest_list_type']) && $GLOBALS['linkdigest_list_type'] === 'ol' ? 'ol' : 'ul';
            unset($GLOBALS['linkdigest_list_type']);
            $content .= "<{$tag}>\n";
            foreach ($ids as $link_id) {
                $link = get_post($link_id);
                $url  = get_post_meta($link_id, '_linkdigest_url', true);
                $desc = trim($link->post_content);

                $item_html = '';
                if ($has_item_template) {
                    $tags      = get_the_terms($link_id, 'linkdigest_tag');
                    $tag_names = ($tags && !is_wp_error($tags)) ? wp_list_pluck($tags, 'name') : array();
                    $item_html = $this->renderTemplate('digest_item', array(
                        'title'       => $link->post_title,
                        'url'         => $url,
                        'description' => $desc,
                        'tags'        => $tag_names,
                    ));
                }

                if (empty($item_html)) {
                    $item_html = !empty($url)
                        ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($link->post_title) . '</a>'
                        : esc_html($link->post_title);
                    if (!empty($desc)) {
                        $item_html .= '<br>' . wp_kses_post($desc);
                    }
                }

                $content .= '<li>' . $item_html . "</li>\n";
            }
            $content .= "</{$tag}>\n\n";
        };

        foreach ($links_by_category as $group) {
            $render_group_heading($group['term']->name);
            $render_list($group['links']);
        }

        if (!empty($uncategorized_links)) {
            $render_group_heading(__('Other', 'linkdigest'));
            $render_list($uncategorized_links);
        }

        return $content;
    }

    /**
     * Assign categories to a digest post.
     *
     * @since 1.0.0
     * @param int $post_id The digest post ID.
     * @param array $links_by_category Links grouped by category.
     * @return void
     */
    private function assignDigestCategories(int $post_id, array $links_by_category): void {
        // Mirrors linkdigest_category terms into native WP categories so the digest
        // appears in standard category archives; creates the WP category if it doesn't exist.
        $all_cats = $this->collectCategoryTerms($links_by_category);

        if (empty($all_cats)) {
            return;
        }

        $wp_cat_ids = array();
        foreach ($all_cats as $cat) {
            $existing = get_category_by_slug($cat->slug);
            if ($existing) {
                $wp_cat_ids[$existing->term_id] = $existing->term_id;
            } else {
                $new = wp_insert_term($cat->name, 'category');
                if (!is_wp_error($new)) {
                    $wp_cat_ids[$new['term_id']] = $new['term_id'];
                }
            }
        }

        if (!empty($wp_cat_ids)) {
            wp_set_post_categories($post_id, array_values($wp_cat_ids));
        }
    }

    /**
     * Collect all unique category terms from grouped links.
     *
     * @since 1.0.0
     * @param array $links_by_category Links grouped by category.
     * @return array Array of category term objects.
     */
    private function collectCategoryTerms(array $links_by_category): array {
        $all_cats = array();
        foreach ($links_by_category as $group) {
            foreach ($group['links'] as $link_id) {
                $cats = get_the_terms($link_id, 'linkdigest_category');
                if ($cats && !is_wp_error($cats)) {
                    foreach ($cats as $cat) {
                        $all_cats[] = $cat;
                    }
                }
            }
        }
        return $all_cats;
    }

    /**
     * Assign tags from links to a digest post.
     *
     * @since 1.0.0
     * @param int $post_id The digest post ID.
     * @param array $link_ids Array of link post IDs.
     * @return void
     */
    private function assignDigestTags(int $post_id, array $link_ids): void {
        $tag_names = array();
        foreach ($link_ids as $link_id) {
            $tags = get_the_terms($link_id, 'linkdigest_tag');
            if ($tags && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }
        }
        if (!empty($tag_names)) {
            wp_set_post_tags($post_id, array_unique($tag_names));
        }
    }

    /**
     * Mark links as published and update their metadata.
     *
     * @since 1.0.0
     * @param array $link_ids Array of link post IDs.
     * @param int $post_id The published blog post ID.
     * @param bool $as_draft Whether links were published as draft.
     * @return void
     */
    private function markLinksAsPublished(array $link_ids, int $post_id, bool $as_draft): void {
        $meta_status = $as_draft ? 'draft' : 'published';
        $wp_status   = $as_draft ? 'linkdigest_draft' : 'linkdigest_published';
        $date        = current_time('mysql');
        foreach ($link_ids as $link_id) {
            $link = get_post($link_id);
            if ($link && $link->post_type === 'linkdigest') {
                wp_update_post(['ID' => $link_id, 'post_status' => $wp_status]);
                update_post_meta($link_id, '_linkdigest_published_post_id', $post_id);
                update_post_meta($link_id, '_linkdigest_publish_status', $meta_status);
                update_post_meta($link_id, '_linkdigest_published_date', $date);
            }
        }
        delete_transient('linkdigest_publish_stats');
    }
}
