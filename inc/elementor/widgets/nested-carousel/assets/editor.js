/**
 * Rey Core — Nested Carousel — Elementor editor integration.
 *
 * Registers the widget as a nested element type so Elementor renders the
 * free-form containers inside each slide in the editor.
 *
 * This follows Elementor's internal pattern for third-party nested widgets:
 *   - `getType()` MUST exactly match the widget's `get_name()` from PHP
 *     (`reycore-nested-carousel`).
 *   - Registration happens once the async `NestedElementBase` type has loaded,
 *     signalled by the `elementor/nested-element-type-loaded` event.
 */
( function( $ ) {
	'use strict';

	console.log( '[Rey Core][Nested Carousel] editor.js loaded.', {
		has_elementor: !! window.elementor,
		has_elementsManager: !!( window.elementor && window.elementor.elementsManager ),
		has_widgetsCache: !!( window.elementor && window.elementor.widgetsCache ),
	} );

	var registered = false;
	var attempts = 0;
	var MAX_ATTEMPTS = 100;

	function getNestedElementBase() {
		var elementsModule = window.elementor && window.elementor.modules && window.elementor.modules.elements;

		if ( ! elementsModule || ! elementsModule.types ) {
			return null;
		}

		return elementsModule.types.NestedElementBase || null;
	}

	function listElementTypes() {
		var elementsModule = window.elementor && window.elementor.modules && window.elementor.modules.elements;

		if ( ! elementsModule || ! elementsModule.types ) {
			return null;
		}

		return Object.keys( elementsModule.types );
	}

	function register() {
		if ( registered ) {
			return;
		}

		var NestedElementBase = getNestedElementBase();

		if ( ! NestedElementBase ) {
			return; // Not loaded yet — the retry loop / event will call again.
		}

		// NestedElementBase can briefly be a Promise while its chunk loads.
		if ( 'function' !== typeof NestedElementBase ) {
			if ( 'function' === typeof NestedElementBase.then ) {
				NestedElementBase.then( register );
			}
			return;
		}

		var NestedCarousel = class extends NestedElementBase {
			getType() {
				return 'reycore-nested-carousel';
			}
		};

		try {
			window.elementor.elementsManager.registerElementType( new NestedCarousel() );
			registered = true;
			console.log( '[Rey Core][Nested Carousel] Element type "reycore-nested-carousel" registered.' );
		} catch ( error ) {
			console.error( '[Rey Core][Nested Carousel] registerElementType() threw:', error );
		}
	}

	function bindEvents() {
		var $window = ( window.elementorCommon && window.elementorCommon.elements )
			? window.elementorCommon.elements.$window
			: $( window );

		$window.on( 'elementor/nested-element-type-loaded', register );
		$window.on( 'elementor:init-components', register );
		$window.on( 'elementor:init', register );
	}

	bindEvents();
	register(); // In case it is already loaded.

	// Bounded fallback for slow / unusual load orders.
	var timer = setInterval( function() {
		attempts += 1;
		register();

		if ( registered || attempts >= MAX_ATTEMPTS ) {
			clearInterval( timer );

			if ( ! registered ) {
				console.error(
					'[Rey Core][Nested Carousel] Failed to register after ' + MAX_ATTEMPTS + ' attempts.',
					'NestedElementBase:', getNestedElementBase(),
					'available element types:', listElementTypes()
				);
			}
		}
	}, 200 );
}( jQuery ) );
