<?php
namespace ReyCore\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Register editor assets at file scope so they load regardless of Rey's
// `! is_admin()` guard inside `set_widgets()`.
add_action( 'elementor/editor/before_enqueue_scripts', function () {
	$dir = REY_CORE_DIR . 'inc/elementor/widgets/nested-carousel/';
	$uri = REY_CORE_URI . 'inc/elementor/widgets/nested-carousel/';

	wp_enqueue_style(
		'reycore-widget-nested-carousel-style',
		$uri . 'assets/style.css',
		[],
		filemtime( $dir . 'assets/style.css' )
	);

	wp_enqueue_script(
		'reycore-widget-nested-carousel-editor',
		$uri . 'assets/editor.js',
		[ 'jquery' ],
		filemtime( $dir . 'assets/editor.js' ),
		true
	);
} );

// Enqueue the widget CSS + frontend handler globally via `wp_enqueue_scripts`
// so they load BOTH on the frontend AND inside the Elementor editor preview
// iframe (which is a frontend request; `render()` and the editor-chrome hooks
// do not run there). Splide itself is lazy-loaded by Rey through
// `reycore_assets()->add_scripts()` inside `render()`, which keeps the engine
// ordering correct (c-slider.js must load after the main `rey` bundle).
add_action( 'wp_enqueue_scripts', function () {
	$dir = REY_CORE_DIR . 'inc/elementor/widgets/nested-carousel/';
	$uri = REY_CORE_URI . 'inc/elementor/widgets/nested-carousel/';

	wp_enqueue_style(
		'reycore-widget-nested-carousel-style',
		$uri . 'assets/style.css',
		[],
		filemtime( $dir . 'assets/style.css' )
	);

	wp_enqueue_script(
		'reycore-widget-nested-carousel-scripts',
		$uri . 'assets/script.js',
		[ 'elementor-frontend', 'rey-script' ],
		filemtime( $dir . 'assets/script.js' ),
		true
	);
}, 20 );

// The nested-elements infrastructure ships with Elementor Free, but is only
// available when the experiment is active. Bail instead of fataling.
if ( ! class_exists( 'Elementor\Modules\NestedElements\Base\Widget_Nested_Base' ) ) {
	return;
}

class NestedCarousel extends Widget_Nested_Base {

	public $_settings = [];

	public $_items = [];

	public $slider_components;

	public static function get_rey_config() {
		return [
			'id'         => 'nested-carousel',
			'title'      => __( 'Nested Carousel', 'rey-core' ),
			'icon'       => 'eicon-carousel',
			'categories' => [ 'rey-theme' ],
			'keywords'   => [ 'carousel', 'slides', 'nested', 'media', 'gallery', 'image' ],
		];
	}

	/**
	 * Splide engine + Rey's slider wrapper, lazy-loaded the same way the
	 * `carousel` widget does. The widget's own script is enqueued globally
	 * (see the `wp_enqueue_scripts` hook above), not through Rey's registry.
	 */
	public function rey_get_script_depends() {
		return [ 'elementor-frontend', 'reycore-elementor-frontend', 'splidejs', 'rey-splide' ];
	}

	public function get_name() {
		return \ReyCore\Elementor\Widgets::PREFIX . 'nested-carousel';
	}

	public function get_title() {
		return __( 'Nested Carousel', 'rey-core' );
	}

	public function get_icon() {
		return 'eicon-carousel';
	}

	public function get_categories() {
		return [ 'basic' ];
	}

	public function get_keywords() {
		return [ 'carousel', 'slides', 'nested', 'media', 'gallery', 'image' ];
	}

	public function show_in_panel() {
		return (bool) reycore__get_purchase_code();
	}

	public function get_html_wrapper_class() {
		return 'elementor-widget-reycore-nested-carousel';
	}

	protected function get_default_children_elements() {
		return [
			[ 'elType' => 'container', 'settings' => [ '_title' => __( 'Slide #1', 'rey-core' ) ] ],
			[ 'elType' => 'container', 'settings' => [ '_title' => __( 'Slide #2', 'rey-core' ) ] ],
			[ 'elType' => 'container', 'settings' => [ '_title' => __( 'Slide #3', 'rey-core' ) ] ],
		];
	}

	protected function get_default_repeater_title_setting_key() {
		return 'slide_title';
	}

	protected function get_default_children_title() {
		/* translators: %d: Slide number. */
		return __( 'Slide #%d', 'rey-core' );
	}

	protected function get_default_children_placeholder_selector() {
		return '.splide__list';
	}

