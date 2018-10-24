<?php
/**
 * Plugin Name: Autohoarder autoload cache
 * Description: Autoloaders do too much IO.
 * Plugin URI: https://github.com/pressjitsu/autohoarder/
 *
 * Bakes and stows away expensive class lookups
 *  as PHP hashtables. Fast and beautiful.
 *
 * GPL3
 * Pressjitsu, Inc.
 * https://pressjitsu.com
 */

namespace Pressjitsu;

class Autohoarder {
	/**
	 * The unknown classes. Delegate and store.
	 */
	private static $busted = array();

	/**
	 * The cache.
	 */
	private static $cache = array();

	/**
	 * The cache store.
	 */
	private static $cache_file = 'autohoarder.cache';

	/**
	 * Read the cache.
	 */
	public static function init() {
		self::$cache_file = sprintf( '%s/%s', untrailingslashit( sys_get_temp_dir() ), self::$cache_file );

		if ( file_exists( self::$cache_file ) ) {
			include self::$cache_file;
			self::$cache = &$_cache;
		}

		register_shutdown_function( array( __CLASS__, 'store' ) );
	}

	/**
	 * Lookup and store unknown cache entries.
	 */
	public static function store() {
		if ( empty( self::$busted ) ) {
			return;
		}

		foreach ( self::$busted as $busted ) {
			if ( ! class_exists( $busted ) && ! interface_exists( $busted ) && ! trait_exists( $busted ) ) {
				continue;
			}

			$class = new \ReflectionClass( $busted );
			self::$cache[ $busted ] = $class->getFileName();
			unset( $class );
		}

		file_put_contents( self::$cache_file, sprintf( '<?php $_cache = %s;', var_export( self::$cache, true ) ), LOCK_EX );
	}

	/**
	 * Wrap and cache :)
	 */
	public static function hoard( callable $callback ) {
		return function( $class_name ) use ( $callback ) {
			if ( ! isset( self::$cache[ $class_name ] ) ) {
				self::$busted []= $class_name;
				return $callback( $class_name );
			}

			$class_file = self::$cache[ $class_name ];

			if ( ! file_exists( self::$cache[ $class_name ] ) ) {
				self::$busted []= $class_name;
				return $callback( $class_name );
			}

			require_once $class_file;
		};
	}
}

Autohoarder::init();
