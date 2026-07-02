<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the Categories admin-page handlers:
 * handleAddCategory() and handleDeleteCategory() (private, invoked via reflection).
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('sanitize_textarea_field')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST = [];
});

afterEach(function (): void {
    $_POST = [];
});

describe('LynxJournal::handleAddCategory()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'handleAddCategory');
    });

    it('returns a security error when the nonce is invalid', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'bad', 'cat_name' => 'Tech'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe('Security check failed.');
    });

    it('returns a permission error when the user cannot edit_posts', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_name' => 'Tech'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe('Insufficient permissions.');
    });

    it('returns a validation error when cat_name is empty', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe('Category name is required.');
    });

    it('returns the WP_Error message when wp_insert_term fails', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_name' => 'Tech'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_term')->justReturn(new WP_Error('term_exists', 'Category already exists.'));

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe('Category already exists.');
    });

    it('returns null and clears caches on success', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_name' => 'Tech', 'cat_description' => 'Tech stuff'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 5, 'term_taxonomy_id' => 5]);

        $cleared = [];
        Functions\when('delete_transient')->alias(function (string $key) use (&$cleared): bool {
            $cleared[] = $key;
            return true;
        });

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBeNull();
        expect($cleared)->toContain('lynxjournal_api_categories_list');
        expect($cleared)->toContain('lynxjournal_categories_terms');
    });
});

describe('LynxJournal::handleDeleteCategory()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'handleDeleteCategory');
    });

    it('returns false when the nonce is invalid', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'bad', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        expect($this->method->invoke($this->plugin))->toBeFalse();
    });

    it('returns false when the user cannot edit_posts', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        expect($this->method->invoke($this->plugin))->toBeFalse();
    });

    it('returns false when cat_term_id is missing', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        expect($this->method->invoke($this->plugin))->toBeFalse();
    });

    it('returns false when wp_delete_term fails', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_delete_term')->justReturn(new WP_Error('invalid_term', 'Invalid term.'));

        expect($this->method->invoke($this->plugin))->toBeFalse();
    });

    it('returns true and clears caches on success', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_delete_term')->justReturn(true);

        $cleared = [];
        Functions\when('delete_transient')->alias(function (string $key) use (&$cleared): bool {
            $cleared[] = $key;
            return true;
        });

        expect($this->method->invoke($this->plugin))->toBeTrue();
        expect($cleared)->toContain('lynxjournal_api_categories_list');
        expect($cleared)->toContain('lynxjournal_categories_terms');
    });
});
