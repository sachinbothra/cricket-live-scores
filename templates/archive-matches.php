<?php
/**
 * Template: Archive Matches
 *
 * Displays an archive of all matches with filters.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$db = Cricket_Database::get_instance();

// Get filter values from query string
$filter_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$filter_format = isset($_GET['format']) ? sanitize_text_field(wp_unslash($_GET['format'])) : '';
$filter_search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

$per_page = get_option('cricket_matches_per_page', 20);

// Build query args
$args = array(
    'limit' => $per_page,
    'offset' => ($current_page - 1) * $per_page,
);

if ($filter_status !== '') {
    $args['status'] = absint($filter_status);
}

if ($filter_format !== '') {
    $args['format'] = $filter_format;
}

if ($filter_search !== '') {
    $args['search'] = $filter_search;
}

$matches = $db->get_matches($args);
?>

<div class="cricket-archive-page">
    <!-- Filter Form -->
    <div class="cricket-archive-filters">
        <form method="get" class="cricket-filter-form">
            <div class="cricket-filter-group">
                <label for="filter-search"><?php esc_html_e('Search', 'cricket-live-scores'); ?></label>
                <input type="text" 
                       id="filter-search" 
                       name="search" 
                       value="<?php echo esc_attr($filter_search); ?>" 
                       placeholder="<?php esc_attr_e('Team, match, venue...', 'cricket-live-scores'); ?>">
            </div>
            
            <div class="cricket-filter-group">
                <label for="filter-status"><?php esc_html_e('Status', 'cricket-live-scores'); ?></label>
                <select id="filter-status" name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'cricket-live-scores'); ?></option>
                    <option value="3" <?php selected($filter_status, '3'); ?>><?php esc_html_e('Live', 'cricket-live-scores'); ?></option>
                    <option value="1" <?php selected($filter_status, '1'); ?>><?php esc_html_e('Upcoming', 'cricket-live-scores'); ?></option>
                    <option value="2" <?php selected($filter_status, '2'); ?>><?php esc_html_e('Completed', 'cricket-live-scores'); ?></option>
                </select>
            </div>
            
            <div class="cricket-filter-group">
                <label for="filter-format"><?php esc_html_e('Format', 'cricket-live-scores'); ?></label>
                <select id="filter-format" name="format">
                    <option value=""><?php esc_html_e('All Formats', 'cricket-live-scores'); ?></option>
                    <option value="t20" <?php selected($filter_format, 't20'); ?>><?php esc_html_e('T20', 'cricket-live-scores'); ?></option>
                    <option value="odi" <?php selected($filter_format, 'odi'); ?>><?php esc_html_e('ODI', 'cricket-live-scores'); ?></option>
                    <option value="test" <?php selected($filter_format, 'test'); ?>><?php esc_html_e('Test', 'cricket-live-scores'); ?></option>
                    <option value="t10" <?php selected($filter_format, 't10'); ?>><?php esc_html_e('T10', 'cricket-live-scores'); ?></option>
                </select>
            </div>
            
            <div class="cricket-filter-actions">
                <button type="submit" class="cricket-btn cricket-btn-primary">
                    <?php esc_html_e('Filter', 'cricket-live-scores'); ?>
                </button>
                <a href="<?php echo esc_url(remove_query_arg(array('status', 'format', 'search', 'paged'))); ?>" class="cricket-btn cricket-btn-secondary">
                    <?php esc_html_e('Reset', 'cricket-live-scores'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Matches Grid -->
    <?php if (!empty($matches)) : ?>
        <div class="cricket-archive-grid">
            <?php foreach ($matches as $match) : ?>
                <?php
                $status_class = '';
                $status_text = '';
                switch ((int) $match->status) {
                    case 1:
                        $status_class = 'upcoming';
                        $status_text = __('Upcoming', 'cricket-live-scores');
                        break;
                    case 2:
                        $status_class = 'completed';
                        $status_text = __('Completed', 'cricket-live-scores');
                        break;
                    case 3:
                        $status_class = 'live';
                        $status_text = __('Live', 'cricket-live-scores');
                        break;
                }
                ?>
                <div class="cricket-archive-card cricket-status-<?php echo esc_attr($status_class); ?>" data-match-id="<?php echo esc_attr($match->match_id); ?>">
                    <div class="cricket-archive-card-header">
                        <span class="cricket-series-name"><?php echo esc_html($match->competition_title); ?></span>
                        <span class="cricket-status-indicator cricket-status-<?php echo esc_attr($status_class); ?>">
                            <?php if ($status_class === 'live') : ?>
                                <span class="cricket-live-dot"></span>
                            <?php endif; ?>
                            <?php echo esc_html($status_text); ?>
                        </span>
                    </div>
                    
                    <div class="cricket-archive-card-body">
                        <div class="cricket-archive-team">
                            <div class="cricket-team-left">
                                <?php if ($match->teama_logo) : ?>
                                    <img src="<?php echo esc_url($match->teama_logo); ?>" alt="<?php echo esc_attr($match->teama_name); ?>" class="cricket-team-flag">
                                <?php else : ?>
                                    <div class="cricket-team-flag cricket-placeholder"></div>
                                <?php endif; ?>
                                <span class="cricket-team-name"><?php echo esc_html($match->teama_short_name ?: $match->teama_name); ?></span>
                            </div>
                            <?php if ($match->teama_scores) : ?>
                                <span class="cricket-team-score"><?php echo esc_html($match->teama_scores); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="cricket-archive-team">
                            <div class="cricket-team-left">
                                <?php if ($match->teamb_logo) : ?>
                                    <img src="<?php echo esc_url($match->teamb_logo); ?>" alt="<?php echo esc_attr($match->teamb_name); ?>" class="cricket-team-flag">
                                <?php else : ?>
                                    <div class="cricket-team-flag cricket-placeholder"></div>
                                <?php endif; ?>
                                <span class="cricket-team-name"><?php echo esc_html($match->teamb_short_name ?: $match->teamb_name); ?></span>
                            </div>
                            <?php if ($match->teamb_scores) : ?>
                                <span class="cricket-team-score"><?php echo esc_html($match->teamb_scores); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="cricket-archive-card-footer">
                        <?php if ($match->status_note) : ?>
                            <span class="cricket-match-result"><?php echo esc_html($match->status_note); ?></span>
                        <?php elseif ($match->result) : ?>
                            <span class="cricket-match-result"><?php echo esc_html($match->result); ?></span>
                        <?php elseif ($match->date_start) : ?>
                            <span class="cricket-match-date"><?php echo esc_html(Cricket_Shortcodes::format_match_date($match->date_start)); ?></span>
                        <?php endif; ?>
                        
                        <?php if ($match->venue_name) : ?>
                            <span class="cricket-match-venue"><?php echo esc_html($match->venue_name); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if (count($matches) >= $per_page) : ?>
            <div class="cricket-archive-pagination">
                <?php if ($current_page > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="cricket-btn cricket-btn-secondary">
                        &laquo; <?php esc_html_e('Previous', 'cricket-live-scores'); ?>
                    </a>
                <?php endif; ?>
                
                <span class="cricket-page-info">
                    <?php 
                    /* translators: %d: page number */
                    printf(esc_html__('Page %d', 'cricket-live-scores'), $current_page); 
                    ?>
                </span>
                
                <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="cricket-btn cricket-btn-secondary">
                    <?php esc_html_e('Next', 'cricket-live-scores'); ?> &raquo;
                </a>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <div class="cricket-empty-state">
            <p><?php esc_html_e('No matches found matching your criteria.', 'cricket-live-scores'); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
