<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal::processLinksPageAction() (private, invoked via reflection)
 * and its helpers: resolveLinksPageActionContext(), userCanPerformLinkAction(),
 * executeLinksPageAction(), executeDeleteAction().
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('sanitize_key')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    Functions\when('absint')->alias(fn($v) => abs((int) $v));
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $this->method  = new \ReflectionMethod(LynxJournal::class, 'processLinksPageAction');
    $_GET = [];
});

afterEach(function (): void {
    $_GET = [];
});

describe('LynxJournal::processLinksPageAction()', function (): void {

    it('returns empty message/error when action params are absent', function (): void {
        $_GET = [];

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('');
    });

    it('returns empty message/error when the nonce is invalid', function (): void {
        $_GET = ['action' => 'delete', 'link_id' => '7', '_wpnonce' => 'bad'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('');
    });

    it('returns empty message/error for an unrecognized action', function (): void {
        $_GET = ['action' => 'bogus', 'link_id' => '7', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('');
    });

    it('returns empty message/error when the user lacks the required capability', function (): void {
        $_GET = ['action' => 'delete', 'link_id' => '7', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('');
    });

    it('deletes the link and returns a success message for the delete action', function (): void {
        $_GET = ['action' => 'delete', 'link_id' => '7', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $deleted = null;
        Functions\when('wp_delete_post')->alias(function (int $id) use (&$deleted) {
            $deleted = $id;
            return lynxjournal_make_post($id, 'Deleted Link');
        });

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($deleted)->toBe(7);
        expect($message)->toBe('Link deleted successfully.');
        expect($error)->toBe('');
    });

    it('delegates to createBlogPost() for the publish_link action', function (): void {
        $_GET = ['action' => 'publish_link', 'link_id' => '3', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/post/1');

        $this->plugin->shouldReceive('createBlogPost')->once()->with(3, false)->andReturn(['success' => true, 'message' => 'Published!', 'post_id' => 1]);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toContain('Published!');
        expect($error)->toBe('');
    });

    it('delegates to createBlogPost() as a draft for the draft_link action', function (): void {
        $_GET = ['action' => 'draft_link', 'link_id' => '3', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_edit_post_link')->justReturn('https://example.com/wp-admin/edit?p=1');

        $this->plugin->shouldReceive('createBlogPost')->once()->with(3, true)->andReturn(['success' => true, 'message' => 'Draft saved!', 'post_id' => 1]);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toContain('Draft saved!');
    });

    it('returns the failure message when createBlogPost() fails', function (): void {
        $_GET = ['action' => 'publish_link', 'link_id' => '3', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $this->plugin->shouldReceive('createBlogPost')->once()->with(3, false)->andReturn(['success' => false, 'message' => 'Failed!']);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('Failed!');
    });

    it('delegates to unpublishLink() for the unpublish_link action', function (): void {
        $_GET = ['action' => 'unpublish_link', 'link_id' => '9', '_wpnonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $this->plugin->shouldReceive('unpublishLink')->once()->with(9)->andReturn(['success' => true, 'message' => 'Unpublished!']);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('Unpublished!');
        expect($error)->toBe('');
    });
});
