<?php
/**
 * Cricket Database Class
 * Handles database operations for matches
 */

if (!defined('ABSPATH')) exit;

class Cricket_Database {
    
    private $table_name;
    private $api_usage_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cricket_matches';
        $this->api_usage_table = $wpdb->prefix . 'cricket_api_usage';
    }
    
    /**
     * Insert or update a match
     */
    public function upsert_match($match_data) {
        global $wpdb;
        
        $existing = $this->get_match_by_id($match_data['match_id']);
        
        $data = [
            'match_id' => sanitize_text_field($match_data['match_id']),
            'title' => sanitize_text_field($match_data['title'] ?? ''),
            'short_title' => sanitize_text_field($match_data['short_title'] ?? ''),
            'competition_id' => sanitize_text_field($match_data['competition_id'] ?? ''),
            'competition_name' => sanitize_text_field($match_data['competition_name'] ?? ''),
            'format' => sanitize_text_field($match_data['format'] ?? ''),
            'status' => sanitize_text_field($match_data['status'] ?? ''),
            'status_note' => sanitize_textarea_field($match_data['status_note'] ?? ''),
            'venue_name' => sanitize_text_field($match_data['venue_name'] ?? ''),
            'venue_city' => sanitize_text_field($match_data['venue_city'] ?? ''),
            'venue_country' => sanitize_text_field($match_data['venue_country'] ?? ''),
            'date_start' => $match_data['date_start'] ?? null,
            'date_end' => $match_data['date_end'] ?? null,
            'teama_id' => sanitize_text_field($match_data['teama_id'] ?? ''),
            'teama_name' => sanitize_text_field($match_data['teama_name'] ?? ''),
            'teama_short' => sanitize_text_field($match_data['teama_short'] ?? ''),
            'teama_logo' => esc_url_raw($match_data['teama_logo'] ?? ''),
            'teama_score' => sanitize_text_field($match_data['teama_score'] ?? ''),
            'teama_overs' => sanitize_text_field($match_data['teama_overs'] ?? ''),
            'teamb_id' => sanitize_text_field($match_data['teamb_id'] ?? ''),
            'teamb_name' => sanitize_text_field($match_data['teamb_name'] ?? ''),
            'teamb_short' => sanitize_text_field($match_data['teamb_short'] ?? ''),
            'teamb_logo' => esc_url_raw($match_data['teamb_logo'] ?? ''),
            'teamb_score' => sanitize_text_field($match_data['teamb_score'] ?? ''),
            'teamb_overs' => sanitize_text_field($match_data['teamb_overs'] ?? ''),
            'toss_text' => sanitize_text_field($match_data['toss_text'] ?? ''),
            'result' => sanitize_textarea_field($match_data['result'] ?? ''),
            'winning_team_id' => sanitize_text_field($match_data['winning_team_id'] ?? ''),
            'live_inning_number' => intval($match_data['live_inning_number'] ?? 0),
            'live_score' => sanitize_text_field($match_data['live_score'] ?? ''),
            'live_wickets' => intval($match_data['live_wickets'] ?? 0),
            'live_overs' => sanitize_text_field($match_data['live_overs'] ?? ''),
            'current_batsmen' => wp_json_encode($match_data['current_batsmen'] ?? []),
            'current_bowlers' => wp_json_encode($match_data['current_bowlers'] ?? []),
            'recent_balls' => wp_json_encode($match_data['recent_balls'] ?? []),
            'match_data' => wp_json_encode($match_data['match_data'] ?? []),
            'last_updated' => current_time('mysql'),
        ];
        
        $format = array_fill(0, count($data), '%s');
        $format[array_search('live_inning_number', array_keys($data))] = '%d';
        $format[array_search('live_wickets', array_keys($data))] = '%d';
        
        if ($existing) {
            return $wpdb->update($this->table_name, $data, ['match_id' => $match_data['match_id']]);
        } else {
            return $wpdb->insert($this->table_name, $data);
        }
    }
    
    /**
     * Get match by ID
     */
    public function get_match_by_id($match_id) {
        global $wpdb;
        $match_id = sanitize_text_field($match_id);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE match_id = %s",
            $match_id
        ));
    }
    
    /**
     * Get match by slug
     */
    public function get_match_by_slug($slug) {
        global $wpdb;
        
        // Extract match ID from slug (format: title-slug-matchid)
        $parts = explode('-', $slug);
        $match_id = end($parts);
        
        if (is_numeric($match_id)) {
            return $this->get_match_by_id($match_id);
        }
        
        return null;
    }
    
    /**
     * Get live matches
     */
    public function get_live_matches($limit = 10) {
        global $wpdb;
        $limit = intval($limit);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status IN ('live', 'Live', 'in progress', 'In Progress') 
             ORDER BY date_start DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get upcoming matches
     */
    public function get_upcoming_matches($limit = 10) {
        global $wpdb;
        $limit = intval($limit);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status IN ('scheduled', 'Scheduled', 'upcoming', 'Upcoming') 
             AND date_start > NOW() 
             ORDER BY date_start ASC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get recent/completed matches
     */
    public function get_recent_matches($limit = 10) {
        global $wpdb;
        $limit = intval($limit);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status IN ('completed', 'Completed', 'finished', 'Finished') 
             ORDER BY date_start DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get all matches
     */
    public function get_all_matches($limit = 100) {
        global $wpdb;
        $limit = intval($limit);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY date_start DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get matches by competition
     */
    public function get_matches_by_competition($competition_id, $limit = 50) {
        global $wpdb;
        $competition_id = sanitize_text_field($competition_id);
        $limit = intval($limit);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE competition_id = %s 
             ORDER BY date_start DESC 
             LIMIT %d",
            $competition_id,
            $limit
        ));
    }
    
    /**
     * Delete old matches
     */
    public function cleanup_old_matches($days = 30) {
        global $wpdb;
        $days = intval($days);
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE status IN ('completed', 'Completed', 'finished', 'Finished') 
             AND date_start < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Increment API request count
     */
    public function increment_api_usage() {
        global $wpdb;
        $today = current_time('Y-m-d');
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->api_usage_table} WHERE request_date = %s",
            $today
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->api_usage_table,
                [
                    'request_count' => $existing->request_count + 1,
                    'last_request' => current_time('mysql')
                ],
                ['request_date' => $today]
            );
        } else {
            $wpdb->insert(
                $this->api_usage_table,
                [
                    'request_date' => $today,
                    'request_count' => 1,
                    'cache_hits' => 0,
                    'last_request' => current_time('mysql')
                ]
            );
        }
    }
    
    /**
     * Increment cache hit count
     */
    public function increment_cache_hits() {
        global $wpdb;
        $today = current_time('Y-m-d');
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->api_usage_table} SET cache_hits = cache_hits + 1 WHERE request_date = %s",
            $today
        ));
    }
    
    /**
     * Get API usage statistics
     */
    public function get_api_usage_stats() {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        $month_start = current_time('Y-m-01');
        
        $today_usage = $wpdb->get_var($wpdb->prepare(
            "SELECT request_count FROM {$this->api_usage_table} WHERE request_date = %s",
            $today
        ));
        
        $month_usage = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(request_count) FROM {$this->api_usage_table} WHERE request_date >= %s",
            $month_start
        ));
        
        $month_cache_hits = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cache_hits) FROM {$this->api_usage_table} WHERE request_date >= %s",
            $month_start
        ));
        
        $monthly_limit = get_option('cricket_api_monthly_limit', 10000);
        
        return [
            'requests_today' => intval($today_usage ?? 0),
            'requests_this_month' => intval($month_usage ?? 0),
            'remaining_requests' => max(0, $monthly_limit - intval($month_usage ?? 0)),
            'cache_hits_this_month' => intval($month_cache_hits ?? 0),
            'monthly_limit' => $monthly_limit,
            'cache_hit_rate' => $month_usage > 0 
                ? round(($month_cache_hits / ($month_usage + $month_cache_hits)) * 100, 1) 
                : 0
        ];
    }
}
