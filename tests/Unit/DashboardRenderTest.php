<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('get_post_meta')->justReturn('');
    Functions\when('get_the_terms')->justReturn(false);
    Functions\when('mysql2date')->returnArg(2);
    Functions\when('get_permalink')->justReturn('https://example.com/post/1');
    Functions\when('get_edit_post_link')->justReturn('https://example.com/wp-admin/edit?p=1');
    Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin.php?page=lynxjournal');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::renderRecentlyPublishedBox()', function (): void { // NOSONAR

    it('shows empty message when no links provided', function (): void {
        ob_start();
        $this->plugin->renderRecentlyPublishedBox([]);
        $html = ob_get_clean();

        expect($html)->toContain('No published links yet.');
        expect($html)->not->toContain('lynxjournal-recent-links');
    });

    it('shows the link list when links are provided', function (): void {
        $link = lynxjournal_make_post(1, 'Test Link');

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-recent-links');
        expect($html)->toContain('Test Link');
    });

    it('shows no badge for a published link', function (): void {
        $link = lynxjournal_make_post(1, 'Published Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_publish_status' ? 'published' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->not->toContain('lynxjournal-status-published');
        expect($html)->not->toContain('lynxjournal-status-draft');
    });

    it('shows draft badge for a draft link', function (): void {
        $link = lynxjournal_make_post(1, 'Draft Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_publish_status' ? 'draft' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-status-draft');
        expect($html)->not->toContain('lynxjournal-status-published');
    });

    it('shows no badge when status is neither published nor draft', function (): void {
        $link = lynxjournal_make_post(1, 'Pending Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_publish_status' ? 'unpublished' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->not->toContain('lynxjournal-status-published');
        expect($html)->not->toContain('lynxjournal-status-draft');
    });

    it('shows View Post link for a published link with published_post_id', function (): void {
        $link = lynxjournal_make_post(1, 'Published Link');
        Functions\when('get_post_meta')->alias(function ($id, $key, $single) {
            return match ($key) {
                '_lynxjournal_publish_status'    => 'published',
                '_lynxjournal_published_post_id' => 42,
                default                       => '',
            };
        });

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('View Post');
        expect($html)->toContain('lynxjournal-link-url');
    });

    it('shows View Draft link for a draft link with published_post_id', function (): void {
        $link = lynxjournal_make_post(1, 'Draft Link');
        Functions\when('get_post_meta')->alias(function ($id, $key, $single) {
            return match ($key) {
                '_lynxjournal_publish_status'    => 'draft',
                '_lynxjournal_published_post_id' => 42,
                default                       => '',
            };
        });

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('View Draft');
    });

    it('shows the category name when the link has a category', function (): void {
        $link = lynxjournal_make_post(1, 'Categorised Link');
        $term = new stdClass();
        $term->name = 'Technology';
        Functions\when('get_the_terms')->justReturn([$term]);

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('Technology');
    });

    it('shows the published date when the link has one', function (): void {
        $link = lynxjournal_make_post(1, 'Dated Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_published_date' ? '2026-04-01 00:00:00' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('2026-04-01 00:00:00');
    });

    it('renders multiple links', function (): void {
        $link1 = lynxjournal_make_post(1, 'First Link');
        $link2 = lynxjournal_make_post(2, 'Second Link');

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link1, $link2]);
        $html = ob_get_clean();

        expect($html)->toContain('First Link');
        expect($html)->toContain('Second Link');
    });
});
