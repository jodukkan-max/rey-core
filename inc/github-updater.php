<?php
namespace ReyCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GitHub-based auto-updater for Rey Core.
 *
 * Replaces the default update channel by checking the GitHub Releases API for
 * newer tagged releases and injecting them into WordPress's plugin update
 * system. Updates are offered only when a release tagged with a version
 * greater than the installed REY_CORE_VERSION is published.
 *
 * @since 3.1.12
 */
class GithubUpdater {

	/**
	 * GitHub repository slug (owner/repo).
	 */
	const REPO = 'jodukkan-max/rey-core';

	/**
	 * Plugin slug and basename, as installed under wp-content/plugins.
	 */
	const SLUG     = 'rey-core';
	const BASENAME = 'rey-core/rey-core.php';

	/**
	 * Transient key used to cache the latest release response.
	 */
	const CACHE_KEY = 'reycore_github_updater_latest';

	/**
	 * How long (seconds) to cache the GitHub API response.
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Register the update hooks.
	 *
	 * Hooks both the "set" and "read" update filters at the highest possible
	 * priority so this runs last and overrides any `rey-core` update entry
	 * injected by the Rey theme's own updater. This makes GitHub the single
	 * source of truth for Rey Core updates.
	 */
	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ], PHP_INT_MAX );
		add_filter( 'site_transient_update_plugins', [ $this, 'check_for_update' ], PHP_INT_MAX );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Inject a pending update when GitHub has a newer release.
	 *
	 * Also drops any pre-existing entry for this plugin (e.g. one injected by
	 * the Rey theme) so that only GitHub can offer a Rey Core update.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {

		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}

		// Always remove any pre-existing Rey Core entry (from Rey's updater)
		// before deciding whether to add our GitHub entry.
		unset( $transient->response[ self::BASENAME ] );

		if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
			unset( $transient->no_update[ self::BASENAME ] );
		}

		// Don't offer an update before the installed version is known.
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$latest_version = $this->normalize_version( $release['tag_name'] );

		if ( ! $latest_version || version_compare( REY_CORE_VERSION, $latest_version, '>=' ) ) {
			return $transient;
		}

		$package = $this->get_package_url( $release );

		if ( ! $package ) {
			return $transient;
		}

		$transient->response[ self::BASENAME ] = (object) [
			'slug'        => self::SLUG,
			'plugin'      => self::BASENAME,
			'new_version' => $latest_version,
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => $package,
			'icons'       => [],
			'banners'     => [],
			'banners_rtl' => [],
			'tested'      => $latest_version,
			'requires_php'=> false,
			'compatibility' => new \stdClass(),
		];

		return $transient;
	}

	/**
	 * Provide plugin information for the "View version details" popup.
	 *
	 * @param false|object $result Default plugin info result.
	 * @param string       $action The type of information being requested.
	 * @param object       $args   Plugin API arguments.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {

		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$latest_version = $this->normalize_version( $release['tag_name'] );
		$body           = ! empty( $release['body'] ) ? $release['body'] : '';

		return (object) [
			'name'          => 'Rey Core',
			'slug'          => self::SLUG,
			'version'       => $latest_version,
			'author'        => '<a href="https://github.com/' . self::REPO . '">' . self::REPO . '</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => $this->get_package_url( $release ),
			'sections'      => [
				'description' => $body,
				'changelog'   => $body,
			],
			'last_updated'  => ! empty( $release['published_at'] ) ? $release['published_at'] : '',
			'icons'         => [],
			'banners'       => [],
		];
	}

	/**
	 * Fetch the latest release from the GitHub API, cached in a transient.
	 *
	 * @return array|false
	 */
	private function get_latest_release() {

		$cached = get_site_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return empty( $cached['tag_name'] ) ? false : $cached;
		}

		$response = wp_safe_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			[
				'timeout' => 15,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'ReyCore-GithubUpdater',
				],
			]
		);

		$release = [];

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$release = json_decode( wp_remote_retrieve_body( $response ), true );
			$release = is_array( $release ) ? $release : [];
		}

		// Cache failures briefly to avoid hammering the API.
		$ttl = empty( $release['tag_name'] ) ? HOUR_IN_SECONDS : self::CACHE_TTL;

		set_site_transient( self::CACHE_KEY, $release, $ttl );

		return empty( $release['tag_name'] ) ? false : $release;
	}

	/**
	 * Resolve the downloadable zip URL for a release.
	 *
	 * @param array $release Release data from the GitHub API.
	 * @return string
	 */
	private function get_package_url( $release ) {

		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		if ( empty( $release['tag_name'] ) ) {
			return '';
		}

		// Conventional fallback for a zip asset named after the slug.
		return 'https://github.com/' . self::REPO . '/releases/download/' . rawurlencode( $release['tag_name'] ) . '/' . self::SLUG . '.zip';
	}

	/**
	 * Strip a leading "v" from a GitHub tag name.
	 *
	 * @param string $tag_name Raw tag name from GitHub.
	 * @return string
	 */
	private function normalize_version( $tag_name ) {
		return ltrim( (string) $tag_name, 'vV' );
	}
}
