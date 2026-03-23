/* AgentClerk Admin JS - shared utilities */
jQuery(function($) {
    'use strict';

    // Handle license activation return
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('license') === 'activated') {
        $('<div class="notice notice-success is-dismissible"><p><strong>AgentClerk:</strong> Lifetime license activated! Transaction fees are now $0.</p></div>')
            .insertAfter('.wrap h1:first');
    }

    // Handle turnkey success return
    if (urlParams.get('turnkey_success') === '1') {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_onboarding_step',
            nonce: agentclerk.nonce,
            step: 2
        }, function() {
            window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
        });
    }
});
