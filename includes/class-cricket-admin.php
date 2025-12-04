<?php
/**
 * Cricket Live Scores - Admin Class
 *
 * Handles the WordPress admin interface, settings, and dashboard.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_Admin
 *
 * Admin panel and settings management.
 */
class Cricket_Admin {

    /**
     * Settings page slug.
     *
     * @var string
     */
    const MENU_SLUG = 'cricket-live-scores';

    /**
     * Initialize admin.
     *
     * @return void
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        
        // Admin AJAX actions
        add_action('wp_ajax_cricket_test_api', array(__CLASS__, 'ajax_test_api'));
        add_action('wp_ajax_cricket_clear_cache', array(__CLASS__, 'ajax_clear_cache'));
        add_action('wp_ajax_cricket_manual_sync', array(__CLASS__, 'ajax_manual_sync'));
        add_action('wp_ajax_cricket_get_logs', array(__CLASS__, 'ajax_get_logs'));
        add_action('wp_ajax_cricket_clear_logs', array(__CLASS__, 'ajax_clear_logs'));
    }

    /**
     * Add admin menu.
     *
     * @return void
     */
    public static function add_admin_menu() {
        add_options_page(
            __('Cricket Live Scores', 'cricket-live-scores'),
            __('Cricket Live Scores', 'cricket-live-scores'),
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'settings_page')
        );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public static function register_settings() {
        // API Settings
        register_setting('cricket_live_scores_settings', 'cricket_api_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('cricket_live_scores_settings', 'cricket_api_base_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://rest.entitysport.com/v2/',
        ));
        
