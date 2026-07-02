<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal_PostType::register_post_type()/register_taxonomies()
 * and the private register_post_statuses() helper.
 */

beforeEach(function (): void {
    Functions\when('_x')->returnArg();
    Functions\when('__')->returnArg();
    Functions\when('plugins_url')->justReturn('https://example.com/wp-content/plugins/lynxjournal/assets/icon-menu.png');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::register_post_type()', function (): void {

    it('registers the lynx-journal post type with the expected slug and key args', function (): void {
        $captured = null;
        Functions\when('register_post_type')->alias(function (string $slug, array $args) use (&$captured) {
            $captured = [$slug, $args];
            return null;
        });
        Functions\when('register_post_status')->justReturn(null);
        Functions\when('register_post_meta')->justReturn(null);
        Functions\when('_n_noop')->justReturn([]);

        $this->plugin->register_post_type();

        [$slug, $args] = $captured;
        expect($slug)->toBe('lynx-journal');
        expect($args['show_in_menu'])->toBeFalse();
        expect($args['show_in_rest'])->toBeTrue();
        expect($args['taxonomies'])->toBe(['lynxjournal_category', 'lynxjournal_tag']);
        expect($args['capability_type'])->toBe('post');
    });

    it('registers the publish-status and url post meta fields', function (): void {
        $meta_calls = [];
        Functions\when('register_post_type')->justReturn(null);
        Functions\when('register_post_status')->justReturn(null);
        Functions\when('_n_noop')->justReturn([]);
        Functions\when('register_post_meta')->alias(function (string $type, string $key, array $args) use (&$meta_calls) {
            $meta_calls[] = [$type, $key, $args];
            return true;
        });

        $this->plugin->register_post_type();

        expect($meta_calls)->toHaveCount(2);
        expect($meta_calls[0])->toBe(['lynx-journal', '_lynxjournal_publish_status', [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => '__return_true',
        ]]);
        expect($meta_calls[1])->toBe(['lynx-journal', '_lynxjournal_url', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback'     => '__return_true',
        ]]);
    });

    it('registers all three custom post statuses with their labels', function (): void {
        $status_calls = [];
        Functions\when('register_post_type')->justReturn(null);
        Functions\when('register_post_meta')->justReturn(null);
        Functions\when('_n_noop')->justReturn(['singular' => '', 'plural' => '', 'context' => null, 'domain' => null]);
        Functions\when('register_post_status')->alias(function (string $status, array $args) use (&$status_calls) {
            $status_calls[] = [$status, $args];
            return null;
        });

        $this->plugin->register_post_type();

        expect($status_calls)->toHaveCount(3);
        expect(array_column($status_calls, 0))->toBe(['lynxjournal_pending', 'lynxjournal_pub', 'lynxjournal_draft']);
        expect($status_calls[0][1]['public'])->toBeTrue();
        expect($status_calls[2][1]['public'])->toBeFalse();
    });
});

describe('LynxJournal::register_taxonomies()', function (): void {

    it('registers the category taxonomy on the lynx-journal post type', function (): void {
        $calls = [];
        Functions\when('register_taxonomy')->alias(function (string $tax, array $types, array $args) use (&$calls) {
            $calls[] = [$tax, $types, $args];
            return null;
        });

        $this->plugin->register_taxonomies();

        expect($calls)->toHaveCount(2);
        [$tax, $types, $args] = $calls[0];
        expect($tax)->toBe('lynxjournal_category');
        expect($types)->toBe(['lynx-journal']);
        expect($args['show_in_rest'])->toBeTrue();
        expect($args['capabilities']['manage_terms'])->toBe('edit_posts');
    });

    it('registers the tag taxonomy on the lynx-journal post type', function (): void {
        $calls = [];
        Functions\when('register_taxonomy')->alias(function (string $tax, array $types, array $args) use (&$calls) {
            $calls[] = [$tax, $types, $args];
            return null;
        });

        $this->plugin->register_taxonomies();

        [$tax, $types, $args] = $calls[1];
        expect($tax)->toBe('lynxjournal_tag');
        expect($types)->toBe(['lynx-journal']);
        expect($args['show_admin_column'])->toBeTrue();
    });
});
