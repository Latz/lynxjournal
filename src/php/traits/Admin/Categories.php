<?php

declare(strict_types=1);

trait LynxJournal_Admin_Categories {

    // -------------------------------------------------------------------------
    // Page entry point
    // -------------------------------------------------------------------------

    public function categoriesPage(): void {
        $notice = null;

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) === 'POST' ) {
            $nonce = isset( $_POST['lynxjournal_cat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_cat_nonce'] ) ) : '';
            if ( isset( $_POST['lynxjournal_add_category'] ) && wp_verify_nonce( $nonce, 'lynxjournal_add_category' ) ) {
                $notice = $this->buildAddCategoryNotice();
            } elseif ( isset( $_POST['lynxjournal_delete_category'] ) && wp_verify_nonce( $nonce, 'lynxjournal_delete_category' ) ) {
                $notice = $this->buildDeleteCategoryNotice();
            }
        }

        $terms  = $this->getCachedCategories();
        $counts = $this->getCategoryLinkCounts();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Link Categories', 'lynx-journal' ); ?></h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] === 'success' ? 'success' : 'error' ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['msg'] ); ?></p>
                </div>
            <?php endif; ?>

            <div class="metabox-holder lynxjournal-dashboard">
                <div id="lynxjournal-postbox-container-1" class="postbox-container">
                    <?php $this->renderCategoriesTable( $terms, $counts ); ?>
                </div>
                <div id="lynxjournal-postbox-container-2" class="postbox-container">
                    <?php $this->renderCategoryForm(); ?>
                </div>
            </div>
        </div>

        <?php
    }

    // -------------------------------------------------------------------------
    // POST handlers
    // -------------------------------------------------------------------------

    private function buildAddCategoryNotice(): array {
        $error = $this->handleAddCategory();
        if ( $error ) {
            return array( 'type' => 'error', 'msg' => $error );
        }
        return array( 'type' => 'success', 'msg' => __( 'Category added.', 'lynx-journal' ) );
    }

    private function buildDeleteCategoryNotice(): array {
        $deleted = $this->handleDeleteCategory();
        return array(
            'type' => $deleted ? 'success' : 'error',
            'msg'  => $deleted
                ? __( 'Category deleted.', 'lynx-journal' )
                : __( 'Could not delete category.', 'lynx-journal' ),
        );
    }

    private function handleAddCategory(): ?string {
        $permission_error = $this->checkAddCategoryPermissions();
        if ( $permission_error ) {
            return $permission_error;
        }

        $input = $this->validateAddCategoryInput();
        if ( $input['error'] !== '' ) {
            return $input['error'];
        }

        return $this->insertNewCategory( $input );
    }

    private function checkAddCategoryPermissions(): ?string {
        $nonce = isset( $_POST['lynxjournal_cat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_cat_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_add_category' ) ) {
            return __( 'Security check failed.', 'lynx-journal' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return __( 'Insufficient permissions.', 'lynx-journal' );
        }
        return null;
    }

    private function validateAddCategoryInput(): array {
        $name  = sanitize_text_field( wp_unslash( $_POST['cat_name'] ?? '' ) );
        $desc  = sanitize_textarea_field( wp_unslash( $_POST['cat_description'] ?? '' ) );
        $error = empty( $name ) ? __( 'Category name is required.', 'lynx-journal' ) : '';
        return array( 'name' => $name, 'description' => $desc, 'error' => $error );
    }

    private function insertNewCategory( array $input ): ?string {
        $result = wp_insert_term( $input['name'], 'lynxjournal_category', array( 'description' => $input['description'] ) );
        if ( is_wp_error( $result ) ) {
            return $result->get_error_message();
        }
        delete_transient( 'lynxjournal_api_categories_list' );
        delete_transient( 'lynxjournal_categories_terms' );
        return null;
    }

    private function handleDeleteCategory(): bool {
        $nonce = isset( $_POST['lynxjournal_cat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_cat_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_delete_category' ) ) {
            return false;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }
        $term_id = isset( $_POST['cat_term_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['cat_term_id'] ) ) : 0;
        if ( ! $term_id ) {
            return false;
        }
        $result = wp_delete_term( $term_id, 'lynxjournal_category' );
        if ( is_wp_error( $result ) || $result === false ) {
            return false;
        }
        delete_transient( 'lynxjournal_api_categories_list' );
        delete_transient( 'lynxjournal_categories_terms' );
        return true;
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    private function getCategoryLinkCounts(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT tt.term_id, COUNT(p.ID) AS cnt
             FROM {$wpdb->term_taxonomy} tt
             LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
             LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                AND p.post_status IN ('lynxjournal_pending','lynxjournal_pub','lynxjournal_draft')
             WHERE tt.taxonomy = 'lynxjournal_category'
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

    private function renderCategoriesTable( array $terms, array $counts ): void {
        if ( empty( $terms ) ) {
            echo '<p>' . esc_html__( 'No categories yet. Use the form to add your first category.', 'lynx-journal' ) . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat striped lynxjournal-cat-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'lynx-journal' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'lynx-journal' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'lynx-journal' ); ?></th>
                    <th class="lynxjournal-cat-count-col"><?php esc_html_e( 'Links', 'lynx-journal' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'lynx-journal' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $terms as $term ) :
                    $count = (int) ( $counts[ $term->term_id ] ?? 0 );
                ?>
                <tr class="lynxjournal-cat-row"
                    data-id="<?php echo (int) $term->term_id; ?>"
                    data-name="<?php echo esc_attr( $term->name ); ?>"
                    data-description="<?php echo esc_attr( $term->description ); ?>"
                    data-slug="<?php echo esc_attr( $term->slug ); ?>"
                    data-count="<?php echo (int) $count; ?>">
                    <td class="lynxjournal-cat-cell-name"><strong><?php echo esc_html( $term->name ); ?></strong></td>
                    <td class="lynxjournal-cat-cell-description lynxjournal-cat-desc"><?php echo esc_html( $term->description ); ?></td>
                    <td class="lynxjournal-cat-cell-slug"><code><?php echo esc_html( $term->slug ); ?></code></td>
                    <td class="lynxjournal-cat-count-col"><?php echo (int) $count; ?></td>
                    <td class="lynxjournal-cat-actions">
                        <button type="button" class="button-link lynxjournal-cat-edit-btn"><?php esc_html_e( 'Edit', 'lynx-journal' ); ?></button>
                        &nbsp;|&nbsp;
                        <form method="post" action="" class="lynxjournal-cat-delete-form"
                              data-name="<?php echo esc_attr( $term->name ); ?>"
                              data-count="<?php echo (int) $count; ?>">
                            <?php wp_nonce_field( 'lynxjournal_delete_category', 'lynxjournal_cat_nonce' ); ?>
                            <input type="hidden" name="cat_term_id" value="<?php echo (int) $term->term_id; ?>">
                            <button type="submit" name="lynxjournal_delete_category" class="button-link lynxjournal-cat-delete-btn">
                                <?php esc_html_e( 'Delete', 'lynx-journal' ); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderCategoryForm(): void {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e( 'Add New Category', 'lynx-journal' ); ?></h2>
            </div>
            <div class="inside">
                <form method="post" action="">
                    <?php wp_nonce_field( 'lynxjournal_add_category', 'lynxjournal_cat_nonce' ); ?>
                    <p>
                        <label for="cat_name"><strong><?php esc_html_e( 'Name', 'lynx-journal' ); ?> *</strong></label><br>
                        <input type="text" id="cat_name" name="cat_name" class="regular-text" required>
                    </p>
                    <p>
                        <label for="cat_description">
                            <strong><?php esc_html_e( 'Description', 'lynx-journal' ); ?></strong>
                            <span class="lynxjournal-optional"><?php esc_html_e( '(optional)', 'lynx-journal' ); ?></span>
                        </label><br>
                        <textarea id="cat_description" name="cat_description" class="regular-text" rows="3"></textarea>
                    </p>
                    <p>
                        <button type="submit" name="lynxjournal_add_category" class="button button-primary">
                            <?php esc_html_e( 'Add Category', 'lynx-journal' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
