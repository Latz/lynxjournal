// Dynamic import with a filemtime-based version query string (set in
// Menu.php) so a stale browser cache of this file can't desync from
// template-page.js — WP's own enqueue cache-busting doesn't reach nested
// static ES imports, only the top-level enqueued script.
const utilsVersion = window.lynxjournalTemplateUtilsVersion ?? '';
const {
	LINK_TOKENS,
	replaceTokens,
	hasLinkToken,
	expandLinkBlocks,
	expandLinkLines,
	validateTemplate,
	getLineStart,
	preserveBlankLines,
} = await import( `../../src/js/template-utils.js?v=${ utilsVersion }` );

/**
 * @typedef {{ '[category_link_count]': string, links: Array<Record<string, string>>, [key: string]: string | Array<Record<string, string>> }} CategoryVariant
 */

let editor            = null;   // CodeMirror instance; null = plain-textarea fallback
let textarea          = null;
let preview           = null;
let previewStatus     = null;
let previewValidation = null;
let previewTimer      = null;
let btnUndo, btnRedo;

const _data            = window.lynxjournalPreviewData ?? {};
const scalarData       = _data.scalar ?? {};
const categoryVariants = ( _data.categories ?? [] ).map( cat => {
	const { token, name } = cat;
	const links = cat.links ?? [];
	return { [ token ]: name, '[category_link_count]': String( links.length ), links };
} );

// ── Preview status ──────────────────────────────────────────

function setPreviewUpdating() {
	if ( previewStatus ) {
		previewStatus.textContent = 'Aktualisiert…';
		previewStatus.classList.add( 'is-updating' );
	}
	if ( preview ) { preview.classList.add( 'is-updating' ); }
}

function setPreviewLive() {
	if ( previewStatus ) {
		previewStatus.textContent = 'Live';
		previewStatus.classList.remove( 'is-updating' );
	}
	if ( preview ) { preview.classList.remove( 'is-updating' ); }
}

// ── Validation ──────────────────────────────────────────────

/**
 * @param {string[]} warnings
 */
function renderValidation( warnings ) {
	if ( !previewValidation ) { return; }
	previewValidation.replaceChildren(
		...warnings.map( msg => {
			const div = document.createElement( 'div' );
			div.className = 'lynxjournal-preview-warning';
			div.innerHTML =
				'<svg viewBox="0 0 16 16" aria-hidden="true">' +
				'<path d="M8 1L15 14H1L8 1z" stroke="currentColor" stroke-width="1.5" fill="none"/>' +
				'<path d="M8 6v4m0 1.5v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>' +
				'</svg>';
			const span = document.createElement( 'span' );
			span.textContent = msg;
			div.append( span );
			return div;
		} )
	);
}

// ── Preview ─────────────────────────────────────────────────

/** @returns {string} */
function getEditorValue() {
	return editor ? editor.getValue() : ( textarea ? textarea.value : '' );
}

function updateTemplatePreview() {
	const rawText = getEditorValue();
	renderValidation( validateTemplate( rawText ) );
	// Run before category/link expansion so only blank lines the user actually
	// typed become visible markers — the category-loop's own join below relies
	// on a real (untouched) blank line between repetitions to terminate the
	// preceding <div> HTML block from the indentation step further down, so
	// marking blank lines any later would either miss real ones inside a
	// category block or wrongly mark that structural join boundary.
	let text = preserveBlankLines( rawText );

	text = text.replace(
		/\[category_start\]([\s\S]*?)\[category_end\]/g,
		( _match, inner ) => categoryVariants.map( cat => {
			let catText = replaceTokens( inner, {
				'[category_name]'       : cat[ '[category_name]' ],
				'[category_link_count]' : cat[ '[category_link_count]' ],
			} );
			catText = expandLinkBlocks( catText, cat.links );
			catText = expandLinkLines( catText, cat.links );
			return catText;
		} ).join( '' )
	);

	text = expandLinkBlocks( text, categoryVariants[ 0 ]?.links ?? [] );
	text = replaceTokens( text, scalarData );

	// Convert indented lines (2+ spaces) to padded divs for visual indentation.
	text = text.split( '\n' ).map( line => {
		const m = line.match( /^( {2,})(- )?(.+)/ );
		if ( !m ) { return line; }
		const level   = Math.floor( m[ 1 ].length / 2 );
		const isList  = !!m[ 2 ];
		const content = window.marked.parseInline( m[ 3 ] );
		return `<div style="padding-left:${ level * 1.5 }em">${ isList ? '• ' : '' }${ content }</div>`;
	} ).join( '\n' );

	preview.innerHTML = text.trim()
		? window.marked.parse( text )
		: '<span class="lynxjournal-preview-empty">—</span>';
	setPreviewLive();
}

// ── Undo/redo button state ──────────────────────────────────

