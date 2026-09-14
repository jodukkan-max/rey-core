<?php
namespace ReyCore\Modules\DynamicTags\Tags\Woo;

use \ReyCore\Modules\DynamicTags\Base as TagDynamic;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class CategoryUrl extends \ReyCore\Modules\DynamicTags\Tags\Tag {

	public static function __config() {
		return [
			'id'         => 'product-category-url',
			'title'      => esc_html__( 'Product Category', 'rey-core' ),
			'categories' => [ 'url' ],
			'group'      => TagDynamic::GROUPS_WOO,
		];
	}

	protected function register_controls() {

		$this->add_control(
			'category_id',
			[
				'label' => esc_html__('Select Category', 'rey-core'),
				'default' => '',
				'label_block' => true,
				'type' => 'rey-query',
				'query_args' => [
					'type'     => 'terms',
					'taxonomy' => 'product_cat',
				],
			]
		);

	}

	public function render() {

		$settings = $this->get_settings();

		if( empty( $settings['category_id'] ) ){
			return TagDynamic::display_placeholder_data('#');
		}

		$link = get_term_link( (int) $settings['category_id'], 'product_cat' );

		if( is_wp_error( $link ) ){
			return;
		}

		echo esc_url( $link );
	}

}
