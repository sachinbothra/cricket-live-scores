/**
 * Cricket Live Scores - Auto Refresh JavaScript
 *
 * Handles automatic refreshing of live match data every 2 seconds.
 * Updates scores, status, and batting information without full page reload.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Refresh interval in milliseconds (default: 2 seconds).
     * @type {number}
     */
    var REFRESH_INTERVAL = window.cricketLiveScores && window.cricketLiveScores.refreshInterval 
        ? window.cricketLiveScores.refreshInterval 
        : 2000;

    /**
     * Timer reference for the refresh interval.
     * @type {number|null}
     */
    var refreshTimer = null;

    /**
     * Flag to track if page is visible.
     * @type {boolean}
     */
    var isPageVisible = true;

    /**
     * Flag to track if refresh is in progress.
     * @type {boolean}
     */
    var isRefreshing = false;

    /**
     * Collection of match elements to update.
     * @type {jQuery}
     */
    var $matchElements = null;

    /**
     * Initialize auto-refresh for single match pages.
     */
    function initSingleMatchRefresh() {
        var $singleMatch = $('.cricket-single-match');
        
        if ($singleMatch.length === 0) {
            return;
        }

        var matchId = $singleMatch.data('match-id');
        
        if (!matchId) {
            return;
        }

        // Start refresh loop
        startRefresh(function() {
            refreshSingleMatch(matchId);
        });
    }

    /**
     * Initialize auto-refresh for widget matches.
     */
    function initWidgetRefresh() {
        $matchElements = $('.cricket-widget-card[data-status="live"]');
        
        if ($matchElements.length === 0) {
            return;
        }

        // Start refresh loop for all live matches
        startRefresh(function() {
            refreshWidgetMatches();
        });
    }

    /**
     * Start the refresh timer.
     *
     * @param {Function} callback - Function to call on each refresh
     */
    function startRefresh(callback) {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }

        refreshTimer = setInterval(function() {
            if (isPageVisible && !isRefreshing) {
                callback();
            }
        }, REFRESH_INTERVAL);

        // Do initial refresh
        callback();
    }

    /**
     * Stop the refresh timer.
     */
    function stopRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    /**
     * Refresh single match data.
     *
     * @param {number} matchId - Match ID to refresh
     */
    function refreshSingleMatch(matchId) {
        if (isRefreshing) {
            return;
        }

        isRefreshing = true;

        $.ajax({
            url: window.cricketLiveScores.ajaxUrl,
            type: 'GET',
            data: {
                action: 'cricket_get_live_match_data',
                match_id: matchId,
                nonce: window.cricketLiveScores.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateSingleMatchDisplay(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Cricket refresh error:', error);
            },
            complete: function() {
                isRefreshing = false;
            }
        });
    }

    /**
     * Update single match display with new data.
     *
     * @param {Object} data - Match data from AJAX response
     */
    function updateSingleMatchDisplay(data) {
        // Update team A score
        updateElementWithAnimation('#teama-score', data.teama.scores);
        updateElementWithAnimation('#teama-overs', data.teama.overs ? '(' + data.teama.overs + ')' : '');

        // Update team B score
        updateElementWithAnimation('#teamb-score', data.teamb.scores);
        updateElementWithAnimation('#teamb-overs', data.teamb.overs ? '(' + data.teamb.overs + ')' : '');

        // Update status note
        if (data.status_note) {
            var $statusNote = $('#status-note');
            if ($statusNote.length) {
                $statusNote.text(data.status_note);
            } else {
                $('.cricket-score-section').append(
                    '<div class="cricket-status-note" id="status-note">' + escapeHtml(data.status_note) + '</div>'
                );
            }
        }

        // Update live batting info
        updateLiveBattingInfo(data);

        // Check if match has ended
        if (data.status !== 3) {
            stopRefresh();
            // Reload page to show final state
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        }
    }

    /**
     * Update live batting information.
     *
     * @param {Object} data - Match data
     */
    function updateLiveBattingInfo(data) {
        var $liveInfo = $('#live-batting-info');
        
        if (!$liveInfo.length || !data.batsmen) {
            return;
        }

        // Update batsmen
        if (data.batsmen && data.batsmen.length) {
            var batsmenHtml = '';
            data.batsmen.forEach(function(batsman) {
                var onStrikeClass = batsman.on_strike ? 'on-strike' : '';
                var strikeIndicator = batsman.on_strike ? '<span class="cricket-strike-indicator">*</span>' : '';
                
                batsmenHtml += '<div class="cricket-batsman ' + onStrikeClass + '">';
                batsmenHtml += '<span class="cricket-batsman-name">' + escapeHtml(batsman.name) + strikeIndicator + '</span>';
                batsmenHtml += '<span class="cricket-batsman-runs">' + batsman.runs + ' (' + batsman.balls + ')</span>';
                batsmenHtml += '</div>';
            });
            
            $liveInfo.find('.cricket-batsmen-list').html(batsmenHtml);
        }

        // Update bowler
        if (data.bowler) {
            var figures = data.bowler.overs + '-' + data.bowler.maidens + '-' + data.bowler.runs + '-' + data.bowler.wickets;
            $liveInfo.find('.cricket-bowler-name').text(data.bowler.name);
            $liveInfo.find('.cricket-bowler-figures').text(figures);
        }

        // Update last balls
        if (data.last_balls && data.last_balls.length) {
            var ballsHtml = '';
            data.last_balls.forEach(function(ball) {
                var ballClass = 'cricket-ball-' + String(ball).toLowerCase().replace(/\s/g, '');
                ballsHtml += '<span class="cricket-ball ' + ballClass + '">' + escapeHtml(String(ball)) + '</span>';
            });
            
            $liveInfo.find('.cricket-balls').html(ballsHtml);
        }
    }

    /**
     * Refresh all widget matches.
     */
    function refreshWidgetMatches() {
        if (isRefreshing) {
            return;
        }

        isRefreshing = true;

        $.ajax({
            url: window.cricketLiveScores.ajaxUrl,
            type: 'GET',
            data: {
                action: 'cricket_get_live_matches',
                nonce: window.cricketLiveScores.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.matches) {
                    updateWidgetMatches(response.data.matches);
                }
            },
            error: function(xhr, status, error) {
                console.error('Cricket widget refresh error:', error);
            },
            complete: function() {
                isRefreshing = false;
            }
        });
    }

    /**
     * Update widget match cards with new data.
     *
     * @param {Array} matches - Array of match data
     */
    function updateWidgetMatches(matches) {
        matches.forEach(function(match) {
            var $card = $('.cricket-widget-card[data-match-id="' + match.match_id + '"]');
            
            if ($card.length === 0) {
                return;
            }

            // Update team A score
            var $teamAScore = $card.find('.cricket-team-score[data-team="a"]');
            var $teamAOvers = $card.find('.cricket-team-overs[data-team="a"]');
            
            if ($teamAScore.length && match.teama.scores !== $teamAScore.text()) {
                $teamAScore.text(match.teama.scores || '-').addClass('updated');
                setTimeout(function() { $teamAScore.removeClass('updated'); }, 800);
            }
            
            if ($teamAOvers.length && match.teama.overs) {
                $teamAOvers.text('(' + match.teama.overs + ')');
            }

            // Update team B score
            var $teamBScore = $card.find('.cricket-team-score[data-team="b"]');
            var $teamBOvers = $card.find('.cricket-team-overs[data-team="b"]');
            
            if ($teamBScore.length && match.teamb.scores !== $teamBScore.text()) {
                $teamBScore.text(match.teamb.scores || '-').addClass('updated');
                setTimeout(function() { $teamBScore.removeClass('updated'); }, 800);
            }
            
            if ($teamBOvers.length && match.teamb.overs) {
                $teamBOvers.text('(' + match.teamb.overs + ')');
            }

            // Update status note
            var $statusNote = $card.find('[data-status-note]');
            if ($statusNote.length && match.status_note) {
                $statusNote.text(match.status_note);
            }
        });
    }

    /**
     * Update element with animation.
     *
     * @param {string} selector - Element selector
     * @param {string} newValue - New value to set
     */
    function updateElementWithAnimation(selector, newValue) {
        var $element = $(selector);
        
        if (!$element.length || !newValue) {
            return;
        }

        var currentValue = $element.text();
        
        if (currentValue !== newValue) {
            $element.addClass('cricket-score-updated');
            $element.text(newValue);
            
            setTimeout(function() {
                $element.removeClass('cricket-score-updated');
            }, 500);
        }
    }

    /**
     * Escape HTML entities.
     *
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Handle page visibility changes.
     */
    function handleVisibilityChange() {
        if (document.hidden) {
            isPageVisible = false;
        } else {
            isPageVisible = true;
        }
    }

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        // Check if auto-refresh is enabled
        if (!window.cricketLiveScores) {
            return;
        }

        // Listen for visibility changes
        document.addEventListener('visibilitychange', handleVisibilityChange);

        // Initialize appropriate refresh based on page context
        initSingleMatchRefresh();
        initWidgetRefresh();
    });

    /**
     * Cleanup on page unload.
     */
    $(window).on('beforeunload', function() {
        stopRefresh();
    });

})(jQuery);
