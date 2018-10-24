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
	}

	/**
	 * Try to autoload from cache.
	 */
	public static function autoload( $class_name, $extensions = null ) {
		if ( ! isset( self::$cache[ $class_name ] ) ) {
			self::$busted []= $class_name;
			return;
		}

		$class_file = self::$cache[ $class_name ];

		if ( ! file_exists( self::$cache[ $class_name ] ) ) {
			self::$busted []= $class_name;
			return;
		}

		require_once $class_file;
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
	 * Remove autoloader prepends.
	 *
	 * We need to be infront of all.
	 * Wrap your autoloader calls with \Pressjitsu\Autohoarder::hoard.
	 */
	public static function hoard( $loader ) {
		switch ( get_class( $loader ) ):
			case 'Composer\Autoload\ClassLoader':
				$loader->unregister();
				$loader->register( false );
				break;
		endswitch;

		return $loader;
	}
}

Autohoarder::init();

spl_autoload_register( '\Pressjitsu\Autohoarder::autoload', true, true );
register_shutdown_function( '\Pressjitsu\Autohoarder::store' );
