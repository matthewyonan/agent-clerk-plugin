<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$scan_cache   = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );
$config       = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$products     = $scan_cache['products'] ?? array();
$policies     = $scan_cache['policies'] ?? array();
$gaps         = $scan_cache['gaps'] ?? array();
$support_file = $config['support_file'] ?? '';

if ( empty( $support_file ) && ! empty( $scan_cache ) && class_exists( 'AgentClerk_Scanner' ) ) {
    $support_file = AgentClerk_Scanner::build_support_file( $scan_cache );
}
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">4</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <h1 class="ac-page-title"><?php echo esc_html( 'Review and fill any gaps' ); ?></h1>
    <p class="ac-page-subtitle"><?php echo esc_html( 'The scan found most of what\'s needed. The agent will ask about what it couldn\'t find automatically.' ); ?></p>

    <div class="ac-grid-2">
        <div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'What the scan found' ); ?></h2></div>
                <div class="ac-card-body">
                    <?php if ( ! empty( $products ) ) :
                        $names = array_map( function( $p ) { return esc_html( $p['name'] ) . ' $' . esc_html( number_format( (float) $p['price'], 0 ) ); }, array_slice( $products, 0, 3 ) );
                    ?>
                        <div class="ac-flex" style="margin-bottom:8px;gap:6px"><span style="color:var(--ac-green);font-weight:600">&#10003;</span><div><strong><?php echo esc_html( count( $products ) . ' products' ); ?></strong> &mdash; <?php echo implode( ', ', $names ); ?></div></div>
                    <?php endif; ?>
                    <div class="ac-flex" style="margin-bottom:8px;gap:6px">
                        <?php if ( ! empty( $policies['refund'] ) ) : ?>
                            <span style="color:var(--ac-green);font-weight:600">&#10003;</span><div><strong><?php echo esc_html( 'Refund policy' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['refund'], 10 ) ); ?></div>
                        <?php else : ?>
                            <span style="color:var(--ac-amber);font-weight:600">&#9888;</span><div><strong><?php echo esc_html( 'Refund policy' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="ac-flex" style="margin-bottom:8px;gap:6px">
                        <?php if ( ! empty( $policies['license'] ) ) : ?>
                            <span style="color:var(--ac-green);font-weight:600">&#10003;</span><div><strong><?php echo esc_html( 'License terms' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['license'], 10 ) ); ?></div>
                        <?php else : ?>
                            <span style="color:var(--ac-amber);font-weight:600">&#9888;</span><div><strong><?php echo esc_html( 'License terms' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="ac-flex" style="margin-bottom:8px;gap:6px">
                        <?php if ( ! empty( $policies['delivery'] ) ) : ?>
                            <span style="color:var(--ac-green);font-weight:600">&#10003;</span><div><strong><?php echo esc_html( 'Delivery' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['delivery'], 10 ) ); ?></div>
                        <?php else : ?>
                            <span style="color:var(--ac-amber);font-weight:600">&#9888;</span><div><strong><?php echo esc_html( 'Delivery method' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php foreach ( $gaps as $gap ) : ?>
                        <div class="ac-flex" style="margin-bottom:8px;gap:6px"><span style="color:var(--ac-amber);font-weight:600">&#9888;</span><div><strong><?php echo esc_html( $gap ); ?></strong> &mdash; <?php echo esc_html( 'asking now' ); ?> &rarr;</div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Auto-drafted support file' ); ?></h2><span class="ac-badge ac-badge-amber"><?php echo esc_html( 'Draft' ); ?></span></div>
                <div class="ac-card-body">
                    <div class="ac-callout ac-callout-slate" style="margin-bottom:10px"><span>&#8505;</span><span><?php echo esc_html( 'Built from your product pages and site content. Edit directly, or tell the agent what to change in the chat.' ); ?></span></div>
                    <textarea id="ac-support-file" style="min-height:150px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $support_file ); ?></textarea>
                    <div class="ac-note"><?php echo esc_html( 'Refine this any time in Settings > Support & Escalation.' ); ?></div>
                </div>
            </div>
        </div>
        <div>
            <div class="ac-card" style="display:flex;flex-direction:column;min-height:500px">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Fill the gaps' ); ?></h2><?php if ( ! empty( $gaps ) ) : ?><span class="ac-badge ac-badge-amber"><?php echo esc_html( count( $gaps ) . ' questions' ); ?></span><?php endif; ?></div>
                <div class="ac-chat-shell" style="border:none;border-radius:0;flex:1">
                    <div class="ac-messages" id="ac-chat-messages" style="height:360px"></div>
                    <div style="padding:6px 12px 2px;display:flex;flex-wrap:wrap;gap:4px" id="ac-chat-chips"></div>
                    <div class="ac-chat-input-row">
                        <input type="text" class="ac-chat-input" id="ac-chat-input" placeholder="<?php echo esc_attr( 'Type or choose above...' ); ?>">
                        <button class="ac-send-btn" id="ac-chat-send">&#10148;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ac-flex ac-mt" style="justify-content:flex-end">
        <button class="ac-btn ac-btn-electric" id="ac-step3-continue"><?php echo esc_html( 'Continue to catalog' ); ?> &rarr;</button>
    </div>