	protected function get_default_children_container_placeholder_selector() {
		return '.splide__slide';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_slides',
			[ 'label' => __( 'Slides', 'rey-core' ) ]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'slide_title',
			[
				'label'       => __( 'Title', 'rey-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Slide Title', 'rey-core' ),
				'placeholder' => __( 'Slide Title', 'rey-core' ),
				'dynamic'     => [ 'active' => true ],
				'label_block' => true,
			]
		);

		$this->add_control(
			'carousel_items',
			[
				'label'              => __( 'Carousel Items', 'rey-core' ),
				'type'               => Control_Nested_Repeater::CONTROL_TYPE,
				'fields'             => $repeater->get_controls(),
				'default'            => [
					[ 'slide_title' => __( 'Slide #1', 'rey-core' ) ],
					[ 'slide_title' => __( 'Slide #2', 'rey-core' ) ],
					[ 'slide_title' => __( 'Slide #3', 'rey-core' ) ],
				],
				'frontend_available' => true,
				'title_field'        => '{{{ slide_title }}}',
			]
		);

		$this->end_controls_section();

		// ---- Carousel settings (mirrors `carousel.php`) ------------------------

		$this->start_controls_section(
			'section_carousel_settings',
			[ 'label' => __( 'Carousel Settings', 'rey-core' ) ]
		);

