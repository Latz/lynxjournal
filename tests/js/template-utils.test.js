import { describe, it, expect } from 'vitest';
import {
    LINK_TOKENS,
    replaceTokens,
    hasLinkToken,
    expandLinkBlocks,
    expandLinkLines,
    validateTemplate,
    getLineStart,
} from '../../src/js/template-utils.js';

// ---------------------------------------------------------------------------
// replaceTokens()
// ---------------------------------------------------------------------------

describe( 'replaceTokens()', () => {
    it( 'replaces a single token', () => {
        expect( replaceTokens( 'Hello [name]!', { '[name]': 'World' } ) ).toBe( 'Hello World!' );
    } );

    it( 'replaces multiple tokens', () => {
        const result = replaceTokens( '[a] and [b]', { '[a]': 'foo', '[b]': 'bar' } );
        expect( result ).toBe( 'foo and bar' );
    } );

    it( 'replaces every occurrence of a repeated token', () => {
        expect( replaceTokens( '[x] [x]', { '[x]': 'y' } ) ).toBe( 'y y' );
    } );

    it( 'leaves unknown tokens untouched', () => {
        expect( replaceTokens( '[unknown]', { '[other]': 'value' } ) ).toBe( '[unknown]' );
    } );

    it( 'returns empty string unchanged', () => {
        expect( replaceTokens( '', { '[a]': 'b' } ) ).toBe( '' );
    } );

    it( 'returns text unchanged when data is empty', () => {
        expect( replaceTokens( 'no tokens', {} ) ).toBe( 'no tokens' );
    } );
} );

// ---------------------------------------------------------------------------
// hasLinkToken()
// ---------------------------------------------------------------------------

describe( 'hasLinkToken()', () => {
    it.each( LINK_TOKENS )( 'returns true for %s', token => {
        expect( hasLinkToken( `prefix ${ token } suffix` ) ).toBe( true );
    } );

    it( 'returns false when no link token is present', () => {
        expect( hasLinkToken( 'just a regular line' ) ).toBe( false );
    } );

    it( 'returns false for empty string', () => {
        expect( hasLinkToken( '' ) ).toBe( false );
    } );
} );

// ---------------------------------------------------------------------------
// expandLinkBlocks()
// ---------------------------------------------------------------------------

describe( 'expandLinkBlocks()', () => {
    it( 'expands a block for a single link', () => {
        const links = [ { '[link]': 'https://a.com' } ];
        expect( expandLinkBlocks( '[link_start][link][link_end]', links ) ).toBe( 'https://a.com' );
    } );

    it( 'expands a block for multiple links, concatenating results', () => {
        const links = [ { '[link]': 'A' }, { '[link]': 'B' } ];
        expect( expandLinkBlocks( '[link_start]* [link]\n[link_end]', links ) ).toBe( '* A\n* B\n' );
    } );

    it( 'produces empty string when links array is empty', () => {
        expect( expandLinkBlocks( '[link_start]anything[link_end]', [] ) ).toBe( '' );
    } );

    it( 'passes through text with no link_start/link_end markers', () => {
        expect( expandLinkBlocks( 'plain text', [ { '[link]': 'x' } ] ) ).toBe( 'plain text' );
    } );

    it( 'handles multiple separate blocks', () => {
        const links = [ { '[link]': 'X' } ];
        const input = '[link_start]([link])[link_end] and [link_start]([link])[link_end]';
        expect( expandLinkBlocks( input, links ) ).toBe( '(X) and (X)' );
    } );
} );

// ---------------------------------------------------------------------------
// expandLinkLines()
// ---------------------------------------------------------------------------

describe( 'expandLinkLines()', () => {
    const links = [ { '[link]': 'A', '[link_description]': 'desc-A' }, { '[link]': 'B', '[link_description]': 'desc-B' } ];

    it( 'repeats an implicit link line for each link', () => {
        const result = expandLinkLines( '- [link]', links );
        expect( result ).toBe( '- A\n- B' );
    } );

    it( 'repeats consecutive link lines as a group per link', () => {
        const result = expandLinkLines( '[link]\n[link_description]', links );
        expect( result ).toBe( 'A\ndesc-A\nB\ndesc-B' );
    } );

    it( 'leaves regular lines untouched', () => {
        const result = expandLinkLines( 'header\n- [link]\nfooter', links );
        expect( result ).toBe( 'header\n- A\n- B\nfooter' );
    } );

    it( 'passes through text with no link tokens', () => {
        expect( expandLinkLines( 'no tokens here', links ) ).toBe( 'no tokens here' );
    } );

    it( 'produces empty output when links array is empty', () => {
        expect( expandLinkLines( '[link]', [] ) ).toBe( '' );
    } );
} );

// ---------------------------------------------------------------------------
// validateTemplate()
// ---------------------------------------------------------------------------

describe( 'validateTemplate()', () => {
    it( 'returns no warnings for a balanced template', () => {
        expect( validateTemplate( '[category_start]…[category_end]\n[link_start]…[link_end]' ) ).toEqual( [] );
    } );

    it( 'returns no warnings for an empty template', () => {
        expect( validateTemplate( '' ) ).toEqual( [] );
    } );

    it( 'warns when category_start count differs from category_end', () => {
        const warnings = validateTemplate( '[category_start][category_start][category_end]' );
        expect( warnings ).toHaveLength( 1 );
        expect( warnings[0] ).toMatch( /category_start.*category_end.*2.*1/i );
    } );

    it( 'warns when link_start count differs from link_end', () => {
        const warnings = validateTemplate( '[link_start]' );
        expect( warnings ).toHaveLength( 1 );
        expect( warnings[0] ).toMatch( /link_start.*link_end.*1.*0/i );
    } );

    it( 'returns two warnings when both pairs are mismatched', () => {
        const warnings = validateTemplate( '[category_start][link_start][link_start]' );
        expect( warnings ).toHaveLength( 2 );
    } );
} );

// ---------------------------------------------------------------------------
// getLineStart()
// ---------------------------------------------------------------------------

describe( 'getLineStart()', () => {
    it( 'returns 0 when pos is at the very start', () => {
        expect( getLineStart( 'abc', 0 ) ).toBe( 0 );
    } );

    it( 'returns 0 for any pos on the first line (no preceding newline)', () => {
        expect( getLineStart( 'hello world', 5 ) ).toBe( 0 );
    } );

    it( 'returns the position after the newline for a second line', () => {
        // "line1\nline2" — line 2 starts at index 6
        expect( getLineStart( 'line1\nline2', 8 ) ).toBe( 6 );
    } );

    it( 'returns correct start when pos is exactly at the newline character', () => {
        // pos === 5 means the cursor is ON the \n; lastIndexOf('\n', 4) finds nothing → 0
        expect( getLineStart( 'line1\nline2', 5 ) ).toBe( 0 );
    } );

    it( 'handles multiple newlines correctly', () => {
        // "a\nb\nc" — 'c' is at index 4, line start is 4
        expect( getLineStart( 'a\nb\nc', 4 ) ).toBe( 4 );
    } );
} );
