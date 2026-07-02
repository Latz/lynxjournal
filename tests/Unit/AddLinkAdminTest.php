<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the Add Link admin-page handlers:
 * processAddLinkSubmission() and getRepopulatedAddLinkFields()
 * (both private, invoked via reflection).
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    Functions\when('esc_url_raw')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST = [];
});

afterEach(function (): void {
    $_POST = [];
});

describe('LynxJournal::processAddLinkSubmission()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'processAddLinkSubmission');
    });

    it('returns empty message/error when the submit key is absent', function (): void {
        $_POST = [];

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('');
    });

    it('returns a security error when the nonce is invalid', function (): void {
        $_POST = ['lynxjournal_add_submit' => '1', 'lynxjournal_add_nonce' => 'bad', 'lynxjournal_title' => 'Title'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('Security check failed.');
    });

    it('returns a permission error when the user cannot edit_posts', function (): void {
        $_POST = ['lynxjournal_add_submit' => '1', 'lynxjournal_add_nonce' => 'valid', 'lynxjournal_title' => 'Title'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($error)->toBe('Insufficient permissions.');
    });

    it('returns a validation error when the title is empty', function (): void {
        $_POST = ['lynxjournal_add_submit' => '1', 'lynxjournal_add_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($error)->toBe('Title is required.');
    });

    it('returns a failure message when wp_insert_post fails', function (): void {
        $_POST = ['lynxjournal_add_submit' => '1', 'lynxjournal_add_nonce' => 'valid', 'lynxjournal_title' => 'Title'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_post')->justReturn(0);

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($message)->toBe('');
        expect($error)->toBe('Failed to add link.');
    });

    it('inserts a link with url/categories/tags and clears the stats cache on success', function (): void {
        $_POST = [
            'lynxjournal_add_submit' => '1',
            'lynxjournal_add_nonce'  => 'valid',
            'lynxjournal_title'      => 'Title',
            'lynxjournal_url'        => 'https://example.com',
            'lynxjournal_categories' => ['2', '3'],
            'lynxjournal_tags'       => 'tag1, tag2',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_post')->justReturn(42);

        $meta_calls = [];
        Functions\when('update_post_meta')->alias(function (...$args) use (&$meta_calls): bool {
            $meta_calls[] = $args;
            return true;
        });
        $term_calls = [];
        Functions\when('wp_set_object_terms')->alias(function (...$args) use (&$term_calls): array {
            $term_calls[] = $args;
            return [];
        });
        $cleared = [];
        Functions\when('delete_transient')->alias(function (string $key) use (&$cleared): bool {
            $cleared[] = $key;
            return true;
        });

        [$message, $error] = $this->method->invoke($this->plugin);

        expect($error)->toBe('');
        expect($message)->toBe('Link added successfully!');
        expect($meta_calls[0])->toBe([42, '_lynxjournal_url', 'https://example.com']);
        expect($term_calls)->toHaveCount(2);
        expect($term_calls[0])->toBe([42, [2, 3], 'lynxjournal_category']);
        expect($term_calls[1])->toBe([42, ['tag1', 'tag2'], 'lynxjournal_tag']);
        expect($cleared)->toContain('lynxjournal_publish_stats');
    });
});

describe('LynxJournal::getRepopulatedAddLinkFields()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'getRepopulatedAddLinkFields');
    });

    it('returns all-empty fields when the nonce is absent', function (): void {
        $_POST = ['lynxjournal_title' => 'Should not repopulate'];

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['title' => '', 'url' => '', 'content' => '', 'tags' => '', 'categories' => []]);
    });

    it('returns all-empty fields when the nonce is invalid', function (): void {
        $_POST = ['lynxjournal_add_nonce' => 'bad', 'lynxjournal_title' => 'Should not repopulate'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        $result = $this->method->invoke($this->plugin);

        expect($result['title'])->toBe('');
    });

    it('repopulates fields from POST when the nonce is valid', function (): void {
        $_POST = [
            'lynxjournal_add_nonce'  => 'valid',
            'lynxjournal_title'      => 'Draft Title',
            'lynxjournal_url'        => 'https://example.com',
            'lynxjournal_content'    => '<p>Body</p>',
            'lynxjournal_tags'       => 'a, b',
            'lynxjournal_categories' => ['4'],
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);

        $result = $this->method->invoke($this->plugin);

        expect($result['title'])->toBe('Draft Title');
        expect($result['url'])->toBe('https://example.com');
        expect($result['content'])->toBe('<p>Body</p>');
        expect($result['tags'])->toBe('a, b');
        expect($result['categories'])->toBe([4]);
    });
});