</div>

<script>
jQuery(function($) {
    var chatHistory = [], gaps = <?php echo wp_json_encode( $gaps ); ?>;
    function addMsg(role, text) {
        var cls = role === 'assistant' ? 'ac-msg ac-msg-agent' : 'ac-msg ac-msg-user';
        var av  = role === 'assistant' ? 'AC' : 'You';
        $('#ac-chat-messages').append('<div class="'+cls+'"><div class="ac-msg-avatar">'+av+'</div><div class="ac-msg-bubble">'+text+'</div></div>');
        var el = $('#ac-chat-messages')[0]; if (el) el.scrollTop = el.scrollHeight;
    }
    function setChips(chips) {
        var $r = $('#ac-chat-chips').empty();
        chips.forEach(function(c) { $('<span class="ac-chip">'+$('<span>').text(c).html()+'</span>').on('click', function() { $('#ac-chat-input').val(c); sendMessage(); }).appendTo($r); });
    }
    if (gaps.length > 0) {
        addMsg('assistant', 'The scan went well &mdash; I found your products, pricing, and policies. Just a few things I couldn\'t find automatically.<br><br>First: <strong>how do you want me to notify you when a buyer has a question I can\'t resolve?</strong>');
        setChips(['Both email and WP notification', 'Email only', 'WP admin only']);
    } else { addMsg('assistant', 'Your site looks well-configured! I found everything I need. You can edit the support file on the left, or continue to the catalog.'); }
    function sendMessage() {
        var txt = $.trim($('#ac-chat-input').val()); if (!txt) return;
        addMsg('user', $('<span>').text(txt).html()); chatHistory.push({role:'user',content:txt}); $('#ac-chat-input').val(''); $('#ac-chat-chips').empty();
        $.post(agentclerk.ajaxUrl, {action:'agentclerk_start_scan',nonce:agentclerk.nonce,message:txt,context:'gap_fill',history:JSON.stringify(chatHistory)}, function(r) {
            if (r.success && r.data.message) { addMsg('assistant',r.data.message); chatHistory.push({role:'assistant',content:r.data.message}); if (r.data.chips) setChips(r.data.chips); }
        });
    }
    $('#ac-chat-send').on('click', sendMessage);
    $('#ac-chat-input').on('keydown', function(e) { if (e.key==='Enter') { e.preventDefault(); sendMessage(); } });
    $('#ac-step3-continue').on('click', function() {
        $(this).prop('disabled',true).text('Saving...');
        $.post(agentclerk.ajaxUrl, {action:'agentclerk_save_agent_config',nonce:agentclerk.nonce,support_file:$('#ac-support-file').val()}, function() {
            $.post(agentclerk.ajaxUrl, {action:'agentclerk_save_onboarding_step',nonce:agentclerk.nonce,step:4}, function() { window.location.href=agentclerk.ajaxUrl.replace('admin-ajax.php','admin.php?page=agentclerk-onboarding'); });
        });
    });
});
</script>
