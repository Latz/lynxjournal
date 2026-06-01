<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.WP.GlobalVariablesOverride,WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- test bootstrap file

/**
 * Bootstrap for Integration tests.
 *
 * Requires the WordPress test suite to be installed first:
 *
 *   bin/install-wp-tests.sh <db> <user> <pass> [host] [wp-version]
 *
 * The WP_TESTS_DIR environment variable must point to that suite, OR you can
 * set it in .env.testing and export it before running Pest.
 *
 * Quick start (adjust values):
 *   export WP_TESTS_DIR=/tmp/wordpress-tests-lib
 *   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
 *   vendor/bin/pest --testsuite=Integration
 */

$linkdigest_wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (! is_dir($linkdigest_wp_tests_dir)) {
    echo "\nERROR: WP test suite not found at {$linkdigest_wp_tests_dir}.\n";
    echo "Run bin/install-wp-tests.sh or set WP_TESTS_DIR.\n\n";
    exit(1);
}

define('LINKDIGEST_TESTS_DIR', dirname(__DIR__));

// Point WP test bootstrap to the Composer-installed polyfills.
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define(
        'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
        dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills'
    );
}

require_once $linkdigest_wp_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require_once LINKDIGEST_TESTS_DIR . '/linkdigest.php';
});

require_once $linkdigest_wp_tests_dir . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
