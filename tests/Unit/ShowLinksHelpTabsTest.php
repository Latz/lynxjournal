<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::showLinksHelpTabs()', function (): void { // NOSONAR

    it('does nothing when there is no current screen', function (): void {
        Functions\when('get_current_screen')->justReturn(false);

        $this->plugin->showLinksHelpTabs();
    })->throwsNoExceptions();

    it('registers one help tab per topic plus an overview tab', function (): void {
        $tabs = [];
        $screen = Mockery::mock();
        $screen->shouldReceive('add_help_tab')
            ->andReturnUsing(function (array $tab) use (&$tabs): void {
                $tabs[] = $tab;
            });
        Functions\when('get_current_screen')->justReturn($screen);

        $this->plugin->showLinksHelpTabs();

        $ids = array_column($tabs, 'id');
        expect($ids)->toBe([
            'lynxjournal-help-overview',
            'lynxjournal-help-status',
            'lynxjournal-help-actions',
        ]);

        foreach ($tabs as $tab) {
            expect($tab['title'])->not->toBe('');
            expect($tab['content'])->not->toBe('');
        }
    });
});

describe('LynxJournal_Admin_Menu::adminMenu() All Links help hook', function (): void { // NOSONAR

    it('hooks showLinksHelpTabs onto the load-{hook} action for the All Links page hook suffix', function (): void {
        Functions\when('add_menu_page')->justReturn('lynxjournal-dashboard');
        Functions\when('add_submenu_page')->alias(
            fn($parent, $page_title, $menu_title, $cap, $slug) => $slug === 'lynxjournal-admin' ? 'lynxjournal-dashboard_page_lynxjournal-admin' : 'some-other-hook'
        );

        $registered = [];
        Functions\when('add_action')->alias(function ($hook, $cb) use (&$registered): void {
            $registered[$hook] = $cb;
        });

        $this->plugin->adminMenu();

        expect($registered)->toHaveKey('load-lynxjournal-dashboard_page_lynxjournal-admin');
    });
});
