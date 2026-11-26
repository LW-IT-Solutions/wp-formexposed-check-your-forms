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
	function filter( level ) {
		var n = 0;
		rows.forEach( function ( row ) {
			var show = 'all' === level || row.getAttribute( 'data-fxm-level' ) === level;
			row.hidden = ! show;
			n += show ? 1 : 0;
		} );
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
