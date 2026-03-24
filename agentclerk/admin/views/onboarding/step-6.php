<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

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

function ac_score_color( $score ) {
    return $score >= 75 ? 'var(--ac-green)' : 'var(--ac-amber)';
}
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Choose tier</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Scan site</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Review</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Catalog</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Placement</span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">6</div><span>Go live</span></div>
    </div>

    <div class="ac-pt">Test your agent before going live</div>
    <div class="ac-ps">Try it as a buyer would. No transaction is processed in test mode.</div>

    <div class="ac-g2">
        <div>
            <div class="ac-chat-shell" style="height:400px">
                <div class="ac-chat-hd">
                    <div class="ac-chat-av">&#9889;</div>
                    <div>
                        <div class="ac-chat-nm">AgentClerk <span style="font-size:10px;background:rgba(245,158,11,0.2);color:var(--ac-amber);padding:1px 6px;border-radius:3px;font-family:'DM Mono',monospace">TEST</span></div>
                        <div class="ac-chat-st">&#9679; Ready</div>
                    </div>
                </div>
                <div class="ac-msgs" id="test-msgs" style="height:280px;max-height:280px"></div>
                <div class="ac-chat-inp-row">
                    <input type="text" class="ac-chat-inp" id="test-input" placeholder="Ask as a buyer would&hellip;">
                    <button class="ac-send-btn" id="test-send">&#10148;</button>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:7px">
                <span class="ac-chip" data-q="What products do you have?">What products do you have?</span>
                <span class="ac-chip" data-q="What's your refund policy?">What's your refund policy?</span>
                <span class="ac-chip" data-q="I need help choosing">I need help choosing</span>
            </div>
        </div>

        <div>
            <div class="ac-card ac-mb">
                <div class="ac-card-head"><h2>Readiness</h2></div>
                <div class="ac-card-body">
                    <div class="ac-sc-row">
                        <div class="ac-sc-lbl">Business context</div>
                        <div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo $business_score; ?>%;background:<?php echo ac_score_color( $business_score ); ?>"></div></div>
                        <div class="ac-sc-val" style="color:<?php echo ac_score_color( $business_score ); ?>"><?php echo $business_score; ?>%</div>
                    </div>
                    <div class="ac-sc-row">
                        <div class="ac-sc-lbl">Catalog</div>
                        <div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo $catalog_score; ?>%;background:<?php echo ac_score_color( $catalog_score ); ?>"></div></div>
                        <div class="ac-sc-val" style="color:<?php echo ac_score_color( $catalog_score ); ?>"><?php echo $catalog_score; ?>%</div>
                    </div>
                    <div class="ac-sc-row">
                        <div class="ac-sc-lbl">Policies</div>
                        <div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo $policy_score; ?>%;background:<?php echo ac_score_color( $policy_score ); ?>"></div></div>
                        <div class="ac-sc-val" style="color:<?php echo ac_score_color( $policy_score ); ?>"><?php echo $policy_score; ?>%</div>
                    </div>
                    <div class="ac-sc-row">
                        <div class="ac-sc-lbl">Support file</div>
                        <div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo $support_score; ?>%;background:<?php echo ac_score_color( $support_score ); ?>"></div></div>
                        <div class="ac-sc-val" style="color:<?php echo ac_score_color( $support_score ); ?>"><?php echo $support_score; ?>%</div>
                    </div>
                    <?php if ( $support_score < 75 ) : ?>
                        <hr>
                        <div class="ac-co am" style="margin-bottom:0"><span class="ac-co-i">&#9888;</span><span>Support file is a draft. Improve it any time in Settings &rarr; Support &amp; Escalation.</span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ac-card">
                <div class="ac-card-body" style="text-align:center;padding:20px 17px">
                    <div style="font-size:26px;margin-bottom:7px">&#128640;</div>
                    <div style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:5px">Ready to go live</div>
                    <div style="font-size:12px;color:var(--ac-text3);margin-bottom:13px">Agent activates across all placements immediately.</div>
                    <button class="ac-btn ac-btn-e ac-btn-lg" id="go-live" style="width:100%">Go live &rarr;</button>
                    <div style="font-size:11px;color:var(--ac-text3);margin-top:8px">Adjust everything in Settings at any time</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    function addMsg(role, text) {
        var cls = role === 'assistant' ? 'ag' : 'us';
        var av = role === 'assistant' ? 'AC' : 'You';
        $('#test-msgs').append('<div class="ac-msg ' + cls + '"><div class="ac-mav">' + av + '</div><div class="ac-mbub">' + text + '</div></div>');
        $('#test-msgs').scrollTop($('#test-msgs')[0].scrollHeight);
    }

    addMsg('assistant', 'Hi! I can help you find the right product. What are you looking for?');

    function sendTest() {
        var txt = $.trim($('#test-input').val());
        if (!txt) return;
        addMsg('user', txt);
        $('#test-input').val('');

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_chat',
            nonce: agentclerk.nonce,
            message: txt,
            test_mode: '1'
        }, function(resp) {
            if (resp.success) addMsg('assistant', resp.data.message);
            else addMsg('assistant', 'Error: ' + (resp.data ? resp.data.message : 'Something went wrong.'));
        });
    }

    $('#test-send').on('click', sendTest);
    $('#test-input').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); sendTest(); } });

    $('.ac-chip').on('click', function() {
        $('#test-input').val($(this).data('q'));
        sendTest();
    });

    $('#go-live').on('click', function() {
        if (!confirm('Ready to go live? Your AI agent will start handling real conversations.')) return;
        $(this).prop('disabled', true).text('Going live...');
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_go_live',
            nonce: agentclerk.nonce
        }, function(resp) {
            if (resp.success && resp.data.redirect) window.location.href = resp.data.redirect;
            else { alert('Failed: ' + (resp.data ? resp.data.message : 'Unknown error')); $('#go-live').prop('disabled', false).text('Go live →'); }
        });
    });
});
</script>
