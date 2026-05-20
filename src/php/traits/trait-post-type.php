<?php
/**
 * Trait for registering the linkdigest post type, statuses, and taxonomies.
 *
 * @package LinkDigest
 */

declare(strict_types=1);

trait LinkDigest_PostType {

    /**
     * Registers the linkdigest custom post type and its meta.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_post_type(): void {
        $this->register_post_statuses();

        $labels = array(
            'name'                  => _x( 'Links', 'Post Type General Name', 'linkdigest' ),
            'singular_name'         => _x( 'Link', 'Post Type Singular Name', 'linkdigest' ),
            'menu_name'             => __( 'linkdigest', 'linkdigest' ),
            'name_admin_bar'        => __( 'Link', 'linkdigest' ),
            'archives'              => __( 'Link Archives', 'linkdigest' ),
            'attributes'            => __( 'Link Attributes', 'linkdigest' ),
            'parent_item_colon'     => __( 'Parent Link:', 'linkdigest' ),
            'all_items'             => __( 'All Links', 'linkdigest' ),
            'add_new_item'          => __( 'Add New Link', 'linkdigest' ),
            'add_new'               => __( 'Add New', 'linkdigest' ),
            'new_item'              => __( 'New Link', 'linkdigest' ),
            'edit_item'             => __( 'Edit Link', 'linkdigest' ),
            'update_item'           => __( 'Update Link', 'linkdigest' ),
            'view_item'             => __( 'View Link', 'linkdigest' ),
            'view_items'            => __( 'View Links', 'linkdigest' ),
            'search_items'          => __( 'Search Link', 'linkdigest' ),
            'not_found'             => __( 'Not found', 'linkdigest' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'linkdigest' ),
            'featured_image'        => __( 'Featured Image', 'linkdigest' ),
            'set_featured_image'    => __( 'Set featured image', 'linkdigest' ),
            'remove_featured_image' => __( 'Remove featured image', 'linkdigest' ),
            'use_featured_image'    => __( 'Use as featured image', 'linkdigest' ),
            'insert_into_item'      => __( 'Insert into link', 'linkdigest' ),
            'uploaded_to_this_item' => __( 'Uploaded to this link', 'linkdigest' ),
            'items_list'            => __( 'Links list', 'linkdigest' ),
            'items_list_navigation' => __( 'Links list navigation', 'linkdigest' ),
            'filter_items_list'     => __( 'Filter links list', 'linkdigest' ),
        );

        $args = array(
            'label'               => __( 'Link', 'linkdigest' ),
            'description'         => __( 'Links to publish on blog', 'linkdigest' ),
            'labels'              => $labels,
            'supports'            => array( 'title', 'editor', 'custom-fields' ),
            'taxonomies'          => array( 'linkdigest_category', 'linkdigest_tag' ),
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,      // Suppressed: plugin uses a custom admin menu.
            'menu_position'       => 5,
            'menu_icon'           => plugins_url( 'assets/icon-menu.png', LINKDIGEST_PLUGIN_FILE ),
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        );

        register_post_type( 'linkdigest', $args );

        // Expose publish status in REST so the block editor and external tools can read it.
        // auth_callback '__return_true' is safe: the field is non-sensitive tracking data.
        register_post_meta(
            'linkdigest',
            '_linkdigest_publish_status',
            array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => '__return_true',
            )
        );
        register_post_meta(
            'linkdigest',
            '_linkdigest_url',
            array(
                'show_in_rest'      => false,
                'single'            => true,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback'     => '__return_true',
            )
        );
    }

    /**
     * Registers the linkdigest custom post statuses.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_post_statuses(): void {
        register_post_status(
            'linkdigest_pending',
            array(
                'label'                     => _x( 'Pending', 'linkdigest post status', 'linkdigest' ),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'Pending <span class="count">(%s)</span>',
                    'Pending <span class="count">(%s)</span>',
                    'linkdigest'
                ),
            )
        );
        register_post_status(
            'linkdigest_published',
            array(
                'label'                     => _x( 'In Digest', 'linkdigest post status', 'linkdigest' ),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'In Digest <span class="count">(%s)</span>',
                    'In Digest <span class="count">(%s)</span>',
                    'linkdigest'
                ),
            )
        );
        register_post_status(
            'linkdigest_draft',
            array(
                'label'                     => _x( 'In Draft Digest', 'linkdigest post status', 'linkdigest' ),
                'public'                    => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'In Draft Digest <span class="count">(%s)</span>',
                    'In Draft Digest <span class="count">(%s)</span>',
                    'linkdigest'
                ),
            )
        );
    }

    /**
     * Registers the linkdigest custom taxonomies (categories and tags).
     *
     * @since 1.0.0
     * @return void
     */
    public function register_taxonomies(): void {
        // Register Category taxonomy.
        $category_labels = array(
            'name'                       => _x( 'Link Categories', 'Taxonomy General Name', 'linkdigest' ),
            'singular_name'              => _x( 'Link Category', 'Taxonomy Singular Name', 'linkdigest' ),
            'menu_name'                  => __( 'Categories', 'linkdigest' ),
            'all_items'                  => __( 'All Categories', 'linkdigest' ),
            'parent_item'                => __( 'Parent Category', 'linkdigest' ),
            'parent_item_colon'          => __( 'Parent Category:', 'linkdigest' ),
            'new_item_name'              => __( 'New Category Name', 'linkdigest' ),
            'add_new_item'               => __( 'Add New Category', 'linkdigest' ),
            'edit_item'                  => __( 'Edit Category', 'linkdigest' ),
            'update_item'                => __( 'Update Category', 'linkdigest' ),
            'view_item'                  => __( 'View Category', 'linkdigest' ),
            'separate_items_with_commas' => __( 'Separate categories with commas', 'linkdigest' ),
            'add_or_remove_items'        => __( 'Add or remove categories', 'linkdigest' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'linkdigest' ),
            'popular_items'              => __( 'Popular Categories', 'linkdigest' ),
            'search_items'               => __( 'Search Categories', 'linkdigest' ),
            'not_found'                  => __( 'Not Found', 'linkdigest' ),
            'no_terms'                   => __( 'No categories', 'linkdigest' ),
            'items_list'                 => __( 'Categories list', 'linkdigest' ),
            'items_list_navigation'      => __( 'Categories list navigation', 'linkdigest' ),
        );

        $category_args = array(
            'labels'            => $category_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'capabilities'      => array(
                'manage_terms' => 'edit_posts',
                'edit_terms'   => 'edit_posts',
                'delete_terms' => 'edit_posts',
                'assign_terms' => 'edit_posts',
            ),
        );

        register_taxonomy( 'linkdigest_category', array( 'linkdigest' ), $category_args );

        // Register Tag taxonomy.
        $tag_labels = array(
            'name'                       => _x( 'Link Tags', 'Taxonomy General Name', 'linkdigest' ),
            'singular_name'              => _x( 'Link Tag', 'Taxonomy Singular Name', 'linkdigest' ),
            'menu_name'                  => __( 'Tags', 'linkdigest' ),
            'all_items'                  => __( 'All Tags', 'linkdigest' ),
            'parent_item'                => __( 'Parent Tag', 'linkdigest' ),
            'parent_item_colon'          => __( 'Parent Tag:', 'linkdigest' ),
            'new_item_name'              => __( 'New Tag Name', 'linkdigest' ),
            'add_new_item'               => __( 'Add New Tag', 'linkdigest' ),
            'edit_item'                  => __( 'Edit Tag', 'linkdigest' ),
            'update_item'                => __( 'Update Tag', 'linkdigest' ),
            'view_item'                  => __( 'View Tag', 'linkdigest' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'linkdigest' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'linkdigest' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'linkdigest' ),
            'popular_items'              => __( 'Popular Tags', 'linkdigest' ),
            'search_items'               => __( 'Search Tags', 'linkdigest' ),
            'not_found'                  => __( 'Not Found', 'linkdigest' ),
            'no_terms'                   => __( 'No tags', 'linkdigest' ),
            'items_list'                 => __( 'Tags list', 'linkdigest' ),
            'items_list_navigation'      => __( 'Tags list navigation', 'linkdigest' ),
        );

        $tag_args = array(
            'labels'            => $tag_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'capabilities'      => array(
                'manage_terms' => 'edit_posts',
                'edit_terms'   => 'edit_posts',
                'delete_terms' => 'edit_posts',
                'assign_terms' => 'edit_posts',
            ),
        );

        register_taxonomy( 'linkdigest_tag', array( 'linkdigest' ), $tag_args );
    }
}
