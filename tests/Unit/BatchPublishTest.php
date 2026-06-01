<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for linkdigestBatchPublishLinks()
 */

beforeEach(function (): void {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('__')->returnArg();
    Functions\when('get_the_terms')->justReturn(false);
    Functions\when('current_time')->justReturn('2026-04-13 10:00:00');
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::batchPublishLinks()', function (): void {

    it('returns zeros and a message when called with an empty array', function (): void {
        $result = $this->plugin->batchPublishLinks([]);

        expect($result['success'])->toBe(0);
        expect($result['failed'])->toBe(0);
        expect($result['messages'])->not->toBeEmpty();
    });

    it('returns zeros and a message when called with a non-array', function (): void {
        $result = $this->plugin->batchPublishLinks('not-an-array');

        expect($result['success'])->toBe(0);
        expect($result['failed'])->toBe(0);
    });

    it('counts successes correctly when all links publish', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => linkdigest_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')->justReturn(99);
        Functions\when('update_post_meta')->justReturn(true);

        $result = $this->plugin->batchPublishLinks([1, 2, 3]);

        expect($result['success'])->toBe(3);
        expect($result['failed'])->toBe(0);
    });

    it('counts failures when links have no permission', function (): void {
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('get_post')->alias(fn($id) => linkdigest_make_post($id, "Link $id"));

        $result = $this->plugin->batchPublishLinks([1, 2]);

        expect($result['success'])->toBe(0);
        expect($result['failed'])->toBe(2);
        expect($result['messages'])->toHaveCount(2);
    });

    it('handles mixed success and failure correctly', function (): void {
        $calls = 0;
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => linkdigest_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')
            ->alias(function () use (&$calls): int {
                $calls++;
                return $calls === 2 ? 0 : 99; // second call fails
            });
        Functions\when('update_post_meta')->justReturn(true);

        $result = $this->plugin->batchPublishLinks([1, 2, 3]);

        expect($result['success'])->toBe(2);
        expect($result['failed'])->toBe(1);
    });
});
