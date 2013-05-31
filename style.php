<?php
class Less_Theme_Generator {
	// Flags
	private $compression = false;
	private $debug = false;

	// Files path
	private $folder_name_cache = 'cache';
	private $path_style = '';
	private $path_cache = '';

	// URLs
	private $url_style = '';

	// List my ressources
	private $ressources = array( );

	/**
	 * Constructor
	 */
	function __construct( $ressources = array(), $path_style = '', $url_style = '', $compression = true, $debug = false, $folder_name_cache = 'cache' ) {
		// Dynamic var class
		$this->folder_name_cache = $folder_name_cache;
		$this->path_cache = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $this->folder_name_cache;

		// Params
		$this->ressources = (array)$ressources;
		$this->path_style = empty( $path_style ) ? TEMPLATEPATH : $path_style;
		$this->url_style = empty( $url_style ) ? get_bloginfo( 'template_directory' ) : $url_style;
		$this->compression = (bool)$compression;
		$this->debug = (bool)$debug;

		// Wait init for play with CSS/JS
		if( !is_admin( ) )
			add_action( 'template_redirect', array( &$this, 'execute' ), 1 );
		// Load it before plugin that want redirect/kill wp process

		// This gets the theme name from the stylesheet (lowercase and without spaces)
		if( !function_exists( 'wp_get_theme' ) ) {// For WordPress not in 3.4 version
			// Get the theme data
			$theme = get_theme_data( STYLESHEETPATH . '/style.css' );

			// Get theme name
			$themename = $theme[ 'Name' ];
		} else {// 3.4 Sites
			// 3.4 WP_Theme Object
			$theme = wp_get_theme( );

			// Get theme name
			$themename = $theme->get( 'Name' );
		}

		$themename = preg_replace( "/\W/", "", strtolower( $themename ) );
		add_action( 'update_option_' . $themename, array( &$this, 'flushCache' ) );
	}

	/**
	 * Try to load static CSS or inline
	 */
	function execute( ) {
		global $wpdb;

		// Get last date modification on db and file
		$db_date = (int) get_theme_mod( 'last-modification' );
		$file_date = $this->getLastDateModification( );

		// Cache file ? or inline ?
		if( $this->cacheWritable( ) ) {
			// Build filename
			$old_file = $this->path_cache . '/theme-style-' . $wpdb->blogid . '-' . $db_date . '.css';
			$new_file = $this->path_cache . '/theme-style-' . $wpdb->blogid . '-' . $file_date . '.css';

			// Newer version ?
			if( $file_date > $db_date || !is_file( $new_file ) ) {
				$db_date = $this->refreshCache( $old_file, $new_file, $db_date, $file_date );
			}

			// File exist ? Enqueue ? Otherwise put inline
			if( is_file( $new_file ) && $this->debug == false )
				wp_enqueue_style( 'theme-style', WP_CONTENT_URL . '/' . $this->folder_name_cache . '/theme-style-' . $wpdb->blogid . '-' . $file_date . '.css', array( ), filemtime( WP_CONTENT_DIR . '/' . $this->folder_name_cache . '/theme-style-' . $wpdb->blogid . '-' . $file_date . '.css' ), 'all' );
			else
				add_action( 'wp_head', array( &$this, 'styleInline' ) );
		} else {
			add_action( 'wp_head', array( &$this, 'styleInline' ) );
		}
	}

	/**
	 * Display the CSS on wp_head ! Inline mode !
	 */
	function styleInline( ) {
		echo '<style type="text/css">/* Impossible to read/write cache */' . $this->getCssFromLess( ) . '</style>';
	}

	/**
	 * Get most recent time edited files
	 */
	function getLastDateModification( ) {
		$most_recent_time = 0;

		foreach( $this->ressources as $file ) {
			$current_time = @filemtime( $this->path_style . $file );
			if( $current_time > $most_recent_time ) {
				$most_recent_time = $current_time;
			}
		}

		return (int)$most_recent_time;
	}

	/**
	 * Get CSS code from LESS ressources
	 */
	function getCssFromLess( ) {
		$code_less = '';

		// Get LESS content
		foreach( $this->ressources as $file ) {
			$code_less .= file_get_contents( $this->path_style . $file );
		}

		// Try to replace dynamic base_url
		$code_less = str_replace( '{theme_url}', $this->url_style, $code_less );

		// Allow plugin have a default comportement of less
		$code_less = apply_filters( 'less_before_parse', $code_less );

		// Get lib
		require_once (dirname( __FILE__ ) . '/lib/lessc.inc.php');

		// Build CSS
		$less = new lessc( );
		
		do_action_ref_array('before-less-compile', array(&$less));
		
		if( $this->compression === true ) {
			$less->setFormatter("compressed");
		} else {
			$less->setFormatter("lessjs");
		}
		
		$css = $less->compile( $code_less );

		// Fix bug with IE6-IE7
		$css = str_replace( ' / ', '/', $css );

		return $css;
	}

	/**
	 * Create CSS static file
	 */
	function refreshCache( $old_file = '', $new_file = '', $db_date = 0, $file_date = 0 ) {
		global $cache_path;

		// Write on new file
		$result = file_put_contents( $new_file, $this->getCssFromLess( ) );
		if( $result != false ) {
			// Delete old file
			if( is_file( $old_file ) && $db_date != $file_date ) {
				@unlink( $old_file );
			}

			// Save new time
			set_theme_mod( 'last-modification', $file_date );

			// Try to clean WP Super Cache when Less changed and cache is recompile
			if( function_exists( 'prune_super_cache' ) ) {
				prune_super_cache( $cache_path, true );
			}

			// Try to clean Hyper Cache when Less changed and cache is recompile
			if( function_exists( 'hyper_delete_path' ) ) {
				hyper_delete_path( WP_CONTENT_DIR . '/cache/hyper-cache' );
			}

			return $file_date;
		}

		return $db_date;
	}

	/**
	 * Try to create the cache folder, or check if exist and writable !
	 */
	function cacheWritable( ) {
		// Folder cache exist ?
		if( !is_dir( $this->path_cache ) )
			mkdir( $this->path_cache, 0777, true );

		// Try to update chmod
		if( !is_writable( $this->path_cache ) )
			chmod( $this->path_cache, 0777 );

		return is_writable( $this->path_cache );
	}

	/**
	 * Remove theme mod
	 */
	function flushCache( ) {
		global $wpdb;

		$db_date = (int) get_theme_mod( 'last-modification' );

		// Cache file exist ?
		if( is_file( $this->path_cache . '/theme-style-' . $wpdb->blogid . '-' . $db_date . '.css' ) ) {
			@unlink( $this->path_cache . '/theme-style-' . $wpdb->blogid . '-' . $db_date . '.css' );
		}

		remove_theme_mod( 'last-modification' );
	}

}
