<?php
/**
 * Cricket Live Scores - Installer Class
 *
 * Handles plugin activation and deactivation.
 * Creates database tables, sets default options, and manages cron jobs.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_Installer
 *
 * Manages plugin installation, activation, and deactivation processes.
 */
class Cricket_Installer {

    /**
     * Database version for schema updates.
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Plugin activation handler.
     *
     * Creates database tables, sets default options, and flushes rewrite rules.
     *
     * @return void
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        flush_rewrite_rules();
        
        // Log activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Cricket Live Scores: Plugin activated successfully');
        }
    }

    /**
     * Plugin deactivation handler.
     *
     * Clears scheduled cron jobs and flushes rewrite rules.
     *
     * @return void
     */
    public static function deactivate() {
        self::clear_cron_jobs();
        flush_rewrite_rules();
        
        // Log deactivation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Cricket Live Scores: Plugin deactivated');
        }
    }

    /**
     * Create all required database tables.
     *
     * Creates 6 tables: matches, competitions, teams, players, squads, scorecard.
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Matches table
        $table_matches = $wpdb->prefix . 'cricket_matches';
        $sql_matches = "CREATE TABLE $table_matches (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            match_id bigint(20) UNSIGNED NOT NULL,
            slug varchar(255) DEFAULT NULL,
            title varchar(500) DEFAULT NULL,
            short_title varchar(255) DEFAULT NULL,
            subtitle varchar(255) DEFAULT NULL,
            match_number varchar(50) DEFAULT NULL,
            format varchar(20) DEFAULT NULL,
            format_str varchar(50) DEFAULT NULL,
            status smallint(2) DEFAULT 1,
            status_str varchar(100) DEFAULT NULL,
            status_note text DEFAULT NULL,
            game_state smallint(2) DEFAULT 0,
            game_state_str varchar(50) DEFAULT NULL,
            domestic smallint(1) DEFAULT 0,
            competition_id bigint(20) UNSIGNED DEFAULT NULL,
            competition_title varchar(255) DEFAULT NULL,
            teama_id bigint(20) UNSIGNED DEFAULT NULL,
            teama_name varchar(255) DEFAULT NULL,
            teama_short_name varchar(50) DEFAULT NULL,
            teama_logo varchar(500) DEFAULT NULL,
            teama_scores varchar(100) DEFAULT NULL,
            teama_overs varchar(20) DEFAULT NULL,
            teamb_id bigint(20) UNSIGNED DEFAULT NULL,
            teamb_name varchar(255) DEFAULT NULL,
            teamb_short_name varchar(50) DEFAULT NULL,
            teamb_logo varchar(500) DEFAULT NULL,
            teamb_scores varchar(100) DEFAULT NULL,
            teamb_overs varchar(20) DEFAULT NULL,
            toss_text varchar(255) DEFAULT NULL,
            result text DEFAULT NULL,
            winning_team_id bigint(20) UNSIGNED DEFAULT NULL,
            venue_id bigint(20) UNSIGNED DEFAULT NULL,
            venue_name varchar(255) DEFAULT NULL,
            venue_location varchar(255) DEFAULT NULL,
            venue_country varchar(100) DEFAULT NULL,
            date_start datetime DEFAULT NULL,
            date_end datetime DEFAULT NULL,
            timestamp_start bigint(20) DEFAULT NULL,
            timestamp_end bigint(20) DEFAULT NULL,
            date_start_ist varchar(50) DEFAULT NULL,
            live_innings_number smallint(2) DEFAULT NULL,
            day varchar(50) DEFAULT NULL,
            session varchar(50) DEFAULT NULL,
            verified smallint(1) DEFAULT 0,
            pre_squad smallint(1) DEFAULT 0,
            umpires text DEFAULT NULL,
            referee text DEFAULT NULL,
            equation text DEFAULT NULL,
            live text DEFAULT NULL,
            man_of_the_match text DEFAULT NULL,
            man_of_the_series text DEFAULT NULL,
            is_leaderboard smallint(1) DEFAULT 0,
            raw_data longtext DEFAULT NULL,
            last_synced datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY match_id (match_id),
            KEY slug (slug),
            KEY status (status),
            KEY competition_id (competition_id),
            KEY date_start (date_start)
        ) $charset_collate;";
        
        dbDelta($sql_matches);

        // Competitions table
        $table_competitions = $wpdb->prefix . 'cricket_competitions';
        $sql_competitions = "CREATE TABLE $table_competitions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cid bigint(20) UNSIGNED NOT NULL,
            title varchar(255) DEFAULT NULL,
            abbr varchar(50) DEFAULT NULL,
            type varchar(50) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            match_format varchar(50) DEFAULT NULL,
            season varchar(50) DEFAULT NULL,
            status varchar(50) DEFAULT NULL,
            date_start date DEFAULT NULL,
            date_end date DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            total_matches int(11) DEFAULT 0,
            total_rounds int(11) DEFAULT 0,
            total_teams int(11) DEFAULT 0,
            logo varchar(500) DEFAULT NULL,
            raw_data longtext DEFAULT NULL,
            last_synced datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cid (cid),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql_competitions);

        // Teams table
        $table_teams = $wpdb->prefix . 'cricket_teams';
        $sql_teams = "CREATE TABLE $table_teams (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tid bigint(20) UNSIGNED NOT NULL,
            title varchar(255) DEFAULT NULL,
            abbr varchar(50) DEFAULT NULL,
            alt_name varchar(255) DEFAULT NULL,
            type varchar(50) DEFAULT NULL,
            thumb_url varchar(500) DEFAULT NULL,
            logo_url varchar(500) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            sex varchar(20) DEFAULT NULL,
            raw_data longtext DEFAULT NULL,
            last_synced datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tid (tid),
            KEY title (title)
        ) $charset_collate;";
        
        dbDelta($sql_teams);

        // Players table
        $table_players = $wpdb->prefix . 'cricket_players';
        $sql_players = "CREATE TABLE $table_players (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            pid bigint(20) UNSIGNED NOT NULL,
            title varchar(255) DEFAULT NULL,
            short_name varchar(100) DEFAULT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            middle_name varchar(100) DEFAULT NULL,
            birthdate date DEFAULT NULL,
            birthplace varchar(255) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            primary_team text DEFAULT NULL,
            thumb_url varchar(500) DEFAULT NULL,
            logo_url varchar(500) DEFAULT NULL,
            playing_role varchar(100) DEFAULT NULL,
            batting_style varchar(100) DEFAULT NULL,
            bowling_style varchar(100) DEFAULT NULL,
            fielding_position varchar(100) DEFAULT NULL,
            raw_data longtext DEFAULT NULL,
            last_synced datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY pid (pid),
            KEY title (title)
        ) $charset_collate;";
        
        dbDelta($sql_players);

        // Squads table
        $table_squads = $wpdb->prefix . 'cricket_squads';
        $sql_squads = "CREATE TABLE $table_squads (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            match_id bigint(20) UNSIGNED NOT NULL,
            team_id bigint(20) UNSIGNED NOT NULL,
            player_id bigint(20) UNSIGNED NOT NULL,
            player_name varchar(255) DEFAULT NULL,
            role varchar(100) DEFAULT NULL,
            role_str varchar(100) DEFAULT NULL,
            is_playing smallint(1) DEFAULT 0,
            is_captain smallint(1) DEFAULT 0,
            is_keeper smallint(1) DEFAULT 0,
            batting_order int(11) DEFAULT NULL,
            raw_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY match_team_player (match_id, team_id, player_id),
            KEY match_id (match_id),
            KEY team_id (team_id),
            KEY player_id (player_id)
        ) $charset_collate;";
        
        dbDelta($sql_squads);

        // Scorecard table
        $table_scorecard = $wpdb->prefix . 'cricket_scorecard';
        $sql_scorecard = "CREATE TABLE $table_scorecard (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            match_id bigint(20) UNSIGNED NOT NULL,
            innings_id smallint(2) NOT NULL,
            batting_team_id bigint(20) UNSIGNED DEFAULT NULL,
            bowling_team_id bigint(20) UNSIGNED DEFAULT NULL,
            innings_number smallint(2) DEFAULT NULL,
            name varchar(255) DEFAULT NULL,
            short_name varchar(100) DEFAULT NULL,
            status varchar(50) DEFAULT NULL,
            result varchar(50) DEFAULT NULL,
            runs int(11) DEFAULT 0,
            overs varchar(20) DEFAULT NULL,
            wickets int(11) DEFAULT 0,
            target int(11) DEFAULT NULL,
            run_rate varchar(20) DEFAULT NULL,
            required_run_rate varchar(20) DEFAULT NULL,
            extras text DEFAULT NULL,
            equations text DEFAULT NULL,
            batsmen longtext DEFAULT NULL,
            bowlers longtext DEFAULT NULL,
            fows longtext DEFAULT NULL,
            powerplay longtext DEFAULT NULL,
            last_wicket text DEFAULT NULL,
            raw_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY match_innings (match_id, innings_id),
            KEY match_id (match_id),
            KEY batting_team_id (batting_team_id)
        ) $charset_collate;";
        
        dbDelta($sql_scorecard);

        // Store database version
        update_option('cricket_live_scores_db_version', self::DB_VERSION);
        
        // Log table creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Cricket Live Scores: Database tables created/updated');
        }
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private static function set_default_options() {
        // API settings
        add_option('cricket_api_token', '');
        add_option('cricket_api_base_url', 'https://rest.entitysport.com/v2/');
        
        // Refresh intervals (in seconds)
        add_option('cricket_live_refresh_interval', 30);
        add_option('cricket_upcoming_refresh_interval', 3600);
        add_option('cricket_recent_refresh_interval', 900);
        
        // Cache durations (in seconds)
        add_option('cricket_cache_duration_live', 30);
        add_option('cricket_cache_duration_upcoming', 3600);
        add_option('cricket_cache_duration_match', 60);
        add_option('cricket_cache_duration_scorecard', 30);
        
        // Display settings
        add_option('cricket_matches_per_page', 20);
        add_option('cricket_enable_auto_refresh', 1);
        add_option('cricket_frontend_refresh_interval', 2);
        
        // Debug settings
        add_option('cricket_enable_debug_logging', 0);
        
        // Last sync times
        add_option('cricket_last_sync_live', 0);
        add_option('cricket_last_sync_upcoming', 0);
        add_option('cricket_last_sync_recent', 0);
        add_option('cricket_last_sync_competitions', 0);
    }

    /**
     * Clear all scheduled cron jobs.
     *
     * @return void
     */
    private static function clear_cron_jobs() {
        // Clear all cricket-related cron hooks
        $cron_hooks = array(
            'cricket_update_live_matches',
            'cricket_update_upcoming_matches',
            'cricket_update_recent_matches',
            'cricket_update_competitions',
            'cricket_cleanup_old_data',
        );
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            // Clear all instances
            wp_clear_scheduled_hook($hook);
        }
        
        // Log cron cleanup
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Cricket Live Scores: Cron jobs cleared');
        }
    }

    /**
     * Uninstall handler.
     *
     * Removes all database tables and options.
     * Called when plugin is deleted.
     *
     * @return void
     */
    public static function uninstall() {
        global $wpdb;
        
        // Drop all tables
        $tables = array(
            $wpdb->prefix . 'cricket_matches',
            $wpdb->prefix . 'cricket_competitions',
            $wpdb->prefix . 'cricket_teams',
            $wpdb->prefix . 'cricket_players',
            $wpdb->prefix . 'cricket_squads',
            $wpdb->prefix . 'cricket_scorecard',
        );
        
        foreach ($tables as $table) {
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
        }
        
        // Delete all options
        $options = array(
            'cricket_api_token',
            'cricket_api_base_url',
            'cricket_live_refresh_interval',
            'cricket_upcoming_refresh_interval',
            'cricket_recent_refresh_interval',
            'cricket_cache_duration_live',
            'cricket_cache_duration_upcoming',
            'cricket_cache_duration_match',
            'cricket_cache_duration_scorecard',
            'cricket_matches_per_page',
            'cricket_enable_auto_refresh',
            'cricket_frontend_refresh_interval',
            'cricket_enable_debug_logging',
            'cricket_last_sync_live',
            'cricket_last_sync_upcoming',
            'cricket_last_sync_recent',
            'cricket_last_sync_competitions',
            'cricket_live_scores_db_version',
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Clear all transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_cricket_%',
                '_transient_timeout_cricket_%'
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
