<?php

declare(strict_types=1);

trait LynxJournal_Admin_Dashboard {

    /**
     * Render the LynxJournal summary dashboard widget.
     *
     * @since 1.0.0
     * @return void
     */
    public function dashboardWidgetContent(): void {
        // Get statistics
        $stats = $this->getPublishStatistics();

        // Get recent unpublished links
        $recent_unpublished = get_posts(array(
            'post_type'      => 'lynx-journal',
            'post_status'    => 'lynxjournal_pending',
            'posts_per_page' => 3,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        ?>
        <div class="lynxjournal-widget-stats">
            <div class="lynxjournal-widget-stat-cell">
                <div class="lynxjournal-widget-stat-value lynxjournal-widget-stat-value--total"><?php echo esc_html(number_format_i18n($stats['total_links'])); ?></div>
                <div class="lynxjournal-widget-stat-label"><?php esc_html_e('Total', 'lynx-journal'); ?></div>
            </div>
            <div class="lynxjournal-widget-stat-cell">
                <div class="lynxjournal-widget-stat-value lynxjournal-widget-stat-value--published"><?php echo esc_html(number_format_i18n($stats['published_links'])); ?></div>
                <div class="lynxjournal-widget-stat-label"><?php esc_html_e('Published', 'lynx-journal'); ?></div>
            </div>
            <div class="lynxjournal-widget-stat-cell">
                <div class="lynxjournal-widget-stat-value lynxjournal-widget-stat-value--unpublished"><?php echo esc_html(number_format_i18n($stats['unpublished_links'])); ?></div>
                <div class="lynxjournal-widget-stat-label"><?php esc_html_e('Unpublished', 'lynx-journal'); ?></div>
            </div>
        </div>

        <?php if (!empty($recent_unpublished)) : ?>
            <div class="lynxjournal-widget-recent">
                <h4 class="lynxjournal-widget-heading"><?php esc_html_e('Recent Unpublished', 'lynx-journal'); ?></h4>
                <ul class="lynxjournal-widget-list">
                    <?php foreach ($recent_unpublished as $link) :
                        $url = get_post_meta($link->ID, '_lynxjournal_url', true);
                    ?>
                        <li class="lynxjournal-widget-item">
                            <div class="lynxjournal-widget-link-title">
                                <?php echo esc_html($link->post_title); ?>
                            </div>
                            <?php if ($url) : ?>
                                <div class="lynxjournal-widget-link-url">
                                    <?php echo esc_html($url); ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="lynxjournal-widget-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=lynxjournal-dashboard')); ?>" class="button button-primary">
                <?php esc_html_e('Go to LynxJournal', 'lynx-journal'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render success/error notices for dashboard actions.
     *
     * @since 1.0.0
     * @param array|null $batch_result Batch publishing result.
     * @param array|null $roundup_result Roundup creation result.
     * @return void
     */
    public function renderDashboardNotices( ?array $batch_result, ?array $roundup_result ): void {
        if ( $batch_result !== null ) {
            if ( $batch_result['success'] > 0 ) {
                /* translators: 1: number of successfully processed links, 2: optional failure message */
                $failed_msg = $batch_result['failed'] > 0 ? sprintf( __( '%d failed.', 'lynx-journal' ), $batch_result['failed'] ) : '';
                echo '<div class="notice notice-success"><p>';
                /* translators: 1: number of successfully processed links, 2: optional failure message */
                printf( esc_html__( 'Successfully processed %1$d link(s). %2$s', 'lynx-journal' ), (int) $batch_result['success'], esc_html( $failed_msg ) );
                echo '</p></div>';
            }
            if ( ! empty( $batch_result['messages'] ) ) {
                echo '<div class="notice notice-error"><p>' . implode( '<br>', array_map( 'esc_html', $batch_result['messages'] ) ) . '</p></div>';
            }
        }
        if ( $roundup_result !== null ) {
            if ( $roundup_result['success'] ) {
                echo '<div class="notice notice-success"><p>' . esc_html( $roundup_result['message'] );
                echo ' <a href="' . esc_url( get_permalink( $roundup_result['post_id'] ) ) . '" target="_blank">' . esc_html__( 'View Post', 'lynx-journal' ) . ' →</a></p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html( $roundup_result['message'] ) . '</p></div>';
            }
        }
    }

    /**
     * Get subtitle text and icon for the unpublished links box.
     *
     * @since 1.0.0
     * @return array Array with text and icon keys for the subtitle.
     */
    private function unpublishedLinksSubtitle(): array {
        $empty    = [ 'text' => '', 'icon' => '' ];
        $schedule = get_option( 'lynxjournal_schedule', null );
        if ( ! $schedule || ! isset( $schedule['mode'] ) ) {
            return $empty;
        }
        $mode = $schedule['mode'];
        if ( in_array( $mode, [ 'daily', 'weekly', 'monthly', 'age' ], true ) ) {
            return $this->getScheduledModeSubtitle();
        }
        return $mode === 'count' ? $this->getCountModeSubtitle( $schedule ) : $empty;
    }

    /**
     * Get subtitle for time-based schedule modes.
     *
     * @since 1.0.0
     * @return array Array with text and icon keys.
     */
    private function getScheduledModeSubtitle(): array {
        $next_ts = wp_next_scheduled( 'lynxjournal_execute_schedule' );
        if ( ! $next_ts ) {
            return [ 'text' => '', 'icon' => '' ];
        }
        $formatted = wp_date(
            get_option( 'date_format' ) . ', ' . get_option( 'time_format' ),
            $next_ts
        );
        return [
            /* translators: %s: formatted next publish datetime */
            'text' => sprintf( __( 'next: %s', 'lynx-journal' ), $formatted ),
            'icon' => 'dashicons-calendar-alt',
        ];
    }

    /**
     * Get subtitle for count-based schedule mode.
     *
     * @since 1.0.0
     * @param array $schedule The schedule option array.
     * @return array Array with text and icon keys.
     */
    private function getCountModeSubtitle( array $schedule ): array {
        $threshold = (int) ( $schedule['trigger']['count'] ?? 10 );
        $stats     = $this->getPublishStatistics();
        $pending   = (int) $stats['unpublished_links'];
        $remaining = max( 0, $threshold - $pending );
        return [
            'text' => sprintf(
                /* translators: 1: remaining links needed, 2: threshold */
                _n( '%1$d out of %2$d left until publish', '%1$d out of %2$d left until publish', $remaining, 'lynx-journal' ),
                $remaining,
                $threshold
            ),
            'icon' => '',
        ];
    }

    /**
     * Render the recent unpublished links dashboard box.
     *
     * @since 1.0.0
     * @param array $recent_links Array of recent unpublished link posts.
     * @return void
     */
    public function renderUnpublishedLinksBox( array $recent_links ): void {
        $subtitle = $this->unpublishedLinksSubtitle();
        ?>
        <div class="postbox" id="lynxjournal-postbox-unpublished">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php esc_html_e( 'Recent Unpublished Links', 'lynx-journal' ); ?>
                    <?php if ( $subtitle['text'] ) : ?>
                        <span class="lynxjournal-box-subtitle">
                            <?php if ( $subtitle['icon'] ) : ?>
                                <span class="dashicons <?php echo esc_attr( $subtitle['icon'] ); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html( $subtitle['text'] ); ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside lynxjournal-box-flush">
                <?php if ( empty( $recent_links ) ) : ?>
                    <p class="lynxjournal-box-empty"><?php esc_html_e( 'No unpublished links at the moment.', 'lynx-journal' ); ?></p>
                <?php else : ?>
                    <?php $this->renderUnpublishedLinksList( $recent_links ); ?>
                    <div class="lynxjournal-box-footer">
                        <a href="<?php echo esc_url( admin_url( self::ADMIN_LINKS_PAGE ) ); ?>" class="button">
                            <?php esc_html_e( 'View All Links', 'lynx-journal' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the list of recent unpublished links.
     *
     * @since 1.0.0
     * @param array $recent_links Array of recent unpublished link posts.
     * @return void
     */
    private function renderUnpublishedLinksList( array $recent_links ): void {
        ?>
        <ul class="lynxjournal-recent-links">
            <?php foreach ( $recent_links as $link ) :
                $url             = get_post_meta( $link->ID, '_lynxjournal_url', true );
                $categories_list = get_the_terms( $link->ID, 'lynxjournal_category' );
                $category_name   = $categories_list && ! is_wp_error( $categories_list ) ? $categories_list[0]->name : '';
            ?>
                <li class="lynxjournal-link-item" data-link-id="<?php echo esc_attr( $link->ID ); ?>">
                    <div class="lynxjournal-link-item-header">
                        <strong class="lynxjournal-link-title"><?php echo esc_html( $link->post_title ); ?></strong>
                    </div>
                    <button class="lynxjournal-delete-btn" title="<?php esc_attr_e( 'Delete link', 'lynx-journal' ); ?>" data-link-id="<?php echo (int) $link->ID; ?>"><span class="dashicons dashicons-trash"></span></button>
                    <?php if ( $url ) : ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="lynxjournal-link-url" target="_blank" rel="noopener">
                            <?php echo esc_html( wp_parse_url( $url, PHP_URL_HOST ) ); ?> ↗
                        </a>
                    <?php endif; ?>
                    <div class="lynxjournal-link-meta">
                        <?php if ( $category_name ) : ?>
                            <span><?php echo esc_html( $category_name ); ?></span>
                        <?php endif; ?>
                        <span class="lynxjournal-date-time" data-timestamp="<?php echo esc_attr( get_the_time( 'U', $link->ID ) ); ?>">
                            <?php echo esc_html( get_the_date( 'M j, Y', $link->ID ) ); ?> <?php echo esc_html( get_the_time( get_option( 'time_format' ), $link->ID ) ); ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Render the recently published links dashboard box.
     *
     * @since 1.0.0
     * @param array $recently_published Array of recently published link posts.
     * @return void
     */
    public function renderRecentlyPublishedBox( array $recently_published ): void {
        ?>
        <div class="postbox" id="lynxjournal-postbox-published">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Recently Published', 'lynx-journal' ); ?></h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside lynxjournal-box-flush">
                <?php if ( empty( $recently_published ) ) : ?>
                    <p class="lynxjournal-box-empty"><?php esc_html_e( 'No published links yet.', 'lynx-journal' ); ?></p>
                <?php else : ?>
                    <?php $this->renderRecentlyPublishedList( $recently_published ); ?>
                    <div class="lynxjournal-box-footer">
                        <a href="<?php echo esc_url( admin_url( self::ADMIN_LINKS_PAGE ) ); ?>">
                            <?php esc_html_e( 'View all links →', 'lynx-journal' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the publish now dashboard box.
     *
     * @since 1.0.0
     * @param int $unpublished_count Number of unpublished links available.
     * @return void
     */
    public function renderPublishBox( int $unpublished_count ): void {
        ?>
        <div class="postbox" id="lynxjournal-postbox-publish">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Publish Now', 'lynx-journal' ); ?></h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside">
                <?php if ( $unpublished_count > 0 ) : ?>
                    <?php
                    printf(
                        '<p>' . wp_kses(
                            /* translators: %d: number of unpublished links */
                            _n( 'You have <strong>%d</strong> unpublished link ready to publish.', 'You have <strong>%d</strong> unpublished links ready to publish.', (int) $unpublished_count, 'lynx-journal' ),
                            array( 'strong' => array() )
                        ) . '</p>',
                        (int) $unpublished_count
                    );
                    ?>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'lynxjournal_create_roundup', 'lynxjournal_roundup_nonce' ); ?>
                        <p>
                            <label for="roundup_title"><strong><?php esc_html_e( 'Post Title', 'lynx-journal' ); ?></strong></label><br>
                            <input type="text" id="roundup_title" name="roundup_title" class="regular-text"
                                value="<?php
                                /* translators: %s is the current date (e.g. "April 15, 2026") */
                                echo esc_attr( sprintf( __( 'Links Roundup - %s', 'lynx-journal' ), wp_date( 'F j, Y' ) ) );
                                ?>">
                        </p>
                        <input type="hidden" name="roundup_as_draft" value="0">
                        <p>
                            <button type="submit" name="lynxjournal_create_roundup" class="button button-primary"><?php esc_html_e( 'Publish', 'lynx-journal' ); ?></button>
                            &nbsp;
                            <button type="submit" name="lynxjournal_create_roundup" value="1" onclick="this.form.elements['roundup_as_draft'].value='1';" class="button"><?php esc_html_e( 'Save as Draft', 'lynx-journal' ); ?></button>
                        </p>
                    </form>
                <?php else : ?>
                    <p class="lynxjournal-muted"><?php esc_html_e( 'No pending links to publish. Add links first, then come back here to publish a roundup.', 'lynx-journal' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the recently published links list.
     *
     * @since 1.0.0
     * @param array $recently_published Array of recently published link posts.
     * @return void
     */
    private function renderRecentlyPublishedList( array $recently_published ): void {
        ?>
        <ul class="lynxjournal-recent-links">
            <?php foreach ( $recently_published as $link ) :
                $meta = $this->getRecentlyPublishedLinkMetadata( $link );
            ?>
                <li class="lynxjournal-link-item">
                    <div class="lynxjournal-link-item-header">
                        <strong class="lynxjournal-link-title"><?php echo esc_html( $link->post_title ); ?></strong>
                        <?php $this->renderPublishedLinkBadge( $meta['is_draft'] ); ?>
                    </div>
                    <?php if ( $meta['published_post_id'] ) : ?>
                        <a href="<?php echo esc_url( $meta['is_draft'] ? get_edit_post_link( $meta['published_post_id'] ) : get_permalink( $meta['published_post_id'] ) ); ?>" class="lynxjournal-link-url" target="_blank" rel="noopener">
                            <?php echo $meta['is_draft'] ? esc_html__( 'View Draft', 'lynx-journal' ) : esc_html__( 'View Post', 'lynx-journal' ); ?> ↗
                        </a>
                    <?php endif; ?>
                    <div class="lynxjournal-link-meta">
                        <?php if ( $meta['category_name'] ) : ?>
                            <span><?php echo esc_html( $meta['category_name'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( $meta['published_date'] ) : ?>
                            <span class="lynxjournal-date-time" data-timestamp="<?php echo esc_attr( (string) mysql2date( 'U', $meta['published_date'] ) ); ?>">
                                <?php echo esc_html( mysql2date( get_option( 'date_format' ), $meta['published_date'] ) ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Render the published status badge for a link.
     *
     * @since 1.0.0
     * @param bool $is_draft Whether the link was published as a draft.
     * @return void
     */
    private function renderPublishedLinkBadge( bool $is_draft ): void {
        if ( $is_draft ) {
            echo '<span class="lynxjournal-status-badge lynxjournal-status-draft">' . esc_html__( 'Draft', 'lynx-journal' ) . '</span>';
        }
    }

    /**
     * Get metadata for a recently published link.
     *
     * @since 1.0.0
     * @param \WP_Post $link The link post object.
     * @return array Metadata array with published post ID, status, date, category, and draft status.
     */
    private function getRecentlyPublishedLinkMetadata( \WP_Post $link ): array {
        $publish_status = get_post_meta( $link->ID, '_lynxjournal_publish_status', true );
        $categories_list = get_the_terms( $link->ID, 'lynxjournal_category' );
        return [
            'published_post_id' => get_post_meta( $link->ID, '_lynxjournal_published_post_id', true ),
            'publish_status' => $publish_status,
            'published_date' => get_post_meta( $link->ID, '_lynxjournal_published_date', true ),
            'category_name' => $categories_list && ! is_wp_error( $categories_list ) ? $categories_list[0]->name : '',
            'is_draft' => $publish_status === 'draft',
        ];
    }

    /**
     * Render the quick add link dashboard box.
     *
     * @since 1.0.0
     * @param bool $quick_add_success Whether the quick add was just successful.
     * @return void
     */
    public function renderQuickAddBox( bool $quick_add_success ): void {
        $categories = $this->getCachedCategories();
        $has_categories = ! empty( $categories );
        ?>
        <div class="postbox" id="lynxjournal-postbox-quickadd">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Quick Add', 'lynx-journal' ); ?></h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside">
                <?php if ( $quick_add_success ) : ?>
                    <div class="notice notice-success inline"><p><?php esc_html_e( 'Link added successfully!', 'lynx-journal' ); ?></p></div>
                <?php endif; ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'lynxjournal_quick_add_link', 'lynxjournal_quick_nonce' ); ?>
                    <p>
                        <label for="quick_title"><strong><?php esc_html_e( 'Title', 'lynx-journal' ); ?> *</strong></label><br>
                        <input type="text" id="quick_title" name="quick_title" class="regular-text"
                            placeholder="<?php esc_attr_e( 'Enter link title', 'lynx-journal' ); ?>" required>
                    </p>
                    <p>
                        <label for="quick_url"><strong><?php esc_html_e( 'URL', 'lynx-journal' ); ?> *</strong></label><br>
                        <input type="url" id="quick_url" name="quick_url" class="regular-text"
                            placeholder="https://example.com" required>
                    </p>
                    <?php if ( $has_categories ) : ?>
                    <p>
                        <label for="quick_category"><strong><?php esc_html_e( 'Category', 'lynx-journal' ); ?> *</strong></label><br>
                        <select id="quick_category" name="quick_category" class="regular-text" required>
                            <option value=""><?php esc_html_e( '— Select category —', 'lynx-journal' ); ?></option>
                            <?php foreach ( $categories as $term ) : ?>
                                <option value="<?php echo (int) $term->term_id; ?>">
                                    <?php echo esc_html( $term->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <?php endif; ?>
                    <p>
                        <button type="submit" name="lynxjournal_quick_add" class="button button-primary"><?php esc_html_e( 'Add Link', 'lynx-journal' ); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render the schedule status bar.
     *
     * @since 1.0.0
     * @return void
     */
    private function renderScheduleStatusBar(): void {
        $schedule = get_option( 'lynxjournal_schedule', null );
        if ( $schedule === null ) {
            return;
        }
        $next_ts = wp_next_scheduled( 'lynxjournal_execute_schedule' );
        $schedule_url = esc_url( admin_url( 'admin.php?page=lynxjournal-schedule' ) );
        ?>
        <div class="lynxjournal-schedule-status">
            <?php if ( $next_ts ) : ?>
                <span class="dashicons dashicons-calendar-alt lynxjournal-schedule-status-icon"></span>
                <span class="lynxjournal-schedule-status-text">
                    <?php
                    printf(
                        /* translators: %s: formatted next run datetime */
                        esc_html__( 'Next run: %s', 'lynx-journal' ),
                        esc_html( wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $next_ts ) )
                    );
                    ?>
                </span>
            <?php else : ?>
                <span class="dashicons dashicons-info lynxjournal-schedule-status-icon lynxjournal-schedule-status-icon--muted"></span>
                <span class="lynxjournal-schedule-status-text lynxjournal-schedule-status-text--muted">
                    <?php esc_html_e( 'No automatic schedule active.', 'lynx-journal' ); ?>
                </span>
            <?php endif; ?>
            <a href="<?php echo esc_url( $schedule_url ); ?>" class="lynxjournal-schedule-status-link">
                <?php esc_html_e( 'Schedule settings →', 'lynx-journal' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render dashboard JavaScript (hook point for subclasses).
     *
     * @since 1.0.0
     * @return void
     */
    public function renderDashboardJs(): void {}

    /**
     * Render the main LynxJournal dashboard page.
     *
     * @since 1.0.0
     * @return void
     */
    public function dashboardPage(): void {
        $d = $this->gatherDashboardData();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Overview', 'lynx-journal' ); ?></h1>

            <?php $this->renderDashboardNotices( $d['batch_result'], $d['roundup_result'] ); ?>

            <?php $this->renderDashboardStatsSection( $d ); ?>

            <!-- Main Content -->
            <div class="metabox-holder lynxjournal-dashboard">
                <div id="lynxjournal-postbox-container-1" class="postbox-container meta-box-sortables">
                    <?php
                    $this->renderUnpublishedLinksBox( $d['recent_links'] );
                    $this->renderRecentlyPublishedBox( $d['recently_published'] );
                    ?>
                </div><!-- #lynxjournal-postbox-container-1 -->

                <div id="lynxjournal-postbox-container-2" class="postbox-container meta-box-sortables">
                    <?php
                    $this->renderQuickAddBox( $d['quick_add_success'] );
                    $this->renderPublishBox( $d['unpublished_links'] );
                    ?>
                </div><!-- #lynxjournal-postbox-container-2 -->
            </div><!-- .lynxjournal-dashboard -->
        </div>

        <?php $this->renderDashboardJs(); ?>
        <?php
    }

    /**
     * Gather all data needed to render the dashboard page: pending-request
     * results, link/category statistics, and recent links.
     *
     * @since 1.0.0
     * @return array{
     *     batch_result: mixed,
     *     roundup_result: mixed,
     *     quick_add_success: mixed,
     *     total_links: int,
     *     published_links: int,
     *     unpublished_links: int,
     *     total_categories: int,
     *     recent_links: \WP_Post[],
     *     recently_published: \WP_Post[],
     *     all_links_url: string,
     *     categories_url: string,
     * }
     */
    private function gatherDashboardData(): array {
        $batch_result      = $this->handleBatchPublishRequest();
        $roundup_result    = $this->handleRoundupRequest();
        $quick_add_success = $this->handleQuickAddRequest();

        $publish_stats    = $this->getPublishStatistics();
        $total_categories = count( $this->getCachedCategories() );

        $recent_links = get_posts( array(
            'post_type'      => 'lynx-journal',
            'post_status'    => 'lynxjournal_pending',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $recently_published = get_posts( array(
            'post_type'      => 'lynx-journal',
            'post_status'    => array( 'lynxjournal_pub', 'lynxjournal_draft' ),
            'posts_per_page' => 5,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        return array(
            'batch_result'       => $batch_result,
            'roundup_result'     => $roundup_result,
            'quick_add_success'  => $quick_add_success,
            'total_links'        => $publish_stats['total_links'],
            'published_links'    => $publish_stats['published_links'],
            'unpublished_links'  => $publish_stats['unpublished_links'],
            'total_categories'   => $total_categories,
            'recent_links'       => $recent_links,
            'recently_published' => $recently_published,
            'all_links_url'      => esc_url( admin_url( self::ADMIN_LINKS_PAGE ) ),
            'categories_url'     => esc_url( admin_url( 'edit-tags.php?taxonomy=lynxjournal_category&post_type=lynx-journal' ) ),
        );
    }

    /**
     * Render the "no categories" warning, onboarding prompt, and stats grid.
     *
     * @since 1.0.0
     * @param array $d Dashboard data, as returned by gatherDashboardData().
     * @return void
     */
    private function renderDashboardStatsSection( array $d ): void {
        ?>
        <?php if ( $d['total_categories'] === 0 ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: link to the category admin page */
                    esc_html__( 'No categories defined yet. %s', 'lynx-journal' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=lynxjournal-categories' ) ) . '">' . esc_html__( 'Add a category', 'lynx-journal' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if ( $d['total_links'] === 0 ) : ?>
        <!-- Onboarding -->
        <div class="lynxjournal-onboarding">
            <span class="dashicons dashicons-admin-links lynxjournal-onboarding-icon"></span>
            <p><strong><?php esc_html_e( 'No links yet.', 'lynx-journal' ); ?></strong>
               <?php esc_html_e( 'Start by adding your first link.', 'lynx-journal' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=lynxjournal-add' ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'Add your first link →', 'lynx-journal' ); ?>
            </a>
        </div>
        <?php else : ?>
        <!-- Statistics -->
        <div class="lynxjournal-stats-grid">
            <a href="<?php echo esc_url( $d['all_links_url'] ); ?>" class="lynxjournal-stat-card lynxjournal-stat-card--link">
                <span class="dashicons dashicons-admin-links lynxjournal-stat-icon"></span>
                <div><span class="lynxjournal-stat-value" id="lynxjournal-stat-total"><?php echo esc_html( number_format_i18n( $d['total_links'] ) ); ?></span>
                <span class="lynxjournal-stat-label"><?php esc_html_e( 'Total Links', 'lynx-journal' ); ?></span></div>
            </a>
            <a href="<?php echo esc_url( $d['categories_url'] ); ?>" class="lynxjournal-stat-card lynxjournal-stat-card--link">
                <span class="dashicons dashicons-category lynxjournal-stat-icon"></span>
                <div><span class="lynxjournal-stat-value"><?php echo esc_html( number_format_i18n( $d['total_categories'] ) ); ?></span>
                <span class="lynxjournal-stat-label"><?php echo esc_html( _n( 'Category', 'Categories', $d['total_categories'], 'lynx-journal' ) ); ?></span></div>
            </a>
            <a href="<?php echo esc_url( $d['all_links_url'] ); ?>" class="lynxjournal-stat-card lynxjournal-stat-card--link">
                <span class="dashicons dashicons-yes-alt lynxjournal-stat-icon"></span>
                <div><span class="lynxjournal-stat-value"><?php echo esc_html( number_format_i18n( $d['published_links'] ) ); ?></span>
                <span class="lynxjournal-stat-label"><?php esc_html_e( 'Published', 'lynx-journal' ); ?></span></div>
            </a>
            <a href="<?php echo esc_url( $d['all_links_url'] ); ?>" class="lynxjournal-stat-card lynxjournal-stat-card--link">
                <span class="dashicons dashicons-clock lynxjournal-stat-icon"></span>
                <div><span class="lynxjournal-stat-value" id="lynxjournal-stat-unpublished"><?php echo esc_html( number_format_i18n( $d['unpublished_links'] ) ); ?></span>
                <span class="lynxjournal-stat-label"><?php esc_html_e( 'Unpublished', 'lynx-journal' ); ?></span></div>
            </a>
        </div>
        <?php endif; ?>
        <?php
    }
}
