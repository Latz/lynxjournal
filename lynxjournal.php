<?php
/**
 * Plugin Name: LynxJournal
 * Description: Self-hosted link aggregation and micro-blogging. Transform web references into structured blog posts.
 * Version: 1.1.0
 * Author: Latz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lynx-journal
 * Domain Path: /languages
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

// Composer dependencies (league/commonmark, used by TemplateRenderer.php).
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/php/ScheduleMode.php';

// Traits (must be required before the class)
require_once __DIR__ . '/src/php/traits/trait-post-type.php';
require_once __DIR__ . '/src/php/traits/MetaBoxes.php';
require_once __DIR__ . '/src/php/traits/Publishing.php';
require_once __DIR__ . '/src/php/traits/Batch.php';
require_once __DIR__ . '/src/php/traits/TemplateRenderer.php';
require_once __DIR__ . '/src/php/traits/Queries.php';
require_once __DIR__ . '/src/php/traits/ScheduleValidator.php';
require_once __DIR__ . '/src/php/traits/RestApi.php';
require_once __DIR__ . '/src/php/traits/RestApiSupport.php';
require_once __DIR__ . '/src/php/traits/Admin/Menu.php';
require_once __DIR__ . '/src/php/traits/Admin/Dashboard.php';
require_once __DIR__ . '/src/php/traits/Admin/DashboardActions.php';
require_once __DIR__ . '/src/php/traits/Admin/LinksPage.php';
require_once __DIR__ . '/src/php/traits/Admin/AddLink.php';
require_once __DIR__ . '/src/php/traits/Admin/Categories.php';
require_once __DIR__ . '/src/php/traits/Admin/TemplatePage.php';
require_once __DIR__ . '/src/php/traits/Admin/NotificationFailureNotice.php';
require_once __DIR__ . '/src/php/traits/Scheduler.php';

// Notification channels (must be required before the class)
require_once __DIR__ . '/src/php/notifications/Channel.php';
require_once __DIR__ . '/src/php/notifications/Http.php';
require_once __DIR__ . '/src/php/notifications/RunMessageContent.php';
require_once __DIR__ . '/src/php/notifications/channels/EmailChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/DiscordChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/SlackBase.php';
require_once __DIR__ . '/src/php/notifications/channels/SlackChannelChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/SlackDmChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/TelegramBase.php';
require_once __DIR__ . '/src/php/notifications/channels/TelegramChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/TelegramDmChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/MastodonChannel.php';
require_once __DIR__ . '/src/php/notifications/channels/BlueskyChannel.php';
require_once __DIR__ . '/src/php/notifications/Manager.php';

require_once __DIR__ . '/src/php/class-lynxjournal.php';

register_deactivation_hook(LYNXJOURNAL_PLUGIN_FILE, function() {
    wp_clear_scheduled_hook('lynxjournal_execute_schedule');
});

LynxJournal::register();
