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

/* Hide user names: the Fix button works without reloading the page; the result shows in the row. */
( function () {
	var data = window.helloadminGuard;
	var box = document.querySelector( '.hag-fix' );
	if ( ! data || ! box || ! window.fetch ) {
		return;
	}
	var table = box.querySelector( '.hag-fix-table' );
	var summary = box.querySelector( '.hag-fix-summary' );
	var total = table.querySelectorAll( 'tbody tr' ).length;
	var fixed = 0;

	function note( cell, text, cls ) {
		var old = cell.querySelector( '.hag-fix-msg' );
		if ( old ) {
			old.remove();
		}
		var p = document.createElement( 'p' );
		p.className = 'hag-fix-msg ' + cls;
		p.textContent = text;
		cell.appendChild( p );
	}

	function done() {
		fixed++;
		if ( fixed < total ) {
			summary.textContent = data.fixCount.replace( '%1$d', fixed ).replace( '%2$d', total );
			return;
		}
		var more = parseInt( summary.getAttribute( 'data-more' ), 10 ) || 0;
		summary.textContent = data.fixAllDone + ( more ? ' ' + data.fixMore : '' );
		summary.classList.add( 'hag-fix-all' );
		table.hidden = true;
	}

	function fix( button ) {
		var row = button.closest( 'tr' );
		var input = row.querySelector( 'input' );
		var cell = input.parentNode;
		var label = button.textContent;
		button.disabled = true;
		button.textContent = data.fixWorking;
		var body = new URLSearchParams( {
			action: data.fixAction,
			user: button.getAttribute( 'data-user' ),
			nonce: button.getAttribute( 'data-nonce' ),
			name: input.value
		} );
		window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				var message = result && result.data && result.data.message ? result.data.message : data.fixFailed;
				if ( result && result.success ) {
					var td = document.createElement( 'td' );
					td.colSpan = 2;
					td.className = 'hag-fix-ok';
					td.textContent = '✓ ' + message;
					button.parentNode.remove();
					cell.replaceWith( td );
					row.classList.add( 'hag-fix-done' );
					done();
					return;
				}
				note( cell, message, 'hag-fix-error' );
				button.disabled = false;
				button.textContent = label;
				input.focus();
			} )
			.catch( function () {
				note( cell, data.fixFailed, 'hag-fix-error' );
				button.disabled = false;
				button.textContent = label;
			} );
	}

	table.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button[data-user]' );
		if ( button ) {
			event.preventDefault();
			fix( button );
		}
	} );
	// Enter in a name field fixes that user instead of saving the whole settings form.
	table.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key && event.target.matches( 'input' ) ) {
			event.preventDefault();
			var button = event.target.closest( 'tr' ).querySelector( 'button[data-user]' );
			if ( button && ! button.disabled ) {
				fix( button );
			}
		}
	} );
}() );
