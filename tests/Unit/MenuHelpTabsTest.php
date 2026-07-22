<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::scheduleHelpTabs()', function (): void { // NOSONAR

    it('does nothing when there is no current screen', function (): void {
        Functions\when('get_current_screen')->justReturn(false);

        $this->plugin->scheduleHelpTabs();
    })->throwsNoExceptions();

    it('registers one help tab per notification channel plus an overview tab', function (): void {
        $tabs = [];
        $screen = Mockery::mock();
        $screen->shouldReceive('add_help_tab')
            ->andReturnUsing(function (array $tab) use (&$tabs): void {
                $tabs[] = $tab;
            });
        Functions\when('get_current_screen')->justReturn($screen);

        $this->plugin->scheduleHelpTabs();

        $ids = array_column($tabs, 'id');
        expect($ids)->toBe([
            'lynxjournal-help-overview',
            'lynxjournal-help-discord',
            'lynxjournal-help-slack',
            'lynxjournal-help-telegram',
            'lynxjournal-help-mastodon',
        ]);

        foreach ($tabs as $tab) {
            expect($tab['title'])->not->toBe('');
            expect($tab['content'])->not->toBe('');
        }
    });
});

describe('LynxJournal_Admin_Menu::adminMenu() Schedule help hook', function (): void { // NOSONAR

    it('hooks scheduleHelpTabs onto the load-{hook} action for the Schedule page hook suffix', function (): void {
        Functions\when('add_menu_page')->justReturn('lynxjournal-dashboard');
        Functions\when('add_submenu_page')->alias(
            fn($parent, $page_title, $menu_title, $cap, $slug) => $slug === 'lynxjournal-schedule' ? 'toplevel_page_lynxjournal-schedule' : 'some-other-hook'
        );

        $registered = [];
        Functions\when('add_action')->alias(function ($hook, $cb) use (&$registered): void {
            $registered[$hook] = $cb;
        });

        $this->plugin->adminMenu();

        expect($registered)->toHaveKey('load-toplevel_page_lynxjournal-schedule');
    });
});
