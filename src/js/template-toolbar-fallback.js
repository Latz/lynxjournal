// Undo/redo history for the plain-textarea fallback path (no CodeMirror).
// State lives at module scope since only one template editor exists per page.
const undoStack = [];
let redoStack = [];
const MAX_HIST = 100;

/**
 * @param {HTMLTextAreaElement} textarea
 */
export function saveSnapshot( textarea ) {
	undoStack.push( { value: textarea.value, start: textarea.selectionStart, end: textarea.selectionEnd } );
	if ( undoStack.length > MAX_HIST ) { undoStack.shift(); }
	redoStack = [];
}

/**
 * @param {HTMLTextAreaElement} textarea
 * @param {{ value: string, start: number, end: number }} snap
 * @param {Function} onChange
 */
function restoreSnapshot( textarea, snap, onChange ) {
	textarea.value = snap.value;
	textarea.setSelectionRange( snap.start, snap.end );
	textarea.focus();
	onChange();
}

/**
 * Wraps the selection (or a placeholder) in an inline marker pair, e.g. `**bold**`.
 *
 * @param {string} value
 * @param {number} start
 * @param {number} end
 * @param {string} sel Current selection text, or '' if none.
 * @param {string} openTag
 * @param {string} closeTag
 * @param {string} placeholder Text inserted when there is no selection.
 * @returns {{ newVal: string, cursor: number }}
 */
function applyInlineWrap( value, start, end, sel, openTag, closeTag, placeholder ) {
	const inner  = sel || placeholder;
	const newVal = value.slice( 0, start ) + openTag + inner + closeTag + value.slice( end );
	const cursor = sel ? end + openTag.length + closeTag.length : start + openTag.length + inner.length;
	return { newVal, cursor };
}

/**
 * Replaces the current line's existing block prefix (heading/list/quote marker,
 * if any) with `prefix`. Shared by the heading, bullet-list, and ordered-list actions.
 *
 * @param {string} value
 * @param {number} start
 * @param {(value: string, pos: number) => number} getLineStart
 * @param {string} prefix
 * @returns {{ newVal: string, cursor: number }}
 */
function applyLinePrefix( value, start, getLineStart, prefix ) {
	const lineStart = getLineStart( value, start );
	const rest       = value.slice( lineStart );
	const stripped   = rest.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
	const removed    = rest.length - stripped.length;
	const newVal     = value.slice( 0, lineStart ) + prefix + stripped;
	const cursor     = Math.max( lineStart + prefix.length, start - removed + prefix.length );
	return { newVal, cursor };
}

/**
 * @param {string} value
 * @param {number} start
 * @param {(value: string, pos: number) => number} getLineStart
 * @returns {{ newVal: string, cursor: number }}
 */
function applyIndent( value, start, getLineStart ) {
	const lineStart = getLineStart( value, start );
	const newVal    = `${ value.slice( 0, lineStart ) }  ${ value.slice( lineStart ) }`;
	return { newVal, cursor: start + 2 };
}

/**
 * @param {string} value
 * @param {number} start
 * @param {(value: string, pos: number) => number} getLineStart
 * @returns {{ newVal: string, cursor: number }}
 */
function applyOutdent( value, start, getLineStart ) {
	const lineStart = getLineStart( value, start );
	const lineText   = value.slice( lineStart );
	const spaces     = /^ {1,2}/.exec( lineText );
	const removed    = spaces ? spaces[ 0 ].length : 0;
	const newVal     = value.slice( 0, lineStart ) + value.slice( lineStart + removed );
	return { newVal, cursor: Math.max( lineStart, start - removed ) };
}

/**
 * @param {string} value
 * @param {number} start
 * @param {number} end
 * @returns {{ newVal: string, cursor: number }}
 */
function applyHorizontalRule( value, start, end ) {
	const insert = '\n---\n';
	const newVal = value.slice( 0, start ) + insert + value.slice( end );
	return { newVal, cursor: start + insert.length };
}

/**
 * Computes the resulting textarea value and cursor position for a toolbar
 * format action, or null if the action is unrecognized.
 *
 * @param {string} action
 * @param {string} value
 * @param {number} start
 * @param {number} end
 * @param {string} sel
 * @param {(value: string, pos: number) => number} getLineStart
 * @returns {{ newVal: string, cursor: number }|null}
 */
function computeFormatResult( action, value, start, end, sel, getLineStart ) {
	if ( action === 'bold' ) { return applyInlineWrap( value, start, end, sel, '**', '**', 'bold text' ); }
	if ( action === 'italic' ) { return applyInlineWrap( value, start, end, sel, '*', '*', 'italic text' ); }
	if ( action === 'underline' ) { return applyInlineWrap( value, start, end, sel, '<u>', '</u>', 'underlined text' ); }
	if ( /^h[1-6]$/.test( action ) ) {
		return applyLinePrefix( value, start, getLineStart, `${ '#'.repeat( Number.parseInt( action[ 1 ], 10 ) ) } ` );
	}
	if ( action === 'list' ) { return applyLinePrefix( value, start, getLineStart, '- ' ); }
	if ( action === 'ol' ) { return applyLinePrefix( value, start, getLineStart, '1. ' ); }
	if ( action === 'indent' ) { return applyIndent( value, start, getLineStart ); }
	if ( action === 'outdent' ) { return applyOutdent( value, start, getLineStart ); }
	if ( action === 'hr' ) { return applyHorizontalRule( value, start, end ); }
	return null;
}

/**
 * Applies a toolbar format action to the plain-textarea fallback editor.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {string} action
 * @param {(value: string, pos: number) => number} getLineStart
 * @param {Function} onChange Called after the textarea value changes so the caller can refresh the preview.
 */
export function fallbackApplyFormat( textarea, action, getLineStart, onChange ) {
	const start = textarea.selectionStart;
	const end   = textarea.selectionEnd;
	const value = textarea.value;

	if ( action === 'undo' ) {
		if ( !undoStack.length ) { return; }
		redoStack.push( { value, start, end } );
		restoreSnapshot( textarea, undoStack.pop(), onChange );
		return;
	}
	if ( action === 'redo' ) {
		if ( !redoStack.length ) { return; }
		undoStack.push( { value, start, end } );
		restoreSnapshot( textarea, redoStack.pop(), onChange );
		return;
	}

	saveSnapshot( textarea );
	const sel    = value.slice( start, end );
	const result = computeFormatResult( action, value, start, end, sel, getLineStart );

	if ( !result ) {
		undoStack.pop();
		return;
	}

	textarea.value = result.newVal;
	textarea.setSelectionRange( result.cursor, result.cursor );
	textarea.focus();
	onChange();
}
