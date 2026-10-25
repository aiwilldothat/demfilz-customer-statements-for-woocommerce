/* Demfilz Customer Statements — print/Save-as-PDF document behaviour.
   Auto-opens the print dialog, and binds the on-page "Print" button. */
( function () {
	'use strict';

	function doPrint() {
		window.print();
	}

	window.addEventListener( 'load', function () {
		window.setTimeout( doPrint, 350 );
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( e.target && 'dcsf-doc-print' === e.target.id ) {
			e.preventDefault();
			doPrint();
		}
	} );
}() );
