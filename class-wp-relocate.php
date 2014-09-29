<?php
if ( ! defined( 'ABSPATH' ) )
	die( 'This class is meant to be used within the WordPress context' );

/**
 * Handles backend changes to an installation's domain or path.
 *
 * Note: this class assumes that it will be used within a WordPress
 * installation, or minimally that it can load WordPress core includes.
 *
 * @package	WP Relocate
 * @author	Frederick Ding <frederick@frederickding.com>
 * @license	http://wordpress.org/about/license/ GPLv2 or later
 * @version	1.0.0
 */
class WP_Relocate {

	/**
	 * Class version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * The "previous" site URL, such as 'http://www.example.com'
	 *
	 * @var string
	 */
	protected $old_site_url;

	/**
	 * The "destination" site URL, such as 'http://wp.example.com/wordpress'
	 *
	 * @var string
	 */
	protected $new_site_url;

	/**
	 * Initializes a new instance of this class for a given set of replacement
	 * parameters.
	 *
	 * @since 1.0.0
	 * @param string $old
	 *        	Site URL to find
	 * @param string $new
	 *        	Site URL with which to replace
	 * @throws InvalidArgumentException if either parameter is not a valid URL.
	 */
	public function __construct ( $old, $new ) {
		// only applying relaxed helper method for this since we want to permit 
		// developer use of this class for more creative URL replacements
		if ( ! self::is_valid_url( $new ) )
			throw new InvalidArgumentException( 
					'Old site URL is not a valid URL' );
		if ( ! self::is_valid_url( $new ) )
			throw new InvalidArgumentException( 
					'New site URL is not a valid URL' );
		$this->old_site_url = $old;
		$this->new_site_url = $new;
	}

	/**
	 * Replaces URLs in post content.
	 *
	 * Custom post types are in theory supported, but it is possible to use a
	 * filter (wp_relocate_all_post_types) to exclude certain post types from
	 * replacement.
	 *
	 * @since 1.0.0
	 * @param array|null $include
	 *        	An array of post IDs to target
	 * @return array An associative array of updated post IDs to post IDs.
	 */
	public function replace_post_content ( $include = array() ) {
		$posts = array();
		$new_posts = array();
		
		// super crucial: wp_update_post() makes REVISIONS
		
		if ( is_array( $include ) && count( $include ) > 0 ) {
			// include is an array of post IDs
			$post_ids = implode( ',', $include );
			$posts = get_posts( 
					array( 
							'include' => $post_ids,
							'posts_per_page' => - 1,
							'post_status' => 'any',
							'post_type' => 'any' 
					) );
		} else {
			// when we later query for all, we want these post types
			$post_types = array( 
					'post',
					'page',
					'nav_menu_item' 
			);
			if ( function_exists( 'get_post_types' ) ) {
				// include custom post types if we are able
				$custom_post_types = get_post_types( 
						array( 
								'_builtin' => false 
						) );
				$post_types = array_merge( $post_types, $custom_post_types );
			}
			$post_types = apply_filters( 'wp_relocate_all_post_types', 
					$post_types );
			
			$posts = get_posts( 
					array( 
							'posts_per_page' => - 1,
							'post_status' => 'any',
							'post_type' => $post_types 
					) );
		}
		foreach ( $posts as $post_object ) {
			$post_object instanceof WP_Post; // just a hint for the IDE
			$post_object->post_content = $this->_replace( 
					$post_object->post_content );
			
			// update in database and add its ID to $new_posts[]
			$_update_id = wp_update_post( $post_object );
			if ( $_update_id == $post_object->ID ) {
				$new_posts[$_update_id] = $post_object->ID;
			}
		}
		
		return $new_posts;
	}

	/**
	 * Replaces URLs on attachment GUIDs.
	 *
	 * Note that GUIDs are used only as a fallback since 2.7, so an options
	 * replacement is also required for updated attachment URLs to be certain.
	 *
	 * @since 1.0.0
	 * @param array|null $include
	 *        	An array of post IDs to target
	 * @return array An associative array of updated post IDs and URLs.
	 */
	public function replace_attachments ( $include = array() ) {
		$attachments = array();
		$new_attachments = array();
		
		if ( is_array( $include ) && count( $include ) > 0 ) {
			// include is an array of attachment IDs
			$attachment_ids = implode( ',', $include );
			$attachments = get_posts( 
					array( 
							'include' => $attachment_ids,
							'posts_per_page' => - 1,
							'post_type' => 'attachment' 
					) );
		} else {
			// fetch all attachments
			$attachments = get_posts( 
					array( 
							'post_type' => 'attachment',
							'posts_per_page' => - 1,
							'post_parent' => null 
					) );
		}
		foreach ( $attachments as $attachment_object ) {
			$_current = get_object_vars( $attachment_object );
			$_current['guid'] = $this->_replace( $_current['guid'] );
			
			// update in database and add its ID to $new_attachments[]
			$_current_id = wp_insert_attachment( $_current );
			if ( $_current_id == $attachment_object->ID ) {
				$new_attachments[$_current_id] = $_current['guid'];
			}
		}
		
		return $new_attachments;
	}

