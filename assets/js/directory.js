/**
 * Chamber Nation Member Directory — progressive enhancement only.
 *
 * Members, search, filters and pagination are rendered server-side. This
 * script adds the "Read More" dialog, "Use my location", and drops empty
 * fields from search URLs.
 */
( function () {
	'use strict';

	function initDialog( root ) {
		var dialog = root.querySelector( '.cnmd-dialog' );
		if ( ! dialog || typeof dialog.showModal !== 'function' ) {
			return;
		}
		var title = dialog.querySelector( '.cnmd-dialog__title' );
		var body = dialog.querySelector( '.cnmd-dialog__body' );

		root.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.read-more-link' );
			if ( ! link ) {
				return;
			}
			e.preventDefault();
			var full = link.closest( '.member_about' ).querySelector( '.cnmd-about-full' );
			title.textContent = ( dialog.getAttribute( 'data-about-label' ) || 'About' ) + ' ' + ( link.getAttribute( 'data-name' ) || '' );
			body.textContent = full ? full.textContent : '';
			dialog.showModal();
		} );

		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.cnmd-dialog__close' ) || e.target === dialog ) {
				dialog.close();
			}
		} );
	}

	function initForms( root ) {
		var forms = root.querySelectorAll( 'form.cnmd-filter' );
		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function () {
				var city = form.querySelector( '.cnmd-geo-city' );
				var zip = form.querySelector( '.cnmd-geo-zip' );
				var hasPlace = ( city && city.value.trim() ) || ( zip && zip.value.trim() );
				Array.prototype.forEach.call( form.elements, function ( el ) {
					if ( ! el.name ) {
						return;
					}
					// Distance only matters with a city or ZIP; drop other empties.
					if ( ! el.value.trim() || ( ! hasPlace && /_dist$/.test( el.name ) ) ) {
						el.disabled = true;
					}
				} );
			} );
		} );
	}

	function reverseGeocode( lat, lng ) {
		var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=' + encodeURIComponent( lat ) + '&lon=' + encodeURIComponent( lng );
		return fetch( url, { headers: { Accept: 'application/json' } } ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'Reverse geocode failed' );
			}
			return res.json();
		} );
	}

	function geolocate( root ) {
		if ( ! ( 'geolocation' in navigator ) ) {
			return;
		}
		navigator.geolocation.getCurrentPosition(
			function ( pos ) {
				reverseGeocode( pos.coords.latitude, pos.coords.longitude )
					.then( function ( data ) {
						var a = ( data && data.address ) || {};
						var city = a.city || a.town || a.village || a.hamlet || a.municipality || '';
						Array.prototype.forEach.call( root.querySelectorAll( '.cnmd-geo-city' ), function ( el ) {
							if ( city ) {
								el.value = city;
							}
						} );
						Array.prototype.forEach.call( root.querySelectorAll( '.cnmd-geo-zip' ), function ( el ) {
							if ( a.postcode ) {
								el.value = a.postcode;
							}
						} );
					} )
					.catch( function () {} );
			},
			function () {},
			{ enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
		);
	}

	function initGeo( root ) {
		var buttons = root.querySelectorAll( '.cnmd-geo-btn' );
		if ( ! ( 'geolocation' in navigator ) ) {
			return;
		}
		Array.prototype.forEach.call( buttons, function ( btn ) {
			btn.hidden = false;
			btn.addEventListener( 'click', function () {
				geolocate( root );
			} );
		} );

		var empty = ! Array.prototype.some.call( root.querySelectorAll( '.cnmd-geo-city, .cnmd-geo-zip' ), function ( el ) {
			return el.value.trim() !== '';
		} );
		if ( 'yes' === root.getAttribute( 'data-geolocate' ) && empty && root.querySelector( '.cnmd-geo-city' ) ) {
			geolocate( root );
		}
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.cnmd-root' ), function ( root ) {
			initDialog( root );
			initForms( root );
			initGeo( root );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
