<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

$config_fields = array( 'agent_name', 'business_name', 'business_desc', 'escalation_email', 'escalation_message' );
$filled = 0;
foreach ( $config_fields as $f ) {
    if ( ! empty( $config[ $f ] ) ) { $filled++; }
}
$business_score = count( $config_fields ) > 0 ? (int) round( ( $filled / count( $config_fields ) ) * 100 ) : 0;

$products   = $scan_cache['products'] ?? array();
$visibility = $config['product_visibility'] ?? array();
$visible    = 0;
foreach ( $products as $p ) {
    if ( ! isset( $visibility[ $p['id'] ] ) || $visibility[ $p['id'] ] ) { $visible++; }
}
$catalog_score = count( $products ) > 0 ? (int) round( ( $visible / count( $products ) ) * 100 ) : 0;

$policies  = $config['policies'] ?? array();
$pol_count = 0;
if ( ! empty( $policies['refund'] ) ) { $pol_count++; }
if ( ! empty( $policies['license'] ) ) { $pol_count++; }
if ( ! empty( $policies['delivery'] ) ) { $pol_count++; }
$policy_score = (int) round( ( $pol_count / 3 ) * 100 );

$support_len   = strlen( $config['support_file'] ?? '' );
$support_score = min( 100, (int) round( ( $support_len / 200 ) * 100 ) );

