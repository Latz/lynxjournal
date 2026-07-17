<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
});

describe('LynxJournal_Notify_RunMessageContent::forRun()', function (): void {

    it('builds published content from the post when post_id is given', function (): void {
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');

        $content = LynxJournal_Notify_RunMessageContent::forRun(42, 3, 'daily');

        expect($content->published)->toBeTrue();
        expect($content->title)->toBe('Links: April 15, 2026');
        expect($content->url)->toBe('https://site.example/roundup-42');
        expect($content->summary)->toContain('3');
    });

    it('builds a neutral summary with no title/url when post_id is null', function (): void {
        $content = LynxJournal_Notify_RunMessageContent::forRun(null, 2, 'count');

        expect($content->published)->toBeFalse();
        expect($content->title)->toBeNull();
        expect($content->url)->toBeNull();
        expect($content->summary)->toContain('count');
    });
});
