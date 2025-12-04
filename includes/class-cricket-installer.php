<?php
/**
 * Cricket Installer Class
 * Handles plugin activation and deactivation
 */

if (!defined('ABSPATH')) exit;

class Cricket_Installer {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Add default options
        self::add_default_options();
        
        // Schedule cron jobs
        Cricket_Cron::schedule_events();
        
        // Add rewrite rules
        self::add_rewrite_rules();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        Cricket_Cron::clear_scheduled_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Matches table
        $matches_table = $wpdb->prefix . 'cricket_matches';
        $sql_matches = "CREATE TABLE $matches_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            match_id varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            short_title varchar(100),
            competition_id varchar(50),
            competition_name varchar(255),
            format varchar(50),
            status varchar(50),
            status_note text,
            venue_name varchar(255),
            venue_city varchar(100),
            venue_country varchar(100),
            date_start datetime,
            date_end datetime,
            teama_id varchar(50),
            teama_name varchar(100),
            teama_short varchar(20),
            teama_logo varchar(500),
            teama_score text,
            teama_overs varchar(20),
            teamb_id varchar(50),
            teamb_name varchar(100),
            teamb_short varchar(20),
            teamb_logo varchar(500),
            teamb_score text,
            teamb_overs varchar(20),
            toss_text varchar(255),
            result text,
            winning_team_id varchar(50),
            live_inning_number int(11),
            live_score text,
            live_wickets int(11),
            live_overs varchar(20),
            current_batsmen text,
            current_bowlers text,
            recent_balls text,
            match_data longtext,
            last_updated datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY match_id (match_id),
            KEY status (status),
            KEY date_start (date_start)
        ) $charset_collate;";
        
        // API usage table
        $api_usage_table = $wpdb->prefix . 'cricket_api_usage';
        $sql_api_usage = "CREATE TABLE $api_usage_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_date date NOT NULL,
            request_count int(11) DEFAULT 0,
            cache_hits int(11) DEFAULT 0,
            last_request datetime,
            PRIMARY KEY (id),
            UNIQUE KEY request_date (request_date)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_matches);
        dbDelta($sql_api_usage);
    }
    
    /**
     * Add default plugin options
     */
    private static function add_default_options() {
        $default_options = [
            'cricket_api_key' => '',
            'cricket_refresh_interval' => 2000, // 2 seconds for live updates
            'cricket_live_cache_duration' => 2, // 2 seconds
            'cricket_upcoming_cache_duration' => 3600, // 1 hour
            'cricket_completed_cache_duration' => 86400, // 24 hours
            'cricket_teams_cache_duration' => 604800, // 7 days
            'cricket_api_rate_limit' => 60, // 60 requests per minute
            'cricket_api_monthly_limit' => 10000, // 10,000 requests per month
        ];
        
        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Add rewrite rules for single match pages
     */
    private static function add_rewrite_rules() {
        add_rewrite_rule(
            '^match/([^/]+)/?$',
            'index.php?cricket_match=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%cricket_match%', '([^&]+)');
    }
}
