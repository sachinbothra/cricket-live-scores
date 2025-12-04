<?php
/**
 * Cricket Live Scores - API Class
 *
 * Handles all communication with the Entity Sport API.
 * Includes caching, rate limiting, and error handling.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_API
 *
 * Entity Sport API integration with caching and rate limiting.
 */
class Cricket_API {

    /**
     * API base URL.
     *
     * @var string
     */
    private $base_url = 'https://rest.entitysport.com/v2/';

    /**
     * API token.
     *
     * @var string
     */
    private $token;

    /**
     * Rate limit: requests per minute.
     *
     * @var int
     */
    private const RATE_LIMIT = 60;

    /**
     * Cache duration for live matches (seconds).
     *
     * @var int
     */
    private const CACHE_LIVE = 30;

    /**
     * Cache duration for upcoming matches (seconds).
     *
     * @var int
     */
    private const CACHE_UPCOMING = 3600;

    /**
     * Cache duration for recent matches (seconds).
     *
     * @var int
     */
    private const CACHE_RECENT = 900;

    /**
     * Cache duration for match info (seconds).
     *
     * @var int
     */
    private const CACHE_MATCH = 60;

    /**
     * Singleton instance.
     *
     * @var Cricket_API|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Cricket_API
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->token = get_option('cricket_api_token', '');
        $custom_base_url = get_option('cricket_api_base_url', '');
        if (!empty($custom_base_url)) {
            $this->base_url = trailingslashit($custom_base_url);
        }
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @param int    $cache_duration Cache duration in seconds.
     * @return array|WP_Error Response data or error.
     */
    private function request($endpoint, $params = array(), $cache_duration = 60) {
        // Check rate limit
        if (!$this->check_rate_limit()) {
            $this->log('Rate limit exceeded');
            return new WP_Error('rate_limit', __('API rate limit exceeded. Please try again later.', 'cricket-live-scores'));
        }

        // Generate cache key
        $cache_key = 'cricket_' . md5($endpoint . wp_json_encode($params));
        
        // Check cache
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Build URL
        $params['token'] = $this->token;
        $url = $this->base_url . $endpoint . '?' . http_build_query($params);

        // Make request
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        // Increment rate limit counter
        $this->increment_rate_limit();

        // Handle errors
        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->log('API HTTP Error: ' . $status_code);
            return new WP_Error('http_error', sprintf(
                /* translators: %d: HTTP status code */
                __('API returned HTTP status %d', 'cricket-live-scores'),
                $status_code
            ));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('API JSON Error: ' . json_last_error_msg());
            return new WP_Error('json_error', __('Invalid JSON response from API', 'cricket-live-scores'));
        }

        // Check API response status
        if (isset($data['status']) && $data['status'] === 'error') {
            $message = isset($data['response']['message']) ? $data['response']['message'] : __('Unknown API error', 'cricket-live-scores');
            $this->log('API Response Error: ' . $message);
            return new WP_Error('api_error', $message);
        }

        // Cache successful response
        if ($cache_duration > 0) {
            set_transient($cache_key, $data, $cache_duration);
        }

