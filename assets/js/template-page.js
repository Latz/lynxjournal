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

/**
 * Recursively wraps a `<ul>`/`<ol>` node in `wp:list`/`wp:list-item` block
 * comments. If an `<li>` contains a nested `<ul>`/`<ol>` (a genuine
 * sub-list — see `convertIndentedLines()`'s nesting fix), that nested list
 * is wrapped as its own inner `wp:list` block too, matching how real
 * Gutenberg represents nested lists, instead of leaving it as unblocked
 * raw HTML inside the parent list-item.
 * @param {HTMLUListElement|HTMLOListElement} node
 * @returns {string}
 */
function wrapListBlock( node ) {
	const tag = node.tagName.toLowerCase();
	node.classList.add( 'wp-block-list' );
	const start     = tag === 'ol' ? node.getAttribute( 'start' ) : null;
	const startAttr = start ? `,"start":${ start }` : '';
	const attrs     = tag === 'ol' ? ` {"ordered":true${ startAttr }}` : '';
	const startHtml = start ? ` start="${ start }"` : '';

	const items = [ ...node.children ].map( li => {
		const nested = li.querySelector( ':scope > ul, :scope > ol' );
		if ( !nested ) {
			return `<!-- wp:list-item -->\n${ li.outerHTML }\n<!-- /wp:list-item -->`;
		}
		const nestedBlock = wrapListBlock( nested );
		nested.remove();
		return `<!-- wp:list-item -->\n<li>${ li.innerHTML }\n${ nestedBlock }</li>\n<!-- /wp:list-item -->`;
	} ).join( '\n' );

	return `<!-- wp:list${ attrs } -->\n<${ tag }${ startHtml } class="wp-block-list">\n${ items }\n</${ tag }>\n<!-- /wp:list -->`;
}

/**
 * Wraps top-level headings, lists, and paragraphs from the rendered preview
 * HTML in Gutenberg block comments, mirroring the block markup the real
 * publish-content builders (Batch.php/Publishing.php) now produce. Other
 * elements (e.g. the padding-div lines from convertIndentedLines()) pass
 * through unwrapped, same as Gutenberg treats freeform content. The
 * `.lynxjournal-blank-line` markers (`preserveBlankLines()` in
 * template-utils.js, only meant to make blank lines visible in the live
 * in-page preview) are dropped entirely rather than becoming empty
 * `<!-- wp:paragraph -->` blocks — real Gutenberg content relies on the
 * theme's block margins for spacing, not empty `&nbsp;` paragraphs.
 * @param {string} renderedHtml
 * @returns {string}
 */
function wrapAsGutenbergBlocks( renderedHtml ) {
	const container = document.createElement( 'div' );
	container.innerHTML = renderedHtml;
	const parts = [];

	for ( const node of container.children ) {
		const tag = node.tagName.toLowerCase();
		if ( tag === 'p' && node.classList.contains( 'lynxjournal-blank-line' ) ) {
			continue;
		}
		if ( tag === 'hr' ) {
			parts.push( '<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n<!-- /wp:separator -->' );
		} else if ( /^h[1-6]$/.test( tag ) ) {
			const level = Number( tag[ 1 ] );
			node.classList.add( 'wp-block-heading' );
			const attrs = level === 2 ? '' : ` {"level":${ level }}`;
			parts.push( `<!-- wp:heading${ attrs } -->\n${ node.outerHTML }\n<!-- /wp:heading -->` );
		} else if ( tag === 'ul' || tag === 'ol' ) {
			parts.push( wrapListBlock( node ) );
		} else if ( tag === 'p' ) {
			parts.push( `<!-- wp:paragraph -->\n${ node.outerHTML }\n<!-- /wp:paragraph -->` );
		} else {
			parts.push( node.outerHTML );
		}
	}

	return parts.join( '\n\n' );
}

/**
 * Derives a distinguishable draft title from the first heading in the
 * rendered preview, prefixed so it's never mistaken for real content in
 * the Posts list.
 * @param {string} renderedHtml
 * @returns {string}
 */
function extractPostTitle( renderedHtml ) {
	const container = document.createElement( 'div' );
	container.innerHTML = renderedHtml;
	const heading = container.querySelector( 'h1, h2, h3, h4, h5, h6' );
	return heading ? `[Test] ${ heading.textContent.trim() }` : '';
}

function updateTemplatePreview() {
	const rawText = getEditorValue();
	renderValidation( previewValidation, validateTemplate( rawText ) );

	const html = buildRenderedHtml();
	preview.innerHTML = html || '<span class="lynxjournal-preview-empty">—</span>';
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

	/**
	 * Creates a real draft WordPress post from the current template preview,
	 * so the admin can review it in the actual block editor. Always a draft —
	 * never published directly from here.
	 * @listens click
	 */
	document.getElementById( 'lynxjournal-test-post-btn' )?.addEventListener( 'click', async ( event ) => {
		const btn = event.currentTarget;
		const html = buildRenderedHtml();
		if ( !html ) { return; }

		const content = wrapAsGutenbergBlocks( html );
		const title   = extractPostTitle( html );

		const originalLabel = btn.textContent;
		btn.disabled    = true;
		btn.textContent = 'Creating…';

		try {
			const res = await fetch( window.lynxjournalTemplate.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.lynxjournalTemplate.nonce },
				body: JSON.stringify( { content, title } ),
			} );
			const data = await res.json();
			if ( !res.ok || !data.success ) {
				throw new Error( ( data && data.message ) || 'Failed to create test post.' );
			}
			window.open( data.edit_url, '_blank' );
		} catch ( err ) {
			alert( err.message || 'Failed to create test post.' );
		} finally {
			btn.disabled    = false;
			btn.textContent = originalLabel;
		}
	} );

	document.querySelectorAll( '.lynxjournal-preview-width-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => {
			document.querySelectorAll( '.lynxjournal-preview-width-btn' ).forEach( b => b.classList.remove( 'is-active' ) );
			btn.classList.add( 'is-active' );
			preview.classList.toggle( 'is-width-mobile', btn.dataset.width === 'mobile' );
		} );
	} );

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
