<?php
/**
 * In-process frontend asset capture (no HTTP loopback).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\OutputBuffer;
use AssetControl\Helpers\UrlHelper;

/**
 * Simulates a front-end request and reads $wp_scripts / $wp_styles queues.
 */
final class AssetCapture {

	/**
	 * @return array{assets: array<int, array<string, mixed>>, queues: array{scripts: array<int, string>, styles: array<int, string>}, error: string}
	 */
	public function capture_for_url( string $url, bool $visitor_mode = false ): array {
		$url = $this->normalize_url( $url );

		if ( '' === $url ) {
			return $this->empty_payload( 'invalid_url' );
		}

		$buffer_level = OutputBuffer::start();

		$state = null;

		try {
			$state = $this->bootstrap_dependency_registry( $url, true, $visitor_mode );

			$registry = new Registry();
			$assets   = array_values(
				array_filter(
					$registry->collect_enqueued(),
					static fn( array $asset ): bool => '' !== ( $asset['src'] ?? '' )
				)
			);

			return array(
				'assets' => $assets,
				'queues' => Registry::get_queue_snapshot(),
				'error'  => '',
			);
		} catch ( \Throwable $e ) {
			return $this->empty_payload( $e->getMessage() );
		} finally {
			if ( is_array( $state ) ) {
				$this->restore_after_bootstrap( $state );
			}
			OutputBuffer::end_clean( $buffer_level );
		}
	}

	/**
	 * Loads frontend script/style queues in-process (for scans and validation).
	 *
	 * @param bool $include_footer When false, skips footer hooks (safer for REST JSON).
	 * @return array{wp_query: mixed, post: mixed, wp_the_query: mixed}
	 */
	public function bootstrap_dependency_registry( string $url, bool $include_footer = true, bool $visitor_mode = false ): array {
		$buffer_level = OutputBuffer::start();

		try {
			$state = $this->save_state();
			$url   = $this->normalize_url( $url );

			$this->reset_dependency_queues();

			if ( '' !== $url ) {
				$this->bootstrap_query( $url );

				add_filter( 'is_admin', '__return_false', PHP_INT_MAX );

				if ( $visitor_mode ) {
					add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
					// Simulate a logged-out visitor (admin REST scans run as a logged-in user).
					wp_set_current_user( 0 );
				}

				/**
				 * Fires before collecting assets during in-process capture.
				 */
				do_action( 'assetpilot_before_bootstrap_enqueue', $url );

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook required to mirror front-end enqueue.
				do_action( 'wp_enqueue_scripts' );

				if ( $visitor_mode ) {
					$this->strip_admin_bar_assets();
					$this->strip_block_editor_assets();
				}

				$post_id = (int) get_queried_object_id();
				if ( $post_id <= 0 ) {
					$post_id = url_to_postid( $url );
				}
				if ( $post_id > 0 ) {
					$this->maybe_enqueue_page_builder_assets( $post_id );
				}

				if ( function_exists( 'wp_print_styles' ) ) {
					wp_print_styles();
				}

				if ( $include_footer ) {
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core footer hooks for script capture.
					do_action( 'wp_footer' );
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					do_action( 'wp_print_footer_scripts' );
				}
			}

			return $state;
		} finally {
			OutputBuffer::end_clean( $buffer_level );
		}
	}

	/**
	 * @param array{wp_query: mixed, post: mixed, wp_the_query: mixed} $state
	 */
	public function restore_after_bootstrap( array $state ): void {
		remove_filter( 'is_admin', '__return_false', PHP_INT_MAX );
		remove_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		$this->restore_state( $state );
		$this->reset_dependency_queues();
	}

