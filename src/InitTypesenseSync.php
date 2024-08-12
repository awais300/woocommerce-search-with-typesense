<?php

namespace AWP\TypesenseSearch;

use Typesense\Client as TypesenseClient;
use AWP\TypesenseSearch\Admin\TypesenseSettings;

use Exception;

defined('ABSPATH') || exit;

/**
 * InitTypesenseSync Class
 *
 * This class initializes the configuration for Typesense and sets up synchronization
 * of WooCommerce products with Typesense.
 *
 * @package TypesenseSearch
 * @subpackage InitTypesenseSync
 * @since 1.0.0
 */
class InitTypesenseSync extends Singleton
{
    use LoggerTrait;

    /**
     * WooCommerceToTypesense instance.
     *
     * @var WooCommerceToTypesense
     */
    private $wc_to_typesense;


    private $collection_name;

    private $typesense_client;

    private $force_reindex = false;


    public const BATCH_SIZE = 10;


    /**
     * init to initialize Typesense configuration and WooCommerceToTypesense instance.
     *
     * This constructor sets up the Typesense configuration and creates an instance
     * of the WooCommerceToTypesense class. It then calls the setup_and_sync method
     * to setup the Typesense collection and synchronize products.
     */
    public function init()
    {
        $this->initialize_log_dir();
        $connection = $this->test_connection($this->get_typesense_config());
        if ($connection === true) {
            $this->set_collection_name('comics');
            $this->wc_to_typesense = WooCommerceToTypesense::get_instance();
            $this->wc_to_typesense->connect_typesense($this->get_typesense_config(), $this->get_collection_name());
            $this->setup_and_sync();
        } else {
            echo 'Typesense connection error: ' . $connection->getMessage();
            exit;
        }
    }

    public function get_collection_name()
    {
        $name = null;

        if (in_array(wp_get_environment_type(), array('staging', 'local'))) {
            $name =  'comics';
        }

        if ('production' === wp_get_environment_type()) {
            $name = 'comics_live';
        }

        $this->collection_name = $name;

        return $name;
    }


    public function set_collection_name($collection_name)
    {
        if (empty($collection_name)) {
            throw new Exception('Collection name can not be empty');
        }

        $this->collection_name = $collection_name;
    }

    public function delete_collection($collection_name)
    {
        $this->initialize_log_dir();
        try {
            $this->test_connection($this->get_typesense_config());
            $client = $this->typesense_client;
            $client->collections[$collection_name]->delete();
            $message = __("Collection deleted successfully", 'woocommerce-search-with-typesense');

            $this->log_error($message);
            $this->log_cli($message);
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            $message = sprintf(
                __("Collection not found %s", 'woocommerce-search-with-typesense'),
                $e->getMessage()
            );
            $this->log_error($message);
            $this->log_cli($message);
        } catch (\Typesense\Exceptions\TypesenseClientError $e) {
            $message = sprintf(
                __("Error deleting collection %s", 'woocommerce-search-with-typesense'),
                $e->getMessage()
            );
            $this->log_error($message);
            $this->log_cli($message);
        }
    }


    public function set_force_reindex(bool $force_reindex)
    {
        $this->force_reindex = $force_reindex;
    }

    /**
     * Setup Typesense collection and synchronize products.
     *
     * This method creates the Typesense collection and synchronizes WooCommerce products
     * with the Typesense collection.
     *
     * @return void
     */
    public function setup_and_sync()
    {
        $this->wc_to_typesense->create_collection();
        $this->wc_to_typesense->synchronize_products(self::BATCH_SIZE, $this->force_reindex);
    }

    /**
     * Get the default Typesense configuration.
     *
     * @return array Returns an array containing the default Typesense configuration:
     *               - host: (string) The Typesense server host.
     *               - port: (string) The Typesense server port.
     *               - protocol: (string) The protocol used (http or https).
     *               - api_key: (string) The API key for authentication.
     */
    public function get_typesense_config()
    {
        return [
            'host' => get_option(TypesenseSettings::HOST, '192.168.10.20'),
            'port' => get_option(TypesenseSettings::PORT, '8108'),
            'protocol' => get_option(TypesenseSettings::PROTOCOL, 'http'),
            'api_key' => get_option(TypesenseSettings::API_KEY, 'xyz')
        ];
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
     * @return bool|Exception
     */
    public function test_connection($typesense_config)
    {
        try {
            // Initialize Typesense client
            $client = new TypesenseClient([
                'nodes' => [
                    [
                        'host' => $typesense_config['host'],
                        'port' => $typesense_config['port'],
                        'protocol' => $typesense_config['protocol']
                    ]
                ],
                'api_key' => $typesense_config['api_key'],
                'connection_timeout_seconds' => 2
            ]);

           /* $this->typesense_client = $client;

            $this->delete_collection($this->get_collection_name());
            exit;*/


            /*$collection_name = $this->get_collection_name(); // Replace with the correct collection name
            $document_id = '520359'; // The ID of the document you want to retrieve

            if (!empty($collection_name) && !empty($document_id)) {
                try {
                    $document = $client->collections[$collection_name]->documents[$document_id]->retrieve();
                    print_r($document);
                } catch (\Typesense\Exceptions\ObjectNotFound $e) {
                    echo "Document not found: " . $e->getMessage();
                } catch (\Exception $e) {
                    echo "An error occurred: " . $e->getMessage();
                }
            } else {
                echo "Collection name or Document ID is missing!";
            }*/

            //exit;

            /*$response = $client->collections['comics']->delete();
            dd($response);
            exit;*/

            /*dd(OptionsManager::get_option());
            dd(OptionsManager::delete_option());
            dd(OptionsManager::get_option());
            exit;*/

            // Attempt to list collections
            $collections = $client->collections->retrieve();

            //dd($collections);

            //Connection is working.
            return true;
        } catch (Exception $e) {
            return $e;
        }
    }

    public function perform_search_with_scoped_api_key($limit_hits, $additional_params = [])
    {
        try {
            $connection = $this->test_connection($this->get_typesense_config());
            if ($connection !== true) {
                throw new Exception("Failed to connect to Typesense");
            }

            $api_key = $this->get_typesense_config()['api_key'];

            // Define the search parameters
            $search_parameters = array_merge([
                'limit_hits' => $limit_hits,

            ], $additional_params);

            // Generate the scoped API key
            $scoped_api_key = $this->typesense_client->keys->generateScopedSearchKey($api_key, $search_parameters);

            return $scoped_api_key;
        } catch (Exception $e) {
            // Log the error or handle it as appropriate for your application
            error_log("Error generating scoped API key: " . $e->getMessage());
            return null;
        }
    }

    public function get_non_index_products_count($collection_name = '')
    {
        if (empty($collection_name)) {
            $collection_name = $this->get_collection_name();
        }

        \WP_CLI::success('Typesense collection name: ' . $collection_name  . '. Records will be indexed againt this collction name');

        global $wpdb;
        $meta_key = $collection_name . PostMetaManager::COLLECTION_KEY;

        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_id IS NULL",
            $meta_key
        );

        /*echo $query;
        exit;*/

        $count = $wpdb->get_var($query);
        return (int) $count;
    }
}
