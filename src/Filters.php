<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

use Exception;

class Filters extends Singleton
{
    use LoggerTrait;

    public function __construct()
    {
        $this->initialize_log_dir();
        $this->register_shortcodes();
    }

    private function register_shortcodes()
    {
        add_shortcode('product_attribute_filter', [$this, 'product_attribute_filter_shortcode']);
        add_shortcode('product_category_filter', [$this, 'product_category_filter_shortcode']);
    }

    public function product_category_filter_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'hide_empty' => true,
                'title' => '',
                'class' => 'filter-category',
                'type' => 'checkbox',
                'parent' => '',
                'include' => '', // New attribute for specific category IDs
            ),
            $atts,
            'product_category_filter'
        );

        $hide_empty = filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        $title = sanitize_text_field($atts['title']);
        $class = sanitize_html_class($atts['class']);
        $type = in_array($atts['type'], ['checkbox', 'radio']) ? $atts['type'] : 'checkbox';
        $parent = absint($atts['parent']);
        $include = array_filter(array_map('absint', explode(',', $atts['include'])));

        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => $hide_empty,
        );

        if (!empty($parent)) {
            $args['parent'] = $parent;
        }

        if (!empty($include)) {
            $args['include'] = $include;
            // When using 'include', 'parent' is ignored
            unset($args['parent']);
        }

        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return ''; // Return empty string if there's an error
        }

        // If parent is set and we're using 'include', filter out terms that don't match the parent
        if (!empty($parent) && !empty($include)) {
            $terms = array_filter($terms, function ($term) use ($parent) {
                return $term->parent == $parent;
            });
        }

        ob_start();

        if (!empty($title)) {
            echo "<h3>" . esc_html($title) . "</h3>";
        }

        echo "<ul class='product-category-filter type-" . $type . "'>";
        foreach ($terms as $term) {
            echo '<li>';
            echo "<input type='{$type}' data-term_id='{$term->term_id}' value='{$term->slug}' id='product_cat-{$term->term_id}' class='{$class}' name='product_cati[]' />";
            echo "<label for='product_cat-{$term->term_id}'>" . esc_html($term->name) . "</label>";
            echo '</li>';
        }
        echo "</ul>";

        return ob_get_clean();
    }
    public function product_attribute_filter_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'attribute' => 'pa_comicbookera',
                'hide_empty' => true,
                'title' => '',
                'class' => 'filter-term',
                'type' => 'checkbox',
            ),
            $atts,
            'product_attribute_filter'
        );

        $attribute = sanitize_text_field($atts['attribute']);
        $hide_empty = filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        $title = sanitize_text_field($atts['title']);
        $class = sanitize_html_class($atts['class']);
        $type = in_array($atts['type'], ['checkbox', 'radio', 'select']) ? $atts['type'] : 'checkbox';

        $terms = get_terms(['taxonomy' => $attribute, 'hide_empty' => $hide_empty]);

        if (is_wp_error($terms)) {
            return ''; // Return empty string if there's an error
        }

        ob_start();

        if (!empty($title)) {
            echo "<h3>" . esc_html($title) . "</h3>";
        }

        if ($type === 'select') {
            echo "<div class='product-attribute-filter'>";
            echo "<select class='{$class}' name='{$attribute}'>";
            echo "<option value=''>" . __('Select an option', 'text-domain') . "</option>";
            foreach ($terms as $term) {
                echo "<option value='{$term->slug}'>" . esc_html($term->name) . "</option>";
            }
            echo "</select>";
            echo "</div>";
        } else {
            $input_name = $type === 'radio' ? "{$attribute}" : "{$attribute}[]";

            echo "<ul class='product-attribute-filter type-{$type}'>";
            foreach ($terms as $term) {
                echo '<li>';
                echo "<input type='{$type}' data-term_id='{$term->term_id}' value='{$term->slug}' id='product_attr-{$term->term_id}' class='{$class}' name='{$input_name}' />";
                echo "<label for='product_attr-{$term->term_id}'>" . esc_html($term->name) . "</label>";
                echo '</li>';
            }
            echo "</ul>";
        }

        return ob_get_clean();
    }
}
