<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

use \Exception;

class Search
{
    public const PER_PAGE = 48;

    public $seller_search = false;

    public function __construct()
    {
        add_action('wp_ajax_typesense_search', [$this, 'handle_typesense_search']);
        add_action('wp_ajax_nopriv_typesense_search', [$this, 'handle_typesense_search']);
        add_action('wp_footer', array($this, 'add_footer_content'));
    }

    public function add_footer_content()
    {
        echo '<div id="loading-overlay" style="display: none;">
                <div class="spinner"></div>
            </div>';
    }

    public function handle_typesense_search()
    {
        /* if (!function_exists('WC')) {
            require_once(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
        }*/
        // Initialize WooCommerceToTypesense
        $wc_to_typesense = WooCommerceToTypesense::get_instance();
        $typesense_init = InitTypesenseSync::get_instance();

        // Get search parameters
        $query = isset($_POST['search_query']) ? sanitize_text_field($_POST['search_query']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $attributes = isset($_POST['attributes']) ? $_POST['attributes'] : array();
        $auctions_only = isset($_POST['auctions_only']) ? filter_var($_POST['auctions_only'], FILTER_VALIDATE_BOOLEAN) : false;
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : '';
        $paged = isset($_POST['cur_page']) ? intval($_POST['cur_page']) : 1;

        // Construct Typesense search parameters
        $search_parameters = [
            'q'         => $query,
            'query_by'  => 'title,description,short_description',
            'filter_by' => 'product_visibility:!=hidden && stock_status:!=outofstock',
            'facet_by'  => 'categories,attribute_terms,product_type,product_visibility,stock_status,author',
            'sort_by' => 'publish_date:desc',
            'per_page' => self::PER_PAGE,
            'page' => $paged,
            'include_fields' => 'id'
        ];

        // Add author filter if provided
        if (isset($_POST['seller_id']) && !empty($_POST['seller_id'])) {
            $this->seller_search = true;
            $author = sanitize_text_field($_POST['seller_id']);
            $search_parameters['filter_by'] .= " && author:=$author";
        }

        // Add category filter
        if (!empty($category) && $category !== 'Category') {
            $search_parameters['filter_by'] .= " && categories:=[" . $category . "]";
        }

        // Add attribute filters
        $attribute_filters = [];
        foreach ($attributes as $attribute => $values) {
            if (is_array($values) && !empty($values)) {
                $attribute_filters[] = "attribute_terms:=[" . implode(',', array_map('sanitize_text_field', $values)) . "]";
            } elseif (!empty($values)) {
                $attribute_filters[] = "attribute_terms:=" . sanitize_text_field($values);
            }
        }
        if (!empty($attribute_filters)) {
            if (!empty($search_parameters['filter_by'])) {
                $search_parameters['filter_by'] .= ' && ';
            }
            $search_parameters['filter_by'] .= implode(' && ', $attribute_filters);
        }

        // Add auctions only filter
        if ($auctions_only) {
            if (!empty($search_parameters['filter_by'])) {
                $search_parameters['filter_by'] .= ' && ';
            }
            $search_parameters['filter_by'] .= 'product_type:=auction';
        }

        // Add sorting
        $today_range = $this->get_today_range_in_utc(wp_timezone_string());

        $today_start = $today_range['start'];
        $today_end = $today_range['end'];

        /*$today_start = strtotime('today midnight');
        $today_end = strtotime('tomorrow midnight') - 1;*/

        if (!empty($orderby)) {
            switch ($orderby) {
                case 'price':
                    $search_parameters['sort_by'] = 'price:asc';
                    break;
                case 'price-desc':
                    $search_parameters['sort_by'] = 'price:desc';
                    break;
                case 'alpha_a':
                    $search_parameters['sort_by'] = 'title:asc';
                    break;
                case 'alpha_z':
                    $search_parameters['sort_by'] = 'title:desc';
                    break;
                case 'auction_started':
                    $search_parameters['filter_by'] .= (!empty($search_parameters['filter_by']) ? ' && ' : '') . "auction_dates_from:[$today_start..$today_end]";
                    $search_parameters['sort_by'] = 'auction_dates_from:asc';
                    break;
                case 'auction_end':
                    $search_parameters['filter_by'] .= (!empty($search_parameters['filter_by']) ? ' && ' : '') . "auction_dates_to:[$today_start..$today_end]";
                    $search_parameters['sort_by'] = 'auction_dates_to:asc';
                    break;
                default:
                    $search_parameters['sort_by'] = 'title:asc';
                    break;
            }
        }

        try {


            $results = $wc_to_typesense->typesense->collections[$wc_to_typesense->collection_name]->documents->search($search_parameters);
            $posts = $this->convert_typesense_to_wp_posts($results);

            ob_start();

            // Vendor
            if ($this->seller_search == true) {
                echo '<div class="product_area">';
                echo '<div id="products-wrapper" class="products-wrapper cd-gallery">';
            }

            if (!empty($results['hits'])) {
                wc_setup_loop([
                    'name'         => 'product',
                    'is_search'    => true,
                    'is_filtered'  => true,
                    'total'        => $results['found'],
                    'total_pages'  => ceil($results['found'] / self::PER_PAGE),
                    'per_page'     => self::PER_PAGE,
                    'current_page' => $paged
                ]);

                do_action('woocommerce_before_shop_loop');

                echo str_replace('columns-4', 'columns-6', woocommerce_product_loop_start(false));

                $total_products = $results['found'];

                $search_title = $query;
                if (!empty($search_title)) {
                    $search_title = ' | Showing results for: ' . $search_title;
                }
?>

                <?php if ($this->seller_search == false) { ?>
                    <li class="total-product-count" id="mbf_products_count">
                        <p>Total listings: <?php echo number_format($total_products) . $search_title ?></p>
                    </li>
                <?php } ?>
<?php
                global $post, $product;
                $found_ids = array();
                foreach ($posts as $prod) {
                    $post = $prod;
                    $found_ids[] = $post->ID;
                    $product = wc_get_product($post->ID);
                    setup_postdata($post);
                    wc_get_template_part('content', 'product');
                }


                woocommerce_product_loop_end();

                // Custom pagination for Typesense results
                $this->generate_pagination_ajax($total_products, $search_parameters['per_page'], $paged);

                //do_action('woocommerce_after_shop_loop');
            } else {
                wc_get_template('loop/no-products-found.php');
            }

            if ($this->seller_search == true) {
                echo '</div></div';
            }

            $html = ob_get_clean();

            wp_send_json_success(array(
                'html' => $html,
                'found_ids' => $found_ids,
                'total' => $results['found'],
                'total_pages' => ceil($results['found'] / $search_parameters['per_page']),
                'current_page' => $paged
            ));
        } catch (Exception $e) {
            wp_send_json_error('An error occurred while performing the search: ' . $e->getMessage());
        }

        wp_die();
    }

    public function get_today_range_in_utc($timezone_string)
    {
        // Create DateTimeZone objects
        $local_timezone = new \DateTimeZone($timezone_string);
        $utc_timezone = new \DateTimeZone('UTC');

        // Create DateTime objects for start and end of today in local time
        $today_start = new \DateTime('today midnight', $local_timezone);
        $today_end = new \DateTime('tomorrow midnight', $local_timezone);
        $today_end->modify('-1 second');

        // Convert to UTC
        $today_start->setTimezone($utc_timezone);
        $today_end->setTimezone($utc_timezone);

        // Return timestamps
        return [
            'start' => $today_start->getTimestamp(),
            'end' => $today_end->getTimestamp()
        ];
    }

    public function generate_pagination_ajax($total_products, $per_page, $current_page)
    {
        $total_pages = ceil($total_products / $per_page);

        $pagination_args = array(
            'base' => '#', // We'll handle the URL in JavaScript
            'format' => '',
            'current' => max(1, $current_page),
            'total' => $total_pages,
            'prev_text' => __('←'),
            'next_text' => __('→'),
            'type' => 'array',
            'mid_size' => 2,
            'end_size' => 1,
        );

        $pagination_links = paginate_links($pagination_args);

        if ($pagination_links) {
            echo '<nav class="woocommerce-pagination ajax">';
            echo '<ul class="page-numbers">';
            foreach ($pagination_links as $link) {
                // Add data-page attribute to pagination links
                $link = preg_replace('/<a([^>]*)>(.*?)<\/a>/', '<a$1 data-page="$2">$2</a>', $link);
                $link = preg_replace('/<span([^>]*)>(.*?)<\/span>/', '<span$1 data-page="$2">$2</span>', $link);
                echo '<li>' . $link . '</li>';
            }
            echo '</ul>';
            echo '</nav>';
        }
    }

    public function search_ts()
    {
        $paged = isset($_GET['cur_page']) ? intval($_GET['cur_page']) : 1;
        $search_query = get_search_query();

        $category = isset($_GET['product_cat']) ? sanitize_text_field($_GET['product_cat']) : '';

        $wc_to_typesense = WooCommerceToTypesense::get_instance();

        $search_parameters = [
            'q'         => $search_query,
            'query_by'  => 'title,description,short_description',
            'filter_by' => 'product_visibility:!=hidden && stock_status:!=outofstock',
            'facet_by'  => 'categories,product_type,product_visibility,stock_status,author',
            'sort_by' => 'publish_date:desc',
            'per_page'  => self::PER_PAGE,
            'page' => $paged,
            'include_fields' => 'id'
        ];

        if (!empty($category)) {
            $search_parameters['filter_by'] .= " && categories:=$category";
        }

        // Add author filter if provided
        if ($seller_id = $this->get_seller_id()) {
            $author = $seller_id;
            $search_parameters['filter_by'] .= " && author:=$author";
        }

        $results = $wc_to_typesense->typesense->collections[$wc_to_typesense->collection_name]->documents->search($search_parameters);

        return $results;
    }


    public function convert_typesense_to_wp_posts($results)
    {
        $posts = array();
        foreach ($results['hits'] as $hit) {
            $product_id = $hit['document']['id'];
            $post = get_post($product_id);
            if ($post && $post->post_type == 'product') {
                $posts[] = new \WP_Post($post);
            }
        }

        return $posts;
    }

    public function get_seller_id()
    {
        $current_url = $_SERVER['REQUEST_URI'];
        $seller_id = '';

        $store_url   = wcfm_get_option('wcfm_store_url', 'store');
        $store_name  = get_query_var($store_url);

        if (strpos($current_url, "/{$store_url}/{$store_name}") !== false) {
            $seller_id  = get_user_by('slug', $store_name)->ID;
        }

        return $seller_id;
    }
}
