/* HelloAdmin Guard settings page: remembers whether the welcome message is folded. */
( function () {
	var box = document.querySelector( '.hag-welcome' );
	var data = window.helloadminGuard;
	if ( ! box || ! data || ! window.fetch ) {
		return;
	}
	box.addEventListener( 'toggle', function () {
		var body = new URLSearchParams( {
			action: data.action,
			_ajax_nonce: data.nonce,
			state: box.open ? 'unfold' : 'fold'
		} );
		window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
	} );
	var gotIt = box.querySelector( '.hag-welcome-fold' );
	if ( gotIt ) {
		gotIt.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			box.open = false;
			box.querySelector( 'summary' ).focus();
		} );
	}
}() );

/* Checks what the site answers to visitors: right away when there are no results yet, or on "Check again". */
( function () {
	var data = window.helloadminGuard;
	var button = document.querySelector( '.hag-check-now' );
	if ( ! data || ! button || ! window.fetch ) {
		return;
	}
	var running = false;
	var label = button.textContent;
	var spinner = document.createElement( 'span' );
	spinner.className = 'spinner is-active hag-spinner';
	function done() {
		running = false;
		button.disabled = false;
		button.textContent = label;
		spinner.remove();
	}
	function check() {
		if ( running ) {
			return;
		}
		running = true;
		button.disabled = true;
		button.textContent = data.checkingText || label;
		button.after( spinner );
		var body = new URLSearchParams( { action: data.checkAction, _ajax_nonce: data.checkNonce } );
		window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result && result.success ) {
					window.location.reload();
				} else {
					done();
				}
			} )
			.catch( done );
	}
	button.addEventListener( 'click', check );
	if ( data.checkNeeded ) {
		check();
	}
}() );
