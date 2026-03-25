<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$scan_cache   = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );
$config       = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$products     = $scan_cache['products'] ?? [];
$policies     = $scan_cache['policies'] ?? [];
$gaps         = $scan_cache['gaps'] ?? [];
$support_file = $config['support_file'] ?? '';

if ( empty( $support_file ) && ! empty( $scan_cache ) ) {
    $support_file = AgentClerk_Scanner::build_support_file( $scan_cache );
}
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Choose tier</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Scan site</span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">3</div><span>Review</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span>Catalog</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span>Placement</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">6</div><span>Go live</span></div>
    </div>

    <div class="ac-pt">Review and fill any gaps</div>
    <div class="ac-ps">The scan found most of what's needed. The agent will ask about what it couldn't find automatically.</div>

    <div class="ac-g2">
        <div>
            <div class="ac-card">
                <div class="ac-card-head"><h2>What the scan found</h2></div>
                <div class="ac-card-body">
                    <?php if ( ! empty( $products ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo count( $products ); ?> products</strong> &mdash;
                            <?php
                            $names = array_map( function( $p ) { return $p['name'] . ' $' . number_format( (float) $p['price'], 0 ); }, array_slice( $products, 0, 3 ) );
                            echo esc_html( implode( ', ', $names ) );
                            ?>
                        </div></div>
                    <?php endif; ?>
                    <div class="ac-finding">
                        <?php if ( ! empty( $policies['refund'] ) ) : ?>
                            <span class="ac-finding-ok">&#10003;</span><div><strong>Refund policy</strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['refund'], 10 ) ); ?></div>
                        <?php else : ?>
                            <span class="ac-finding-warn">&#9888;</span><div><strong>Refund policy</strong> &mdash; not found</div>
                        <?php endif; ?>
                    </div>
                    <div class="ac-finding">
                        <?php if ( ! empty( $policies['license'] ) ) : ?>
                            <span class="ac-finding-ok">&#10003;</span><div><strong>License terms</strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['license'], 10 ) ); ?></div>
                        <?php else : ?>
                            <span class="ac-finding-warn">&#9888;</span><div><strong>License terms</strong> &mdash; not found</div>
                        <?php endif; ?>
                    </div>
                    <div class="ac-finding">
                        <?php if ( ! empty( $policies['delivery'] ) ) : ?>
                            <span class="ac-finding-ok">&#10003;</span><div><strong>Delivery</strong> &mdash; <?php echo esc_html( wp_trim_words( $policies['delivery'], 10 ) ); ?></div>
                        <?php else : ?>
                            <span class="ac-finding-warn">&#9888;</span><div><strong>Delivery method</strong> &mdash; not found</div>
                        <?php endif; ?>
                    </div>
                    <?php foreach ( $gaps as $gap ) : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( $gap ); ?></strong> &mdash; asking now &rarr;</div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ac-card">
                <div class="ac-card-head"><h2>Auto-drafted support file</h2><span class="ac-b ac-b-a">Draft</span></div>
                <div class="ac-card-body">
                    <div class="ac-co sl" style="margin-bottom:10px"><span class="ac-co-i">&#8505;</span><span>Built from your product pages and site content. Edit directly, or tell the agent what to change in the chat.</span></div>
                    <textarea id="support-file" style="min-height:150px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $support_file ); ?></textarea>
                    <div class="ac-fn">Refine this any time in Settings &rarr; Support &amp; Escalation.</div>
                </div>
            </div>
        </div>

        <div>
            <div class="ac-card" style="display:flex;flex-direction:column;min-height:500px">
                <div class="ac-card-head"><h2>Fill the gaps</h2><?php if ( ! empty( $gaps ) ) : ?><span class="ac-b ac-b-a"><?php echo count( $gaps ); ?> questions</span><?php endif; ?></div>
                <div class="ac-chat-shell" style="border:none;border-radius:0;flex:1">
                    <div class="ac-msgs" id="chat-messages" style="height:360px"></div>
                    <div class="ac-chips-row" id="chat-chips"></div>
                    <div class="ac-chat-inp-row">
                        <input type="text" class="ac-chat-inp" id="chat-input" placeholder="Type or choose above&hellip;">
                        <button class="ac-send-btn" id="chat-send">&#10148;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ac-fr ac-mt">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="step3-continue">Continue to catalog &rarr;</button>
    </div>
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
