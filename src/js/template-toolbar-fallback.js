// Undo/redo history for the plain-textarea fallback path (no CodeMirror).
// State lives at module scope since only one template editor exists per page.
let undoStack = [];
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
	let newVal, cursor;

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
	const sel = value.slice( start, end );

	if ( action === 'bold' ) {
		const inner = sel || 'bold text';
		newVal = value.slice( 0, start ) + `**${ inner }**` + value.slice( end );
		cursor = sel ? end + 4 : start + 2 + inner.length;
	} else if ( action === 'italic' ) {
		const inner = sel || 'italic text';
		newVal = value.slice( 0, start ) + `*${ inner }*` + value.slice( end );
		cursor = sel ? end + 2 : start + 1 + inner.length;
	} else if ( action === 'underline' ) {
		const inner = sel || 'underlined text';
		newVal = value.slice( 0, start ) + `<u>${ inner }</u>` + value.slice( end );
		cursor = sel ? end + 7 : start + 3 + inner.length;
	} else if ( /^h[1-6]$/.test( action ) || action === 'list' ) {
		const prefix    = action === 'list' ? '- ' : '#'.repeat( parseInt( action[ 1 ], 10 ) ) + ' ';
		const lineStart = getLineStart( value, start );
		const rest      = value.slice( lineStart );
		const stripped  = rest.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
		const removed   = rest.length - stripped.length;
		newVal  = value.slice( 0, lineStart ) + prefix + stripped;
		cursor  = Math.max( lineStart + prefix.length, start - removed + prefix.length );
	} else if ( action === 'indent' ) {
		const lineStart = getLineStart( value, start );
		newVal  = value.slice( 0, lineStart ) + '  ' + value.slice( lineStart );
		cursor  = start + 2;
	} else if ( action === 'outdent' ) {
		const lineStart = getLineStart( value, start );
		const lineText  = value.slice( lineStart );
		const spaces    = lineText.match( /^ {1,2}/ );
		const removed   = spaces ? spaces[ 0 ].length : 0;
		newVal  = value.slice( 0, lineStart ) + value.slice( lineStart + removed );
		cursor  = Math.max( lineStart, start - removed );
	} else if ( action === 'hr' ) {
		const insert = '\n---\n';
		newVal  = value.slice( 0, start ) + insert + value.slice( end );
		cursor  = start + insert.length;
	} else if ( action === 'ol' ) {
		const prefix    = '1. ';
		const lineStart = getLineStart( value, start );
		const rest      = value.slice( lineStart );
		const stripped  = rest.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
		const removed   = rest.length - stripped.length;
		newVal  = value.slice( 0, lineStart ) + prefix + stripped;
		cursor  = Math.max( lineStart + prefix.length, start - removed + prefix.length );
	} else {
		undoStack.pop();
		return;
	}

	textarea.value = newVal;
	textarea.setSelectionRange( cursor, cursor );
	textarea.focus();
	onChange();
}
