<?php

namespace AWP\TypesenseSearch;

use Typesense\Client as TypesenseClient;
use Exception;

defined('ABSPATH') || exit;

/**
 * Class WCTypesenseLiveSearch
 *
 * Bootstraps the live search functionality for WooCommerce using Typesense.
 *
 * @package AWP\TypesenseSearch
 * @subpackage Live Search
 * @since 1.0.0
 */
class WCTypesenseLiveSearch
{
    use LoggerTrait;

    /**
     * @var WooCommerceToTypesense Instance of WooCommerceToTypesense.
     */
    public $typesense;

    /**
     * @var InitTypesenseSync Instance of InitTypesenseSync.
     */
    private $typesense_init;

    /**
     * Constructor.
     *
     * Initializes the live search functionality and sets up necessary connections.
     */
    public function __construct()
    {
        $this->initialize_log_dir();

        $this->typesense_init = InitTypesenseSync::get_instance();
        $connection = $this->typesense_init->test_connection($this->typesense_init->get_typesense_config());

        if ($connection === true) {
            $this->connect_typesense($this->typesense_init->get_typesense_config());
            $this->add_actions();
        } else {
            $this->log_error('Typesense connection error: ' . $connection->getMessage());
        }
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
    public function connect_typesense($typesense_config)
    {
        // Initialize Typesense client
        $this->typesense = new TypesenseClient([
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
    }

    /**
     * Adds necessary WordPress actions for AJAX search.
     */
    private function add_actions()
    {
        add_action('wp_ajax_wc_live_search', array($this, 'perform_search'));
        add_action('wp_ajax_nopriv_wc_live_search', array($this, 'perform_search'));
    }

    /**
     * Performs the search operation using Typesense.
     *
     * Handles the AJAX request for live search.
     */
    public function perform_search()
    {
        // Check nonce for security
        if (!check_ajax_referer('wc_live_search_nonce', 'security', false)) {
            wp_die('Invalid security token sent.');
            return;
        }

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        if (empty($query)) {
            return;
        }

        $search_parameters = [
            'q'         => $query,
            'query_by'  => 'title,description,short_description',
            'per_page'  => 5
        ];

        /*$searchParameters = [
            'filter_by' => 'categories:["Category1", "Category2"] && attribute_terms:["Term1", "Term2"]'
        ];*/
        //This would find products that are in either "Category1" or "Category2" AND have either "Term1" or "Term2" as attribute terms.

        //$collections = $this->typesense->collections['comics'];

        //dd($collections);

        try {
            /*if (!isset($this->typesense->collections['comics'])) {
                $this->log_error('Typesense collection "comics" is not set.');
                echo '<p>Typesense collection "comics" is not set.</p>';
                exit();
            }*/

            $search_results = $this->typesense->collections['comics']->documents->search($search_parameters);
            if (!empty($search_results['hits'])) {
                $html = $this->render_search_results($search_results['hits']);
                echo $html;
            } else {
                echo '<p>No results found.</p>';
            }
        } catch (Exception $e) {
            $this->log_error('Typesense search error: ' . $e->getMessage());
            echo '<p>Typesense search error: ' . $e->getMessage() . '</p>';
        }

        exit();
    }

    /**
     * Renders the search results.
     *
     * @param array $hits The search results from Typesense.
     * @return string HTML output of search results.
     */
    private function render_search_results($hits)
    {
        ob_start();
        foreach ($hits as $result) {
            $product = wc_get_product($result['document']['id']);
            if ($product) {
?>
                <div class="live-search-result">
                    <a href="<?php echo esc_url($product->get_permalink()); ?>">
                        <img src="<?php echo esc_url(get_the_post_thumbnail_url($result['document']['id'], 'thumbnail')); ?>" alt="<?php echo esc_attr($result['document']['title']); ?>">
                        <div class="product-details">
                            <div class="product-title"><?php echo esc_html($result['document']['title']); ?></div>
                            <div class="product-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
                        </div>
                    </a>
                </div>
<?php
            }
        }
        return ob_get_clean();
    }
}
