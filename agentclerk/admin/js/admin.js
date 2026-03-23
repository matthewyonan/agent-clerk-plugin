/* AgentClerk Admin JS - shared utilities */
jQuery(function($) {
    'use strict';

    var urlParams = new URLSearchParams(window.location.search);

    // Handle turnkey success return — advance to step 2
    if (urlParams.get('turnkey_success') === '1') {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_onboarding_step',
            nonce: agentclerk.nonce,
            step: 2
        }, function() {
            window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
        });
    }

    // Handle turnkey cancel return — show notice (handled in step-1.php via query param)

    // Handle license activation return — show success notice
    if (urlParams.get('license_success') === '1') {
        $('<div class="notice notice-success is-dismissible"><p>Lifetime license activated. No more transaction fees.</p></div>')
            .insertAfter('.wrap h1:first');
    }

    // On return from Stripe Billing Portal — refresh billing status
    if (urlParams.get('page') === 'agentclerk-sales' && !urlParams.get('license_success')) {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_stats',
            nonce: agentclerk.nonce
        });
    }
});
