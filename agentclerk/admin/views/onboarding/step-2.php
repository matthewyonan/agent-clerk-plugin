<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap agentclerk-onboarding">
    <h1>AgentClerk Setup — Step 2: Site Scan</h1>

    <div class="agentclerk-card" id="scan-intro">
        <h2>Scanning Your Site</h2>
        <p>AgentClerk will scan your website to understand your products, policies, and content. This helps us build an accurate AI agent for your store.</p>
        <p>We'll read your sitemap, extract product information from WooCommerce, and identify any gaps in your store's information.</p>
        <button class="button button-primary" id="start-scan">Start Scan</button>
    </div>

    <div id="scan-progress" style="display:none;" class="agentclerk-card">
        <h2>Scanning...</h2>
        <p id="scan-status-text">Preparing scan...</p>
        <div class="agentclerk-progress-bar">
            <div class="agentclerk-progress-fill" id="scan-progress-bar" style="width:0%"></div>
        </div>
        <p id="scan-estimate"></p>
    </div>

    <div id="scan-complete" style="display:none;" class="agentclerk-card">
        <h2>Scan Complete!</h2>
        <p>Your site has been analyzed. Proceeding to review...</p>
    </div>
</div>

<script>
jQuery(function($) {
    var pollTimer;

    $('#start-scan').on('click', function() {
        $(this).prop('disabled', true);
        $('#scan-intro').hide();
        $('#scan-progress').show();

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_start_scan',
            nonce: agentclerk.nonce
        }, function(resp) {
            clearInterval(pollTimer);
            if (resp.success) {
                $('#scan-progress').hide();
                $('#scan-complete').show();
                setTimeout(function() {
                    window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
                }, 1500);
            } else {
                $('#scan-status-text').text('Scan failed: ' + (resp.data.message || 'Unknown error'));
            }
        });

        pollTimer = setInterval(function() {
            $.post(agentclerk.ajaxUrl, {
                action: 'agentclerk_scan_progress',
                nonce: agentclerk.nonce
            }, function(resp) {
                if (resp.success && resp.data) {
                    var d = resp.data;
                    if (d.total > 0) {
                        var pct = Math.round((d.completed / d.total) * 100);
                        $('#scan-progress-bar').css('width', pct + '%');
                        $('#scan-status-text').text('Scanned ' + d.completed + ' of ' + d.total + ' pages');
                        var est = Math.ceil((d.total - d.completed) * 2 / 60);
                        $('#scan-estimate').text(est > 0 ? 'About ' + est + ' minute(s) remaining' : '');
                    }
                    if (d.status === 'complete') {
                        clearInterval(pollTimer);
                    }
                }
            });
        }, 2000);
    });
});
</script>
