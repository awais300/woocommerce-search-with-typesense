<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

use Exception;


/**
 * AjaxIndexing class - Handles all Ajax operations for WooCommerce to Typesense product indexing.
 *
 * This class manages the Ajax requests for indexing WooCommerce products into Typesense.
 * It provides methods for fetching unindexed products, synchronizing them with Typesense,
 * and handling the overall indexing process.
 *
 * @package TypesenseSearch
 * @subpackage Ajax
 * @since 1.0.0
 */
class AjaxIndexing extends Singleton
{
    use LoggerTrait;

    /**
     * Instance of WooCommerceToTypesense for interacting with Typesense.
     *
     * @var WooCommerceToTypesense
     */
    private $wc_to_typesense;
    private $typesense_init;

    /**
     * Name of the Typesense collection.
     *
     * @var string
     */
    private $collection_name;

    /**
     * Constructor for the AjaxIndexing class.
     *
     * @param WooCommerceToTypesense $wc_to_typesense Instance of WooCommerceToTypesense.
     * @param string $collection_name Name of the Typesense collection.
     */
    public function __construct()
    {

        $this->initialize_log_dir();

        $this->typesense_init = InitTypesenseSync::get_instance();
        $this->collection_name = $this->typesense_init->get_collection_name();

        $this->wc_to_typesense = WooCommerceToTypesense::get_instance();
        $this->wc_to_typesense->connect_typesense($this->typesense_init->get_typesense_config(), $this->collection_name);

        add_action('wp_ajax_index_products', array($this, 'ajax_index_products'));
        add_action('wp_ajax_force_reindex_products', array($this, 'ajax_force_reindex_products'));
    }

    /**
     * Handle Ajax request to force reindex all products.
     *
     * This method initiates a force reindex of all products, regardless of their current index status.
     *
     * @return void
     */
    public function ajax_force_reindex_products()
    {
        check_ajax_referer('force_reindex_products_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'woocommerce-search-with-typesense')), 403);
        }

        // Reset the indexing status. This need to run only once.
        OptionsManager::reset_indexing_status($this->collection_name);

        // Start the reindexing process
        $this->ajax_index_products(true);
    }

    /**
     * Handle Ajax request to index products.
     *
     * @param bool $force_reindex Whether to force reindex all products.
     * @return void
     */
    public function ajax_index_products($force_reindex = false)
    {
        if (!$force_reindex) {
            check_ajax_referer('index_products_nonce', 'security');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Unauthorized', 'woocommerce-search-with-typesense')), 403);
            }
        }

        $this->wc_to_typesense->create_collection();
        $response = $this->wc_to_typesense->synchronize_products_ajax(10, $force_reindex);
        $response = array_merge($response, ['total_count' => $this->typesense_init->get_non_index_products_count($this->collection_name)]);
        wp_send_json($response);
    }
}
