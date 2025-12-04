<?php
/**
 * Cricket Shortcodes Class
 * Handles all shortcodes for displaying cricket data
 */

if (!defined('ABSPATH')) exit;

class Cricket_Shortcodes {
    
    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode('cricket_live_matches', [self::class, 'live_matches_shortcode']);
        add_shortcode('cricket_upcoming_matches', [self::class, 'upcoming_matches_shortcode']);
        add_shortcode('cricket_recent_matches', [self::class, 'recent_matches_shortcode']);
        add_shortcode('cricket_live_widget', [self::class, 'live_widget_shortcode']);
        add_shortcode('cricket_match', [self::class, 'single_match_shortcode']);
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_assets']);
        
        // Handle single match page routing
        add_action('init', [self::class, 'add_rewrite_rules']);
        add_filter('query_vars', [self::class, 'add_query_vars']);
        add_action('template_redirect', [self::class, 'handle_match_template']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        wp_enqueue_style(
            'cricket-frontend-css',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            CRICKET_LIVE_SCORES_VERSION
        );
        
        wp_enqueue_style(
            'cricket-widget-css',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/css/widget.css',
            [],
            CRICKET_LIVE_SCORES_VERSION
        );
        
        wp_enqueue_script(
            'cricket-auto-refresh-js',
            CRICKET_LIVE_SCORES_PLUGIN_URL . 'assets/js/auto-refresh.js',
            ['jquery'],
            CRICKET_LIVE_SCORES_VERSION,
            true
        );
        
        wp_localize_script('cricket-auto-refresh-js', 'cricketLive', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cricket_frontend_nonce'),
            'refreshInterval' => 2000, // Hardcoded 2 seconds for live updates
            'strings' => [
                'loading' => __('Loading...', 'cricket-live-scores'),
                'error' => __('Error loading data', 'cricket-live-scores'),
                'noMatches' => __('No matches found', 'cricket-live-scores'),
            ]
        ]);
    }
    
    /**
     * Add rewrite rules for single match pages
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^match/([^/]+)/?$',
            'index.php?cricket_match=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query vars
     */
    public static function add_query_vars($vars) {
        $vars[] = 'cricket_match';
        return $vars;
    }
    
    /**
     * Handle match template redirect
     */
    public static function handle_match_template() {
        $match_slug = get_query_var('cricket_match');
        
        if (!empty($match_slug)) {
            $db = new Cricket_Database();
            $match = $db->get_match_by_slug($match_slug);
            
            if ($match) {
                // Load the single match template
                include CRICKET_LIVE_SCORES_PLUGIN_DIR . 'templates/single-match.php';
                exit;
            } else {
                // Match not found - show 404
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit;
            }
        }
    }
    
    /**
     * Live matches shortcode
     */
    public static function live_matches_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 10,
            'show_title' => 'yes',
        ], $atts);
        
        $db = new Cricket_Database();
        $matches = $db->get_live_matches(intval($atts['limit']));
        
        ob_start();
        ?>
        <div class="cricket-matches-container cricket-live-matches" data-type="live">
            <?php if ($atts['show_title'] === 'yes') : ?>
                <h2 class="cricket-section-title">
                    <span class="live-indicator"></span>
                    <?php esc_html_e('Live Matches', 'cricket-live-scores'); ?>
                </h2>
            <?php endif; ?>
            
            <div class="cricket-matches-list">
                <?php if (empty($matches)) : ?>
                    <div class="cricket-no-matches">
                        <?php esc_html_e('No live matches at the moment.', 'cricket-live-scores'); ?>
                    </div>
                <?php else : ?>
                    <?php foreach ($matches as $match) : ?>
                        <?php echo self::render_match_card($match, 'live'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Upcoming matches shortcode
     */
    public static function upcoming_matches_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 10,
            'show_title' => 'yes',
        ], $atts);
        
        $db = new Cricket_Database();
        $matches = $db->get_upcoming_matches(intval($atts['limit']));
        
        ob_start();
        ?>
        <div class="cricket-matches-container cricket-upcoming-matches" data-type="upcoming">
            <?php if ($atts['show_title'] === 'yes') : ?>
                <h2 class="cricket-section-title">
                    <?php esc_html_e('Upcoming Matches', 'cricket-live-scores'); ?>
                </h2>
            <?php endif; ?>
            
            <div class="cricket-matches-list">
                <?php if (empty($matches)) : ?>
                    <div class="cricket-no-matches">
                        <?php esc_html_e('No upcoming matches scheduled.', 'cricket-live-scores'); ?>
                    </div>
                <?php else : ?>
                    <?php foreach ($matches as $match) : ?>
                        <?php echo self::render_match_card($match, 'upcoming'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Recent matches shortcode
     */
    public static function recent_matches_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 10,
            'show_title' => 'yes',
        ], $atts);
        
        $db = new Cricket_Database();
        $matches = $db->get_recent_matches(intval($atts['limit']));
        
        ob_start();
        ?>
        <div class="cricket-matches-container cricket-recent-matches" data-type="recent">
            <?php if ($atts['show_title'] === 'yes') : ?>
                <h2 class="cricket-section-title">
                    <?php esc_html_e('Recent Results', 'cricket-live-scores'); ?>
                </h2>
            <?php endif; ?>
            
            <div class="cricket-matches-list">
                <?php if (empty($matches)) : ?>
                    <div class="cricket-no-matches">
                        <?php esc_html_e('No recent matches found.', 'cricket-live-scores'); ?>
                    </div>
                <?php else : ?>
                    <?php foreach ($matches as $match) : ?>
                        <?php echo self::render_match_card($match, 'completed'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Live widget shortcode (compact)
     */
    public static function live_widget_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 5,
            'show_header' => 'yes',
        ], $atts);
        
        $db = new Cricket_Database();
        $matches = $db->get_live_matches(intval($atts['limit']));
        
        ob_start();
        ?>
        <div class="cricket-widget cricket-live-widget" data-type="live">
            <?php if ($atts['show_header'] === 'yes') : ?>
                <div class="cricket-widget-header">
                    <span class="live-indicator"></span>
                    <span class="widget-title"><?php esc_html_e('Live Scores', 'cricket-live-scores'); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="cricket-widget-body">
                <?php if (empty($matches)) : ?>
                    <div class="cricket-widget-empty">
                        <?php esc_html_e('No live matches', 'cricket-live-scores'); ?>
                    </div>
                <?php else : ?>
                    <?php foreach ($matches as $match) : ?>
                        <?php echo self::render_widget_item($match); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Single match shortcode
     */
    public static function single_match_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
        ], $atts);
        
        if (empty($atts['id'])) {
            return '<div class="cricket-error">' . esc_html__('Match ID required', 'cricket-live-scores') . '</div>';
        }
        
        $db = new Cricket_Database();
        $match = $db->get_match_by_id(sanitize_text_field($atts['id']));
        
        if (!$match) {
            return '<div class="cricket-error">' . esc_html__('Match not found', 'cricket-live-scores') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="cricket-single-match" data-match-id="<?php echo esc_attr($match->match_id); ?>">
            <?php echo self::render_full_match($match); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render match card
     */
    private static function render_match_card($match, $type = 'live') {
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
     * Render widget item (compact)
     */
    private static function render_widget_item($match) {
        $match_slug = sanitize_title($match->short_title ?: $match->title) . '-' . $match->match_id;
        $match_url = home_url('/match/' . $match_slug);
        
        ob_start();
        ?>
        <div class="cricket-widget-item" data-match-id="<?php echo esc_attr($match->match_id); ?>">
            <a href="<?php echo esc_url($match_url); ?>">
                <div class="widget-match-header">
                    <span class="widget-competition"><?php echo esc_html($match->competition_name ?: $match->format); ?></span>
                    <span class="widget-live-dot"></span>
                </div>
                <div class="widget-teams">
                    <div class="widget-team">
                        <span class="widget-team-name"><?php echo esc_html($match->teama_short ?: $match->teama_name); ?></span>
                        <span class="widget-team-score"><?php echo esc_html($match->teama_score ?: '-'); ?></span>
                    </div>
                    <div class="widget-team">
                        <span class="widget-team-name"><?php echo esc_html($match->teamb_short ?: $match->teamb_name); ?></span>
                        <span class="widget-team-score"><?php echo esc_html($match->teamb_score ?: '-'); ?></span>
                    </div>
                </div>
                <?php if (!empty($match->status_note)) : ?>
                    <div class="widget-status"><?php echo esc_html($match->status_note); ?></div>
                <?php endif; ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render full match view
     */
    private static function render_full_match($match) {
        $is_live = in_array(strtolower($match->status), ['live', 'in progress']);
        $match_data = json_decode($match->match_data, true);
        $current_batsmen = json_decode($match->current_batsmen, true) ?: [];
        $current_bowlers = json_decode($match->current_bowlers, true) ?: [];
        $recent_balls = json_decode($match->recent_balls, true) ?: [];
        
        ob_start();
        ?>
        <div class="cricket-full-match <?php echo $is_live ? 'is-live' : ''; ?>">
            <!-- Match Header -->
            <div class="match-full-header">
                <div class="match-competition">
                    <?php echo esc_html($match->competition_name); ?>
                </div>
                <h1 class="match-title"><?php echo esc_html($match->title); ?></h1>
                <div class="match-meta">
                    <span class="match-format-badge"><?php echo esc_html($match->format); ?></span>
                    <?php if ($is_live) : ?>
                        <span class="match-live-badge">● LIVE</span>
                    <?php else : ?>
                        <span class="match-status-badge"><?php echo esc_html(ucfirst($match->status)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
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
            
            <?php if ($is_live) : ?>
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
            
            <!-- Match Info -->
            <div class="match-info-section">
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Venue', 'cricket-live-scores'); ?></span>
                    <span class="info-value"><?php echo esc_html($match->venue_name . ', ' . $match->venue_city); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Date', 'cricket-live-scores'); ?></span>
                    <span class="info-value"><?php echo esc_html(date_i18n('F j, Y, g:i A', strtotime($match->date_start))); ?></span>
                </div>
                <?php if (!empty($match->toss_text)) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Toss', 'cricket-live-scores'); ?></span>
                        <span class="info-value"><?php echo esc_html($match->toss_text); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get CSS class for ball based on runs
     */
    private static function get_ball_class($ball) {
        return self::get_ball_class_static($ball);
    }
    
    /**
     * Get CSS class for ball - static public version for templates
     */
    public static function get_ball_class_static($ball) {
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
