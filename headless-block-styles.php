<?php
/**
 * Plugin Name: Headless Block Styles
 * Plugin URI: https://github.com/FuSan21/headless-block-styles
 * Description: Exposes Gutenberg block inline styles and the global stylesheet over the WordPress REST API for any headless (e.g. Next.js) frontend.
 * Version: 0.1.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Author: Fuad Hasan
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: headless-block-styles
 *
 * @package HeadlessBlockStyles
 */

namespace HeadlessBlockStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HEADLESS_BLOCK_STYLES_VERSION', '0.1.0' );
define( 'HEADLESS_BLOCK_STYLES_REST_NAMESPACE', 'headless-block-styles/v1' );
define( 'HEADLESS_BLOCK_STYLES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require HEADLESS_BLOCK_STYLES_PLUGIN_DIR . 'includes/styles.php';
require HEADLESS_BLOCK_STYLES_PLUGIN_DIR . 'includes/rest.php';
require HEADLESS_BLOCK_STYLES_PLUGIN_DIR . 'includes/render.php';
