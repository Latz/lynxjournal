( function () {
	var textarea = null;
	var preview  = null;

	var _data            = window.lynxjournalPreviewData || {};
	var scalarData       = _data.scalar || {};
	var categoryVariants = ( _data.categories || [] ).map( function ( cat ) {
		var entry                        = { links: cat.links || [] };
		entry[ cat.token ]               = cat.name;
		entry[ '[category_link_count]' ] = String( ( cat.links || [] ).length );
		return entry;
	} );

	// ── Token replacement ───────────────────────────────────────

	var LINK_TOKENS = [
		'[link]', '[link_description]',
		'[link_domain]', '[link_date]',
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

	function hasLinkToken( line ) {
		return LINK_TOKENS.some( function ( t ) { return line.indexOf( t ) !== -1; } );
	}

	function expandLinkLines( text, links ) {
		var lines  = text.split( '\n' );
		var result = [];
		var i = 0;
		while ( i < lines.length ) {
			if ( ! hasLinkToken( lines[i] ) ) {
				result.push( lines[i] );
				i++;
				continue;
			}
			var group = [];
			while ( i < lines.length && hasLinkToken( lines[i] ) ) {
				group.push( lines[i] );
				i++;
			}
			links.forEach( function ( link ) {
				group.forEach( function ( groupLine ) {
					result.push( replaceTokens( groupLine, link ) );
				} );
			} );
		}
		return result.join( '\n' );
	}

	// ── Preview ─────────────────────────────────────────────────

	function updateTemplatePreview() {
		var text = textarea.value;

		// 1. Expand [category_start]...[category_end] → 3 category iterations.
		text = text.replace(
			/\[category_start\]([\s\S]*?)\[category_end\]/g,
			function ( _match, inner ) {
				return categoryVariants.map( function ( cat ) {
					var catText = replaceTokens( inner, {
					'[category_name]'       : cat['[category_name]'],
					'[category_link_count]' : cat['[category_link_count]'],
				} );
					catText = expandLinkBlocks( catText, cat.links );
					catText = expandLinkLines( catText, cat.links );
					return catText;
				} ).join( '' );
			}
		);

		// 2. Expand remaining [link_start]...[link_end] outside category blocks.
		text = expandLinkBlocks( text, categoryVariants[0].links );

		// 3. Replace scalar tokens.
		text = replaceTokens( text, scalarData );

		// 3.5. Convert indented lines (2+ spaces) to padded divs so the
		//      preview shows visual indentation regardless of parent context.
		//      Matches both "  - item" (bullet) and "  plain text" (no bullet).
		text = text.split( '\n' ).map( function ( line ) {
			var m = line.match( /^( {2,})(- )?(.+)/ );
			if ( ! m ) { return line; }
			var level   = Math.floor( m[1].length / 2 );
			var isList  = !! m[2];
			var content = window.marked.parseInline( m[3] );
			return '<div style="padding-left:' + ( level * 1.5 ) + 'em">'
				+ ( isList ? '• ' : '' ) + content + '</div>';
		} ).join( '\n' );

		// 4. Render Markdown.
		preview.innerHTML = text.trim()
			? window.marked.parse( text )
			: '<span class="lynxjournal-preview-empty">—</span>';
	}

	// ── Toolbar ─────────────────────────────────────────────────

	function getLineStart( value, pos ) {
		var idx = value.lastIndexOf( '\n', pos - 1 );
		return idx === -1 ? 0 : idx + 1;
	}

	function applyFormat( action ) {
		var start  = textarea.selectionStart;
		var end    = textarea.selectionEnd;
		var value  = textarea.value;
		var sel    = value.slice( start, end );
		var newVal, cursor;

		if ( action === 'bold' ) {
			var inner  = sel || 'bold text';
			newVal     = value.slice( 0, start ) + '**' + inner + '**' + value.slice( end );
			cursor     = sel ? end + 4 : start + 2 + inner.length;
		} else if ( action === 'italic' ) {
			var inner  = sel || 'italic text';
			newVal     = value.slice( 0, start ) + '*' + inner + '*' + value.slice( end );
			cursor     = sel ? end + 2 : start + 1 + inner.length;
		} else if ( action === 'h2' || action === 'h3' || action === 'list' ) {
			var prefix  = action === 'h2' ? '## ' : action === 'h3' ? '### ' : '- ';
			var lineStart = getLineStart( value, start );
			newVal  = value.slice( 0, lineStart ) + prefix + value.slice( lineStart );
			cursor  = start + prefix.length;
		} else if ( action === 'indent' ) {
			var lineStart = getLineStart( value, start );
			newVal  = value.slice( 0, lineStart ) + '  ' + value.slice( lineStart );
			cursor  = start + 2;
		} else if ( action === 'outdent' ) {
			var lineStart  = getLineStart( value, start );
			var lineText   = value.slice( lineStart );
			var spaces     = lineText.match( /^ {1,2}/ );
			var removed    = spaces ? spaces[0].length : 0;
			newVal  = value.slice( 0, lineStart ) + value.slice( lineStart + removed );
			cursor  = Math.max( lineStart, start - removed );
		} else if ( action === 'hr' ) {
			var insert = '\n---\n';
			newVal  = value.slice( 0, start ) + insert + value.slice( end );
			cursor  = start + insert.length;
		} else {
			return;
		}

		textarea.value = newVal;
		textarea.setSelectionRange( cursor, cursor );
		textarea.focus();
		updateTemplatePreview();
	}

	// ── Init ────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		textarea = document.getElementById( 'lynxjournal-post-template' );
		preview  = document.getElementById( 'lynxjournal-template-preview' );

		if ( ! textarea || ! preview ) {
			return;
		}

		textarea.addEventListener( 'input', updateTemplatePreview );
		updateTemplatePreview();

		document.querySelectorAll( '.lynxjournal-format-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				applyFormat( this.dataset.action );
			} );
		} );

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
