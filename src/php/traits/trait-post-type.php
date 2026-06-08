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
            'name'                  => _x( 'Links', 'Post Type General Name', 'lynx-journal' ),
            'singular_name'         => _x( 'Link', 'Post Type Singular Name', 'lynx-journal' ),
            'menu_name'             => __( 'lynx-journal', 'lynx-journal' ),
            'name_admin_bar'        => __( 'Link', 'lynx-journal' ),
            'archives'              => __( 'Link Archives', 'lynx-journal' ),
            'attributes'            => __( 'Link Attributes', 'lynx-journal' ),
            'parent_item_colon'     => __( 'Parent Link:', 'lynx-journal' ),
            'all_items'             => __( 'All Links', 'lynx-journal' ),
            'add_new_item'          => __( 'Add New Link', 'lynx-journal' ),
            'add_new'               => __( 'Add New', 'lynx-journal' ),
            'new_item'              => __( 'New Link', 'lynx-journal' ),
            'edit_item'             => __( 'Edit Link', 'lynx-journal' ),
            'update_item'           => __( 'Update Link', 'lynx-journal' ),
            'view_item'             => __( 'View Link', 'lynx-journal' ),
            'view_items'            => __( 'View Links', 'lynx-journal' ),
            'search_items'          => __( 'Search Link', 'lynx-journal' ),
            'not_found'             => __( 'Not found', 'lynx-journal' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'lynx-journal' ),
            'featured_image'        => __( 'Featured Image', 'lynx-journal' ),
            'set_featured_image'    => __( 'Set featured image', 'lynx-journal' ),
            'remove_featured_image' => __( 'Remove featured image', 'lynx-journal' ),
            'use_featured_image'    => __( 'Use as featured image', 'lynx-journal' ),
            'insert_into_item'      => __( 'Insert into link', 'lynx-journal' ),
            'uploaded_to_this_item' => __( 'Uploaded to this link', 'lynx-journal' ),
            'items_list'            => __( 'Links list', 'lynx-journal' ),
            'items_list_navigation' => __( 'Links list navigation', 'lynx-journal' ),
            'filter_items_list'     => __( 'Filter links list', 'lynx-journal' ),
        );

        $args = array(
            'label'               => __( 'Link', 'lynx-journal' ),
            'description'         => __( 'Links to publish on blog', 'lynx-journal' ),
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

        register_post_type( 'lynx-journal', $args );

        // Expose publish status in REST so the block editor and external tools can read it.
        // auth_callback '__return_true' is safe: the field is non-sensitive tracking data.
        register_post_meta(
            'lynx-journal',
            '_lynxjournal_publish_status',
            array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => '__return_true',
            )
        );
        register_post_meta(
            'lynx-journal',
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
                'label'                     => _x( 'Pending', 'lynxjournal post status', 'lynx-journal' ),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'Pending <span class="count">(%s)</span>',
                    'Pending <span class="count">(%s)</span>',
                    'lynx-journal'
                ),
            )
        );
        register_post_status(
            'lynxjournal_published',
            array(
                'label'                     => _x( 'In Roundup', 'lynxjournal post status', 'lynx-journal' ),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'In Roundup <span class="count">(%s)</span>',
                    'In Roundup <span class="count">(%s)</span>',
                    'lynx-journal'
                ),
            )
        );
        register_post_status(
            'lynxjournal_draft',
            array(
                'label'                     => _x( 'In Draft Roundup', 'lynxjournal post status', 'lynx-journal' ),
                'public'                    => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // translators: %s: number of posts with this status.
                'label_count'               => _n_noop(
                    'In Draft Roundup <span class="count">(%s)</span>',
                    'In Draft Roundup <span class="count">(%s)</span>',
                    'lynx-journal'
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
            'name'                       => _x( 'Link Categories', 'Taxonomy General Name', 'lynx-journal' ),
            'singular_name'              => _x( 'Link Category', 'Taxonomy Singular Name', 'lynx-journal' ),
            'menu_name'                  => __( 'Categories', 'lynx-journal' ),
            'all_items'                  => __( 'All Categories', 'lynx-journal' ),
            'parent_item'                => __( 'Parent Category', 'lynx-journal' ),
            'parent_item_colon'          => __( 'Parent Category:', 'lynx-journal' ),
            'new_item_name'              => __( 'New Category Name', 'lynx-journal' ),
            'add_new_item'               => __( 'Add New Category', 'lynx-journal' ),
            'edit_item'                  => __( 'Edit Category', 'lynx-journal' ),
            'update_item'                => __( 'Update Category', 'lynx-journal' ),
            'view_item'                  => __( 'View Category', 'lynx-journal' ),
            'separate_items_with_commas' => __( 'Separate categories with commas', 'lynx-journal' ),
            'add_or_remove_items'        => __( 'Add or remove categories', 'lynx-journal' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'lynx-journal' ),
            'popular_items'              => __( 'Popular Categories', 'lynx-journal' ),
            'search_items'               => __( 'Search Categories', 'lynx-journal' ),
            'not_found'                  => __( 'Not Found', 'lynx-journal' ),
            'no_terms'                   => __( 'No categories', 'lynx-journal' ),
            'items_list'                 => __( 'Categories list', 'lynx-journal' ),
            'items_list_navigation'      => __( 'Categories list navigation', 'lynx-journal' ),
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

        register_taxonomy( 'lynxjournal_category', array( 'lynx-journal' ), $category_args );

        // Register Tag taxonomy.
        $tag_labels = array(
            'name'                       => _x( 'Link Tags', 'Taxonomy General Name', 'lynx-journal' ),
            'singular_name'              => _x( 'Link Tag', 'Taxonomy Singular Name', 'lynx-journal' ),
            'menu_name'                  => __( 'Tags', 'lynx-journal' ),
            'all_items'                  => __( 'All Tags', 'lynx-journal' ),
            'parent_item'                => __( 'Parent Tag', 'lynx-journal' ),
            'parent_item_colon'          => __( 'Parent Tag:', 'lynx-journal' ),
            'new_item_name'              => __( 'New Tag Name', 'lynx-journal' ),
            'add_new_item'               => __( 'Add New Tag', 'lynx-journal' ),
            'edit_item'                  => __( 'Edit Tag', 'lynx-journal' ),
            'update_item'                => __( 'Update Tag', 'lynx-journal' ),
            'view_item'                  => __( 'View Tag', 'lynx-journal' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'lynx-journal' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'lynx-journal' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'lynx-journal' ),
            'popular_items'              => __( 'Popular Tags', 'lynx-journal' ),
            'search_items'               => __( 'Search Tags', 'lynx-journal' ),
            'not_found'                  => __( 'Not Found', 'lynx-journal' ),
            'no_terms'                   => __( 'No tags', 'lynx-journal' ),
            'items_list'                 => __( 'Tags list', 'lynx-journal' ),
            'items_list_navigation'      => __( 'Tags list navigation', 'lynx-journal' ),
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

        register_taxonomy( 'lynxjournal_tag', array( 'lynx-journal' ), $tag_args );
    }
}
