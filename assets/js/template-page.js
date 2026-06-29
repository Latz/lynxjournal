( function () {
	var editor     = null;   // CodeMirror instance; null = plain-textarea fallback
	var textarea   = null;
	var preview    = null;
	var inputTimer = null;
	var btnUndo, btnRedo;

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

	function getEditorValue() {
		return editor ? editor.getValue() : ( textarea ? textarea.value : '' );
	}

	function updateTemplatePreview() {
		var text = getEditorValue();

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

		text = expandLinkBlocks( text, categoryVariants[0] ? categoryVariants[0].links : [] );
		text = replaceTokens( text, scalarData );

		// Convert indented lines (2+ spaces) to padded divs for visual indentation.
		text = text.split( '\n' ).map( function ( line ) {
			var m = line.match( /^( {2,})(- )?(.+)/ );
			if ( ! m ) { return line; }
			var level   = Math.floor( m[1].length / 2 );
			var isList  = !! m[2];
			var content = window.marked.parseInline( m[3] );
			return '<div style="padding-left:' + ( level * 1.5 ) + 'em">'
				+ ( isList ? '• ' : '' ) + content + '</div>';
		} ).join( '\n' );

		preview.innerHTML = text.trim()
			? window.marked.parse( text )
			: '<span class="lynxjournal-preview-empty">—</span>';
	}

	// ── Undo/redo button state ──────────────────────────────────

	function updateUndoRedoState() {
		if ( editor ) {
			var hist = editor.historySize();
			if ( btnUndo ) { btnUndo.disabled = hist.undo === 0; }
			if ( btnRedo ) { btnRedo.disabled = hist.redo === 0; }
		} else {
			if ( btnUndo ) { btnUndo.disabled = true; }
			if ( btnRedo ) { btnRedo.disabled = true; }
		}
	}

	// ── Custom CodeMirror overlay for [token] highlighting ──────

	function defineTokenOverlay() {
		wp.CodeMirror.defineMode( 'lynxjournal', function ( config ) {
			return wp.CodeMirror.overlayMode(
				wp.CodeMirror.getMode( config, { name: 'null' } ),
				{
					token: function ( stream ) {
						if ( stream.match( /\[[^\]]*\]/ ) ) { return 'lynxjournal-token'; }
						stream.next();
						return null;
					}
				}
			);
		} );
	}

	// ── Line-prefix helper (headings, lists) ────────────────────

	function applyLinePrefix( prefix ) {
		var cursor   = editor.getCursor();
		var lineNum  = cursor.line;
		var line     = editor.getLine( lineNum );
		var stripped = line.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
		var removed  = line.length - stripped.length;
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

	// ── Toolbar: CodeMirror path ────────────────────────────────

	function applyFormat( action ) {
		if ( ! editor ) { fallbackApplyFormat( action ); return; }

		if ( action === 'undo' ) {
			editor.undo();
			updateTemplatePreview();
			updateUndoRedoState();
			return;
		}
		if ( action === 'redo' ) {
			editor.redo();
			updateTemplatePreview();
			updateUndoRedoState();
			return;
		}

		var sel = editor.getSelection();

		if ( action === 'bold' ) {
			editor.replaceSelection( '**' + ( sel || 'bold text' ) + '**' );
		} else if ( action === 'italic' ) {
			editor.replaceSelection( '*' + ( sel || 'italic text' ) + '*' );
		} else if ( action === 'underline' ) {
			editor.replaceSelection( '<u>' + ( sel || 'underlined text' ) + '</u>' );
		} else if ( /^h[1-6]$/.test( action ) ) {
			applyLinePrefix( '#'.repeat( parseInt( action[1], 10 ) ) + ' ' );
		} else if ( action === 'list' ) {
			applyLinePrefix( '- ' );
		} else if ( action === 'ol' ) {
			applyLinePrefix( '1. ' );
		} else if ( action === 'indent' ) {
			var cur = editor.getCursor();
			editor.replaceRange( '  ', { line: cur.line, ch: 0 } );
		} else if ( action === 'outdent' ) {
			var cur  = editor.getCursor();
			var line = editor.getLine( cur.line );
			var sp   = line.match( /^ {1,2}/ );
			if ( sp ) {
				editor.replaceRange( '', { line: cur.line, ch: 0 }, { line: cur.line, ch: sp[0].length } );
			}
		} else if ( action === 'hr' ) {
			editor.replaceSelection( '\n---\n' );
		}

		editor.focus();
		updateTemplatePreview();
		updateUndoRedoState();
	}

	// ── Toolbar: plain-textarea fallback ────────────────────────

	var undoStack = [];
	var redoStack = [];
	var MAX_HIST  = 100;

	function saveSnapshot() {
		undoStack.push( { value: textarea.value, start: textarea.selectionStart, end: textarea.selectionEnd } );
		if ( undoStack.length > MAX_HIST ) { undoStack.shift(); }
		redoStack = [];
	}

	function restoreSnapshot( snap ) {
		textarea.value = snap.value;
		textarea.setSelectionRange( snap.start, snap.end );
		textarea.focus();
		updateTemplatePreview();
	}

	function getLineStart( value, pos ) {
		var idx = value.lastIndexOf( '\n', pos - 1 );
		return idx === -1 ? 0 : idx + 1;
	}

	function fallbackApplyFormat( action ) {
		var start = textarea.selectionStart;
		var end   = textarea.selectionEnd;
		var value = textarea.value;
		var newVal, cursor;

		if ( action === 'undo' ) {
			if ( ! undoStack.length ) { return; }
			redoStack.push( { value: value, start: start, end: end } );
			restoreSnapshot( undoStack.pop() );
			return;
		}
		if ( action === 'redo' ) {
			if ( ! redoStack.length ) { return; }
			undoStack.push( { value: value, start: start, end: end } );
			restoreSnapshot( redoStack.pop() );
			return;
		}

		saveSnapshot();
		var sel = value.slice( start, end );

		if ( action === 'bold' ) {
			var inner = sel || 'bold text';
			newVal = value.slice( 0, start ) + '**' + inner + '**' + value.slice( end );
			cursor = sel ? end + 4 : start + 2 + inner.length;
		} else if ( action === 'italic' ) {
			var inner = sel || 'italic text';
			newVal = value.slice( 0, start ) + '*' + inner + '*' + value.slice( end );
			cursor = sel ? end + 2 : start + 1 + inner.length;
		} else if ( action === 'underline' ) {
			var inner = sel || 'underlined text';
			newVal = value.slice( 0, start ) + '<u>' + inner + '</u>' + value.slice( end );
			cursor = sel ? end + 7 : start + 3 + inner.length;
		} else if ( /^h[1-6]$/.test( action ) || action === 'list' ) {
			var prefix    = action === 'list' ? '- ' : '#'.repeat( parseInt( action[1], 10 ) ) + ' ';
			var lineStart = getLineStart( value, start );
			var rest      = value.slice( lineStart );
			var stripped  = rest.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
			var removed   = rest.length - stripped.length;
			newVal  = value.slice( 0, lineStart ) + prefix + stripped;
			cursor  = Math.max( lineStart + prefix.length, start - removed + prefix.length );
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
		} else if ( action === 'ol' ) {
			var prefix    = '1. ';
			var lineStart = getLineStart( value, start );
			var rest      = value.slice( lineStart );
			var stripped  = rest.replace( /^(#{1,6} |- |\d+\. |> )/, '' );
			var removed   = rest.length - stripped.length;
			newVal  = value.slice( 0, lineStart ) + prefix + stripped;
			cursor  = Math.max( lineStart + prefix.length, start - removed + prefix.length );
		} else {
			undoStack.pop();
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

		if ( ! textarea || ! preview ) { return; }

		btnUndo = document.querySelector( '.lynxjournal-format-btn[data-action="undo"]' );
		btnRedo = document.querySelector( '.lynxjournal-format-btn[data-action="redo"]' );

		if ( window.wp && window.wp.codeEditor && window.wp.CodeMirror ) {
			defineTokenOverlay();
			var settings = window.lynxjournalEditorSettings || {};
			if ( ! settings.codemirror ) { settings.codemirror = {}; }
			settings.codemirror.mode        = 'lynxjournal';
			settings.codemirror.lineWrapping = true;

			var instance = window.wp.codeEditor.initialize( textarea, settings );
			editor = instance.codemirror;

			editor.on( 'change', function () {
				clearTimeout( inputTimer );
				inputTimer = setTimeout( updateUndoRedoState, 300 );
				updateTemplatePreview();
			} );
		} else {
			// Accessibility mode or CodeMirror unavailable — use plain textarea.
			textarea.addEventListener( 'input', function () {
				clearTimeout( inputTimer );
				inputTimer = setTimeout( updateTemplatePreview, 100 );
			} );
			textarea.addEventListener( 'keydown', function ( e ) {
				if ( ! ( e.ctrlKey || e.metaKey ) ) { return; }
				if ( e.key === 'z' || e.key === 'Z' ) {
					e.preventDefault();
					fallbackApplyFormat( e.shiftKey ? 'redo' : 'undo' );
				} else if ( e.key === 'y' || e.key === 'Y' ) {
					e.preventDefault();
					fallbackApplyFormat( 'redo' );
				}
			} );
		}

		updateTemplatePreview();
		updateUndoRedoState();

		document.querySelectorAll( '.lynxjournal-format-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				applyFormat( this.dataset.action );
			} );
		} );

		var headingSelect = document.getElementById( 'lynxjournal-heading-select' );
		if ( headingSelect ) {
			headingSelect.addEventListener( 'change', function () {
				if ( this.value ) {
					applyFormat( this.value );
					this.value = '';
				}
			} );
		}

		document.querySelectorAll( '.lynxjournal-insert-token' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var token = this.dataset.token;
				if ( editor ) {
					editor.replaceSelection( token );
					editor.focus();
				} else {
					var start = textarea.selectionStart;
					var end   = textarea.selectionEnd;
					saveSnapshot();
					textarea.value = textarea.value.slice( 0, start ) + token + textarea.value.slice( end );
					textarea.setSelectionRange( start + token.length, start + token.length );
					textarea.focus();
				}
				updateTemplatePreview();
			} );
		} );
	} );
} )();
