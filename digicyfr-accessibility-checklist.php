<?php
/**
 * Plugin Name: Digicyfr Accessibility Checklist
 * Plugin URI: https://digicyfr.com/plugins/digicyfr-accessibility-checklist/
 * Description: Private, dated records for six guided human accessibility checks on pages and journeys.
 * Version: 0.1.1
 * Requires at least: 7.1
 * Requires PHP: 8.3
 * Author: Digicyfr
 * Author URI: https://digicyfr.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: digicyfr-accessibility-checklist
 * Domain Path: /languages
 * Update URI: https://digicyfr.com/plugins/digicyfr-accessibility-checklist/
 *
 * @package Digicyfr_Accessibility_Checklist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIGICYFR_ACCESSIBILITY_CHECKLIST_VERSION', '0.1.1' );
define( 'DIGICYFR_ACCESSIBILITY_CHECKLIST_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIGICYFR_ACCESSIBILITY_CHECKLIST_URL', plugin_dir_url( __FILE__ ) );

require_once DIGICYFR_ACCESSIBILITY_CHECKLIST_PATH . 'includes/class-dacl-model.php';
require_once DIGICYFR_ACCESSIBILITY_CHECKLIST_PATH . 'includes/class-dacl-admin.php';

add_action( 'init', array( 'DACL_Model', 'register' ) );
DACL_Admin::boot();
