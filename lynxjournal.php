<?php
/**
 * Plugin Name: LynxJournal
 * Description: Save and publish links to your blog
 * Version: 2.0.0
 * Author: Latz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lynx-journal
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Shared constants — single source of truth for REST namespace/routes.
// Inlined to avoid file_get_contents + json_decode on every request.
// constants.json is imported separately by Vitest and Playwright tests.
// ---------------------------------------------------------------------------
define('LYNXJOURNAL_REST_NAMESPACE', 'lynxjournal/v1');
define('LYNXJOURNAL_POST_TYPE',      'lynxjournal');

define('LYNXJOURNAL_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/src/php/ScheduleMode.php';

// Traits (must be required before the class)
require_once __DIR__ . '/src/php/traits/trait-post-type.php';
require_once __DIR__ . '/src/php/traits/Templates.php';
require_once __DIR__ . '/src/php/traits/MetaBoxes.php';
require_once __DIR__ . '/src/php/traits/Publishing.php';
require_once __DIR__ . '/src/php/traits/Batch.php';
require_once __DIR__ . '/src/php/traits/Queries.php';
require_once __DIR__ . '/src/php/traits/ScheduleValidator.php';
require_once __DIR__ . '/src/php/traits/RestApi.php';
require_once __DIR__ . '/src/php/traits/Admin/Menu.php';
require_once __DIR__ . '/src/php/traits/Admin/Dashboard.php';
require_once __DIR__ . '/src/php/traits/Admin/LinksPage.php';
require_once __DIR__ . '/src/php/traits/Admin/AddLink.php';
require_once __DIR__ . '/src/php/traits/Admin/Categories.php';
require_once __DIR__ . '/src/php/traits/Scheduler.php';
require_once __DIR__ . '/src/php/class-lynxjournal.php';

register_deactivation_hook(LYNXJOURNAL_PLUGIN_FILE, function() {
    wp_clear_scheduled_hook('lynxjournal_execute_schedule');
});

LynxJournal::register();
