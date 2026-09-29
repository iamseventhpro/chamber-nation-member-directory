<?php
/**
 * Shared /llms.txt and /llms-full.txt for Chamber Nation plugins.
 *
 * Several Chamber Nation plugins bundle this same file; whichever loads first
 * defines the class and the others reuse it. Each plugin adds its own
 * section through the cn_llms_txt_sections filter and opts in through
 * cn_llms_txt_enabled. A physical llms.txt file in the site root always
 * wins, because the web server serves it before WordPress runs.
 *
 * @package ChamberNation
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CN_Llms_Txt' ) ) {

	/**
	 * llms.txt endpoint.
	 */
	class CN_Llms_Txt {

		/**
		 * Query var.
		 */
		const QV = 'cn_llms';

		/**
		 * Cache lifetime.
		 */
		const TTL = HOUR_IN_SECONDS;

		/**
		 * Whether hooks are registered.
		 *
		 * @var bool
		 */
		private static $booted = false;

		/**
		 * Register hooks once.
		 */
		public static function boot() {
			if ( self::$booted ) {
				return;
			}
			self::$booted = true;
			add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
			add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
			add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		}

		/**
		 * Rewrite rules.
		 */
		public static function add_rewrite_rules() {
			add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QV . '=index', 'top' );
			add_rewrite_rule( '^llms-full\.txt$', 'index.php?' . self::QV . '=full', 'top' );
		}

		/**
		 * Query var.
		 *
		 * @param string[] $vars Vars.
		 * @return string[]
		 */
		public static function query_vars( $vars ) {
			$vars[] = self::QV;
			return $vars;
		}

		/**
		 * URL of a file.
		 *
		 * @param string $type index or full.
		 * @return string
		 */
		public static function url( $type = 'index' ) {
			if ( get_option( 'permalink_structure' ) ) {
				return home_url( 'full' === $type ? '/llms-full.txt' : '/llms.txt' );
			}
			return add_query_arg( self::QV, $type, home_url( '/' ) );
		}

		/**
		 * Clear cached output (call after data changes).
		 */
		public static function flush() {
			delete_transient( 'cn_llms_index' );
			delete_transient( 'cn_llms_full' );
		}

		/**
		 * Serve the file.
		 */
		public static function serve() {
			$type = (string) get_query_var( self::QV );
			if ( ! in_array( $type, array( 'index', 'full' ), true ) ) {
				return;
			}
			if ( ! apply_filters( 'cn_llms_txt_enabled', false ) ) {
				status_header( 404 );
				exit;
			}

			$body = get_transient( 'cn_llms_' . $type );
			if ( ! is_string( $body ) ) {
				$body = self::build( $type );
				set_transient( 'cn_llms_' . $type, $body, self::TTL );
			}

			status_header( 200 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			header( 'Cache-Control: public, max-age=3600' );
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text document; sections sanitize their own content.
			exit;
		}

		/**
		 * Build the document.
		 *
		 * @param string $type index or full.
		 * @return string
		 */
		private static function build( $type ) {
			$name        = wp_strip_all_tags( get_bloginfo( 'name' ) );
			$description = wp_strip_all_tags( get_bloginfo( 'description' ) );

			$out = '# ' . $name . "\n\n";
			if ( $description ) {
				$out .= '> ' . $description . "\n\n";
			}
			$out .= sprintf( "Website: %s\n", home_url( '/' ) );
			if ( 'index' === $type ) {
				$out .= sprintf( "Full details: %s\n", self::url( 'full' ) );
			}
			$out .= "\n";

			/**
			 * Filter the sections of llms.txt / llms-full.txt.
			 *
			 * @param array[] $sections Each: array( 'title' => string, 'body' => Markdown ).
			 * @param string  $type     index or full.
			 */
			$sections = apply_filters( 'cn_llms_txt_sections', array(), $type );
			foreach ( (array) $sections as $section ) {
				if ( empty( $section['title'] ) || ! isset( $section['body'] ) ) {
					continue;
				}
				$out .= '## ' . self::line( $section['title'] ) . "\n\n" . trim( (string) $section['body'] ) . "\n\n";
			}

			$pages = get_pages(
				array(
					'parent'      => 0,
					'sort_column' => 'menu_order,post_title',
					'number'      => 30,
				)
			);
			if ( $pages ) {
				$out .= "## Pages\n\n";
				foreach ( $pages as $page ) {
					$out .= '- [' . self::line( get_the_title( $page ) ) . '](' . get_permalink( $page ) . ")\n";
				}
			}

			return $out;
		}

		/**
		 * Make a value safe for one Markdown line.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public static function line( $text ) {
			$text = wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			return trim( preg_replace( '/\s+/u', ' ', str_replace( array( '[', ']' ), array( '(', ')' ), $text ) ) );
		}
	}
}

CN_Llms_Txt::boot();
