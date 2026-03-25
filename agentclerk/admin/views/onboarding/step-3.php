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
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span><?php echo esc_html( 'Placement' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-pt"><?php echo esc_html( 'Review and fill any gaps' ); ?></div>
    <div class="ac-ps"><?php echo esc_html( "The scan found most of what's needed. The agent will ask about what it couldn't find automatically." ); ?></div>

    <div class="ac-g2">
        <div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'What the scan found' ); ?></h2></div>
                <div class="ac-card-body" style="padding:14px 17px">
                    <?php if ( ! empty( $products ) ) :
                        $names = array_map( function( $p ) { return esc_html( $p['name'] ) . ' $' . esc_html( number_format( (float) $p['price'], 0 ) ); }, array_slice( $products, 0, 3 ) );
                    ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( count( $products ) . ' products' ); ?></strong> &mdash; <?php echo implode( ', ', $names ); ?></div></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $policies['refund'] ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( 'Refund policy' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['refund'], 10 ) ); ?></div></div>
                    <?php else : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( 'Refund policy' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $policies['license'] ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( 'License terms' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['license'], 10 ) ); ?></div></div>
                    <?php else : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( 'License terms' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $policies['delivery'] ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( 'Delivery' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['delivery'], 10 ) ); ?></div></div>
                    <?php else : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( 'Delivery method' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div></div>
                    <?php endif; ?>
                    <?php foreach ( $gaps as $gap ) : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( $gap ); ?></strong> &mdash; <?php echo esc_html( 'asking now' ); ?> &rarr;</div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Auto-drafted support file' ); ?></h2><span class="ac-b ac-b-a"><?php echo esc_html( 'Draft' ); ?></span></div>
                <div class="ac-card-body">
                    <div class="ac-co sl" style="margin-bottom:10px"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'Built from your product pages and blog content. Edit directly, or tell the agent what to change in the chat.' ); ?></span></div>
                    <textarea id="ac-support-file" style="min-height:130px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $support_file ); ?></textarea>
                    <div class="ac-fn"><?php echo esc_html( 'Refine this any time in Settings → Support & Escalation.' ); ?></div>
                </div>
            </div>
        </div>
        <div>
            <div class="ac-card" style="display:flex;flex-direction:column;min-height:500px">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Fill the gaps' ); ?></h2><?php if ( ! empty( $gaps ) ) : ?><span class="ac-b ac-b-a"><?php echo esc_html( count( $gaps ) . ' questions' ); ?></span><?php endif; ?></div>
                <div class="ac-chat-shell" style="border:none;border-radius:0;flex:1">
                    <div class="ac-msgs" id="ac-chat-messages" style="height:360px"></div>
                    <div class="ac-chips-row" id="ac-chat-chips"></div>
                    <div class="ac-chat-inp-row">
                        <textarea class="ac-chat-inp" id="ac-chat-input" rows="1" placeholder="<?php echo esc_attr( 'Type or choose above…' ); ?>"></textarea>
                        <button class="ac-send-btn" id="ac-chat-send">&#10148;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ac-fr ac-mt">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-step3-continue"><?php echo esc_html( 'Continue to catalog' ); ?> &rarr;</button>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>

<script>
jQuery(function($) {
    var chatHistory = [];
    var gaps = <?php echo wp_json_encode( $gaps ); ?>;

    function addMsg(role, text) {
        var cls = role === 'assistant' ? 'ag' : 'us';
        var av = role === 'assistant' ? 'AC' : 'You';
        $('#chat-messages').append('<div class="ac-msg ' + cls + '"><div class="ac-mav">' + av + '</div><div class="ac-mbub">' + text + '</div></div>');
        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
    }

    function setChips(chips) {
        var row = $('#chat-chips').empty();
        chips.forEach(function(c) {
            $('<span class="ac-chip">' + c + '</span>').on('click', function() {
                $('#chat-input').val(c);
                sendMessage();
            }).appendTo(row);
        });
    }

    if (gaps.length > 0) {
        addMsg('assistant', 'The scan went well &mdash; I found your products, pricing, and policies. Just a few things I couldn\'t find automatically.<br><br>First: <strong>how do you want me to notify you when a buyer has a question I can\'t resolve?</strong>');
        setChips(['Both email and WP notification', 'Email only', 'WP admin only']);
    } else {
        addMsg('assistant', 'Your site looks well-configured! I found everything I need. You can edit the support file on the left, or continue to the catalog.');
    }

    function sendMessage() {
        var txt = $.trim($('#chat-input').val());
        if (!txt) return;
        addMsg('user', txt);
        var historyToSend = JSON.stringify(chatHistory);
        chatHistory.push({ role: 'user', content: txt });
        $('#chat-input').val('');
        $('#chat-chips').empty();

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_onboarding_chat',
            nonce: agentclerk.nonce,
            message: txt,
            context: 'gap_fill',
            history: historyToSend
        }, function(resp) {
            if (resp.success && resp.data.message) {
                addMsg('assistant', resp.data.message);
                chatHistory.push({ role: 'assistant', content: resp.data.message });
                if (resp.data.chips) setChips(resp.data.chips);
            } else {
                addMsg('assistant', 'Something went wrong — please try again.');
            }
        }).fail(function() {
            addMsg('assistant', 'Connection error — please try again.');
        });
    }

    $('#chat-send').on('click', sendMessage);
    $('#chat-input').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); sendMessage(); } });

    $('#step3-continue').on('click', function() {
        $(this).prop('disabled', true).text('Saving...');
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_agent_config',
            nonce: agentclerk.nonce,
            support_file: $('#support-file').val()
        }, function() {
            $.post(agentclerk.ajaxUrl, {
                action: 'agentclerk_save_onboarding_step',
                nonce: agentclerk.nonce,
                step: 4
            }, function() {
                window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
            });
        });
    });
});
</script>
