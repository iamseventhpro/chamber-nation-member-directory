<?php
/**
 * Plugin Name:       Chamber Nation Member Directory
 * Plugin URI:        https://github.com/iamseventhpro/chamber-nation-member-directory
 * Description:       Server-rendered member directory for ChamberOrganizer / ECTownUSA chambers, with member pages, schema.org markup and llms.txt for search engines and AI crawlers.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Chamber Nation
 * Author URI:        https://chambernation.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chamber-nation-member-directory
 * Update URI:        https://github.com/iamseventhpro/chamber-nation-member-directory
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

define( 'CNMD_VERSION', '1.0.0' );
define( 'CNMD_FILE', __FILE__ );
define( 'CNMD_DIR', plugin_dir_path( __FILE__ ) );
define( 'CNMD_URL', plugin_dir_url( __FILE__ ) );

require_once CNMD_DIR . 'includes/class-cnmd-settings.php';
require_once CNMD_DIR . 'includes/class-cnmd-api.php';
require_once CNMD_DIR . 'includes/class-cnmd-store.php';
require_once CNMD_DIR . 'includes/class-cnmd-links.php';
require_once CNMD_DIR . 'includes/class-cnmd-schema.php';
require_once CNMD_DIR . 'includes/class-cnmd-directory.php';
require_once CNMD_DIR . 'includes/class-cnmd-member-page.php';
require_once CNMD_DIR . 'includes/class-cnmd-seo.php';
require_once CNMD_DIR . 'includes/class-cnmd-sitemap.php';
require_once CNMD_DIR . 'includes/class-cn-llms-txt.php';
require_once CNMD_DIR . 'includes/class-cnmd-admin.php';
require_once CNMD_DIR . 'includes/class-cnmd-updater.php';
require_once CNMD_DIR . 'includes/class-cnmd-plugin.php';

register_activation_hook( __FILE__, array( 'CNMD_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CNMD_Plugin', 'deactivate' ) );

CNMD_Plugin::init();
