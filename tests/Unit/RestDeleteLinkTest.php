<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::restDeleteLink()', function (): void {

    it('returns 404 WP_Error when post type is not lynx-journal', function (): void {
        Functions\when('get_post_type')->justReturn('post');

        $request = lynxjournal_make_request(['id' => 99]);

        $result = $this->plugin->restDeleteLink($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_link');
        expect($result->get_error_data()['status'])->toBe(404);
    });

    it('returns 500 WP_Error when wp_delete_post fails', function (): void {
        Functions\when('get_post_type')->justReturn('lynx-journal');
        Functions\when('wp_delete_post')->justReturn(false);

        $request = lynxjournal_make_request(['id' => 99]);

        $result = $this->plugin->restDeleteLink($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('delete_failed');
        expect($result->get_error_data()['status'])->toBe(500);
    });

    it('returns 204 response and deletes stats transient on success', function (): void {
        Functions\when('get_post_type')->justReturn('lynx-journal');

        $post      = new WP_Post();
        $post->ID  = 99;
        Functions\when('wp_delete_post')->justReturn($post);

        $deletedTransient = null;
        Functions\when('delete_transient')->alias(function (string $key) use (&$deletedTransient): bool {
            $deletedTransient = $key;
            return true;
        });

        $request = lynxjournal_make_request(['id' => 99]);

        $result = $this->plugin->restDeleteLink($request);

        expect($result)->toBeInstanceOf(WP_REST_Response::class);
        expect($result->get_status())->toBe(204);
        expect($deletedTransient)->toBe('lynxjournal_publish_stats');
    });
});
