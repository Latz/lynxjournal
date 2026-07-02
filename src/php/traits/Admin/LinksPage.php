<?php

declare(strict_types=1);

trait LynxJournal_Admin_LinksPage {

    public function showLinksPage(): void {
        global $wpdb, $wp_locale;

        [$action_message, $action_error] = $this->processLinksPageAction();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $search = isset($_GET['s'])              ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $month  = isset($_GET['m'])              ? absint($_GET['m']) : 0;
        $cat    = isset($_GET['lynxjournal_cat']) ? absint($_GET['lynxjournal_cat']) : 0;
        $paged  = isset($_GET['paged'])          ? max(1, absint($_GET['paged'])) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $settings = wp_parse_args((array) get_option('lynxjournal_x_settings', []), ['ui_links_per_page' => 20]);
        $per_page = max(1, (int) $settings['ui_links_per_page']);

        $result        = $this->getLinksGroupedByCategory($search, $month, $cat, $paged, $per_page);
        $grouped_links = $result['grouped'];
        $max_num_pages = $result['max_num_pages'];
        $total_items   = $result['total_items'];
        $has_links     = $this->hasLinks($grouped_links);
        $is_filtered   = $search !== '' || $month > 0 || $cat > 0;

        // Date options: distinct year/month from lynxjournal posts.
        $date_options = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month
             FROM {$wpdb->posts}
             WHERE post_type = 'lynx-journal'
               AND post_status IN ('lynxjournal_pending','lynxjournal_pub','lynxjournal_draft')
             ORDER BY post_date DESC"
        );

        $categories = $this->getCachedCategories();

