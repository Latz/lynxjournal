<?php

declare(strict_types=1);

trait LinkDigest_Queries {

    /**
     * Get link publishing statistics.
     *
     * Uses static cache within requests and transient cache across requests.
     *
     * @since 1.0.0
     * @return array Array with total_links, published_links, draft_links, unpublished_links counts.
     */
    public function getPublishStatistics(): array {
        // Static prevents repeated DB hits within a single request (e.g., widget + dashboard page).
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        // Transient caches across requests; invalidated by markLinksAsPublished() and unpublishLink().
        $cached = get_transient('linkdigest_publish_stats');
        if ($cached !== false) {
            return $cache = $cached;
        }

        // wp_count_posts reads the indexed post_status column — no meta joins needed.
        $counts          = wp_count_posts('linkdigest');
        $published_links = (int) ($counts->linkdigest_published ?? 0);
        $draft_links     = (int) ($counts->linkdigest_draft     ?? 0);
        $pending_links   = (int) ($counts->linkdigest_pending   ?? 0);
        $total_links     = $published_links + $draft_links + $pending_links;

        $cache = [
            'total_links'       => $total_links,
            'published_links'   => $published_links,
            'draft_links'       => $draft_links,
            'unpublished_links' => $pending_links,
        ];
        set_transient('linkdigest_publish_stats', $cache, 300);
        return $cache;
    }

    /**
     * Get links grouped by category with optional filtering and pagination.
     *
     * @since 1.0.0
     * @param string $search Optional search term.
     * @param int $month Optional month filter (YYYYMM format).
     * @param int $cat Optional category term ID filter.
     * @param int $paged Page number for pagination.
     * @param int $per_page Number of items per page.
     * @return array Array with grouped links, max_num_pages, and total_items.
     */
    public function getLinksGroupedByCategory(string $search = '', int $month = 0, int $cat = 0, int $paged = 1, int $per_page = 20): array {
        $args = [
            'post_type'              => 'linkdigest',
            'post_status'            => ['linkdigest_pending', 'linkdigest_published', 'linkdigest_draft'],
            'posts_per_page'         => $per_page,
            'paged'                  => $paged,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'update_post_term_cache' => false,
        ];
        if ($search !== '') {
            $args['s'] = $search;
        }
        if ($month > 0) {
            $args['date_query'] = [[
                'year'  => (int) substr((string) $month, 0, 4),
                'month' => (int) substr((string) $month, 4, 2),
            ]];
        }
        if ($cat > 0) {
            $args['tax_query'] = [[ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'taxonomy' => 'linkdigest_category',
                'field'    => 'term_id',
                'terms'    => $cat,
            ]];
        }
        $query = new WP_Query($args);
        $all_links = $query->posts;
        if (empty($all_links)) {
            return ['grouped' => [], 'max_num_pages' => 0, 'total_items' => 0];
        }

        $link_ids = wp_list_pluck($all_links, 'ID');
        // Batch queries prime caches; all subsequent get_the_terms() and get_post_meta() calls become cache hits.
        update_meta_cache('post', $link_ids);
        update_object_term_cache($link_ids, 'linkdigest');

        $grouped = [];
        $uncategorized_key = __('Uncategorized', 'linkdigest');
        foreach ($all_links as $link) {
            $cats = get_the_terms($link->ID, 'linkdigest_category');
            $group_name = ($cats && !is_wp_error($cats)) ? $cats[0]->name : $uncategorized_key;
            $grouped[$group_name][] = $link;
        }

        wp_reset_postdata();

        return [
            'grouped'       => $grouped,
            'max_num_pages' => (int) $query->max_num_pages,
            'total_items'   => (int) $query->found_posts,
        ];
    }

    /**
     * Get all linkdigest categories with caching.
     *
     * @since 1.0.0
     * @return array Array of category term objects.
     */
    public function getCachedCategories(): array {
        $cache_key = 'linkdigest_categories_terms';
        $categories = get_transient($cache_key);

        if ($categories === false) {
            $categories = get_terms([
                'taxonomy'   => 'linkdigest_category',
                'hide_empty' => false,
            ]);

            if (is_wp_error($categories)) {
                return [];
            }

            set_transient($cache_key, $categories, HOUR_IN_SECONDS);
        }

        return is_array($categories) ? $categories : [];
    }

    /**
     * Run schema migration to custom post statuses if needed.
     *
     * Migrates existing published linkdigest posts to custom status values.
     *
     * @since 1.0.0
     * @return void
     */
    public function maybeRunMigration(): void {
        if (get_option('linkdigest_schema_version') === '2') {
            return;
        }
        global $wpdb;

        // Bulk-migrate existing 'publish'-status linkdigest posts to custom statuses.
        // Three SQL UPDATEs are far faster than iterating with wp_update_post() for large sites.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID
               AND pm.meta_key = '_linkdigest_publish_status'
               AND pm.meta_value = 'published'
             SET p.post_status = 'linkdigest_published'
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            'linkdigest'
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID
               AND pm.meta_key = '_linkdigest_publish_status'
               AND pm.meta_value = 'draft'
             SET p.post_status = 'linkdigest_draft'
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            'linkdigest'
        ));

        // All remaining 'publish' linkdigest posts have no status meta → they are pending.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts}
             SET post_status = 'linkdigest_pending'
             WHERE post_type = %s AND post_status = 'publish'",
            'linkdigest'
        ));

        delete_transient('linkdigest_publish_stats');
        update_option('linkdigest_schema_version', '2');
    }

    /**
     * Unpublish a link and revert it to pending status.
     *
     * @since 1.0.0
     * @param int $link_id The link post ID.
     * @return array Result array with success status and message.
     */
    public function unpublishLink(int $link_id): array {
        // Get published post ID
        $published_post_id = get_post_meta($link_id, '_linkdigest_published_post_id', true);

        if (!$published_post_id) {
            return array(
                'success' => false,
                'message' => __('This link has not been published.', 'linkdigest')
            );
        }

        // Move blog post to trash
        $trashed = wp_trash_post($published_post_id);

        if (!$trashed) {
            return array(
                'success' => false,
                'message' => __('Failed to unpublish link.', 'linkdigest')
            );
        }

        // Reset post status and meta fields
        wp_update_post(['ID' => $link_id, 'post_status' => 'linkdigest_pending']);
        delete_post_meta($link_id, '_linkdigest_published_post_id');
        delete_post_meta($link_id, '_linkdigest_publish_status');
        delete_post_meta($link_id, '_linkdigest_published_date');
        delete_transient('linkdigest_publish_stats');

        return array(
            'success' => true,
            'message' => __('Link unpublished successfully.', 'linkdigest')
        );
    }
}
