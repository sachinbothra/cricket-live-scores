<?php
/**
 * Cricket Cron Class
 * Handles scheduled tasks for syncing matches
 */

if (!defined('ABSPATH')) exit;

class Cricket_Cron {
    
    // Cron hook names
    private const LIVE_UPDATE_HOOK = 'cricket_update_live_matches';
    private const UPCOMING_UPDATE_HOOK = 'cricket_update_upcoming_matches';
    private const COMPLETED_UPDATE_HOOK = 'cricket_update_completed_matches';
    private const CLEANUP_HOOK = 'cricket_cleanup_old_matches';
    
    /**
     * Initialize cron handlers
     */
    public static function init() {
        // Add custom cron schedules
        add_filter('cron_schedules', [self::class, 'add_cron_schedules']);
        
        // Register cron handlers
        add_action(self::LIVE_UPDATE_HOOK, [self::class, 'update_live_matches']);
        add_action(self::UPCOMING_UPDATE_HOOK, [self::class, 'update_upcoming_matches']);
        add_action(self::COMPLETED_UPDATE_HOOK, [self::class, 'update_completed_matches']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup_old_matches']);
    }
    
    /**
     * Add custom cron schedules
     */
    public static function add_cron_schedules($schedules) {
        // 2 seconds interval for live matches
        $schedules['cricket_two_seconds'] = [
            'interval' => 2,
            'display' => __('Every 2 Seconds', 'cricket-live-scores')
        ];
        
        // 1 minute interval
        $schedules['cricket_one_minute'] = [
            'interval' => 60,
            'display' => __('Every 1 Minute', 'cricket-live-scores')
        ];
        
        // 5 minutes interval
        $schedules['cricket_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'cricket-live-scores')
        ];
        
        // 1 hour interval
        $schedules['cricket_hourly'] = [
            'interval' => 3600,
            'display' => __('Every Hour', 'cricket-live-scores')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all cron events
     */
    public static function schedule_events() {
        // Live matches - every 2 seconds
        if (!wp_next_scheduled(self::LIVE_UPDATE_HOOK)) {
            wp_schedule_event(time(), 'cricket_two_seconds', self::LIVE_UPDATE_HOOK);
        }
        
        // Upcoming matches - every hour
        if (!wp_next_scheduled(self::UPCOMING_UPDATE_HOOK)) {
            wp_schedule_event(time(), 'cricket_hourly', self::UPCOMING_UPDATE_HOOK);
        }
        
        // Completed matches - twice daily
        if (!wp_next_scheduled(self::COMPLETED_UPDATE_HOOK)) {
            wp_schedule_event(time(), 'twicedaily', self::COMPLETED_UPDATE_HOOK);
        }
        
        // Cleanup old matches - daily
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
        }
    }
    
    /**
     * Clear all scheduled cron events
     */
    public static function clear_scheduled_events() {
        wp_clear_scheduled_hook(self::LIVE_UPDATE_HOOK);
        wp_clear_scheduled_hook(self::UPCOMING_UPDATE_HOOK);
        wp_clear_scheduled_hook(self::COMPLETED_UPDATE_HOOK);
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }
    
    /**
     * Update live matches - runs every 2 seconds
     */
    public static function update_live_matches() {
        $api = new Cricket_API();
        $db = new Cricket_Database();
        
        $response = $api->get_live_matches();
        
        if (is_wp_error($response)) {
            error_log('Cricket Live Scores: Error fetching live matches - ' . $response->get_error_message());
            return;
        }
        
        if (!empty($response['response']['items'])) {
            foreach ($response['response']['items'] as $match_data) {
                $parsed = $api->parse_match_data($match_data);
                if ($parsed) {
                    $db->upsert_match($parsed);
                }
            }
        }
    }
    
    /**
     * Update upcoming matches - runs every hour
     */
    public static function update_upcoming_matches() {
        $api = new Cricket_API();
        $db = new Cricket_Database();
        
        $response = $api->get_upcoming_matches(50);
        
        if (is_wp_error($response)) {
            error_log('Cricket Live Scores: Error fetching upcoming matches - ' . $response->get_error_message());
            return;
        }
        
        if (!empty($response['response']['items'])) {
            foreach ($response['response']['items'] as $match_data) {
                $parsed = $api->parse_match_data($match_data);
                if ($parsed) {
                    $db->upsert_match($parsed);
                }
            }
        }
    }
    
    /**
     * Update completed matches - runs twice daily
     */
    public static function update_completed_matches() {
        $api = new Cricket_API();
        $db = new Cricket_Database();
        
        $response = $api->get_recent_matches(50);
        
        if (is_wp_error($response)) {
            error_log('Cricket Live Scores: Error fetching completed matches - ' . $response->get_error_message());
            return;
        }
        
        if (!empty($response['response']['items'])) {
            foreach ($response['response']['items'] as $match_data) {
                $parsed = $api->parse_match_data($match_data);
                if ($parsed) {
                    $db->upsert_match($parsed);
                }
            }
        }
    }
    
    /**
     * Cleanup old matches - runs daily
     */
    public static function cleanup_old_matches() {
        $db = new Cricket_Database();
        $deleted = $db->cleanup_old_matches(30);
        
        if ($deleted > 0) {
            error_log('Cricket Live Scores: Cleaned up ' . $deleted . ' old matches');
        }
    }
    
    /**
     * Manual sync trigger (for admin use)
     */
    public static function sync_all_matches() {
        self::update_live_matches();
        self::update_upcoming_matches();
        self::update_completed_matches();
        
        return true;
    }
}
