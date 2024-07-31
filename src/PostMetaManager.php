<?php
namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

/**
 * PostMetaManager Class
 *
 * Manages post meta related to Typesense indexing.
 */
class PostMetaManager
{
    public const COLLECTION_KEY = '_awp_typesense_indexed';

    public static function update_post_meta($product_id, $collection_name)
    {
        $key = $collection_name . self::COLLECTION_KEY;
        update_post_meta($product_id, $key, true);
    }

    public static function get_post_meta($product_id, $collection_name)
    {
        $key = $collection_name . self::COLLECTION_KEY;
        return get_post_meta($product_id, $key, true);
    }

    public static function delete_post_meta($product_id, $collection_name)
    {
        $key = $collection_name . self::COLLECTION_KEY;
        delete_post_meta($product_id, $key);
    }

    /**
     * Delete all Typesense indexing meta for a specific collection.
     *
     * @param string $collection_name The name of the Typesense collection.
     * @return void
     */
    public static function delete_all_indexing_meta($collection_name)
    {
        global $wpdb;
        $key = $wpdb->esc_like($collection_name . self::COLLECTION_KEY);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s",
            $key
        ));
    }
}