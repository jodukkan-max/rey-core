/**
 * Nested Carousel — frontend + editor handler.
 *
 * Sliding uses Rey's Splide wrapper (`rey.components.slider` + `rey.frontend.inView`),
 * exactly like the `carousel` widget. The Splide config is read from the
 * `data-carousel-settings` attribute emitted by PHP.
 *
 * Editor: `wrapSlideContent()` re-parents Elementor's injected `.e-con`
 * containers into their `.splide__slide` placeholders. Slide layout in the
 * editor is handled purely by CSS (see style.css), so Splide is NOT initialized
 * in edit mode — matching how Rey's own carousels work.
 *
 * Load order: this script is enqueued with a `rey-script` dependency (see
 * nested-carousel.php), so `window.rey` is defined before it runs. Splide is
 * triggered the same way the `carousel` widget does — via Rey's
 * `elementor/init` element registry and the `rey/preloader/loaded` event —
 * rather than relying on `element_ready`, which can fire before Rey's bundle.
 */
( function( $ ) {
	'use strict';

	var TYPE = 'reycore-nested-carousel';

	// ---- Shared frontend init -----------------------------------------------

	// Mirror the `carousel` widget's Splide config builder. `bp_devices` maps
	// Splide option keys (e.g. `perPage`) to setting keys (e.g. `items_to_show`);
	// responsive overrides live in `items_to_show_tablet`, `items_to_show_mobile`.
	function buildSplideConfig( a ) {
		var d = {
			type: a.infinite ? 'loop' : 'slide',
			autoplay: a.autoplay,
			interval: parseInt( a.interval ),
			speed: parseInt( a.speed ),
			pauseOnHover: a.pauseOnHover,
			rewind: true,
			perPage: parseInt( a.items_to_show ) || 1,
			arrows: false,
			pagination: a.pagination,
			autoWidth: true,
			direction: a.direction,
			breakpoints: {}
		};

		if ( 'undefined' !== typeof elementorFrontendConfig && a.bp_devices ) {
			Object.keys( a.bp_devices ).forEach( function( option ) {
				var key = a.bp_devices[ option ];

				Object.keys( elementorFrontendConfig.responsive.breakpoints ).forEach( function( bpName ) {
					var bp = elementorFrontendConfig.responsive.breakpoints[ bpName ],
						valueKey = key + '_' + bpName;

					if ( bp.is_enabled && void 0 !== a[ valueKey ] && a[ valueKey ] ) {
						d.breakpoints[ bp.value ] = d.breakpoints[ bp.value ] || {};
						d.breakpoints[ bp.value ][ option ] = parseInt( a[ valueKey ] );
					}
				} );
			} );
		}

		return d;
	}

	// Initialize Splide on a single `.rey-nested-carousel` wrapper. Safe to call
	// multiple times (deduped via `rey.util.alreadyLoaded`, like the carousel).
	function initSplideOnElement( wrapperEl ) {
		if ( ! wrapperEl || 1 !== wrapperEl.nodeType ) {
			return;
		}

		// Editor preview: CSS-only layout handles slides; don't init Splide.
		if ( window.elementorFrontend && elementorFrontend.isEditMode && elementorFrontend.isEditMode() ) {
			return;
		}

		if ( ! window.rey || ! rey.util || ! rey.util.alreadyLoaded || ! rey.frontend || ! rey.frontend.inView ) {
			return;
		}

		if ( rey.util.alreadyLoaded( wrapperEl ) ) {
			return;
		}

		var splide = wrapperEl.querySelector( '.splide' );

		if ( ! splide ) {
			return;
		}

		// Nothing to slide with a single slide.
		if ( 2 > splide.querySelectorAll( '.splide__slide' ).length ) {
			return;
		}

		var config = {};
		try {
			config = JSON.parse( wrapperEl.getAttribute( 'data-carousel-settings' ) || '{}' );
		} catch ( error ) {
			config = {};
		}

		if ( ! Object.keys( config ).length ) {
			return;
		}

		var options = buildSplideConfig( config );

		rey.frontend.inView( {
			target: splide,
			cb: function( entry ) {
				rey.components.slider( {
					element: entry.target,
					config: options,
					customArrows: config.customArrows,
					mount: true
				} );
			},
			once: true
		} );
	}

	// ---- Editor handler (re-parents injected containers) ----------------------

	if (
		window.elementorModules &&
		window.elementorModules.frontend &&
		window.elementorModules.frontend.handlers &&
		window.elementorModules.frontend.handlers.Base
	) {
		var Base = elementorModules.frontend.handlers.Base;

		var NestedCarousel = Base.extend( {

			getDefaultSettings: function() {
				return {
					selectors: {
						carousel: '.rey-nested-carousel',
						slidesWrapper: '.rey-nested-carousel > .splide__list',
						slideContent: '.splide__slide'
					}
				};
			},

			getDefaultElements: function() {
				var selectors = this.getSettings( 'selectors' ),
					elements = {
						$carousel: this.$element.find( selectors.carousel ),
						$slidesWrapper: this.$element.find( selectors.slidesWrapper )
					};

				elements.$slides = elements.$carousel.find( selectors.slideContent );
				return elements;
			},

			isEditMode: function() {
				return !!( window.elementorFrontend && elementorFrontend.isEditMode && elementorFrontend.isEditMode() );
			},

			onInit: function() {
				if ( this.isEditMode() ) {
					// Editor: just re-parent the injected containers; CSS does the layout.
					this.wrapSlideContent();
					return;
				}
				initSplideOnElement( this.elements.$carousel[ 0 ] );
			},

			bindEvents: function() {
				if ( this.isEditMode() ) {
					// Re-sync the DOM when a slide is added / duplicated / moved.
					elementorFrontend.elements.$window.on(
						'elementor/nested-container/atomic-repeater',
						this.onAtomicRepeater.bind( this )
					);
				}
			},

			onAtomicRepeater: function() {
				var self = this;
				// Wait for the nested system to finish mutating the DOM.
				setTimeout( function() {
					self.wrapSlideContent();
				}, 0 );
			},

			// In the editor, Elementor injects each child `.e-con` as a direct child
			// of `.splide__list`, beside the `.splide__slide` placeholders. Move each
			// one into its matching slide.
			wrapSlideContent: function() {
				var settings = this.getSettings(),
					slideContentClass = settings.selectors.slideContent.replace( '.', '' ),
					widget = this.$element,
					index = 1;

				this.findElement( settings.selectors.slidesWrapper + ' > .e-con' ).each( function() {
					var $container = $( this ),
						hasWrapper = $container.closest( 'div' ).hasClass( slideContentClass ),
						$slide = widget.find( settings.selectors.slidesWrapper + ' > .' + slideContentClass + ':nth-child(' + index + ')' );

					if ( ! hasWrapper ) {
						$slide.append( $container );
					}
					index++;
				} );

				// Refresh cached slide references after re-parenting.
				this.elements.$slides = this.$element.find( settings.selectors.slidesWrapper + ' > ' + settings.selectors.slideContent );
			}
		} );

		var addHandler = function( $element ) {
			if ( ! window.elementorFrontend || ! elementorFrontend.elementsHandler ) {
				return;
			}
			if ( $element.data( 'reyNcInit' ) ) {
				return;
			}
			$element.data( 'reyNcInit', true );
			elementorFrontend.elementsHandler.addHandler( NestedCarousel, {
				$element: $element
			} );
		};

		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			elementorFrontend.hooks.addAction( 'frontend/element_ready/' + TYPE, addHandler );
			elementorFrontend.hooks.addAction( 'frontend/element_ready/' + TYPE + '.default', addHandler );
		} else {
			$( window ).on( 'elementor/frontend/init', function() {
				if ( elementorFrontend && elementorFrontend.hooks ) {
					elementorFrontend.hooks.addAction( 'frontend/element_ready/' + TYPE, addHandler );
					elementorFrontend.hooks.addAction( 'frontend/element_ready/' + TYPE + '.default', addHandler );
				}
			} );
		}

		// Late-init fallback: if this footer script loads after `element_ready`
		// has already fired, re-attach the handler. Works in both the frontend
		// and the editor preview.
		$( document ).ready( function() {
			$( '.elementor-widget-reycore-nested-carousel' ).each( function() {
				addHandler( $( this ) );
			} );
		} );
	}

	// ---- Frontend triggers (mirror carousel/assets/script.js) ---------------

	// Rey's element registry fires for each widget of this type on the frontend.
	if ( window.rey && rey.hooks ) {
		rey.hooks.addAction( 'elementor/init', function( register ) {
			register.registerElement( {
				name: TYPE + '.default',
				cb: function( $element ) {
					initSplideOnElement( $element[ 0 ] );
				}
			} );
		} );
	}

	// Fallback for non-Elementor render contexts / late DOM.
	document.addEventListener( 'rey/preloader/loaded', function() {
		document.querySelectorAll( '.elementor-widget-reycore-nested-carousel .rey-nested-carousel' ).forEach( initSplideOnElement );
	} );

	$( document ).ready( function() {
		$( '.elementor-widget-reycore-nested-carousel .rey-nested-carousel' ).each( function() {
			initSplideOnElement( this );
		} );
	} );
}( jQuery ) );
