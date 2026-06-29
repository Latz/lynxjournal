document.addEventListener( 'DOMContentLoaded', function () {
	var textarea = document.getElementById( 'lynxjournal-post-template' );
	if ( ! textarea ) {
		return;
	}

	document.querySelectorAll( '.lynxjournal-insert-token' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var token = this.dataset.token;
			var start = textarea.selectionStart;
			var end   = textarea.selectionEnd;
			textarea.value = textarea.value.slice( 0, start ) + token + textarea.value.slice( end );
			var cursor = start + token.length;
			textarea.setSelectionRange( cursor, cursor );
			textarea.focus();
		} );
	} );
} );
