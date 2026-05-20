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
     * Create a roundup post from multiple links.
     *
     * @since 1.0.0
     * @param mixed $link_ids Array of link post IDs.
     * @param string $post_title The roundup post title.
     * @param bool $as_draft Whether to create as draft instead of published.
     * @param string $mode The scheduling mode that triggered this ('manual', 'daily', etc).
     * @return array Result array with success status, post_id, link count, and message.
     */
    public function createRoundupPost(mixed $link_ids, string $post_title, bool $as_draft = false, string $mode = 'manual'): array {
        $guard = $this->validateRoundupRequest($link_ids);
        if ($guard !== null) {
            return $guard;
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
            /* translators: %s: formatted date, e.g. "April 15, 2026" */
            $post_title = sprintf(__('Links Roundup - %s', 'linkdigest'), gmdate('F j, Y'));
        }

        [$links_by_category, $uncategorized_links, $published_count] = $this->groupLinksByCategory($link_ids);

        if ($published_count === 0) {
            return array('success' => false, 'post_id' => 0, 'message' => __('No valid links to publish.', 'linkdigest'), 'error_code' => 'no_valid_links');
        }

        return $this->executeRoundupInsertion($post_title, $as_draft, $links_by_category, $uncategorized_links, $published_count, $link_ids, $mode);
    }

    /**
     * Validate a roundup publish request.
     *
     * @since 1.0.0
     * @param mixed $link_ids Array of link post IDs.
     * @return array|null Validation error array or null if valid.
     */
    private function validateRoundupRequest(mixed $link_ids): ?array {
        if (!current_user_can('publish_posts')) {
            return array('success' => false, 'post_id' => 0, 'message' => __('You do not have permission to publish posts.', 'linkdigest'), 'error_code' => 'no_permission');
        }
        if (empty($link_ids) || !is_array($link_ids)) {
            return array('success' => false, 'post_id' => 0, 'message' => __('No links to publish.', 'linkdigest'), 'error_code' => 'no_links');
        }
        return null;
    }

    /**
     * Execute the roundup post insertion and metadata assignment.
     *
     * @since 1.0.0
     * @param string $post_title The roundup post title.
     * @param bool $as_draft Whether to create as draft.
     * @param array $links_by_category Links grouped by category.
     * @param array $uncategorized_links Links without a category.
     * @param int $count Total count of links.
     * @param array $link_ids All link post IDs.
     * @param string $mode The scheduling mode that triggered this.
     * @return array Result array with success status, post_id, link_count, and message.
     */
    private function executeRoundupInsertion(string $post_title, bool $as_draft, array $links_by_category, array $uncategorized_links, int $count, array $link_ids, string $mode = 'manual'): array {
        // post_type 'post': the roundup is a normal blog post, not a linkdigest CPT entry.
        $args = apply_filters('linkdigest_roundup_post_args', array(
            'post_title'   => $post_title,
            'post_content' => $this->buildRoundupContent($links_by_category, $uncategorized_links),
            'post_status'  => $as_draft ? 'draft' : 'publish',
            'post_type'    => 'post',
        ), $link_ids, $mode);
        $post_id = wp_insert_post($args);

        if (is_wp_error($post_id) || !$post_id) {
            return array('success' => false, 'post_id' => 0, 'message' => __('Failed to create roundup post.', 'linkdigest'), 'error_code' => 'insert_failed');
        }

        $this->assignRoundupCategories($post_id, $links_by_category);
        $this->assignRoundupTags($post_id, $link_ids);
        $this->markLinksAsPublished($link_ids, $post_id, $as_draft);

        return array(
            'success'    => true,
            'post_id'    => $post_id,
            'link_count' => $count,
            /* translators: %d: number of links */
            'message'    => sprintf(__('Roundup post created successfully with %d link(s).', 'linkdigest'), $count),
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
     * Build HTML content for a roundup post.
     *
     * @since 1.0.0
     * @param array $links_by_category Links grouped by category.
     * @param array $uncategorized_links Links without a category.
     * @return string The formatted roundup content HTML.
     */
    private function buildRoundupContent(array $links_by_category, array $uncategorized_links): string {
        $content               = '';
        $has_item_template     = $this->getTemplatePostId('roundup_item') !== null;
        $has_group_template    = $this->getTemplatePostId('roundup_group') !== null;

        $render_group_heading = function(string $name) use (&$content, $has_group_template) {
            if ($has_group_template) {
                $heading = $this->renderTemplate('roundup_group', array('category' => $name));
                if (!empty($heading)) {
                    $content .= $heading . "\n\n";
                    return;
                }
            }
            $content .= '<h2>' . esc_html($name) . "</h2>\n\n";
        };

        $render_list = function(array $ids) use (&$content, $has_item_template) {
            $content .= "<ul>\n";
            foreach ($ids as $link_id) {
                $link = get_post($link_id);
                $url  = get_post_meta($link_id, '_linkdigest_url', true);
                $desc = trim($link->post_content);

                $item_html = '';
                if ($has_item_template) {
                    $tags      = get_the_terms($link_id, 'linkdigest_tag');
                    $tag_names = ($tags && !is_wp_error($tags)) ? wp_list_pluck($tags, 'name') : array();
                    $item_html = $this->renderTemplate('roundup_item', array(
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
            $content .= "</ul>\n\n";
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
     * Assign categories to a roundup post.
     *
     * @since 1.0.0
     * @param int $post_id The roundup post ID.
     * @param array $links_by_category Links grouped by category.
     * @return void
     */
    private function assignRoundupCategories(int $post_id, array $links_by_category): void {
        // Mirrors linkdigest_category terms into native WP categories so the roundup
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
     * Assign tags from links to a roundup post.
     *
     * @since 1.0.0
     * @param int $post_id The roundup post ID.
     * @param array $link_ids Array of link post IDs.
     * @return void
     */
    private function assignRoundupTags(int $post_id, array $link_ids): void {
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
