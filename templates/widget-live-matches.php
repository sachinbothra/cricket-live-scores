<?php
/**
 * Template: Live Matches Widget
 *
 * Displays a horizontal scrollable widget with live cricket matches.
 * Includes series filter tabs and navigation arrows.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$db = Cricket_Database::get_instance();
$live_matches = $db->get_live_matches(50);
$upcoming_matches = $db->get_upcoming_matches(20);
$recent_matches = $db->get_recent_matches(20);

// Get unique series from matches
$series = array();
foreach ($live_matches as $match) {
    if ($match->competition_id && !isset($series[$match->competition_id])) {
        $series[$match->competition_id] = $match->competition_title;
    }
}
?>

<div class="cricket-widget" id="cricket-widget">
    <!-- Series Filter Tabs -->
    <div class="cricket-widget-tabs">
        <button class="cricket-tab active" data-filter="all">
            <?php esc_html_e('All', 'cricket-live-scores'); ?>
        </button>
        <button class="cricket-tab" data-filter="live">
            <?php esc_html_e('Live', 'cricket-live-scores'); ?>
            <?php if (count($live_matches) > 0) : ?>
                <span class="cricket-badge cricket-badge-live"><?php echo esc_html(count($live_matches)); ?></span>
            <?php endif; ?>
        </button>
        <button class="cricket-tab" data-filter="upcoming">
            <?php esc_html_e('Upcoming', 'cricket-live-scores'); ?>
        </button>
        <button class="cricket-tab" data-filter="recent">
            <?php esc_html_e('Recent', 'cricket-live-scores'); ?>
        </button>
        <?php foreach ($series as $cid => $title) : ?>
            <button class="cricket-tab" data-filter="series-<?php echo esc_attr($cid); ?>">
                <?php echo esc_html($title); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Match Cards Container -->
    <div class="cricket-widget-container">
        <button class="cricket-nav-arrow cricket-nav-left" aria-label="<?php esc_attr_e('Previous', 'cricket-live-scores'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
        
        <div class="cricket-widget-scroll" id="cricket-widget-scroll">
            <?php if (empty($live_matches) && empty($upcoming_matches) && empty($recent_matches)) : ?>
                <div class="cricket-empty-state">
                    <p><?php esc_html_e('No matches available at the moment.', 'cricket-live-scores'); ?></p>
                </div>
            <?php else : ?>
                <!-- Live Matches -->
                <?php foreach ($live_matches as $match) : ?>
                    <div class="cricket-widget-card" 
                         data-match-id="<?php echo esc_attr($match->match_id); ?>"
                         data-status="live"
                         data-series="<?php echo esc_attr($match->competition_id); ?>">
                        
                        <div class="cricket-card-header">
                            <span class="cricket-card-series"><?php echo esc_html($match->competition_title); ?></span>
                            <span class="cricket-status-badge cricket-status-live">
                                <span class="cricket-live-dot"></span>
                                <?php esc_html_e('LIVE', 'cricket-live-scores'); ?>
                            </span>
                        </div>
                        
                        <div class="cricket-card-match">
                            <?php echo esc_html($match->format_str); ?>
                            <?php if ($match->venue_name) : ?>
                                • <?php echo esc_html($match->venue_name); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="cricket-card-teams">
                            <!-- Team A -->
                            <div class="cricket-card-team">
                                <div class="cricket-team-left">
                                    <?php if ($match->teama_logo) : ?>
                                        <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                    <?php else : ?>
                                        <div class="cricket-team-flag cricket-team-placeholder"></div>
                                    <?php endif; ?>
                                    <span class="cricket-team-abbr"><?php echo esc_html($match->teama_short_name ?: $match->teama_name); ?></span>
                                </div>
                                <div class="cricket-team-right">
                                    <span class="cricket-team-score" data-team="a"><?php echo esc_html($match->teama_scores ?: '-'); ?></span>
                                    <?php if ($match->teama_overs) : ?>
                                        <span class="cricket-team-overs" data-team="a">(<?php echo esc_html($match->teama_overs); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Team B -->
                            <div class="cricket-card-team">
                                <div class="cricket-team-left">
                                    <?php if ($match->teamb_logo) : ?>
                                        <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                    <?php else : ?>
                                        <div class="cricket-team-flag cricket-team-placeholder"></div>
                                    <?php endif; ?>
                                    <span class="cricket-team-abbr"><?php echo esc_html($match->teamb_short_name ?: $match->teamb_name); ?></span>
                                </div>
                                <div class="cricket-team-right">
                                    <span class="cricket-team-score" data-team="b"><?php echo esc_html($match->teamb_scores ?: '-'); ?></span>
                                    <?php if ($match->teamb_overs) : ?>
                                        <span class="cricket-team-overs" data-team="b">(<?php echo esc_html($match->teamb_overs); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($match->status_note) : ?>
                            <div class="cricket-card-status" data-status-note>
                                <?php echo esc_html($match->status_note); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Upcoming Matches -->
                <?php foreach ($upcoming_matches as $match) : ?>
                    <div class="cricket-widget-card" 
                         data-match-id="<?php echo esc_attr($match->match_id); ?>"
                         data-status="upcoming"
                         data-series="<?php echo esc_attr($match->competition_id); ?>">
                        
                        <div class="cricket-card-header">
                            <span class="cricket-card-series"><?php echo esc_html($match->competition_title); ?></span>
                            <span class="cricket-status-badge cricket-status-upcoming">
                                <?php echo esc_html(Cricket_Shortcodes::get_time_remaining($match->date_start)); ?>
                            </span>
                        </div>
                        
                        <div class="cricket-card-match">
                            <?php echo esc_html($match->format_str); ?>
                            <?php if ($match->venue_name) : ?>
                                • <?php echo esc_html($match->venue_name); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="cricket-card-teams">
                            <!-- Team A -->
                            <div class="cricket-card-team">
                                <div class="cricket-team-left">
                                    <?php if ($match->teama_logo) : ?>
                                        <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                    <?php else : ?>
                                        <div class="cricket-team-flag cricket-team-placeholder"></div>
                                    <?php endif; ?>
                                    <span class="cricket-team-abbr"><?php echo esc_html($match->teama_short_name ?: $match->teama_name); ?></span>
                                </div>
                            </div>
                            
                            <div class="cricket-card-vs">vs</div>
                            
                            <!-- Team B -->
                            <div class="cricket-card-team">
                                <div class="cricket-team-left">
                                    <?php if ($match->teamb_logo) : ?>
                                        <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                    <?php else : ?>
                                        <div class="cricket-team-flag cricket-team-placeholder"></div>
                                    <?php endif; ?>
                                    <span class="cricket-team-abbr"><?php echo esc_html($match->teamb_short_name ?: $match->teamb_name); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cricket-card-time">
                            <?php echo esc_html(Cricket_Shortcodes::format_match_date($match->date_start)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Recent Matches -->
                <?php foreach ($recent_matches as $match) : ?>
                    <div class="cricket-widget-card" 
                         data-match-id="<?php echo esc_attr($match->match_id); ?>"
                         data-status="recent"
                         data-series="<?php echo esc_attr($match->competition_id); ?>">
                        
                        <div class="cricket-card-header">
                            <span class="cricket-card-series"><?php echo esc_html($match->competition_title); ?></span>
                            <span class="cricket-status-badge cricket-status-completed">
                                <?php esc_html_e('Completed', 'cricket-live-scores'); ?>
                            </span>
                        </div>
                        
                        <div class="cricket-card-match">
                            <?php echo esc_html($match->format_str); ?>
                            <?php if ($match->venue_name) : ?>
                                • <?php echo esc_html($match->venue_name); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="cricket-card-teams">
                            <!-- Team A -->
                            <div class="cricket-card-team <?php echo $match->winning_team_id == $match->teama_id ? 'cricket-winner' : ''; ?>">
                                <div class="cricket-team-left">
                                    <?php if ($match->teama_logo) : ?>
                                        <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                    <?php else : ?>
                                        <div class="cricket-team-flag cricket-team-placeholder"></div>
                                    <?php endif; ?>
                                    <span class="cricket-team-abbr"><?php echo esc_html($match->teama_short_name ?: $match->teama_name); ?></span>
                                </div>
                                <div class="cricket-team-right">
                                    <span class="cricket-team-score"><?php echo esc_html($match->teama_scores ?: '-'); ?></span>
                                    <?php if ($match->teama_overs) : ?>
                                        <span class="cricket-team-overs">(<?php echo esc_html($match->teama_overs); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Team B -->
                            <div class="cricket-card-team <?php echo $match->winning_team_id == $match->teamb_id ? 'cricket-winner' : ''; ?>">
                                <div class="cricket-team-left">
                                    <?php if ($match->teamb_logo) : ?>
                                        <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                    <?php else : ?>
                                        <div class="cricket-team-flag cricket-team-placeholder"></div>
                                    <?php endif; ?>
                                    <span class="cricket-team-abbr"><?php echo esc_html($match->teamb_short_name ?: $match->teamb_name); ?></span>
                                </div>
                                <div class="cricket-team-right">
                                    <span class="cricket-team-score"><?php echo esc_html($match->teamb_scores ?: '-'); ?></span>
                                    <?php if ($match->teamb_overs) : ?>
                                        <span class="cricket-team-overs">(<?php echo esc_html($match->teamb_overs); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($match->result) : ?>
                            <div class="cricket-card-result">
                                <?php echo esc_html($match->result); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button class="cricket-nav-arrow cricket-nav-right" aria-label="<?php esc_attr_e('Next', 'cricket-live-scores'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
    </div>
</div>
