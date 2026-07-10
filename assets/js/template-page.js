// Dynamic import with a filemtime-based version query string (set in
// Menu.php) so a stale browser cache of this file can't desync from
// template-page.js — WP's own enqueue cache-busting doesn't reach nested
// static ES imports, only the top-level enqueued script.
const utilsVersion = window.lynxjournalTemplateUtilsVersion ?? '';
const {
	replaceTokens,
	expandLinkBlocks,
	expandLinkLines,
	validateTemplate,
	getLineStart,
	preserveBlankLines,
	getActiveFormatsAtCursor,
} = await import( `../../src/js/template-utils.js?v=${ utilsVersion }` );
const { setPreviewUpdating, setPreviewLive, renderValidation, buildTemplateText, convertIndentedLines } =
	await import( `../../src/js/template-preview.js?v=${ utilsVersion }` );
const { saveSnapshot, fallbackApplyFormat } =
	await import( `../../src/js/template-toolbar-fallback.js?v=${ utilsVersion }` );

/**
 * @typedef {{ '[category_link_count]': string, links: Array<Record<string, string>>, [key: string]: string | Array<Record<string, string>> }} CategoryVariant
 */

let editor              = null;   // CodeMirror instance; null = plain-textarea fallback
let textarea            = null;
let preview             = null;
let previewStatus       = null;
let previewValidation   = null;
let previewTimer        = null;
let btnUndo, btnRedo;
let initialTemplateValue = '';
let isSubmitting         = false;
let formatButtons        = [];   // cached toolbar buttons, excluding undo/redo
let headingSelectEl      = null;

const _data            = window.lynxjournalPreviewData ?? {};
const scalarData       = _data.scalar ?? {};
const categoryVariants = ( _data.categories ?? [] ).map( cat => {
	const { token, name } = cat;
	const links = cat.links ?? [];
	return { [ token ]: name, '[category_link_count]': String( links.length ), links };
} );

// ── Preview ─────────────────────────────────────────────────

/** @returns {string} */
function getEditorValue() {
	return editor ? editor.getValue() : ( textarea ? textarea.value : '' );
}

/**
 * Builds the final rendered HTML string for the current editor contents,
 * using the same token-substitution + Markdown pipeline as the live preview.
 * @returns {string} Rendered HTML, or '' if the template is empty.
 */
function buildRenderedHtml() {
	const rawText = getEditorValue();
	let text = buildTemplateText( rawText, categoryVariants, scalarData, {
		replaceTokens, expandLinkBlocks, expandLinkLines, preserveBlankLines,
	} );
	text = convertIndentedLines( text, window.marked.parseInline );
	return text.trim() ? window.marked.parse( text ) : '';
}

const themePreview = window.lynxjournalThemePreview ?? { stylesheets: [], globalStyles: '', contentClass: 'entry-content' };

/** @type {'theme'|'default'} Which preview styling the iframe currently renders. */
let previewViewMode = 'theme';

// The plugin's original generic preview styling, used for "Default" view
// mode — kept separate from PREVIEW_OVERRIDE_CSS (which applies in both
// modes) so switching to "Theme" view doesn't carry any of it over.
const DEFAULT_VIEW_CSS = `
	body { background: #f6f7f7; font-size: 12px; line-height: 1.5; color: #3c434a; word-break: break-word; }
	h2, h3 { margin-block: .3em .15em; margin-inline: 0; }
	ul { margin-block: .15em; margin-inline: 0; padding-inline-start: 1.4em; list-style: disc; }
	ul ul { list-style: circle; }
	ul ul ul { list-style: square; }
	ol { margin-block: .15em; margin-inline: 0; padding-inline-start: 1.4em; }
	hr { border: none; border-block-start: 1px solid #c3c4c7; margin-block: .4em; margin-inline: 0; }
	p { margin-block: .1em; margin-inline: 0; }
`;