function updateUndoRedoState() {
	if ( editor ) {
		const hist = editor.historySize();
		if ( btnUndo ) { btnUndo.disabled = hist.undo === 0; }
		if ( btnRedo ) { btnRedo.disabled = hist.redo === 0; }
	} else {
		if ( btnUndo ) { btnUndo.disabled = true; }
		if ( btnRedo ) { btnRedo.disabled = true; }
	}
}

// ── Custom CodeMirror overlay for [token] highlighting ──────

function defineTokenOverlay() {
	window.wp.CodeMirror.defineMode( 'lynxjournal', config =>
		window.wp.CodeMirror.overlayMode(
			window.wp.CodeMirror.getMode( config, { name: 'null' } ),
			{
				token( stream ) {
					if ( stream.match( /\[[^\]]*\]/ ) ) { return 'lynxjournal-token'; }
					stream.next();
					return null;
				},
			}
		)
	);
}

// ── Blank-line marker (¶ shown on empty lines) ──────────────

let blankLineMarkers = [];

/**
 * Marks every empty line in the editor with a faint ¶ widget, so blank
 * lines are easy to spot while typing. Clears and rebuilds all markers
 * each time — templates are short enough that this is cheap.
 */
function updateBlankLineMarkers() {
	blankLineMarkers.forEach( m => m.clear() );
	blankLineMarkers = [];
	const count = editor.lineCount();
	for ( let i = 0; i < count; i++ ) {
		if ( editor.getLine( i ) !== '' ) { continue; }
		const marker = document.createElement( 'span' );
		marker.className = 'lynxjournal-blank-line-marker';
		marker.textContent = '¶';
		marker.setAttribute( 'aria-hidden', 'true' );
		blankLineMarkers.push( editor.setBookmark( { line: i, ch: 0 }, { widget: marker } ) );
	}
}

// ── Line-prefix helper (headings, lists) ────────────────────

/** @param {string} prefix */
function applyLinePrefix( prefix ) {
	const cursor   = editor.getCursor();
	const lineNum  = cursor.line;
	const line     = editor.getLine( lineNum );
	const stripped = line.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
	const removed  = line.length - stripped.length;
	editor.replaceRange(
		prefix + stripped,
		{ line: lineNum, ch: 0 },
		{ line: lineNum, ch: line.length }
	);
	editor.setCursor( {
		line: lineNum,
		ch: Math.max( prefix.length, cursor.ch - removed + prefix.length ),
	} );
}

// ── Toolbar: CodeMirror path ────────────────────────────────

/** @param {string} action */
function applyFormat( action ) {
	if ( !editor ) { fallbackApplyFormat( action ); return; }

	if ( action === 'undo' ) {
		editor.undo();
		updateTemplatePreview();
		updateUndoRedoState();
		return;
	}
	if ( action === 'redo' ) {
		editor.redo();
		updateTemplatePreview();
		updateUndoRedoState();
		return;
	}

	const sel = editor.getSelection();

	if ( action === 'bold' ) {
		editor.replaceSelection( `**${ sel || 'bold text' }**` );
	} else if ( action === 'italic' ) {
		editor.replaceSelection( `*${ sel || 'italic text' }*` );
	} else if ( action === 'underline' ) {
		editor.replaceSelection( `<u>${ sel || 'underlined text' }</u>` );
	} else if ( /^h[1-6]$/.test( action ) ) {
		applyLinePrefix( '#'.repeat( parseInt( action[ 1 ], 10 ) ) + ' ' );
	} else if ( action === 'list' ) {
		applyLinePrefix( '- ' );
	} else if ( action === 'ol' ) {
		applyLinePrefix( '1. ' );
	} else if ( action === 'indent' ) {
		const cur = editor.getCursor();
		editor.replaceRange( '  ', { line: cur.line, ch: 0 } );
	} else if ( action === 'outdent' ) {
		const cur  = editor.getCursor();
		const line = editor.getLine( cur.line );
		const sp   = line.match( /^ {1,2}/ );
		if ( sp ) {
			editor.replaceRange( '', { line: cur.line, ch: 0 }, { line: cur.line, ch: sp[ 0 ].length } );
		}
	} else if ( action === 'hr' ) {
		editor.replaceSelection( '\n---\n' );
	}

	editor.focus();
	updateTemplatePreview();
	updateUndoRedoState();
}

// ── Toolbar: plain-textarea fallback ────────────────────────

let undoStack = [];
let redoStack = [];
const MAX_HIST = 100;

function saveSnapshot() {
	undoStack.push( { value: textarea.value, start: textarea.selectionStart, end: textarea.selectionEnd } );
	if ( undoStack.length > MAX_HIST ) { undoStack.shift(); }
	redoStack = [];
}

