<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

use \Exception;

class Search
{
    public const PER_PAGE = 48;
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
            'filter_by' => '',
            'facet_by'  => 'categories,attribute_terms,product_type',
            'sort_by' => 'publish_date:desc',
            'per_page' => self::PER_PAGE,
            'page' => $paged,
            'include_fields' => 'id'
        ];

        // Add category filter
        if (!empty($category) && $category !== 'Category') {
            $search_parameters['filter_by'] .= "categories:=[" . $category . "]";
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
        $today_start = strtotime('today midnight');
        $today_end = strtotime('tomorrow midnight') - 1;

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
                    $search_parameters['filter_by'] .= (!empty($search_parameters['filter_by']) ? ' && ' : '') . "auction_dates_from:[$today_start...$today_end]";
                    $search_parameters['sort_by'] = 'auction_dates_from:asc';
                    break;
                case 'auction_end':
                    $search_parameters['filter_by'] .= (!empty($search_parameters['filter_by']) ? ' && ' : '') . "auction_dates_to:[$today_start...$today_end]";
                    $search_parameters['sort_by'] = 'auction_dates_to:asc';
                    break;
                default:
                    $search_parameters['sort_by'] = 'title:asc';
                    break;
            }
        }

        try {


            $results = $wc_to_typesense->typesense->collections[$wc_to_typesense->collection_name]->documents->search($search_parameters);
            /*echo $scoped_key = $typesense_init->perform_search_with_scoped_api_key(15000);
            exit;
            if ($scoped_key) {
                $results = $wc_to_typesense->typesense->collections[$wc_to_typesense->collection_name]->documents->search($search_parameters, ['x-typesense-api-key' => $scoped_key]);
            } else {
                exit('something wrong');
            }*/

            //$results = $wc_to_typesense->typesense->collections[$wc_to_typesense->collection_name]->documents->search($search_parameters);
            $posts = $this->convert_typesense_to_wp_posts($results);


            //dd($results);

            ob_start();

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

                /*global $wp_query;
                $wp_query->set('post_type', 'product');
                $wp_query->set('wc_query', 'product_query');*/

                do_action('woocommerce_before_shop_loop');

                echo str_replace('columns-4', 'columns-6', woocommerce_product_loop_start(false));

                $total_products = $results['found'];

                $search_title = $query;
                if (!empty($search_title)) {
                    $search_title = ' | Showing results for: ' . $search_title;
                }
?>

                <li class="total-product-count" id="mbf_products_count">
                    <p>Total listings: <?php echo number_format($total_products) . $search_title ?></p>
                </li>
<?php
                global $post, $product;
                foreach ($posts as $prod) {
                    $post = $prod;
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

            $html = ob_get_clean();

            wp_send_json_success(array(
                'html' => $html,
                'total' => $results['found'],
                'total_pages' => ceil($results['found'] / $search_parameters['per_page']),
                'current_page' => $paged
            ));
        } catch (Exception $e) {
            wp_send_json_error('An error occurred while performing the search: ' . $e->getMessage());
        }

        wp_die();
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

    public function generate_pagination($total_products, $per_page, $current_page)
    {
        $total_pages = ceil($total_products / $per_page);
        $base_url = get_pagenum_link(1); // Get the base URL without pagination
        $base_url = preg_replace('/\/page\/1\/$/', '/', $base_url); // Remove /page/1/ if it exists

        // Parse the base URL to get the query string
        $parsed_url = wp_parse_url($base_url);
        $query_args = array();

        // If there's a query string, parse it into an array
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_args);
        }

        // Add or update the paged query parameter
        $query_args['cur_page'] = '%#%';

        // Rebuild the URL with the updated query arguments
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'];
        $base_url = add_query_arg($query_args, $base_url);

        $pagination_args = array(
            'base' => $base_url,
            'format' => '',
            'current' => max(1, $current_page),
            'total' => $total_pages,
            'prev_text' => __('←'),
            'next_text' => __('→'),
            'type' => 'array',
            'mid_size' => 2,
            'end_size' => 1,
            'add_args' => array()
        );

        $pagination_links = paginate_links($pagination_args);

        if ($pagination_links) {
            echo '<nav class="woocommerce-pagination">';
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
            'query_by'  => 'title,description',
            'filter_by' => '',
            'per_page'  => self::PER_PAGE,
            'page' => $paged
        ];

        if (!empty($category)) {
            $search_parameters['filter_by'] .= "categories:=$category";
        }

        $results = $wc_to_typesense->typesense->collections[$wc_to_typesense->collection_name]->documents->search($search_parameters);

        return $results;
    }

    public function is_frontend_ajax_request()
    {
        if (wp_doing_ajax()) {
            $referer = wp_get_referer();
            if ($referer && !strpos($referer, 'wp-admin')) {
                return true;
            }
        }
        return false;
    }


    function convert_typesense_to_wp_posts($results)
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
}
