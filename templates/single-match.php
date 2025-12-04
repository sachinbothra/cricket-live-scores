<?php
/**
 * Template: Single Match
 *
 * Displays a complete match page with tabs for different sections.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// $match is passed from the shortcode
if (!isset($match) || !$match) {
    echo '<div class="cricket-error">' . esc_html__('Match not found.', 'cricket-live-scores') . '</div>';
    return;
}

$db = Cricket_Database::get_instance();
$scorecard = $db->get_match_scorecard($match->match_id);
$squad = $db->get_match_squad($match->match_id);
$is_live = (int) $match->status === 3;

// Parse live data
$live_data = $match->live ? json_decode($match->live, true) : null;
?>

<div class="cricket-single-match" data-match-id="<?php echo esc_attr($match->match_id); ?>">
    <!-- Match Header -->
    <div class="cricket-match-header-section">
        <div class="cricket-match-series-info">
            <span class="cricket-series-title"><?php echo esc_html($match->competition_title); ?></span>
            <span class="cricket-match-info"><?php echo esc_html($match->subtitle ?: $match->format_str); ?></span>
        </div>
        
        <?php if ($is_live) : ?>
            <span class="cricket-live-badge">
                <span class="cricket-live-dot"></span>
                <?php esc_html_e('LIVE', 'cricket-live-scores'); ?>
            </span>
        <?php else : ?>
            <span class="cricket-status-badge cricket-status-<?php echo esc_attr($match->status == 1 ? 'upcoming' : 'completed'); ?>">
                <?php echo esc_html($match->status_str); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Score Section -->
    <div class="cricket-score-section">
        <div class="cricket-teams-score">
            <!-- Team A -->
            <div class="cricket-team-score-block">
                <div class="cricket-team-info">
                    <?php if ($match->teama_logo) : ?>
                        <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-logo-lg">
                    <?php endif; ?>
                    <div class="cricket-team-details">
                        <span class="cricket-team-name-full"><?php echo esc_html($match->teama_name); ?></span>
                        <span class="cricket-team-name-short"><?php echo esc_html($match->teama_short_name); ?></span>
                    </div>
                </div>
                <div class="cricket-score-display">
                    <span class="cricket-score-runs" id="teama-score"><?php echo esc_html($match->teama_scores ?: '-'); ?></span>
                    <?php if ($match->teama_overs) : ?>
                        <span class="cricket-score-overs" id="teama-overs">(<?php echo esc_html($match->teama_overs); ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cricket-vs-divider">
                <span><?php esc_html_e('VS', 'cricket-live-scores'); ?></span>
            </div>
            
            <!-- Team B -->
            <div class="cricket-team-score-block">
                <div class="cricket-team-info">
                    <?php if ($match->teamb_logo) : ?>
                        <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-logo-lg">
                    <?php endif; ?>
                    <div class="cricket-team-details">
                        <span class="cricket-team-name-full"><?php echo esc_html($match->teamb_name); ?></span>
                        <span class="cricket-team-name-short"><?php echo esc_html($match->teamb_short_name); ?></span>
                    </div>
                </div>
                <div class="cricket-score-display">
                    <span class="cricket-score-runs" id="teamb-score"><?php echo esc_html($match->teamb_scores ?: '-'); ?></span>
                    <?php if ($match->teamb_overs) : ?>
                        <span class="cricket-score-overs" id="teamb-overs">(<?php echo esc_html($match->teamb_overs); ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Status Note -->
        <?php if ($match->status_note) : ?>
            <div class="cricket-status-note" id="status-note">
                <?php echo esc_html($match->status_note); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Live Batting Info -->
    <?php if ($is_live && $live_data) : ?>
    <div class="cricket-live-info" id="live-batting-info">
        <?php if (isset($live_data['batsmen']) && is_array($live_data['batsmen'])) : ?>
        <div class="cricket-current-batsmen">
            <h4><?php esc_html_e('Batting', 'cricket-live-scores'); ?></h4>
            <div class="cricket-batsmen-list">
                <?php foreach ($live_data['batsmen'] as $batsman) : ?>
                <div class="cricket-batsman <?php echo !empty($batsman['on_strike']) ? 'on-strike' : ''; ?>">
                    <span class="cricket-batsman-name">
                        <?php echo esc_html($batsman['name'] ?? ''); ?>
                        <?php if (!empty($batsman['on_strike'])) : ?>
                            <span class="cricket-strike-indicator">*</span>
                        <?php endif; ?>
                    </span>
                    <span class="cricket-batsman-runs"><?php echo esc_html($batsman['runs'] ?? 0); ?> (<?php echo esc_html($batsman['balls_faced'] ?? 0); ?>)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($live_data['bowlers']) && is_array($live_data['bowlers']) && !empty($live_data['bowlers'])) : ?>
        <div class="cricket-current-bowler">
            <h4><?php esc_html_e('Bowling', 'cricket-live-scores'); ?></h4>
            <?php $bowler = $live_data['bowlers'][0]; ?>
            <div class="cricket-bowler">
                <span class="cricket-bowler-name"><?php echo esc_html($bowler['name'] ?? ''); ?></span>
                <span class="cricket-bowler-figures">
                    <?php 
                    echo esc_html(($bowler['overs'] ?? 0) . '-' . ($bowler['maidens'] ?? 0) . '-' . ($bowler['runs_conceded'] ?? 0) . '-' . ($bowler['wickets'] ?? 0)); 
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($live_data['last_6_balls']) && is_array($live_data['last_6_balls'])) : ?>
        <div class="cricket-last-balls">
            <span class="cricket-last-balls-label"><?php esc_html_e('Recent:', 'cricket-live-scores'); ?></span>
            <div class="cricket-balls">
                <?php foreach ($live_data['last_6_balls'] as $ball) : ?>
                <span class="cricket-ball cricket-ball-<?php echo esc_attr(strtolower(str_replace(' ', '', (string) $ball))); ?>">
                    <?php echo esc_html($ball); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="cricket-match-tabs">
        <button class="cricket-match-tab active" data-tab="info"><?php esc_html_e('Info', 'cricket-live-scores'); ?></button>
        <button class="cricket-match-tab" data-tab="scorecard"><?php esc_html_e('Scorecard', 'cricket-live-scores'); ?></button>
        <button class="cricket-match-tab" data-tab="squads"><?php esc_html_e('Squads', 'cricket-live-scores'); ?></button>
    </div>

    <!-- Tab Content -->
    <div class="cricket-match-tab-content">
        <!-- Info Tab -->
        <div class="cricket-tab-pane active" id="tab-info">
            <div class="cricket-match-details">
                <div class="cricket-detail-row">
                    <span class="cricket-detail-label"><?php esc_html_e('Match', 'cricket-live-scores'); ?></span>
                    <span class="cricket-detail-value"><?php echo esc_html($match->title); ?></span>
                </div>
                
                <?php if ($match->venue_name) : ?>
                <div class="cricket-detail-row">
                    <span class="cricket-detail-label"><?php esc_html_e('Venue', 'cricket-live-scores'); ?></span>
                    <span class="cricket-detail-value">
                        <?php echo esc_html($match->venue_name); ?>
                        <?php if ($match->venue_location) : ?>
                            , <?php echo esc_html($match->venue_location); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="cricket-detail-row">
                    <span class="cricket-detail-label"><?php esc_html_e('Date', 'cricket-live-scores'); ?></span>
                    <span class="cricket-detail-value"><?php echo esc_html(Cricket_Shortcodes::format_match_date($match->date_start)); ?></span>
                </div>
                
                <?php if ($match->toss_text) : ?>
                <div class="cricket-detail-row">
                    <span class="cricket-detail-label"><?php esc_html_e('Toss', 'cricket-live-scores'); ?></span>
                    <span class="cricket-detail-value"><?php echo esc_html($match->toss_text); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($match->result) : ?>
                <div class="cricket-detail-row">
                    <span class="cricket-detail-label"><?php esc_html_e('Result', 'cricket-live-scores'); ?></span>
                    <span class="cricket-detail-value"><?php echo esc_html($match->result); ?></span>
                </div>
                <?php endif; ?>
                
                <?php 
                $umpires = $match->umpires ? json_decode($match->umpires, true) : null;
                if ($umpires && is_array($umpires)) : 
                ?>
                <div class="cricket-detail-row">
                    <span class="cricket-detail-label"><?php esc_html_e('Umpires', 'cricket-live-scores'); ?></span>
                    <span class="cricket-detail-value">
                        <?php 
                        $umpire_names = array();
                        foreach ($umpires as $umpire) {
                            if (isset($umpire['name'])) {
                                $umpire_names[] = $umpire['name'];
                            }
                        }
                        echo esc_html(implode(', ', $umpire_names));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Scorecard Tab -->
        <div class="cricket-tab-pane" id="tab-scorecard">
            <?php if (!empty($scorecard)) : ?>
                <?php foreach ($scorecard as $innings) : ?>
                <div class="cricket-innings-scorecard">
                    <h3 class="cricket-innings-title">
                        <?php echo esc_html($innings->name); ?>
                        <span class="cricket-innings-score">
                            <?php echo esc_html($innings->runs); ?>/<?php echo esc_html($innings->wickets); ?>
                            (<?php echo esc_html($innings->overs); ?> ov)
                        </span>
                    </h3>
                    
                    <!-- Batting -->
                    <?php 
                    $batsmen = $db->decode_json($innings->batsmen);
                    if (!empty($batsmen)) : 
                    ?>
                    <div class="cricket-scorecard-section">
                        <h4><?php esc_html_e('Batting', 'cricket-live-scores'); ?></h4>
                        <table class="cricket-scorecard-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Batter', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('R', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('B', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('4s', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('6s', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('SR', 'cricket-live-scores'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batsmen as $batsman) : ?>
                                <tr>
                                    <td>
                                        <span class="cricket-player-name"><?php echo esc_html($batsman['name'] ?? ''); ?></span>
                                        <?php if (isset($batsman['how_out']) && $batsman['how_out'] !== 'not out') : ?>
                                            <span class="cricket-dismissal"><?php echo esc_html($batsman['how_out']); ?></span>
                                        <?php else : ?>
                                            <span class="cricket-not-out"><?php esc_html_e('not out', 'cricket-live-scores'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cricket-stat"><?php echo esc_html($batsman['runs'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($batsman['balls_faced'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($batsman['fours'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($batsman['sixes'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($batsman['strike_rate'] ?? '0.00'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Extras -->
                    <?php 
                    $extras = $db->decode_json($innings->extras);
                    if (!empty($extras)) : 
                    ?>
                    <div class="cricket-extras">
                        <span class="cricket-extras-label"><?php esc_html_e('Extras:', 'cricket-live-scores'); ?></span>
                        <span class="cricket-extras-value">
                            <?php echo esc_html($extras['total'] ?? 0); ?>
                            (b <?php echo esc_html($extras['byes'] ?? 0); ?>, 
                            lb <?php echo esc_html($extras['legbyes'] ?? 0); ?>, 
                            w <?php echo esc_html($extras['wides'] ?? 0); ?>, 
                            nb <?php echo esc_html($extras['noballs'] ?? 0); ?>)
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Bowling -->
                    <?php 
                    $bowlers = $db->decode_json($innings->bowlers);
                    if (!empty($bowlers)) : 
                    ?>
                    <div class="cricket-scorecard-section">
                        <h4><?php esc_html_e('Bowling', 'cricket-live-scores'); ?></h4>
                        <table class="cricket-scorecard-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Bowler', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('O', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('M', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('R', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('W', 'cricket-live-scores'); ?></th>
                                    <th><?php esc_html_e('Econ', 'cricket-live-scores'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bowlers as $bowler) : ?>
                                <tr>
                                    <td>
                                        <span class="cricket-player-name"><?php echo esc_html($bowler['name'] ?? ''); ?></span>
                                    </td>
                                    <td class="cricket-stat"><?php echo esc_html($bowler['overs'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($bowler['maidens'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($bowler['runs_conceded'] ?? 0); ?></td>
                                    <td class="cricket-stat cricket-wickets"><?php echo esc_html($bowler['wickets'] ?? 0); ?></td>
                                    <td class="cricket-stat"><?php echo esc_html($bowler['econ'] ?? '0.00'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Fall of Wickets -->
                    <?php 
                    $fows = $db->decode_json($innings->fows);
                    if (!empty($fows)) : 
                    ?>
                    <div class="cricket-fow">
                        <h4><?php esc_html_e('Fall of Wickets', 'cricket-live-scores'); ?></h4>
                        <div class="cricket-fow-list">
                            <?php foreach ($fows as $fow) : ?>
                            <span class="cricket-fow-item">
                                <?php echo esc_html(($fow['score_at_dismissal'] ?? '') . '-' . ($fow['number'] ?? '') . ' (' . ($fow['name'] ?? '') . ', ' . ($fow['overs_at_dismissal'] ?? '') . ')'); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="cricket-empty-state">
                    <p><?php esc_html_e('Scorecard not available yet.', 'cricket-live-scores'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Squads Tab -->
        <div class="cricket-tab-pane" id="tab-squads">
            <?php if (!empty($squad)) : ?>
                <?php
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
                    $teams[$team_id]['players'][] = $player;
                }
                ?>
                
                <div class="cricket-squads-container">
                    <?php foreach ($teams as $team_id => $team_data) : ?>
                        <?php
                        $team_name = $team_id == $match->teama_id ? $match->teama_name : ($team_id == $match->teamb_id ? $match->teamb_name : '');
                        ?>
                        <div class="cricket-squad-team">
                            <h3 class="cricket-squad-team-name"><?php echo esc_html($team_name); ?></h3>
                            <div class="cricket-squad-list">
                                <?php foreach ($team_data['players'] as $player) : ?>
                                <div class="cricket-squad-player <?php echo $player->is_playing ? 'playing-xi' : 'bench'; ?>">
                                    <span class="cricket-player-name">
                                        <?php echo esc_html($player->player_name); ?>
                                        <?php if ($player->is_captain) : ?>
                                            <span class="cricket-badge-c"><?php esc_html_e('C', 'cricket-live-scores'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($player->is_keeper) : ?>
                                            <span class="cricket-badge-wk"><?php esc_html_e('WK', 'cricket-live-scores'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="cricket-player-role"><?php echo esc_html($player->role_str ?: $player->role); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="cricket-empty-state">
                    <p><?php esc_html_e('Squad information not available yet.', 'cricket-live-scores'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.cricket-match-tab');
    const panes = document.querySelectorAll('.cricket-tab-pane');
    
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            tabs.forEach(function(t) { t.classList.remove('active'); });
            panes.forEach(function(p) { p.classList.remove('active'); });
            
            this.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
        });
    });
});
</script>
