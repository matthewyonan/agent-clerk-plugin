<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );
$placement  = json_decode( get_option( 'agentclerk_placement', '{}' ), true );

// Calculate readiness scores
$config_fields = [ 'agent_name', 'business_name', 'business_desc', 'escalation_email', 'escalation_message' ];
$filled = 0;
foreach ( $config_fields as $f ) {
    if ( ! empty( $config[ $f ] ) ) $filled++;
}
$business_score = count( $config_fields ) > 0 ? round( ( $filled / count( $config_fields ) ) * 100 ) : 0;

$products   = $scan_cache['products'] ?? [];
$visibility = $config['product_visibility'] ?? [];
$visible    = 0;
foreach ( $products as $p ) {
    if ( ! isset( $visibility[ $p['id'] ] ) || $visibility[ $p['id'] ] ) $visible++;
}
$catalog_score = count( $products ) > 0 ? round( ( $visible / count( $products ) ) * 100 ) : 0;

$policies = $config['policies'] ?? [];
$pol_count = 0;
if ( ! empty( $policies['refund'] ) ) $pol_count++;
if ( ! empty( $policies['license'] ) ) $pol_count++;
if ( ! empty( $policies['delivery'] ) ) $pol_count++;
$policy_score = round( ( $pol_count / 3 ) * 100 );

$support_len   = strlen( $config['support_file'] ?? '' );
$support_score = min( 100, round( ( $support_len / 200 ) * 100 ) );
?>
<div class="wrap agentclerk-onboarding">
    <h1>AgentClerk Setup — Step 6: Test &amp; Go Live</h1>

    <div class="agentclerk-two-col">
        <div class="agentclerk-col-left">
            <div class="agentclerk-card">
                <h2>Readiness Scores</h2>

                <div class="agentclerk-score">
                    <label>Business Context</label>
                    <div class="agentclerk-progress-bar">
                        <div class="agentclerk-progress-fill" style="width:<?php echo $business_score; ?>%;background:<?php echo $business_score >= 75 ? '#4caf50' : '#ff9800'; ?>"></div>
                    </div>
                    <span><?php echo $business_score; ?>%</span>
                </div>

                <div class="agentclerk-score">
                    <label>Catalog</label>
                    <div class="agentclerk-progress-bar">
                        <div class="agentclerk-progress-fill" style="width:<?php echo $catalog_score; ?>%;background:<?php echo $catalog_score >= 75 ? '#4caf50' : '#ff9800'; ?>"></div>
                    </div>
                    <span><?php echo $visible; ?>/<?php echo count( $products ); ?> visible</span>
                </div>

                <div class="agentclerk-score">
                    <label>Policies</label>
                    <div class="agentclerk-progress-bar">
                        <div class="agentclerk-progress-fill" style="width:<?php echo $policy_score; ?>%;background:<?php echo $policy_score >= 75 ? '#4caf50' : '#ff9800'; ?>"></div>
                    </div>
                    <span><?php echo $pol_count; ?>/3</span>
                </div>

                <div class="agentclerk-score">
                    <label>Support File</label>
                    <div class="agentclerk-progress-bar">
                        <div class="agentclerk-progress-fill" style="width:<?php echo $support_score; ?>%;background:<?php echo $support_score >= 75 ? '#4caf50' : '#ff9800'; ?>"></div>
                    </div>
                    <span><?php echo $support_len; ?> chars</span>
                    <?php if ( $support_score < 75 ) : ?>
                        <span style="color:#ff9800;"> &#9888; Consider adding more detail to your support file.</span>
                    <?php endif; ?>
                </div>
            </div>

            <p style="margin-top:20px;">
                <button class="button button-primary button-hero" id="go-live">Go Live</button>
            </p>
        </div>

        <div class="agentclerk-col-right">
            <div class="agentclerk-card">
                <h2>Test Mode Chat</h2>
                <p style="color:#ff9800;font-weight:bold;">TEST MODE — No real transactions</p>
                <div id="test-chat-messages" class="agentclerk-chat-messages"></div>
                <div class="agentclerk-chat-input">
                    <input type="text" id="test-chat-input" placeholder="Test a conversation..." />
                    <button class="button" id="test-chat-send">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var $messages = $('#test-chat-messages');
    var $input = $('#test-chat-input');

    function addMsg(role, text) {
        var cls = role === 'user' ? 'agentclerk-msg-user' : 'agentclerk-msg-assistant';
        $messages.append('<div class="' + cls + '">' + $('<span>').text(text).html() + '</div>');
        $messages.scrollTop($messages[0].scrollHeight);
    }

    addMsg('assistant', 'Hi! I\'m your AI agent in test mode. Try asking me about your products.');

    function sendTest() {
        var text = $input.val().trim();
        if (!text) return;
        $input.val('');
        addMsg('user', text);

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_chat',
            nonce: agentclerk.nonce,
            message: text,
            test_mode: '1'
        }, function(resp) {
            if (resp.success) {
                addMsg('assistant', resp.data.message);
            } else {
                addMsg('assistant', 'Error: ' + (resp.data.message || 'Something went wrong.'));
            }
        });
    }

    $('#test-chat-send').on('click', sendTest);
    $input.on('keypress', function(e) { if (e.which === 13) sendTest(); });

    $('#go-live').on('click', function() {
        if (!confirm('Ready to go live? Your AI agent will start handling real conversations.')) return;
        $(this).prop('disabled', true).text('Going live...');

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_go_live',
            nonce: agentclerk.nonce
        }, function(resp) {
            if (resp.success && resp.data.redirect) {
                window.location.href = resp.data.redirect;
            } else {
                alert('Failed to go live: ' + (resp.data ? resp.data.message : 'Unknown error'));
                $('#go-live').prop('disabled', false).text('Go Live');
            }
        });
    });
});
</script>
