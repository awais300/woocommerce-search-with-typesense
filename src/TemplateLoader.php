<?php

namespace AWP\TypesenseSearch;

defined('ABSPATH') || exit;

/**
 * Class TemplateLoader
 * @package AWP\TypesenseSearch
 */

class TemplateLoader extends Singleton
{
	/**
	 * Loads a template.
	 *
	 * @param  string $template_name
	 * @param  array $args
	 * @param  string $template_path
	 * @param  bool $echo
	 *
	 */
	public function get_template($template_name = '', $args = array(), $template_path = '', $echo = false)
	{
		$output = null;

		$template_path = $template_path . $template_name;

		if (file_exists($template_path)) {
			extract($args); // @codingStandardsIgnoreLine required for template.

			ob_start();
			include $template_path;
			$output = ob_get_clean();
		} else {
			throw new \Exception(__('Specified path does not exist', 'exclude-files-for-aio-wp-migration'));
		}

		if ($echo) {
			print $output; // @codingStandardsIgnoreLine $output contains dynamic data and escping is being handled in the template file.
		} else {
			return $output;
		}
	}
}
