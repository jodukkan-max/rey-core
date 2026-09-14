<?php
namespace ReyCore\Modules\DynamicTags\Tags\Woo;

use \ReyCore\Modules\DynamicTags\Base as TagDynamic;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class TagUrl extends \ReyCore\Modules\DynamicTags\Tags\Tag {

	public static function __config() {
		return [
			'id'         => 'product-tag-url',
			'title'      => esc_html__( 'Product Tag', 'rey-core' ),
			'categories' => [ 'url' ],
			'group'      => TagDynamic::GROUPS_WOO,
		];
	}

	protected function register_controls() {

		$this->add_control(
			'tag_id',
			[
				'label' => esc_html__('Select Tag', 'rey-core'),
				'default' => '',
				'label_block' => true,
				'type' => 'rey-query',
				'query_args' => [
					'type'     => 'terms',
					'taxonomy' => 'product_tag',
				],
			]
		);

	}

	public function render() {

		$settings = $this->get_settings();

		if( empty( $settings['tag_id'] ) ){
			return TagDynamic::display_placeholder_data('#');
		}

		$link = get_term_link( (int) $settings['tag_id'], 'product_tag' );

		if( is_wp_error( $link ) ){
			return;
		}

		echo esc_url( $link );
	}

}