		$this->add_control(
			'direction',
			[
				'label'   => __( 'Direction', 'rey-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'ltr',
				'options' => [
					'ltr' => __( 'Horizontal', 'rey-core' ),
					'rtl' => __( 'Horizontal Reverse', 'rey-core' ),
				],
			]
		);

		$items_to_show = range( 1, 10 );
		$items_to_show = array_combine( $items_to_show, $items_to_show );

		$this->add_responsive_control(
			'items_to_show',
			[
				'label'     => __( 'Items to Show', 'rey-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					'' => __( 'Default', 'rey-core' ),
				] + $items_to_show,
				'selectors' => [
					'{{WRAPPER}} .rey-nested-carousel' => '--per-row: {{VALUE}}',
				],
				'default'   => '1',
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label'     => __( 'Gap (px)', 'rey-core' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => '',
				'min'       => 0,
				'max'       => 200,
				'step'      => 1,
				'selectors' => [
					'{{WRAPPER}} .rey-nested-carousel' => '--gap: {{VALUE}}px;',
				],
			]
		);

		$this->add_control(
			'infinite',
			[
				'label'   => __( 'Infinite Loop', 'rey-core' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label'        => __( 'Autoplay', 'rey-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->add_control(
			'autoplay_duration',
			[
				'label'     => __( 'Autoplay Duration (ms)', 'rey-core' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 9000,
				'min'       => 3500,
				'max'       => 20000,
				'step'      => 50,
				'condition' => [
					'autoplay' => 'yes',
				],
			]
		);

		$this->add_control(
			'autoplay_pause_hover',
			[
				'label'     => __( 'Pause on hover', 'rey-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => [
					'autoplay!' => '',
				],
			]
		);

		$this->end_controls_section();

		// ---- Carousel Navigation (Arrows + Dots via Slider_Components) --------

		$this->start_controls_section(
			'section_carousel_navigation',
			[ 'label' => __( 'Carousel Navigation', 'rey-core' ) ]
		);

		\ReyCore\Libs\Slider_Components::controls_nav( $this );

		$this->end_controls_section();

		// Arrows / Dots style tabs.
		\ReyCore\Libs\Slider_Components::controls( $this );
	}

	protected function render() {

		reycore_assets()->add_styles( [ 'rey-splide' ] );
		reycore_assets()->add_scripts( $this->rey_get_script_depends() );

		$this->_settings = $this->get_settings_for_display();

		if ( empty( $this->_settings['carousel_items'] ) ) {
			return;
		}

		$this->_items = $this->_settings['carousel_items'];

		$this->slider_components = new \ReyCore\Libs\Slider_Components( $this );

		$settings  = $this->_settings;
		$direction = ( 'rtl' === ( $settings['direction'] ?? 'ltr' ) ) ? 'rtl' : 'ltr';

		$attributes['class'] = [
			'rey-nested-carousel',
			$this->slider_components::$selectors['wrapper'],
		];

		if ( ( $settings['autoplay'] ?? '' ) !== '' && ( $settings['autoplay_pause_hover'] ?? '' ) !== '' ) {
			$attributes['class'][] = $this->slider_components::$selectors['pause_hover'];
		}

		if ( count( $this->_items ) > 1 ) {

			$carousel_config = [
				'type'          => 'slide',
				'direction'     => $direction,
				'items_to_show' => ! empty( $settings['items_to_show'] ) ? $settings['items_to_show'] : 1,
				'infinite'      => ( $settings['infinite'] ?? 'yes' ) !== '',
				'autoplay'      => ( $settings['autoplay'] ?? '' ) !== '',
				'pauseOnHover'  => ( $settings['autoplay'] ?? '' ) !== '' && ( $settings['autoplay_pause_hover'] ?? '' ) !== '',
				'interval'      => ! empty( $settings['autoplay_duration'] ) ? absint( $settings['autoplay_duration'] ) : 9000,
				'customArrows'  => $this->slider_components::$selectors['arrows'],
				'pagination'    => ( $settings['dots'] ?? '' ) !== '',
				'speed'         => 700,
				'bp_devices'    => [
					'perPage' => 'items_to_show',
				],
			];

			foreach ( \ReyCore\Elementor\Helper::get_breakpoints() as $device ) {
				$key = 'items_to_show' . $device;
				if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
					$carousel_config[ $key ] = $settings[ $key ];
				}
			}

			$attributes['data-carousel-settings'] = wp_json_encode( $carousel_config );
		}

		$this->add_render_attribute( 'wrapper', $attributes );
		?>
		<div <?php $this->print_render_attribute_string( 'wrapper' ); ?>>
			<div class="splide">
				<div class="splide__track">
					<div class="splide__list">
						<?php foreach ( $this->_items as $index => $slide ) : ?>
							<div class="splide__slide" data-slide="<?php echo absint( $index + 1 ); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( '%1$d / %2$d', $index + 1, count( $this->_items ) ) ); ?>">
								<?php $this->print_child( $index ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php $this->slider_components->render_dots_container(); ?>
			</div>
			<?php $this->slider_components->render(); ?>
		</div>
		<?php
	}

	protected function get_initial_config(): array {
		return array_merge( parent::get_initial_config(), [
			'support_improved_repeaters' => true,
			'target_container'           => [ '.rey-nested-carousel > .splide > .splide__track > .splide__list' ],
			'node'                       => 'div',
			'is_interlaced'              => true,
		] );
	}

	protected function content_template_single_repeater_item() {
		?>
		<#
		const elementUid = view.getIDInt().toString().substr( 0, 3 ),
			numOfSlides = view.collection.length + 1;
		const slideCount = numOfSlides;
		const slideWrapperKeyItem = {
			'class': 'splide__slide',
			'data-slide': slideCount,
			'role': 'group',
			'aria-roledescription': 'slide',
			'aria-label': slideCount + ' <?php echo esc_attr__( 'of', 'rey-core' ); ?> ' + numOfSlides,
		};
		view.addRenderAttribute( 'single-slide', slideWrapperKeyItem, null, true );
		#>
		<div {{{ view.getRenderAttributeString( 'single-slide' ) }}}></div>
		<?php
	}

	protected function content_template() {
		?>
		<# if ( settings['carousel_items'] ) {
			const elementUid = view.getIDInt().toString().substr( 0, 3 ),
				carouselKey = 'carousel-' + elementUid,
				listKey = 'list-' + elementUid;

			view.addRenderAttribute( carouselKey, {
				'class': 'rey-nested-carousel',
				'role': 'region',
				'aria-roledescription': 'carousel',
			} );

			view.addRenderAttribute( listKey, { 'class': 'splide__list' } );

			if ( !! settings['direction'] ) {
				view.addRenderAttribute( carouselKey, 'dir', settings['direction'] );
			}
		#>
			<div {{{ view.getRenderAttributeString( carouselKey ) }}}>
				<div class="splide">
					<div class="splide__track">
						<div {{{ view.getRenderAttributeString( listKey ) }}}>
							<# _.each( settings['carousel_items'], function( slide, index ) {
								const slideCount = index + 1,
									slideKey = elementUid + slideCount;
								view.addRenderAttribute( slideKey, {
									'class': 'splide__slide',
									'data-slide': slideCount,
									'role': 'group',
									'aria-roledescription': 'slide',
									'aria-label': slideCount + ' <?php echo esc_attr__( 'of', 'rey-core' ); ?> ' + settings['carousel_items'].length,
								} );
							#>
								<div {{{ view.getRenderAttributeString( slideKey ) }}}></div>
							<# } ); #>
						</div>
					</div>
				</div>
			</div>
		<# } #>
		<?php
	}
}
