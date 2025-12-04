/**
 * Cricket Live Scores Auto-Refresh
 * Handles automatic refresh of live match data every 2 seconds
 */

(function($) {
    'use strict';
    
    // Refresh interval - 2 seconds (2000ms) for live updates
    const REFRESH_INTERVAL = 2000;
    
    // Store active refresh timers
    let refreshTimers = {};
    
    /**
     * Initialize auto-refresh for all cricket containers
     */
    function init() {
        // Auto-refresh live matches containers
        $('.cricket-live-matches, .cricket-live-widget, [data-type="live"]').each(function() {
            const $container = $(this);
            startRefresh($container, 'live');
        });
        
        // Auto-refresh single match pages
        $('.cricket-single-match').each(function() {
            const $container = $(this);
            const matchId = $container.data('match-id');
            if (matchId) {
                startSingleMatchRefresh($container, matchId);
            }
        });
        
        // Also check for full match containers
        $('.cricket-full-match.is-live').each(function() {
            const $container = $(this).closest('.cricket-single-match');
            const matchId = $container.data('match-id');
            if (matchId) {
                startSingleMatchRefresh($container, matchId);
            }
        });
    }
    
    /**
     * Start refresh for a matches container
     */
    function startRefresh($container, type) {
        const containerId = $container.attr('id') || 'container_' + Math.random().toString(36).substr(2, 9);
        $container.attr('id', containerId);
        
        // Initial check if live matches exist
        if (type === 'live') {
            // Start the refresh timer
            refreshTimers[containerId] = setInterval(function() {
                refreshLiveMatches($container);
            }, REFRESH_INTERVAL);
        }
    }
    
    /**
     * Start refresh for single match page
     */
    function startSingleMatchRefresh($container, matchId) {
        const containerId = 'match_' + matchId;
        
        // Set up 2-second refresh for live match
        refreshTimers[containerId] = setInterval(function() {
            refreshSingleMatch($container, matchId);
        }, REFRESH_INTERVAL);
    }
    
    /**
     * Refresh live matches list
     */
    function refreshLiveMatches($container) {
        const limit = $container.data('limit') || 10;
        
        $.ajax({
            url: cricketLive.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cricket_get_live_matches',
                nonce: cricketLive.nonce,
                limit: limit
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    const $list = $container.find('.cricket-matches-list, .cricket-widget-body');
                    if ($list.length) {
                        // Smooth update - only update if content changed
                        const currentHtml = $list.html().trim();
                        const newHtml = response.data.html.trim();
                        
                        if (currentHtml !== newHtml) {
                            $list.fadeOut(100, function() {
                                $(this).html(newHtml).fadeIn(100);
                            });
                        }
                    }
                }
            },
            error: function() {
                console.log('Cricket Live: Error refreshing matches');
            }
        });
    }
    
    /**
     * Refresh single match data
     */
    function refreshSingleMatch($container, matchId) {
        $.ajax({
            url: cricketLive.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cricket_get_match',
                nonce: cricketLive.nonce,
                match_id: matchId
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    updateMatchDisplay($container, response.data.match, response.data.html);
                }
            },
            error: function() {
                console.log('Cricket Live: Error refreshing match ' + matchId);
            }
        });
    }
    
    /**
     * Update match display with new data
     */
    function updateMatchDisplay($container, matchData, html) {
        // Update score section
        const $scoreSection = $container.find('.match-score-section');
        const $newContent = $(html);
        const $newScoreSection = $newContent.filter('.match-score-section');
        
        if ($scoreSection.length && $newScoreSection.length) {
            // Check if scores changed
            const currentScores = getScoresFromElement($scoreSection);
            const newScores = getScoresFromElement($newScoreSection);
            
            if (JSON.stringify(currentScores) !== JSON.stringify(newScores)) {
                // Animate score update
                $scoreSection.addClass('score-updated');
                $container.find('.match-score-section, .match-status-note-full, .match-result-full, .match-live-stats').remove();
                $container.find('.match-full-header').after(html);
                
                setTimeout(function() {
                    $container.find('.match-score-section').removeClass('score-updated');
                }, 500);
            }
        }
        
        // Update status note if changed
        if (matchData.status_note) {
            const $statusNote = $container.find('.match-status-note-full');
            if ($statusNote.length) {
                if ($statusNote.text() !== matchData.status_note) {
                    $statusNote.text(matchData.status_note).addClass('status-updated');
                    setTimeout(function() {
                        $statusNote.removeClass('status-updated');
                    }, 500);
                }
            }
        }
        
        // Update live stats
        if (matchData.is_live) {
            updateLiveStats($container, matchData);
        }
        
        // If match is no longer live, stop refreshing
        if (!matchData.is_live) {
            const containerId = 'match_' + matchData.match_id;
            if (refreshTimers[containerId]) {
                clearInterval(refreshTimers[containerId]);
                delete refreshTimers[containerId];
            }
        }
    }
    
    /**
     * Get scores from score section element
     */
    function getScoresFromElement($element) {
        return {
            teamA: $element.find('.team-a-row .score').text().trim(),
            teamB: $element.find('.team-b-row .score').text().trim()
        };
    }
    
    /**
     * Update live stats section
     */
    function updateLiveStats($container, matchData) {
        const $liveStats = $container.find('.match-live-stats');
        
        // Update batsmen
        if (matchData.current_batsmen && matchData.current_batsmen.length) {
            const $batsmen = $liveStats.find('.live-batsmen');
            if ($batsmen.length) {
                let batsmenHtml = '<h3>' + (cricketLive.strings.atCrease || 'At Crease') + '</h3>';
                matchData.current_batsmen.forEach(function(batsman) {
                    batsmenHtml += '<div class="batsman-row">' +
                        '<span class="batsman-name">' + escapeHtml(batsman.name || '') + '</span>' +
                        '<span class="batsman-stats">' + (batsman.runs || 0) + ' (' + (batsman.balls || 0) + ')</span>' +
                        '</div>';
                });
                $batsmen.html(batsmenHtml);
            }
        }
        
        // Update bowler
        if (matchData.current_bowlers && matchData.current_bowlers.length) {
            const $bowler = $liveStats.find('.live-bowler');
            if ($bowler.length) {
                let bowlerHtml = '<h3>' + (cricketLive.strings.bowling || 'Bowling') + '</h3>';
                matchData.current_bowlers.forEach(function(bowler) {
                    bowlerHtml += '<div class="bowler-row">' +
                        '<span class="bowler-name">' + escapeHtml(bowler.name || '') + '</span>' +
                        '<span class="bowler-stats">' + (bowler.wickets || 0) + '/' + (bowler.runs || 0) + ' (' + (bowler.overs || 0) + ')</span>' +
                        '</div>';
                });
                $bowler.html(bowlerHtml);
            }
        }
        
        // Update recent balls
        if (matchData.recent_balls && matchData.recent_balls.length) {
            const $recentBalls = $liveStats.find('.balls-container');
            if ($recentBalls.length) {
                let ballsHtml = '';
                matchData.recent_balls.forEach(function(ball) {
                    const ballClass = getBallClass(ball);
                    ballsHtml += '<span class="ball-item ' + ballClass + '">' + escapeHtml(ball) + '</span>';
                });
                $recentBalls.html(ballsHtml);
            }
        }
    }
    
    /**
     * Get CSS class for ball
     */
    function getBallClass(ball) {
        const ballStr = (ball + '').toLowerCase().trim();
        
        if (ballStr === 'w' || ballStr === 'out') {
            return 'ball-wicket';
        } else if (ballStr === '4') {
            return 'ball-four';
        } else if (ballStr === '6') {
            return 'ball-six';
        } else if (ballStr.indexOf('wd') !== -1 || ballStr.indexOf('nb') !== -1) {
            return 'ball-extra';
        } else if (ballStr === '0' || ballStr === '.') {
            return 'ball-dot';
        }
        
        return 'ball-run';
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return (text + '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Stop all refresh timers
     */
    function stopAllRefresh() {
        Object.keys(refreshTimers).forEach(function(key) {
            clearInterval(refreshTimers[key]);
        });
        refreshTimers = {};
    }
    
    /**
     * Cleanup on page unload
     */
    $(window).on('beforeunload', function() {
        stopAllRefresh();
    });
    
    /**
     * Handle visibility change - pause refresh when tab is hidden
     */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Tab is hidden, pause refresh
            stopAllRefresh();
        } else {
            // Tab is visible again, restart refresh
            init();
        }
    });
    
    // Initialize on document ready
    $(document).ready(function() {
        if (typeof cricketLive !== 'undefined') {
            init();
        }
    });
    
})(jQuery);
