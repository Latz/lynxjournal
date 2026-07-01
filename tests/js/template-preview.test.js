import { describe, it, expect, vi } from 'vitest';
import { convertIndentedLines, buildTemplateText, renderValidation, setPreviewUpdating, setPreviewLive } from '../../src/js/template-preview.js';
import { replaceTokens, expandLinkBlocks, expandLinkLines, preserveBlankLines } from '../../src/js/template-utils.js';

const utils = { replaceTokens, expandLinkBlocks, expandLinkLines, preserveBlankLines };

/**
 * Minimal stand-in for marked.parseInline(): renders bold and italic markup
 * and preserves embedded newlines verbatim, matching the real library's
 * behaviour that convertIndentedLines() relies on for batching.
 *
 * @param {string} markdown
 * @returns {string}
 */
function stubParseInline( markdown ) {
    return markdown
        .replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
        .replace( /\*(.+?)\*/g, '<em>$1</em>' );
}

describe( 'convertIndentedLines()', () => {
    it( 'leaves non-indented lines untouched', () => {
        expect( convertIndentedLines( 'plain line\nanother line', stubParseInline ) )
            .toBe( 'plain line\nanother line' );
    } );

    it( 'wraps a single indented line in a padded div', () => {
        const result = convertIndentedLines( '  indented text', stubParseInline );
        expect( result ).toBe( '<div style="padding-left:1.5em">indented text</div>' );
    } );

    it( 'scales padding by indent level', () => {
        const result = convertIndentedLines( '    double indent', stubParseInline );
        expect( result ).toBe( '<div style="padding-left:3em">double indent</div>' );
    } );

    it( 'prefixes list markers with a bullet character', () => {
        const result = convertIndentedLines( '  - list item', stubParseInline );
        expect( result ).toBe( '<div style="padding-left:1.5em">• list item</div>' );
    } );

    it( 'renders inline markdown within an indented line', () => {
        const result = convertIndentedLines( '  **bold** text', stubParseInline );
        expect( result ).toBe( '<div style="padding-left:1.5em"><strong>bold</strong> text</div>' );
    } );

    it( 'keeps indented and non-indented lines in their original order', () => {
        const input  = 'header\n  indented\nfooter';
        const result = convertIndentedLines( input, stubParseInline );
        expect( result ).toBe( 'header\n<div style="padding-left:1.5em">indented</div>\nfooter' );
    } );

    it( 'renders each line in a contiguous indented run independently', () => {
        const input  = '  first\n  second\n  third';
        const result = convertIndentedLines( input, stubParseInline );
        expect( result ).toBe(
            '<div style="padding-left:1.5em">first</div>\n' +
            '<div style="padding-left:1.5em">second</div>\n' +
            '<div style="padding-left:1.5em">third</div>'
        );
    } );

    it( 'calls parseInline once per contiguous indented run, not once per line', () => {
        const spy    = vi.fn( stubParseInline );
        const input  = '  a\n  b\n  c\nplain\n  d\n  e';
        convertIndentedLines( input, spy );
        // Two runs of indented lines ("a,b,c" and "d,e") → 2 calls, not 5.
        expect( spy ).toHaveBeenCalledTimes( 2 );
    } );

    it( 'returns an empty string unchanged', () => {
        expect( convertIndentedLines( '', stubParseInline ) ).toBe( '' );
    } );
} );

// ---------------------------------------------------------------------------
// buildTemplateText()
// ---------------------------------------------------------------------------

