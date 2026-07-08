<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the Menu.php admin trait: menu registration, filters,
 * the settings/schedule/settingX pages, and asset enqueueing.
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('esc_html_e')->alias(function (string $t): void {
        echo htmlspecialchars($t);
    });
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr_e')->alias(function (string $t): void {
        echo htmlspecialchars($t);
    });
    Functions\when('esc_attr')->alias(fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('plugins_url')->justReturn('https://example.com/wp-content/plugins/lynxjournal/assets/icon-menu.png');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST = [];
    $_GET  = [];
});

afterEach(function (): void {
    $_POST = [];
    $_GET  = [];
    unset($GLOBALS['pagenow']);
});

describe('LynxJournal::adminMenu()', function (): void { // NOSONAR

    it('registers the top-level menu page and all eight submenus', function (): void {
        Functions\when('add_menu_page')->justReturn('lynxjournal-dashboard');
        $submenu_calls = [];
        Functions\when('add_submenu_page')->alias(function (...$args) use (&$submenu_calls) {
            $submenu_calls[] = $args;
            return 'x';
        });

        $this->plugin->adminMenu();

        expect($submenu_calls)->toHaveCount(8);
        expect(array_column($submenu_calls, 4))->toBe([
            'lynxjournal-dashboard',
            'lynxjournal-admin',
            'lynxjournal-add',
            'lynxjournal-categories',
            'edit-tags.php?taxonomy=lynxjournal_tag&post_type=lynx-journal',
            'lynxjournal-settings',
            'lynxjournal-schedule',
            'lynxjournal-template',
        ]);
        expect($submenu_calls[0][0])->toBe('lynxjournal-dashboard');
    });

    it('passes null as the render callback for the Tags submenu', function (): void {
        Functions\when('add_menu_page')->justReturn('lynxjournal-dashboard');
        $submenu_calls = [];
        Functions\when('add_submenu_page')->alias(function (...$args) use (&$submenu_calls) {
            $submenu_calls[] = $args;
            return 'x';
        });

        $this->plugin->adminMenu();

        expect($submenu_calls[4][5])->toBeNull();
        expect($submenu_calls[0][5])->toBe([$this->plugin, 'dashboardPage']);
    });
});

describe('LynxJournal::parentFileFilter() / submenuFileFilter()', function (): void { // NOSONAR

    it('returns the lynxjournal dashboard file when on the lynxjournal_tag screen', function (): void {
        $GLOBALS['pagenow'] = 'edit-tags.php';
        $_GET['taxonomy']   = 'lynxjournal_tag';
        Functions\when('sanitize_key')->returnArg();

        expect($this->plugin->parentFileFilter('edit.php'))->toBe('lynxjournal-dashboard');
        expect($this->plugin->submenuFileFilter(null))->toBe('edit-tags.php?taxonomy=lynxjournal_tag&post_type=lynx-journal');
    });

    it('passes through the original file when not on the lynxjournal_tag screen', function (): void {
        $GLOBALS['pagenow'] = 'edit.php';

        expect($this->plugin->parentFileFilter('edit.php'))->toBe('edit.php');
        expect($this->plugin->submenuFileFilter('some-file.php'))->toBe('some-file.php');
    });

    it('falls back to an empty string when submenu_file is null and not on the tag screen', function (): void {
        $GLOBALS['pagenow'] = 'edit.php';

        expect($this->plugin->submenuFileFilter(null))->toBe('');
    });

    it('returns false for a different taxonomy on the edit-tags screen', function (): void {
        $GLOBALS['pagenow'] = 'edit-tags.php';
        $_GET['taxonomy']   = 'category';
        Functions\when('sanitize_key')->returnArg();

        expect($this->plugin->parentFileFilter('edit.php'))->toBe('edit.php');
    });
});

