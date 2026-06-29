( function () {
	var textarea = null;
	var preview  = null;

	var dummyData = {
		'[title]'          : 'Wochenrückblick KW 26',
		'[date]'           : '29.06.2026',
		'[author]'         : 'Latz',
		'[site_name]'      : 'Mein Blog',
		'[link_count]'     : '7',
		'[link_title]'     : 'Warum TypeScript die bessere Wahl ist',
		'[link_url]'       : 'https://example.com/typescript-vs-javascript',
		'[link_description]': 'Ein ausführlicher Vergleich beider Sprachen mit praktischen Beispielen.',
		'[link_domain]'    : 'example.com',
		'[link_date]'      : '27.06.2026',
		'[category]'       : 'Technologie, Design, Open Source',
		'[category_list]'  : 'Technologie, Design, Open Source',
		'[tags]'           : 'javascript, typescript, webdev',
		'[unpublished]'    : '3',
		'[oldest_link_date]': '21.06.2026',
	};

	function updateTemplatePreview() {
		var text = textarea.value;

		Object.keys( dummyData ).forEach( function ( token ) {
			text = text.split( token ).join( dummyData[ token ] );
		} );

		text = text
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /\n/g, '<br>' );

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
