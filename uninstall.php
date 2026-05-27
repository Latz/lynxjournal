<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function linkdigest_uninstall(): void {
    // Register types so WP API functions recognise them in this context.
    register_post_type( 'linkdigest' );
    register_taxonomy( 'linkdigest_category', 'linkdigest' );
    register_taxonomy( 'linkdigest_tag', 'linkdigest' );

    // Posts — force-delete removes postmeta and term relationships automatically.
    $post_ids = get_posts( array(
        'post_type'      => 'linkdigest',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'post_status'    => 'any',
        'no_found_rows'  => true,
    ) );
    foreach ( $post_ids as $post_id ) {
        wp_delete_post( (int) $post_id, true );
    }

    // Taxonomy terms — wp_delete_term cleans terms, termmeta, term_taxonomy.
    foreach ( array( 'linkdigest_category', 'linkdigest_tag' ) as $taxonomy ) {
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
        'linkdigest_schema_version',
        'linkdigest_cron_notice_dismissed',
        'linkdigest_schedule',
        'linkdigest_last_run',
        'linkdigest_run_history',
        'linkdigest_api_key',
    ) as $option ) {
        delete_option( $option );
    }

    // Transients.
    foreach ( array(
        'linkdigest_run_lock',
        'linkdigest_publish_stats',
        'linkdigest_categories_terms',
        'linkdigest_api_categories_list',
    ) as $transient ) {
        delete_transient( $transient );
    }

    // Scheduled event.
    wp_clear_scheduled_hook( 'linkdigest_execute_schedule' );
}

linkdigest_uninstall();