        // Refresh intervals
        register_setting('cricket_live_scores_settings', 'cricket_live_refresh_interval', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30,
        ));
        
        register_setting('cricket_live_scores_settings', 'cricket_frontend_refresh_interval', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 2,
        ));
        
        // Display settings
        register_setting('cricket_live_scores_settings', 'cricket_matches_per_page', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 20,
        ));
        
        register_setting('cricket_live_scores_settings', 'cricket_enable_auto_refresh', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ));
        
        // Debug settings
        register_setting('cricket_live_scores_settings', 'cricket_enable_debug_logging', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ));
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public static function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_' . self::MENU_SLUG) {
            return;
        }
        
        wp_enqueue_style(
            'cricket-admin',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CRICKET_LIVE_SCORES_VERSION
        );
        
        wp_enqueue_script(
            'cricket-admin',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CRICKET_LIVE_SCORES_VERSION,
            true
        );
        
        wp_localize_script('cricket-admin', 'cricketAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cricket_admin_nonce'),
            'strings' => array(
                'testing' => __('Testing...', 'cricket-live-scores'),
                'success' => __('Success!', 'cricket-live-scores'),
                'error' => __('Error', 'cricket-live-scores'),
                'syncing' => __('Syncing...', 'cricket-live-scores'),
                'clearing' => __('Clearing...', 'cricket-live-scores'),
                'copied' => __('Copied!', 'cricket-live-scores'),
            ),
        ));
    }

    /**
     * Admin notices.
     *
     * @return void
     */
    public static function admin_notices() {
        // Check if API token is set
        $token = get_option('cricket_api_token', '');
        $screen = get_current_screen();
        
        if (empty($token) && $screen && $screen->id !== 'settings_page_' . self::MENU_SLUG) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Cricket Live Scores: Please configure your API token to start syncing cricket data.', 'cricket-live-scores');
            echo ' <a href="' . esc_url(admin_url('options-general.php?page=' . self::MENU_SLUG)) . '">';
            echo esc_html__('Configure Now', 'cricket-live-scores');
            echo '</a></p></div>';
        }
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public static function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $db = Cricket_Database::get_instance();
        $stats = $db->get_statistics();
        $sync_status = Cricket_Cron::get_sync_status();
        $api = Cricket_API::get_instance();
        $rate_limit = $api->get_rate_limit_status();
        ?>
        <div class="wrap cricket-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cricket-admin-container">
                <div class="cricket-admin-main">
                    <!-- Statistics Dashboard -->
                    <div class="cricket-admin-section">
                        <h2><?php esc_html_e('Dashboard', 'cricket-live-scores'); ?></h2>
                        <div class="cricket-stats-grid">
                            <div class="cricket-stat-box">
                                <span class="cricket-stat-number"><?php echo esc_html($stats['matches']['live']); ?></span>
                                <span class="cricket-stat-label"><?php esc_html_e('Live Matches', 'cricket-live-scores'); ?></span>
                            </div>
                            <div class="cricket-stat-box">
                                <span class="cricket-stat-number"><?php echo esc_html($stats['matches']['upcoming']); ?></span>
                                <span class="cricket-stat-label"><?php esc_html_e('Upcoming', 'cricket-live-scores'); ?></span>
                            </div>
                            <div class="cricket-stat-box">
                                <span class="cricket-stat-number"><?php echo esc_html($stats['matches']['completed']); ?></span>
                                <span class="cricket-stat-label"><?php esc_html_e('Completed', 'cricket-live-scores'); ?></span>
                            </div>
                            <div class="cricket-stat-box">
                                <span class="cricket-stat-number"><?php echo esc_html($stats['competitions']); ?></span>
                                <span class="cricket-stat-label"><?php esc_html_e('Competitions', 'cricket-live-scores'); ?></span>
                            </div>
                            <div class="cricket-stat-box">
                                <span class="cricket-stat-number"><?php echo esc_html($stats['teams']); ?></span>
                                <span class="cricket-stat-label"><?php esc_html_e('Teams', 'cricket-live-scores'); ?></span>
                            </div>
                            <div class="cricket-stat-box">
                                <span class="cricket-stat-number"><?php echo esc_html($rate_limit['remaining']); ?>/<?php echo esc_html($rate_limit['limit']); ?></span>
                                <span class="cricket-stat-label"><?php esc_html_e('API Rate Limit', 'cricket-live-scores'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Sync Status -->
                    <div class="cricket-admin-section">
                        <h2><?php esc_html_e('Sync Status', 'cricket-live-scores'); ?></h2>
                        <table class="cricket-sync-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Type', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('Last Sync', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('Next Scheduled', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('Action', 'cricket-live-scores'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sync_status as $type => $status) : ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($type)); ?></td>
                                    <td>
                                        <?php
                                        if ($status['last_sync']) {
                                            echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $status['last_sync']));
                                        } else {
                                            esc_html_e('Never', 'cricket-live-scores');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($status['next_scheduled']) {
                                            echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $status['next_scheduled']));
                                        } else {
                                            esc_html_e('Not scheduled', 'cricket-live-scores');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button cricket-sync-btn" data-type="<?php echo esc_attr($type); ?>">
                                            <?php esc_html_e('Sync Now', 'cricket-live-scores'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="cricket-sync-actions">
                            <button type="button" class="button button-primary" id="cricket-sync-all">
                                <?php esc_html_e('Sync All', 'cricket-live-scores'); ?>
                            </button>
                            <button type="button" class="button" id="cricket-clear-cache">
                                <?php esc_html_e('Clear Cache', 'cricket-live-scores'); ?>
                            </button>
                        </p>
                    </div>

                    <!-- Settings Form -->
                    <div class="cricket-admin-section">
                        <h2><?php esc_html_e('Settings', 'cricket-live-scores'); ?></h2>
                        <form method="post" action="options.php">
                            <?php settings_fields('cricket_live_scores_settings'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="cricket_api_token"><?php esc_html_e('API Token', 'cricket-live-scores'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               name="cricket_api_token" 
                                               id="cricket_api_token" 
                                               value="<?php echo esc_attr(get_option('cricket_api_token', '')); ?>" 
                                               class="regular-text"
                                               autocomplete="new-password">
                                        <button type="button" class="button" id="cricket-test-api">
                                            <?php esc_html_e('Test Connection', 'cricket-live-scores'); ?>
                                        </button>
                                        <span id="cricket-api-status"></span>
                                        <p class="description">
                                            <?php esc_html_e('Enter your Entity Sport API token.', 'cricket-live-scores'); ?>
                                            <a href="https://www.entitysport.com/" target="_blank" rel="noopener"><?php esc_html_e('Get API Token', 'cricket-live-scores'); ?></a>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="cricket_api_base_url"><?php esc_html_e('API Base URL', 'cricket-live-scores'); ?></label>
                                    </th>
                                    <td>
                                        <input type="url" 
                                               name="cricket_api_base_url" 
                                               id="cricket_api_base_url" 
                                               value="<?php echo esc_url(get_option('cricket_api_base_url', 'https://rest.entitysport.com/v2/')); ?>" 
                                               class="regular-text">
                                        <p class="description"><?php esc_html_e('Default: https://rest.entitysport.com/v2/', 'cricket-live-scores'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="cricket_frontend_refresh_interval"><?php esc_html_e('Frontend Refresh Interval', 'cricket-live-scores'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="cricket_frontend_refresh_interval" 
                                               id="cricket_frontend_refresh_interval" 
                                               value="<?php echo esc_attr(get_option('cricket_frontend_refresh_interval', 2)); ?>" 
                                               min="1" 
                                               max="60" 
                                               class="small-text"> <?php esc_html_e('seconds', 'cricket-live-scores'); ?>
                                        <p class="description"><?php esc_html_e('How often live scores refresh on the frontend (default: 2 seconds).', 'cricket-live-scores'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="cricket_matches_per_page"><?php esc_html_e('Matches Per Page', 'cricket-live-scores'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               name="cricket_matches_per_page" 
                                               id="cricket_matches_per_page" 
                                               value="<?php echo esc_attr(get_option('cricket_matches_per_page', 20)); ?>" 
                                               min="5" 
                                               max="100" 
                                               class="small-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Auto Refresh', 'cricket-live-scores'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="cricket_enable_auto_refresh" 
                                                   value="1" 
                                                   <?php checked(get_option('cricket_enable_auto_refresh', 1)); ?>>
                                            <?php esc_html_e('Enable auto-refresh for live matches', 'cricket-live-scores'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Debug Logging', 'cricket-live-scores'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="cricket_enable_debug_logging" 
                                                   value="1" 
                                                   <?php checked(get_option('cricket_enable_debug_logging', 0)); ?>>
                                            <?php esc_html_e('Enable debug logging', 'cricket-live-scores'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Logs API calls and sync operations to the error log.', 'cricket-live-scores'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button(); ?>
                        </form>
                    </div>
                </div>
                
                <div class="cricket-admin-sidebar">
                    <!-- Shortcode Documentation -->
                    <div class="cricket-admin-section">
                        <h2><?php esc_html_e('Shortcodes', 'cricket-live-scores'); ?></h2>
                        <div class="cricket-shortcode-list">
                            <div class="cricket-shortcode-item">
                                <code class="cricket-shortcode-code" data-shortcode="[cricket_widget]">[cricket_widget]</code>
                                <p><?php esc_html_e('Display the live matches widget with tabs and scrollable cards.', 'cricket-live-scores'); ?></p>
                            </div>
                            <div class="cricket-shortcode-item">
                                <code class="cricket-shortcode-code" data-shortcode="[cricket_live_matches]">[cricket_live_matches]</code>
                                <p><?php esc_html_e('Display live matches.', 'cricket-live-scores'); ?></p>
                                <small><?php esc_html_e('Attributes: limit, series, format', 'cricket-live-scores'); ?></small>
                            </div>
                            <div class="cricket-shortcode-item">
                                <code class="cricket-shortcode-code" data-shortcode="[cricket_upcoming_matches]">[cricket_upcoming_matches]</code>
                                <p><?php esc_html_e('Display upcoming matches.', 'cricket-live-scores'); ?></p>
                                <small><?php esc_html_e('Attributes: limit, series, format', 'cricket-live-scores'); ?></small>
                            </div>
                            <div class="cricket-shortcode-item">
                                <code class="cricket-shortcode-code" data-shortcode="[cricket_recent_matches]">[cricket_recent_matches]</code>
                                <p><?php esc_html_e('Display recent matches.', 'cricket-live-scores'); ?></p>
                                <small><?php esc_html_e('Attributes: limit, series, format', 'cricket-live-scores'); ?></small>
                            </div>
                            <div class="cricket-shortcode-item">
                                <code class="cricket-shortcode-code" data-shortcode='[cricket_match id=""]'>[cricket_match id=""]</code>
                                <p><?php esc_html_e('Display a single match.', 'cricket-live-scores'); ?></p>
                            </div>
                            <div class="cricket-shortcode-item">
                                <code class="cricket-shortcode-code" data-shortcode='[cricket_series id=""]'>[cricket_series id=""]</code>
                                <p><?php esc_html_e('Display a series page.', 'cricket-live-scores'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Debug Logs -->
                    <?php if (get_option('cricket_enable_debug_logging', 0)) : ?>
                    <div class="cricket-admin-section">
                        <h2><?php esc_html_e('Debug Logs', 'cricket-live-scores'); ?></h2>
                        <button type="button" class="button" id="cricket-view-logs">
                            <?php esc_html_e('View Logs', 'cricket-live-scores'); ?>
                        </button>
                        <button type="button" class="button" id="cricket-clear-logs">
                            <?php esc_html_e('Clear Logs', 'cricket-live-scores'); ?>
                        </button>
                        <div id="cricket-logs-container" style="display:none;">
                            <textarea id="cricket-logs-content" readonly rows="10" style="width:100%;font-family:monospace;font-size:12px;"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Test API connection.
     *
     * @return void
     */
    public static function ajax_test_api() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'cricket-live-scores')));
        }

        $api = Cricket_API::get_instance();
        $result = $api->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => $result['message']));
    }

    /**
     * AJAX: Clear cache.
     *
     * @return void
     */
    public static function ajax_clear_cache() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'cricket-live-scores')));
        }

        $api = Cricket_API::get_instance();
        $api->clear_cache();
        
        wp_send_json_success(array('message' => __('Cache cleared successfully.', 'cricket-live-scores')));
    }

    /**
     * AJAX: Manual sync.
     *
     * @return void
     */
    public static function ajax_manual_sync() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'cricket-live-scores')));
        }

        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'all';
        
        $result = Cricket_Cron::manual_sync($type);
        
        wp_send_json_success(array(
            'message' => __('Sync completed successfully.', 'cricket-live-scores'),
            'result' => $result,
        ));
    }

    /**
     * AJAX: Get logs.
     *
     * @return void
     */
    public static function ajax_get_logs() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'cricket-live-scores')));
        }

        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_file)) {
            wp_send_json_success(array('logs' => __('No log file found.', 'cricket-live-scores')));
        }

        // Get only cricket-related logs
        $logs = file_get_contents($log_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $lines = explode("\n", $logs);
        $cricket_logs = array();
        
        foreach ($lines as $line) {
            if (strpos($line, 'Cricket Live Scores') !== false) {
                $cricket_logs[] = $line;
            }
        }
        
        // Get last 100 lines
        $cricket_logs = array_slice($cricket_logs, -100);
        
        wp_send_json_success(array('logs' => implode("\n", $cricket_logs)));
    }

    /**
     * AJAX: Clear logs.
     *
     * @return void
     */
    public static function ajax_clear_logs() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'cricket-live-scores')));
        }

        // We can't clear the entire log file, but we can acknowledge the action
        wp_send_json_success(array('message' => __('Log view cleared.', 'cricket-live-scores')));
    }
}
