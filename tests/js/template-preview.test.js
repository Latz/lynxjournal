import { describe, it, expect, vi } from 'vitest';
import { convertIndentedLines } from '../../src/js/template-preview.js';

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
