<?php
/**
 * Plugin Name: Cricket Live Scores
 * Plugin URI: https://github.com/sachinbothra/cricket-live-scores
 * Description: Display live cricket scores from Entity Sport API with automatic updates
 * Version: 1.0.0
 * Requires at least: 6. 8
 * Requires PHP: 8.2
 * Author: Sachin Bothra
 * License: GPL v2 or later
 * Text Domain: cricket-live-scores
 */

if (!defined('ABSPATH')) exit;

define('CRICKET_LIVE_SCORES_VERSION', '1.0.0');
define('CRICKET_LIVE_SCORES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CRICKET_LIVE_SCORES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include files
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR . 'includes/class-cricket-installer.php';
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR .  'includes/class-cricket-api.php';
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR . 'includes/class-cricket-database. php';
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR . 'includes/class-cricket-cron. php';
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR . 'includes/class-cricket-shortcodes. php';
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR . 'includes/class-cricket-admin.php';
require_once CRICKET_LIVE_SCORES_PLUGIN_DIR . 'includes/class-cricket-ajax.php';

register_activation_hook(__FILE__, ['Cricket_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['Cricket_Installer', 'deactivate']);

add_action('plugins_loaded', function() {
    Cricket_Admin::init();
    Cricket_Shortcodes::init();
    Cricket_Cron::init();
    Cricket_Ajax::init();
});
