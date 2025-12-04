<?php
/**
 * Cricket Live Scores - Database Class
 *
 * Handles all database operations for the plugin.
 * Provides CRUD operations for matches, competitions, teams, players, squads, and scorecards.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_Database
 *
 * Database operations handler.
 */
class Cricket_Database {

    /**
     * Singleton instance.
     *
     * @var Cricket_Database|null
     */
    private static $instance = null;

    /**
     * WordPress database object.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Table names.
     *
     * @var array
     */
    private $tables;

    /**
     * Get singleton instance.
     *
     * @return Cricket_Database
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
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->tables = array(
            'matches' => $wpdb->prefix . 'cricket_matches',
            'competitions' => $wpdb->prefix . 'cricket_competitions',
            'teams' => $wpdb->prefix . 'cricket_teams',
            'players' => $wpdb->prefix . 'cricket_players',
            'squads' => $wpdb->prefix . 'cricket_squads',
            'scorecard' => $wpdb->prefix . 'cricket_scorecard',
        );
    }

    /**
     * Get table name.
     *
     * @param string $table Table key.
     * @return string
     */
    public function get_table($table) {
        return isset($this->tables[$table]) ? $this->tables[$table] : '';
    }

    // ==========================================
    // MATCH OPERATIONS
    // ==========================================

