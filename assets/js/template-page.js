( function () {
	var textarea = null;
	var preview  = null;

	var scalarData = {
		'[title]'           : 'Weekly Roundup #26',
		'[date]'            : '2026-06-29',
		'[author]'          : 'Latz',
		'[site_name]'       : 'My Blog',
		'[link_count]'      : '9',
		'[category_name]'   : 'Travel',
		'[category_list]'   : 'Travel, Food & Cooking, Nature & Environment',
		'[tags]'            : 'tips, guide, lifestyle',
		'[unpublished]'     : '4',
		'[oldest_link_date]': '2026-06-21',
		// Fallbacks for link tokens used outside a link block
		'[link_title]'      : '10 Tips for Stress-Free Travel',
		'[link_url]'        : 'https://travelblog.com/stress-free-travel',
		'[link_description]': 'Everything you need to know before your next trip.',
		'[link_domain]'     : 'travelblog.com',
		'[link_date]'       : '2026-06-27',
	};

	var categoryVariants = [
		{
			'[category_name]': 'Travel',
			links: [
				{
					'[link_title]'      : '10 Tips for Stress-Free Travel',
					'[link_url]'        : 'https://travelblog.com/stress-free-travel',
					'[link_description]': 'Everything you need to know before your next trip.',
					'[link_domain]'     : 'travelblog.com',
					'[link_date]'       : '2026-06-27',
				},
				{
					'[link_title]'      : 'Hidden Gems in Southern Europe',
					'[link_url]'        : 'https://wanderlust.io/southern-europe-gems',
					'[link_description]': 'Lesser-known destinations worth adding to your bucket list.',
					'[link_domain]'     : 'wanderlust.io',
					'[link_date]'       : '2026-06-25',
				},
				{
					'[link_title]'      : 'How to Pack Light for Any Trip',
					'[link_url]'        : 'https://packingpro.net/pack-light',
					'[link_description]': 'A minimalist packing guide for travellers of all kinds.',
					'[link_domain]'     : 'packingpro.net',
					'[link_date]'       : '2026-06-24',
				},
			],
		},
		{
			'[category_name]': 'Food & Cooking',
			links: [
				{
					'[link_title]'      : 'Easy Weeknight Pasta in 20 Minutes',
					'[link_url]'        : 'https://kitchenstories.com/pasta-20min',
					'[link_description]': 'Quick, satisfying meals for busy evenings.',
					'[link_domain]'     : 'kitchenstories.com',
					'[link_date]'       : '2026-06-26',
				},
				{
					'[link_title]'      : "Beginner's Guide to Plant-Based Eating",
					'[link_url]'        : 'https://eatwell.org/plant-based-guide',
					'[link_description]': 'How to get started without giving up your favourite foods.',
					'[link_domain]'     : 'eatwell.org',
					'[link_date]'       : '2026-06-24',
				},
				{
					'[link_title]'      : '5 Soups You Can Make from Leftovers',
					'[link_url]'        : 'https://homecook.io/leftover-soups',
					'[link_description]': 'Reduce food waste and eat well at the same time.',
					'[link_domain]'     : 'homecook.io',
					'[link_date]'       : '2026-06-22',
				},
			],
		},
		{
			'[category_name]': 'Nature & Environment',
			links: [
				{
					'[link_title]'      : 'Why Bees Are Essential to Our Ecosystem',
					'[link_url]'        : 'https://naturemag.org/bees-ecosystem',
					'[link_description]': 'A closer look at the role pollinators play in our food supply.',
					'[link_domain]'     : 'naturemag.org',
					'[link_date]'       : '2026-06-25',
				},
				{
					'[link_title]'      : 'Simple Everyday Habits for Climate Action',
					'[link_url]'        : 'https://greenliving.net/climate-habits',
					'[link_description]': 'Small changes that add up to a real difference.',
					'[link_domain]'     : 'greenliving.net',
					'[link_date]'       : '2026-06-23',
				},
				{
					'[link_title]'      : 'Best Hiking Trails for Beginners',
					'[link_url]'        : 'https://outdoorguide.com/beginner-hiking',
					'[link_description]': 'Accessible routes that still offer stunning views.',
					'[link_domain]'     : 'outdoorguide.com',
					'[link_date]'       : '2026-06-21',
				},
			],
		},
	];

	// ── Token replacement ───────────────────────────────────────

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

	// ── Preview ─────────────────────────────────────────────────

	function updateTemplatePreview() {
		var text = textarea.value;

		// 1. Expand [category_start]...[category_end] → 3 category iterations.
		text = text.replace(
			/\[category_start\]([\s\S]*?)\[category_end\]/g,
			function ( _match, inner ) {
				return categoryVariants.map( function ( cat ) {
					var catText = replaceTokens( inner, { '[category_name]': cat['[category_name]'] } );
					return expandLinkBlocks( catText, cat.links );
				} ).join( '' );
			}
		);

		// 2. Expand remaining [link_start]...[link_end] outside category blocks.
		text = expandLinkBlocks( text, categoryVariants[0].links );

		// 3. Replace scalar tokens.
		text = replaceTokens( text, scalarData );

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
