<?php

namespace AWP\TypesenseSearch;

use AWP\TypesenseSearch\Admin\TypesenseSettings;

defined('ABSPATH') || exit;

/**
 * Class Bootstrap the plugin.
 * 
 * @package TypesenseSearch
 * @subpackage Bootstrap
 * @since 1.0.0
 */
class Bootstrap
{

	private $version = '1.0.0';

	/**
	 * Instance to call certain functions globally within the plugin.
	 *
	 * @var instance
	 */
	protected static $instance = null;

	/**
	 * Construct the plugin.
	 */
	public function __construct()
	{
		add_action('init', array($this, 'load_plugin'), 0);
	}

	/**
	 * Main Bootstrap instance.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
	 * @return self Main instance.
	 */
	public static function get_instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin()
	{
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define WC Constants.
	 */
	private function define_constants()
	{
		// Path related defines
		$this->define('WSWT_CUST_PLUGIN_FILE', WSWT_CUST_PLUGIN_FILE);
		$this->define('WSWT_CUST_PLUGIN_BASENAME', plugin_basename(WSWT_CUST_PLUGIN_FILE));
		$this->define('WSWT_CUST_PLUGIN_DIR_PATH', untrailingslashit(plugin_dir_path(WSWT_CUST_PLUGIN_FILE)));
		$this->define('WSWT_CUST_PLUGIN_DIR_URL', untrailingslashit(plugins_url('/', WSWT_CUST_PLUGIN_FILE)));
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks()
	{
		add_action('init', array($this, 'load_textdomain'));
		add_action('init', array($this, 'init'), 1);

		add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'));
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
	}

	/**
	 * Localisation.
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain('woocommerce-search-with-typesense', false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}

	/**
	 * Initialize the plugin.
	 */
	public function init()
	{
		new TypesenseSettings();
		new TypesenseCLI();
		new AjaxIndexing();
		//new WCTypesenseLiveSearch();
	}

	/**
	 * Enqueue all styles.
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style('wswt-frontend', WSWT_CUST_PLUGIN_DIR_URL . '/assets/css/wswt-frontend.css', array(), null, 'all');
	}

	/**
	 * Enqueue all scripts.
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('wswt-frontend', WSWT_CUST_PLUGIN_DIR_URL . '/assets/js/wswt-frontend.js', array('jquery'), '1.0', true);
        wp_localize_script('wswt-frontend', 'wc_live_search_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_live_search_nonce'),
        ));
	}

	/**
	 * Enqueue all admin styles.
	 */
	public function admin_enqueue_styles()
	{
		wp_enqueue_style('wswt-backend', WSWT_CUST_PLUGIN_DIR_URL . '/assets/css/wswt-backend.css', array(), null, 'all');
	}

	/**
	 * Enqueue all admin scripts.
	 */
	public function admin_enqueue_scripts()
	{
		wp_enqueue_script('wswt-backend', WSWT_CUST_PLUGIN_DIR_URL . '/assets/js/wswt-backend.js', array('jquery'));
		wp_localize_script('wswt-backend', 'TS_LOCAL', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'test_typesense_connection_nonce' => wp_create_nonce('test_typesense_connection_nonce'),
            'index_products_nonce' => wp_create_nonce('index_products_nonce'),
            'force_reindex_products_nonce' => wp_create_nonce('force_reindex_products_nonce'),
        ));
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string $name
	 * @param  string|bool $value
	 */
	public function define($name, $value)
	{
		if (!defined($name)) {
			define($name, $value);
		}
	}
}
