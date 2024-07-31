<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;


use WP_CLI;
//use WP_CLI_Command;

/**
 * WP-CLI command for Typesense indexing.
 * 
 * @package TypesenseSearch
 * @subpackage WP CLI
 * @since 1.0.0
 */
class TypesenseCLI
{

    /**
     * Index data to Typesense.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Force indexing even if an index is already present.
     *
     * ## EXAMPLES
     *
     *     wp typesense index
     *     wp typesense index --force
     *
     * @when after_wp_load
     */
    public function index($args, $assoc_args)
    {
        error_reporting(0);

        $force = isset($assoc_args['force']) ? true : false;

        if ($force === true) {
            $itsync = InitTypesenseSync::get_instance();
            $itsync->set_force_reindex(true);
            OptionsManager::reset_indexing_status($itsync->get_collection_name());
        }

        WP_CLI::success('Typesense indexing started.');
        (InitTypesenseSync::get_instance())->init();
        WP_CLI::success('Typesense indexing completed.');
    }
}

// Register the command
if (class_exists('WP_CLI')) {
    WP_CLI::add_command('typesense', __NAMESPACE__ . '\\TypesenseCLI');
}