// Injected by preserveBlankLines() for each blank line in the source — a
// left border + reserved height marks the row as a blank line so a
// trailing/leading blank run reads as intentional and not just background
// padding. This is plugin editor UI, not theme typography, so it's injected
// directly into the preview iframe rather than relying on the theme's CSS.
const PREVIEW_OVERRIDE_CSS = `
	body { padding: 12px 14px; }
	.lynxjournal-preview-empty { color: #a7aaad; font-style: italic; }
	p.lynxjournal-blank-line {
		margin-block: 0.25em;
		min-height: 1.5em;
		padding-inline-start: 6px;
		border-inline-start: 3px solid #c3c4c7;
	}
`;

/**
 * Builds the full HTML document loaded into the preview iframe's `srcdoc`.
 * In "theme" mode, links the active theme's stylesheets so the rendered
 * markup picks up real theme typography/colors instead of the plugin's own
 * generic CSS (only the content area is themed — no header/footer/sidebar,
 * see the "Post Template" readme section). In "default" mode, the theme
 * stylesheets are skipped and the plugin's original generic styling is used
 * instead.
 * @param {string} bodyHtml
 * @returns {string}
 */
function buildPreviewDocument( bodyHtml ) {
	const isTheme = previewViewMode === 'theme';
	const links = isTheme
		? themePreview.stylesheets
			.map( href => `<link rel="stylesheet" href="${ href.replace( /"/g, '&quot;' ) }">` )
			.join( '\n' )
		: '';
	const modeStyle = isTheme ? themePreview.globalStyles : DEFAULT_VIEW_CSS;
	const contentClass = isTheme ? themePreview.contentClass : '';
	return `<!doctype html><html><head><meta charset="utf-8">${ links }` +
		`<style>${ modeStyle }</style><style>${ PREVIEW_OVERRIDE_CSS }</style></head>` +
		`<body><div class="${ contentClass }">${ bodyHtml }</div></body></html>`;
}

/**
 * Grows the preview iframe to fit its rendered content height, since an
 * iframe has no intrinsic height of its own.
 * @listens load
 */
function resizePreviewIframe() {
	const body = preview.contentDocument?.body;
	if ( body ) { preview.style.height = `${ body.scrollHeight }px`; }
	preview.style.opacity = '1';
}

/**
 * Slides a pill toggle's highlight thumb under the given button, sized and
 * positioned to match its actual (text-driven) width.
 * @param {HTMLElement} toggleEl The `.lynxjournal-preview-*-toggle` fieldset.
 * @param {HTMLElement} activeBtn The button the thumb should move to.
 */
function positionToggleThumb( toggleEl, activeBtn ) {
	const thumb = toggleEl.querySelector( '.lynxjournal-toggle-thumb' );
	if ( !thumb ) { return; }
	thumb.style.transform = `translateX(${ activeBtn.offsetLeft }px)`;
	thumb.style.width = `${ activeBtn.offsetWidth }px`;
}