        // Build pagination links, preserving current filters.
        $base_url = add_query_arg(
            array_filter([
                'page'           => 'lynxjournal-admin',
                's'              => $search ?: null,
                'm'              => $month  ?: null,
                'lynxjournal_cat' => $cat    ?: null,
                'paged'          => '%#%',
            ]),
            admin_url('admin.php')
        );
        $pagination_links = $max_num_pages > 1 ? paginate_links([
            'base'      => $base_url,
            'format'    => '',
            'current'   => $paged,
            'total'     => $max_num_pages,
            'type'      => 'plain',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ]) : '';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('LynxJournal - All Links', 'lynx-journal'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lynxjournal-add')); ?>" class="page-title-action"><?php esc_html_e('Add New', 'lynx-journal'); ?></a>
            <hr class="wp-header-end">

            <?php if ($action_message) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post($action_message); ?></p></div>
            <?php endif; ?>
            <?php if ($action_error) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($action_error); ?></p></div>
            <?php endif; ?>

            <?php $this->renderLinksFilterForm($date_options, $categories, ['month' => $month, 'cat' => $cat, 'search' => $search], $total_items, $max_num_pages, $pagination_links, $wp_locale); ?>
            <?php $this->renderLinksTableSection($has_links, $is_filtered, $grouped_links); ?>

            <?php if ($max_num_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo $pagination_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() escapes internally ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderLinksFilterForm(array $date_options, array $categories, array $filters, int $total_items, int $max_num_pages, string $pagination_links, ?\WP_Locale $wp_locale = null): void {
        if ($wp_locale === null && isset($GLOBALS['wp_locale'])) {
            $wp_locale = $GLOBALS['wp_locale'];
        }

        $month  = (int) ($filters['month'] ?? 0);
        $cat    = (int) ($filters['cat'] ?? 0);
        $search = (string) ($filters['search'] ?? '');
        ?>
        <form method="get" class="lynxjournal-filter-form">
            <input type="hidden" name="page" value="lynxjournal-admin">

            <div class="tablenav top lynxjournal-links-tablenav">
                <div class="alignleft actions">
                    <label class="screen-reader-text" for="filter-by-date"><?php esc_html_e('Filter by date', 'lynx-journal'); ?></label>
                    <select name="m" id="filter-by-date">
                        <option value="0"<?php selected($month, 0); ?>><?php esc_html_e('All dates', 'lynx-journal'); ?></option>
                        <?php foreach ($date_options as $row) :
                            $val = (int) $row->year * 100 + (int) $row->month;
                            $label = sprintf(
                                /* translators: 1: month name, 2: year */
                                _x('%1$s %2$d', 'month year', 'lynx-journal'),
                                $wp_locale->get_month($row->month),
                                $row->year
                            );
                        ?>
                            <option value="<?php echo esc_attr((string) $val); ?>"<?php selected($month, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="screen-reader-text" for="lynxjournal-filter-cat"><?php esc_html_e('Filter by category', 'lynx-journal'); ?></label>
                    <select name="lynxjournal_cat" id="lynxjournal-filter-cat">
                        <option value="0"<?php selected($cat, 0); ?>><?php esc_html_e('All categories', 'lynx-journal'); ?></option>
                        <?php foreach ($categories as $term) : ?>
                            <option value="<?php echo esc_attr((string) $term->term_id); ?>"<?php selected($cat, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e('Filter', 'lynx-journal'); ?>">
                </div>

                <p class="search-box">
                    <label class="screen-reader-text" for="link-search-input"><?php esc_html_e('Search Links', 'lynx-journal'); ?></label>
                    <input type="search" id="link-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e('Search Links', 'lynx-journal'); ?>">
                </p>

                <?php if ($max_num_pages > 1) : ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: number of items in the list table */
                            _n('%s item', '%s items', $total_items, 'lynx-journal'),
                            number_format_i18n($total_items)
                        )); ?>
                    </span>
                    <?php echo $pagination_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() escapes internally ?>
                </div>
                <?php endif; ?>

                <br class="clear">
            </div>
        </form>
        <?php
    }

    private function renderLinksTableSection(bool $has_links, bool $is_filtered, array $grouped_links): void {
        if (!$has_links && !$is_filtered) {
            echo '<p>' . esc_html__('No links found. Add your first link!', 'lynx-journal') . '</p>';
            return;
        }
        if (!$has_links) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th class="manage-column column-title"><?php esc_html_e('Title', 'lynx-journal'); ?></th>
                    <th class="manage-column column-url"><?php esc_html_e('URL', 'lynx-journal'); ?></th>
                    <th class="manage-column column-status"><?php esc_html_e('Status', 'lynx-journal'); ?></th>
                    <th class="manage-column column-published"><?php esc_html_e('Published', 'lynx-journal'); ?></th>
                    <th class="manage-column column-date"><?php esc_html_e('Date', 'lynx-journal'); ?></th>
                    <th class="manage-column column-actions"><?php esc_html_e('Actions', 'lynx-journal'); ?></th>
                </tr></thead>
                <tbody>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="6"><?php esc_html_e('No links found.', 'lynx-journal'); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php
            return;
        }
        $this->renderCategoryLinks($grouped_links);
    }

    private function hasLinks(array $grouped_links): bool {
        foreach ($grouped_links as $category_links) {
            if (!empty($category_links)) {
                return true;
            }
        }
        return false;
    }

    private function renderCategoryLinks(array $grouped_links): void {
        foreach ($grouped_links as $category_name => $category_links) : ?>
            <div class="lynxjournal-category-section">
                <h2 class="lynxjournal-category-heading"><?php echo esc_html($category_name); ?></h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="manage-column column-title sortable desc" data-col="0"><a href="#"><span><?php esc_html_e('Title', 'lynx-journal'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
                            <th class="manage-column column-url sortable desc" data-col="1"><a href="#"><span><?php esc_html_e('URL', 'lynx-journal'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
                            <th class="manage-column column-status sortable desc" data-col="2"><a href="#"><span><?php esc_html_e('Status', 'lynx-journal'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
                            <th class="manage-column column-published sortable desc" data-col="3"><a href="#"><span><?php esc_html_e('Published', 'lynx-journal'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
                            <th class="manage-column column-date sortable desc" data-col="4"><a href="#"><span><?php esc_html_e('Date', 'lynx-journal'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
                            <th class="manage-column column-actions"><?php esc_html_e('Actions', 'lynx-journal'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_links as $link) :
                            $this->renderLinkTableRow($link);
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach;
    }

    private function renderLinkTableRow(\WP_Post $link): void {
        $url = get_post_meta($link->ID, '_lynxjournal_url', true);
        $publish_status = get_post_meta($link->ID, '_lynxjournal_publish_status', true);
        $published_post_id = get_post_meta($link->ID, '_lynxjournal_published_post_id', true);
        $published_date = get_post_meta($link->ID, '_lynxjournal_published_date', true);
        if (empty($publish_status)) {
            $publish_status = 'unpublished';
        }
        $sort_val = match ($publish_status) {
            'published' => '1',
            'draft' => '2',
            default => '3',
        };
        $url_display = strlen($url) > 50 ? substr($url, 0, 50) . '...' : $url;
        ?>
        <tr>
            <td class="column-title"><strong><?php echo esc_html($link->post_title); ?></strong></td>
            <td class="column-url">
                <?php if ($url) : ?>
                    <a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url_display); ?></a>
                <?php else : ?>
                    -
                <?php endif; ?>
            </td>
            <td class="column-status" data-sort-val="<?php echo esc_attr($sort_val); ?>"><?php $this->renderLinkStatusBadge($publish_status); ?></td>
            <td class="column-published">
                <?php if ($published_date) : ?>
                    <?php echo esc_html(mysql2date('Y-m-d', $published_date)); ?>
                <?php else : ?>
                    -
                <?php endif; ?>
            </td>
            <td class="column-date"><?php echo esc_html(get_the_date('Y-m-d', $link->ID)); ?></td>
            <td class="column-actions"><?php $this->renderLinkActions($link, $publish_status, $published_post_id); ?></td>
        </tr>
        <?php
    }

    private function processLinksPageAction(): array {
        $message = '';
        $error   = '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['action'], $_GET['link_id'], $_GET['_wpnonce'])) {
            return [$message, $error];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action  = sanitize_key(wp_unslash($_GET['action']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $link_id = absint($_GET['link_id']);

        $nonce_actions = [
            'publish_link'   => 'publish_link_' . $link_id,
            'draft_link'     => 'draft_link_' . $link_id,
            'unpublish_link' => 'unpublish_link_' . $link_id,
            'delete'         => 'delete_link_' . $link_id,
        ];

        if (!array_key_exists($action, $nonce_actions)
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_actions[$action])
        ) {
            return [$message, $error];
        }

        if ($action === 'publish_link') {
            if (!current_user_can('publish_post', $link_id)) {
                return [$message, $error];
            }
            [$message, $error] = $this->executePublishAction($link_id, false);
        } elseif ($action === 'draft_link') {
            if (!current_user_can('edit_post', $link_id)) {
                return [$message, $error];
            }
            [$message, $error] = $this->executePublishAction($link_id, true);
        } elseif ($action === 'unpublish_link') {
            if (!current_user_can('edit_post', $link_id)) {
                return [$message, $error];
            }
            [$message, $error] = $this->executeUnpublishAction($link_id);
        } elseif ($action === 'delete') {
            if (!current_user_can('delete_post', $link_id)) {
                return [$message, $error];
            }
            wp_delete_post($link_id, true);
            $message = __('Link deleted successfully.', 'lynx-journal');
        }

        return [$message, $error];
    }

    private function executePublishAction(int $link_id, bool $as_draft): array {
        $result = $this->createBlogPost($link_id, $as_draft);
        if (!$result['success']) {
            return ['', $result['message']];
        }
        if ($as_draft) {
            return [esc_html($result['message']) . ' <a href="' . esc_url(get_edit_post_link($result['post_id'])) . '" target="_blank">' . esc_html__('Edit Draft', 'lynx-journal') . '</a>', ''];
        }
        return [esc_html($result['message']) . ' <a href="' . esc_url(get_permalink($result['post_id'])) . '" target="_blank">' . esc_html__('View Post', 'lynx-journal') . '</a>', ''];
    }

    private function executeUnpublishAction(int $link_id): array {
        $result = $this->unpublishLink($link_id);
        if ($result['success']) {
            return [$result['message'], ''];
        }
        return ['', $result['message']];
    }

    private function renderLinkStatusBadge(string $publish_status): void {
        if ($publish_status === 'published') {
            echo esc_html__('Published', 'lynx-journal');
        } elseif ($publish_status === 'draft') {
            echo '<span class="lynxjournal-status-badge lynxjournal-status-draft">📝 ' . esc_html__('Draft', 'lynx-journal') . '</span>';
        } elseif ($publish_status === 'unpublished') {
            echo '<span class="lynxjournal-status-badge lynxjournal-status-unpublished">' . esc_html__('Unpublished', 'lynx-journal') . '</span>';
        }
    }

    private function renderLinkActions(\WP_Post $link, string $publish_status, mixed $published_post_id): void {
        $unpublish_url = esc_url( wp_nonce_url( admin_url( self::ADMIN_LINKS_PAGE . '&action=unpublish_link&link_id=' . $link->ID ), 'unpublish_link_' . $link->ID ) );
        $delete_url    = esc_url( wp_nonce_url( admin_url( self::ADMIN_LINKS_PAGE . '&action=delete&link_id=' . $link->ID ), 'delete_link_' . $link->ID ) );

        if ( $publish_status === 'published' ) {
            echo '<a href="' . esc_url( get_permalink( $published_post_id ) ) . '" target="_blank">' . esc_html__( 'View Post', 'lynx-journal' ) . '</a> | ';
            $this->renderConfirmLink( $unpublish_url, esc_js( __( 'Are you sure you want to unpublish this link?', 'lynx-journal' ) ), esc_html__( 'Unpublish', 'lynx-journal' ) );
            echo ' | ';
        } elseif ( $publish_status === 'draft' ) {
            echo '<a href="' . esc_url( get_edit_post_link( $published_post_id ) ) . '" target="_blank">' . esc_html__( 'View Draft', 'lynx-journal' ) . '</a> | ';
            $this->renderConfirmLink( $unpublish_url, esc_js( __( 'Are you sure you want to unpublish this link?', 'lynx-journal' ) ), esc_html__( 'Unpublish', 'lynx-journal' ) );
            echo ' | ';
        }
        echo '<a href="' . esc_url( get_edit_post_link( $link->ID ) ) . '">' . esc_html__( 'Edit', 'lynx-journal' ) . '</a> | ';
        $this->renderConfirmLink( $delete_url, esc_js( __( 'Are you sure you want to delete this link?', 'lynx-journal' ) ), esc_html__( 'Delete', 'lynx-journal' ) );
    }

    /**
     * Echo an anchor tag that prompts a JS confirm() dialog before following its href.
     *
     * @param string $url              Pre-escaped href value (esc_url).
     * @param string $confirm_message  Pre-escaped confirmation text (esc_js).
     * @param string $label            Pre-escaped link label (esc_html__).
     */
    private function renderConfirmLink( string $url, string $confirm_message, string $label ): void {
        echo '<a href="' . $url . '" onclick="return confirm(\'' . $confirm_message . '\');">' . $label . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all three arguments are pre-escaped by the caller via esc_url/esc_js/esc_html__
    }
}