	/**
	 * Replaces URLs within options and commits them to the database.
	 *
	 * If the parameter is a numerically indexed, non-empty array, it will be
	 * treated as option names, and replacement will be limited to those
	 * options.
	 *
	 * If it is an associative array of option names to values, replacement will
	 * only affect the provided entries, but the changes will be saved in the
	 * options table.
	 *
	 * Otherwise, this function will operate on all autoload options.
	 *
	 * @since 1.0.0
	 * @param array|null $include
	 *        	Options to target.
	 * @return array An associative array of option names and updated option
	 *         values.
	 */
	public function replace_options ( $include = array() ) {
		$options = array();
		$new_options = array();
		
		if ( is_array( $include ) && count( $include ) > 0 ) {
			// $include is an array of option names, go get existing values
			if ( $include === array_values( $include ) ) {
				foreach ( $include as $option_name ) {
					$options[$option_name] = get_option( $option_name );
				}
			} else {
				// it's an associative array of names => values, don't bother
				// fetching anything from the database; just operate on it
				$options = $include;
			}
		} else {
			// get all the options we can work with, and replace on them all
			$options = wp_load_alloptions();
			/*
			 * ^ does not return unserialized options unlike get_option; for
			 * consistency, we unserialize them also means that _replace()
			 * should never actually be called with serialized data by this
			 * replace_options method
			 */
			array_walk( $options, 
					array( 
							$this,
							'_maybe_unserialize' 
					) );
			$options = apply_filters( 'wp_relocate_all_options', $options );
		}
		
		// replace and copy into a new array
		$new_options = $this->_replace( $options );
		foreach ( $options as $option_name => $option_value ) {
			// actually commit the changes
			update_option( $option_name, $new_options[$option_name] );
		}
		return $new_options;
	}

	/**
	 * Replaces the old URL with the new URL recursively.
	 *
	 * Supports strings, iterable objects, arrays, and serialized data.
	 *
	 * @since 1.0.0
	 * @param string|array|object $value
	 *        	a value in which to find and replace
	 * @return mixed supplied value, in the same type as provided
	 */
	public function _replace ( $value ) {
		if ( is_serialized( $value ) ) {
			$unserialized = unserialize( $value );
			$unserialized_new = $this->_replace( $unserialized ); // recurse!
			return serialize( $unserialized_new );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $key => &$val )
				$val = $this->_replace( $val ); // recurse!
			return $value;
		} elseif ( is_object( $value ) ) {
			try {
				$new_object = clone $value;
				foreach ( $value as $key => $val ) {
					$new_object->$key = $this->_replace( $val ); // recurse!
				}
				return $new_object;
			} catch ( Exception $e ) {}
		} elseif ( is_string( $value ) ) {
			return str_replace( $this->old_site_url, $this->new_site_url, 
					$value ); // no more recursion
		}
		
		return $value;
	}

	/**
	 * A callback for unserialization when loading options from the database.
	 *
	 * @since 1.0.0
	 * @param string $value        	
	 * @param mixed $key        	
	 * @uses maybe_unserialize
	 */
	public function _maybe_unserialize ( &$value, $key ) {
		$value = maybe_unserialize( $value );
	}

	/**
	 * Checks if the provided URL can be a site URL.
	 *
	 * This function is particularly picky and will reject anything that isn't
	 * HTTP(S), or that has a query string. It does not check for a trailing
	 * slash because that can be handled by untrailingslashit().
	 *
	 * @since 1.0.0
	 * @param string $url        	
	 * @return boolean
	 * @uses WP_Relocate::is_valid_url
	 */
	public static function is_valid_siteurl ( $url ) {
	    $arr = array();
		$valid = self::is_valid_url( $url ) &&
				 preg_match_all( '/^https?:\/\/[^\?=]*$/', $url, $arr );
		return (boolean) $valid;
	}

	/**
	 * Checks if the provided string is a valid URL.
	 *
	 * @since 1.0.0
	 * @param string $url        	
	 * @return boolean
	 * @uses filter_var
	 */
	public static function is_valid_url ( $url ) {
		if ( empty( $url ) )
			return false;
		return (boolean) filter_var( $url, FILTER_VALIDATE_URL );
	}
}