/**
 * Cricket Live Scores - Widget JavaScript
 *
 * Handles widget interactions including tab switching,
 * scroll navigation, and drag-to-scroll functionality.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Widget refresh interval (10 seconds for widget).
     * @type {number}
     */
    var WIDGET_REFRESH_INTERVAL = 10000;

    /**
     * Scroll amount for arrow navigation.
     * @type {number}
     */
    var SCROLL_AMOUNT = 300;

    /**
     * Widget container.
     * @type {jQuery}
     */
    var $widget = null;

    /**
     * Scroll container.
     * @type {jQuery}
     */
    var $scrollContainer = null;

    /**
     * Navigation arrows.
     * @type {Object}
     */
    var $arrows = {
        left: null,
        right: null
    };

    /**
     * Drag state.
     * @type {Object}
     */
    var dragState = {
        isDragging: false,
        startX: 0,
        scrollLeft: 0
    };

    /**
     * Initialize widget.
     */
    function init() {
        $widget = $('#cricket-widget');
        
        if ($widget.length === 0) {
            // Try alternate selector for shortcode output
            $widget = $('.cricket-widget');
        }
        
        if ($widget.length === 0) {
            return;
        }

        $scrollContainer = $widget.find('.cricket-widget-scroll');
        $arrows.left = $widget.find('.cricket-nav-left');
        $arrows.right = $widget.find('.cricket-nav-right');

        // Initialize features
        initTabs();
        initScrollNavigation();
        initDragToScroll();
        updateArrowStates();

        // Update arrows on scroll
        $scrollContainer.on('scroll', debounce(updateArrowStates, 50));

        // Update arrows on window resize
        $(window).on('resize', debounce(updateArrowStates, 100));
    }

    /**
     * Initialize tab switching.
     */
    function initTabs() {
        var $tabs = $widget.find('.cricket-tab');
        var $cards = $widget.find('.cricket-widget-card');

        $tabs.on('click', function() {
            var $tab = $(this);
            var filter = $tab.data('filter');

            // Update active tab
            $tabs.removeClass('active');
            $tab.addClass('active');

            // Filter cards
            filterCards($cards, filter);

            // Reset scroll position
            $scrollContainer.scrollLeft(0);

            // Update arrows
            updateArrowStates();
        });
    }

    /**
     * Filter match cards based on tab selection.
     *
     * @param {jQuery} $cards - All card elements
     * @param {string} filter - Filter type
     */
    function filterCards($cards, filter) {
        if (filter === 'all') {
            $cards.removeClass('hidden');
            return;
        }

        $cards.each(function() {
            var $card = $(this);
            var cardStatus = $card.data('status');
            var cardSeries = $card.data('series');
            var shouldShow = false;

            if (filter === 'live' && cardStatus === 'live') {
                shouldShow = true;
            } else if (filter === 'upcoming' && cardStatus === 'upcoming') {
                shouldShow = true;
            } else if (filter === 'recent' && cardStatus === 'recent') {
                shouldShow = true;
            } else if (filter.indexOf('series-') === 0) {
                var seriesId = filter.replace('series-', '');
                if (String(cardSeries) === seriesId) {
                    shouldShow = true;
                }
            }

            $card.toggleClass('hidden', !shouldShow);
        });

        // Check for empty state
        var visibleCards = $cards.not('.hidden').length;
        var $emptyState = $widget.find('.cricket-empty-state');
        
        if (visibleCards === 0) {
            if ($emptyState.length === 0) {
                $scrollContainer.append('<div class="cricket-empty-state"><p>No matches found for this filter.</p></div>');
            }
        } else {
            $emptyState.remove();
        }
    }

    /**
     * Initialize scroll navigation arrows.
     */
    function initScrollNavigation() {
        $arrows.left.on('click', function() {
            scrollBy(-SCROLL_AMOUNT);
        });

        $arrows.right.on('click', function() {
            scrollBy(SCROLL_AMOUNT);
        });
    }

    /**
     * Scroll container by amount.
     *
     * @param {number} amount - Scroll amount (positive = right, negative = left)
     */
    function scrollBy(amount) {
        var currentScroll = $scrollContainer.scrollLeft();
        $scrollContainer.animate({
            scrollLeft: currentScroll + amount
        }, 300);
    }

    /**
     * Update arrow visibility based on scroll position.
     */
    function updateArrowStates() {
        if (!$scrollContainer.length) {
            return;
        }

        var scrollLeft = $scrollContainer.scrollLeft();
        var scrollWidth = $scrollContainer[0].scrollWidth;
        var clientWidth = $scrollContainer[0].clientWidth;
        var maxScroll = scrollWidth - clientWidth;

        // Show/hide left arrow
        $arrows.left.prop('disabled', scrollLeft <= 0);

        // Show/hide right arrow
        $arrows.right.prop('disabled', scrollLeft >= maxScroll - 1);
    }

    /**
     * Initialize drag-to-scroll functionality.
     */
    function initDragToScroll() {
        var scrollElement = $scrollContainer[0];

        // Mouse events
        $scrollContainer.on('mousedown', function(e) {
            // Only handle left mouse button
            if (e.button !== 0) return;
            
            dragState.isDragging = true;
            dragState.startX = e.pageX - scrollElement.offsetLeft;
            dragState.scrollLeft = scrollElement.scrollLeft;
            $scrollContainer.addClass('grabbing');
        });

        $(document).on('mousemove', function(e) {
            if (!dragState.isDragging) return;
            
            e.preventDefault();
            var x = e.pageX - scrollElement.offsetLeft;
            var walk = (x - dragState.startX) * 1.5; // Scroll speed multiplier
            scrollElement.scrollLeft = dragState.scrollLeft - walk;
        });

        $(document).on('mouseup', function() {
            if (dragState.isDragging) {
                dragState.isDragging = false;
                $scrollContainer.removeClass('grabbing');
            }
        });

        // Touch events
        var touchStartX = 0;
        var touchScrollLeft = 0;

        $scrollContainer.on('touchstart', function(e) {
            touchStartX = e.touches[0].pageX;
            touchScrollLeft = scrollElement.scrollLeft;
        });

        $scrollContainer.on('touchmove', function(e) {
            if (!touchStartX) return;
            
            var touchX = e.touches[0].pageX;
            var walk = touchStartX - touchX;
            scrollElement.scrollLeft = touchScrollLeft + walk;
        });

        $scrollContainer.on('touchend', function() {
            touchStartX = 0;
        });

        // Prevent default link behavior during drag
        $scrollContainer.on('click', 'a', function(e) {
            if (dragState.isDragging) {
                e.preventDefault();
            }
        });
    }

    /**
     * Debounce function.
     *
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in ms
     * @return {Function} Debounced function
     */
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    /**
     * Scroll to a specific card.
     *
     * @param {number|string} matchId - Match ID to scroll to
     */
    function scrollToMatch(matchId) {
        var $card = $widget.find('.cricket-widget-card[data-match-id="' + matchId + '"]');
        
        if ($card.length === 0 || $card.hasClass('hidden')) {
            return;
        }

        var cardLeft = $card.position().left;
        var containerWidth = $scrollContainer.width();
        var cardWidth = $card.outerWidth(true);
        
        // Center the card in view
        var targetScroll = $scrollContainer.scrollLeft() + cardLeft - (containerWidth / 2) + (cardWidth / 2);
        
        $scrollContainer.animate({
            scrollLeft: targetScroll
        }, 300);
    }

    /**
     * Highlight a match card briefly.
     *
     * @param {number|string} matchId - Match ID to highlight
     */
    function highlightMatch(matchId) {
        var $card = $widget.find('.cricket-widget-card[data-match-id="' + matchId + '"]');
        
        if ($card.length === 0) {
            return;
        }

        $card.addClass('highlighted');
        
        setTimeout(function() {
            $card.removeClass('highlighted');
        }, 2000);
    }

    /**
     * Refresh widget data.
     */
    function refreshWidget() {
        if (!window.cricketLiveScores) {
            return;
        }

        $.ajax({
            url: window.cricketLiveScores.ajaxUrl,
            type: 'GET',
            data: {
                action: 'cricket_get_live_matches',
                nonce: window.cricketLiveScores.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update live badge count
                    var liveCount = response.data.count || 0;
                    var $liveBadge = $widget.find('.cricket-tab[data-filter="live"] .cricket-badge');
                    
                    if ($liveBadge.length) {
                        $liveBadge.text(liveCount);
                    }
                }
            }
        });
    }

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        init();

        // Optional: Start widget refresh if enabled
        if (window.cricketLiveScores) {
            setInterval(refreshWidget, WIDGET_REFRESH_INTERVAL);
        }
    });

    // Expose public methods
    window.CricketWidget = {
        scrollToMatch: scrollToMatch,
        highlightMatch: highlightMatch,
        refresh: refreshWidget
    };

})(jQuery);
