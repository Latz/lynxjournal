( function () {
	var textarea = null;
	var preview  = null;

	var scalarData = {
		'[title]'          : 'Wochenrückblick KW 26',
		'[date]'           : '29.06.2026',
		'[author]'         : 'Latz',
		'[site_name]'      : 'Mein Blog',
		'[link_count]'     : '3',
		'[category]'       : 'Technologie, Design, Open Source',
		'[category_list]'  : 'Technologie, Design, Open Source',
		'[tags]'           : 'javascript, typescript, webdev',
		'[unpublished]'    : '3',
		'[oldest_link_date]': '21.06.2026',
	};

	var linkVariants = [
		{
			'[link_title]'      : 'Warum TypeScript die bessere Wahl ist',
			'[link_url]'        : 'https://example.com/typescript-vs-javascript',
			'[link_description]': 'Ein ausführlicher Vergleich beider Sprachen mit praktischen Beispielen.',
			'[link_domain]'     : 'example.com',
			'[link_date]'       : '27.06.2026',
		},
		{
			'[link_title]'      : 'CSS Grid vs. Flexbox',
			'[link_url]'        : 'https://css-tricks.com/snippets/css/complete-guide-grid/',
			'[link_description]': 'Wann welches Layout-Modell sinnvoll ist.',
			'[link_domain]'     : 'css-tricks.com',
			'[link_date]'       : '25.06.2026',
		},
		{
			'[link_title]'      : 'Open Source im Wandel',
			'[link_url]'        : 'https://opensource.com/article/trends',
			'[link_description]': 'Aktuelle Entwicklungen in der Open-Source-Welt.',
			'[link_domain]'     : 'opensource.com',
			'[link_date]'       : '23.06.2026',
		},
	];

	function replaceLinkTokens( text, variant ) {
		Object.keys( variant ).forEach( function ( token ) {
			text = text.split( token ).join( variant[ token ] );
		} );
		return text;
	}

	function escapeHtml( text ) {
		return text
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function updateTemplatePreview() {
		var text = textarea.value;

		// Expand [link_start]...[link_end] blocks into 3 simulated iterations.
		text = text.replace(
			/\[link_start\]([\s\S]*?)\[link_end\]/g,
			function ( _match, inner ) {
				return linkVariants.map( function ( variant ) {
					return replaceLinkTokens( inner, variant );
				} ).join( '' );
			}
		);

		// Strip category block markers (they are invisible structural wrappers).
		text = text.replace( /\[category_start\]/g, '' );
		text = text.replace( /\[category_end\]/g, '' );

		// Replace scalar tokens.
		Object.keys( scalarData ).forEach( function ( token ) {
			text = text.split( token ).join( scalarData[ token ] );
		} );

		// Escape HTML and convert newlines.
		text = escapeHtml( text ).replace( /\n/g, '<br>' );

		preview.innerHTML = text || '<span class="lynxjournal-preview-empty">—</span>';
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		textarea = document.getElementById( 'lynxjournal-post-template' );
		preview  = document.getElementById( 'lynxjournal-template-preview' );

		if ( ! textarea || ! preview ) {
			return;
		}

		textarea.addEventListener( 'input', updateTemplatePreview );
		updateTemplatePreview();

		document.querySelectorAll( '.lynxjournal-insert-token' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var token  = this.dataset.token;
				var start  = textarea.selectionStart;
				var end    = textarea.selectionEnd;
				textarea.value = textarea.value.slice( 0, start ) + token + textarea.value.slice( end );
				var cursor = start + token.length;
				textarea.setSelectionRange( cursor, cursor );
				textarea.focus();
				updateTemplatePreview();
			} );
		} );
	} );
} )();
