/**
 * Only updates the small status badge, not the preview content itself —
 * dimming the whole rendered preview on every debounced re-render caused a
 * visible flicker while typing, since natural typing pauses often exceed
 * the render debounce delay. Keeps the label text fixed at "Live" (greyed
 * out via the `is-updating` class instead of swapped for a longer string)
 * so the badge doesn't change width and cause the toolbar to jitter.
 *
 * @param {HTMLElement|null} previewStatus
 */
export function setPreviewUpdating( previewStatus ) {
	if ( !previewStatus ) { return; }
	previewStatus.classList.add( 'is-updating' );
}

/**
 * @param {HTMLElement|null} previewStatus
 */
export function setPreviewLive( previewStatus ) {
	if ( !previewStatus ) { return; }
	previewStatus.classList.remove( 'is-updating' );
}

/**
 * @param {HTMLElement|null} previewValidation
 * @param {string[]} warnings
 */
export function renderValidation( previewValidation, warnings ) {
	if ( !previewValidation ) { return; }
	previewValidation.hidden = warnings.length === 0;
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

/**
 * Expands category/link tokens in the raw template text into the final
 * preview text, ready for the indentation pass and markdown rendering.
 *
 * Takes the template-utils.js functions as parameters instead of importing
 * them directly: this module is loaded via dynamic `import()` from
 * template-page.js without the filemtime cache-busting query string that
 * template-utils.js needs, so a static import here would risk resolving a
 * stale cached copy of template-utils.js.
 *
 * @param {string} rawText
 * @param {CategoryVariant[]} categoryVariants
 * @param {Record<string, string>} scalarData
 * @param {{ replaceTokens: Function, expandLinkBlocks: Function, expandLinkLines: Function, preserveBlankLines: Function }} utils
 * @returns {string}
 */
export function buildTemplateText( rawText, categoryVariants, scalarData, utils ) {
	const { replaceTokens, expandLinkBlocks, expandLinkLines, preserveBlankLines } = utils;

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

	return text;
}

const TOP_LEVEL_LIST_RE = /^(-|\d+\.)\s/;
const INDENT_RE = /^( {2,})(?:(-|\d+\.) )?(.+)/;

/**
 * Converts indented lines (2+ leading spaces) into padded `<div>` wrappers so
 * indentation survives Markdown rendering, which otherwise discards leading
 * whitespace on plain paragraph text. Lines marked with `- ` or `N. ` are
 * real list items instead: consecutive same-level, same-type ("-" vs
 * numbered) list lines are grouped into one `<ul>`/`<ol>` with a `<li>` per
 * item, rather than one `<div>` per line. An indented list that directly
 * continues a preceding *unindented* list item (no blank line between them)
 * is left completely untouched instead — that's a genuine sub-list, and
 * `marked.js` already parses real nested `<ul>`/`<ol>` natively, so
 * flattening it here would lose the nesting. Batches each contiguous run of
 * indented lines into a single `parseInline()` call (joined/split on `\n`,
 * which `marked.parseInline()` preserves verbatim) instead of calling it
 * once per line, since each call carries its own lexer/tokenizer setup cost.
 *
 * @param {string} text
 * @param {(markdown: string) => string} parseInline
 * @returns {string}
 */
export function convertIndentedLines( text, parseInline ) {
	const lines  = text.split( '\n' );
	const result = [];
	let i = 0;

	while ( i < lines.length ) {
		const match = INDENT_RE.exec( lines[ i ] );
		if ( !match ) {
			result.push( lines[ i ] );
			i++;
			continue;
		}

		const prevRawLine = i > 0 ? lines[ i - 1 ] : '';
		const { matches, nextIndex } = collectIndentedRun( lines, i );
		i = nextIndex;

		if ( TOP_LEVEL_LIST_RE.test( prevRawLine ) ) {
			matches.forEach( m => result.push( m.input ) );
			continue;
		}

		result.push( ...renderIndentedGroup( matches, parseInline ) );
	}

	return result.join( '\n' );
}

/**
 * Collects the contiguous run of lines starting at `start` that match
 * INDENT_RE, stopping at the first non-matching (or missing) line.
 *
 * @param {string[]} lines
 * @param {number} start
 * @returns {{ matches: RegExpMatchArray[], nextIndex: number }}
 */
function collectIndentedRun( lines, start ) {
	const matches = [];
	let i = start;
	while ( i < lines.length ) {
		const match = INDENT_RE.exec( lines[ i ] );
		if ( !match ) { break; }
		matches.push( match );
		i++;
	}
	return { matches, nextIndex: i };
}

/**
 * Renders one contiguous group of indented-line matches into `<div>`
 * wrappers and grouped `<ul>`/`<ol>` list blocks, per the rules described
 * on convertIndentedLines() above.
 *
 * @param {RegExpMatchArray[]} group
 * @param {(markdown: string) => string} parseInline
 * @returns {string[]}
 */
function renderIndentedGroup( group, parseInline ) {
	const rendered = parseInline( group.map( g => g[ 3 ] ).join( '\n' ) ).split( '\n' );
	const output   = [];
	let listBuffer = null; // { type: 'ul'|'ol', level, items, start }

	const flushList = () => {
		if ( !listBuffer ) { return; }
		const { type, level, items, start } = listBuffer;
		const startAttr = type === 'ol' && start !== 1 ? ` start="${ start }"` : '';
		const listItems = items.map( item => `<li>${ item }</li>` ).join( '' );
		output.push(
			`<${ type } style="padding-left:${ level * 1.5 }em"${ startAttr }>${ listItems }</${ type }>`
		);
		listBuffer = null;
	};

	group.forEach( ( g, idx ) => {
		const level   = Math.floor( g[ 1 ].length / 2 );
		const marker  = g[ 2 ];
		const content = rendered[ idx ] ?? '';

		if ( !marker ) {
			flushList();
			output.push( `<div style="padding-left:${ level * 1.5 }em">${ content }</div>` );
			return;
		}

		const type = marker === '-' ? 'ul' : 'ol';
		const num  = type === 'ol' ? Number.parseInt( marker, 10 ) : 1;

		if ( listBuffer?.type === type && listBuffer?.level === level ) {
			listBuffer.items.push( content );
		} else {
			flushList();
			listBuffer = { type, level, items: [ content ], start: num };
		}
	} );

	flushList();
	return output;
}
