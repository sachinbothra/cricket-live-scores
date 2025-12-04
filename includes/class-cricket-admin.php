<?php
/**
 * Cricket Admin Class
 * Handles WordPress admin functionality
 */

if (!defined('ABSPATH')) exit;

class Cricket_Admin {
    
    /**
     * Initialize admin hooks
     */
    public static function init() {
        add_action('admin_menu', [self::class, 'add_matches_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('wp_ajax_cricket_sync_matches', [self::class, 'ajax_sync_matches']);
        add_action('wp_ajax_cricket_clear_cache', [self::class, 'ajax_clear_cache']);
    }
    
    /**
     * Add matches menu to WordPress admin
     */
    public static function add_matches_menu() {
        add_menu_page(
            __('Cricket Matches', 'cricket-live-scores'),
            __('Cricket Matches', 'cricket-live-scores'),
            'manage_options',
            'cricket-matches-list',
            [self::class, 'render_matches_list_page'],
            'dashicons-tickets-alt',
            25
        );
        
        add_submenu_page(
            'cricket-matches-list',
            __('All Matches', 'cricket-live-scores'),
            __('All Matches', 'cricket-live-scores'),
            'manage_options',
            'cricket-matches-list',
            [self::class, 'render_matches_list_page']
        );
        
        add_submenu_page(
            'cricket-matches-list',
            __('Settings', 'cricket-live-scores'),
            __('Settings', 'cricket-live-scores'),
            'manage_options',
            'cricket-live-scores-settings',
            [self::class, 'render_settings_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public static function register_settings() {
        // API Settings
        register_setting('cricket_settings', 'cricket_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        // Cache Settings
        register_setting('cricket_settings', 'cricket_live_cache_duration', [
            'type' => 'integer',
            'default' => 2,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('cricket_settings', 'cricket_upcoming_cache_duration', [
            'type' => 'integer',
            'default' => 3600,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('cricket_settings', 'cricket_completed_cache_duration', [
            'type' => 'integer',
            'default' => 86400,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('cricket_settings', 'cricket_teams_cache_duration', [
            'type' => 'integer',
            'default' => 604800,
            'sanitize_callback' => 'absint',
        ]);
        
        // Rate Limiting
        register_setting('cricket_settings', 'cricket_api_rate_limit', [
            'type' => 'integer',
            'default' => 60,
            'sanitize_callback' => 'absint',
        ]);
        
        register_setting('cricket_settings', 'cricket_api_monthly_limit', [
            'type' => 'integer',
            'default' => 10000,
            'sanitize_callback' => 'absint',
        ]);
        
        // Refresh Interval
        register_setting('cricket_settings', 'cricket_refresh_interval', [
            'type' => 'integer',
            'default' => 2000,
            'sanitize_callback' => 'absint',
        ]);
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'cricket') === false) {
            return;
        }
        
        wp_enqueue_style(
            'cricket-admin-css',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CRICKET_LIVE_SCORES_VERSION
        );
        
        wp_enqueue_script(
            'cricket-admin-js',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            CRICKET_LIVE_SCORES_VERSION,
            true
        );
        
        wp_localize_script('cricket-admin-js', 'cricketAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cricket_admin_nonce'),
            'strings' => [
                'syncing' => __('Syncing...', 'cricket-live-scores'),
                'syncComplete' => __('Sync Complete!', 'cricket-live-scores'),
                'syncError' => __('Sync Failed', 'cricket-live-scores'),
                'clearing' => __('Clearing...', 'cricket-live-scores'),
                'clearComplete' => __('Cache Cleared!', 'cricket-live-scores'),
            ]
        ]);
    }
    
    /**
     * Render matches list page
     */
    public static function render_matches_list_page() {
        $db = new Cricket_Database();
        
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Get matches based on filter
        if ($status_filter === 'live') {
            $matches = $db->get_live_matches(100);
        } elseif ($status_filter === 'upcoming') {
            $matches = $db->get_upcoming_matches(100);
        } elseif ($status_filter === 'completed') {
            $matches = $db->get_recent_matches(100);
        } else {
            // Get all matches
            $matches = $db->get_all_matches(100);
        }
        
        // Apply search filter
        if (!empty($search) && !empty($matches)) {
            $matches = array_filter($matches, function($match) use ($search) {
                return stripos($match->title, $search) !== false || 
                       stripos($match->teama_name, $search) !== false || 
                       stripos($match->teamb_name, $search) !== false;
            });
        }
        
        // Get API usage stats
        $usage_stats = $db->get_api_usage_stats();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Cricket Matches', 'cricket-live-scores'); ?></h1>
            
            <button type="button" id="cricket-sync-btn" class="page-title-action"><?php esc_html_e('Sync Now', 'cricket-live-scores'); ?></button>
            
            <hr class="wp-header-end">
            
            <!-- API Usage Stats -->
            <div class="cricket-stats-box" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('API Usage Statistics', 'cricket-live-scores'); ?></h3>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <strong><?php esc_html_e('Today:', 'cricket-live-scores'); ?></strong> 
                        <?php echo esc_html(number_format($usage_stats['requests_today'])); ?> <?php esc_html_e('requests', 'cricket-live-scores'); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('This Month:', 'cricket-live-scores'); ?></strong> 
                        <?php echo esc_html(number_format($usage_stats['requests_this_month'])); ?> / <?php echo esc_html(number_format($usage_stats['monthly_limit'])); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Remaining:', 'cricket-live-scores'); ?></strong> 
                        <?php echo esc_html(number_format($usage_stats['remaining_requests'])); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Cache Hit Rate:', 'cricket-live-scores'); ?></strong> 
                        <?php echo esc_html($usage_stats['cache_hit_rate']); ?>%
                    </div>
                </div>
            </div>
            
            <!-- Filter Form -->
            <form method="get" style="margin: 20px 0;">
                <input type="hidden" name="page" value="cricket-matches-list">
                
                <select name="status" style="margin-right: 10px;">
                    <option value="all" <?php selected($status_filter, 'all'); ?>><?php esc_html_e('All Matches', 'cricket-live-scores'); ?></option>
                    <option value="live" <?php selected($status_filter, 'live'); ?>><?php esc_html_e('Live', 'cricket-live-scores'); ?></option>
                    <option value="upcoming" <?php selected($status_filter, 'upcoming'); ?>><?php esc_html_e('Upcoming', 'cricket-live-scores'); ?></option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'cricket-live-scores'); ?></option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search matches...', 'cricket-live-scores'); ?>" style="margin-right: 10px;">
                
                <button type="submit" class="button"><?php esc_html_e('Filter', 'cricket-live-scores'); ?></button>
                
                <?php if ($status_filter !== 'all' || !empty($search)) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cricket-matches-list')); ?>" class="button"><?php esc_html_e('Reset', 'cricket-live-scores'); ?></a>
                <?php endif; ?>
            </form>
            
            <!-- Matches Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Match ID', 'cricket-live-scores'); ?></th>
                        <th><?php esc_html_e('Title', 'cricket-live-scores'); ?></th>
                        <th><?php esc_html_e('Teams', 'cricket-live-scores'); ?></th>
                        <th><?php esc_html_e('Status', 'cricket-live-scores'); ?></th>
                        <th><?php esc_html_e('Format', 'cricket-live-scores'); ?></th>
                        <th><?php esc_html_e('Date', 'cricket-live-scores'); ?></th>
                        <th><?php esc_html_e('Actions', 'cricket-live-scores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($matches)) : ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">
                                <?php esc_html_e('No matches found. Click "Sync Now" to fetch matches.', 'cricket-live-scores'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($matches as $match) : 
                            $match_slug = sanitize_title($match->short_title ?: $match->title) . '-' . $match->match_id;
                            $match_url = home_url('/match/' . $match_slug);
                            
                            // Status badge color
                            $status_class = 'scheduled';
                            if (in_array(strtolower($match->status), ['live', 'in progress'])) {
                                $status_class = 'live';
                            } elseif (in_array(strtolower($match->status), ['completed', 'finished'])) {
                                $status_class = 'completed';
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($match->match_id); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($match->title); ?></strong>
                                    <?php if (!empty($match->competition_name)) : ?>
                                        <br><small style="color: #666;"><?php echo esc_html($match->competition_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($match->teama_name); ?> vs <?php echo esc_html($match->teamb_name); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($status_class); ?>" style="padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block;
                                        <?php if ($status_class === 'live') echo 'background: #dc3545; color: white;'; ?>
                                        <?php if ($status_class === 'completed') echo 'background: #28a745; color: white;'; ?>
                                        <?php if ($status_class === 'scheduled') echo 'background: #6c757d; color: white;'; ?>">
                                        <?php echo esc_html(ucfirst($match->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($match->format); ?></td>
                                <td><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($match->date_start))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($match_url); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e('View', 'cricket-live-scores'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 20px;">
                <strong><?php esc_html_e('Total Matches:', 'cricket-live-scores'); ?></strong> <?php echo count($matches); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $db = new Cricket_Database();
        $usage_stats = $db->get_api_usage_stats();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cricket Live Scores Settings', 'cricket-live-scores'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('cricket_settings'); ?>
                
                <h2><?php esc_html_e('API Configuration', 'cricket-live-scores'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cricket_api_key"><?php esc_html_e('Entity Sport API Key', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="cricket_api_key" name="cricket_api_key" 
                                   value="<?php echo esc_attr(get_option('cricket_api_key')); ?>" 
                                   class="regular-text">
                            <p class="description"><?php esc_html_e('Enter your Entity Sport API key.', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Cache Settings', 'cricket-live-scores'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cricket_live_cache_duration"><?php esc_html_e('Live Match Cache (seconds)', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_live_cache_duration" name="cricket_live_cache_duration" 
                                   value="<?php echo esc_attr(get_option('cricket_live_cache_duration', 2)); ?>" 
                                   min="1" max="60" class="small-text">
                            <p class="description"><?php esc_html_e('Cache duration for live matches. Default: 2 seconds.', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cricket_upcoming_cache_duration"><?php esc_html_e('Upcoming Match Cache (seconds)', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_upcoming_cache_duration" name="cricket_upcoming_cache_duration" 
                                   value="<?php echo esc_attr(get_option('cricket_upcoming_cache_duration', 3600)); ?>" 
                                   min="60" class="small-text">
                            <p class="description"><?php esc_html_e('Cache duration for upcoming matches. Default: 3600 seconds (1 hour).', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cricket_completed_cache_duration"><?php esc_html_e('Completed Match Cache (seconds)', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_completed_cache_duration" name="cricket_completed_cache_duration" 
                                   value="<?php echo esc_attr(get_option('cricket_completed_cache_duration', 86400)); ?>" 
                                   min="3600" class="small-text">
                            <p class="description"><?php esc_html_e('Cache duration for completed matches. Default: 86400 seconds (24 hours).', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cricket_teams_cache_duration"><?php esc_html_e('Teams/Players Cache (seconds)', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_teams_cache_duration" name="cricket_teams_cache_duration" 
                                   value="<?php echo esc_attr(get_option('cricket_teams_cache_duration', 604800)); ?>" 
                                   min="3600" class="small-text">
                            <p class="description"><?php esc_html_e('Cache duration for teams/players data. Default: 604800 seconds (7 days).', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Rate Limiting', 'cricket-live-scores'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cricket_api_rate_limit"><?php esc_html_e('Rate Limit (requests/minute)', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_api_rate_limit" name="cricket_api_rate_limit" 
                                   value="<?php echo esc_attr(get_option('cricket_api_rate_limit', 60)); ?>" 
                                   min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum API requests per minute. Starter Plan: 60/min.', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cricket_api_monthly_limit"><?php esc_html_e('Monthly Limit', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_api_monthly_limit" name="cricket_api_monthly_limit" 
                                   value="<?php echo esc_attr(get_option('cricket_api_monthly_limit', 10000)); ?>" 
                                   min="1000" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum API requests per month. Starter Plan: 10,000/month.', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Frontend Settings', 'cricket-live-scores'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cricket_refresh_interval"><?php esc_html_e('Auto-refresh Interval (ms)', 'cricket-live-scores'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cricket_refresh_interval" name="cricket_refresh_interval" 
                                   value="<?php echo esc_attr(get_option('cricket_refresh_interval', 2000)); ?>" 
                                   min="1000" max="60000" class="small-text">
                            <p class="description"><?php esc_html_e('Frontend auto-refresh interval for live matches. Default: 2000ms (2 seconds).', 'cricket-live-scores'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('API Usage', 'cricket-live-scores'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Requests Today', 'cricket-live-scores'); ?></th>
                    <td><?php echo esc_html(number_format($usage_stats['requests_today'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Requests This Month', 'cricket-live-scores'); ?></th>
                    <td><?php echo esc_html(number_format($usage_stats['requests_this_month'])); ?> / <?php echo esc_html(number_format($usage_stats['monthly_limit'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Remaining Requests', 'cricket-live-scores'); ?></th>
                    <td><?php echo esc_html(number_format($usage_stats['remaining_requests'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cache Hit Rate', 'cricket-live-scores'); ?></th>
                    <td><?php echo esc_html($usage_stats['cache_hit_rate']); ?>%</td>
                </tr>
            </table>
            
            <p>
                <button type="button" id="cricket-clear-cache-btn" class="button button-secondary">
                    <?php esc_html_e('Clear API Cache', 'cricket-live-scores'); ?>
                </button>
            </p>
            
            <hr>
            
            <h2><?php esc_html_e('Shortcodes', 'cricket-live-scores'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Live Matches', 'cricket-live-scores'); ?></th>
                    <td><code>[cricket_live_matches]</code> - <?php esc_html_e('Display all live matches', 'cricket-live-scores'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Upcoming Matches', 'cricket-live-scores'); ?></th>
                    <td><code>[cricket_upcoming_matches]</code> - <?php esc_html_e('Display upcoming matches', 'cricket-live-scores'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Recent Matches', 'cricket-live-scores'); ?></th>
                    <td><code>[cricket_recent_matches]</code> - <?php esc_html_e('Display recent completed matches', 'cricket-live-scores'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Live Widget', 'cricket-live-scores'); ?></th>
                    <td><code>[cricket_live_widget]</code> - <?php esc_html_e('Display compact live scores widget', 'cricket-live-scores'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Single Match', 'cricket-live-scores'); ?></th>
                    <td><code>[cricket_match id="123"]</code> - <?php esc_html_e('Display specific match by ID', 'cricket-live-scores'); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for syncing matches
     */
    public static function ajax_sync_matches() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cricket-live-scores')]);
        }
        
        try {
            Cricket_Cron::sync_all_matches();
            wp_send_json_success(['message' => __('Matches synced successfully!', 'cricket-live-scores')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public static function ajax_clear_cache() {
        check_ajax_referer('cricket_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cricket-live-scores')]);
        }
        
        try {
            $api = new Cricket_API();
            $api->clear_cache();
            wp_send_json_success(['message' => __('Cache cleared successfully!', 'cricket-live-scores')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
