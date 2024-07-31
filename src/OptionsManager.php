<?php
namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

/**
 * Options_Manager Class
 *
 * This class manages the indexing options for the WooCommerce search with Typesense.
 * It provides methods to update, check, and reset the indexing status.
 */
class OptionsManager
{
    /**
     * Constant for the option key used in the WordPress options table.
     */
    private const WSWT_OPTIONS = '_wswt_awp_options';

    private const WSWT_INDEXED_PRODUCT_COUNT = '_wswt_typesense_indexed_product_count';

    /**
     * Update the options in the WordPress options table to mark indexing as completed.
     *
     * This method sets the 'index_completed' option to true and records the current timestamp
     * in the 'index_completed_at' option.
     *
     * @return void
     */
    public static function indexing_completed()
    {
        $options = self::get_option();
        $options['index_completed'] = true;
        $options['index_completed_at'] = time();
        update_option(self::WSWT_OPTIONS, $options);
    }

    /**
     * Reset the indexing status and clear all indexing meta.
     *
     * This method sets the 'index_completed' option to false, removes the
     * 'index_completed_at' timestamp, and clears all product indexing meta.
     * It's typically used when initiating a force reindex.
     *
     * @param string $collection_name The name of the Typesense collection.
     * @return void
     */
    public static function reset_indexing_status($collection_name)
    {
        $options = self::get_option();
        $options['index_completed'] = false;
        unset($options['index_completed_at']);
        update_option(self::WSWT_OPTIONS, $options);

        self::set_indexed_product_count(0);

        // Clear all indexing meta for the specified collection
        PostMetaManager::delete_all_indexing_meta($collection_name);
    }


    public static function get_indexed_product_count() {
        return get_option(self::WSWT_INDEXED_PRODUCT_COUNT, 0);
    }

    public static function set_indexed_product_count($val) {
        update_option(self::WSWT_INDEXED_PRODUCT_COUNT, $val);
    }

    /**
     * Check if the indexing has been completed.
     *
     * This method retrieves the options from the WordPress options table and checks
     * if the 'index_completed' option is set to true.
     *
     * @return bool True if indexing is completed, false otherwise.
     */
    public static function is_indexing_completed()
    {
        $options = self::get_option();
        return isset($options['index_completed']) && $options['index_completed'] === true;
    }

    /**
     * Retrieve the plugin options from the WordPress database.
     *
     * This static method fetches the options associated with the plugin
     * from the WordPress options table using the predefined option name.
     *
     * @return array The option value. Returns an empty array if the option does not exist.
     */
    public static function get_option()
    {
        $options = get_option(self::WSWT_OPTIONS, array());
        return is_array($options) ? $options : array();
    }

    /**
     * Delete the plugin options from the WordPress database.
     *
     * This static method removes the plugin's options from the WordPress
     * options table. It's typically used during plugin deactivation or uninstallation.
     *
     * @return void
     */
    public static function delete_option()
    {
        delete_option(self::WSWT_OPTIONS);
    }
}