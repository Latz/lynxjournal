<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the Dashboard form-submission handlers:
 * handleBatchPublishRequest(), handleRoundupRequest(), handleQuickAddRequest().
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST = [];
});

afterEach(function (): void {
    $_POST = [];
});

describe('LynxJournal::handleBatchPublishRequest()', function (): void {

    it('returns null when the submit key is absent', function (): void {
        $_POST = [];

        expect($this->plugin->handleBatchPublishRequest())->toBeNull();
    });

    it('returns null when the nonce is invalid', function (): void {
        $_POST = ['lynxjournal_batch_publish' => '1', 'lynxjournal_batch_nonce' => 'bad'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        expect($this->plugin->handleBatchPublishRequest())->toBeNull();
    });

    it('returns null when the user lacks publish_posts capability', function (): void {
        $_POST = ['lynxjournal_batch_publish' => '1', 'lynxjournal_batch_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        expect($this->plugin->handleBatchPublishRequest())->toBeNull();
    });

    it('delegates to batchPublishLinks() with unpublished link IDs when valid', function (): void {
        $_POST = ['lynxjournal_batch_publish' => '1', 'lynxjournal_batch_nonce' => 'valid', 'publish_as_draft' => '1'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $this->plugin->shouldReceive('getUnpublishedLinkIds')->once()->andReturn([1, 2]);
        $this->plugin->shouldReceive('batchPublishLinks')->once()->with([1, 2], true)->andReturn(['success' => 2, 'failed' => 0, 'messages' => []]);

        $result = $this->plugin->handleBatchPublishRequest();

        expect($result['success'])->toBe(2);
    });
});

describe('LynxJournal::handleRoundupRequest()', function (): void {

    it('returns null when the submit key is absent', function (): void {
        $_POST = [];

        expect($this->plugin->handleRoundupRequest())->toBeNull();
    });

    it('returns null when the nonce is invalid', function (): void {
        $_POST = ['lynxjournal_create_roundup' => '1', 'lynxjournal_roundup_nonce' => 'bad'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        expect($this->plugin->handleRoundupRequest())->toBeNull();
    });

    it('returns null when the user lacks publish_posts capability', function (): void {
        $_POST = ['lynxjournal_create_roundup' => '1', 'lynxjournal_roundup_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        expect($this->plugin->handleRoundupRequest())->toBeNull();
    });

    it('delegates to createRoundupPost() with unpublished link IDs when valid', function (): void {
        $_POST = [
            'lynxjournal_create_roundup' => '1',
            'lynxjournal_roundup_nonce'  => 'valid',
            'roundup_title'              => 'My Roundup',
            'roundup_as_draft'           => '1',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $this->plugin->shouldReceive('getUnpublishedLinkIds')->once()->andReturn([3, 4]);
        $this->plugin->shouldReceive('createRoundupPost')->once()->with([3, 4], 'My Roundup', true)->andReturn(['success' => true, 'post_id' => 10]);

        $result = $this->plugin->handleRoundupRequest();

        expect($result['success'])->toBeTrue();
    });
});

describe('LynxJournal::handleQuickAddRequest()', function (): void {

    it('returns false when the submit key is absent', function (): void {
        $_POST = [];

        expect($this->plugin->handleQuickAddRequest())->toBeFalse();
    });

    it('returns false when required fields are missing', function (): void {
        $_POST = ['lynxjournal_quick_add' => '1', 'lynxjournal_quick_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('esc_url_raw')->returnArg();

        expect($this->plugin->handleQuickAddRequest())->toBeFalse();
    });

    it('returns false when the nonce is invalid', function (): void {
        $_POST = [
            'lynxjournal_quick_add'    => '1',
            'lynxjournal_quick_nonce'  => 'bad',
            'quick_title'              => 'Title',
            'quick_url'                => 'https://example.com',
            'quick_category'           => '2',
        ];
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('esc_url_raw')->returnArg();

        expect($this->plugin->handleQuickAddRequest())->toBeFalse();
    });

    it('returns false when the user lacks edit_posts capability', function (): void {
        $_POST = [
            'lynxjournal_quick_add'    => '1',
            'lynxjournal_quick_nonce'  => 'valid',
            'quick_title'              => 'Title',
            'quick_url'                => 'https://example.com',
            'quick_category'           => '2',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('current_user_can')->justReturn(false);

        expect($this->plugin->handleQuickAddRequest())->toBeFalse();
    });

    it('inserts a link with URL and category when valid', function (): void {
        $_POST = [
            'lynxjournal_quick_add'    => '1',
            'lynxjournal_quick_nonce'  => 'valid',
            'quick_title'              => 'Title',
            'quick_url'                => 'https://example.com',
            'quick_category'           => '2',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_post')->justReturn(55);

        $meta_calls = [];
        Functions\when('update_post_meta')->alias(function (...$args) use (&$meta_calls): bool {
            $meta_calls[] = $args;
            return true;
        });
        $term_calls = [];
        Functions\when('wp_set_post_terms')->alias(function (...$args) use (&$term_calls): array {
            $term_calls[] = $args;
            return [];
        });

        $result = $this->plugin->handleQuickAddRequest();

        expect($result)->toBeTrue();
        expect($meta_calls)->toHaveCount(1);
        expect($meta_calls[0])->toBe([55, '_lynxjournal_url', 'https://example.com']);
        expect($term_calls)->toHaveCount(1);
        expect($term_calls[0])->toBe([55, [2], 'lynxjournal_category']);
    });

    it('returns false when wp_insert_post fails', function (): void {
        $_POST = [
            'lynxjournal_quick_add'    => '1',
            'lynxjournal_quick_nonce'  => 'valid',
            'quick_title'              => 'Title',
            'quick_url'                => 'https://example.com',
            'quick_category'           => '2',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_post')->justReturn(0);

        expect($this->plugin->handleQuickAddRequest())->toBeFalse();
    });
});
