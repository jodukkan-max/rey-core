<?php
namespace ReyCore\Modules\DynamicTags\Tags\Woo;

use \ReyCore\Modules\DynamicTags\Base as TagDynamic;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Brand extends \ReyCore\Modules\DynamicTags\Tags\Tag {

	public static function __config() {
		return [
			'id'         => 'product-brand',
			'title'      => esc_html__( 'Product Brand', 'rey-core' ),
			'categories' => [ 'url' ],
			'group'      => TagDynamic::GROUPS_WOO,
		];
	}

	protected function register_controls() {

		$this->add_control(
			'brand_id',
			[
				'label' => esc_html__('Select Brand', 'rey-core'),
				'default' => '',
				'label_block' => true,
				'type' => 'rey-query',
				'query_args' => [
					'type'     => 'terms',
					'taxonomy' => $this->get_brand_taxonomy(),
				],
			]
		);

	}

	public function render() {

		$settings = $this->get_settings();

		if( empty( $settings['brand_id'] ) ){
			return TagDynamic::display_placeholder_data('#');
		}

		$link = get_term_link( (int) $settings['brand_id'], $this->get_brand_taxonomy() );

		if( is_wp_error( $link ) ){
			return;
		}

		echo esc_url( $link );
	}

	/**
	 * Resolve the brand taxonomy the same way Rey's Brands module does.
	 */
	private function get_brand_taxonomy() {

		if( class_exists( '\ReyCore\Modules\Brands\Base' ) ) {
			$base = \ReyCore\Modules\Brands\Base::instance();
			if( $base && method_exists( $base, 'get_brand_attribute' ) ) {
				$taxonomy = $base->get_brand_attribute();
				if( $taxonomy && taxonomy_exists( $taxonomy ) ) {
					return $taxonomy;
				}
			}
		}

		// WooCommerce Brands plugin compatibility.
		if( taxonomy_exists( 'product_brand' ) ) {
			return 'product_brand';
		}

		return 'pa_brand';
	}

}
