<?php
namespace ReyCore\Modules\DynamicTags\Tags\Post;

use \ReyCore\Modules\DynamicTags\Base as TagDynamic;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class PageUrl extends \ReyCore\Modules\DynamicTags\Tags\Tag {

	public static function __config() {
		return [
			'id'         => 'page-url',
			'title'      => esc_html__( 'Page URL', 'rey-core' ),
			'categories' => [ 'url' ],
			'group'      => TagDynamic::GROUPS_POST,
		];
	}

	protected function register_controls() {
		TagDynamic::post_control($this);
	}

	public function render() {

		if( ! ($post = TagDynamic::get_selected_post($this)) ){
			return TagDynamic::display_placeholder_data('#');
		}

		echo esc_url( get_permalink( $post->ID ) );
	}

}
