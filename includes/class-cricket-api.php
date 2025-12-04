<?php
/**
 * Cricket API Class
 * Handles Entity Sport API integration with rate limiting and caching
 */

if (!defined('ABSPATH')) exit;

class Cricket_API {
    
    private $api_key;
    private $base_url = 'https://rest.entitysport.com/v2/';
    private $db;
    
    // Cache keys
    private const CACHE_PREFIX = 'cricket_api_';
    private const RATE_LIMIT_KEY = 'cricket_api_rate_limit';
    
    public function __construct() {
        $this->api_key = get_option('cricket_api_key', '');
        $this->db = new Cricket_Database();
    }
    
    /**
     * Make API request with rate limiting and caching
     */
    private function make_request($endpoint, $params = [], $cache_duration = 60) {
        // Check if API key is set
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'cricket-live-scores'));
        }
        
        // Build cache key
        $cache_key = self::CACHE_PREFIX . md5($endpoint . serialize($params));
        
        // Check cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->db->increment_cache_hits();
            return $cached;
        }
        
        // Check rate limit
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit', __('API rate limit exceeded. Please try again later.', 'cricket-live-scores'));
        }
        
        // Build URL
        $params['token'] = $this->api_key;
        $url = $this->base_url . $endpoint . '?' . http_build_query($params);
        
        // Make request
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || (isset($data['status']) && $data['status'] === 'error')) {
            return new WP_Error('api_error', $data['message'] ?? __('API request failed', 'cricket-live-scores'));
        }
        
        // Increment API usage
        $this->db->increment_api_usage();
        $this->record_rate_limit_request();
        
        // Cache the response
        set_transient($cache_key, $data, $cache_duration);
        
        return $data;
    }
    
    /**
     * Check if within rate limit
     */
    private function check_rate_limit() {
        $rate_limit = get_option('cricket_api_rate_limit', 60); // 60 requests per minute
        $requests = get_transient(self::RATE_LIMIT_KEY);
        
        if ($requests === false) {
            return true;
        }
        
        return $requests < $rate_limit;
    }
    
    /**
     * Record a rate limit request
     */
    private function record_rate_limit_request() {
        $requests = get_transient(self::RATE_LIMIT_KEY);
        
        if ($requests === false) {
            set_transient(self::RATE_LIMIT_KEY, 1, 60); // Reset every minute
        } else {
            set_transient(self::RATE_LIMIT_KEY, $requests + 1, 60);
        }
    }
    
    /**
     * Get live matches
     */
    public function get_live_matches() {
        $cache_duration = get_option('cricket_live_cache_duration', 2); // 2 seconds for live
        return $this->make_request('matches', ['status' => 2], $cache_duration);
    }
    
    /**
     * Get upcoming matches
     */
    public function get_upcoming_matches($per_page = 25) {
        $cache_duration = get_option('cricket_upcoming_cache_duration', 3600); // 1 hour
        return $this->make_request('matches', [
            'status' => 1,
            'per_page' => $per_page
        ], $cache_duration);
    }
    
    /**
     * Get recent/completed matches
     */
    public function get_recent_matches($per_page = 25) {
        $cache_duration = get_option('cricket_completed_cache_duration', 86400); // 24 hours
        return $this->make_request('matches', [
            'status' => 3,
            'per_page' => $per_page
        ], $cache_duration);
    }
    
    /**
     * Get match details
     */
    public function get_match_details($match_id) {
        $match_id = sanitize_text_field($match_id);
        
        // Determine cache duration based on match status
        $cached_match = $this->db->get_match_by_id($match_id);
        $cache_duration = 3600; // Default 1 hour
        
        if ($cached_match) {
            $status = strtolower($cached_match->status);
            if (in_array($status, ['live', 'in progress'])) {
                $cache_duration = get_option('cricket_live_cache_duration', 2);
            } elseif (in_array($status, ['completed', 'finished'])) {
                $cache_duration = get_option('cricket_completed_cache_duration', 86400);
            }
        }
        
        return $this->make_request('matches/' . $match_id . '/info', [], $cache_duration);
    }
    
    /**
     * Get match live scorecard
     */
    public function get_match_scorecard($match_id) {
        $match_id = sanitize_text_field($match_id);
        $cache_duration = get_option('cricket_live_cache_duration', 2);
        return $this->make_request('matches/' . $match_id . '/live', [], $cache_duration);
    }
    
    /**
     * Get match innings
     */
    public function get_match_innings($match_id, $inning_number = 1) {
        $match_id = sanitize_text_field($match_id);
        $inning_number = intval($inning_number);
        return $this->make_request('matches/' . $match_id . '/innings/' . $inning_number, [], 60);
    }
    
    /**
     * Get competitions/tournaments
     */
    public function get_competitions() {
        $cache_duration = get_option('cricket_teams_cache_duration', 604800); // 7 days
        return $this->make_request('competitions', [], $cache_duration);
    }
    
    /**
     * Get competition details
     */
    public function get_competition($competition_id) {
        $competition_id = sanitize_text_field($competition_id);
        $cache_duration = get_option('cricket_teams_cache_duration', 604800);
        return $this->make_request('competitions/' . $competition_id, [], $cache_duration);
    }
    
    /**
     * Get team details
     */
    public function get_team($team_id) {
        $team_id = sanitize_text_field($team_id);
        $cache_duration = get_option('cricket_teams_cache_duration', 604800);
        return $this->make_request('teams/' . $team_id, [], $cache_duration);
    }
    
    /**
     * Get player details
     */
    public function get_player($player_id) {
        $player_id = sanitize_text_field($player_id);
        $cache_duration = get_option('cricket_teams_cache_duration', 604800);
        return $this->make_request('players/' . $player_id, [], $cache_duration);
    }
    
    /**
     * Clear all API caches
     */
    public function clear_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_" . self::CACHE_PREFIX . "%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_" . self::CACHE_PREFIX . "%'"
        );
    }
    
    /**
     * Parse match data from API response
     */
    public function parse_match_data($api_match) {
        if (empty($api_match)) {
            return null;
        }
        
        $match = [
            'match_id' => $api_match['match_id'] ?? '',
            'title' => $api_match['title'] ?? '',
            'short_title' => $api_match['short_title'] ?? '',
            'competition_id' => $api_match['competition']['cid'] ?? '',
            'competition_name' => $api_match['competition']['title'] ?? '',
            'format' => $api_match['format_str'] ?? '',
            'status' => $this->map_status($api_match['status'] ?? 0),
            'status_note' => $api_match['status_note'] ?? '',
            'venue_name' => $api_match['venue']['name'] ?? '',
            'venue_city' => $api_match['venue']['city'] ?? '',
            'venue_country' => $api_match['venue']['country'] ?? '',
            'date_start' => isset($api_match['date_start']) ? date('Y-m-d H:i:s', strtotime($api_match['date_start'])) : null,
            'date_end' => isset($api_match['date_end']) ? date('Y-m-d H:i:s', strtotime($api_match['date_end'])) : null,
            'teama_id' => $api_match['teama']['team_id'] ?? '',
            'teama_name' => $api_match['teama']['name'] ?? '',
            'teama_short' => $api_match['teama']['short_name'] ?? '',
            'teama_logo' => $api_match['teama']['logo_url'] ?? '',
            'teama_score' => $api_match['teama']['scores_full'] ?? '',
            'teama_overs' => $api_match['teama']['overs'] ?? '',
            'teamb_id' => $api_match['teamb']['team_id'] ?? '',
            'teamb_name' => $api_match['teamb']['name'] ?? '',
            'teamb_short' => $api_match['teamb']['short_name'] ?? '',
            'teamb_logo' => $api_match['teamb']['logo_url'] ?? '',
            'teamb_score' => $api_match['teamb']['scores_full'] ?? '',
            'teamb_overs' => $api_match['teamb']['overs'] ?? '',
            'toss_text' => $api_match['toss']['text'] ?? '',
            'result' => $api_match['result'] ?? '',
            'winning_team_id' => $api_match['winning_team_id'] ?? '',
            'match_data' => $api_match,
        ];
        
        // Add live data if available
        if (isset($api_match['live'])) {
            $match['live_inning_number'] = $api_match['live']['inning_number'] ?? 0;
            $match['live_score'] = $api_match['live']['score'] ?? '';
            $match['live_wickets'] = $api_match['live']['wickets'] ?? 0;
            $match['live_overs'] = $api_match['live']['overs'] ?? '';
            $match['current_batsmen'] = $api_match['live']['batsmen'] ?? [];
            $match['current_bowlers'] = $api_match['live']['bowlers'] ?? [];
            $match['recent_balls'] = $api_match['live']['recent_balls'] ?? [];
        }
        
        return $match;
    }
    
    /**
     * Map API status code to string
     */
    private function map_status($status_code) {
        $statuses = [
            1 => 'Scheduled',
            2 => 'Live',
            3 => 'Completed',
            4 => 'Cancelled',
        ];
        
        return $statuses[$status_code] ?? 'Unknown';
    }
}