	/**
	 * Admin bar (logged-in toolbar) pulls in underscore, hoverIntent, etc. — not visitor-facing.
	 */
	private function strip_admin_bar_assets(): void {
		wp_dequeue_script( 'admin-bar' );
		wp_dequeue_style( 'admin-bar' );

		// Admin bar deps (underscore, hoverIntent, etc.) — dequeue if nothing else on the page needs them.
		foreach ( array( 'hoverintent-js', 'hoverIntent', 'common', 'underscore', 'underscore-js', 'wp-util', 'wp-a11y' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * Block editor bundles (enqueue_block_editor_assets) must not appear on front scans.
	 */
	private function strip_block_editor_assets(): void {
		$handles = array(
			'codemirror',
			'codemirror-autoload',
			'codemirror-blocks-editor',
			'wp-block-editor',
			'wp-block-library-editor',
			'wp-editor',
			'wp-edit-widgets',
			'wp-edit-post',
			'wp-format-library',
		);

		$handles = array_merge(
			$handles,
			(array) \apply_filters( 'assetpilot_strip_block_editor_handles', array() )
		);

		foreach ( array_unique( $handles ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * @return array{assets: array<int, array<string, mixed>>, queues: array{scripts: array<int, string>, styles: array<int, string>}, error: string}
	 */
	private function empty_payload( string $error ): array {
		return array(
			'assets' => array(),
			'queues' => array(
				'scripts' => array(),
				'styles'  => array(),
			),
			'error'  => $error,
		);
	}

	private function normalize_url( string $url ): string {
		$url   = trim( rawurldecode( $url ) );
		$clean = \esc_url_raw( $url );

		return '' !== $clean ? $clean : $url;
	}

	private function reset_dependency_queues(): void {
		if ( wp_scripts() ) {
			wp_scripts()->reset();
		}
		if ( wp_styles() ) {
			wp_styles()->reset();
		}
	}

	private function bootstrap_query( string $url ): void {
		if ( UrlHelper::is_site_front_url( $url ) ) {
			$this->bootstrap_query_from_request_uri( $url );
			return;
		}

		$post_id    = url_to_postid( $url );
		$query_args = array();

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				if ( 'page' === $post->post_type ) {
					$query_args['page_id'] = $post_id;
				} else {
					$query_args['p'] = $post_id;
				}
			}
		}

		$GLOBALS['wp_query'] = new \WP_Query( $query_args );

		if ( $GLOBALS['wp_query']->have_posts() ) {
			$GLOBALS['wp_query']->the_post();
		}
	}

	/**
	 * Parse the real front URL (template hierarchy) instead of forcing page_on_front.
	 */
	private function bootstrap_query_from_request_uri( string $url ): void {
		$path  = (string) \wp_parse_url( $url, PHP_URL_PATH );
		$query = (string) \wp_parse_url( $url, PHP_URL_QUERY );

		if ( '' === $path ) {
			$path = '/';
		}

		$request_uri = $path;
		if ( '' !== $query ) {
			$request_uri .= '?' . $query;
		}

		global $wp;

		if ( ! ( $wp instanceof \WP ) ) {
			$wp = new \WP();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- restored after bootstrap.
		$_SERVER['REQUEST_URI'] = $request_uri;

		$wp->parse_request();
		$wp->query_posts();
		$wp->register_globals();

		if ( $GLOBALS['wp_query']->have_posts() ) {
			$GLOBALS['wp_query']->the_post();
		}
	}

	private function maybe_enqueue_page_builder_assets( int $post_id ): void {
		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( $plugin->db->is_built_with_elementor( $post_id ) ) {
				$plugin->frontend->enqueue_styles();
				$plugin->frontend->enqueue_scripts();
			}
		}
	}

	/**
	 * @return array{wp_query: mixed, post: mixed, wp_the_query: mixed}
	 */
	private function save_state(): array {
		global $post, $wp_the_query;

		return array(
			'wp_query'        => $GLOBALS['wp_query'] ?? null,
			'post'            => $post ?? null,
			'wp_the_query'    => $wp_the_query ?? null,
			'request_uri'     => isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
				: '',
			'current_user_id' => get_current_user_id(),
		);
	}

	/**
	 * @param array{wp_query: mixed, post: mixed, wp_the_query: mixed} $state
	 */
	private function restore_state( array $state ): void {
		global $post, $wp_the_query;

		wp_reset_postdata();

		if ( $state['wp_query'] instanceof \WP_Query ) {
			$GLOBALS['wp_query'] = $state['wp_query'];
		}

		$post         = $state['post'];
		$wp_the_query = $state['wp_the_query'];

		if ( array_key_exists( 'request_uri', $state ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- restoring prior value.
			$_SERVER['REQUEST_URI'] = $state['request_uri'];
		}

		if ( array_key_exists( 'current_user_id', $state ) ) {
			wp_set_current_user( (int) $state['current_user_id'] );
		}
	}
}
