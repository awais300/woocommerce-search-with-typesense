<?php

namespace AWP\TypesenseSearch\Admin;

use AWP\TypesenseSearch\TemplateLoader;
use AWP\TypesenseSearch\InitTypesenseSync;

defined('ABSPATH') || exit;

/**
 * Class TypesenseSettings
 *
 * Manages the settings for the Typesense Search plugin.
 */
class TypesenseSettings
{
    // Class constant definitions for settings keys
    const ENABLE_LOGGING = 'awp_typesense_enable_logging';
    const HOST = 'awp_typesense_host';
    const PORT = 'awp_typesense_port';
    const PROTOCOL = 'awp_typesense_protocol';
    const API_KEY = 'awp_typesense_api_key';

    /**
     * @var TemplateLoader The template loader instance.
     */
    private $template_loader;

    /**
     * @var array The settings array.
     */
    private $settings;

    /**
     * TypesenseSettings constructor.
     *
     * Initializes the class and sets up the necessary actions and filters.
     */
    public function __construct()
    {
        $this->template_loader = TemplateLoader::get_instance();
        $this->settings = $this->define_settings();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_save_typesense_settings', array($this, 'save_settings'));
        add_action('wp_ajax_test_typesense_connection', array($this, 'test_connection'));
        add_action('wp_ajax_index_products', array($this, 'index_products'));
    }

    /**
     * Define the settings for the Typesense plugin.
     *
     * @return array The settings array.
     */
    private function define_settings()
    {
        return [
            self::ENABLE_LOGGING => [
                'type' => 'checkbox',
                'default' => false,
                'label' => __('Enable Logging', 'woocommerce-search-with-typesense')
            ],
            self::HOST => [
                'type' => 'text',
                'default' => '',
                'label' => __('Typesense Host', 'woocommerce-search-with-typesense')
            ],
            self::PORT => [
                'type' => 'text',
                'default' => '',
                'label' => __('Typesense Port', 'woocommerce-search-with-typesense')
            ],
            self::PROTOCOL => [
                'type' => 'text',
                'default' => '',
                'label' => __('Typesense Protocol', 'woocommerce-search-with-typesense')
            ],
            self::API_KEY => [
                'type' => 'password',
                'default' => '',
                'label' => __('Typesense API Key', 'woocommerce-search-with-typesense')
            ]
        ];
    }

    /**
     * Add the Typesense Search admin menu to the WordPress dashboard.
     *
     * @return void
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Typesense Search', 'woocommerce-search-with-typesense'),
            __('Typesense Search', 'woocommerce-search-with-typesense'),
            'manage_options',
            'typesense-search',
            array($this, 'render_settings_page'),
            'dashicons-search'
        );
    }

    /**
     * Render the settings page for the Typesense Search plugin.
     *
     * @return void
     */
    public function render_settings_page()
    {
        $data = [
            'settings' => $this->settings,
            'values' => $this->get_all_settings(),
            'nonce' => wp_create_nonce('typesense_settings_nonce'),
            'test_connection_nonce' => wp_create_nonce('test_typesense_connection_nonce')
        ];

        $this->template_loader->get_template(
            'typesense-settings.php',
            $data,
            WSWT_CUST_PLUGIN_DIR_PATH . '/templates/admin/',
            true
        );
    }

    /**
     * Save the settings for the Typesense Search plugin.
     *
     * This method checks user permissions, verifies the nonce, and updates the settings in the database.
     *
     * @return void
     */
    public function save_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'woocommerce-search-with-typesense'));
        }

        check_admin_referer('typesense_settings_nonce');

        foreach ($this->settings as $key => $setting) {
            switch ($setting['type']) {
                case 'checkbox':
                    update_option($key, isset($_POST[$key]) ? 1 : 0);
                    break;
                case 'text':
                case 'password':
                    update_option($key, sanitize_text_field($_POST[$key]));
                    break;
                    // Add more cases for different input types if needed
            }
        }

        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=typesense-search')));
        exit;
    }

    /**
     * Retrieve all settings for the Typesense Search plugin.
     *
     * @return array An associative array of settings values, with defaults applied where necessary.
     */
    public function get_all_settings()
    {
        $values = [];
        foreach ($this->settings as $key => $setting) {
            $values[$key] = get_option($key, $setting['default']);
        }
        return $values;
    }

    /**
     * Test the connection to the Typesense server via AJAX.
     *
     * This method checks user permissions and verifies the nonce before attempting to connect.
     * It sends a JSON response indicating the success or failure of the connection test.
     *
     * @return void
     */
    public function test_connection()
    {
        check_ajax_referer('test_typesense_connection_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'woocommerce-search-with-typesense'));
        }

        $typesense_init = InitTypesenseSync::get_instance();
        $connection = $typesense_init->test_connection($typesense_init->get_typesense_config());

        if ($connection === true) {
            wp_send_json_success(__('Connection successful', 'woocommerce-search-with-typesense'));
        } else {
            wp_send_json_error(__('Connection failed: ', 'woocommerce-search-with-typesense') . $connection->getMessage());
        }
    }
}
