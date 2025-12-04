<?php
/**
 * Cricket Live Scores - AJAX Class
 *
 * Handles all AJAX requests for live data updates.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_Ajax
 *
 * Frontend AJAX handlers.
 */
class Cricket_Ajax {

    /**
     * Database instance.
     *
     * @var Cricket_Database
     */
    private static $db;

    /**
     * API instance.
     *
     * @var Cricket_API
     */
    private static $api;

    /**
     * Initialize AJAX handlers.
     *
     * @return void
     */
    public static function init() {
        self::$db = Cricket_Database::get_instance();
        self::$api = Cricket_API::get_instance();
        
        // Public AJAX actions (no login required)
        add_action('wp_ajax_cricket_get_live_match_data', array(__CLASS__, 'get_live_match_data'));
        add_action('wp_ajax_nopriv_cricket_get_live_match_data', array(__CLASS__, 'get_live_match_data'));
        
        add_action('wp_ajax_cricket_get_live_matches', array(__CLASS__, 'get_live_matches'));
        add_action('wp_ajax_nopriv_cricket_get_live_matches', array(__CLASS__, 'get_live_matches'));
        
        add_action('wp_ajax_cricket_get_match_scorecard', array(__CLASS__, 'get_match_scorecard'));
        add_action('wp_ajax_nopriv_cricket_get_match_scorecard', array(__CLASS__, 'get_match_scorecard'));
        
        add_action('wp_ajax_cricket_get_match_squad', array(__CLASS__, 'get_match_squad'));
        add_action('wp_ajax_nopriv_cricket_get_match_squad', array(__CLASS__, 'get_match_squad'));
        
        add_action('wp_ajax_cricket_get_all_matches', array(__CLASS__, 'get_all_matches'));
        add_action('wp_ajax_nopriv_cricket_get_all_matches', array(__CLASS__, 'get_all_matches'));
    }

    /**
     * Verify AJAX nonce.
     *
     * @return bool
     */
    private static function verify_nonce() {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        return wp_verify_nonce($nonce, 'cricket_ajax_nonce');
    }

    /**
     * Get live match data for auto-refresh.
     * Lightweight endpoint for 2-second refresh.
     *
     * @return void
     */
    public static function get_live_match_data() {
        if (!self::verify_nonce()) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cricket-live-scores')));
        }

        $match_id = isset($_GET['match_id']) ? absint($_GET['match_id']) : 0;
        
        if (!$match_id) {
            wp_send_json_error(array('message' => __('Match ID required.', 'cricket-live-scores')));
        }

        $data = self::$db->get_live_match_data($match_id);
        
        if (!$data) {
            wp_send_json_error(array('message' => __('Match not found.', 'cricket-live-scores')));
        }

        // Get current ball info if available
        $live = $data['live'];
        $current_batsmen = array();
        $current_bowler = null;
        $last_balls = array();
        
        if ($live) {
            // Extract batsmen on crease
            if (isset($live['batsmen']) && is_array($live['batsmen'])) {
                foreach ($live['batsmen'] as $batsman) {
                    $current_batsmen[] = array(
                        'name' => isset($batsman['name']) ? $batsman['name'] : '',
                        'runs' => isset($batsman['runs']) ? $batsman['runs'] : 0,
                        'balls' => isset($batsman['balls_faced']) ? $batsman['balls_faced'] : 0,
                        'fours' => isset($batsman['fours']) ? $batsman['fours'] : 0,
                        'sixes' => isset($batsman['sixes']) ? $batsman['sixes'] : 0,
                        'strike_rate' => isset($batsman['strike_rate']) ? $batsman['strike_rate'] : 0,
                        'on_strike' => isset($batsman['on_strike']) ? $batsman['on_strike'] : false,
                    );
                }
            }
            
            // Extract current bowler
            if (isset($live['bowlers']) && is_array($live['bowlers']) && !empty($live['bowlers'])) {
                $bowler = $live['bowlers'][0];
                $current_bowler = array(
                    'name' => isset($bowler['name']) ? $bowler['name'] : '',
                    'overs' => isset($bowler['overs']) ? $bowler['overs'] : 0,
                    'maidens' => isset($bowler['maidens']) ? $bowler['maidens'] : 0,
                    'runs' => isset($bowler['runs_conceded']) ? $bowler['runs_conceded'] : 0,
                    'wickets' => isset($bowler['wickets']) ? $bowler['wickets'] : 0,
                    'economy' => isset($bowler['econ']) ? $bowler['econ'] : 0,
                );
            }
            
            // Last 6 balls
            if (isset($live['last_6_balls']) && is_array($live['last_6_balls'])) {
                $last_balls = $live['last_6_balls'];
            }
        }