describe( 'buildTemplateText()', () => {
    it( 'expands a category block for a single category with multiple links', () => {
        const rawText = '[category_start]**[category_name]**\n[link_start]- [link]\n[link_end][category_end]';
        const categoryVariants = [ {
            '[category_name]': 'Tech',
            '[category_link_count]': '2',
            links: [ { '[link]': 'A' }, { '[link]': 'B' } ],
        } ];

        const result = buildTemplateText( rawText, categoryVariants, {}, utils );

        expect( result ).toBe( '**Tech**\n- A\n- B\n' );
    } );

    it( 'repeats the category block once per category', () => {
        const rawText = '[category_start][category_name]\n[category_end]';
        const categoryVariants = [
            { '[category_name]': 'Tech', '[category_link_count]': '0', links: [] },
            { '[category_name]': 'Design', '[category_link_count]': '0', links: [] },
        ];

        const result = buildTemplateText( rawText, categoryVariants, {}, utils );

        expect( result ).toBe( 'Tech\nDesign\n' );
    } );

    it( 'replaces scalar tokens outside category blocks', () => {
        const rawText = '# [title]\nby [author]';
        const result  = buildTemplateText( rawText, [], { '[title]': 'My Roundup', '[author]': 'Latz' }, utils );

        expect( result ).toBe( '# My Roundup\nby Latz' );
    } );

    it( 'produces an empty category expansion when categoryVariants is empty', () => {
        const rawText = 'Intro\n[category_start]anything[category_end]\nOutro';
        const result  = buildTemplateText( rawText, [], {}, utils );

        expect( result ).toBe( 'Intro\n\nOutro' );
    } );

    it( 'preserves blank lines through the full pipeline', () => {
        const rawText = 'Intro\n\nOutro';
        const result  = buildTemplateText( rawText, [], {}, utils );

        expect( result ).toContain( '<p class="lynxjournal-blank-line">&nbsp;</p>' );
    } );

    it( 'expands bare link tokens outside category blocks using the first category’s links', () => {
        const rawText = '[link_start]- [link]\n[link_end]';
        const categoryVariants = [ { '[category_name]': 'Tech', '[category_link_count]': '1', links: [ { '[link]': 'A' } ] } ];

        const result = buildTemplateText( rawText, categoryVariants, {}, utils );

        expect( result ).toBe( '- A\n' );
    } );
} );

// ---------------------------------------------------------------------------
// renderValidation()
// ---------------------------------------------------------------------------

describe( 'renderValidation()', () => {
    it( 'does nothing when previewValidation is null', () => {
        expect( () => renderValidation( null, [ 'some warning' ] ) ).not.toThrow();
    } );

    it( 'hides the panel when there are no warnings', () => {
        const el = document.createElement( 'div' );
        renderValidation( el, [] );
        expect( el.hidden ).toBe( true );
        expect( el.children ).toHaveLength( 0 );
    } );

    it( 'shows the panel and renders one warning element per message', () => {
        const el = document.createElement( 'div' );
        renderValidation( el, [ 'first issue', 'second issue' ] );

        expect( el.hidden ).toBe( false );
        const warnings = el.querySelectorAll( '.lynxjournal-preview-warning' );
        expect( warnings ).toHaveLength( 2 );
        expect( warnings[ 0 ].textContent ).toContain( 'first issue' );
        expect( warnings[ 1 ].textContent ).toContain( 'second issue' );
    } );

    it( 'replaces previously rendered warnings rather than appending', () => {
        const el = document.createElement( 'div' );
        renderValidation( el, [ 'stale warning' ] );
        renderValidation( el, [ 'fresh warning' ] );

        const warnings = el.querySelectorAll( '.lynxjournal-preview-warning' );
        expect( warnings ).toHaveLength( 1 );
        expect( warnings[ 0 ].textContent ).toContain( 'fresh warning' );
    } );
} );

// ---------------------------------------------------------------------------
// setPreviewUpdating() / setPreviewLive()
// ---------------------------------------------------------------------------

describe( 'setPreviewUpdating() / setPreviewLive()', () => {
    it( 'setPreviewUpdating() adds the "updating" class without changing the label text', () => {
        const status = document.createElement( 'span' );
        status.textContent = 'Live';

        setPreviewUpdating( status );

        expect( status.textContent ).toBe( 'Live' );
        expect( status.classList.contains( 'is-updating' ) ).toBe( true );
    } );

    it( 'setPreviewLive() removes the "updating" class without changing the label text', () => {
        const status = document.createElement( 'span' );
        status.textContent = 'Live';
        setPreviewUpdating( status );

        setPreviewLive( status );

        expect( status.textContent ).toBe( 'Live' );
        expect( status.classList.contains( 'is-updating' ) ).toBe( false );
    } );

    it( 'both functions tolerate a null status element without throwing', () => {
        expect( () => setPreviewUpdating( null ) ).not.toThrow();
        expect( () => setPreviewLive( null ) ).not.toThrow();
    } );
} );