describe('LynxJournal::settingsPage()', function (): void { // NOSONAR

    beforeEach(function (): void {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('rest_url')->justReturn('https://example.com/wp-json/lynxjournal/v1');
        Functions\when('wp_nonce_field')->justReturn('');
    });

    it('does not show API key fields when none exists yet', function (): void {
        Functions\when('get_option')->justReturn(false);

        ob_start();
        $this->plugin->settingsPage();
        $html = ob_get_clean();

        expect($html)->not->toContain('lynxjournal-api-key');
        expect($html)->toContain('Generate API Key');
    });

    it('shows the API key fields when a key exists', function (): void {
        Functions\when('get_option')->justReturn('secret-key-123');

        ob_start();
        $this->plugin->settingsPage();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-api-key');
        expect($html)->toContain('secret-key-123');
        expect($html)->toContain('Generate New API Key');
    });

    it('generates a new API key on valid submission and shows a success notice', function (): void {
        $_POST = [
            'lynxjournal_generate_api_key' => '1',
            'lynxjournal_settings_nonce'   => 'valid',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_generate_password')->justReturn('newly-generated-key');
        Functions\when('get_option')->justReturn(false);

        $updated = null;
        Functions\when('update_option')->alias(function (string $opt, $val) use (&$updated) {
            $updated = [$opt, $val];
            return true;
        });

        ob_start();
        $this->plugin->settingsPage();
        $html = ob_get_clean();

        expect($html)->toContain('New API key generated successfully!');
        expect($updated)->toBe(['lynxjournal_api_key', 'newly-generated-key']);
    });

    it('does not generate a key when the nonce is invalid', function (): void {
        $_POST = [
            'lynxjournal_generate_api_key' => '1',
            'lynxjournal_settings_nonce'   => 'bad',
        ];
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('get_option')->justReturn(false);

        ob_start();
        $this->plugin->settingsPage();
        $html = ob_get_clean();

        expect($html)->not->toContain('New API key generated successfully!');
    });

    it('does not generate a key when the user lacks edit_posts capability', function (): void {
        $_POST = [
            'lynxjournal_generate_api_key' => '1',
            'lynxjournal_settings_nonce'   => 'valid',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('get_option')->justReturn(false);

        ob_start();
        $this->plugin->settingsPage();
        $html = ob_get_clean();

        expect($html)->not->toContain('New API key generated successfully!');
    });
});

describe('LynxJournal::schedulePage()', function (): void { // NOSONAR

    it('renders the schedule root container', function (): void {
        ob_start();
        $this->plugin->schedulePage();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-schedule-root');
    });
});

describe('LynxJournal::settingXPage()', function (): void { // NOSONAR

    it('renders the settings wrap', function (): void {
        ob_start();
        $this->plugin->settingXPage();
        $html = ob_get_clean();

        expect($html)->toContain('class="wrap"');
    });
});

describe('LynxJournal::addDashboardWidget()', function (): void { // NOSONAR

    it('registers the dashboard widget with the correct id and callback', function (): void {
        $captured = null;
        Functions\when('wp_add_dashboard_widget')->alias(function (...$args) use (&$captured) {
            $captured = $args;
            return null;
        });

        $this->plugin->addDashboardWidget();

        expect($captured[0])->toBe('lynxjournal_dashboard_widget');
        expect($captured[2])->toBe([$this->plugin, 'dashboardWidgetContent']);
    });
});

describe('LynxJournal::enqueueAdminAssets()', function (): void { // NOSONAR

    beforeEach(function (): void {
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/lynxjournal/');
        Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__, 2) . '/');
        Functions\when('wp_enqueue_style')->justReturn(null);
        Functions\when('wp_enqueue_script')->justReturn(null);
        Functions\when('wp_localize_script')->justReturn(null);
        Functions\when('rest_url')->justReturn('https://example.com/wp-json/lynxjournal/v1/links/');
        Functions\when('wp_create_nonce')->justReturn('nonce123');
        Functions\when('wp_timezone_string')->justReturn('UTC');
    });

    it('does nothing beyond the dashboard CSS for a non-lynxjournal hook', function (): void {
        $enqueued_scripts = [];
        Functions\when('wp_enqueue_script')->alias(function (...$a) use (&$enqueued_scripts) {
            $enqueued_scripts[] = $a[0];
            return null;
        });

        $this->plugin->enqueueAdminAssets('edit.php');

        expect($enqueued_scripts)->toBe([]);
    });

    it('loads the dashboard CSS on the WP core dashboard (index.php)', function (): void {
        $styles = [];
        Functions\when('wp_enqueue_style')->alias(function (...$a) use (&$styles) {
            $styles[] = $a[0];
            return null;
        });

        $this->plugin->enqueueAdminAssets('index.php');

        expect($styles)->toContain('lynxjournal-dashboard');
    });

    it('enqueues dashboard scripts and localizes data for the dashboard hook', function (): void {
        $localized = [];
        Functions\when('wp_localize_script')->alias(function (...$a) use (&$localized) {
            $localized[] = $a;
            return null;
        });

        $this->plugin->enqueueAdminAssets('toplevel_page_lynxjournal-dashboard');

        expect($localized)->toHaveCount(1);
        expect($localized[0][0])->toBe('lynxjournal-dashboard-js');
        expect($localized[0][1])->toBe('lynxjournalDash');
    });

    it('enqueues settings scripts and localizes data for the settings hook', function (): void {
        $localized = [];
        Functions\when('wp_localize_script')->alias(function (...$a) use (&$localized) {
            $localized[] = $a;
            return null;
        });

        // Note: WP's real submenu hook suffix is "lynxjournal-dashboard_page_lynxjournal-settings",
        // which also matches the dashboard-hook substring check, so both localize calls fire.
        $this->plugin->enqueueAdminAssets('lynxjournal-dashboard_page_lynxjournal-settings');

        expect(array_column($localized, 1))->toContain('lynxjournalSettings');
    });

    it('enqueues the links page script for the admin (links) hook', function (): void {
        $scripts = [];
        Functions\when('wp_enqueue_script')->alias(function (...$a) use (&$scripts) {
            $scripts[] = $a[0];
            return null;
        });

        $this->plugin->enqueueAdminAssets('lynxjournal-dashboard_page_lynxjournal-admin');

        expect($scripts)->toContain('lynxjournal-links-page');
    });

    it('enqueues categories scripts and localizes data for the categories hook', function (): void {
        $localized = [];
        Functions\when('wp_localize_script')->alias(function (...$a) use (&$localized) {
            $localized[] = $a;
            return null;
        });

        $this->plugin->enqueueAdminAssets('lynxjournal-dashboard_page_lynxjournal-categories');

        expect(array_column($localized, 1))->toContain('lynxjournalCats');
    });

    it('enqueues the schedule script, style, and localized data using the real asset file', function (): void {
        $scripts = [];
        $styles  = [];
        Functions\when('wp_enqueue_script')->alias(function (...$a) use (&$scripts) {
            $scripts[] = $a;
            return null;
        });
        Functions\when('wp_enqueue_style')->alias(function (...$a) use (&$styles) {
            $styles[] = $a;
            return null;
        });

        $this->plugin->enqueueAdminAssets('lynxjournal-dashboard_page_lynxjournal-schedule');

        expect(array_column($scripts, 0))->toContain('lynxjournal-schedule');
        expect(array_column($styles, 0))->toContain('lynxjournal-schedule-style');
    });
});
