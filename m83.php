<?php
/**
 * Plugin Name: M83 - Routing for WordPress
 * Version: 0.1-alpha
 * Description: Simple router plugin to direct templates to helper classes.
 * Author: David Hewitson
 * Author URI: http://github.com/shstkvch/
 * Plugin URI: http://github.com/shstkvch/m83/
 * Text Domain: m83
 * Domain Path: /languages
 * @package M83 - Routing for WordPress
 */

namespace m83;

use \Exception as Exception;

final class Router {

	/**
	 * The routes we have assigned to the class
	 * @var $routes
	 */
	private static $routes = [];

	/**
	 * Protected constructor to prevent new instances of the class
	 */
	protected function __construct() {
	}

	/**
	 * Static initialiser
	 */
	public function __init__() {
		add_filter( 'template_include', [ __CLASS__, 'dispatchRequest' ] );

		static::loadHelpers();
		static::loadRoutesFile();
	}

	/**
	 * Assign a GET route
	 *
	 * @param string $slug the template file to override without the .php
	 * 	extension (i.e. index, singular, archive...)
	 * @param string $helper the helper class to instantiate. If you include
	 *  an @ symbol, you can call a specific method - like 'indexHelper@view'
	 * @return void
	 */
	public function get( $slug = '', $helper = '' ) {
		static::assign( 'GET', $slug, $helper );
	}

	/**
	 * Assign a route to the correct helper
	 *
	 * @param  string $verb the HTTP verb to user (only GET currently supported)
	 * @param  string $slug the template name to match against
	 * @param  string $helper the identifier of the helper to call
	 * @return void
	 */
	private function assign( $verb = 'GET', $slug = '', $helper = '' ) {
		if ( 'GET' !== $verb ) {
			return false; // only GET supported
		}

		if ( !$slug || !$helper ) {
			return; // we need a template name and helper
		}

		// HelperClass@helperMethod
		list( $helper_class, $helper_method ) = explode( '@', $helper );

		if ( !static::locateHelper( $helper_class ) ) {
			throw new Exception( "Helper with class $helper_class could not be located." );
			return;
		}

		static::$routes[ $slug ] = [
			'class' => $helper_class,
			'method' => $helper_method
		];
	}

	/**
	 * Determine if a class exists
	 *
	 * @param  string $helper_class the class to try and find
	 * @return bool True if the class is found, else False.
	 */
	private function locateHelper( $helper_class ) {
		if ( class_exists( $helper_class ) ) {
			return true;
		}
	}

	/**
	 * Attempt to load the routes.php file from the theme directory
	 */
	private function loadRoutesFile() {
		$routes_location = realpath( get_template_directory() . '/routes.php' );
		$routes_location = apply_filters( 'm83/routes_file_location', $routes_location );

		if ( file_exists( $routes_location ) ) {
			require_once( $routes_location );
		}
	}

	/**
	 * Attempt to load the helper classes from the theme's helpers directory
	 */
	private function loadHelpers() {
		$helpers_location = realpath( get_template_directory() . '/helpers/' );
		$helpers_location = apply_filters( 'm83/helpers_location', $helpers_location );

		if ( ! is_dir( $helpers_location ) ) {
			return;
		}

		$helper_files = glob( $helpers_location . '/*.php');

		foreach ( $helper_files as $helper ) {
			require_once( $helper );
		}
	}

	/**
	 * Get a slug from a template path
	 *
	 * @param  string $path the path to check
	 * @return mixed the slug if it exists, or false
	 */
	private function getSlugFromTemplate( $path ) {
		$slug = basename( $path );

		if ( !$slug ) {
			return false;
		}

		return substr( $slug, 0, -4 );
	}

	/**
	 * Handle a request for a template path
	 *
	 * @param  string $template the template path to parse
	 * @return void
	 */
	public function dispatchRequest( $template ) {
		$slug = self::getSlugFromTemplate( $template );

		if ( !$slug ) {
			return $template; // pass through if we don't have a slug
		}

		$helper = self::getHelperFromSlug( $slug );

		$helper_class =  $helper['class'];
		$helper_method = $helper['method'];

		if ( !$helper_class ) {
			return $template; // pass through if we don't have a helper.
		}

		self::callHelper( $helper_class, $helper_method );
	}

	/**
	 * Get the helper class and method from a given slug
	 *
	 * @param  string $slug
	 * @return array
	 */
	private function getHelperFromSlug( $slug ) {
		return static::$routes[ $slug ];
	}

	/**
	 * Instantiate a helper and call a class on it
	 *
	 * @param string $helper_class the class to call
	 * @param string $helper_method the method to call
	 */
	private function callHelper( $helper_class, $helper_method ) {
		if ( !$helper_method ) {
			$helper_method = 'main';
		}

		if ( method_exists( $helper_class, $helper_method ) ) {
			call_user_func( [ $helper_class, $helper_method ] );
		}
	}
}

Router::__init__();
