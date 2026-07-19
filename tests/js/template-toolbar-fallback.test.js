import { describe, it, expect, vi } from 'vitest';

/**
 * saveSnapshot()/fallbackApplyFormat() keep undo/redo history at module
 * scope (by design — only one template editor exists per page), so each
 * test re-imports the module fresh via resetModules() to avoid leaking
 * history between tests.
 *
 * @returns {Promise<{ saveSnapshot: Function, fallbackApplyFormat: Function }>}
 */
function freshModule() {
    vi.resetModules();
    return import( '../../src/js/template-toolbar-fallback.js' );
}

/** @returns {HTMLTextAreaElement} */
function makeTextarea( value, start = value.length, end = start ) {
    const el = document.createElement( 'textarea' );
    document.body.append( el );
    el.value = value;
    el.setSelectionRange( start, end );
    return el;
}

const getLineStart = ( value, pos ) => {
    const idx = value.lastIndexOf( '\n', pos - 1 );
    return idx === -1 ? 0 : idx + 1;
};

describe( 'saveSnapshot()', () => {
    it( 'does not throw and leaves the textarea untouched', async () => {
        const { saveSnapshot } = await freshModule();
        const textarea = makeTextarea( 'hello world' );
        expect( () => saveSnapshot( textarea ) ).not.toThrow();
        expect( textarea.value ).toBe( 'hello world' );
    } );
} );

describe( 'fallbackApplyFormat()', () => {
    it( 'wraps the selection in ** for bold', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 0, 5 );
        const onChange  = vi.fn();

        fallbackApplyFormat( textarea, 'bold', getLineStart, onChange );

        expect( textarea.value ).toBe( '**hello** world' );
        expect( onChange ).toHaveBeenCalledOnce();
    } );

    it( 'inserts placeholder bold text when there is no selection', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( '', 0, 0 );

        fallbackApplyFormat( textarea, 'bold', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '**bold text**' );
    } );

    it( 'wraps the selection in * for italic', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 6, 11 );

        fallbackApplyFormat( textarea, 'italic', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( 'hello *world*' );
    } );

    it( 'wraps the selection in <u></u> for underline', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 0, 5 );

        fallbackApplyFormat( textarea, 'underline', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '<u>hello</u> world' );
    } );

    it( 'prefixes the current line with a heading marker', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'a line', 3, 3 );

        fallbackApplyFormat( textarea, 'h2', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '## a line' );
    } );

    it( 'prefixes the current line with a bullet for list', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'item', 0, 0 );

        fallbackApplyFormat( textarea, 'list', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '- item' );
    } );

    it( 'prefixes the current line with "1. " for ol', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'item', 0, 0 );

        fallbackApplyFormat( textarea, 'ol', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '1. item' );
    } );

    it( 'replaces an existing line prefix instead of stacking a new one', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( '- item', 0, 0 );

        fallbackApplyFormat( textarea, 'h2', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '## item' );
    } );

    it( 'inserts a horizontal rule at the cursor', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'before after', 6, 6 );

        fallbackApplyFormat( textarea, 'hr', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( 'before\n---\n after' );
    } );

    it( 'indents the current line by two spaces', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'item', 0, 0 );

        fallbackApplyFormat( textarea, 'indent', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( '  item' );
    } );

    it( 'outdents a line that has leading spaces', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( '  item', 2, 2 );

        fallbackApplyFormat( textarea, 'outdent', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( 'item' );
    } );

    it( 'outdent is a no-op on a line with no leading spaces', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'item', 0, 0 );

        fallbackApplyFormat( textarea, 'outdent', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( 'item' );
    } );

    it( 'is a no-op and does not corrupt undo history for an unrecognized action', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 0, 5 );

        fallbackApplyFormat( textarea, 'not-a-real-action', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( 'hello world' );

        // The bogus action must not have left a stray undo entry.
        fallbackApplyFormat( textarea, 'undo', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( 'hello world' );
    } );

    it( 'undo restores the value from before the last format action', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 0, 5 );

        fallbackApplyFormat( textarea, 'bold', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( '**hello** world' );

        fallbackApplyFormat( textarea, 'undo', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( 'hello world' );
    } );

    it( 'redo re-applies a change that was just undone', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 0, 5 );

        fallbackApplyFormat( textarea, 'bold', getLineStart, vi.fn() );
        fallbackApplyFormat( textarea, 'undo', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( 'hello world' );

        fallbackApplyFormat( textarea, 'redo', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( '**hello** world' );
    } );

    it( 'undo is a no-op when there is no history', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'untouched', 0, 0 );

        fallbackApplyFormat( textarea, 'undo', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( 'untouched' );
    } );

    it( 'redo is a no-op when there is nothing to redo', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'untouched', 0, 0 );

        fallbackApplyFormat( textarea, 'redo', getLineStart, vi.fn() );

        expect( textarea.value ).toBe( 'untouched' );
    } );

    it( 'starting a new format action after an undo clears the redo stack', async () => {
        const { fallbackApplyFormat } = await freshModule();
        const textarea = makeTextarea( 'hello world', 0, 5 );

        fallbackApplyFormat( textarea, 'bold', getLineStart, vi.fn() );
        fallbackApplyFormat( textarea, 'undo', getLineStart, vi.fn() );
        fallbackApplyFormat( textarea, 'italic', getLineStart, vi.fn() );

        // The bold redo entry should be gone now — redo should be a no-op.
        const before = textarea.value;
        fallbackApplyFormat( textarea, 'redo', getLineStart, vi.fn() );
        expect( textarea.value ).toBe( before );
    } );
} );
