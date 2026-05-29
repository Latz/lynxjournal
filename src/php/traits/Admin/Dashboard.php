<?php

declare(strict_types=1);

trait LinkDigest_Admin_Dashboard {

    /**
     * Render the LinkDigest summary dashboard widget.
     *
     * @since 1.0.0
     * @return void
     */
    public function dashboardWidgetContent(): void {
        // Get statistics
        $stats = $this->getPublishStatistics();

        // Get recent unpublished links
        $recent_unpublished = get_posts(array(
            'post_type'      => 'linkdigest',
            'post_status'    => 'linkdigest_pending',
            'posts_per_page' => 3,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        ?>
        <div class="linkdigest-widget-stats">
            <div class="linkdigest-widget-stat-cell">
                <div class="linkdigest-widget-stat-value linkdigest-widget-stat-value--total"><?php echo esc_html(number_format_i18n($stats['total_links'])); ?></div>
                <div class="linkdigest-widget-stat-label"><?php esc_html_e('Total', 'linkdigest'); ?></div>
            </div>
            <div class="linkdigest-widget-stat-cell">
                <div class="linkdigest-widget-stat-value linkdigest-widget-stat-value--published"><?php echo esc_html(number_format_i18n($stats['published_links'])); ?></div>
                <div class="linkdigest-widget-stat-label"><?php esc_html_e('Published', 'linkdigest'); ?></div>
            </div>
            <div class="linkdigest-widget-stat-cell">
                <div class="linkdigest-widget-stat-value linkdigest-widget-stat-value--unpublished"><?php echo esc_html(number_format_i18n($stats['unpublished_links'])); ?></div>
                <div class="linkdigest-widget-stat-label"><?php esc_html_e('Unpublished', 'linkdigest'); ?></div>
            </div>
        </div>

        <?php if (!empty($recent_unpublished)) : ?>
            <div class="linkdigest-widget-recent">
                <h4 class="linkdigest-widget-heading"><?php esc_html_e('Recent Unpublished', 'linkdigest'); ?></h4>
                <ul class="linkdigest-widget-list">
                    <?php foreach ($recent_unpublished as $link) :
                        $url = get_post_meta($link->ID, '_linkdigest_url', true);
                    ?>
                        <li class="linkdigest-widget-item">
                            <div class="linkdigest-widget-link-title">
                                <?php echo esc_html($link->post_title); ?>
                            </div>
                            <?php if ($url) : ?>
                                <div class="linkdigest-widget-link-url">
                                    <?php echo esc_html($url); ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="linkdigest-widget-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=linkdigest-dashboard')); ?>" class="button button-primary">
                <?php esc_html_e('Go to LinkDigest', 'linkdigest'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Get all unpublished link IDs in oldest-first order.
     *
     * @since 1.0.0
     * @return array Array of unpublished link post IDs.
     */
    public function getUnpublishedLinkIds(): array {
        return get_posts( array(
            'post_type'      => 'linkdigest',
            'post_status'    => 'linkdigest_pending',
            'posts_per_page' => self::UNPUBLISHED_PAGE_SIZE,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'ASC', // oldest first: used by age-mode trigger and batch ordering
        ) );
    }

    /**
     * Handle batch publish form submission.
     *
     * @since 1.0.0
     * @return array|null Batch result or null if no request was made.
     */
    public function handleBatchPublishRequest(): ?array {
        if ( ! isset( $_POST['linkdigest_batch_publish'] ) ) {
            return null;
        }
        $nonce = isset( $_POST['linkdigest_batch_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['linkdigest_batch_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'linkdigest_batch_publish' ) ) {
            return null;
        }
        if ( ! current_user_can( 'publish_posts' ) ) {
            return null;
        }
        $as_draft = isset( $_POST['publish_as_draft'] ) && sanitize_text_field( wp_unslash( $_POST['publish_as_draft'] ) ) === '1';
        return $this->batchPublishLinks( $this->getUnpublishedLinkIds(), $as_draft );
    }

    /**
     * Handle roundup creation form submission.
     *
     * @since 1.0.0
     * @return array|null Roundup result or null if no request was made.
     */
    public function handleRoundupRequest(): ?array {
        if ( ! isset( $_POST['linkdigest_create_roundup'] ) ) {
            return null;
        }
        $nonce = isset( $_POST['linkdigest_roundup_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['linkdigest_roundup_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'linkdigest_create_roundup' ) ) {
            return null;
        }
        if ( ! current_user_can( 'publish_posts' ) ) {
            return null;
        }
        $roundup_title = isset( $_POST['roundup_title'] ) ? sanitize_text_field( wp_unslash( $_POST['roundup_title'] ) ) : '';
        $as_draft      = isset( $_POST['roundup_as_draft'] ) && sanitize_text_field( wp_unslash( $_POST['roundup_as_draft'] ) ) === '1';
        return $this->createRoundupPost( $this->getUnpublishedLinkIds(), $roundup_title, $as_draft );
    }

    /**
     * Handle quick add link form submission.
     *
     * @since 1.0.0
     * @return bool True if link was added successfully.
     */
    public function handleQuickAddRequest(): bool {
        if ( ! isset( $_POST['linkdigest_quick_add'] ) ) {
            return false;
        }
        $nonce    = isset( $_POST['linkdigest_quick_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['linkdigest_quick_nonce'] ) ) : '';
        $title    = isset( $_POST['quick_title'] )    ? sanitize_text_field( wp_unslash( $_POST['quick_title'] ) )    : '';
        $url      = isset( $_POST['quick_url'] )      ? esc_url_raw( wp_unslash( $_POST['quick_url'] ) )              : '';
        $category = isset( $_POST['quick_category'] ) ? (int) $_POST['quick_category']                                : 0;
        if ( ! wp_verify_nonce( $nonce, 'linkdigest_quick_add_link' ) || empty( $title ) ) {
            return false;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }
        $post_id = wp_insert_post( array(
            'post_title'  => $title,
            'post_type'   => 'linkdigest',
            'post_status' => 'linkdigest_pending',
        ) );
        if ( $post_id ) {
            if ( ! empty( $url ) ) {
                update_post_meta( $post_id, '_linkdigest_url', $url );
            }
            if ( $category > 0 ) {
                wp_set_post_terms( $post_id, array( $category ), 'linkdigest_category' );
            }
        }
        return (bool) $post_id;
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
                $failed_msg = $batch_result['failed'] > 0 ? sprintf( __( '%d failed.', 'linkdigest' ), $batch_result['failed'] ) : '';
                echo '<div class="notice notice-success"><p>';
                /* translators: 1: number of successfully processed links, 2: optional failure message */
                printf( esc_html__( 'Successfully processed %1$d link(s). %2$s', 'linkdigest' ), (int) $batch_result['success'], esc_html( $failed_msg ) );
                echo '</p></div>';
            }
            if ( ! empty( $batch_result['messages'] ) ) {
                echo '<div class="notice notice-error"><p>' . implode( '<br>', array_map( 'esc_html', $batch_result['messages'] ) ) . '</p></div>';
            }
        }
        if ( $roundup_result !== null ) {
            if ( $roundup_result['success'] ) {
                echo '<div class="notice notice-success"><p>' . esc_html( $roundup_result['message'] );
                echo ' <a href="' . esc_url( get_permalink( $roundup_result['post_id'] ) ) . '" target="_blank">' . esc_html__( 'View Post', 'linkdigest' ) . ' →</a></p></div>';
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
        $schedule = get_option( 'linkdigest_schedule', null );
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
        $next_ts = wp_next_scheduled( 'linkdigest_execute_schedule' );
        if ( ! $next_ts ) {
            return [ 'text' => '', 'icon' => '' ];
        }
        $formatted = wp_date(
            get_option( 'date_format' ) . ', ' . get_option( 'time_format' ),
            $next_ts
        );
        return [
            /* translators: %s: formatted next publish datetime */
            'text' => sprintf( __( 'next: %s', 'linkdigest' ), $formatted ),
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
                _n( '%1$d out of %2$d left until publish', '%1$d out of %2$d left until publish', $remaining, 'linkdigest' ),
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
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <?php esc_html_e( 'Recent Unpublished Links', 'linkdigest' ); ?>
                    <?php if ( $subtitle['text'] ) : ?>
                        <span class="linkdigest-box-subtitle">
                            <?php if ( $subtitle['icon'] ) : ?>
                                <span class="dashicons <?php echo esc_attr( $subtitle['icon'] ); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html( $subtitle['text'] ); ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside linkdigest-box-flush">
                <?php if ( empty( $recent_links ) ) : ?>
                    <p class="linkdigest-box-empty"><?php esc_html_e( 'No unpublished links at the moment.', 'linkdigest' ); ?></p>
                <?php else : ?>
                    <?php $this->renderUnpublishedLinksList( $recent_links ); ?>
                    <div class="linkdigest-box-footer">
                        <a href="<?php echo esc_url( admin_url( self::ADMIN_LINKS_PAGE ) ); ?>" class="button">
                            <?php esc_html_e( 'View All Links', 'linkdigest' ); ?>
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
        <ul class="linkdigest-recent-links">
            <?php foreach ( $recent_links as $link ) :
                $url             = get_post_meta( $link->ID, '_linkdigest_url', true );
                $categories_list = get_the_terms( $link->ID, 'linkdigest_category' );
                $category_name   = $categories_list && ! is_wp_error( $categories_list ) ? $categories_list[0]->name : '';
            ?>
                <li class="linkdigest-link-item" data-link-id="<?php echo esc_attr( $link->ID ); ?>">
                    <div class="linkdigest-link-item-header">
                        <strong class="linkdigest-link-title"><?php echo esc_html( $link->post_title ); ?></strong>
                    </div>
                    <button class="linkdigest-delete-btn" title="<?php esc_attr_e( 'Delete link', 'linkdigest' ); ?>" data-link-id="<?php echo (int) $link->ID; ?>"><span class="dashicons dashicons-trash"></span></button>
                    <?php if ( $url ) : ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="linkdigest-link-url" target="_blank" rel="noopener">
                            <?php echo esc_html( wp_parse_url( $url, PHP_URL_HOST ) ); ?> ↗
                        </a>
                    <?php endif; ?>
                    <div class="linkdigest-link-meta">
                        <?php if ( $category_name ) : ?>
                            <span><?php echo esc_html( $category_name ); ?></span>
                        <?php endif; ?>
                        <span class="linkdigest-date-time" data-timestamp="<?php echo esc_attr( get_the_time( 'U', $link->ID ) ); ?>">
                            <?php echo esc_html( get_the_date( 'M j, Y', $link->ID ) ); ?> <?php echo esc_html( get_the_time( 'g:i a', $link->ID ) ); ?>
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
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Recently Published', 'linkdigest' ); ?></h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside linkdigest-box-flush">
                <?php if ( empty( $recently_published ) ) : ?>
                    <p class="linkdigest-box-empty"><?php esc_html_e( 'No published links yet.', 'linkdigest' ); ?></p>
                <?php else : ?>
                    <?php $this->renderRecentlyPublishedList( $recently_published ); ?>
                    <div class="linkdigest-box-footer">
                        <a href="<?php echo esc_url( admin_url( self::ADMIN_LINKS_PAGE ) ); ?>">
                            <?php esc_html_e( 'View all links →', 'linkdigest' ); ?>
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
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Publish Now', 'linkdigest' ); ?></h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside">
                <?php if ( $unpublished_count > 0 ) : ?>
                    <?php
                    printf(
                        '<p>' . wp_kses(
                            /* translators: %d: number of unpublished links */
                            _n( 'You have <strong>%d</strong> unpublished link ready to publish.', 'You have <strong>%d</strong> unpublished links ready to publish.', (int) $unpublished_count, 'linkdigest' ),
                            array( 'strong' => array() )
                        ) . '</p>',
                        (int) $unpublished_count
                    );
                    ?>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'linkdigest_create_roundup', 'linkdigest_roundup_nonce' ); ?>
                        <p>
                            <label for="roundup_title"><strong><?php esc_html_e( 'Post Title', 'linkdigest' ); ?></strong></label><br>
                            <input type="text" id="roundup_title" name="roundup_title" class="regular-text"
                                value="<?php
                                /* translators: %s is the current date (e.g. "April 15, 2026") */
                                echo esc_attr( sprintf( __( 'Links Roundup - %s', 'linkdigest' ), gmdate( 'F j, Y' ) ) );
                                ?>">
                        </p>
                        <input type="hidden" name="roundup_as_draft" value="0">
                        <p>
                            <button type="submit" name="linkdigest_create_roundup" class="button button-primary"><?php esc_html_e( 'Publish', 'linkdigest' ); ?></button>
                            &nbsp;
                            <button type="submit" name="linkdigest_create_roundup" value="1" onclick="this.form.elements['roundup_as_draft'].value='1';" class="button"><?php esc_html_e( 'Save as Draft', 'linkdigest' ); ?></button>
                        </p>
                    </form>
                <?php else : ?>
                    <p class="linkdigest-muted"><?php esc_html_e( 'No pending links to publish. Add links first, then come back here to publish a roundup.', 'linkdigest' ); ?></p>
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
        <ul class="linkdigest-recent-links">
            <?php foreach ( $recently_published as $link ) :
                $meta = $this->getRecentlyPublishedLinkMetadata( $link );
            ?>
                <li class="linkdigest-link-item">
                    <div class="linkdigest-link-item-header">
                        <strong class="linkdigest-link-title"><?php echo esc_html( $link->post_title ); ?></strong>
                        <?php $this->renderPublishedLinkBadge( $meta['is_draft'] ); ?>
                    </div>
                    <?php if ( $meta['published_post_id'] ) : ?>
                        <a href="<?php echo esc_url( $meta['is_draft'] ? get_edit_post_link( $meta['published_post_id'] ) : get_permalink( $meta['published_post_id'] ) ); ?>" class="linkdigest-link-url" target="_blank" rel="noopener">
                            <?php echo $meta['is_draft'] ? esc_html__( 'View Draft', 'linkdigest' ) : esc_html__( 'View Post', 'linkdigest' ); ?> ↗
                        </a>
                    <?php endif; ?>
                    <div class="linkdigest-link-meta">
                        <?php if ( $meta['category_name'] ) : ?>
                            <span><?php echo esc_html( $meta['category_name'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( $meta['published_date'] ) : ?>
                            <span><?php echo esc_html( mysql2date( 'M j, Y', $meta['published_date'] ) ); ?></span>
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
            echo '<span class="linkdigest-status-badge linkdigest-status-draft">' . esc_html__( 'Draft', 'linkdigest' ) . '</span>';
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
        $publish_status = get_post_meta( $link->ID, '_linkdigest_publish_status', true );
        $categories_list = get_the_terms( $link->ID, 'linkdigest_category' );
        return [
            'published_post_id' => get_post_meta( $link->ID, '_linkdigest_published_post_id', true ),
            'publish_status' => $publish_status,
            'published_date' => get_post_meta( $link->ID, '_linkdigest_published_date', true ),
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
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Quick Add', 'linkdigest' ); ?></h2>
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
            <div class="inside">
                <?php if ( $quick_add_success ) : ?>
                    <div class="notice notice-success inline"><p><?php esc_html_e( 'Link added successfully!', 'linkdigest' ); ?></p></div>
                <?php endif; ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'linkdigest_quick_add_link', 'linkdigest_quick_nonce' ); ?>
                    <p>
                        <label for="quick_title"><strong><?php esc_html_e( 'Title', 'linkdigest' ); ?> *</strong></label><br>
                        <input type="text" id="quick_title" name="quick_title" class="regular-text"
                            placeholder="<?php esc_attr_e( 'Enter link title', 'linkdigest' ); ?>" required>
                    </p>
                    <p>
                        <label for="quick_url">
                            <strong><?php esc_html_e( 'URL', 'linkdigest' ); ?></strong>
                            <span class="linkdigest-optional"><?php esc_html_e( '(optional)', 'linkdigest' ); ?></span>
                        </label><br>
                        <input type="url" id="quick_url" name="quick_url" class="regular-text"
                            placeholder="https://example.com">
                    </p>
                    <?php if ( $has_categories ) : ?>
                    <p>
                        <label for="quick_category">
                            <strong><?php esc_html_e( 'Category', 'linkdigest' ); ?></strong>
                            <span class="linkdigest-optional"><?php esc_html_e( '(optional)', 'linkdigest' ); ?></span>
                        </label><br>
                        <select id="quick_category" name="quick_category" class="regular-text">
                            <option value=""><?php esc_html_e( '— No category —', 'linkdigest' ); ?></option>
                            <?php foreach ( $categories as $term ) : ?>
                                <option value="<?php echo (int) $term->term_id; ?>">
                                    <?php echo esc_html( $term->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <?php endif; ?>
                    <p>
                        <button type="submit" name="linkdigest_quick_add" class="button button-primary"><?php esc_html_e( 'Add Link', 'linkdigest' ); ?></button>
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
        $schedule = get_option( 'linkdigest_schedule', null );
        if ( $schedule === null ) {
            return;
        }
        $next_ts = wp_next_scheduled( 'linkdigest_execute_schedule' );
        $schedule_url = esc_url( admin_url( 'admin.php?page=linkdigest-schedule' ) );
        ?>
        <div class="linkdigest-schedule-status">
            <?php if ( $next_ts ) : ?>
                <span class="dashicons dashicons-calendar-alt linkdigest-schedule-status-icon"></span>
                <span class="linkdigest-schedule-status-text">
                    <?php
                    printf(
                        /* translators: %s: formatted next run datetime */
                        esc_html__( 'Next run: %s', 'linkdigest' ),
                        esc_html( wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $next_ts ) )
                    );
                    ?>
                </span>
            <?php else : ?>
                <span class="dashicons dashicons-info linkdigest-schedule-status-icon linkdigest-schedule-status-icon--muted"></span>
                <span class="linkdigest-schedule-status-text linkdigest-schedule-status-text--muted">
                    <?php esc_html_e( 'No automatic schedule active.', 'linkdigest' ); ?>
                </span>
            <?php endif; ?>
            <a href="<?php echo esc_url( $schedule_url ); ?>" class="linkdigest-schedule-status-link">
                <?php esc_html_e( 'Schedule settings →', 'linkdigest' ); ?>
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
     * Render the main LinkDigest dashboard page.
     *
     * @since 1.0.0
     * @return void
     */
    public function dashboardPage(): void {
        $batch_result      = $this->handleBatchPublishRequest();
        $roundup_result    = $this->handleRoundupRequest();
        $quick_add_success = $this->handleQuickAddRequest();

        $publish_stats     = $this->getPublishStatistics();
        $total_links       = $publish_stats['total_links'];
        $published_links   = $publish_stats['published_links'];
        $unpublished_links = $publish_stats['unpublished_links'];
        $total_categories  = count( $this->getCachedCategories() );

        $recent_links = get_posts( array(
            'post_type'      => 'linkdigest',
            'post_status'    => 'linkdigest_pending',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $recently_published = get_posts( array(
            'post_type'      => 'linkdigest',
            'post_status'    => array( 'linkdigest_published', 'linkdigest_draft' ),
            'posts_per_page' => 5,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        $all_links_url  = esc_url( admin_url( self::ADMIN_LINKS_PAGE ) );
        $categories_url = esc_url( admin_url( 'edit-tags.php?taxonomy=linkdigest_category&post_type=linkdigest' ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Overview', 'linkdigest' ); ?></h1>

            <?php $this->renderDashboardNotices( $batch_result, $roundup_result ); ?>

            <?php if ( $total_links === 0 ) : ?>
            <!-- Onboarding -->
            <div class="linkdigest-onboarding">
                <span class="dashicons dashicons-admin-links linkdigest-onboarding-icon"></span>
                <p><strong><?php esc_html_e( 'No links yet.', 'linkdigest' ); ?></strong>
                   <?php esc_html_e( 'Start by adding your first link.', 'linkdigest' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=linkdigest-add' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Add your first link →', 'linkdigest' ); ?>
                </a>
            </div>
            <?php else : ?>
            <!-- Statistics -->
            <div class="linkdigest-stats-grid">
                <a href="<?php echo esc_url( $all_links_url ); ?>" class="linkdigest-stat-card linkdigest-stat-card--link">
                    <span class="dashicons dashicons-admin-links linkdigest-stat-icon"></span>
                    <div><span class="linkdigest-stat-value" id="linkdigest-stat-total"><?php echo esc_html( number_format_i18n( $total_links ) ); ?></span>
                    <span class="linkdigest-stat-label"><?php esc_html_e( 'Total Links', 'linkdigest' ); ?></span></div>
                </a>
                <a href="<?php echo esc_url( $categories_url ); ?>" class="linkdigest-stat-card linkdigest-stat-card--link">
                    <span class="dashicons dashicons-category linkdigest-stat-icon"></span>
                    <div><span class="linkdigest-stat-value"><?php echo esc_html( number_format_i18n( $total_categories ) ); ?></span>
                    <span class="linkdigest-stat-label"><?php esc_html_e( 'Categories', 'linkdigest' ); ?></span></div>
                </a>
                <a href="<?php echo esc_url( $all_links_url ); ?>" class="linkdigest-stat-card linkdigest-stat-card--link">
                    <span class="dashicons dashicons-yes-alt linkdigest-stat-icon"></span>
                    <div><span class="linkdigest-stat-value"><?php echo esc_html( number_format_i18n( $published_links ) ); ?></span>
                    <span class="linkdigest-stat-label"><?php esc_html_e( 'Published', 'linkdigest' ); ?></span></div>
                </a>
                <a href="<?php echo esc_url( $all_links_url ); ?>" class="linkdigest-stat-card linkdigest-stat-card--link">
                    <span class="dashicons dashicons-clock linkdigest-stat-icon"></span>
                    <div><span class="linkdigest-stat-value" id="linkdigest-stat-unpublished"><?php echo esc_html( number_format_i18n( $unpublished_links ) ); ?></span>
                    <span class="linkdigest-stat-label"><?php esc_html_e( 'Unpublished', 'linkdigest' ); ?></span></div>
                </a>
            </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="metabox-holder linkdigest-dashboard">
                <div id="linkdigest-postbox-container-1" class="postbox-container meta-box-sortables">
                    <?php
                    $this->renderUnpublishedLinksBox( $recent_links );
                    $this->renderRecentlyPublishedBox( $recently_published );
                    ?>
                </div><!-- #linkdigest-postbox-container-1 -->

                <div id="linkdigest-postbox-container-2" class="postbox-container meta-box-sortables">
                    <?php
                    $this->renderQuickAddBox( $quick_add_success );
                    $this->renderPublishBox( $unpublished_links );
                    ?>
                </div><!-- #linkdigest-postbox-container-2 -->
            </div><!-- .linkdigest-dashboard -->
        </div>

        <?php $this->renderDashboardJs(); ?>
        <?php
    }
}
