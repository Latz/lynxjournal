( function () {
	var textarea = null;
	var preview  = null;

	var scalarData = {
		'[title]'           : 'Wochenrückblick KW 26',
		'[date]'            : '29.06.2026',
		'[author]'          : 'Latz',
		'[site_name]'       : 'Mein Blog',
		'[link_count]'      : '5',
		'[category]'        : 'Technologie',
		'[category_list]'   : 'Technologie, Design, Open Source',
		'[tags]'            : 'javascript, typescript, webdev',
		'[unpublished]'     : '3',
		'[oldest_link_date]': '21.06.2026',
		// Fallbacks for link tokens used outside a link block
		'[link_title]'      : 'Warum TypeScript die bessere Wahl ist',
		'[link_url]'        : 'https://example.com/typescript-vs-javascript',
		'[link_description]': 'Ein ausführlicher Vergleich beider Sprachen mit praktischen Beispielen.',
		'[link_domain]'     : 'example.com',
		'[link_date]'       : '27.06.2026',
	};

	var categoryVariants = [
		{
			'[category]': 'Technologie',
			links: [
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
			],
		},
		{
			'[category]': 'Design',
			links: [
				{
					'[link_title]'      : 'Figma Tipps für Einsteiger',
					'[link_url]'        : 'https://figma.com/blog/tips',
					'[link_description]': 'Praktische Tricks für den Design-Alltag.',
					'[link_domain]'     : 'figma.com',
					'[link_date]'       : '24.06.2026',
				},
			],
		},
		{
			'[category]': 'Open Source',
			links: [
				{
					'[link_title]'      : 'Open Source im Wandel',
					'[link_url]'        : 'https://opensource.com/article/trends',
					'[link_description]': 'Aktuelle Entwicklungen in der Open-Source-Welt.',
					'[link_domain]'     : 'opensource.com',
					'[link_date]'       : '23.06.2026',
				},
				{
					'[link_title]'      : 'Linux Kernel 6.9 veröffentlicht',
					'[link_url]'        : 'https://kernel.org/news',
					'[link_description]': 'Neue Features und Verbesserungen im Überblick.',
					'[link_domain]'     : 'kernel.org',
					'[link_date]'       : '22.06.2026',
				},
			],
		},
	];

	function replaceTokens( text, data ) {
		Object.keys( data ).forEach( function ( token ) {
			text = text.split( token ).join( data[ token ] );
		} );
		return text;
	}

	function expandLinkBlocks( text, links ) {
		return text.replace(
			/\[link_start\]([\s\S]*?)\[link_end\]/g,
			function ( _match, inner ) {
				return links.map( function ( variant ) {
					return replaceTokens( inner, variant );
				} ).join( '' );
			}
		);
	}

	function escapeHtml( text ) {
		return text
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function updateTemplatePreview() {
		var text = textarea.value;

		// 1. Expand [category_start]...[category_end] blocks — 3 category iterations.
		text = text.replace(
			/\[category_start\]([\s\S]*?)\[category_end\]/g,
			function ( _match, inner ) {
				return categoryVariants.map( function ( cat ) {
					var catText = replaceTokens( inner, { '[category]': cat['[category]'] } );
					return expandLinkBlocks( catText, cat.links );
				} ).join( '' );
			}
		);

		// 2. Expand any remaining [link_start]...[link_end] outside category blocks.
		text = expandLinkBlocks( text, categoryVariants[0].links );

		// 3. Replace scalar tokens (including link token fallbacks).
		text = replaceTokens( text, scalarData );

		// 4. Escape HTML and convert newlines.
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
