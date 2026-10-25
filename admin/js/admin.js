/* Demfilz Customer Statements — admin scripts */
( function ( $ ) {
	'use strict';

	$( function () {

		// The customer search box (.wc-customer-search) is auto-initialised by
		// WooCommerce's enhanced-select script from its data-* attributes.

		// Show/hide period field rows based on the selected period.
		function syncPeriod() {
			var period = $( 'input[name="period"]:checked' ).val() || 'fy';
			$( '.dcsf-period-row' ).each( function () {
				var show = ( '' + $( this ).data( 'show' ) ).split( ' ' );
				$( this ).toggleClass( 'dcsf-hidden', show.indexOf( period ) === -1 );
			} );
		}
		$( document ).on( 'change', 'input[name="period"]', syncPeriod );
		syncPeriod();

		// Logo media picker.
		var frame;
		$( '.dcsf-logo-pick' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = wp.media( {
				title: 'Select logo',
				button: { text: 'Use this image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				$( '.dcsf-logo-url' ).val( att.url );
				$( '.dcsf-logo-preview' ).html( '<img src="' + att.url + '" alt="">' );
			} );
			frame.open();
		} );

		$( '.dcsf-logo-clear' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '.dcsf-logo-url' ).val( '' );
			$( '.dcsf-logo-preview' ).empty();
		} );
	} );

}( jQuery ) );