    /**
     * Upsert a match (insert or update).
     *
     * @param array $data Match data from API.
     * @return int|false Match ID or false on failure.
     */
    public function upsert_match($data) {
        if (empty($data['match_id'])) {
            return false;
        }

        $match_id = absint($data['match_id']);
        $existing = $this->get_match_by_api_id($match_id);

        $prepared_data = $this->prepare_match_data($data);
        
        if ($existing) {
            // Update existing match
            $result = $this->wpdb->update(
                $this->tables['matches'],
                $prepared_data,
                array('match_id' => $match_id),
                $this->get_match_format(),
                array('%d')
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result !== false ? $existing->id : false;
        } else {
            // Insert new match
            $result = $this->wpdb->insert(
                $this->tables['matches'],
                $prepared_data,
                $this->get_match_format()
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Prepare match data for database.
     *
     * @param array $data Raw match data.
     * @return array Prepared data.
     */
    private function prepare_match_data($data) {
        return array(
            'match_id' => absint($data['match_id']),
            'slug' => isset($data['slug']) ? sanitize_title($data['slug']) : null,
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'short_title' => isset($data['short_title']) ? sanitize_text_field($data['short_title']) : null,
            'subtitle' => isset($data['subtitle']) ? sanitize_text_field($data['subtitle']) : null,
            'match_number' => isset($data['match_number']) ? sanitize_text_field($data['match_number']) : null,
            'format' => isset($data['format']) ? sanitize_text_field($data['format']) : null,
            'format_str' => isset($data['format_str']) ? sanitize_text_field($data['format_str']) : null,
            'status' => isset($data['status']) ? absint($data['status']) : 1,
            'status_str' => isset($data['status_str']) ? sanitize_text_field($data['status_str']) : null,
            'status_note' => isset($data['status_note']) ? sanitize_textarea_field($data['status_note']) : null,
            'game_state' => isset($data['game_state']) ? absint($data['game_state']) : 0,
            'game_state_str' => isset($data['game_state_str']) ? sanitize_text_field($data['game_state_str']) : null,
            'domestic' => isset($data['domestic']) ? absint($data['domestic']) : 0,
            'competition_id' => isset($data['competition']['cid']) ? absint($data['competition']['cid']) : null,
            'competition_title' => isset($data['competition']['title']) ? sanitize_text_field($data['competition']['title']) : null,
            'teama_id' => isset($data['teama']['team_id']) ? absint($data['teama']['team_id']) : null,
            'teama_name' => isset($data['teama']['name']) ? sanitize_text_field($data['teama']['name']) : null,
            'teama_short_name' => isset($data['teama']['short_name']) ? sanitize_text_field($data['teama']['short_name']) : null,
            'teama_logo' => isset($data['teama']['logo_url']) ? esc_url_raw($data['teama']['logo_url']) : null,
            'teama_scores' => isset($data['teama']['scores']) ? sanitize_text_field($data['teama']['scores']) : null,
            'teama_overs' => isset($data['teama']['overs']) ? sanitize_text_field($data['teama']['overs']) : null,
            'teamb_id' => isset($data['teamb']['team_id']) ? absint($data['teamb']['team_id']) : null,
            'teamb_name' => isset($data['teamb']['name']) ? sanitize_text_field($data['teamb']['name']) : null,
            'teamb_short_name' => isset($data['teamb']['short_name']) ? sanitize_text_field($data['teamb']['short_name']) : null,
            'teamb_logo' => isset($data['teamb']['logo_url']) ? esc_url_raw($data['teamb']['logo_url']) : null,
            'teamb_scores' => isset($data['teamb']['scores']) ? sanitize_text_field($data['teamb']['scores']) : null,
            'teamb_overs' => isset($data['teamb']['overs']) ? sanitize_text_field($data['teamb']['overs']) : null,
            'toss_text' => isset($data['toss']['text']) ? sanitize_text_field($data['toss']['text']) : null,
            'result' => isset($data['result']) ? sanitize_textarea_field($data['result']) : null,
            'winning_team_id' => isset($data['winning_team_id']) ? absint($data['winning_team_id']) : null,
            'venue_id' => isset($data['venue']['venue_id']) ? absint($data['venue']['venue_id']) : null,
            'venue_name' => isset($data['venue']['name']) ? sanitize_text_field($data['venue']['name']) : null,
            'venue_location' => isset($data['venue']['location']) ? sanitize_text_field($data['venue']['location']) : null,
            'venue_country' => isset($data['venue']['country']) ? sanitize_text_field($data['venue']['country']) : null,
            'date_start' => isset($data['date_start']) ? sanitize_text_field($data['date_start']) : null,
            'date_end' => isset($data['date_end']) ? sanitize_text_field($data['date_end']) : null,
            'timestamp_start' => isset($data['timestamp_start']) ? absint($data['timestamp_start']) : null,
            'timestamp_end' => isset($data['timestamp_end']) ? absint($data['timestamp_end']) : null,
            'date_start_ist' => isset($data['date_start_ist']) ? sanitize_text_field($data['date_start_ist']) : null,
            'live_innings_number' => isset($data['live_innings_number']) ? absint($data['live_innings_number']) : null,
            'day' => isset($data['day']) ? sanitize_text_field($data['day']) : null,
            'session' => isset($data['session']) ? sanitize_text_field($data['session']) : null,
            'verified' => isset($data['verified']) ? absint($data['verified']) : 0,
            'pre_squad' => isset($data['pre_squad']) ? absint($data['pre_squad']) : 0,
            'umpires' => isset($data['umpires']) ? wp_json_encode($data['umpires']) : null,
            'referee' => isset($data['referee']) ? wp_json_encode($data['referee']) : null,
            'equation' => isset($data['equation']) ? sanitize_textarea_field($data['equation']) : null,
            'live' => isset($data['live']) ? wp_json_encode($data['live']) : null,
            'man_of_the_match' => isset($data['man_of_the_match']) ? wp_json_encode($data['man_of_the_match']) : null,
            'man_of_the_series' => isset($data['man_of_the_series']) ? wp_json_encode($data['man_of_the_series']) : null,
            'is_leaderboard' => isset($data['is_leaderboard']) ? absint($data['is_leaderboard']) : 0,
            'raw_data' => wp_json_encode($data),
            'last_synced' => current_time('mysql'),
        );
    }

    /**
     * Get format array for match data.
     *
     * @return array
     */
    private function get_match_format() {
        return array(
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
            '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s',
            '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s',
            '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s',
            '%s', '%d', '%s', '%s',
        );
    }

    /**
     * Get match by API ID.
     *
     * @param int $match_id Match ID from API.
     * @return object|null
     */
    public function get_match_by_api_id($match_id) {
        $match_id = absint($match_id);
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE match_id = %d",
                $match_id
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get match by database ID.
     *
     * @param int $id Database ID.
     * @return object|null
     */
    public function get_match_by_id($id) {
        $id = absint($id);
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE id = %d",
                $id
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get match by slug.
     *
     * @param string $slug Match slug.
     * @return object|null
     */
    public function get_match_by_slug($slug) {
        $slug = sanitize_title($slug);
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE slug = %s",
                $slug
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get live matches.
     *
     * @param int $limit Number of matches.
     * @return array
     */
    public function get_live_matches($limit = 50) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE status = 3 ORDER BY date_start DESC LIMIT %d",
                absint($limit)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get upcoming matches.
     *
     * @param int $limit Number of matches.
     * @return array
     */
    public function get_upcoming_matches($limit = 50) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE status = 1 ORDER BY date_start ASC LIMIT %d",
                absint($limit)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get recent (completed) matches.
     *
     * @param int $limit Number of matches.
     * @return array
     */
    public function get_recent_matches($limit = 50) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE status = 2 ORDER BY date_end DESC LIMIT %d",
                absint($limit)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get matches by competition.
     *
     * @param int    $competition_id Competition ID.
     * @param string $status Match status (live, upcoming, recent, all).
     * @param int    $limit Number of matches.
     * @return array
     */
    public function get_matches_by_competition($competition_id, $status = 'all', $limit = 50) {
        $competition_id = absint($competition_id);
        
        $status_clause = '';
        switch ($status) {
            case 'live':
                $status_clause = 'AND status = 3';
                break;
            case 'upcoming':
                $status_clause = 'AND status = 1';
                break;
            case 'recent':
                $status_clause = 'AND status = 2';
                break;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE competition_id = %d {$status_clause} ORDER BY date_start DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $competition_id,
                absint($limit)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get live match data for AJAX refresh.
     *
     * @param int $match_id Match ID.
     * @return array|null
     */
    public function get_live_match_data($match_id) {
        $match = $this->get_match_by_api_id($match_id);
        
        if (!$match) {
            return null;
        }

        return array(
            'match_id' => $match->match_id,
            'status' => $match->status,
            'status_str' => $match->status_str,
            'status_note' => $match->status_note,
            'teama_scores' => $match->teama_scores,
            'teama_overs' => $match->teama_overs,
            'teamb_scores' => $match->teamb_scores,
            'teamb_overs' => $match->teamb_overs,
            'live_innings_number' => $match->live_innings_number,
            'live' => $match->live ? json_decode($match->live, true) : null,
            'last_synced' => $match->last_synced,
        );
    }

    /**
     * Search matches.
     *
     * @param string $search Search term.
     * @param int    $limit Number of results.
     * @return array
     */
    public function search_matches($search, $limit = 20) {
        $search = '%' . $this->wpdb->esc_like(sanitize_text_field($search)) . '%';
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} 
                WHERE title LIKE %s 
                   OR teama_name LIKE %s 
                   OR teamb_name LIKE %s 
                   OR venue_name LIKE %s
                   OR competition_title LIKE %s
                ORDER BY date_start DESC 
                LIMIT %d",
                $search,
                $search,
                $search,
                $search,
                $search,
                absint($limit)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Delete old matches.
     *
     * @param int $days Days to keep.
     * @return int Number of deleted rows.
     */
    public function delete_old_matches($days = 30) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['matches']} WHERE status = 2 AND date_end < DATE_SUB(NOW(), INTERVAL %d DAY)",
                absint($days)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ==========================================
    // COMPETITION OPERATIONS
    // ==========================================

    /**
     * Upsert a competition.
     *
     * @param array $data Competition data.
     * @return int|false
     */
    public function upsert_competition($data) {
        if (empty($data['cid'])) {
            return false;
        }

        $cid = absint($data['cid']);
        $existing = $this->get_competition_by_cid($cid);

        $prepared_data = array(
            'cid' => $cid,
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'abbr' => isset($data['abbr']) ? sanitize_text_field($data['abbr']) : null,
            'type' => isset($data['type']) ? sanitize_text_field($data['type']) : null,
            'category' => isset($data['category']) ? sanitize_text_field($data['category']) : null,
            'match_format' => isset($data['match_format']) ? sanitize_text_field($data['match_format']) : null,
            'season' => isset($data['season']) ? sanitize_text_field($data['season']) : null,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : null,
            'date_start' => isset($data['datestart']) ? sanitize_text_field($data['datestart']) : null,
            'date_end' => isset($data['dateend']) ? sanitize_text_field($data['dateend']) : null,
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : null,
            'total_matches' => isset($data['total_matches']) ? absint($data['total_matches']) : 0,
            'total_rounds' => isset($data['total_rounds']) ? absint($data['total_rounds']) : 0,
            'total_teams' => isset($data['total_teams']) ? absint($data['total_teams']) : 0,
            'logo' => isset($data['logo_url']) ? esc_url_raw($data['logo_url']) : null,
            'raw_data' => wp_json_encode($data),
            'last_synced' => current_time('mysql'),
        );

        $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s');

        if ($existing) {
            $result = $this->wpdb->update(
                $this->tables['competitions'],
                $prepared_data,
                array('cid' => $cid),
                $format,
                array('%d')
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result !== false ? $existing->id : false;
        } else {
            $result = $this->wpdb->insert(
                $this->tables['competitions'],
                $prepared_data,
                $format
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Get competition by CID.
     *
     * @param int $cid Competition ID.
     * @return object|null
     */
    public function get_competition_by_cid($cid) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['competitions']} WHERE cid = %d",
                absint($cid)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get all active competitions.
     *
     * @return array
     */
    public function get_active_competitions() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->tables['competitions']} WHERE status = 'live' OR status = 'upcoming' ORDER BY date_start DESC"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ==========================================
    // TEAM OPERATIONS
    // ==========================================

    /**
     * Upsert a team.
     *
     * @param array $data Team data.
     * @return int|false
     */
    public function upsert_team($data) {
        if (empty($data['tid'])) {
            return false;
        }

        $tid = absint($data['tid']);
        $existing = $this->get_team_by_tid($tid);

        $prepared_data = array(
            'tid' => $tid,
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'abbr' => isset($data['abbr']) ? sanitize_text_field($data['abbr']) : null,
            'alt_name' => isset($data['alt_name']) ? sanitize_text_field($data['alt_name']) : null,
            'type' => isset($data['type']) ? sanitize_text_field($data['type']) : null,
            'thumb_url' => isset($data['thumb_url']) ? esc_url_raw($data['thumb_url']) : null,
            'logo_url' => isset($data['logo_url']) ? esc_url_raw($data['logo_url']) : null,
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : null,
            'sex' => isset($data['sex']) ? sanitize_text_field($data['sex']) : null,
            'raw_data' => wp_json_encode($data),
            'last_synced' => current_time('mysql'),
        );

        $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

        if ($existing) {
            $result = $this->wpdb->update(
                $this->tables['teams'],
                $prepared_data,
                array('tid' => $tid),
                $format,
                array('%d')
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result !== false ? $existing->id : false;
        } else {
            $result = $this->wpdb->insert(
                $this->tables['teams'],
                $prepared_data,
                $format
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Get team by TID.
     *
     * @param int $tid Team ID.
     * @return object|null
     */
    public function get_team_by_tid($tid) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['teams']} WHERE tid = %d",
                absint($tid)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ==========================================
    // PLAYER OPERATIONS
    // ==========================================

    /**
     * Upsert a player.
     *
     * @param array $data Player data.
     * @return int|false
     */
    public function upsert_player($data) {
        if (empty($data['pid'])) {
            return false;
        }

        $pid = absint($data['pid']);
        $existing = $this->get_player_by_pid($pid);

        $prepared_data = array(
            'pid' => $pid,
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'short_name' => isset($data['short_name']) ? sanitize_text_field($data['short_name']) : null,
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : null,
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : null,
            'middle_name' => isset($data['middle_name']) ? sanitize_text_field($data['middle_name']) : null,
            'birthdate' => isset($data['birthdate']) ? sanitize_text_field($data['birthdate']) : null,
            'birthplace' => isset($data['birthplace']) ? sanitize_text_field($data['birthplace']) : null,
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : null,
            'primary_team' => isset($data['primary_team']) ? wp_json_encode($data['primary_team']) : null,
            'thumb_url' => isset($data['thumb_url']) ? esc_url_raw($data['thumb_url']) : null,
            'logo_url' => isset($data['logo_url']) ? esc_url_raw($data['logo_url']) : null,
            'playing_role' => isset($data['playing_role']) ? sanitize_text_field($data['playing_role']) : null,
            'batting_style' => isset($data['batting_style']) ? sanitize_text_field($data['batting_style']) : null,
            'bowling_style' => isset($data['bowling_style']) ? sanitize_text_field($data['bowling_style']) : null,
            'fielding_position' => isset($data['fielding_position']) ? sanitize_text_field($data['fielding_position']) : null,
            'raw_data' => wp_json_encode($data),
            'last_synced' => current_time('mysql'),
        );

        $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

        if ($existing) {
            $result = $this->wpdb->update(
                $this->tables['players'],
                $prepared_data,
                array('pid' => $pid),
                $format,
                array('%d')
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result !== false ? $existing->id : false;
        } else {
            $result = $this->wpdb->insert(
                $this->tables['players'],
                $prepared_data,
                $format
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Get player by PID.
     *
     * @param int $pid Player ID.
     * @return object|null
     */
    public function get_player_by_pid($pid) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['players']} WHERE pid = %d",
                absint($pid)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ==========================================
    // SQUAD OPERATIONS
    // ==========================================

    /**
     * Upsert a squad member.
     *
     * @param int   $match_id Match ID.
     * @param int   $team_id Team ID.
     * @param array $player Player data.
     * @return int|false
     */
    public function upsert_squad_member($match_id, $team_id, $player) {
        if (empty($player['player_id'])) {
            return false;
        }

        $match_id = absint($match_id);
        $team_id = absint($team_id);
        $player_id = absint($player['player_id']);

        // Check if exists
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['squads']} WHERE match_id = %d AND team_id = %d AND player_id = %d",
                $match_id,
                $team_id,
                $player_id
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $prepared_data = array(
            'match_id' => $match_id,
            'team_id' => $team_id,
            'player_id' => $player_id,
            'player_name' => isset($player['name']) ? sanitize_text_field($player['name']) : null,
            'role' => isset($player['role']) ? sanitize_text_field($player['role']) : null,
            'role_str' => isset($player['role_str']) ? sanitize_text_field($player['role_str']) : null,
            'is_playing' => isset($player['playing11']) ? absint($player['playing11']) : 0,
            'is_captain' => isset($player['is_captain']) ? absint($player['is_captain']) : 0,
            'is_keeper' => isset($player['is_keeper']) ? absint($player['is_keeper']) : 0,
            'batting_order' => isset($player['batting_order']) ? absint($player['batting_order']) : null,
            'raw_data' => wp_json_encode($player),
        );

        $format = array('%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s');

        if ($existing) {
            $result = $this->wpdb->update(
                $this->tables['squads'],
                $prepared_data,
                array('id' => $existing->id),
                $format,
                array('%d')
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result !== false ? $existing->id : false;
        } else {
            $result = $this->wpdb->insert(
                $this->tables['squads'],
                $prepared_data,
                $format
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Get match squad.
     *
     * @param int $match_id Match ID.
     * @param int $team_id Team ID (optional).
     * @return array
     */
    public function get_match_squad($match_id, $team_id = null) {
        $match_id = absint($match_id);
        
        if ($team_id) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->tables['squads']} WHERE match_id = %d AND team_id = %d ORDER BY batting_order ASC",
                    $match_id,
                    absint($team_id)
                )
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['squads']} WHERE match_id = %d ORDER BY team_id, batting_order ASC",
                $match_id
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ==========================================
    // SCORECARD OPERATIONS
    // ==========================================

    /**
     * Upsert an innings scorecard.
     *
     * @param int   $match_id Match ID.
     * @param array $innings Innings data.
     * @return int|false
     */
    public function upsert_scorecard($match_id, $innings) {
        if (empty($innings['innings_id'])) {
            return false;
        }

        $match_id = absint($match_id);
        $innings_id = absint($innings['innings_id']);

        // Check if exists
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['scorecard']} WHERE match_id = %d AND innings_id = %d",
                $match_id,
                $innings_id
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $prepared_data = array(
            'match_id' => $match_id,
            'innings_id' => $innings_id,
            'batting_team_id' => isset($innings['batting_team_id']) ? absint($innings['batting_team_id']) : null,
            'bowling_team_id' => isset($innings['bowling_team_id']) ? absint($innings['bowling_team_id']) : null,
            'innings_number' => isset($innings['number']) ? absint($innings['number']) : null,
            'name' => isset($innings['name']) ? sanitize_text_field($innings['name']) : null,
            'short_name' => isset($innings['short_name']) ? sanitize_text_field($innings['short_name']) : null,
            'status' => isset($innings['status']) ? sanitize_text_field($innings['status']) : null,
            'result' => isset($innings['result']) ? sanitize_text_field($innings['result']) : null,
            'runs' => isset($innings['runs']) ? absint($innings['runs']) : 0,
            'overs' => isset($innings['overs']) ? sanitize_text_field($innings['overs']) : null,
            'wickets' => isset($innings['wickets']) ? absint($innings['wickets']) : 0,
            'target' => isset($innings['target']) ? absint($innings['target']) : null,
            'run_rate' => isset($innings['run_rate']) ? sanitize_text_field($innings['run_rate']) : null,
            'required_run_rate' => isset($innings['required_run_rate']) ? sanitize_text_field($innings['required_run_rate']) : null,
            'extras' => isset($innings['extras']) ? wp_json_encode($innings['extras']) : null,
            'equations' => isset($innings['equations']) ? wp_json_encode($innings['equations']) : null,
            'batsmen' => isset($innings['batsmen']) ? wp_json_encode($innings['batsmen']) : null,
            'bowlers' => isset($innings['bowlers']) ? wp_json_encode($innings['bowlers']) : null,
            'fows' => isset($innings['fows']) ? wp_json_encode($innings['fows']) : null,
            'powerplay' => isset($innings['powerplay']) ? wp_json_encode($innings['powerplay']) : null,
            'last_wicket' => isset($innings['last_wicket']) ? wp_json_encode($innings['last_wicket']) : null,
            'raw_data' => wp_json_encode($innings),
        );

        $format = array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

        if ($existing) {
            $result = $this->wpdb->update(
                $this->tables['scorecard'],
                $prepared_data,
                array('id' => $existing->id),
                $format,
                array('%d')
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result !== false ? $existing->id : false;
        } else {
            $result = $this->wpdb->insert(
                $this->tables['scorecard'],
                $prepared_data,
                $format
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Get match scorecard.
     *
     * @param int $match_id Match ID.
     * @return array
     */
    public function get_match_scorecard($match_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['scorecard']} WHERE match_id = %d ORDER BY innings_id ASC",
                absint($match_id)
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ==========================================
    // STATISTICS
    // ==========================================

    /**
     * Get database statistics.
     *
     * @return array
     */
    public function get_statistics() {
        $stats = array();

        // Count matches by status
        $match_counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->tables['matches']} GROUP BY status"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $stats['matches'] = array(
            'total' => 0,
            'live' => 0,
            'upcoming' => 0,
            'completed' => 0,
        );

        foreach ($match_counts as $count) {
            $stats['matches']['total'] += (int) $count->count;
            switch ((int) $count->status) {
                case 1:
                    $stats['matches']['upcoming'] = (int) $count->count;
                    break;
                case 2:
                    $stats['matches']['completed'] = (int) $count->count;
                    break;
                case 3:
                    $stats['matches']['live'] = (int) $count->count;
                    break;
            }
        }

        // Count other tables
        $stats['competitions'] = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['competitions']}"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $stats['teams'] = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['teams']}"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $stats['players'] = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['players']}"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        // Last sync times
        $stats['last_sync'] = array(
            'live' => get_option('cricket_last_sync_live', 0),
            'upcoming' => get_option('cricket_last_sync_upcoming', 0),
            'recent' => get_option('cricket_last_sync_recent', 0),
            'competitions' => get_option('cricket_last_sync_competitions', 0),
        );

        return $stats;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Decode JSON field safely.
     *
     * @param string $json JSON string.
     * @return array
     */
    public function decode_json($json) {
        if (empty($json)) {
            return array();
        }
        
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Get all matches with filters.
     *
     * @param array $args Filter arguments.
     * @return array
     */
    public function get_matches($args = array()) {
        $defaults = array(
            'status' => null,
            'competition_id' => null,
            'format' => null,
            'search' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'date_start',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();

        if ($args['status'] !== null) {
            $where[] = 'status = %d';
            $values[] = absint($args['status']);
        }

        if ($args['competition_id'] !== null) {
            $where[] = 'competition_id = %d';
            $values[] = absint($args['competition_id']);
        }

        if ($args['format'] !== null) {
            $where[] = 'format = %s';
            $values[] = sanitize_text_field($args['format']);
        }

        if ($args['search'] !== null) {
            $search = '%' . $this->wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(title LIKE %s OR teama_name LIKE %s OR teamb_name LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'date_start DESC';
        }

        $values[] = absint($args['limit']);
        $values[] = absint($args['offset']);

        $query = "SELECT * FROM {$this->tables['matches']} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        if (count($values) > 2) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare($query, $values) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['matches']} WHERE 1=1 ORDER BY {$orderby} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint($args['limit']),
                absint($args['offset'])
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
