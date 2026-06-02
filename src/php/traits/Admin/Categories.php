<?php

declare(strict_types=1);

trait LinkDigest_Admin_Categories {

    // -------------------------------------------------------------------------
    // Page entry point
    // -------------------------------------------------------------------------

    /**
     * Render the Link Categories admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function categoriesPage(): void {
        $notice = null;

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) === 'POST' ) {
            $nonce = isset( $_POST['linkdigest_cat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['linkdigest_cat_nonce'] ) ) : '';
            if ( isset( $_POST['linkdigest_add_category'] ) && wp_verify_nonce( $nonce, 'linkdigest_add_category' ) ) {
                $notice = $this->buildAddCategoryNotice();
            } elseif ( isset( $_POST['linkdigest_delete_category'] ) && wp_verify_nonce( $nonce, 'linkdigest_delete_category' ) ) {
                $notice = $this->buildDeleteCategoryNotice();
            }
        }

        $terms  = $this->getCachedCategories();
        $counts = $this->getCategoryLinkCounts();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Link Categories', 'linkdigest' ); ?></h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] === 'success' ? 'success' : 'error' ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['msg'] ); ?></p>
                </div>
            <?php endif; ?>

            <div class="metabox-holder linkdigest-dashboard">
                <div id="linkdigest-postbox-container-1" class="postbox-container">
                    <?php $this->renderCategoriesTable( $terms, $counts ); ?>
                </div>
                <div id="linkdigest-postbox-container-2" class="postbox-container">
                    <?php $this->renderCategoryForm(); ?>
                </div>
            </div>
        </div>

        <?php
    }

    // -------------------------------------------------------------------------
    // POST handlers
    // -------------------------------------------------------------------------

    /**
     * Process the add-category POST and build a notice array.
     *
     * @since 1.0.0
     * @return array Notice array with 'type' (success|error) and 'msg' keys.
     */
    private function buildAddCategoryNotice(): array {
        $error = $this->handleAddCategory();
        if ( $error ) {
            return array( 'type' => 'error', 'msg' => $error );
        }
        return array( 'type' => 'success', 'msg' => __( 'Category added.', 'linkdigest' ) );
    }

    /**
     * Process the delete-category POST and build a notice array.
     *
     * @since 1.0.0
     * @return array Notice array with 'type' (success|error) and 'msg' keys.
     */
    private function buildDeleteCategoryNotice(): array {
        $deleted = $this->handleDeleteCategory();
        return array(
            'type' => $deleted ? 'success' : 'error',
            'msg'  => $deleted
                ? __( 'Category deleted.', 'linkdigest' )
                : __( 'Could not delete category.', 'linkdigest' ),
        );
    }

