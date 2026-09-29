/* global jQuery, wp, cnmdAdmin */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;

		$( '#cnmd_default_image_pick' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = wp.media( {
					title: cnmdAdmin.chooseTitle,
					button: { text: cnmdAdmin.chooseButton },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					$( '#cnmd_default_image' ).val( attachment.url );
				} );
			}
			frame.open();
		} );
	} );
} )( jQuery );