.cricket-archive-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.cricket-archive-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.cricket-filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.cricket-filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.cricket-filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
}

.cricket-filter-group input,
.cricket-filter-group select {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}

.cricket-filter-group input:focus,
.cricket-filter-group select:focus {
    outline: none;
    border-color: #1a73e8;
    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.1);
}

.cricket-filter-actions {
    display: flex;
    gap: 10px;
}

.cricket-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.cricket-btn-primary {
    background: #1a73e8;
    color: white;
}

.cricket-btn-primary:hover {
    background: #1557b0;
}

.cricket-btn-secondary {
    background: #fff;
    color: #333;
    border: 1px solid #ddd;
}

.cricket-btn-secondary:hover {
    background: #f5f5f5;
}

.cricket-archive-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.cricket-archive-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
}

.cricket-archive-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.cricket-archive-card.cricket-status-live {
    border-left: 3px solid #ff5252;
}

.cricket-archive-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.cricket-series-name {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.cricket-status-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 3px;
}

.cricket-status-indicator.cricket-status-live {
    background: #ff5252;
    color: white;
}

.cricket-status-indicator.cricket-status-upcoming {
    background: #2196f3;
    color: white;
}

.cricket-status-indicator.cricket-status-completed {
    background: #4caf50;
    color: white;
}

.cricket-live-dot {
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.cricket-archive-card-body {
    padding: 15px;
}

.cricket-archive-team {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.cricket-team-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cricket-team-flag {
    width: 24px;
    height: 24px;
    object-fit: contain;
}

.cricket-team-flag.cricket-placeholder {
    background: #e0e0e0;
    border-radius: 50%;
}

.cricket-team-name {
    font-size: 14px;
    font-weight: 500;
    color: #1a1a1a;
}

.cricket-team-score {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
}

.cricket-archive-card-footer {
    padding: 12px 15px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.cricket-match-result {
    display: block;
    font-size: 13px;
    color: #333;
    margin-bottom: 5px;
}

.cricket-match-date,
.cricket-match-venue {
    display: block;
    font-size: 12px;
    color: #666;
}

.cricket-archive-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.cricket-page-info {
    font-size: 14px;
    color: #666;
}

.cricket-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
}

@media (max-width: 768px) {
    .cricket-filter-form {
        flex-direction: column;
    }
    
    .cricket-filter-group {
        width: 100%;
    }
    
    .cricket-filter-group input,
    .cricket-filter-group select {
        width: 100%;
        min-width: auto;
    }
    
    .cricket-filter-actions {
        width: 100%;
    }
    
    .cricket-filter-actions .cricket-btn {
        flex: 1;
    }
    
    .cricket-archive-grid {
        grid-template-columns: 1fr;
    }
}
</style>