        return $data;
    }

    /**
     * Check if we're within rate limit.
     *
     * @return bool
     */
    private function check_rate_limit() {
        $count = get_transient('cricket_api_rate_count');
        if ($count === false) {
            return true;
        }
        return (int) $count < self::RATE_LIMIT;
    }

    /**
     * Increment rate limit counter.
     *
     * @return void
     */
    private function increment_rate_limit() {
        $count = get_transient('cricket_api_rate_count');
        if ($count === false) {
            set_transient('cricket_api_rate_count', 1, 60);
        } else {
            set_transient('cricket_api_rate_count', (int) $count + 1, 60);
        }
    }

    /**
     * Log messages for debugging.
     *
     * @param string $message Message to log.
     * @return void
     */
    private function log($message) {
        if (get_option('cricket_enable_debug_logging', 0)) {
            error_log('Cricket Live Scores API: ' . $message);
        }
    }

    /**
     * Test API connection.
     *
     * @return array|WP_Error Test result.
     */
    public function test_connection() {
        if (empty($this->token)) {
            return new WP_Error('no_token', __('API token is not configured', 'cricket-live-scores'));
        }

        $response = $this->request('matches', array('status' => 3, 'per_page' => 1), 0);
        
        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'success' => true,
            'message' => __('API connection successful', 'cricket-live-scores'),
            'data' => $response,
        );
    }

    /**
     * Get live matches.
     *
     * @param array $params Additional parameters.
     * @return array|WP_Error
     */
    public function get_live_matches($params = array()) {
        $default_params = array(
            'status' => 3, // Live status
            'per_page' => 50,
            'paged' => 1,
        );
        
        $params = wp_parse_args($params, $default_params);
        
        return $this->request('matches', $params, self::CACHE_LIVE);
    }

    /**
     * Get upcoming matches.
     *
     * @param array $params Additional parameters.
     * @return array|WP_Error
     */
    public function get_upcoming_matches($params = array()) {
        $default_params = array(
            'status' => 1, // Upcoming status
            'per_page' => 50,
            'paged' => 1,
        );
        
        $params = wp_parse_args($params, $default_params);
        
        return $this->request('matches', $params, self::CACHE_UPCOMING);
    }

    /**
     * Get recent (completed) matches.
     *
     * @param array $params Additional parameters.
     * @return array|WP_Error
     */
    public function get_recent_matches($params = array()) {
        $default_params = array(
            'status' => 2, // Completed status
            'per_page' => 50,
            'paged' => 1,
        );
        
        $params = wp_parse_args($params, $default_params);
        
        return $this->request('matches', $params, self::CACHE_RECENT);
    }

    /**
     * Get match info.
     *
     * @param int $match_id Match ID.
     * @return array|WP_Error
     */
    public function get_match_info($match_id) {
        $match_id = absint($match_id);
        if ($match_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid match ID', 'cricket-live-scores'));
        }

        return $this->request("matches/{$match_id}/info", array(), self::CACHE_MATCH);
    }

    /**
     * Get match scorecard.
     *
     * @param int $match_id Match ID.
     * @return array|WP_Error
     */
    public function get_match_scorecard($match_id) {
        $match_id = absint($match_id);
        if ($match_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid match ID', 'cricket-live-scores'));
        }

        return $this->request("matches/{$match_id}/scorecard", array(), self::CACHE_LIVE);
    }

    /**
     * Get match live data.
     *
     * @param int $match_id Match ID.
     * @return array|WP_Error
     */
    public function get_match_live($match_id) {
        $match_id = absint($match_id);
        if ($match_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid match ID', 'cricket-live-scores'));
        }

        return $this->request("matches/{$match_id}/live", array(), self::CACHE_LIVE);
    }

    /**
     * Get match squad.
     *
     * @param int $match_id Match ID.
     * @return array|WP_Error
     */
    public function get_match_squad($match_id) {
        $match_id = absint($match_id);
        if ($match_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid match ID', 'cricket-live-scores'));
        }

        return $this->request("matches/{$match_id}/squads", array(), self::CACHE_MATCH);
    }

    /**
     * Get competition details.
     *
     * @param int $competition_id Competition ID.
     * @return array|WP_Error
     */
    public function get_competition($competition_id) {
        $competition_id = absint($competition_id);
        if ($competition_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid competition ID', 'cricket-live-scores'));
        }

        return $this->request("competitions/{$competition_id}", array(), self::CACHE_UPCOMING);
    }

    /**
     * Get competition matches.
     *
     * @param int   $competition_id Competition ID.
     * @param array $params Additional parameters.
     * @return array|WP_Error
     */
    public function get_competition_matches($competition_id, $params = array()) {
        $competition_id = absint($competition_id);
        if ($competition_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid competition ID', 'cricket-live-scores'));
        }

        $default_params = array(
            'per_page' => 50,
            'paged' => 1,
        );
        
        $params = wp_parse_args($params, $default_params);

        return $this->request("competitions/{$competition_id}/matches", $params, self::CACHE_RECENT);
    }

    /**
     * Get list of competitions.
     *
     * @param array $params Additional parameters.
     * @return array|WP_Error
     */
    public function get_competitions($params = array()) {
        $default_params = array(
            'per_page' => 50,
            'paged' => 1,
        );
        
        $params = wp_parse_args($params, $default_params);

        return $this->request('competitions', $params, self::CACHE_UPCOMING);
    }

    /**
     * Get featured competitions.
     *
     * @return array|WP_Error
     */
    public function get_featured_competitions() {
        return $this->request('competitions/featured', array(), self::CACHE_UPCOMING);
    }

    /**
     * Get team details.
     *
     * @param int $team_id Team ID.
     * @return array|WP_Error
     */
    public function get_team($team_id) {
        $team_id = absint($team_id);
        if ($team_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid team ID', 'cricket-live-scores'));
        }

        return $this->request("teams/{$team_id}", array(), self::CACHE_UPCOMING);
    }

    /**
     * Get player details.
     *
     * @param int $player_id Player ID.
     * @return array|WP_Error
     */
    public function get_player($player_id) {
        $player_id = absint($player_id);
        if ($player_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid player ID', 'cricket-live-scores'));
        }

        return $this->request("players/{$player_id}", array(), self::CACHE_UPCOMING);
    }

    /**
     * Get match commentary.
     *
     * @param int $match_id Match ID.
     * @param int $innings_id Innings ID.
     * @param int $over_id Over ID (optional).
     * @return array|WP_Error
     */
    public function get_match_commentary($match_id, $innings_id = 1, $over_id = null) {
        $match_id = absint($match_id);
        $innings_id = absint($innings_id);
        
        if ($match_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid match ID', 'cricket-live-scores'));
        }

        $endpoint = "matches/{$match_id}/innings/{$innings_id}/commentary";
        if ($over_id !== null) {
            $endpoint .= '?over=' . absint($over_id);
        }

        return $this->request($endpoint, array(), self::CACHE_LIVE);
    }

    /**
     * Clear all API caches.
     *
     * @return bool
     */
    public function clear_cache() {
        global $wpdb;
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_cricket_%',
                '_transient_timeout_cricket_%'
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $this->log('Cache cleared: ' . $result . ' entries removed');
        
        return true;
    }

    /**
     * Get current rate limit status.
     *
     * @return array
     */
    public function get_rate_limit_status() {
        $count = get_transient('cricket_api_rate_count');
        return array(
            'current' => $count !== false ? (int) $count : 0,
            'limit' => self::RATE_LIMIT,
            'remaining' => self::RATE_LIMIT - ($count !== false ? (int) $count : 0),
        );
    }
}
