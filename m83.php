<?php
/**
 * Plugin Name: M83 - Routing for WordPress
 * Version: 0.2
 * Description: Simple router plugin to direct templates to helper classes.
 * Author: David Hewitson
 * Text Domain: m83
 * Domain Path: /languages
 * @package M83 - Routing for WordPress
 */

namespace Shstkvch\M83;

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
		if ( defined( 'WP_CLI' ) ) {
			return;
		}

		add_filter( 'template_include', [ __CLASS__, 'dispatchRequest' ] );
		add_action( 'plugins_loaded', [ __CLASS__, 'loadUserClasses' ] );
		add_filter( 'theme_page_templates', [ __CLASS__, 'filterPageTemplates'] );

		// TODO: ACF Support
		// add_filter( 'acf/location/rule_types', [__CLASS__, 'filterACFRules' ] );
		// add_filter( 'acf/location/rule_match/m83_route', [__CLASS__, 'filterACFRuleMatchRoute' ], 10, 3 );
		// add_filter( 'acf/location/rule_match/m83_helper_class', [__CLASS__, 'filterACFRuleMatchHelperClass' ], 10, 3 );
		// add_filter( 'acf/location/rule_values/m83_route', [__CLASS__, 'filterACFRuleValuesRoute' ] );
		// add_filter( 'acf/location/rule_values/m83_helper_class', [__CLASS__, 'filterACFRuleValuesHelperClass' ] );
	}

	/**
	 * Assign a GET route
	 *
	 * @param string $slug the template file to override without the .php
	 * 	extension (i.e. index, singular, archive...)
	 * @param string $helper the helper class to instantiate. If you include
	 *  an @ symbol, you can call a specific method - like 'indexHelper@view'
	 * @param array $options an array of additional options.
	 * 			'page_template' => 'template.php' a page template to simulate
	 *											  (such as 'page-news.php')
	 * @return void
	 */
	public function get( $slug = '', $helper = '', $options = [] ) {
		static::assign( 'GET', $slug, $helper, $options );
	}

	/**
	 * Assign a route to the correct helper
	 *
	 * @param  string $verb the HTTP verb to user (only GET currently supported)
	 * @param  string $slug the template name to match against
	 * @param  string $helper the identifier of the helper to call
	 * @param  array  $options additional options to pass
	 * @return void
	 */
	private function assign( $verb = 'GET', $slug = '', $helper = '', $user_options = [] ) {
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

		$options = [
			'class' => $helper_class,
			'method' => $helper_method
		];

		$options = array_merge( $options, $user_options );

		static::$routes[ $slug ] = $options;
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
			$instance = new $helper_class();

			$args = apply_filters( 'm83/pre_call_helper_args', [], $instance );

			$result = call_user_func_array( [ $instance, $helper_method ], $args );

			do_action( 'm83/after_call_helper', $instance, $result );
		}
	}

	/**
	 * Load the user classes (routes/helpers)
	 */
	public function loadUserClasses() {
		static::loadHelpers();
		static::loadRoutesFile();
	}

	/**
	 * Filter the list of page templates to include our routes
	 */
	public function filterPageTemplates( $page_templates ) {
		return( array_merge( $page_templates, static::getPageTemplates() ) );
	}

	/**
	 * Get the page templates our routes are virtually providing
	 */
	private function getPageTemplates() {
		$page_templates = [];

		foreach ( static::$routes as $slug => $value ) {
			if ( $value['page_template'] ) {
				$page_templates[ucfirst($slug)] = $value['page_template'];
			}
		}

		return $page_templates;
	}

	/**
	 * Filter the ACF rules list and add our own rule :)
	 */
	public function filterACFRules( $rules ) {
		$rules['M83'] = [
			'm83_route' => 'Route',
			'm83_helper_class' => 'Helper Class',
		];

		return $rules;
	}

	/**
	 * Filter whether we're matching for ACF - helper class
	 */
	public function filterACFRuleMatchHelperClass( $match, $rule, $args ) {
		var_dump( $rule, $args ); die();
	}

	/**
	 * Filter whether we're matching for ACF - route
	 */
	public function filterACFRuleMatchRoute( $match, $rule, $args ) {
		var_dump( $rule, $args ); die();
	}

	/**
	 * Filter the values list for ACF - M83 Routes
	 */
	public function filterACFRuleValuesRoute( $values ) {
		$values = [];

		foreach( self::$routes as $slug => $options ) {
			$values[$slug] = ucfirst( $slug );
		}

		return $values;
	}

	/**
	 * Filter the values list for ACF - M83 Helper Classes
	 */
	public function filterACFRuleValuesHelperClass( $values ) {
		$values = [];

		foreach( self::$routes as $slug => $options ) {
			$class = $options['class'];
			$method = $options['method'];

			if ( $method ) {
				$method = '@' . $method;
			} else {
				$method = '';
			}

			$values[$class . $method] = $class . $method;
		}

		return $values;
	}

	/**
	 * TODO...
	 */
	private function getTemplateFromPost() {
		// ...
	}
}

Router::__init__();
