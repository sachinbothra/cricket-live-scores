<?php
/**
 * Single Match Template
 * Displays a single cricket match page
 */

if (!defined('ABSPATH')) exit;

// Get match from query var
$match_slug = get_query_var('cricket_match');
$db = new Cricket_Database();
$match = $db->get_match_by_slug($match_slug);

if (!$match) {
    wp_die(__('Match not found', 'cricket-live-scores'), __('Match Not Found', 'cricket-live-scores'), ['response' => 404]);
}

$is_live = in_array(strtolower($match->status), ['live', 'in progress']);
$match_data = json_decode($match->match_data, true);
$current_batsmen = json_decode($match->current_batsmen, true) ?: [];
$current_bowlers = json_decode($match->current_bowlers, true) ?: [];
$recent_balls = json_decode($match->recent_balls, true) ?: [];

// Page title
$page_title = $match->title . ' - ' . __('Live Score', 'cricket-live-scores');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($page_title); ?></title>
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo esc_attr($match->title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($match->teama_name . ' vs ' . $match->teamb_name . ' - ' . $match->format); ?>">
    <meta property="og:type" content="website">
    
    <?php wp_head(); ?>
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .site-header {
            background: linear-gradient(135deg, #046a38 0%, #1a237e 100%);
            color: #fff;
            padding: 20px;
        }
        
        .site-header .container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .site-logo {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
        }
        
        .site-logo:hover {
            color: #fff;
        }
        
        .back-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover {
            color: #fff;
        }
        
        .match-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .site-footer {
            text-align: center;
            padding: 30px 20px;
            color: #666;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .match-container {
                margin: 15px auto;
                padding: 0 10px;
            }
            
            .site-header .container {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body <?php body_class('cricket-match-page'); ?>>

<header class="site-header">
    <div class="container">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
            <?php echo esc_html(get_bloginfo('name')); ?>
        </a>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="back-link">
            <span>←</span> <?php esc_html_e('Back to Home', 'cricket-live-scores'); ?>
        </a>
    </div>
</header>

<main class="match-container">
    <div class="cricket-single-match" data-match-id="<?php echo esc_attr($match->match_id); ?>">
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
                                <?php foreach ($recent_balls as $ball) : 
                                    $ball_class = Cricket_Shortcodes::get_ball_class_static($ball);
                                ?>
                                    <span class="ball-item <?php echo esc_attr($ball_class); ?>">
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
                    <span class="info-value"><?php echo esc_html(trim($match->venue_name . ', ' . $match->venue_city, ', ')); ?></span>
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
    </div>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?> | 
    <?php esc_html_e('Powered by Cricket Live Scores', 'cricket-live-scores'); ?></p>
</footer>

<?php wp_footer(); ?>

</body>
</html>