        wp_send_json_success(array(
            'match_id' => $data['match_id'],
            'status' => $data['status'],
            'status_str' => $data['status_str'],
            'status_note' => $data['status_note'],
            'teama' => array(
                'scores' => $data['teama_scores'],
                'overs' => $data['teama_overs'],
            ),
            'teamb' => array(
                'scores' => $data['teamb_scores'],
                'overs' => $data['teamb_overs'],
            ),
            'live_innings_number' => $data['live_innings_number'],
            'batsmen' => $current_batsmen,
            'bowler' => $current_bowler,
            'last_balls' => $last_balls,
            'last_synced' => $data['last_synced'],
        ));
    }

    /**
     * Get all live matches.
     * Used for widget refresh.
     *
     * @return void
     */
    public static function get_live_matches() {
        if (!self::verify_nonce()) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cricket-live-scores')));
        }

        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 20;
        $matches = self::$db->get_live_matches($limit);
        
        $data = array();
        foreach ($matches as $match) {
            $data[] = self::format_match_for_json($match);
        }

        wp_send_json_success(array(
            'matches' => $data,
            'count' => count($data),
        ));
    }

    /**
     * Get match scorecard.
     *
     * @return void
     */
    public static function get_match_scorecard() {
        if (!self::verify_nonce()) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cricket-live-scores')));
        }

        $match_id = isset($_GET['match_id']) ? absint($_GET['match_id']) : 0;
        
        if (!$match_id) {
            wp_send_json_error(array('message' => __('Match ID required.', 'cricket-live-scores')));
        }

        $scorecard = self::$db->get_match_scorecard($match_id);
        
        if (empty($scorecard)) {
            wp_send_json_error(array('message' => __('Scorecard not available.', 'cricket-live-scores')));
        }

        $innings_data = array();
        foreach ($scorecard as $innings) {
            $innings_data[] = array(
                'innings_id' => $innings->innings_id,
                'innings_number' => $innings->innings_number,
                'name' => $innings->name,
                'short_name' => $innings->short_name,
                'runs' => $innings->runs,
                'wickets' => $innings->wickets,
                'overs' => $innings->overs,
                'run_rate' => $innings->run_rate,
                'target' => $innings->target,
                'extras' => self::$db->decode_json($innings->extras),
                'batsmen' => self::$db->decode_json($innings->batsmen),
                'bowlers' => self::$db->decode_json($innings->bowlers),
                'fows' => self::$db->decode_json($innings->fows),
            );
        }

        wp_send_json_success(array(
            'match_id' => $match_id,
            'innings' => $innings_data,
        ));
    }

    /**
     * Get match squad.
     *
     * @return void
     */
    public static function get_match_squad() {
        if (!self::verify_nonce()) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cricket-live-scores')));
        }

        $match_id = isset($_GET['match_id']) ? absint($_GET['match_id']) : 0;
        
        if (!$match_id) {
            wp_send_json_error(array('message' => __('Match ID required.', 'cricket-live-scores')));
        }

        $squad = self::$db->get_match_squad($match_id);
        
        if (empty($squad)) {
            wp_send_json_error(array('message' => __('Squad not available.', 'cricket-live-scores')));
        }

        // Group by team
        $teams = array();
        foreach ($squad as $player) {
            $team_id = $player->team_id;
            if (!isset($teams[$team_id])) {
                $teams[$team_id] = array(
                    'team_id' => $team_id,
                    'players' => array(),
                );
            }
            
            $teams[$team_id]['players'][] = array(
                'player_id' => $player->player_id,
                'name' => $player->player_name,
                'role' => $player->role,
                'role_str' => $player->role_str,
                'is_playing' => (bool) $player->is_playing,
                'is_captain' => (bool) $player->is_captain,
                'is_keeper' => (bool) $player->is_keeper,
            );
        }

        wp_send_json_success(array(
            'match_id' => $match_id,
            'teams' => array_values($teams),
        ));
    }

    /**
     * Get all matches with filters.
     *
     * @return void
     */
    public static function get_all_matches() {
        if (!self::verify_nonce()) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cricket-live-scores')));
        }

        $args = array(
            'status' => isset($_GET['status']) ? absint($_GET['status']) : null,
            'competition_id' => isset($_GET['competition_id']) ? absint($_GET['competition_id']) : null,
            'format' => isset($_GET['format']) ? sanitize_text_field(wp_unslash($_GET['format'])) : null,
            'search' => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : null,
            'limit' => isset($_GET['limit']) ? absint($_GET['limit']) : 20,
            'offset' => isset($_GET['offset']) ? absint($_GET['offset']) : 0,
        );

        $matches = self::$db->get_matches($args);
        
        $data = array();
        foreach ($matches as $match) {
            $data[] = self::format_match_for_json($match);
        }

        wp_send_json_success(array(
            'matches' => $data,
            'count' => count($data),
        ));
    }

    /**
     * Format match object for JSON response.
     *
     * @param object $match Match object.
     * @return array
     */
    private static function format_match_for_json($match) {
        return array(
            'match_id' => $match->match_id,
            'title' => $match->title,
            'short_title' => $match->short_title,
            'subtitle' => $match->subtitle,
            'format' => $match->format,
            'format_str' => $match->format_str,
            'status' => $match->status,
            'status_str' => $match->status_str,
            'status_note' => $match->status_note,
            'competition' => array(
                'id' => $match->competition_id,
                'title' => $match->competition_title,
            ),
            'teama' => array(
                'id' => $match->teama_id,
                'name' => $match->teama_name,
                'short_name' => $match->teama_short_name,
                'logo' => $match->teama_logo,
                'scores' => $match->teama_scores,
                'overs' => $match->teama_overs,
            ),
            'teamb' => array(
                'id' => $match->teamb_id,
                'name' => $match->teamb_name,
                'short_name' => $match->teamb_short_name,
                'logo' => $match->teamb_logo,
                'scores' => $match->teamb_scores,
                'overs' => $match->teamb_overs,
            ),
            'venue' => array(
                'name' => $match->venue_name,
                'location' => $match->venue_location,
                'country' => $match->venue_country,
            ),
            'date_start' => $match->date_start,
            'date_end' => $match->date_end,
            'result' => $match->result,
        );
    }
}