function updateTemplatePreview() {
	const rawText = getEditorValue();
	renderValidation( previewValidation, validateTemplate( rawText ) );

	const html = buildRenderedHtml();
	preview.style.opacity = '0.35';
	preview.srcdoc = buildPreviewDocument( html || '<span class="lynxjournal-preview-empty">—</span>' );
	setPreviewLive( previewStatus );
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

// ── Toolbar active-state detection ──────────────────────────

/**
 * Syncs toolbar button/select "pressed" state to the current cursor position.
 *
 * @returns {void}
 */
function updateToolbarActiveState() {
	let line = '';
	let ch   = 0;

	if ( editor ) {
		const cursor = editor.getCursor();
		line = editor.getLine( cursor.line );
		ch   = cursor.ch;
	} else if ( textarea ) {
		const pos       = textarea.selectionStart;
		const lineStart = getLineStart( textarea.value, pos );
		const lineEnd   = textarea.value.indexOf( '\n', pos );
		line = textarea.value.slice( lineStart, lineEnd === -1 ? textarea.value.length : lineEnd );
		ch   = pos - lineStart;
	} else {
		return;
	}

	const active = getActiveFormatsAtCursor( line, ch );

	formatButtons.forEach( btn => {
		btn.classList.toggle( 'is-active', active.has( btn.dataset.action ) );
	} );

	if ( headingSelectEl ) {
		headingSelectEl.value = [ ...active ].find( a => /^h[1-6]$/.test( a ) ) ?? '';
	}
}

// ── Toolbar: CodeMirror path ────────────────────────────────

/** @param {string} action */
function applyFormat( action ) {
	if ( !editor ) { fallbackApplyFormat( textarea, action, getLineStart, updateTemplatePreview ); return; }

	if ( action === 'undo' ) {
		editor.undo();
		updateTemplatePreview();
		updateUndoRedoState();
		updateToolbarActiveState();
		return;
	}
	if ( action === 'redo' ) {
		editor.redo();
		updateTemplatePreview();
		updateUndoRedoState();
		updateToolbarActiveState();
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
	updateToolbarActiveState();
}

// ── Unsaved-changes guard ────────────────────────────────────

/**
 * Warns before leaving the page if the template textarea/editor value has
 * changed since load. Suppressed once the form is actually being submitted.
 *
 * @listens beforeunload
 * @param {BeforeUnloadEvent} event
 */
function warnOnUnsavedChanges( event ) {
	if ( isSubmitting || getEditorValue() === initialTemplateValue ) { return; }
	event.preventDefault();
}

// ── Collapsible panel state persistence ────────────────────────

const PANEL_STATE_KEY = 'lynxjournalTemplatePanelState';

/** @returns {Record<string, boolean>} Persisted open/closed state, keyed by panel id. */
function loadPanelState() {
	try {
		return JSON.parse( localStorage.getItem( PANEL_STATE_KEY ) || '{}' );
	} catch {
		return {};
	}
}

/** @param {Record<string, boolean>} state */
function savePanelState( state ) {
	try {
		localStorage.setItem( PANEL_STATE_KEY, JSON.stringify( state ) );
	} catch {
		// Ignore quota/availability errors — persistence is a non-critical enhancement.
	}
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

	preview.addEventListener( 'load', resizePreviewIframe );

	initialTemplateValue = textarea.value;
	window.addEventListener( 'beforeunload', warnOnUnsavedChanges );
	textarea.form?.addEventListener( 'submit', () => { isSubmitting = true; } );

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
			setPreviewUpdating( previewStatus );
			clearTimeout( previewTimer );
			previewTimer = setTimeout( () => {
				updateBlankLineMarkers();
				updateUndoRedoState();
				updateTemplatePreview();
			}, 400 );
		} );
		editor.on( 'cursorActivity', updateToolbarActiveState );
	} else {
		// Accessibility mode or CodeMirror unavailable — use plain textarea.
		textarea.addEventListener( 'input', () => {
			setPreviewUpdating( previewStatus );
			clearTimeout( previewTimer );
			previewTimer = setTimeout( updateTemplatePreview, 400 );
			updateToolbarActiveState();
		} );
		textarea.addEventListener( 'keyup', updateToolbarActiveState );
		textarea.addEventListener( 'click', updateToolbarActiveState );
		textarea.addEventListener( 'keydown', e => {
			if ( !( e.ctrlKey || e.metaKey ) ) { return; }
			if ( e.key === 'z' || e.key === 'Z' ) {
				e.preventDefault();
				fallbackApplyFormat( textarea, e.shiftKey ? 'redo' : 'undo', getLineStart, updateTemplatePreview );
			} else if ( e.key === 'y' || e.key === 'Y' ) {
				e.preventDefault();
				fallbackApplyFormat( textarea, 'redo', getLineStart, updateTemplatePreview );
			}
		} );
	}

	const allFormatButtons = [ ...document.querySelectorAll( '.lynxjournal-format-btn[data-action]' ) ];
	formatButtons   = allFormatButtons.filter( btn => btn.dataset.action !== 'undo' && btn.dataset.action !== 'redo' );
	headingSelectEl = document.getElementById( 'lynxjournal-heading-select' );

	if ( editor ) { updateBlankLineMarkers(); }
	updateTemplatePreview();
	updateUndoRedoState();
	updateToolbarActiveState();

	allFormatButtons.forEach( btn => {
		btn.addEventListener( 'click', () => applyFormat( btn.dataset.action ) );
	} );

	if ( headingSelectEl ) {
		headingSelectEl.addEventListener( 'change', () => {
			if ( headingSelectEl.value ) {
				applyFormat( headingSelectEl.value );
				headingSelectEl.value = '';
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
				saveSnapshot( textarea );
				textarea.value = textarea.value.slice( 0, start ) + token + textarea.value.slice( end );
				textarea.setSelectionRange( start + token.length, start + token.length );
				textarea.focus();
			}
			updateTemplatePreview();
			updateToolbarActiveState();
		} );
	} );

	const widthToggle = document.querySelector( '.lynxjournal-preview-width-toggle' );
	const viewToggle  = document.querySelector( '.lynxjournal-preview-view-toggle' );

	document.querySelectorAll( '.lynxjournal-preview-width-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => {
			document.querySelectorAll( '.lynxjournal-preview-width-btn' ).forEach( b => b.classList.remove( 'is-active' ) );
			btn.classList.add( 'is-active' );
			preview.classList.toggle( 'is-width-mobile', btn.dataset.width === 'mobile' );
			if ( widthToggle ) { positionToggleThumb( widthToggle, btn ); }
			resizePreviewIframe();
		} );
	} );

	document.querySelectorAll( '.lynxjournal-preview-view-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => {
			if ( btn.dataset.view === previewViewMode ) { return; }
			document.querySelectorAll( '.lynxjournal-preview-view-btn' ).forEach( b => b.classList.remove( 'is-active' ) );
			btn.classList.add( 'is-active' );
			previewViewMode = btn.dataset.view;
			if ( viewToggle ) { positionToggleThumb( viewToggle, btn ); }
			updateTemplatePreview();
		} );
	} );

	if ( widthToggle ) { positionToggleThumb( widthToggle, widthToggle.querySelector( '.is-active' ) ); }
	if ( viewToggle ) { positionToggleThumb( viewToggle, viewToggle.querySelector( '.is-active' ) ); }

	const panelState = loadPanelState();

	const tokenAccordion = document.getElementById( 'lynxjournal-token-accordion' );
	if ( tokenAccordion ) {
		if ( panelState[ 'lynxjournal-token-accordion' ] === true ) {
			tokenAccordion.open = true;
		}
		/** @listens toggle Persists the token accordion's native open/closed state. */
		tokenAccordion.addEventListener( 'toggle', () => {
			panelState[ 'lynxjournal-token-accordion' ] = tokenAccordion.open;
			savePanelState( panelState );
		} );
	}

	document.querySelectorAll( '.lynxjournal-preview-collapse-btn' ).forEach( btn => {
		const panelId = btn.getAttribute( 'aria-controls' );
		const panel   = panelId ? document.getElementById( panelId ) : null;

		if ( panel && typeof panelState[ panelId ] === 'boolean' ) {
			btn.setAttribute( 'aria-expanded', String( panelState[ panelId ] ) );
			panel.classList.toggle( 'is-open', panelState[ panelId ] );
		}

		btn.addEventListener( 'click', () => {
			const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
			const nowOpen  = !expanded;
			btn.setAttribute( 'aria-expanded', String( nowOpen ) );
			panel?.classList.toggle( 'is-open', nowOpen );
			if ( panelId ) {
				panelState[ panelId ] = nowOpen;
				savePanelState( panelState );
			}
		} );
	} );
}

initTemplateEditor();