/** @param {{ value: string, start: number, end: number }} snap */
function restoreSnapshot( snap ) {
	textarea.value = snap.value;
	textarea.setSelectionRange( snap.start, snap.end );
	textarea.focus();
	updateTemplatePreview();
}

/** @param {string} action */
function fallbackApplyFormat( action ) {
	const start = textarea.selectionStart;
	const end   = textarea.selectionEnd;
	const value = textarea.value;
	let newVal, cursor;

	if ( action === 'undo' ) {
		if ( !undoStack.length ) { return; }
		redoStack.push( { value, start, end } );
		restoreSnapshot( undoStack.pop() );
		return;
	}
	if ( action === 'redo' ) {
		if ( !redoStack.length ) { return; }
		undoStack.push( { value, start, end } );
		restoreSnapshot( redoStack.pop() );
		return;
	}

	saveSnapshot();
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
	updateTemplatePreview();
}

// ── Init ────────────────────────────────────────────────────

/**
 * Runs directly rather than on 'DOMContentLoaded': this module is deferred
 * (type="module"), so the DOM is already parsed by the time it executes —
 * and since the top-level `await import()` above can resolve after the
 * real DOMContentLoaded event has already fired, a listener registered
 * here would otherwise never run.
 */
function initTemplateEditor() {
	textarea          = document.getElementById( 'lynxjournal-post-template' );
	preview           = document.getElementById( 'lynxjournal-template-preview' );
	previewStatus     = document.getElementById( 'lynxjournal-preview-status' );
	previewValidation = document.getElementById( 'lynxjournal-preview-validation' );

	if ( !textarea || !preview ) { return; }

	btnUndo = document.querySelector( '.lynxjournal-format-btn[data-action="undo"]' );
	btnRedo = document.querySelector( '.lynxjournal-format-btn[data-action="redo"]' );

	if ( window.wp && window.wp.codeEditor && window.wp.CodeMirror ) {
		defineTokenOverlay();
		const settings = window.lynxjournalEditorSettings ?? {};
		if ( !settings.codemirror ) { settings.codemirror = {}; }
		settings.codemirror.mode        = 'lynxjournal';
		settings.codemirror.lineWrapping = true;

		const instance = window.wp.codeEditor.initialize( textarea, settings );
		editor = instance.codemirror;

		editor.on( 'change', () => {
			updateBlankLineMarkers();
			setPreviewUpdating();
			clearTimeout( previewTimer );
			previewTimer = setTimeout( () => {
				updateUndoRedoState();
				updateTemplatePreview();
			}, 400 );
		} );
	} else {
		// Accessibility mode or CodeMirror unavailable — use plain textarea.
		textarea.addEventListener( 'input', () => {
			setPreviewUpdating();
			clearTimeout( previewTimer );
			previewTimer = setTimeout( updateTemplatePreview, 400 );
		} );
		textarea.addEventListener( 'keydown', e => {
			if ( !( e.ctrlKey || e.metaKey ) ) { return; }
			if ( e.key === 'z' || e.key === 'Z' ) {
				e.preventDefault();
				fallbackApplyFormat( e.shiftKey ? 'redo' : 'undo' );
			} else if ( e.key === 'y' || e.key === 'Y' ) {
				e.preventDefault();
				fallbackApplyFormat( 'redo' );
			}
		} );
	}

	if ( editor ) { updateBlankLineMarkers(); }
	updateTemplatePreview();
	updateUndoRedoState();

	document.querySelectorAll( '.lynxjournal-format-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => applyFormat( btn.dataset.action ) );
	} );

	const headingSelect = document.getElementById( 'lynxjournal-heading-select' );
	if ( headingSelect ) {
		headingSelect.addEventListener( 'change', () => {
			if ( headingSelect.value ) {
				applyFormat( headingSelect.value );
				headingSelect.value = '';
			}
		} );
	}

	document.querySelectorAll( '.lynxjournal-insert-token' ).forEach( btn => {
		btn.addEventListener( 'click', () => {
			const token = btn.dataset.token;
			if ( editor ) {
				editor.replaceSelection( token );
				editor.focus();
			} else {
				const start = textarea.selectionStart;
				const end   = textarea.selectionEnd;
				saveSnapshot();
				textarea.value = textarea.value.slice( 0, start ) + token + textarea.value.slice( end );
				textarea.setSelectionRange( start + token.length, start + token.length );
				textarea.focus();
			}
			updateTemplatePreview();
		} );
	} );

	document.querySelectorAll( '.lynxjournal-accordion-toggle' ).forEach( btn => {
		btn.addEventListener( 'click', () => {
			const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
			btn.setAttribute( 'aria-expanded', String( !expanded ) );
			const panel = document.getElementById( btn.getAttribute( 'aria-controls' ) );
			panel?.classList.toggle( 'is-open', !expanded );
		} );
	} );
}

initTemplateEditor();
