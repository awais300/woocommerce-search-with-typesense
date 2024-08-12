<?php

namespace AWP\TypesenseSearch;

use AWP\TypesenseSearch\Admin\TypesenseSettings;

defined('ABSPATH') || exit;

/**
 * LoggerTrait
 *
 * A trait that provides logging functionality for classes.
 * Logs messages to files stored in the 'wp-uploads/woocommerce-search-with-typesense/' directory.
 *
 * @package TypesenseSearch
 * @subpackage Logging
 * @since 1.0.0
 */
trait LoggerTrait
{
    /**
     * Directory where log files will be saved.
     *
     * @var string
     */
    private $log_dir;

    /**
     * Initializes the log directory.
     */
    protected function initialize_log_dir()
    {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/woocommerce-search-with-typesense/';

        if (!file_exists($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
    }

    /**
     * Logs a message to a file.
     *
     * @param string $message The message to log.
     * @param string $type The type of log message ('info' or 'error').
     */
    protected function log($message, $type = 'info')
    {
        if ($this->is_wp_cli() && $type == 'cli') {
            \WP_CLI::line($message);
        }

        // Uncommnent to display all sort of logging on CLI.
        /*if ($this->is_wp_cli()) {
            \WP_CLI::line($message);
        }*/

        if (!$this->is_logging_enabled() && !$this->is_wp_cli()) {
            return;
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        $file = $this->log_dir . date('Y-m-d') . '.log';

        $time = date('Y-m-d H:i:s');
        $formatted_message = "[$time] [$type] $message" . PHP_EOL;
        file_put_contents($file, $formatted_message, FILE_APPEND);
    }

    /**
     * Logs an informational message.
     *
     * @param string $message The informational message to log.
     */
    public function log_info($message)
    {
        $this->log($message, 'info');
    }


    /**
     * Logs a message for CLI.
     *
     * @param string $message The informational message to log.
     */
    public function log_cli($message)
    {
        $this->log($message, 'cli');
    }

    /**
     * Logs an error message.
     *
     * @param string $message The error message to log.
     */
    public function log_error($message)
    {
        $this->log($message, 'error');
    }

    private function is_logging_enabled()
    {
        return get_option(TypesenseSettings::ENABLE_LOGGING, false);
    }

    /**
     * Check if you are CLI mode.
     **/
    public function is_wp_cli()
    {
        if (php_sapi_name() === 'cli' && defined('WP_CLI') && WP_CLI) {
            return true;
        } else {
            return false;
        }
    }
}