if ( ! function_exists( 'ac_readiness_color' ) ) {
    function ac_readiness_color( $score ) {
        return $score >= 75 ? 'var(--ac-green)' : 'var(--ac-amber)';
    }
}
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Placement' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">6</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <h1 class="ac-page-title"><?php echo esc_html( 'Test your agent before going live' ); ?></h1>
    <p class="ac-page-subtitle"><?php echo esc_html( 'Try it as a buyer would. No transaction is processed in test mode.' ); ?></p>

    <div class="ac-grid-2">
        <div>
            <div class="ac-chat-shell" style="height:420px">
                <div class="ac-chat-header">
                    <div class="ac-chat-avatar">&#9889;</div>
                    <div>
                        <div class="ac-chat-name"><?php echo esc_html( 'AgentClerk' ); ?> <span style="font-size:10px;background:rgba(245,158,11,0.2);color:var(--ac-amber);padding:1px 6px;border-radius:3px;font-family:'DM Mono',monospace"><?php echo esc_html( 'TEST' ); ?></span></div>
                        <div class="ac-chat-status">&#9679; <?php echo esc_html( 'Ready' ); ?></div>
                    </div>
                </div>
                <div class="ac-messages" id="ac-test-msgs" style="height:290px;max-height:290px"></div>
                <div class="ac-chat-input-row">
                    <input type="text" class="ac-chat-input" id="ac-test-input" placeholder="<?php echo esc_attr( 'Ask as a buyer would...' ); ?>">
                    <button class="ac-send-btn" id="ac-test-send">&#10148;</button>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:7px">
                <span class="ac-chip" data-q="<?php echo esc_attr( 'What products do you have?' ); ?>"><?php echo esc_html( 'What products do you have?' ); ?></span>
                <span class="ac-chip" data-q="<?php echo esc_attr( 'What\'s your refund policy?' ); ?>"><?php echo esc_html( 'What\'s your refund policy?' ); ?></span>
                <span class="ac-chip" data-q="<?php echo esc_attr( 'I need help choosing' ); ?>"><?php echo esc_html( 'I need help choosing' ); ?></span>
            </div>
        </div>

        <div>
            <div class="ac-card ac-mb">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Readiness' ); ?></h2></div>
                <div class="ac-card-body">
                    <div class="ac-score-row"><div class="ac-score-label"><?php echo esc_html( 'Business context' ); ?></div><div class="ac-score-track"><div class="ac-score-fill" style="width:<?php echo (int) $business_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $business_score ) ); ?>"></div></div><div class="ac-score-value" style="color:<?php echo esc_attr( ac_readiness_color( $business_score ) ); ?>"><?php echo (int) $business_score; ?>%</div></div>
                    <div class="ac-score-row"><div class="ac-score-label"><?php echo esc_html( 'Catalog' ); ?></div><div class="ac-score-track"><div class="ac-score-fill" style="width:<?php echo (int) $catalog_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $catalog_score ) ); ?>"></div></div><div class="ac-score-value" style="color:<?php echo esc_attr( ac_readiness_color( $catalog_score ) ); ?>"><?php echo (int) $catalog_score; ?>%</div></div>
                    <div class="ac-score-row"><div class="ac-score-label"><?php echo esc_html( 'Policies' ); ?></div><div class="ac-score-track"><div class="ac-score-fill" style="width:<?php echo (int) $policy_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $policy_score ) ); ?>"></div></div><div class="ac-score-value" style="color:<?php echo esc_attr( ac_readiness_color( $policy_score ) ); ?>"><?php echo (int) $policy_score; ?>%</div></div>
                    <div class="ac-score-row"><div class="ac-score-label"><?php echo esc_html( 'Support file' ); ?></div><div class="ac-score-track"><div class="ac-score-fill" style="width:<?php echo (int) $support_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $support_score ) ); ?>"></div></div><div class="ac-score-value" style="color:<?php echo esc_attr( ac_readiness_color( $support_score ) ); ?>"><?php echo (int) $support_score; ?>%</div></div>
                    <?php if ( $support_score < 75 ) : ?>
                        <hr style="border:none;border-top:1px solid var(--ac-border);margin:12px 0">
                        <div class="ac-callout ac-callout-amber" style="margin-bottom:0"><span>&#9888;</span><span><?php echo esc_html( 'Support file is a draft. Improve it any time in Settings > Support & Escalation.' ); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ac-card">
                <div class="ac-card-body" style="text-align:center;padding:20px 17px">
                    <div style="font-size:26px;margin-bottom:7px">&#128640;</div>
                    <div style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:5px"><?php echo esc_html( 'Ready to go live' ); ?></div>
                    <div style="font-size:12px;color:var(--ac-text3);margin-bottom:13px"><?php echo esc_html( 'Agent activates across all placements immediately.' ); ?></div>
                    <button class="ac-btn ac-btn-electric" id="ac-go-live" style="width:100%;justify-content:center;padding:12px"><?php echo esc_html( 'Go live' ); ?> &rarr;</button>
                    <div style="font-size:11px;color:var(--ac-text3);margin-top:8px"><?php echo esc_html( 'Adjust everything in Settings at any time' ); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    function addMsg(role, text) {
        var cls = role==='assistant' ? 'ac-msg ac-msg-agent' : 'ac-msg ac-msg-user';
        var av = role==='assistant' ? 'AC' : 'You';
        $('#ac-test-msgs').append('<div class="'+cls+'"><div class="ac-msg-avatar">'+av+'</div><div class="ac-msg-bubble">'+text+'</div></div>');
        var el=$('#ac-test-msgs')[0]; if(el) el.scrollTop=el.scrollHeight;
    }
    addMsg('assistant','Hi! I can help you find the right product. What are you looking for?');
    function sendTest() {
        var txt=$.trim($('#ac-test-input').val()); if(!txt) return;
        addMsg('user',$('<span>').text(txt).html()); $('#ac-test-input').val('');
        $.post(agentclerk.ajaxUrl,{action:'agentclerk_start_scan',nonce:agentclerk.nonce,message:txt,test_mode:'1'},function(r){
            if(r.success) addMsg('assistant',r.data.message);
            else addMsg('assistant','Error: '+(r.data?r.data.message:'Something went wrong.'));
        });
    }
    $('#ac-test-send').on('click',sendTest);
    $('#ac-test-input').on('keydown',function(e){if(e.key==='Enter'){e.preventDefault();sendTest();}});
    $('.ac-chip').on('click',function(){$('#ac-test-input').val($(this).data('q'));sendTest();});
    $('#ac-go-live').on('click',function(){
        if(!confirm('Ready to go live? Your AI agent will start handling real conversations.')) return;
        $(this).prop('disabled',true).text('Going live...');
        $.post(agentclerk.ajaxUrl,{action:'agentclerk_go_live',nonce:agentclerk.nonce},function(r){
            if(r.success&&r.data.redirect) window.location.href=r.data.redirect;
            else{alert('Failed: '+(r.data?r.data.message:'Unknown error'));$('#ac-go-live').prop('disabled',false).html('Go live &rarr;');}
        });
    });
});
</script>
