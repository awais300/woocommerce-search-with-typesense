<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

use Typesense\Client as TypesenseClient;
use Exception;

/**
 * WooCommerceToTypesense class - Handles synchronization of WooCommerce products with Typesense search engine.
 *
 * @package TypesenseSearch
 * @subpackage Integration
 * @since 1.0.0
 */
class WooCommerceToTypesense extends Singleton
{
    use LoggerTrait;

    /**
     * Typesense client instance.
     *
     * @var \Typesense\Client
     */
    private $typesense;

    /**
     * Name of the Typesense collection.
     *
     * @var string
     */
    private $collection_name;

    /**
     * Store options.
     *
     * @var string
     */
    private const WSWT_OPTIONS = '_wswt_awp_options';

    /**
     * Constructor to initialize Typesense client and add hooks.
     */
    public function __construct()
    {
        $this->initialize_log_dir();

        // Add hooks
        add_action('save_post_product', array($this, 'on_product_save'), 10, 3);
        add_action('delete_post', array($this, 'on_product_delete'), 10);
        add_action('wp_trash_post', array($this, 'on_product_trash'), 10);
        add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);
        add_action('untrash_post', array($this, 'on_product_untrash'), 10, 1);

        add_filter('woocommerce_product_data_store_cpt_get_products_query', array($this, 'modify_product_query'), 10, 3);
    }

    /**
     * Connect to the Typesense client with the given configuration and set the collection name.
     *
     * @param array  $typesense_config  Configuration for Typesense client, including:
     *                                  - host: (string) The Typesense server host.
     *                                  - port: (int) The Typesense server port.
     *                                  - protocol: (string) The protocol used (http or https).
     *                                  - api_key: (string) The API key for authentication.
     * @param string $collection_name   The name of the collection to use.
     *
     * @return void
     */
    public function connect_typesense($typesense_config, $collection_name)
    {
        // Initialize Typesense client
        $this->typesense = new TypesenseClient(array(
            'nodes' => array(
                array(
                    'host' => $typesense_config['host'],
                    'port' => $typesense_config['port'],
                    'protocol' => $typesense_config['protocol']
                )
            ),
            'api_key' => $typesense_config['api_key'],
            'connection_timeout_seconds' => 2
        ));

        $this->collection_name = $collection_name;
    }

    /**
     * Create a Typesense collection with the specified schema.
     *
     * @return void
     */
    public function create_collection()
    {
        $schema = array(
            'name' => $this->collection_name,
            'fields' => array(
                array('name' => 'id', 'type' => 'string'),
                array('name' => 'title', 'type' => 'string', 'sort' => true),
                array('name' => 'description', 'type' => 'string', 'optional' => true),
                array('name' => 'short_description', 'type' => 'string', 'optional' => true),
                array('name' => 'price', 'type' => 'float', 'optional' => true, 'sort' => true),
                array('name' => 'categories', 'type' => 'string[]', 'facet' => true),
                array('name' => 'attribute_terms', 'type' => 'string[]', 'facet' => true),
                array('name' => 'auction_dates_from', 'type' => 'int64', 'optional' => true, 'sort' => true, 'facet' => true),
                array('name' => 'auction_dates_to', 'type' => 'int64', 'optional' => true, 'sort' => true, 'facet' => true),
                array('name' => 'auction_start_price', 'type' => 'float', 'optional' => true, 'sort' => true),
                array('name' => 'auction_has_started', 'type' => 'bool', 'optional' => true, 'facet' => true),
                array('name' => 'product_type', 'type' => 'string', 'facet' => true),
                array('name' => 'product_visibility', 'type' => 'string', 'facet' => true),
                array('name' => 'product_image_url', 'type' => 'string', 'optional' => true, 'index' => false),
                array('name' => 'product_image_html', 'type' => 'string', 'optional' => true, 'index' => false),
                array('name' => 'publish_date', 'type' => 'int64', 'sort' => true, 'facet' => true),
            ),
            'default_sorting_field' => 'title'
        );

        try {
            $this->typesense->collections->create($schema);
            $this->log_info(
                sprintf(
                    __("Collection '%s' created successfully.", 'woocommerce-search-with-typesense'),
                    $this->collection_name
                )
            );
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                $this->log_error(
                    sprintf(
                        __("Error creating collection '%s': %s", 'woocommerce-search-with-typesense'),
                        $this->collection_name,
                        $e->getMessage()
                    )
                );
            } else {
                $this->log_info(
                    sprintf(
                        __("Collection '%s' already exists.", 'woocommerce-search-with-typesense'),
                        $this->collection_name
                    )
                );
            }
        }
    }

    /**
     * Synchronize all WooCommerce products with Typesense.
     *
     * @param int $batch_size Number of products to fetch per batch.
     * @return bool
     */
    public function synchronize_products($batch_size = 10, $reindex = false)
    {
        if ($this->collection_exists($this->collection_name) === false) {
            $this->log_info(
                __("Skipping adding documents to collection as collection does not exist.", 'woocommerce-search-with-typesense')
            );
            return false;
        }

        $generator_product = $this->fetch_products_generator($batch_size);
        foreach ($generator_product as $product) {
            try {
                $prepared_product = $this->prepare_product($product);
                $this->add_or_update_product($prepared_product, $reindex);
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
    }

    /**
     * Fetch WooCommerce products in batches using a generator.
     *
     * @param int $batch_size Number of products to fetch per batch.
     * @return \Generator
     */
    public function fetch_products_generator($batch_size = 10)
    {
        $page = 1;

        while (true) {
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

            $products =  wc_get_products($args);

            $this->log_info(sprintf("Fetching page %d with %d products", $page, count($products)));

            if (empty($products)) {
                OptionsManager::indexing_completed();
                $this->log_info(
                    sprintf(
                        __("Yielding completed at: %s", 'woocommerce-search-with-typesense'),
                        date('l, F j Y - H:i:s', time())
                    )
                );
                break;
            }

            foreach ($products as $product) {
                $this->log_info(
                    sprintf(
                        __("Yielding Product: %d", 'woocommerce-search-with-typesense'),
                        $product->get_id()
                    )
                );
                yield $product;
            }

            $page++;
        }
    }

    /**
     * Prepare WooCommerce product data for Typesense.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array
     */
    public function prepare_product($product)
    {
        // Get product categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));

        // Get product attribute terms
        $attribute_terms = array();
        $product_attributes = $product->get_attributes();
        foreach ($product_attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));


                $attribute_terms = array_merge($attribute_terms, $terms);
            }
        }

        $product_data = array(
            'id' => (string) $product->get_id(),
            'title' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => (float) $product->get_price(),
            'categories' => $categories,
            'attribute_terms' => array_unique($attribute_terms),
            'product_type' => $product->get_type(),
            'product_visibility' => $product->get_catalog_visibility(),
            'publish_date' => strtotime($product->get_date_created()),
        );

        // Add auction-specific fields if the product is an auction
        if ($product->get_type() === 'auction') {
            $dates_from = get_post_meta($product->get_id(), '_auction_dates_from', true);
            $dates_to = get_post_meta($product->get_id(), '_auction_dates_to', true);

            $product_data['auction_dates_from'] = $dates_from ? strtotime($dates_from) : null;
            $product_data['auction_dates_to'] = $dates_to ? strtotime($dates_to) : null;
            $product_data['auction_start_price'] = (float) get_post_meta($product->get_id(), '_auction_start_price', true);
            $product_data['auction_has_started'] = (bool) get_post_meta($product->get_id(), '_auction_has_started', true);
        }

        // Add product image fields
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            $image_html = wp_get_attachment_image($image_id, 'thumbnail');

            $product_data['product_image_url'] = $image_url ? $image_url : '';
            $product_data['product_image_html'] = $image_html ? $image_html : '';
        }

        return $product_data;
    }

    /**
     * Add or update a product in Typesense.
     *
     * @param array $product Prepared product data.
     * @param bool $reindex Whether to reindex the product.
     * @return void
     */
    public function add_or_update_product($product, $reindex = false)
    {
        if (OptionsManager::is_indexing_completed() == true && $reindex == false) {
            $this->log_info(__('Return: Indexing is completed.', 'woocommerce-search-with-typesense'));
            return;
        }

        try {
            $this->typesense->collections[$this->collection_name]->documents->create($product);
            PostMetaManager::update_post_meta($product['id'], $this->collection_name, true);
            $message = sprintf(
                __("Product ID %d added/indexed to Typesense.", 'woocommerce-search-with-typesense'),
                $product['id']
            );
            $this->log_info($message);
            $this->log_cli($message);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $this->typesense->collections[$this->collection_name]->documents[$product['id']]->update($product);
                PostMetaManager::update_post_meta($product['id'], $this->collection_name, true);
                $message = sprintf(
                    __("Product ID %d updated/re-indexed in Typesense.", 'woocommerce-search-with-typesense'),
                    $product['id']
                );
                $this->log_info($message);
                $this->log_cli($message);
            } else {
                $message = sprintf(
                    __("Error adding/updating product ID %d: %s", 'woocommerce-search-with-typesense'),
                    $product['id'],
                    $e->getMessage()
                );
                $this->log_error($message);
                $this->log_cli($message);
            }
        }
    }

    /**
     * Remove a product from Typesense.
     *
     * @param int $product_id WooCommerce product ID.
     * @return void
     */
    public function remove_product($product_id)
    {
        try {
            $this->typesense->collections[$this->collection_name]->documents[(string) $product_id]->delete();
            $this->log_info(
                sprintf(
                    __("Product ID %d removed from Typesense.", 'woocommerce-search-with-typesense'),
                    $product_id
                )
            );
        } catch (Exception $e) {
            $this->log_error(
                sprintf(
                    __("Error removing product ID %d from Typesense: %s", 'woocommerce-search-with-typesense'),
                    $product_id,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Checks if a collection exists in Typesense.
     *
     * @param string $collection_name Name of the collection to check.
     * @return bool
     */
    public function collection_exists($collection_name)
    {
        try {
            $this->typesense->collections[$collection_name]->retrieve();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Event handler for product save action.
     *
     * @param int $post_id Post ID.
     * @param WP_Post $post Post object.
     * @param bool $update Whether this is an existing post being updated or not.
     */
    public function on_product_save($post_id, $post, $update)
    {
        // Check if the post is a product
        if ($post->post_type !== 'product') {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        // Add or update the product in Typesense
        $prepared_product = $this->prepare_product($product);
        $this->add_or_update_product($prepared_product, true);
    }

    /**
     * Event handler for product delete action.
     *
     * @param int $post_id Post ID.
     */
    public function on_product_delete($post_id)
    {
        $post = get_post($post_id);

        // Check if the post is a product
        if ($post && $post->post_type === 'product') {
            $this->remove_product($post_id);
        }
    }

    /**
     * Event handler for product trash action.
     *
     * @param int $post_id Post ID.
     */
    public function on_product_trash($post_id)
    {
        $this->remove_product($post_id);
    }

    /**
     * Event handler for post status change action.
     *
     * @param string $new_status New post status.
     * @param string $old_status Old post status.
     * @param WP_Post $post Post object.
     */
    public function on_post_status_change($new_status, $old_status, $post)
    {
        if ($post->post_type !== 'product') {
            return;
        }

        if ($new_status === 'trash') {
            $this->on_product_trash($post->ID);
        } elseif ($old_status === 'trash' && $new_status === 'publish') {
            $product = wc_get_product($post->ID);
            if ($product) {
                $prepared_product = $this->prepare_product($product);
                $this->add_or_update_product($prepared_product, true);
            }
        }
    }

    /**
     * Event handler for product untrash action.
     *
     * @param int $post_id Post ID.
     */
    public function on_product_untrash($post_id)
    {
        $product = wc_get_product($post_id);
        if ($product) {
            $prepared_product = $this->prepare_product($product);
            $this->add_or_update_product($prepared_product, true);
        }
    }

    public function modify_product_query($wp_query_args, $query_vars, $data_store_cpt)
    {
        if (!empty($query_vars['meta_query'])) {
            $wp_query_args['meta_query'][] = $query_vars['meta_query'];
        }
        return $wp_query_args;
    }
}
