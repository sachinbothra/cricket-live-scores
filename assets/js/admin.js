/**
 * Cricket Live Scores Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Sync Matches button
        $('#cricket-sync-btn').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(cricketAdmin.strings.syncing);
            
            $.ajax({
                url: cricketAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cricket_sync_matches',
                    nonce: cricketAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text(cricketAdmin.strings.syncComplete);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(response.data.message || cricketAdmin.strings.syncError);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert(cricketAdmin.strings.syncError);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Clear Cache button
        $('#cricket-clear-cache-btn').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();
            
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
                        $btn.text(cricketAdmin.strings.clearComplete);
                        setTimeout(function() {
                            $btn.prop('disabled', false).text(originalText);
                        }, 2000);
                    } else {
                        alert(response.data.message);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('Error clearing cache');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Show/hide API key
        const $apiKeyInput = $('#cricket_api_key');
        if ($apiKeyInput.length) {
            const $toggleBtn = $('<button type="button" class="button button-secondary" style="margin-left: 10px;">Show</button>');
            $apiKeyInput.after($toggleBtn);
            
            $toggleBtn.on('click', function() {
                if ($apiKeyInput.attr('type') === 'password') {
                    $apiKeyInput.attr('type', 'text');
                    $(this).text('Hide');
                } else {
                    $apiKeyInput.attr('type', 'password');
                    $(this).text('Show');
                }
            });
        }
        
    });
    
})(jQuery);
