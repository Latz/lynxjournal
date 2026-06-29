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
