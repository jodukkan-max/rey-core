<?php
namespace ReyCore\Elementor;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use \ReyCore\Elementor\Helper;

class Base
{

	public static $props = [];

	public $frontend;
	public $cover;
	public $widgets;
	public $widgets_manager;

	public function __construct()
	{

		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		new Editor();
		new WidgetsOverrides();

		add_action( 'init', [ $this, 'init' ], 0 );
		add_action( 'rey/flush_cache_after_updates', [ $this, 'flush_cache' ] );
		add_action( 'reycore/assets/cleanup', [ $this, 'flush_cache' ] );
	}

	public function init(){

		$this->widgets = new Widgets();
		$this->widgets_manager = new WidgetsManager;

		new WidgetsAssets();
		new Assets();
		$this->frontend = new Frontend();
		new GlobalSections();
		$this->cover = TagCover::instance();

		do_action('reycore/elementor');

	}

	/**
	 * Flush Elementor cache
	 * - after updates
	 */
	public function flush_cache(){
		// Dukkan patch (2026-11-03): do NOT call Elementor's
		// files_manager->clear_cache() here. It deletes ALL compiled
		// post-*.css files, and nginx's FastCGI cache keeps serving cached
		// pages that still reference the deleted CSS (the "broken design"
		// bug). Elementor regenerates its own CSS on document saves and on
		// Elementor version bumps, so a global wipe is unnecessary.
	}

}
