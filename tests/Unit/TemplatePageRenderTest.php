<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('get_option')->justReturn('');
    Functions\when('wp_nonce_field')->justReturn('');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST = [];
});

afterEach(function (): void {
    $_POST = [];
});

describe('LynxJournal::templatePage() — save handling', function (): void {

    it('does not save when the nonce field is absent from POST', function (): void {
        $_POST = ['lynxjournal_post_template' => 'new template text'];

        $called = false;
        Functions\when('update_option')->alias(function () use (&$called): bool {
            $called = true;
            return true;
        });

        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($called)->toBeFalse();
        expect($html)->not->toContain('Template saved.');
    });

    it('does not save when the nonce is invalid', function (): void {
        $_POST = [
            'lynxjournal_post_template' => 'new template text',
            'lynxjournal_template_nonce' => 'bad-nonce',
        ];
        Functions\when('wp_verify_nonce')->justReturn(false);

        $called = false;
        Functions\when('update_option')->alias(function () use (&$called): bool {
            $called = true;
            return true;
        });

        ob_start();
        $this->plugin->templatePage();
        ob_get_clean();

        expect($called)->toBeFalse();
    });

    it('does not save when the user lacks the edit_posts capability', function (): void {
        $_POST = [
            'lynxjournal_post_template' => 'new template text',
            'lynxjournal_template_nonce' => 'good-nonce',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $called = false;
        Functions\when('update_option')->alias(function () use (&$called): bool {
            $called = true;
            return true;
        });

        ob_start();
        $this->plugin->templatePage();
        ob_get_clean();

        expect($called)->toBeFalse();
    });

    it('saves the sanitized template and shows a success notice on valid submission', function (): void {
        $_POST = [
            'lynxjournal_post_template' => 'new template text',
            'lynxjournal_template_nonce' => 'good-nonce',
        ];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $savedKey = null;
        $savedVal = null;
        Functions\when('update_option')->alias(function (string $key, mixed $val) use (&$savedKey, &$savedVal): bool {
            $savedKey = $key;
            $savedVal = $val;
            return true;
        });

        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($savedKey)->toBe('lynxjournal_post_template');
        expect($savedVal)->toBe('new template text');
        expect($html)->toContain('Template saved.');
    });
});

describe('LynxJournal::templatePage() — rendering', function (): void {

    it('renders the stored template value inside the textarea, escaped', function (): void {
        Functions\when('get_option')->justReturn('<script>alert(1)</script>');

        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
        expect($html)->not->toContain('<script>alert(1)</script>');
    });

    it('renders a token group heading and its token buttons', function (): void {
        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($html)->toContain('Structure');
        expect($html)->toContain('data-token="[category_start]"');
        expect($html)->toContain('data-token="[link]"');
    });

    it('renders the token search input', function (): void {
        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($html)->toContain('id="lynxjournal-token-search"');
    });

    it('renders the preview width toggle buttons', function (): void {
        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($html)->toContain('data-width="desktop"');
        expect($html)->toContain('data-width="mobile"');
    });

    it('renders aria-labels on the toolbar buttons', function (): void {
        ob_start();
        $this->plugin->templatePage();
        $html = ob_get_clean();

        expect($html)->toContain('aria-label="Bold"');
        expect($html)->toContain('aria-label="Undo"');
    });
});
