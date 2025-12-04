<?php
/**
 * Template: Single Series
 *
 * Displays a series/competition page with tabs for different match statuses.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// $competition and $matches are passed from the shortcode
$series_id = isset($series_id) ? $series_id : 0;

// Group matches by status
$live_matches = array();
$upcoming_matches = array();
$recent_matches = array();

if (!empty($matches)) {
    foreach ($matches as $match) {
        switch ((int) $match->status) {
            case 1:
                $upcoming_matches[] = $match;
                break;
            case 2:
                $recent_matches[] = $match;
                break;
            case 3:
                $live_matches[] = $match;
                break;
        }
    }
}
?>

<div class="cricket-series-page" data-series-id="<?php echo esc_attr($series_id); ?>">
    <!-- Series Header -->
    <div class="cricket-series-header">
        <?php if ($competition && $competition->logo) : ?>
            <img src="<?php echo esc_url($competition->logo); ?>" alt="<?php echo esc_attr($competition->title); ?>" class="cricket-series-logo">
        <?php endif; ?>
        
        <div class="cricket-series-info">
            <h1 class="cricket-series-title">
                <?php echo $competition ? esc_html($competition->title) : esc_html__('Series', 'cricket-live-scores'); ?>
            </h1>
            <?php if ($competition) : ?>
                <div class="cricket-series-meta">
                    <?php if ($competition->season) : ?>
                        <span class="cricket-series-season"><?php echo esc_html($competition->season); ?></span>
                    <?php endif; ?>
                    <?php if ($competition->country) : ?>
                        <span class="cricket-series-country"><?php echo esc_html($competition->country); ?></span>
                    <?php endif; ?>
                    <?php if ($competition->total_matches) : ?>
                        <span class="cricket-series-matches"><?php echo esc_html($competition->total_matches); ?> <?php esc_html_e('Matches', 'cricket-live-scores'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="cricket-series-tabs">
        <button class="cricket-series-tab <?php echo !empty($live_matches) ? 'active' : ''; ?>" data-tab="live">
            <?php esc_html_e('Live', 'cricket-live-scores'); ?>
            <?php if (count($live_matches) > 0) : ?>
                <span class="cricket-badge cricket-badge-live"><?php echo esc_html(count($live_matches)); ?></span>
            <?php endif; ?>
        </button>
        <button class="cricket-series-tab <?php echo empty($live_matches) ? 'active' : ''; ?>" data-tab="upcoming">
            <?php esc_html_e('Upcoming', 'cricket-live-scores'); ?>
            <?php if (count($upcoming_matches) > 0) : ?>
                <span class="cricket-badge"><?php echo esc_html(count($upcoming_matches)); ?></span>
            <?php endif; ?>
        </button>
        <button class="cricket-series-tab" data-tab="recent">
            <?php esc_html_e('Results', 'cricket-live-scores'); ?>
            <?php if (count($recent_matches) > 0) : ?>
                <span class="cricket-badge"><?php echo esc_html(count($recent_matches)); ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Tab Content -->
    <div class="cricket-series-content">
        <!-- Live Matches Tab -->
        <div class="cricket-series-pane <?php echo !empty($live_matches) ? 'active' : ''; ?>" id="series-tab-live">
            <?php if (!empty($live_matches)) : ?>
                <div class="cricket-series-matches-list">
                    <?php foreach ($live_matches as $match) : ?>
                        <div class="cricket-series-match-card cricket-status-live" data-match-id="<?php echo esc_attr($match->match_id); ?>">
                            <div class="cricket-match-card-header">
                                <span class="cricket-match-format"><?php echo esc_html($match->format_str); ?></span>
                                <span class="cricket-live-indicator">
                                    <span class="cricket-live-dot"></span>
                                    <?php esc_html_e('LIVE', 'cricket-live-scores'); ?>
                                </span>
                            </div>
                            
                            <div class="cricket-match-card-body">
                                <!-- Team A -->
                                <div class="cricket-match-team">
                                    <div class="cricket-team-info">
                                        <?php if ($match->teama_logo) : ?>
                                            <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                        <?php endif; ?>
                                        <span class="cricket-team-name"><?php echo esc_html($match->teama_short_name ?: $match->teama_name); ?></span>
                                    </div>
                                    <div class="cricket-team-score">
                                        <span class="cricket-score"><?php echo esc_html($match->teama_scores ?: '-'); ?></span>
                                        <?php if ($match->teama_overs) : ?>
                                            <span class="cricket-overs">(<?php echo esc_html($match->teama_overs); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Team B -->
                                <div class="cricket-match-team">
                                    <div class="cricket-team-info">
                                        <?php if ($match->teamb_logo) : ?>
                                            <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                        <?php endif; ?>
                                        <span class="cricket-team-name"><?php echo esc_html($match->teamb_short_name ?: $match->teamb_name); ?></span>
                                    </div>
                                    <div class="cricket-team-score">
                                        <span class="cricket-score"><?php echo esc_html($match->teamb_scores ?: '-'); ?></span>
                                        <?php if ($match->teamb_overs) : ?>
                                            <span class="cricket-overs">(<?php echo esc_html($match->teamb_overs); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($match->status_note) : ?>
                                <div class="cricket-match-card-footer">
                                    <span class="cricket-status-note"><?php echo esc_html($match->status_note); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="cricket-empty-state">
                    <p><?php esc_html_e('No live matches at the moment.', 'cricket-live-scores'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Matches Tab -->
        <div class="cricket-series-pane <?php echo empty($live_matches) ? 'active' : ''; ?>" id="series-tab-upcoming">
            <?php if (!empty($upcoming_matches)) : ?>
                <div class="cricket-series-matches-list">
                    <?php foreach ($upcoming_matches as $match) : ?>
                        <div class="cricket-series-match-card cricket-status-upcoming" data-match-id="<?php echo esc_attr($match->match_id); ?>">
                            <div class="cricket-match-card-header">
                                <span class="cricket-match-format"><?php echo esc_html($match->format_str); ?></span>
                                <span class="cricket-match-time"><?php echo esc_html(Cricket_Shortcodes::format_match_date($match->date_start)); ?></span>
                            </div>
                            
                            <div class="cricket-match-card-body">
                                <!-- Team A -->
                                <div class="cricket-match-team">
                                    <div class="cricket-team-info">
                                        <?php if ($match->teama_logo) : ?>
                                            <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                        <?php endif; ?>
                                        <span class="cricket-team-name"><?php echo esc_html($match->teama_name); ?></span>
                                    </div>
                                </div>
                                
                                <div class="cricket-vs">vs</div>
                                
                                <!-- Team B -->
                                <div class="cricket-match-team">
                                    <div class="cricket-team-info">
                                        <?php if ($match->teamb_logo) : ?>
                                            <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                        <?php endif; ?>
                                        <span class="cricket-team-name"><?php echo esc_html($match->teamb_name); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($match->venue_name) : ?>
                                <div class="cricket-match-card-footer">
                                    <span class="cricket-venue"><?php echo esc_html($match->venue_name); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="cricket-empty-state">
                    <p><?php esc_html_e('No upcoming matches scheduled.', 'cricket-live-scores'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Matches Tab -->
        <div class="cricket-series-pane" id="series-tab-recent">
            <?php if (!empty($recent_matches)) : ?>
                <div class="cricket-series-matches-list">
                    <?php foreach ($recent_matches as $match) : ?>
                        <div class="cricket-series-match-card cricket-status-completed" data-match-id="<?php echo esc_attr($match->match_id); ?>">
                            <div class="cricket-match-card-header">
                                <span class="cricket-match-format"><?php echo esc_html($match->format_str); ?></span>
                                <span class="cricket-status-badge"><?php esc_html_e('Completed', 'cricket-live-scores'); ?></span>
                            </div>
                            
                            <div class="cricket-match-card-body">
                                <!-- Team A -->
                                <div class="cricket-match-team <?php echo $match->winning_team_id == $match->teama_id ? 'winner' : ''; ?>">
                                    <div class="cricket-team-info">
                                        <?php if ($match->teama_logo) : ?>
                                            <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                        <?php endif; ?>
                                        <span class="cricket-team-name"><?php echo esc_html($match->teama_short_name ?: $match->teama_name); ?></span>
                                        <?php if ($match->winning_team_id == $match->teama_id) : ?>
                                            <span class="cricket-winner-badge">✓</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cricket-team-score">
                                        <span class="cricket-score"><?php echo esc_html($match->teama_scores ?: '-'); ?></span>
                                        <?php if ($match->teama_overs) : ?>
                                            <span class="cricket-overs">(<?php echo esc_html($match->teama_overs); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Team B -->
                                <div class="cricket-match-team <?php echo $match->winning_team_id == $match->teamb_id ? 'winner' : ''; ?>">
                                    <div class="cricket-team-info">
                                        <?php if ($match->teamb_logo) : ?>
                                            <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                        <?php endif; ?>
                                        <span class="cricket-team-name"><?php echo esc_html($match->teamb_short_name ?: $match->teamb_name); ?></span>
                                        <?php if ($match->winning_team_id == $match->teamb_id) : ?>
                                            <span class="cricket-winner-badge">✓</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cricket-team-score">
                                        <span class="cricket-score"><?php echo esc_html($match->teamb_scores ?: '-'); ?></span>
                                        <?php if ($match->teamb_overs) : ?>
                                            <span class="cricket-overs">(<?php echo esc_html($match->teamb_overs); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($match->result) : ?>
                                <div class="cricket-match-card-footer">
                                    <span class="cricket-result"><?php echo esc_html($match->result); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="cricket-empty-state">
                    <p><?php esc_html_e('No completed matches yet.', 'cricket-live-scores'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.cricket-series-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.cricket-series-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.cricket-series-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
}

.cricket-series-title {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 600;
    color: #1a1a1a;
}

.cricket-series-meta {
    display: flex;
    gap: 15px;
    color: #666;
    font-size: 14px;
}

.cricket-series-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.cricket-series-tab {
    padding: 12px 20px;
    background: none;
    border: none;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
}

.cricket-series-tab:hover {
    color: #1a73e8;
}

.cricket-series-tab.active {
    color: #1a73e8;
}

.cricket-series-tab.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: #1a73e8;
}

.cricket-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    margin-left: 6px;
    background: #e0e0e0;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

.cricket-badge-live {
    background: #ff5252;
    color: white;
}

.cricket-series-pane {
    display: none;
}

.cricket-series-pane.active {
    display: block;
}

.cricket-series-matches-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.cricket-series-match-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}

.cricket-series-match-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.cricket-series-match-card.cricket-status-live {
    border-left: 3px solid #ff5252;
}

.cricket-match-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.cricket-match-format {
    font-size: 12px;
    color: #666;
}

.cricket-live-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #ff5252;
    font-size: 12px;
    font-weight: 600;
}

.cricket-live-dot {
    width: 8px;
    height: 8px;
    background: #ff5252;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.cricket-match-card-body {
    padding: 15px;
}

.cricket-match-team {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.cricket-match-team.winner {
    font-weight: 600;
}

.cricket-team-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cricket-team-flag {
    width: 24px;
    height: 24px;
    object-fit: contain;
}

.cricket-team-name {
    font-size: 14px;
    color: #1a1a1a;
}

.cricket-winner-badge {
    color: #4caf50;
    margin-left: 5px;
}

.cricket-team-score {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
}

.cricket-overs {
    color: #666;
    font-weight: 400;
    margin-left: 3px;
}

.cricket-vs {
    text-align: center;
    color: #999;
    font-size: 12px;
    padding: 5px 0;
}

.cricket-match-card-footer {
    padding: 10px 15px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.cricket-status-note,
.cricket-result {
    font-size: 13px;
    color: #666;
}

.cricket-venue {
    font-size: 12px;
    color: #888;
}

.cricket-match-time {
    font-size: 12px;
    color: #666;
}

.cricket-status-badge {
    font-size: 11px;
    padding: 3px 8px;
    background: #4caf50;
    color: white;
    border-radius: 3px;
}

.cricket-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

@media (max-width: 600px) {
    .cricket-series-page {
        padding: 15px;
    }
    
    .cricket-series-header {
        flex-direction: column;
        text-align: center;
    }
    
    .cricket-series-meta {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .cricket-series-tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .cricket-series-tab {
        padding: 10px 15px;
        white-space: nowrap;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.cricket-series-tab');
    const panes = document.querySelectorAll('.cricket-series-pane');
    
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            tabs.forEach(function(t) { t.classList.remove('active'); });
            panes.forEach(function(p) { p.classList.remove('active'); });
            
            this.classList.add('active');
            document.getElementById('series-tab-' + targetTab).classList.add('active');
        });
    });
});
</script>
