<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function lynxjournal_uninstall(): void {
    // Register types so WP API functions recognise them in this context.
    register_post_type( 'lynxjournal' );
    register_taxonomy( 'lynxjournal_category', 'lynxjournal' );
    register_taxonomy( 'lynxjournal_tag', 'lynxjournal' );

    // Posts — force-delete removes postmeta and term relationships automatically.
    $post_ids = get_posts( array(
        'post_type'      => 'lynxjournal',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'post_status'    => 'any',
        'no_found_rows'  => true,
    ) );
    foreach ( $post_ids as $post_id ) {
        wp_delete_post( (int) $post_id, true );
    }

    // Taxonomy terms — wp_delete_term cleans terms, termmeta, term_taxonomy.
    foreach ( array( 'lynxjournal_category', 'lynxjournal_tag' ) as $taxonomy ) {
        $term_ids = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        ) );
        if ( is_array( $term_ids ) ) {
            foreach ( $term_ids as $term_id ) {
                wp_delete_term( (int) $term_id, $taxonomy );
            }
        }
    }

    // Options.
    foreach ( array(
        'lynxjournal_schema_version',
        'lynxjournal_cron_notice_dismissed',
        'lynxjournal_schedule',
        'lynxjournal_last_run',
        'lynxjournal_run_history',
        'lynxjournal_api_key',
    ) as $option ) {
        delete_option( $option );
    }

    // Transients.
    foreach ( array(
        'lynxjournal_run_lock',
        'lynxjournal_publish_stats',
        'lynxjournal_categories_terms',
        'lynxjournal_api_categories_list',
    ) as $transient ) {
        delete_transient( $transient );
    }

    // Scheduled event.
    wp_clear_scheduled_hook( 'lynxjournal_execute_schedule' );
}

lynxjournal_uninstall();
