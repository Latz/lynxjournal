export const LINK_TOKENS = Object.freeze( [
	'[link]', '[link_description]',
	'[link_domain]', '[link_date]',
] );

/**
 * @param {string} text
 * @param {Record<string, string>} data
 * @returns {string}
 */
export function replaceTokens( text, data ) {
	for ( const [ token, value ] of Object.entries( data ) ) {
		text = text.replaceAll( token, value );
	}
	return text;
}

/**
 * @param {string} line
 * @returns {boolean}
 */
export function hasLinkToken( line ) {
	return LINK_TOKENS.some( t => line.includes( t ) );
}

/**
 * @param {string} text
 * @param {Array<Record<string, string>>} links
 * @returns {string}
 */
export function expandLinkBlocks( text, links ) {
	return text.replace(
		/\[link_start\]([\s\S]*?)\[link_end\]/g,
		( _match, inner ) => links.map( variant => replaceTokens( inner, variant ) ).join( '' )
	);
}

/**
 * @param {string} text
 * @param {Array<Record<string, string>>} links
 * @returns {string}
 */
export function expandLinkLines( text, links ) {
	const lines  = text.split( '\n' );
	const result = [];
	let i = 0;
	while ( i < lines.length ) {
		if ( !hasLinkToken( lines[ i ] ) ) {
			result.push( lines[ i ] );
			i++;
			continue;
		}
		const group = [];
		while ( i < lines.length && hasLinkToken( lines[ i ] ) ) {
			group.push( lines[ i ] );
			i++;
		}
		links.forEach( link => {
			group.forEach( groupLine => result.push( replaceTokens( groupLine, link ) ) );
		} );
	}
	return result.join( '\n' );
}

/**
 * @param {string} text
 * @returns {string[]}
 */
export function validateTemplate( text ) {
	const warnings = [];
	const cs = ( text.match( /\[category_start\]/g ) ?? [] ).length;
	const ce = ( text.match( /\[category_end\]/g )   ?? [] ).length;
	const ls = ( text.match( /\[link_start\]/g )     ?? [] ).length;
	const le = ( text.match( /\[link_end\]/g )       ?? [] ).length;
	if ( cs !== ce ) {
		warnings.push( `[category_start] / [category_end] mismatch (${ cs } / ${ ce })` );
	}
	if ( ls !== le ) {
		warnings.push( `[link_start] / [link_end] mismatch (${ ls } / ${ le })` );
	}
	return warnings;
}

/**
 * @param {string} value
 * @param {number} pos
 * @returns {number}
 */
export function getLineStart( value, pos ) {
	const idx = value.lastIndexOf( '\n', pos - 1 );
	return idx === -1 ? 0 : idx + 1;
}

/**
 * Determines which toolbar actions apply to the current cursor position on
 * a single line, so the matching toolbar button can be shown as "pressed" —
 * mirroring the toggle-state UX of a typical rich text editor toolbar.
 *
 * @param {string} line
 * @param {number} ch
 * @returns {Set<string>}
 */
export function getActiveFormatsAtCursor( line, ch ) {
	const active = new Set();

	const heading = /^(#{1,6})\s/.exec( line );
	if ( heading ) { active.add( `h${ heading[ 1 ].length }` ); }
	if ( /^-\s/.test( line ) ) { active.add( 'list' ); }
	if ( /^\d+\.\s/.test( line ) ) { active.add( 'ol' ); }

	/** @param {RegExp} regex */
	const cursorIsWithin = regex => {
		regex.lastIndex = 0;
		let match;
		while ( ( match = regex.exec( line ) ) !== null ) {
			if ( ch >= match.index && ch <= match.index + match[ 0 ].length ) { return true; }
		}
		return false;
	};

	if ( cursorIsWithin( /\*\*[^*]*?\*\*/g ) ) { active.add( 'bold' ); }
	if ( cursorIsWithin( /(?<!\*)\*[^*]+?\*(?!\*)/g ) ) { active.add( 'italic' ); }
	if ( cursorIsWithin( /<u>[\s\S]*?<\/u>/g ) ) { active.add( 'underline' ); }

	return active;
}

/**
 * Markdown collapses any run of blank lines into a single paragraph break,
 * so a blank line the user typed never actually shows up as visible empty
 * space — the shared, deliberately compact `<p>` margin used for regular
 * preview content is too tight to read as a blank line either. This turns
 * every blank line in a run into its own `.lynxjournal-blank-line`
 * paragraph (a raw HTML block CommonMark passes through unchanged), which
 * gets a dedicated CSS rule (left border + reserved height) so it reads as
 * a distinct blank line even when trailing/leading with nothing adjacent.
 *
 * @param {string} text
 * @returns {string}
 */
export function preserveBlankLines( text ) {
	if ( text === '' ) { return text; }
	const lines  = text.split( '\n' );
	const result = [];
	let i = 0;
	while ( i < lines.length ) {
		if ( lines[ i ].trim() !== '' ) {
			result.push( lines[ i ] );
			i++;
			continue;
		}
		let j = i;
		while ( j < lines.length && lines[ j ].trim() === '' ) { j++; }
		const blankCount = j - i;
		result.push( '' );
		for ( let k = 0; k < blankCount; k++ ) {
			result.push( '<p class="lynxjournal-blank-line">&nbsp;</p>', '' );
		}
		i = j;
	}
	return result.join( '\n' );
}
