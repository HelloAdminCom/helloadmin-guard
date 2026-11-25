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
					var check = document.createElement( 'span' );
					var url = result.data.url || '';
					td.colSpan = 2;
					td.className = 'hag-fix-ok';
					check.className = 'hag-fix-check';
					check.setAttribute( 'aria-hidden', 'true' );
					check.textContent = '✓';
					td.appendChild( check );
					// The new author page address becomes a link; the rest stays plain text.
					var at = url ? message.indexOf( url ) : -1;
					td.appendChild( document.createTextNode( at < 0 ? message : message.slice( 0, at ) ) );
					if ( at >= 0 ) {
						var link = document.createElement( 'a' );
						link.href = url;
						link.target = '_blank';
						link.rel = 'noopener';
						link.textContent = url;
						td.appendChild( link );
						td.appendChild( document.createTextNode( message.slice( at + url.length ) ) );
					}
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

/* Card views: the icon buttons switch the view at once and save it for this user. In the short views "Details" opens
   the card's texts, fields and rules in a panel across the whole width under the card's row. */
( function () {
	var data = window.helloadminGuard;
	var cards = document.querySelector( '.hag-cards' );
	var buttons = document.querySelectorAll( '.hag-view' );
	if ( ! data || ! cards ) {
		return;
	}
	var panel = document.createElement( 'div' );
	panel.className = 'hag-panel';
	panel.setAttribute( 'role', 'region' );
	var active = null;

	function columns() {
		return window.getComputedStyle( cards ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length || 1;
	}
	function close() {
		if ( ! active ) {
			return;
		}
		var body = panel.querySelector( '.hag-body' );
		var link = active.querySelector( '.hag-toggle-body' );
		// The body goes back into its card (before the link), so the form and the full view stay as they were.
		if ( body ) {
			active.insertBefore( body, link );
		}
		panel.remove();
		panel.textContent = '';
		active.classList.remove( 'hag-active' );
		link.setAttribute( 'aria-expanded', 'false' );
		link.textContent = data.showText;
		active = null;
	}
	function open( card ) {
		close();
		var link = card.querySelector( '.hag-toggle-body' );
		var body = card.querySelector( '.hag-body' );
		if ( ! body ) {
			return;
		}
		var title = document.createElement( 'p' );
		title.className = 'hag-panel-title';
		title.textContent = card.querySelector( '.hag-title' ).textContent;
		panel.appendChild( title );
		panel.appendChild( body );
		panel.setAttribute( 'aria-label', title.textContent );
		// Under the last card of this card's row: cards have the even CSS order numbers 0, 2, 4…
		var index = parseInt( card.style.order, 10 ) / 2;
		var cols = columns();
		var last = Math.min( Math.floor( index / cols ) * cols + cols - 1, cards.querySelectorAll( '.hag-card' ).length - 1 );
		panel.style.order = String( last * 2 + 1 );
		card.parentNode.appendChild( panel );
		card.classList.add( 'hag-active' );
		link.setAttribute( 'aria-expanded', 'true' );
		link.textContent = data.hideText;
		active = card;
		panel.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
	}

	Array.prototype.forEach.call( buttons, function ( button ) {
		button.addEventListener( 'click', function () {
			var view = button.getAttribute( 'data-view' );
			close();
			cards.className = cards.className.replace( /\bhag-view-\w+/, 'hag-view-' + view );
			Array.prototype.forEach.call( buttons, function ( other ) {
				other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
			} );
			if ( window.fetch ) {
				var body = new URLSearchParams( { action: data.viewAction, _ajax_nonce: data.viewNonce, view: view } );
				window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
			}
		} );
	} );
	cards.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.hag-toggle-body' );
		if ( ! link ) {
			return;
		}
		var card = link.closest( '.hag-card' );
		if ( card === active ) {
			close();
		} else {
			open( card );
		}
	} );
	// A click outside the card and its panel closes the panel.
	document.addEventListener( 'click', function ( event ) {
		if ( active && ! active.contains( event.target ) && ! panel.contains( event.target ) && ! event.target.closest( '.hag-views' ) ) {
			close();
		}
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && active ) {
			var link = active.querySelector( '.hag-toggle-body' );
			close();
			link.focus();
		}
	} );
	// The panel follows its row when the window changes from two columns to one or back.
	window.addEventListener( 'resize', function () {
		if ( active ) {
			open( active );
		}
	} );
	// A link to a card (#hag-…-card, for example from the gap list) opens its details in the short views.
	if ( window.location.hash && ! /hag-view-full/.test( cards.className ) ) {
		var target = document.getElementById( window.location.hash.slice( 1 ) );
		var card = target && target.closest( '.hag-card' );
		if ( card && cards.contains( card ) ) {
			open( card );
		}
	}
}() );
