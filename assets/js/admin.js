/* Scrolls an element to the top of the screen, under the admin bar, in about 0.7 s with a soft start and end (the
   browser's own smooth scroll is too quick). Stops when the user starts scrolling; instant when the system asks for
   less motion. Used by the card views and the Tips tab. */
function hagScrollToTop( element, done ) {
	done = done || function () {};
	var bar = document.getElementById( 'wpadminbar' );
	// On phones WordPress lets the admin bar scroll away with the page: then it does not cover anything.
	var cover = bar && 'fixed' === window.getComputedStyle( bar ).position ? bar.offsetHeight : 0;
	var from = window.pageYOffset;
	var max = document.documentElement.scrollHeight - window.innerHeight;
	var to = Math.max( 0, Math.min( max, element.getBoundingClientRect().top + from - cover - 12 ) );
	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( still || Math.abs( to - from ) < 2 ) {
		window.scrollTo( 0, to );
		done();
		return;
	}
	var start = null;
	var stopped = false;
	var stop = function () {
		if ( ! stopped ) {
			stopped = true;
			done();
		}
	};
	window.addEventListener( 'wheel', stop, { once: true, passive: true } );
	window.addEventListener( 'touchstart', stop, { once: true, passive: true } );
	var step = function ( now ) {
		if ( stopped ) {
			return;
		}
		start = null === start ? now : start;
		var t = Math.min( 1, ( now - start ) / 700 );
		var ease = t < 0.5 ? 4 * t * t * t : 1 - Math.pow( -2 * t + 2, 3 ) / 2;
		window.scrollTo( 0, from + ( to - from ) * ease );
		if ( t < 1 ) {
			window.requestAnimationFrame( step );
		} else {
			window.removeEventListener( 'wheel', stop );
			window.removeEventListener( 'touchstart', stop );
			stopped = true;
			done();
		}
	};
	window.requestAnimationFrame( step );
}

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

	function idle( button ) {
		button.disabled = false;
		button.classList.remove( 'hag-busy' );
		button.removeAttribute( 'aria-label' );
	}

	function fix( button ) {
		var row = button.closest( 'tr' );
		var input = row.querySelector( 'input' );
		var cell = input.parentNode;
		// The label stays (a longer "working" text would widen the button and squeeze the field): the button pulses,
		// screen readers hear the working text.
		button.disabled = true;
		button.classList.add( 'hag-busy' );
		button.setAttribute( 'aria-label', data.fixWorking );
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
				idle( button );
				input.focus();
			} )
			.catch( function () {
				note( cell, data.fixFailed, 'hag-fix-error' );
				idle( button );
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

/* Card views: the icon buttons switch the view at once and save it for this user; "Details" opens one card. */
( function () {
	var data = window.helloadminGuard;
	var cards = document.querySelector( '.hag-cards' );
	var buttons = document.querySelectorAll( '.hag-view' );
	if ( ! data || ! cards ) {
		return;
	}
	Array.prototype.forEach.call( buttons, function ( button ) {
		button.addEventListener( 'click', function () {
			var view = button.getAttribute( 'data-view' );
			cards.className = cards.className.replace( /\bhag-view-\w+/, 'hag-view-' + view );
			Array.prototype.forEach.call( buttons, function ( other ) {
				other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
			} );
			Array.prototype.forEach.call( cards.querySelectorAll( '.hag-open' ), function ( card ) {
				toggle( card, false );
			} );
			if ( window.fetch ) {
				var body = new URLSearchParams( { action: data.viewAction, _ajax_nonce: data.viewNonce, view: view } );
				window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
			}
		} );
	} );
	// Cards stand in one column when a card is about as wide as the whole list.
	function oneColumn( card ) {
		return card.offsetWidth > cards.offsetWidth * 0.75;
	}
	// Two columns side by side: the opened card takes whole rows of the other column (closed cards are all the same
	// height), so the cards under it line up with their neighbours again.
	function snap( card, closed ) {
		var column = card.parentNode;
		if ( 'flex' !== window.getComputedStyle( column ).display ) {
			return;
		}
		var gap = parseFloat( window.getComputedStyle( column ).rowGap ) || 0;
		var rows = Math.ceil( ( card.offsetHeight + gap ) / ( closed + gap ) );
		card.style.minHeight = ( rows * ( closed + gap ) - gap ) + 'px';
	}
	function toggle( card, open ) {
		var link = card.querySelector( '.hag-toggle-body' );
		if ( open ) {
			// One card open at a time.
			Array.prototype.forEach.call( cards.querySelectorAll( '.hag-open' ), function ( other ) {
				if ( other !== card ) {
					toggle( other, false );
				}
			} );
		}
		var closed = card.offsetHeight;
		card.style.minHeight = '';
		card.classList.toggle( 'hag-open', open );
		if ( open ) {
			snap( card, closed );
		}
		if ( link ) {
			link.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			link.textContent = open ? data.hideText : data.showText;
		}
	}
	var byMouse = true;
	cards.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.hag-toggle-body' );
		if ( ! link ) {
			return;
		}
		var card = link.closest( '.hag-card' );
		var open = ! card.classList.contains( 'hag-open' );
		// A mouse click has a click count; Enter or Space on the focused link has none.
		byMouse = event.detail > 0;
		var before = card.getBoundingClientRect().top;
		toggle( card, open );
		if ( open && oneColumn( card ) ) {
			// The card that was open above has just closed: keep this one where it was, so the page does not jump
			// before it slides up.
			window.scrollBy( 0, card.getBoundingClientRect().top - before );
			// One column (phones, tablets, narrow windows): the opened card starts at the top of the screen.
			hagScrollToTop( card );
		} else {
			card.scrollIntoView( { block: 'nearest' } );
		}
	} );
	// A click anywhere outside the opened card closes it.
	document.addEventListener( 'click', function ( event ) {
		var card = cards.querySelector( '.hag-open' );
		if ( card && ! card.contains( event.target ) && ! event.target.closest( '.hag-views' ) ) {
			toggle( card, false );
		}
	} );
	document.addEventListener( 'keydown', function ( event ) {
		var card = cards.querySelector( '.hag-open' );
		if ( 'Escape' === event.key && card ) {
			var link = card.querySelector( '.hag-toggle-body' );
			toggle( card, false );
			// Opened from the keyboard: the focus goes back to its link. Opened with the mouse: no focus frame appears.
			if ( byMouse ) {
				link.blur();
			} else {
				link.focus();
			}
		}
	} );
	// A title cut with "…" shows in full in a hint; only cut titles get one, checked again on resize and view change.
	function hints() {
		Array.prototype.forEach.call( cards.querySelectorAll( '.hag-title' ), function ( title ) {
			if ( title.scrollWidth > title.clientWidth ) {
				title.setAttribute( 'title', title.textContent.trim() );
			} else {
				title.removeAttribute( 'title' );
			}
		} );
	}
	hints();
	var waiting = 0;
	window.addEventListener( 'resize', function () {
		window.clearTimeout( waiting );
		waiting = window.setTimeout( hints, 150 );
	} );
	Array.prototype.forEach.call( buttons, function ( button ) {
		button.addEventListener( 'click', hints );
	} );
	cards.addEventListener( 'click', function () {
		window.setTimeout( hints, 0 );
	} );
	// A link to a card (#hag-…-card, for example from the gap list) opens it in the short views.
	if ( window.location.hash ) {
		var target = document.getElementById( window.location.hash.slice( 1 ) );
		var card = target && target.closest( '.hag-card' );
		if ( card && cards.contains( card ) ) {
			toggle( card, true );
		}
	}
}() );

