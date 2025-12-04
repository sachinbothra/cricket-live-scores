/**
 * Cricket Live Scores - Admin JavaScript
 *
 * Handles admin panel interactions including API testing,
 * cache clearing, syncing, and log viewing.
 *
 * @package Cricket_Live_Scores
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Cache DOM elements
    var $testApiBtn = $('#cricket-test-api');
    var $apiStatus = $('#cricket-api-status');
    var $clearCacheBtn = $('#cricket-clear-cache');
    var $syncAllBtn = $('#cricket-sync-all');
    var $viewLogsBtn = $('#cricket-view-logs');
    var $clearLogsBtn = $('#cricket-clear-logs');
    var $logsContainer = $('#cricket-logs-container');
    var $logsContent = $('#cricket-logs-content');

    /**
     * Test API connection.
     */
    $testApiBtn.on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $apiStatus.text(cricketAdmin.strings.testing).removeClass('success error').addClass('loading');

        $.ajax({
            url: cricketAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cricket_test_api',
                nonce: cricketAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $apiStatus.text(cricketAdmin.strings.success).removeClass('loading error').addClass('success');
                } else {
                    $apiStatus.text(response.data.message || cricketAdmin.strings.error).removeClass('loading success').addClass('error');
                }
            },
            error: function() {
                $apiStatus.text(cricketAdmin.strings.error).removeClass('loading success').addClass('error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    /**
     * Clear cache.
     */
    $clearCacheBtn.on('click', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(cricketAdmin.strings.clearing);

        $.ajax({
            url: cricketAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cricket_clear_cache',
                nonce: cricketAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data.message || cricketAdmin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cricketAdmin.strings.error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    /**
     * Sync all data.
     */
    $syncAllBtn.on('click', function() {
        triggerSync('all', $(this));
    });

    /**
     * Individual sync buttons.
     */
    $('.cricket-sync-btn').on('click', function() {
        var type = $(this).data('type');
        triggerSync(type, $(this));
    });

    /**
     * Trigger sync operation.
     *
     * @param {string} type - Sync type
     * @param {jQuery} $btn - Button element
     */
    function triggerSync(type, $btn) {
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(cricketAdmin.strings.syncing);

        $.ajax({
            url: cricketAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cricket_manual_sync',
                type: type,
                nonce: cricketAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    // Reload page to show updated stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotice(response.data.message || cricketAdmin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cricketAdmin.strings.error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * View logs.
     */
    $viewLogsBtn.on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: cricketAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cricket_get_logs',
                nonce: cricketAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $logsContent.val(response.data.logs);
                    $logsContainer.slideDown();
                } else {
                    showNotice(response.data.message || cricketAdmin.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(cricketAdmin.strings.error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    /**
     * Clear logs view.
     */
    $clearLogsBtn.on('click', function() {
        $logsContent.val('');
        $logsContainer.slideUp();
    });

    /**
     * Copy shortcode to clipboard.
     */
    $('.cricket-shortcode-code').on('click', function() {
        var $code = $(this);
        var shortcode = $code.data('shortcode') || $code.text();
        
        // Create temporary input
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        
        try {
            document.execCommand('copy');
            $code.addClass('copied');
            
            // Show feedback
            var originalText = $code.text();
            $code.text(cricketAdmin.strings.copied);
            
            setTimeout(function() {
                $code.text(originalText).removeClass('copied');
            }, 1500);
        } catch (err) {
            console.error('Failed to copy shortcode:', err);
        }
        
        $temp.remove();
    });

    /**
     * Show admin notice.
     *
     * @param {string} message - Notice message
     * @param {string} type - Notice type (success/error)
     */
    function showNotice(message, type) {
        var $notice = $('<div class="cricket-admin-notice ' + type + '">' + message + '</div>');
        
        $('.cricket-admin-wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Refresh statistics.
     */
    function refreshStats() {
        // Could implement periodic stats refresh if needed
    }

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        // Auto-scroll logs to bottom
        if ($logsContent.length && $logsContent.val()) {
            $logsContent.scrollTop($logsContent[0].scrollHeight);
        }
    });

})(jQuery);
