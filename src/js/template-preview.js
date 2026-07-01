/**
 * @param {HTMLElement|null} previewStatus
 * @param {HTMLElement|null} preview
 */
export function setPreviewUpdating( previewStatus, preview ) {
	if ( previewStatus ) {
		previewStatus.textContent = 'Aktualisiert…';
		previewStatus.classList.add( 'is-updating' );
	}
	if ( preview ) { preview.classList.add( 'is-updating' ); }
}

/**
 * @param {HTMLElement|null} previewStatus
 * @param {HTMLElement|null} preview
 */
export function setPreviewLive( previewStatus, preview ) {
	if ( previewStatus ) {
		previewStatus.textContent = 'Live';
		previewStatus.classList.remove( 'is-updating' );
	}
	if ( preview ) { preview.classList.remove( 'is-updating' ); }
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
