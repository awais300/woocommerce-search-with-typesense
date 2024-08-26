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
        $this->typesense_init->set_collection_name('comics');
        $this->collection_name = $this->typesense_init->get_collection_name();

        // delete collection
        /*$this->typesense_init->delete_collection($this->collection_name);
        exit;*/

        $this->wc_to_typesense = WooCommerceToTypesense::get_instance();
        $this->wc_to_typesense->connect_typesense($this->typesense_init->get_typesense_config(), $this->collection_name);

        add_filter('woocommerce_product_data_store_cpt_get_products_query', array($this, 'modify_product_query'), 10, 3);


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

    /**
     * Synchronize a batch of products with Typesense.
     *
     * @param int $batch_size Number of products to process in this batch.
     * @param bool $force_reindex Whether to force reindex all products.
     * @return array An array of product IDs that were successfully indexed.
     */
    private function ajax_synchronize_products($batch_size = 10, $force_reindex = false)
    {
        if (!$this->wc_to_typesense->collection_exists($this->collection_name)) {
            $this->log_info(
                __("Skipping adding documents to collection as collection does not exist.", 'woocommerce-search-with-typesense')
            );
            return array();
        }

        $products = $this->ajax_fetch_products($batch_size);

        if (empty($products)) {
            return array();
        }

        $indexed_products = array();

        foreach ($products as $product) {
            try {
                $prepared_product = $this->wc_to_typesense->prepare_product($product);
                $this->wc_to_typesense->add_or_update_product($prepared_product, $force_reindex);
                $indexed_products[] = $product->get_id();
            } catch (Exception $e) {
                $this->log_error(
                    sprintf(
                        __("Error processing product ID %d: %s", 'woocommerce-search-with-typesense'),
                        $product->get_id(),
                        $e->getMessage()
                    )
                );
            }
        }

        return $indexed_products;
    }

    /**
     * Fetch a batch of products to be indexed.
     *
     * @param int $batch_size Number of products to fetch.
     * @return array An array of WooCommerce product objects.
     */
    private function ajax_fetch_products($batch_size = 10)
    {
        $args = array(
            'limit' => $batch_size,
            'status' => 'publish',
        );

        $key = $this->collection_name . PostMetaManager::COLLECTION_KEY;
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => $key,
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => $key,
                'value' => '1',
                'compare' => '!='
            )
        );


        return wc_get_products($args);
    }

    /**
     * Get the total count of products to be indexed.
     *
     * @return int The total number of products to be indexed.
     */
    private function get_total_products()
    {
        global $wpdb;

        $args = array(
            'limit' => -1,
            'status' => 'publish',
            'fields' => 'ids'
        );

        $key = $this->collection_name . PostMetaManager::COLLECTION_KEY;
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => $key,
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => $key,
                'value' => '1',
                'compare' => '!='
            )
        );

        $products = wc_get_products($args);
        return count($products);
    }

    public function modify_product_query($wp_query_args, $query_vars, $data_store_cpt)
    {
        if (!empty($query_vars['meta_query'])) {
            $wp_query_args['meta_query'][] = $query_vars['meta_query'];
        }
        return $wp_query_args;
    }
}
