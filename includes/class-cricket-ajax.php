<?php
/**
 * Cricket AJAX Class
 * Handles AJAX requests for live updates
 */

if (!defined('ABSPATH')) exit;

class Cricket_Ajax {
    
    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // Public AJAX endpoints
        add_action('wp_ajax_cricket_get_live_matches', [self::class, 'get_live_matches']);
        add_action('wp_ajax_nopriv_cricket_get_live_matches', [self::class, 'get_live_matches']);
        
        add_action('wp_ajax_cricket_get_match', [self::class, 'get_match']);
        add_action('wp_ajax_nopriv_cricket_get_match', [self::class, 'get_match']);
        
        add_action('wp_ajax_cricket_get_upcoming_matches', [self::class, 'get_upcoming_matches']);
        add_action('wp_ajax_nopriv_cricket_get_upcoming_matches', [self::class, 'get_upcoming_matches']);
        
        add_action('wp_ajax_cricket_get_recent_matches', [self::class, 'get_recent_matches']);
        add_action('wp_ajax_nopriv_cricket_get_recent_matches', [self::class, 'get_recent_matches']);
    }
    
    /**
     * Get live matches
     */
    public static function get_live_matches() {
        check_ajax_referer('cricket_frontend_nonce', 'nonce');
        
        $db = new Cricket_Database();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $matches = $db->get_live_matches($limit);
        
        $html = '';
        if (!empty($matches)) {
            foreach ($matches as $match) {
                $html .= self::render_match_card_html($match, 'live');
            }
        } else {
            $html = '<div class="cricket-no-matches">' . esc_html__('No live matches at the moment.', 'cricket-live-scores') . '</div>';
        }
        
        wp_send_json_success([
            'html' => $html,
            'count' => count($matches),
            'matches' => self::format_matches_for_json($matches)
        ]);
    }
    
    /**
     * Get single match
     */
    public static function get_match() {
        check_ajax_referer('cricket_frontend_nonce', 'nonce');
        
        if (!isset($_POST['match_id'])) {
            wp_send_json_error(['message' => __('Match ID required', 'cricket-live-scores')]);
        }
        
        $match_id = sanitize_text_field($_POST['match_id']);
        $db = new Cricket_Database();
        $match = $db->get_match_by_id($match_id);
        
        if (!$match) {
            wp_send_json_error(['message' => __('Match not found', 'cricket-live-scores')]);
        }
        
        wp_send_json_success([
            'match' => self::format_match_for_json($match),
            'html' => self::render_full_match_html($match)
        ]);
    }
    
    /**
     * Get upcoming matches
     */
    public static function get_upcoming_matches() {
        check_ajax_referer('cricket_frontend_nonce', 'nonce');
        
        $db = new Cricket_Database();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $matches = $db->get_upcoming_matches($limit);
        
        $html = '';
        if (!empty($matches)) {
            foreach ($matches as $match) {
                $html .= self::render_match_card_html($match, 'upcoming');
            }
        } else {
            $html = '<div class="cricket-no-matches">' . esc_html__('No upcoming matches scheduled.', 'cricket-live-scores') . '</div>';
        }
        
        wp_send_json_success([
            'html' => $html,
            'count' => count($matches),
            'matches' => self::format_matches_for_json($matches)
        ]);
    }
    
    /**
     * Get recent matches
     */
    public static function get_recent_matches() {
        check_ajax_referer('cricket_frontend_nonce', 'nonce');
        
        $db = new Cricket_Database();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $matches = $db->get_recent_matches($limit);
        
        $html = '';
        if (!empty($matches)) {
            foreach ($matches as $match) {
                $html .= self::render_match_card_html($match, 'completed');
            }
        } else {
            $html = '<div class="cricket-no-matches">' . esc_html__('No recent matches found.', 'cricket-live-scores') . '</div>';
        }
        
        wp_send_json_success([
            'html' => $html,
            'count' => count($matches),
            'matches' => self::format_matches_for_json($matches)
        ]);
    }
    
    /**
     * Format matches array for JSON response
     */
    private static function format_matches_for_json($matches) {
        $formatted = [];
        foreach ($matches as $match) {
            $formatted[] = self::format_match_for_json($match);
        }
        return $formatted;
    }
    
    /**
     * Format single match for JSON response
     */
    private static function format_match_for_json($match) {
        return [
            'match_id' => $match->match_id,
            'title' => $match->title,
            'short_title' => $match->short_title,
            'format' => $match->format,
            'status' => $match->status,
            'status_note' => $match->status_note,
            'teama_name' => $match->teama_name,
            'teama_short' => $match->teama_short,
            'teama_score' => $match->teama_score,
            'teama_overs' => $match->teama_overs,
            'teamb_name' => $match->teamb_name,
            'teamb_short' => $match->teamb_short,
            'teamb_score' => $match->teamb_score,
            'teamb_overs' => $match->teamb_overs,
            'venue_name' => $match->venue_name,
            'date_start' => $match->date_start,
            'result' => $match->result,
            'is_live' => in_array(strtolower($match->status), ['live', 'in progress']),
            'current_batsmen' => json_decode($match->current_batsmen, true) ?: [],
            'current_bowlers' => json_decode($match->current_bowlers, true) ?: [],
            'recent_balls' => json_decode($match->recent_balls, true) ?: [],
            'last_updated' => $match->last_updated,
        ];
    }
    
    /**
     * Render match card HTML
     */
    private static function render_match_card_html($match, $type = 'live') {
        $match_slug = sanitize_title($match->short_title ?: $match->title) . '-' . $match->match_id;
        $match_url = home_url('/match/' . $match_slug);
        $is_live = in_array(strtolower($match->status), ['live', 'in progress']);
        
        ob_start();
        ?>
        <div class="cricket-match-card <?php echo $is_live ? 'is-live' : ''; ?>" data-match-id="<?php echo esc_attr($match->match_id); ?>">
            <a href="<?php echo esc_url($match_url); ?>" class="match-card-link">
                <div class="match-header">
                    <span class="match-format"><?php echo esc_html($match->format); ?></span>
                    <?php if ($is_live) : ?>
                        <span class="match-live-badge">LIVE</span>
                    <?php else : ?>
                        <span class="match-status"><?php echo esc_html(ucfirst($match->status)); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="match-teams">
                    <div class="team team-a">
                        <?php if (!empty($match->teama_logo)) : ?>
                            <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="team-logo">
                        <?php else : ?>
                            <div class="team-logo-placeholder"><?php echo esc_html(substr($match->teama_short ?: $match->teama_name, 0, 2)); ?></div>
                        <?php endif; ?>
                        <span class="team-name"><?php echo esc_html($match->teama_short ?: $match->teama_name); ?></span>
                        <span class="team-score"><?php echo esc_html($match->teama_score ?: '-'); ?></span>
                    </div>
                    
                    <div class="match-vs">vs</div>
                    
                    <div class="team team-b">
                        <?php if (!empty($match->teamb_logo)) : ?>
                            <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="team-logo">
                        <?php else : ?>
                            <div class="team-logo-placeholder"><?php echo esc_html(substr($match->teamb_short ?: $match->teamb_name, 0, 2)); ?></div>
                        <?php endif; ?>
                        <span class="team-name"><?php echo esc_html($match->teamb_short ?: $match->teamb_name); ?></span>
                        <span class="team-score"><?php echo esc_html($match->teamb_score ?: '-'); ?></span>
                    </div>
                </div>
                
                <div class="match-info">
                    <?php if (!empty($match->status_note)) : ?>
                        <div class="match-status-note"><?php echo esc_html($match->status_note); ?></div>
                    <?php elseif (!empty($match->result)) : ?>
                        <div class="match-result"><?php echo esc_html($match->result); ?></div>
                    <?php endif; ?>
                    
                    <div class="match-details">
                        <span class="match-venue"><?php echo esc_html($match->venue_name); ?></span>
                        <span class="match-time"><?php echo esc_html(date_i18n('M j, g:i A', strtotime($match->date_start))); ?></span>
                    </div>
                </div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render full match HTML for AJAX update
     */
    private static function render_full_match_html($match) {
        $is_live = in_array(strtolower($match->status), ['live', 'in progress']);
        $current_batsmen = json_decode($match->current_batsmen, true) ?: [];
        $current_bowlers = json_decode($match->current_bowlers, true) ?: [];
        $recent_balls = json_decode($match->recent_balls, true) ?: [];
        
        ob_start();
        ?>
        <!-- Score Section -->
        <div class="match-score-section">
            <div class="team-score-row team-a-row">
                <div class="team-info">
                    <?php if (!empty($match->teama_logo)) : ?>
                        <img src="<?php echo esc_url($match->teama_logo); ?>" alt="" class="team-logo-large">
                    <?php endif; ?>
                    <span class="team-name-full"><?php echo esc_html($match->teama_name); ?></span>
                </div>
                <div class="team-score-full">
                    <span class="score"><?php echo esc_html($match->teama_score ?: '-'); ?></span>
                    <?php if (!empty($match->teama_overs)) : ?>
                        <span class="overs">(<?php echo esc_html($match->teama_overs); ?> ov)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="team-score-row team-b-row">
                <div class="team-info">
                    <?php if (!empty($match->teamb_logo)) : ?>
                        <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="" class="team-logo-large">
                    <?php endif; ?>
                    <span class="team-name-full"><?php echo esc_html($match->teamb_name); ?></span>
                </div>
                <div class="team-score-full">
                    <span class="score"><?php echo esc_html($match->teamb_score ?: '-'); ?></span>
                    <?php if (!empty($match->teamb_overs)) : ?>
                        <span class="overs">(<?php echo esc_html($match->teamb_overs); ?> ov)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Status Note -->
        <?php if (!empty($match->status_note)) : ?>
            <div class="match-status-note-full">
                <?php echo esc_html($match->status_note); ?>
            </div>
        <?php elseif (!empty($match->result)) : ?>
            <div class="match-result-full">
                <?php echo esc_html($match->result); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($is_live && (!empty($current_batsmen) || !empty($current_bowlers) || !empty($recent_balls))) : ?>
            <!-- Live Stats -->
            <div class="match-live-stats">
                <?php if (!empty($current_batsmen)) : ?>
                    <div class="live-batsmen">
                        <h3><?php esc_html_e('At Crease', 'cricket-live-scores'); ?></h3>
                        <?php foreach ($current_batsmen as $batsman) : ?>
                            <div class="batsman-row">
                                <span class="batsman-name"><?php echo esc_html($batsman['name'] ?? ''); ?></span>
                                <span class="batsman-stats">
                                    <?php echo esc_html(($batsman['runs'] ?? 0) . ' (' . ($batsman['balls'] ?? 0) . ')'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($current_bowlers)) : ?>
                    <div class="live-bowler">
                        <h3><?php esc_html_e('Bowling', 'cricket-live-scores'); ?></h3>
                        <?php foreach ($current_bowlers as $bowler) : ?>
                            <div class="bowler-row">
                                <span class="bowler-name"><?php echo esc_html($bowler['name'] ?? ''); ?></span>
                                <span class="bowler-stats">
                                    <?php echo esc_html(($bowler['wickets'] ?? 0) . '/' . ($bowler['runs'] ?? 0) . ' (' . ($bowler['overs'] ?? 0) . ')'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recent_balls)) : ?>
                    <div class="recent-balls">
                        <h3><?php esc_html_e('Recent', 'cricket-live-scores'); ?></h3>
                        <div class="balls-container">
                            <?php foreach ($recent_balls as $ball) : ?>
                                <span class="ball-item <?php echo esc_attr(self::get_ball_class($ball)); ?>">
                                    <?php echo esc_html($ball); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get CSS class for ball based on runs
     */
    private static function get_ball_class($ball) {
        $ball_str = strtolower(trim($ball));
        
        if ($ball_str === 'w' || $ball_str === 'out') {
            return 'ball-wicket';
        } elseif ($ball_str === '4') {
            return 'ball-four';
        } elseif ($ball_str === '6') {
            return 'ball-six';
        } elseif (strpos($ball_str, 'wd') !== false || strpos($ball_str, 'nb') !== false) {
            return 'ball-extra';
        } elseif ($ball_str === '0' || $ball_str === '.') {
            return 'ball-dot';
        }
        
        return 'ball-run';
    }
}
