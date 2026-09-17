<?php
namespace ReyCore;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

	class Admin {

		const POPOVER_KEY = 'rey_page_settings_popover';

		public function __construct(){

			add_action('init', [$this, 'init']);

			new \ReyCore\Libs\IgTokenManager;
		}

		public function init(){
			add_action( 'admin_init', [$this, 'admin_init']);
			add_action( 'wp_head', [$this, 'admin_bar_css'] );
			add_action( 'admin_menu', [$this, 'add_customizer_shortcut'], 100 );
			add_filter( 'admin_body_class', [$this, 'add_body_class'], 30 );
			add_action( 'admin_enqueue_scripts', [$this, 'register_admin_scripts'], 5);
			add_action( 'admin_head', [$this, 'admin_bar_css'] );
			add_action( 'admin_menu', [$this, 'admin_menu'] );
			add_action( 'reycore/ajax/register_actions', [ $this, 'register_actions' ] );
			add_action( 'admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
			add_filter( 'rey/api/remote_get/args', [$this, 'db_version']);
			add_filter( 'rey/api/remote_post/args', [$this, 'db_version']);
			add_filter( 'rey/api/file_remote_get/args', [$this, 'db_version']);
			add_action( 'wp_enqueue_scripts', [$this, 'enqueue_scripts']);
			add_action( 'wp_footer', [$this, 'add_page_settings_popover'], 9999);
			add_action( 'admin_footer', [$this, 'admin_footer']);
			add_action( 'customize_controls_print_scripts', [$this, 'include_customizer_icons_sprite']);
			add_action( 'reycore/global_section_template/after_the_content', [$this, 'add_gs_registration_notice']);
			add_action( 'reycore/custom_template/after_the_content', [$this, 'add_gs_registration_notice']);
		}

		public function admin_init(){

			if( ! class_exists('\ACF') ){
				return false;
			}

			$this->keep_dashboard_menu_open();

			if( reycore__acf_get_field('rey_widgets_blocks_layout', REY_CORE_THEME_NAME) === true ){
				return;
			}

			// Disables the block editor from managing widgets in the Gutenberg plugin.
			add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
			// Disables the block editor from managing widgets.
			add_filter( 'use_widgets_block_editor', '__return_false' );

		}

		public function keep_dashboard_menu_open()
		{

			if( ! (isset($_REQUEST['action']) && 'edit' === $_REQUEST['action']) ){
				return;
			}

			if( ! (isset($_REQUEST['post']) && ($pid = $_REQUEST['post']) ) ){
				return;
			}

			if( get_post_type($pid) !== \ReyCore\Elementor\GlobalSections::POST_TYPE ){
				return;
			}

			global $menu;

			foreach( $menu as $key => $value )
			{
				if( reycore__get_dashboard_page_id() == $value[2] ){
					$menu[$key][4] .= " wp-has-current-submenu";
				}
			}
		}

		public function admin_bar_css() {

			if ( ! is_admin_bar_showing() ) {
				return;
			}

			wp_enqueue_style('reycore-frontend-admin');

		}

		public function admin_menu(){

			if( apply_filters('reycore/admin_menu', reycore__get_props('admin_menu')) ){
				return;
			}

			remove_menu_page( reycore__get_dashboard_page_id() );

		}

		public function add_body_class( $classes ) {
			return $classes . (!reycore__get_purchase_code() ? ' --rey-not-registered' : '');
		}

		/**
		 * Adds the admin menu item
		 *
		 * @return void
		 */
		public function add_customizer_shortcut() {

			if( \ReyCore\ACF\Helper::admin_menu_item_is_hidden('customizer') ){
				return;
			}

			if( $dashboard_id = reycore__get_dashboard_page_id() ){
				$title = esc_html__('Customizer', 'rey-core') . '&nbsp; <span class="dashicons dashicons-external" style="width: 1em; height: 1em; font-size: 16px; vertical-align: middle;"></span>';
				add_submenu_page(
					$dashboard_id,
					$title,
					$title,
					'customize',
					admin_url( 'customize.php' )
				);
			}
		}

		public function register_admin_scripts(){

			wp_register_script( 'rey-core-admin-script', REY_CORE_URI . 'assets/js/admin.js', ['jquery', 'underscore', 'wp-util', 'rey-script'], REY_CORE_VERSION, true );

			wp_localize_script( 'rey-core-admin-script', 'reyCoreAdmin', apply_filters('reycore/admin_script_params', [
				'rey_core_version' => REY_CORE_VERSION,
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'ajax_nonce'    => wp_create_nonce('reycore-ajax-verification'),
				'back_btn_text' => esc_html__('Back to List', 'rey-core'),
				'back_btn_url'  => admin_url('edit.php?post_type=rey-global-sections'),
				'is_customizer' => false,
				'strings' => [
					'refresh_demos_error' => esc_html__('Error. Please retry!', 'rey-core'),
					'reloading' => esc_html__('Reloading page!', 'rey-core'),
					'refresh_btn_text' => esc_html__('Refresh Demos List', 'rey-core'),
					'refreshing_btn_text' => esc_html__('Refreshing..', 'rey-core'),
					'migrating_btn_text' => esc_html__('Migrating..', 'rey-core'),
					'help' => esc_html__('Need help?', 'rey-core'),
				],
				'sound_effect' => REY_CORE_URI . 'assets/audio/ding.mp3',
				'support_url__post_tax_cat' => reycore__support_url('kb/settings-for-individual-page-post-taxonomy-category/'),
				'site_url' => site_url()
			]) );

			$rtl = reycore_assets()::rtl();

			$reycore_styles = [
				'rey-core-admin-style' => [
					'src'     => REY_CORE_URI . 'assets/css/admin'. $rtl .'.css',
					'deps'    => [],
					'version' => REY_CORE_VERSION,
				],
				'reycore-frontend-admin' => [
					'src'     => REY_CORE_URI . 'assets/css/general-components/frontend-admin/frontend-admin' . $rtl . '.css',
					'deps'    => [],
					'version' => REY_CORE_VERSION,
				],
			];

			foreach($reycore_styles as $handle => $style ){
				wp_register_style($handle, $style['src'], $style['deps'], $style['version']);
			}
		}

		public function get_frontend_admin_localized_settings(){
			$params = [];
			return $params;
		}

		public function register_actions( $ajax_manager ){
			$ajax_manager->register_ajax_action( 'disable_page_settings_popover', [$this, 'ajax__hide_popover'], [
				'auth' => 1,
				'capability' => 'administrator',
			] );
			$ajax_manager->register_ajax_action( 'refresh_all_caches', [$this, 'ajax__refresh_all'], [
				'auth' => 1,
				'capability' => 'administrator',
			] );
		}

		public static function registration_message(){
			return sprintf('<strong>Purchase code is missing.</strong> Please <a href="%s" target="_blank" onclick="window.open(event.currentTarget.href, \'_blank\');">register Rey</a> to be able to make adjustments and get premium features.', admin_url('admin.php?page=' . reycore__get_dashboard_page_id() ));
		}

		public static function add_registration_overlays( $args = []){

			if( reycore__get_purchase_code() ){
				return;
			}

			$args = wp_parse_args($args, [
				'type' => 'backend',
				'message' => self::registration_message(),
				'script_tag' => true
			]); ?>

			<?php if( $args['script_tag'] ){ ?>
				<script type="text/html" id="tmpl-rey-lock-overlay">
			<?php } ?>

				<div class="rey-lockOverlay --<?php echo $args['type'] ?>">
					<button class="__close" onclick="this.parentElement.remove()"><?php echo reycore__get_svg_icon(['id' => 'close']) ?></button>
					<div class="__inner"><?php echo $args['message'] ?></div>
				</div>

			<?php if( $args['script_tag'] ){ ?>
				</script><?php
			}
		}

		public function add_gs_registration_notice(){
			self::add_registration_overlays([
				'type' => 'elementor',
				'script_tag' => false
			]);
		}

		public function include_customizer_icons_sprite(){

			if( $fs = reycore__wp_filesystem() ){
				/**
				 * var WP_Filesystem_Base $fs
				 */
				echo $fs->get_contents( REY_CORE_DIR . 'assets/images/customizer-icons-inline.svg' );
			}
		}

		public function admin_footer(){

			if( ($current_screen = get_current_screen()) && $current_screen->post_type === \ReyCore\Elementor\GlobalSections::POST_TYPE && $current_screen->base === 'post' ){
				$this->include_customizer_icons_sprite();
			}

			self::add_registration_overlays();
		}

		public function enqueue_admin_scripts(){
			wp_enqueue_script( 'rey-core-admin-script');
			wp_enqueue_style( 'rey-core-admin-style');
		}

		public function enqueue_scripts(){

			if( current_user_can('administrator') && ! is_404() ){

				wp_enqueue_script(
					'reycore-frontend-admin',
					REY_CORE_URI . 'assets/js/general/c-frontend-admin.js',
					['jquery', 'rey-script'],
					REY_CORE_VERSION,
					true
				);

				// wp_localize_script( 'reycore-frontend-admin', 'reyCoreFrontendAdminParams', $this->get_frontend_admin_localized_settings() );

			}
		}

		public function ajax__refresh_all(){
			do_action('reycore/refresh_all_caches');
			return true;
		}

		/**
		 * Hides the popover settings forever (per page)
		 *
		 * @param array $data
		 * @return bool
		 */
		public function ajax__hide_popover($data){

			if( ! isset($data['type'], $data['page_id']) ){
				return;
			}

			if( ! in_array($data['type'], ['header'], true) ){
				return;
			}

			return update_option(self::POPOVER_KEY . $data['type'] . $data['page_id'], true);
		}

		public function get_id(){

			$page_id = get_queried_object_id();

			if( function_exists('is_shop') && is_shop() ){
				$page_id = wc_get_page_id( 'shop' );
			}

			return $page_id;
		}


		public function db_version( $args ){
			$args['body']['db-version'] = REY_CORE_DB_VERSION;
			return $args;
		}

		/**
		 * Shows a popover notice for the header if its settings
		 * are overridden in the page's settings
		 *
		 * @return string
		 */
		public function page_settings_popover__header(){

			if( is_404() ){
				return;
			}

			if( ! apply_filters('reycore/admin/show_page_settings_notice', true) ){
				return;
			}

			$page_id = $this->get_id();

			if( get_option(self::POPOVER_KEY . 'header' . $page_id) ){
				return;
			}

			if( 'none' === reycore__get_option('header_layout_type', 'default') ){
				return;
			}

			$settings = [
				'header_layout_type' => [
					'title' => esc_html__('Header Type', 'rey-core'),
					'default' => 'default',
					'global_section' => true,
				],
				'header_text_color' => [
					'title' => esc_html__('Text Color', 'rey-core'),
					'default' => '',
				],
				'header_position' => [
					'title' => esc_html__('Header Position', 'rey-core'),
					'default' => 'rel',
					'definitions' => [
						'rel' => esc_html__('Relative', 'rey-core'),
						'absolute' => esc_html__('Absolute', 'rey-core'),
						'fixed' => esc_html__('Fixed', 'rey-core'),
					]
				],
				'header_fixed_overlap' => [
					'title' => esc_html__('Header Overlap', 'rey-core'),
					'default' => true,
				],
				'header_fixed_overlap_tablet' => [
					'title' => esc_html__('Header Overlap (tablet)', 'rey-core'),
					'default' => true,
				],
				'header_fixed_overlap_mobile' => [
					'title' => esc_html__('Header Overlap (mobile)', 'rey-core'),
					'default' => true,
				],
				'custom_logo' => [
					'title' => esc_html__('Logo', 'rey-core'),
					'default' => '',
					'image' => true
				],
				'logo_mobile' => [
					'title' => esc_html__('Logo (mobile)', 'rey-core'),
					'default' => '',
					'image' => true
				],
			];

			$overrides = $page_settings = $global_settings = [];

			foreach ($settings as $k => $s) {

				if( ! ($page_setting = get_field($k, $page_id)) ){
					continue;
				}

				$page_settings[$k] = $page_setting;
				$global_settings[$k] = get_theme_mod($k, $s['default']);

				if( $page_settings[$k] !== $global_settings[$k] ){
					$overrides[$k] = $s['title'];
				}
			}

			if( empty($overrides) ){
				return;
			}

			$output = _x('<p>Some of the Header\'s settings (from Customizer > Header) <strong>will not work</strong> because they are overridden in this page, such as:</p>', 'Various admin. texts', 'rey-core');

			$output .= '<table>';
			$output .= '<tr>';
			$output .= sprintf('<th>%s</th>', esc_html__('Setting', 'rey-core'));
			$output .= sprintf('<th>%s</th>', esc_html__('Current', 'rey-core'));
			$output .= sprintf('<th>%s</th>', esc_html__('Global', 'rey-core'));
			$output .= '</tr>';

			foreach ($overrides as $key => $title) {

				if( ! ($current = self::settings_popover_data($page_settings[$key], $settings[$key]))){
					$current = $page_settings[$key];
				}

				$was = '-';
				if( ($global_setting = $global_settings[$key]) ){
					$was = self::settings_popover_data($global_setting, $settings[$key]);
				}

				$output .= '<tr>';
				$output .= sprintf('<td class="_title">%s</td>', $title);
				$output .= sprintf('<td>%s</td>', $current);
				$output .= sprintf('<td>%s</td>', $was);
				$output .= '</tr>';
			}

			$output .= '</table>';

			$output .= sprintf( _x('<p>No action is required.  <a href="%s" target="_blank"><u>Review</u></a> this page\'s Header settings, or <a href="#" class="js-hide"><u>hide this notice</u></a></p>', 'Various admin. texts', 'rey-core'), get_edit_post_link() . '#header-settings' );

			$output .= _x('<p><small>This notice shows only for Administrators.</small></p>', 'Various admin. texts', 'rey-core');

			printf('<div class="__ps-popover --is-active" data-id="%d" data-type="header" data-overrides="%s">', esc_attr($page_id), esc_attr( implode(',', array_keys($overrides)) ));
				echo '<div class="__ps-popover-icon"></div>';
				printf('<div class="rey-simplePopover --arr-top-left">%s</div>', $output );
			echo '</div>';

			return $output;
		}

		public static function settings_popover_data($data, $default_settings){

			$image = isset($default_settings['image']) && $default_settings['image'] === true;

			// Boolean (Yes/No)
			if( is_bool($data) || 'true' === $data || 'false' === $data ){
				return $data === true ? esc_html__('Yes', 'rey-core') : esc_html__('No', 'rey-core');
			}
			// Numeric
			else if( is_numeric($data) && ! $image ){

				$prefix = '';

				if( isset($default_settings['global_section']) && $default_settings['global_section'] === true ){
					$prefix = '<span title="Global Section">G.S.</span> ';
				}

				return sprintf('%s<a href="%s" target="_blank"><u>%d</u></a>', $prefix, get_edit_post_link($data), $data);
			}
			// Image
			else if( $image ){
				return wp_get_attachment_image($data, 'thumbnail');
			}
			// find definition
			else if(isset($default_settings['definitions'][ $data ]) ){
				return $default_settings['definitions'][ $data ];
			}

			return '';
		}

		/**
		 * Popovers for various notices
		 *
		 * @return void
		 */
		public function add_page_settings_popover(){

			if( is_admin() ){
				return;
			}

			if( ! current_user_can('administrator') ){
				return;
			}

			$this->page_settings_popover__header();
		}

		public static function render_top_buttons( $args = [] ){

			$args = wp_parse_args($args, [
				'post_type' => '',
				'export'    => true,
				'export_label'  => 'template',
				'back'      => true,
			]);

			global $current_screen;

			if ( ! $current_screen ) {
				return;
			}

			if( ! ($args['post_type'] === $current_screen->id && $args['post_type'] === $current_screen->post_type) ){
				return;
			} ?>

			<script>

				document.addEventListener('DOMContentLoaded', function () {

					var menuItem = document.getElementById('toplevel_page_rey-dashboard');

					if (menuItem) {

						var menuItemLink = menuItem.querySelector('a');

						menuItem.classList.add('wp-has-current-submenu', 'wp-menu-open');
						menuItem.classList.remove('wp-not-current-submenu');

						if( menuItemLink ){
							menuItemLink.classList.add('wp-has-current-submenu', 'wp-menu-open');
							menuItemLink.classList.remove('wp-not-current-submenu');
						}

						var ptLink = menuItem.querySelector('a[href="edit.php?post_type=<?php echo $args['post_type'] ?>"]');

						if( ptLink ){
							ptLink.classList.add('current');
							ptLink.parentElement.classList.add('current');
						}
					}

					if( document.querySelector('body.post-type-<?php echo $args['post_type'] ?>:not(.edit-php)') ){

						var theTarget;
						var topTarget = document.querySelectorAll('.wrap .wp-heading-inline ~ a.page-title-action');

						if( topTarget.length ){
							theTarget = topTarget[ topTarget.length - 1 ];
						}

						<?php if($args['back']) { ?>

							var backToListLink = document.createElement('a');
								backToListLink.classList.add('page-title-action');
								backToListLink.setAttribute('href', '<?php echo admin_url( 'edit.php?post_type=' . $args['post_type'] ) ?>');
								backToListLink.textContent = '<?php esc_html_e('Back to list', 'rey-core') ?>';

							if( theTarget ){
								theTarget.parentNode.insertBefore(backToListLink, theTarget.nextSibling);
							}

						<?php
						}

						global $post;

						if( $args['export'] && isset($post->ID) && $template_id = $post->ID ){

							if( ( $document = \Elementor\Plugin::$instance->documents->get( $template_id ) ) && $document->is_built_with_elementor() ){

								$link = add_query_arg([
										'action'         => 'elementor_library_direct_actions',
										'library_action' => 'export_template',
										'source'         => 'local',
										'_nonce'         => wp_create_nonce( 'elementor_ajax' ),
										'template_id'    => $template_id,
									],
									admin_url( 'admin-ajax.php' )
								); ?>

								var exportLink = document.createElement('a');
									exportLink.classList.add('page-title-action');
									exportLink.setAttribute('href', '<?php echo $link ?>');
									exportLink.textContent = '<?php printf(esc_html__('Export %s', 'rey-core'), $args['export_label']) ?>';

								if( theTarget ){
									theTarget.parentNode.insertBefore(exportLink, theTarget.nextSibling);
								} <?php
							}
						}
						?>
					}
				});

			</script>
			<?php
		}

	}