    /**
     * Validate, sanitize, and insert a new linkdigest category.
     *
     * @since 1.0.0
     * @return string|null Error message string, or null on success.
     */
    private function handleAddCategory(): ?string {
        $nonce = isset( $_POST['linkdigest_cat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['linkdigest_cat_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'linkdigest_add_category' ) ) {
            return __( 'Security check failed.', 'linkdigest' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return __( 'Insufficient permissions.', 'linkdigest' );
        }
        $name = sanitize_text_field( wp_unslash( $_POST['cat_name'] ?? '' ) );
        if ( empty( $name ) ) {
            return __( 'Category name is required.', 'linkdigest' );
        }
        $desc   = sanitize_textarea_field( wp_unslash( $_POST['cat_description'] ?? '' ) );
        $result = wp_insert_term( $name, 'linkdigest_category', array( 'description' => $desc ) );
        if ( is_wp_error( $result ) ) {
            return $result->get_error_message();
        }
        delete_transient( 'linkdigest_api_categories_list' );
        delete_transient( 'linkdigest_categories_terms' );
        return null;
    }

    /**
     * Validate nonce, permissions, and delete a linkdigest category term.
     *
     * @since 1.0.0
     * @return bool True on successful deletion, false otherwise.
     */
    private function handleDeleteCategory(): bool {
        $nonce = isset( $_POST['linkdigest_cat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['linkdigest_cat_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'linkdigest_delete_category' ) ) {
            return false;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }
        $term_id = isset( $_POST['cat_term_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['cat_term_id'] ) ) : 0;
        if ( ! $term_id ) {
            return false;
        }
        $result = wp_delete_term( $term_id, 'linkdigest_category' );
        if ( is_wp_error( $result ) || $result === false ) {
            return false;
        }
        delete_transient( 'linkdigest_api_categories_list' );
        delete_transient( 'linkdigest_categories_terms' );
        return true;
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Get the number of linkdigest posts per category via a direct DB query.
     *
     * @since 1.0.0
     * @return array Map of term_id => link count.
     */
    private function getCategoryLinkCounts(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT tt.term_id, COUNT(p.ID) AS cnt
             FROM {$wpdb->term_taxonomy} tt
             LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
             LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                AND p.post_status IN ('linkdigest_pending','linkdigest_published','linkdigest_draft')
             WHERE tt.taxonomy = 'linkdigest_category'
             GROUP BY tt.term_id",
            ARRAY_A
        );
        if ( ! $rows ) {
            return array();
        }
        return array_column( $rows, 'cnt', 'term_id' );
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Render the categories list table with edit and delete actions.
     *
     * @since 1.0.0
     * @param array $terms  Array of linkdigest category term objects.
     * @param array $counts Map of term_id => link count from getCategoryLinkCounts().
     * @return void
     */
    private function renderCategoriesTable( array $terms, array $counts ): void {
        if ( empty( $terms ) ) {
            echo '<p>' . esc_html__( 'No categories yet. Use the form to add your first category.', 'linkdigest' ) . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat striped linkdigest-cat-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'linkdigest' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'linkdigest' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'linkdigest' ); ?></th>
                    <th class="linkdigest-cat-count-col"><?php esc_html_e( 'Links', 'linkdigest' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'linkdigest' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $terms as $term ) :
                    $count = (int) ( $counts[ $term->term_id ] ?? 0 );
                ?>
                <tr class="linkdigest-cat-row"
                    data-id="<?php echo (int) $term->term_id; ?>"
                    data-name="<?php echo esc_attr( $term->name ); ?>"
                    data-description="<?php echo esc_attr( $term->description ); ?>"
                    data-slug="<?php echo esc_attr( $term->slug ); ?>"
                    data-count="<?php echo (int) $count; ?>">
                    <td class="linkdigest-cat-cell-name"><strong><?php echo esc_html( $term->name ); ?></strong></td>
                    <td class="linkdigest-cat-cell-description linkdigest-cat-desc"><?php echo esc_html( $term->description ); ?></td>
                    <td class="linkdigest-cat-cell-slug"><code><?php echo esc_html( $term->slug ); ?></code></td>
                    <td class="linkdigest-cat-count-col"><?php echo (int) $count; ?></td>
                    <td class="linkdigest-cat-actions">
                        <button type="button" class="button-link linkdigest-cat-edit-btn"><?php esc_html_e( 'Edit', 'linkdigest' ); ?></button>
                        &nbsp;|&nbsp;
                        <form method="post" action="" class="linkdigest-cat-delete-form"
                              data-name="<?php echo esc_attr( $term->name ); ?>"
                              data-count="<?php echo (int) $count; ?>">
                            <?php wp_nonce_field( 'linkdigest_delete_category', 'linkdigest_cat_nonce' ); ?>
                            <input type="hidden" name="cat_term_id" value="<?php echo (int) $term->term_id; ?>">
                            <button type="submit" name="linkdigest_delete_category" class="button-link linkdigest-cat-delete-btn">
                                <?php esc_html_e( 'Delete', 'linkdigest' ); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the Add New Category form postbox.
     *
     * @since 1.0.0
     * @return void
     */
    private function renderCategoryForm(): void {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Add New Category', 'linkdigest' ); ?></h2>
            </div>
            <div class="inside">
                <form method="post" action="">
                    <?php wp_nonce_field( 'linkdigest_add_category', 'linkdigest_cat_nonce' ); ?>
                    <p>
                        <label for="cat_name"><strong><?php esc_html_e( 'Name', 'linkdigest' ); ?> *</strong></label><br>
                        <input type="text" id="cat_name" name="cat_name" class="regular-text" required>
                    </p>
                    <p>
                        <label for="cat_description">
                            <strong><?php esc_html_e( 'Description', 'linkdigest' ); ?></strong>
                            <span class="linkdigest-optional"><?php esc_html_e( '(optional)', 'linkdigest' ); ?></span>
                        </label><br>
                        <textarea id="cat_description" name="cat_description" class="regular-text" rows="3"></textarea>
                    </p>
                    <p>
                        <button type="submit" name="linkdigest_add_category" class="button button-primary">
                            <?php esc_html_e( 'Add Category', 'linkdigest' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
