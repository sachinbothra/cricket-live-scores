<?php
/**
 * Cricket Live Scores - Cron Class
 *
 * Handles all scheduled tasks for syncing data with the API.
 * Manages custom cron schedules and sync operations.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_Cron
 *
 * Cron job management and data synchronization.
 */
class Cricket_Cron {

    /**
     * API instance.
     *
     * @var Cricket_API
     */
    private static $api;

    /**
     * Database instance.
     *
     * @var Cricket_Database
     */
    private static $db;

    /**
     * Initialize the cron system.
     *
     * @return void
     */
    public static function init() {
        // Register custom cron schedules
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules'));
        
        // Schedule events on init
        add_action('init', array(__CLASS__, 'schedule_events'));
        
        // Register cron hooks
        add_action('cricket_update_live_matches', array(__CLASS__, 'update_live_matches'));
        add_action('cricket_update_upcoming_matches', array(__CLASS__, 'update_upcoming_matches'));
        add_action('cricket_update_recent_matches', array(__CLASS__, 'update_recent_matches'));
        add_action('cricket_update_competitions', array(__CLASS__, 'update_competitions'));
        add_action('cricket_cleanup_old_data', array(__CLASS__, 'cleanup_old_data'));
        
        // Initialize API and DB
        self::$api = Cricket_API::get_instance();
        self::$db = Cricket_Database::get_instance();
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public static function add_cron_schedules($schedules) {
        // Every 30 seconds for live matches
        $schedules['cricket_30_seconds'] = array(
            'interval' => 30,
            'display' => __('Every 30 Seconds', 'cricket-live-scores'),
        );
        
        // Every 15 minutes for recent matches
        $schedules['cricket_15_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'cricket-live-scores'),
        );
        
        return $schedules;
    }

    /**
     * Schedule all cron events.
     *
     * @return void
     */
    public static function schedule_events() {
        // Only schedule if API token is configured
        $token = get_option('cricket_api_token', '');
        if (empty($token)) {
            return;
        }

        // Live matches - every 30 seconds
        if (!wp_next_scheduled('cricket_update_live_matches')) {
            wp_schedule_event(time(), 'cricket_30_seconds', 'cricket_update_live_matches');
        }
        
        // Upcoming matches - hourly
        if (!wp_next_scheduled('cricket_update_upcoming_matches')) {
            wp_schedule_event(time(), 'hourly', 'cricket_update_upcoming_matches');
        }
        
        // Recent matches - every 15 minutes
        if (!wp_next_scheduled('cricket_update_recent_matches')) {
            wp_schedule_event(time(), 'cricket_15_minutes', 'cricket_update_recent_matches');
        }
        
        // Competitions - daily
        if (!wp_next_scheduled('cricket_update_competitions')) {
            wp_schedule_event(time(), 'daily', 'cricket_update_competitions');
        }
        
        // Cleanup old data - daily
        if (!wp_next_scheduled('cricket_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'cricket_cleanup_old_data');
        }
    }

    /**
     * Update live matches.
     * Runs every 30 seconds.
     *
     * @return void
     */
    public static function update_live_matches() {
        self::log('Starting live matches sync');
        
        $response = self::$api->get_live_matches();
        
        if (is_wp_error($response)) {
            self::log('Error fetching live matches: ' . $response->get_error_message());
            return;
        }

        $matches = isset($response['response']['items']) ? $response['response']['items'] : array();
        $count = 0;

        foreach ($matches as $match) {
            $result = self::$db->upsert_match($match);
            if ($result) {
                $count++;
                
                // Update match scorecard for live matches
                self::update_match_scorecard($match['match_id']);
            }
        }

        update_option('cricket_last_sync_live', time());
        self::log("Live matches sync completed. Updated: {$count} matches");
    }

    /**
     * Update upcoming matches.
     * Runs hourly.
     *
     * @return void
     */
    public static function update_upcoming_matches() {
        self::log('Starting upcoming matches sync');
        
        $response = self::$api->get_upcoming_matches();
        
        if (is_wp_error($response)) {
            self::log('Error fetching upcoming matches: ' . $response->get_error_message());
            return;
        }

        $matches = isset($response['response']['items']) ? $response['response']['items'] : array();
        $count = 0;

        foreach ($matches as $match) {
            $result = self::$db->upsert_match($match);
            if ($result) {
                $count++;
                
                // Update teams
                self::update_match_teams($match);
            }
        }

        update_option('cricket_last_sync_upcoming', time());
        self::log("Upcoming matches sync completed. Updated: {$count} matches");
    }

    /**
     * Update recent (completed) matches.
     * Runs every 15 minutes.
     *
     * @return void
     */
    public static function update_recent_matches() {
        self::log('Starting recent matches sync');
        
        $response = self::$api->get_recent_matches();
        
        if (is_wp_error($response)) {
            self::log('Error fetching recent matches: ' . $response->get_error_message());
            return;
        }

        $matches = isset($response['response']['items']) ? $response['response']['items'] : array();
        $count = 0;

        foreach ($matches as $match) {
            $result = self::$db->upsert_match($match);
            if ($result) {
                $count++;
            }
        }

        update_option('cricket_last_sync_recent', time());
        self::log("Recent matches sync completed. Updated: {$count} matches");
    }

    /**
     * Update competitions.
     * Runs daily.
     *
     * @return void
     */
    public static function update_competitions() {
        self::log('Starting competitions sync');
        
        // Get featured competitions
        $response = self::$api->get_featured_competitions();
        
        if (is_wp_error($response)) {
            self::log('Error fetching featured competitions: ' . $response->get_error_message());
            return;
        }

        $competitions = isset($response['response']['items']) ? $response['response']['items'] : array();
        $count = 0;

        foreach ($competitions as $competition) {
            $result = self::$db->upsert_competition($competition);
            if ($result) {
                $count++;
            }
        }

        // Also fetch active competitions
        $response = self::$api->get_competitions(array('status' => 'live'));
        
        if (!is_wp_error($response)) {
            $active_competitions = isset($response['response']['items']) ? $response['response']['items'] : array();
            
            foreach ($active_competitions as $competition) {
                $result = self::$db->upsert_competition($competition);
                if ($result) {
                    $count++;
                }
            }
        }

        update_option('cricket_last_sync_competitions', time());
        self::log("Competitions sync completed. Updated: {$count} competitions");
    }

    /**
     * Cleanup old data.
     * Runs daily.
     *
     * @return void
     */
    public static function cleanup_old_data() {
        self::log('Starting old data cleanup');
        
        // Delete matches older than 30 days
        $deleted = self::$db->delete_old_matches(30);
        
        self::log("Cleanup completed. Deleted: {$deleted} old matches");
    }

    /**
     * Update teams from match data.
     *
     * @param array $match Match data.
     * @return void
     */
    private static function update_match_teams($match) {
        // Update Team A
        if (!empty($match['teama']['team_id'])) {
            $team_data = array(
                'tid' => $match['teama']['team_id'],
                'title' => isset($match['teama']['name']) ? $match['teama']['name'] : '',
                'abbr' => isset($match['teama']['short_name']) ? $match['teama']['short_name'] : '',
                'logo_url' => isset($match['teama']['logo_url']) ? $match['teama']['logo_url'] : '',
            );
            self::$db->upsert_team($team_data);
        }
        
        // Update Team B
        if (!empty($match['teamb']['team_id'])) {
            $team_data = array(
                'tid' => $match['teamb']['team_id'],
                'title' => isset($match['teamb']['name']) ? $match['teamb']['name'] : '',
                'abbr' => isset($match['teamb']['short_name']) ? $match['teamb']['short_name'] : '',
                'logo_url' => isset($match['teamb']['logo_url']) ? $match['teamb']['logo_url'] : '',
            );
            self::$db->upsert_team($team_data);
        }
    }

    /**
     * Update match scorecard.
     *
     * @param int $match_id Match ID.
     * @return void
     */
    private static function update_match_scorecard($match_id) {
        $response = self::$api->get_match_scorecard($match_id);
        
        if (is_wp_error($response)) {
            return;
        }

        $innings_list = isset($response['response']['innings']) ? $response['response']['innings'] : array();
        
        foreach ($innings_list as $innings) {
            self::$db->upsert_scorecard($match_id, $innings);
        }
    }

    /**
     * Update match squad.
     *
     * @param int $match_id Match ID.
     * @return void
     */
    public static function update_match_squad($match_id) {
        $response = self::$api->get_match_squad($match_id);
        
        if (is_wp_error($response)) {
            return;
        }

        $squads = isset($response['response']['squads']) ? $response['response']['squads'] : array();
        
        foreach ($squads as $team_squad) {
            $team_id = isset($team_squad['team_id']) ? $team_squad['team_id'] : 0;
            $players = isset($team_squad['players']) ? $team_squad['players'] : array();
            
            foreach ($players as $player) {
                self::$db->upsert_squad_member($match_id, $team_id, $player);
            }
        }
    }

    /**
     * Manual sync trigger.
     *
     * @param string $type Sync type (live, upcoming, recent, competitions, all).
     * @return array
     */
    public static function manual_sync($type = 'all') {
        $results = array();
        
        if ($type === 'live' || $type === 'all') {
            self::update_live_matches();
            $results['live'] = true;
        }
        
        if ($type === 'upcoming' || $type === 'all') {
            self::update_upcoming_matches();
            $results['upcoming'] = true;
        }
        
        if ($type === 'recent' || $type === 'all') {
            self::update_recent_matches();
            $results['recent'] = true;
        }
        
        if ($type === 'competitions' || $type === 'all') {
            self::update_competitions();
            $results['competitions'] = true;
        }
        
        return $results;
    }

    /**
     * Get sync status.
     *
     * @return array
     */
    public static function get_sync_status() {
        return array(
            'live' => array(
                'last_sync' => get_option('cricket_last_sync_live', 0),
                'next_scheduled' => wp_next_scheduled('cricket_update_live_matches'),
            ),
            'upcoming' => array(
                'last_sync' => get_option('cricket_last_sync_upcoming', 0),
                'next_scheduled' => wp_next_scheduled('cricket_update_upcoming_matches'),
            ),
            'recent' => array(
                'last_sync' => get_option('cricket_last_sync_recent', 0),
                'next_scheduled' => wp_next_scheduled('cricket_update_recent_matches'),
            ),
            'competitions' => array(
                'last_sync' => get_option('cricket_last_sync_competitions', 0),
                'next_scheduled' => wp_next_scheduled('cricket_update_competitions'),
            ),
        );
    }

    /**
     * Log cron messages.
     *
     * @param string $message Log message.
     * @return void
     */
    private static function log($message) {
        if (get_option('cricket_enable_debug_logging', 0)) {
            error_log('Cricket Live Scores Cron: ' . $message);
        }
    }

    /**
     * Sync specific match by ID.
     *
     * @param int $match_id Match ID.
     * @return bool
     */
    public static function sync_match($match_id) {
        $match_id = absint($match_id);
        
        self::log("Syncing match: {$match_id}");
        
        $response = self::$api->get_match_info($match_id);
        
        if (is_wp_error($response)) {
            self::log('Error syncing match: ' . $response->get_error_message());
            return false;
        }

        $match = isset($response['response']) ? $response['response'] : null;
        
        if ($match) {
            $result = self::$db->upsert_match($match);
            
            // Update related data
            self::update_match_teams($match);
            self::update_match_scorecard($match_id);
            self::update_match_squad($match_id);
            
            return $result !== false;
        }
        
        return false;
    }
}
