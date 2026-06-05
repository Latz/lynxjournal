<?php
/**
 * Trait for registering the lynxjournal post type, statuses, and taxonomies.
 *
 * @package LynxJournal
 */

declare(strict_types=1);

trait LynxJournal_PostType {

    /**
     * Registers the lynxjournal custom post type and its meta.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_post_type(): void {
        $this->register_post_statuses();

        $labels = array(
            'name'                  => _x( 'Links', 'Post Type General Name', 'lynxjournal' ),
            'singular_name'         => _x( 'Link', 'Post Type Singular Name', 'lynxjournal' ),
            'menu_name'             => __( 'lynxjournal', 'lynxjournal' ),
            'name_admin_bar'        => __( 'Link', 'lynxjournal' ),
            'archives'              => __( 'Link Archives', 'lynxjournal' ),
            'attributes'            => __( 'Link Attributes', 'lynxjournal' ),
            'parent_item_colon'     => __( 'Parent Link:', 'lynxjournal' ),
            'all_items'             => __( 'All Links', 'lynxjournal' ),
            'add_new_item'          => __( 'Add New Link', 'lynxjournal' ),
            'add_new'               => __( 'Add New', 'lynxjournal' ),
            'new_item'              => __( 'New Link', 'lynxjournal' ),
            'edit_item'             => __( 'Edit Link', 'lynxjournal' ),
            'update_item'           => __( 'Update Link', 'lynxjournal' ),
            'view_item'             => __( 'View Link', 'lynxjournal' ),
            'view_items'            => __( 'View Links', 'lynxjournal' ),
            'search_items'          => __( 'Search Link', 'lynxjournal' ),
            'not_found'             => __( 'Not found', 'lynxjournal' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'lynxjournal' ),
            'featured_image'        => __( 'Featured Image', 'lynxjournal' ),
            'set_featured_image'    => __( 'Set featured image', 'lynxjournal' ),
            'remove_featured_image' => __( 'Remove featured image', 'lynxjournal' ),
            'use_featured_image'    => __( 'Use as featured image', 'lynxjournal' ),
            'insert_into_item'      => __( 'Insert into link', 'lynxjournal' ),
            'uploaded_to_this_item' => __( 'Uploaded to this link', 'lynxjournal' ),
            'items_list'            => __( 'Links list', 'lynxjournal' ),
            'items_list_navigation' => __( 'Links list navigation', 'lynxjournal' ),
            'filter_items_list'     => __( 'Filter links list', 'lynxjournal' ),
        );

        $args = array(
            'label'               => __( 'Link', 'lynxjournal' ),
            'description'         => __( 'Links to publish on blog', 'lynxjournal' ),
            'labels'              => $labels,
            'supports'            => array( 'title', 'editor', 'custom-fields' ),
            'taxonomies'          => array( 'lynxjournal_category', 'lynxjournal_tag' ),
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,      // Suppressed: plugin uses a custom admin menu.
            'menu_position'       => 5,
            'menu_icon'           => plugins_url( 'assets/icon-menu.png', LYNXJOURNAL_PLUGIN_FILE ),
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        );

        register_post_type( 'lynxjournal', $args );

        // Expose publish status in REST so the block editor and external tools can read it.
        // auth_callback '__return_true' is safe: the field is non-sensitive tracking data.
        register_post_meta(
            'lynxjournal',
            '_lynxjournal_publish_status',
            array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => '__return_true',
            )
        );
        register_post_meta(
            'lynxjournal',
            '_lynxjournal_url',
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
     * Registers the lynxjournal custom post statuses.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_post_statuses(): void {
        register_post_status(
            'lynxjournal_pending',
            array(
                'label'                     => _x( 'Pending', 'lynxjournal post status', 'lynxjournal' ),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'Pending <span class="count">(%s)</span>',
                    'Pending <span class="count">(%s)</span>',
                    'lynxjournal'
                ),
            )
        );
        register_post_status(
            'lynxjournal_published',
            array(
                'label'                     => _x( 'In Roundup', 'lynxjournal post status', 'lynxjournal' ),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'In Roundup <span class="count">(%s)</span>',
                    'In Roundup <span class="count">(%s)</span>',
                    'lynxjournal'
                ),
            )
        );
        register_post_status(
            'lynxjournal_draft',
            array(
                'label'                     => _x( 'In Draft Roundup', 'lynxjournal post status', 'lynxjournal' ),
                'public'                    => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'In Draft Roundup <span class="count">(%s)</span>',
                    'In Draft Roundup <span class="count">(%s)</span>',
                    'lynxjournal'
                ),
            )
        );
    }

    /**
     * Registers the lynxjournal custom taxonomies (categories and tags).
     *
     * @since 1.0.0
     * @return void
     */
    public function register_taxonomies(): void {
        // Register Category taxonomy.
        $category_labels = array(
            'name'                       => _x( 'Link Categories', 'Taxonomy General Name', 'lynxjournal' ),
            'singular_name'              => _x( 'Link Category', 'Taxonomy Singular Name', 'lynxjournal' ),
            'menu_name'                  => __( 'Categories', 'lynxjournal' ),
            'all_items'                  => __( 'All Categories', 'lynxjournal' ),
            'parent_item'                => __( 'Parent Category', 'lynxjournal' ),
            'parent_item_colon'          => __( 'Parent Category:', 'lynxjournal' ),
            'new_item_name'              => __( 'New Category Name', 'lynxjournal' ),
            'add_new_item'               => __( 'Add New Category', 'lynxjournal' ),
            'edit_item'                  => __( 'Edit Category', 'lynxjournal' ),
            'update_item'                => __( 'Update Category', 'lynxjournal' ),
            'view_item'                  => __( 'View Category', 'lynxjournal' ),
            'separate_items_with_commas' => __( 'Separate categories with commas', 'lynxjournal' ),
            'add_or_remove_items'        => __( 'Add or remove categories', 'lynxjournal' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'lynxjournal' ),
            'popular_items'              => __( 'Popular Categories', 'lynxjournal' ),
            'search_items'               => __( 'Search Categories', 'lynxjournal' ),
            'not_found'                  => __( 'Not Found', 'lynxjournal' ),
            'no_terms'                   => __( 'No categories', 'lynxjournal' ),
            'items_list'                 => __( 'Categories list', 'lynxjournal' ),
            'items_list_navigation'      => __( 'Categories list navigation', 'lynxjournal' ),
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

        register_taxonomy( 'lynxjournal_category', array( 'lynxjournal' ), $category_args );

        // Register Tag taxonomy.
        $tag_labels = array(
            'name'                       => _x( 'Link Tags', 'Taxonomy General Name', 'lynxjournal' ),
            'singular_name'              => _x( 'Link Tag', 'Taxonomy Singular Name', 'lynxjournal' ),
            'menu_name'                  => __( 'Tags', 'lynxjournal' ),
            'all_items'                  => __( 'All Tags', 'lynxjournal' ),
            'parent_item'                => __( 'Parent Tag', 'lynxjournal' ),
            'parent_item_colon'          => __( 'Parent Tag:', 'lynxjournal' ),
            'new_item_name'              => __( 'New Tag Name', 'lynxjournal' ),
            'add_new_item'               => __( 'Add New Tag', 'lynxjournal' ),
            'edit_item'                  => __( 'Edit Tag', 'lynxjournal' ),
            'update_item'                => __( 'Update Tag', 'lynxjournal' ),
            'view_item'                  => __( 'View Tag', 'lynxjournal' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'lynxjournal' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'lynxjournal' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'lynxjournal' ),
            'popular_items'              => __( 'Popular Tags', 'lynxjournal' ),
            'search_items'               => __( 'Search Tags', 'lynxjournal' ),
            'not_found'                  => __( 'Not Found', 'lynxjournal' ),
            'no_terms'                   => __( 'No tags', 'lynxjournal' ),
            'items_list'                 => __( 'Tags list', 'lynxjournal' ),
            'items_list_navigation'      => __( 'Tags list navigation', 'lynxjournal' ),
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

        register_taxonomy( 'lynxjournal_tag', array( 'lynxjournal' ), $tag_args );
    }
}
