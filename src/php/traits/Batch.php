<?php

declare(strict_types=1);

trait LynxJournal_Batch {

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
                'messages' => array(__('No links to publish.', 'lynx-journal'))
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
                    __('Failed to publish "%1$s": %2$s', 'lynx-journal'),
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
    public function createRoundupPost(mixed $link_ids, string $post_title, bool $as_draft = false, string $mode = 'manual', int $author_id = 0): array {
        if (empty($link_ids) || !is_array($link_ids)) {
            return array('success' => false, 'post_id' => 0, 'message' => __('No links to publish.', 'lynx-journal'), 'error_code' => 'no_links');
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
        update_object_term_cache($link_ids, 'lynxjournal_category');
        update_object_term_cache($link_ids, 'lynxjournal_tag');

        if (empty($post_title)) {
            /* translators: %s: formatted date, e.g. "April 15, 2026" */
            $post_title = sprintf(__('Links Roundup - %s', 'lynx-journal'), gmdate('F j, Y'));
        }

        [$links_by_category, $uncategorized_links, $published_count] = $this->groupLinksByCategory($link_ids);

        if ($published_count === 0) {
            return array('success' => false, 'post_id' => 0, 'message' => __('No valid links to publish.', 'lynx-journal'), 'error_code' => 'no_valid_links');
        }

        $grouped_links = array(
            'by_category'   => $links_by_category,
            'uncategorized' => $uncategorized_links,
            'count'         => $published_count,
            'link_ids'      => $link_ids,
        );

        return $this->executeRoundupInsertion($post_title, $as_draft, $grouped_links, $mode, $author_id);
    }

    /**
     * Execute the roundup post insertion and metadata assignment.
     *
     * @since 1.0.0
     * @param string $post_title The roundup post title.
     * @param bool $as_draft Whether to create as draft.
     * @param array{by_category: array, uncategorized: array, count: int, link_ids: array} $grouped_links Links grouped by category, per groupLinksByCategory(), plus the original link_ids.
     * @param string $mode The scheduling mode that triggered this.
     * @param int $author_id Optional post author user ID.
     * @return array Result array with success status, post_id, link_count, and message.
     */
    private function executeRoundupInsertion(string $post_title, bool $as_draft, array $grouped_links, string $mode = 'manual', int $author_id = 0): array {
        ['by_category' => $links_by_category, 'uncategorized' => $uncategorized_links, 'count' => $count, 'link_ids' => $link_ids] = $grouped_links;

        // post_type 'post': the roundup is a normal blog post, not a lynxjournal CPT entry.
        $post_args = array(
            'post_title'   => $post_title,
            'post_content' => $this->buildRoundupContent($links_by_category, $uncategorized_links, $post_title, $author_id),
            'post_status'  => $as_draft ? 'draft' : 'publish',
            'post_type'    => 'post',
        );
        if ($author_id > 0) {
            $post_args['post_author'] = $author_id;
        }
        $args    = apply_filters('lynxjournal_roundup_post_args', $post_args, $link_ids, $mode);
        $post_id = wp_insert_post($args, true);

        if (is_wp_error($post_id)) {
            return array('success' => false, 'post_id' => 0, 'message' => __('Failed to create roundup post.', 'lynx-journal'), 'error_code' => 'insert_failed');
        }

        $this->assignRoundupCategories($post_id, $links_by_category);
        $this->assignRoundupTags($post_id, $link_ids);
        $this->markLinksAsPublished($link_ids, $post_id, $as_draft);

        if (!$as_draft) {
            update_option('lynxjournal_roundup_count', (int) get_option('lynxjournal_roundup_count', 0) + 1);
        }

        return array(
            'success'    => true,
            'post_id'    => $post_id,
            'link_count' => $count,
            /* translators: %d: number of links */
            /* translators: %d: number of links included in the roundup post */
            'message'    => sprintf(_n('Roundup post created successfully with %d link.', 'Roundup post created successfully with %d links.', $count, 'lynx-journal'), $count),
        );
    }

    private function groupLinksByCategory(array $link_ids): array {
        $links_by_category  = array();
        $uncategorized_links = array();
        $count = 0;

        foreach ($link_ids as $link_id) {
            $link = get_post($link_id);
            if (!$link || $link->post_type !== 'lynx-journal') {
                continue;
            }
            $cats = get_the_terms($link_id, 'lynxjournal_category');
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
     * Build HTML content for a roundup post. Uses the saved Post Template
     * (lynxjournal_post_template option) when one is configured, via the
     * PHP-ported rendering pipeline in TemplateRenderer.php — falling back
     * to the fixed builder below when no template is set, or when a
     * configured template renders to effectively empty content.
     *
     * @since 1.0.0
     * @param array $links_by_category Links grouped by category.
     * @param array $uncategorized_links Links without a category.
     * @param string $post_title Already-resolved roundup post title, used for the template's [title] token.
     * @param int $author_id Resolved post author user ID, used for the template's [author] token.
     * @return string The formatted roundup content HTML.
     */
    private function buildRoundupContent(array $links_by_category, array $uncategorized_links, string $post_title = '', int $author_id = 0): string {
        $template = get_option('lynxjournal_post_template', self::DEFAULT_POST_TEMPLATE);
        if (trim((string) $template) !== '') {
            $rendered = $this->buildRoundupContentFromTemplate(
                (string) $template, $links_by_category, $uncategorized_links, $post_title, $author_id
            );
            if (trim($rendered) !== '') {
                return $rendered;
            }
            // Falls through to the fixed builder below if the template
            // rendered to effectively empty content (e.g. every token unmatched).
        }

        $content       = '';
        $schema_items  = [];
        $heading_level = $this->getCategoryHeadingLevel();

        foreach ($links_by_category as $group) {
            $content .= $this->buildHeadingBlock(esc_html($group['term']->name), $heading_level);
            $content .= $this->renderLinkListBlock($group['links'], $schema_items);
        }

        if (!empty($uncategorized_links)) {
            $content .= $this->buildHeadingBlock(esc_html__('Other', 'lynx-journal'), $heading_level);
            $content .= $this->renderLinkListBlock($uncategorized_links, $schema_items);
        }

        if (!empty($schema_items)) {
            $content .= $this->buildItemListSchemaScript($schema_items);
        }

        return $content;
    }

    /**
     * Renders a Gutenberg list block for a set of links, appending a
     * schema.org ListItem entry for each rendered link into $schema_items.
     *
     * @since 1.0.0
     * @param array $ids Link post IDs to render.
     * @param array $schema_items Accumulator of schema.org ListItem entries, passed by reference.
     * @return string The rendered wp:list block markup.
     */
    private function renderLinkListBlock(array $ids, array &$schema_items): string {
        $items = [];
        foreach ($ids as $link_id) {
            $link = get_post($link_id);
            if (!$link) {
                continue;
            }
            $url  = get_post_meta($link_id, '_lynxjournal_url', true);
            $desc = trim($link->post_content);
            $title = '<strong>' . esc_html($link->post_title) . '</strong>';
            $item = !empty($url)
                ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $title . '</a>'
                : $title;
            if (!empty($desc)) {
                $item .= ' — ' . wp_kses_post($desc);
            }
            $items[] = "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->";

            $schema_item = [
                '@type'    => 'ListItem',
                'position' => count($schema_items) + 1,
                'name'     => $link->post_title,
            ];
            if (!empty($url)) {
                $schema_item['url'] = esc_url_raw($url);
            }
            $schema_items[] = $schema_item;
        }
        return "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n" . implode("\n", $items) . "\n</ul>\n<!-- /wp:list -->\n\n";
    }

    /**
     * Builds the ItemList JSON-LD schema.org script block for the roundup's links.
     *
     * @since 1.0.0
     * @param array $schema_items Schema.org ListItem entries collected while rendering link lists.
     * @return string The rendered wp:html block containing the JSON-LD script tag.
     */
    private function buildItemListSchemaScript(array $schema_items): string {
        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => $schema_items,
        ];
        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES);
        // Defends against a title/URL containing a literal "</script>" sequence,
        // which would otherwise terminate the script tag early.
        $json = str_replace('</', '<\/', $json);
        return "<!-- wp:html -->\n<script type=\"application/ld+json\">{$json}</script>\n<!-- /wp:html -->\n";
    }

    /**
     * Renders the roundup post content from the saved Post Template, using
     * the PHP port of the admin Test Publish/Test Post preview pipeline.
     *
     * @since 1.0.0
     * @param string $template Raw Post Template markdown+tokens text.
     * @param array $links_by_category Links grouped by category.
     * @param array $uncategorized_links Links without a category.
     * @param string $post_title Already-resolved roundup post title.
     * @param int $author_id Resolved post author user ID.
     * @return string Rendered Gutenberg block markup, or '' if the template rendered empty.
     */
    private function buildRoundupContentFromTemplate(
        string $template,
        array $links_by_category,
        array $uncategorized_links,
        string $post_title,
        int $author_id
    ): string {
        [$scalar_data, $category_variants, $all_link_token_maps] =
            $this->buildTemplateTokenData($links_by_category, $uncategorized_links, $post_title, $author_id);

        $text = $this->buildTemplateTextPhp($template, $category_variants, $scalar_data, $all_link_token_maps);
        $text = $this->convertIndentedLinesPhp($text);
        $html = $this->renderMarkdownPhp($text);

        return $html !== '' ? $this->wrapAsGutenbergBlocksPhp($html) : '';
    }

    /**
     * Determines the Gutenberg heading level to use for roundup category
     * headings, based on the Markdown heading marker (if any) on the
     * [category_name] line inside the admin's configured Post Template.
     * Falls back to level 3 (today's fixed behavior) when no template is
     * configured or no heading marker is found.
     *
     * Only used by the fixed (no-template) builder above; templates set via
     * the Post Template editor get their heading levels from real Markdown
     * parsing instead — see buildRoundupContentFromTemplate().
     *
     * @return int
     */
    private function getCategoryHeadingLevel(): int {
        $template = get_option('lynxjournal_post_template', self::DEFAULT_POST_TEMPLATE);
        if (!preg_match('/\[category_start\]([\s\S]*?)\[category_end\]/', $template, $block)) {
            return 3;
        }
        foreach (explode("\n", $block[1]) as $line) {
            if (strpos($line, '[category_name]') === false) {
                continue;
            }
            if (preg_match('/^(#{1,6})\s/', ltrim($line), $heading)) {
                return strlen($heading[1]);
            }
            break;
        }
        return 3;
    }

    /**
     * Builds a Gutenberg heading block at the given level, omitting the
     * {"level":N} attribute for the default level 2 (matches real WP
     * serialization, and assets/js/template-page.js's wrapAsGutenbergBlocks()).
     *
     * @param string $text Already-escaped heading text.
     * @param int $level
     * @return string
     */
    private function buildHeadingBlock(string $text, int $level): string {
        $tag   = "h{$level}";
        $attrs = $level === 2 ? '' : " {\"level\":{$level}}";
        return "<!-- wp:heading{$attrs} -->\n<{$tag} class=\"wp-block-heading\">{$text}</{$tag}>\n<!-- /wp:heading -->\n\n";
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
        // Mirrors lynxjournal_category terms into native WP categories so the roundup
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
                $cats = get_the_terms($link_id, 'lynxjournal_category');
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
            $tags = get_the_terms($link_id, 'lynxjournal_tag');
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
        global $wpdb;

        $meta_status = $as_draft ? 'draft' : 'published';
        $wp_status   = $as_draft ? 'lynxjournal_draft' : 'lynxjournal_pub';
        $date        = current_time('mysql');

        $placeholders = implode(',', array_fill(0, count($link_ids), '%d'));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_status = %s WHERE ID IN ({$placeholders}) AND post_type = 'lynx-journal'",
            array_merge([$wp_status], $link_ids)
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        foreach ($link_ids as $link_id) {
            update_post_meta($link_id, '_lynxjournal_published_post_id', $post_id);
            update_post_meta($link_id, '_lynxjournal_publish_status', $meta_status);
            update_post_meta($link_id, '_lynxjournal_published_date', $date);
        }
        delete_transient('lynxjournal_publish_stats');
    }
}