/* Tips: one group and one tip open at a time (the name attribute of <details> does that in the browser). The title
   of the opened one slides to the top of the screen. */
( function () {
	var tips = document.querySelector( '.hag-tips' );
	if ( ! tips ) {
		return;
	}
	// The click opens the block itself. The block that was open stays open while the new title slides to the top,
	// and closes after that, above the screen, with the page held in place: so there is one smooth move and no
	// jump, even when the block that closes is taller than the page was scrolled. Enter and Space send a click too.
	function room( title ) {
		// Enough height under the title for it to stand at the top of the screen at the end of the page.
		tips.style.minHeight = Math.max( 0, title.getBoundingClientRect().top - tips.getBoundingClientRect().top + window.innerHeight ) + 'px';
	}
	tips.addEventListener( 'click', function ( event ) {
		var title = event.target.closest( 'summary' );
		var box = title && title.parentNode;
		if ( ! box || ! tips.contains( box ) || box.tagName !== 'DETAILS' ) {
			return;
		}
		event.preventDefault();
		if ( box.open ) {
			// Closing: the title stays where it is.
			tips.style.minHeight = tips.offsetHeight + 'px';
			box.open = false;
			room( title );
			return;
		}
		var name = box.getAttribute( 'name' );
		var others = Array.prototype.filter.call( tips.querySelectorAll( 'details[open]' ), function ( other ) {
			return other !== box && name && other.getAttribute( 'name' ) === name;
		} );
		// Without the shared name for a moment, opening this block does not close the others yet.
		others.forEach( function ( other ) {
			other.removeAttribute( 'name' );
		} );
		box.open = true;
		room( title );
		hagScrollToTop( title, function () {
			others.forEach( function ( other ) {
				var before = title.getBoundingClientRect().top;
				other.open = false;
				other.setAttribute( 'name', name );
				window.scrollBy( 0, title.getBoundingClientRect().top - before );
			} );
			room( title );
		} );
	} );
}() );
