/* formexposed - check your forms: filters the table, shows the key while typing and asks before the key
   is removed. Runs only on the page of the extension and sends nothing anywhere. */
( function () {
	'use strict';
	var text = window.formexposedMainwp || {};
	var wrap = document.querySelector( '.fxm-wrap' );
	if ( ! wrap ) {
		return;
	}
	wrap.classList.add( 'fxm-js' );

	/* Filter by the summary tiles. */
	var tiles = wrap.querySelectorAll( '[data-fxm-filter]' );
	var rows = wrap.querySelectorAll( 'tr[data-fxm-level]' );
	var shown = wrap.querySelector( '[data-fxm-shown]' );
	var current = 'all';
	var term = '';
	var noMatch = null;
	// Marks the search term in the site name and host name of a row (text nodes only).
	function mark( row ) {
		Array.prototype.forEach.call( row.querySelectorAll( '.fxm-site strong, .fxm-site .fxm-host' ), function ( node ) {
			var plain = node.getAttribute( 'data-fxm-plain' );
			if ( null === plain ) {
				plain = node.textContent;
				node.setAttribute( 'data-fxm-plain', plain );
			}
			var at = term ? plain.toLowerCase().indexOf( term ) : -1;
			node.textContent = '';
			if ( at < 0 ) {
				node.textContent = plain;
				return;
			}
			var hit = document.createElement( 'mark' );
			hit.className = 'fxm-hit';
			hit.textContent = plain.slice( at, at + term.length );
			node.appendChild( document.createTextNode( plain.slice( 0, at ) ) );
			node.appendChild( hit );
			node.appendChild( document.createTextNode( plain.slice( at + term.length ) ) );
		} );
	}
	function filter( level ) {
		var n = 0;
		current = level;
		rows.forEach( function ( row ) {
			var words = ( ( row.getAttribute( 'data-fxm-name' ) || '' ) + ' ' + ( row.getAttribute( 'data-fxm-host' ) || '' ) ).toLowerCase();
			var show = ( 'all' === level || row.getAttribute( 'data-fxm-level' ) === level ) && ( ! term || words.indexOf( term ) >= 0 );
			row.hidden = ! show;
			n += show ? 1 : 0;
			mark( row );
		} );
		if ( noMatch ) {
			noMatch.hidden = n > 0;
		}
		tiles.forEach( function ( tile ) {
			tile.setAttribute( 'aria-pressed', tile.getAttribute( 'data-fxm-filter' ) === level ? 'true' : 'false' );
		} );
		if ( shown && text.shown ) {
			shown.textContent = text.shown.replace( '%1$d', String( n ) ).replace( '%2$d', String( rows.length ) );
		}
	}
	tiles.forEach( function ( tile ) {
		tile.addEventListener( 'click', function () {
			var level = tile.getAttribute( 'data-fxm-filter' );
			filter( 'true' === tile.getAttribute( 'aria-pressed' ) && 'all' !== level ? 'all' : level );
		} );
	} );
	if ( rows.length ) {
		filter( 'all' );
	}

	/* Show or hide the key, and say whether its form is right. */
	var input = document.getElementById( 'fxm-key' );
	var format = document.getElementById( 'fxm-key-format' );
	if ( input && input.parentNode ) {
		var toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'button fxm-key-toggle';
		toggle.setAttribute( 'aria-controls', 'fxm-key' );
		toggle.setAttribute( 'aria-pressed', 'false' );
		toggle.setAttribute( 'aria-label', text.show || '' );
		toggle.title = text.show || '';
		var eye = document.createElement( 'span' );
		eye.className = 'dashicons dashicons-visibility';
		eye.setAttribute( 'aria-hidden', 'true' );
		toggle.appendChild( eye );
		input.parentNode.appendChild( toggle );
		toggle.addEventListener( 'click', function () {
			var visible = 'password' === input.type;
			input.type = visible ? 'text' : 'password';
			toggle.setAttribute( 'aria-pressed', visible ? 'true' : 'false' );
			toggle.setAttribute( 'aria-label', visible ? text.hide : text.show );
			toggle.title = visible ? text.hide : text.show;
			eye.className = 'dashicons ' + ( visible ? 'dashicons-hidden' : 'dashicons-visibility' );
		} );
		input.addEventListener( 'input', function () {
			var value = input.value.trim();
			if ( ! format ) {
				return;
			}
			if ( '' === value ) {
				format.textContent = '';
				format.className = 'fxm-key-format';
				return;
			}
			var ok = /^fxk_[a-f0-9]{40}$/.test( value );
			format.textContent = ok ? text.formOk : text.format;
			format.className = 'fxm-key-format ' + ( ok ? 'is-good' : 'is-bad' );
		} );
	}

	/* 1.3.0: count-up of the key figures, search, sorting, CSV export, copying host names, jumps from the risk line. */
	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var numbers = window.Intl && Intl.NumberFormat ? new Intl.NumberFormat( document.documentElement.lang || undefined ) : null;
	if ( ! still && window.requestAnimationFrame ) {
		Array.prototype.forEach.call( wrap.querySelectorAll( '[data-fxm-count]' ), function ( node ) {
			var target = parseInt( node.getAttribute( 'data-fxm-count' ), 10 );
			var final = node.textContent;
			if ( ! target || target < 10 ) {
				return;
			}
			var start = null;
			function step( now ) {
				start = start || now;
				var p = Math.min( 1, ( now - start ) / 800 );
				var v = Math.round( target * ( 1 - Math.pow( 1 - p, 3 ) ) );
				node.textContent = p < 1 ? ( numbers ? numbers.format( v ) : String( v ) ) : final;
				if ( p < 1 ) {
					window.requestAnimationFrame( step );
				}
			}
			node.textContent = '0';
			window.requestAnimationFrame( step );
		} );
	}

	var toolbar = wrap.querySelector( '.fxm-toolbar' );
	var table = wrap.querySelector( '.fxm-table' );
	if ( toolbar && table && rows.length ) {
		// Search with "/" as shortcut.
		var label = document.createElement( 'label' );
		label.className = 'fxm-search';
		var icon = document.createElement( 'span' );
		icon.className = 'dashicons dashicons-search';
		icon.setAttribute( 'aria-hidden', 'true' );
		var search = document.createElement( 'input' );
		search.type = 'search';
		search.placeholder = text.search || '';
		search.setAttribute( 'aria-label', text.search || '' );
		search.setAttribute( 'aria-keyshortcuts', '/' );
		search.autocomplete = 'off';
		var kbd = document.createElement( 'kbd' );
		kbd.className = 'fxm-kbd';
		kbd.setAttribute( 'aria-hidden', 'true' );
		kbd.textContent = '/';
		label.appendChild( icon );
		label.appendChild( search );
		label.appendChild( kbd );
		toolbar.insertBefore( label, toolbar.firstChild );
		noMatch = document.createElement( 'p' );
		noMatch.className = 'fxm-nomatch';
		noMatch.textContent = text.noMatch || '';
		noMatch.hidden = true;
		table.parentNode.parentNode.insertBefore( noMatch, table.parentNode.nextSibling );
		search.addEventListener( 'input', function () {
			term = search.value.trim().toLowerCase();
			filter( current );
		} );
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && search.value ) {
				event.preventDefault();
				search.value = '';
				term = '';
				filter( current );
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			var target = event.target || {};
			if ( '/' === event.key && ! event.ctrlKey && ! event.metaKey && ! event.altKey && ! /^(INPUT|TEXTAREA|SELECT)$/.test( target.tagName || '' ) && ! target.isContentEditable && ! document.querySelector( 'dialog[open]' ) ) {
				event.preventDefault();
				search.focus();
			}
		} );

		// Sorting by a click on a column heading.
		var tbody = table.tBodies[ 0 ];
		var keys = [ 'name', 'score', 'time', 'problems', 'bots' ];
		Array.prototype.forEach.call( table.tHead.rows[ 0 ].cells, function ( th, index ) {
			var key = keys[ index ];
			if ( ! key ) {
				return;
			}
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'fxm-sort';
			button.textContent = th.textContent.trim();
			button.setAttribute( 'aria-label', ( text.sortBy || '%s' ).replace( '%s', th.textContent.trim() ) );
			var arrow = document.createElement( 'span' );
			arrow.className = 'fxm-sort-arrow';
			arrow.setAttribute( 'aria-hidden', 'true' );
			button.appendChild( arrow );
			th.textContent = '';
			th.appendChild( button );
			button.addEventListener( 'click', function () {
				var down = 'descending' !== th.getAttribute( 'aria-sort' ) && 'name' !== key ? true : 'ascending' === th.getAttribute( 'aria-sort' );
				Array.prototype.forEach.call( table.tHead.rows[ 0 ].cells, function ( other ) {
					other.removeAttribute( 'aria-sort' );
				} );
				th.setAttribute( 'aria-sort', down ? 'descending' : 'ascending' );
				var list = Array.prototype.slice.call( tbody.querySelectorAll( 'tr[data-fxm-level]' ) );
				list.sort( function ( a, b ) {
					var x = a.getAttribute( 'data-fxm-' + key ) || '';
					var y = b.getAttribute( 'data-fxm-' + key ) || '';
					if ( 'name' === key ) {
						return ( down ? -1 : 1 ) * x.localeCompare( y );
					}
					if ( '' === x || '' === y ) {
						return '' === x ? ( '' === y ? 0 : 1 ) : -1; // rows without a value stay at the end
					}
					return ( down ? -1 : 1 ) * ( parseFloat( x ) - parseFloat( y ) );
				} );
				list.forEach( function ( row ) {
					tbody.appendChild( row );
				} );
			} );
		} );

		// CSV of the rows shown, built in the browser.
		var csv = document.createElement( 'button' );
		csv.type = 'button';
		csv.className = 'button fxm-csv';
		var csvIcon = document.createElement( 'span' );
		csvIcon.className = 'dashicons dashicons-download';
		csvIcon.setAttribute( 'aria-hidden', 'true' );
		csv.appendChild( csvIcon );
		csv.appendChild( document.createTextNode( ' ' + ( text.csv || 'CSV' ) ) );
		toolbar.appendChild( csv );
		csv.addEventListener( 'click', function () {
			function cell( value ) {
				var v = String( null === value || undefined === value ? '' : value );
				if ( /^[=+\-@\t\r]/.test( v ) ) {
					v = "'" + v; // no formulas in spreadsheets
				}
				return '"' + v.replace( /"/g, '""' ) + '"';
			}
			var lines = [ ( text.csvHead || [] ).map( cell ).join( ',' ) ];
			Array.prototype.forEach.call( tbody.querySelectorAll( 'tr[data-fxm-level]' ), function ( row ) {
				if ( row.hidden ) {
					return;
				}
				var badge = row.cells[ 1 ] ? row.cells[ 1 ].querySelector( '.fxm-badge' ) : null;
				var link = row.querySelector( '.fxm-report a' );
				var time = parseInt( row.getAttribute( 'data-fxm-time' ), 10 );
				lines.push( [
					row.getAttribute( 'data-fxm-name' ),
					row.getAttribute( 'data-fxm-host' ),
					badge ? badge.textContent.trim() : '',
					row.getAttribute( 'data-fxm-score' ),
					time ? new Date( time * 1000 ).toISOString().slice( 0, 10 ) : '',
					row.getAttribute( 'data-fxm-problems' ),
					row.getAttribute( 'data-fxm-bots' ),
					link ? link.href : '',
				].map( cell ).join( ',' ) );
			} );
			var blob = new Blob( [ '\ufeff' + lines.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8' } );
			var a = document.createElement( 'a' );
			a.href = URL.createObjectURL( blob );
			a.download = 'formexposed-portfolio-' + new Date().toISOString().slice( 0, 10 ) + '.csv';
			document.body.appendChild( a );
			a.click();
			window.setTimeout( function () {
				URL.revokeObjectURL( a.href );
				a.remove();
			}, 1000 );
		} );

		// Copy a host name.
		if ( navigator.clipboard && window.isSecureContext ) {
			var live = document.createElement( 'span' );
			live.className = 'screen-reader-text';
			live.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( live );
			Array.prototype.forEach.call( tbody.querySelectorAll( '.fxm-site .fxm-host' ), function ( host ) {
				var copy = document.createElement( 'button' );
				copy.type = 'button';
				copy.className = 'fxm-copy';
				copy.title = text.copy || '';
				copy.setAttribute( 'aria-label', ( text.copy || '' ) + ': ' + host.textContent );
				var ic = document.createElement( 'span' );
				ic.className = 'dashicons dashicons-admin-page';
				ic.setAttribute( 'aria-hidden', 'true' );
				copy.appendChild( ic );
				host.parentNode.insertBefore( copy, host.nextSibling );
				copy.addEventListener( 'click', function () {
					navigator.clipboard.writeText( host.getAttribute( 'data-fxm-plain' ) || host.textContent ).then( function () {
						copy.classList.add( 'is-done' );
						ic.className = 'dashicons dashicons-yes';
						live.textContent = text.copied || '';
						window.setTimeout( function () {
							copy.classList.remove( 'is-done' );
							ic.className = 'dashicons dashicons-admin-page';
							live.textContent = '';
						}, 1600 );
					} );
				} );
			} );
		}
	}

	// A dot on the risk line: the row lights up (the link itself works without this).
	wrap.addEventListener( 'click', function ( event ) {
		var dot = event.target && event.target.closest ? event.target.closest( 'a.fxm-land-dot' ) : null;
		var row = dot ? document.getElementById( dot.getAttribute( 'href' ).slice( 1 ) ) : null;
		if ( ! row ) {
			return;
		}
		event.preventDefault();
		if ( row.hidden ) {
			if ( noMatch ) {
				var field = wrap.querySelector( '.fxm-search input' );
				field.value = '';
				term = '';
			}
			filter( 'all' );
		}
		row.scrollIntoView( { behavior: still ? 'auto' : 'smooth', block: 'center' } );
		row.classList.remove( 'fxm-flash' );
		void row.offsetWidth; // restart the animation
		row.classList.add( 'fxm-flash' );
		var first = row.querySelector( 'a, button' );
		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	} );

	/* Removing the key: own dialog instead of a browser prompt. */
	var remove = wrap.querySelector( '[data-fxm-remove]' );
	var form = wrap.querySelector( '[data-fxm-key-form]' );
	var confirmed = false;
	if ( ! remove || ! form || 'function' !== typeof HTMLDialogElement ) {
		return;
	}
	function el( tag, className, content ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== content ) {
			node.textContent = content;
		}
		return node;
	}
	remove.addEventListener( 'click', function ( event ) {
		if ( confirmed ) {
			return;
		}
		event.preventDefault();
		var dialog = el( 'dialog', 'fxm-dialog' );
		var heading = el( 'h2', '', text.title );
		var body = el( 'p', '', text.text );
		heading.id = 'fxm-dialog-title';
		body.id = 'fxm-dialog-text';
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', heading.id );
		dialog.setAttribute( 'aria-describedby', body.id );
		var actions = el( 'p', 'fxm-dialog-actions' );
		var cancel = el( 'button', 'button', text.cancel );
		var ok = el( 'button', 'button button-primary is-destructive', text.remove );
		cancel.type = 'button';
		ok.type = 'button';
		actions.appendChild( cancel );
		actions.appendChild( ok );
		dialog.appendChild( heading );
		dialog.appendChild( body );
		dialog.appendChild( actions );
		cancel.addEventListener( 'click', function () {
			dialog.close( 'cancel' );
		} );
		ok.addEventListener( 'click', function () {
			dialog.close( 'ok' );
		} );
		// Tab stays inside the dialog: from the last button back to the first and the other way.
		dialog.addEventListener( 'keydown', function ( e ) {
			if ( 'Tab' !== e.key ) {
				return;
			}
			if ( e.shiftKey && document.activeElement === cancel ) {
				e.preventDefault();
				ok.focus();
			} else if ( ! e.shiftKey && document.activeElement === ok ) {
				e.preventDefault();
				cancel.focus();
			}
		} );
		dialog.addEventListener( 'close', function () {
			var yes = 'ok' === dialog.returnValue;
			dialog.remove();
			if ( yes ) {
				confirmed = true;
				remove.click();
			} else {
				remove.focus();
			}
		} );
		document.body.appendChild( dialog );
		dialog.showModal();
		cancel.focus();
	} );
}() );
