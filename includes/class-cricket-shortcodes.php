<?php
/**
 * Cricket Live Scores - Shortcodes Class
 *
 * Handles all shortcodes for displaying cricket data on the frontend.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cricket_Shortcodes
 *
 * Register and render all shortcodes.
 */
class Cricket_Shortcodes {

    /**
     * Database instance.
     *
     * @var Cricket_Database
     */
    private static $db;

    /**
     * Initialize shortcodes.
     *
     * @return void
     */
    public static function init() {
        self::$db = Cricket_Database::get_instance();
        
        // Register shortcodes
        add_shortcode('cricket_live_matches', array(__CLASS__, 'live_matches_shortcode'));
        add_shortcode('cricket_upcoming_matches', array(__CLASS__, 'upcoming_matches_shortcode'));
        add_shortcode('cricket_recent_matches', array(__CLASS__, 'recent_matches_shortcode'));
        add_shortcode('cricket_match', array(__CLASS__, 'single_match_shortcode'));
        add_shortcode('cricket_series', array(__CLASS__, 'series_shortcode'));
        add_shortcode('cricket_competition_matches', array(__CLASS__, 'competition_matches_shortcode'));
        add_shortcode('cricket_widget', array(__CLASS__, 'widget_shortcode'));
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    /**
     * Enqueue frontend CSS and JS.
     *
     * @return void
     */
    public static function enqueue_assets() {
        // Widget CSS
        wp_enqueue_style(
            'cricket-widget',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/css/widget.css',
            array(),
            CRICKET_LIVE_SCORES_VERSION
        );
        
        // Frontend CSS
        wp_enqueue_style(
            'cricket-frontend',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CRICKET_LIVE_SCORES_VERSION
        );
        
        // Widget JS
        wp_enqueue_script(
            'cricket-widget',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/js/widget.js',
            array('jquery'),
            CRICKET_LIVE_SCORES_VERSION,
            true
        );
        
        // Auto-refresh JS
        wp_enqueue_script(
            'cricket-auto-refresh',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/js/auto-refresh.js',
            array('jquery'),
            CRICKET_LIVE_SCORES_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('cricket-widget', 'cricketLiveScores', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cricket_ajax_nonce'),
            'refreshInterval' => get_option('cricket_frontend_refresh_interval', 2) * 1000,
        ));
    }

    /**
     * Live matches shortcode.
     * [cricket_live_matches limit="10" series="" format=""]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function live_matches_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'series' => '',
            'format' => '',
        ), $atts, 'cricket_live_matches');
        
        $args = array(
            'status' => 3,
            'limit' => absint($atts['limit']),
        );
        
        if (!empty($atts['series'])) {
            $args['competition_id'] = absint($atts['series']);
        }
        
        if (!empty($atts['format'])) {
            $args['format'] = sanitize_text_field($atts['format']);
        }
        
        $matches = self::$db->get_matches($args);
        
        if (empty($matches)) {
            return '<div class="cricket-no-matches">' . esc_html__('No live matches at the moment.', 'cricket-live-scores') . '</div>';
        }
        
        return self::render_match_cards($matches, 'live');
    }

    /**
     * Upcoming matches shortcode.
     * [cricket_upcoming_matches limit="10" series="" format=""]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function upcoming_matches_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'series' => '',
            'format' => '',
        ), $atts, 'cricket_upcoming_matches');
        
        $args = array(
            'status' => 1,
            'limit' => absint($atts['limit']),
            'orderby' => 'date_start',
            'order' => 'ASC',
        );
        
        if (!empty($atts['series'])) {
            $args['competition_id'] = absint($atts['series']);
        }
        
        if (!empty($atts['format'])) {
            $args['format'] = sanitize_text_field($atts['format']);
        }
        
        $matches = self::$db->get_matches($args);
        
        if (empty($matches)) {
            return '<div class="cricket-no-matches">' . esc_html__('No upcoming matches scheduled.', 'cricket-live-scores') . '</div>';
        }
        
        return self::render_match_cards($matches, 'upcoming');
    }

    /**
     * Recent matches shortcode.
     * [cricket_recent_matches limit="10" series="" format=""]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function recent_matches_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'series' => '',
            'format' => '',
        ), $atts, 'cricket_recent_matches');
        
        $args = array(
            'status' => 2,
            'limit' => absint($atts['limit']),
        );
        
        if (!empty($atts['series'])) {
            $args['competition_id'] = absint($atts['series']);
        }
        
        if (!empty($atts['format'])) {
            $args['format'] = sanitize_text_field($atts['format']);
        }
        
        $matches = self::$db->get_matches($args);
        
        if (empty($matches)) {
            return '<div class="cricket-no-matches">' . esc_html__('No recent matches found.', 'cricket-live-scores') . '</div>';
        }
        
        return self::render_match_cards($matches, 'recent');
    }

    /**
     * Single match shortcode.
     * [cricket_match id="12345"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function single_match_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'cricket_match');
        
        $match_id = absint($atts['id']);
        
        if (!$match_id) {
            return '<div class="cricket-error">' . esc_html__('Please provide a match ID.', 'cricket-live-scores') . '</div>';
        }
        
        $match = self::$db->get_match_by_api_id($match_id);
        
        if (!$match) {
            return '<div class="cricket-error">' . esc_html__('Match not found.', 'cricket-live-scores') . '</div>';
        }
        
        ob_start();
        include CRICKET_LIVE_SCORES_PLUGIN_DIR . 'templates/single-match.php';
        return ob_get_clean();
    }

    /**
     * Series shortcode.
     * [cricket_series id="123"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function series_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'cricket_series');
        
        $series_id = absint($atts['id']);
        
        if (!$series_id) {
            return '<div class="cricket-error">' . esc_html__('Please provide a series ID.', 'cricket-live-scores') . '</div>';
        }
        
        $competition = self::$db->get_competition_by_cid($series_id);
        $matches = self::$db->get_matches_by_competition($series_id, 'all', 100);
        
        ob_start();
        include CRICKET_LIVE_SCORES_PLUGIN_DIR . 'templates/single-series.php';
        return ob_get_clean();
    }

    /**
     * Competition matches shortcode.
     * [cricket_competition_matches id="123" status="live" limit="20"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function competition_matches_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'status' => 'all',
            'limit' => 20,
        ), $atts, 'cricket_competition_matches');
        
        $competition_id = absint($atts['id']);
        
        if (!$competition_id) {
            return '<div class="cricket-error">' . esc_html__('Please provide a competition ID.', 'cricket-live-scores') . '</div>';
        }
        
        $matches = self::$db->get_matches_by_competition(
            $competition_id,
            sanitize_text_field($atts['status']),
            absint($atts['limit'])
        );
        
        if (empty($matches)) {
            return '<div class="cricket-no-matches">' . esc_html__('No matches found for this competition.', 'cricket-live-scores') . '</div>';
        }
        
        return self::render_match_cards($matches, sanitize_text_field($atts['status']));
    }

    /**
     * Widget shortcode.
     * [cricket_widget]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_tabs' => true,
        ), $atts, 'cricket_widget');
        
        ob_start();
        include CRICKET_LIVE_SCORES_PLUGIN_DIR . 'templates/widget-live-matches.php';
        return ob_get_clean();
    }

    /**
     * Render match cards HTML.
     *
     * @param array  $matches Array of match objects.
     * @param string $type Match type (live, upcoming, recent).
     * @return string
     */
    private static function render_match_cards($matches, $type = 'live') {
        $output = '<div class="cricket-matches cricket-matches-' . esc_attr($type) . '">';
        
        foreach ($matches as $match) {
            $output .= self::render_single_match_card($match);
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render a single match card.
     *
     * @param object $match Match object.
     * @return string
     */
    private static function render_single_match_card($match) {
        $status_class = self::get_status_class($match->status);
        $status_text = self::get_status_text($match);
        
        $output = '<div class="cricket-match-card cricket-status-' . esc_attr($status_class) . '" data-match-id="' . esc_attr($match->match_id) . '">';
        
        // Match header
        $output .= '<div class="cricket-match-header">';
        $output .= '<span class="cricket-match-series">' . esc_html($match->competition_title) . '</span>';
        $output .= '<span class="cricket-match-format">' . esc_html($match->format_str) . '</span>';
        $output .= '</div>';
        
        // Teams
        $output .= '<div class="cricket-match-teams">';
        
        // Team A
        $output .= '<div class="cricket-team cricket-team-a">';
        $output .= '<div class="cricket-team-info">';
        if ($match->teama_logo) {
            $output .= '<img src="' . esc_url($match->teama_logo) . '" alt="' . esc_attr($match->teama_name) . '" class="cricket-team-logo">';
        }
        $output .= '<span class="cricket-team-name">' . esc_html($match->teama_short_name ?: $match->teama_name) . '</span>';
        $output .= '</div>';
        if ($match->teama_scores) {
            $output .= '<div class="cricket-team-score">';
            $output .= '<span class="cricket-score">' . esc_html($match->teama_scores) . '</span>';
            if ($match->teama_overs) {
                $output .= '<span class="cricket-overs">(' . esc_html($match->teama_overs) . ')</span>';
            }
            $output .= '</div>';
        }
        $output .= '</div>';
        
        // Team B
        $output .= '<div class="cricket-team cricket-team-b">';
        $output .= '<div class="cricket-team-info">';
        if ($match->teamb_logo) {
            $output .= '<img src="' . esc_url($match->teamb_logo) . '" alt="' . esc_attr($match->teamb_name) . '" class="cricket-team-logo">';
        }
        $output .= '<span class="cricket-team-name">' . esc_html($match->teamb_short_name ?: $match->teamb_name) . '</span>';
        $output .= '</div>';
        if ($match->teamb_scores) {
            $output .= '<div class="cricket-team-score">';
            $output .= '<span class="cricket-score">' . esc_html($match->teamb_scores) . '</span>';
            if ($match->teamb_overs) {
                $output .= '<span class="cricket-overs">(' . esc_html($match->teamb_overs) . ')</span>';
            }
            $output .= '</div>';
        }
        $output .= '</div>';
        
        $output .= '</div>'; // End teams
        
        // Match footer
        $output .= '<div class="cricket-match-footer">';
        $output .= '<span class="cricket-match-status cricket-status-' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
        if ($match->status_note) {
            $output .= '<span class="cricket-match-note">' . esc_html($match->status_note) . '</span>';
        }
        $output .= '</div>';
        
        $output .= '</div>'; // End card
        
        return $output;
    }

    /**
     * Get CSS class for match status.
     *
     * @param int $status Status code.
     * @return string
     */
    private static function get_status_class($status) {
        switch ((int) $status) {
            case 1:
                return 'upcoming';
            case 2:
                return 'completed';
            case 3:
                return 'live';
            case 4:
                return 'abandoned';
            default:
                return 'unknown';
        }
    }

    /**
     * Get display text for match status.
     *
     * @param object $match Match object.
     * @return string
     */
    private static function get_status_text($match) {
        if ($match->status_str) {
            return $match->status_str;
        }
        
        switch ((int) $match->status) {
            case 1:
                return __('Upcoming', 'cricket-live-scores');
            case 2:
                return __('Completed', 'cricket-live-scores');
            case 3:
                return __('Live', 'cricket-live-scores');
            case 4:
                return __('Abandoned', 'cricket-live-scores');
            default:
                return __('Unknown', 'cricket-live-scores');
        }
    }

    /**
     * Format match date for display.
     *
     * @param string $date Date string.
     * @return string
     */
    public static function format_match_date($date) {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    /**
     * Get time remaining until match.
     *
     * @param string $date_start Match start date.
     * @return string
     */
    public static function get_time_remaining($date_start) {
        if (empty($date_start)) {
            return '';
        }
        
        $start = strtotime($date_start);
        $now = current_time('timestamp');
        $diff = $start - $now;
        
        if ($diff < 0) {
            return '';
        }
        
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        if ($days > 0) {
            /* translators: %d: number of days */
            return sprintf(_n('%d day', '%d days', $days, 'cricket-live-scores'), $days);
        } elseif ($hours > 0) {
            /* translators: %d: number of hours */
            return sprintf(_n('%d hour', '%d hours', $hours, 'cricket-live-scores'), $hours);
        } else {
            /* translators: %d: number of minutes */
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'cricket-live-scores'), $minutes);
        }
    }
}
