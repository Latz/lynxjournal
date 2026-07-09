<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * @param mixed $method Expected method name on the registered callback array.
 * @return \Mockery\Matcher\Closure Matches a [LynxJournal instance, $method] callback array.
 */
function lynxjournalCallback(string $method): Mockery\Matcher\Closure {
    return Mockery::on(fn($cb) => is_array($cb) && $cb[0] instanceof LynxJournal && $cb[1] === $method);
}

describe('LynxJournal::register()', function (): void {

    it('registers the universal hooks and skips admin hooks outside admin context', function (): void {
        Functions\when('is_admin')->justReturn(false);

        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('register_post_type'), 0);
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('register_taxonomies'), 0);
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('maybeRunMigration'), 5);
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('registerSchedulerHooks'), 0);
        Functions\expect('add_action')->once()->with('created_lynxjournal_category', lynxjournalCallback('invalidateCategoriesCache'));
        Functions\expect('add_action')->once()->with('edited_lynxjournal_category', lynxjournalCallback('invalidateCategoriesCache'));
        Functions\expect('add_action')->once()->with('delete_lynxjournal_category', lynxjournalCallback('invalidateCategoriesCache'));
        Functions\expect('add_action')->once()->with('rest_api_init', lynxjournalCallback('register_rest_hooks'), 1);

        // Admin-only hooks must never be registered outside admin context.
        Functions\expect('add_action')->never()->with('add_meta_boxes', Mockery::any());
        Functions\expect('add_filter')->never()->with('parent_file', Mockery::any());

        expect(fn() => LynxJournal::register())->not->toThrow(Throwable::class);
    });

    it('also registers admin-specific hooks in admin context', function (): void {
        Functions\when('is_admin')->justReturn(true);

        // Universal hooks still fire.
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('register_post_type'), 0);
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('register_taxonomies'), 0);
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('maybeRunMigration'), 5);
        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('registerSchedulerHooks'), 0);
        Functions\expect('add_action')->once()->with('created_lynxjournal_category', lynxjournalCallback('invalidateCategoriesCache'));
        Functions\expect('add_action')->once()->with('edited_lynxjournal_category', lynxjournalCallback('invalidateCategoriesCache'));
        Functions\expect('add_action')->once()->with('delete_lynxjournal_category', lynxjournalCallback('invalidateCategoriesCache'));
        Functions\expect('add_action')->once()->with('rest_api_init', lynxjournalCallback('register_rest_hooks'), 1);

        // Admin-specific hooks.
        Functions\expect('add_action')->once()->with('add_meta_boxes', lynxjournalCallback('addMetaBoxes'));
        Functions\expect('add_action')->once()->with('save_post_lynx-journal', lynxjournalCallback('saveUrl'));
        Functions\expect('add_action')->once()->with('wp_ajax_lynxjournal_get_rest_nonce', lynxjournalCallback('handleGetRestNonce'));
        Functions\expect('add_action')->once()->with('admin_menu', lynxjournalCallback('adminMenu'));
        Functions\expect('add_action')->once()->with('admin_enqueue_scripts', lynxjournalCallback('enqueueAdminAssets'));
        Functions\expect('add_action')->once()->with('wp_dashboard_setup', lynxjournalCallback('addDashboardWidget'));
        Functions\expect('add_filter')->once()->with('parent_file', lynxjournalCallback('parentFileFilter'));
        Functions\expect('add_filter')->once()->with('submenu_file', lynxjournalCallback('submenuFileFilter'));

        expect(fn() => LynxJournal::register())->not->toThrow(Throwable::class);
    });
});

describe('LynxJournal::register_rest_hooks()', function (): void {

    beforeEach(function (): void {
        $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    });

    it('registers the preflight hook, the REST routes, and the CORS filter', function (): void {
        $this->plugin->shouldReceive('registerRestRoutes')->once();

        Functions\expect('add_action')->once()->with('init', lynxjournalCallback('handlePreflight'), 1);
        Functions\expect('add_filter')->once()->with('rest_pre_serve_request', lynxjournalCallback('addCorsHeaders'), 15);

        expect(fn() => $this->plugin->register_rest_hooks())->not->toThrow(Throwable::class);
    });
});
