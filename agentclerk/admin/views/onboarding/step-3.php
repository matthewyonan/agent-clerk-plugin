<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );
$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$products   = $scan_cache['products'] ?? [];
$policies   = $scan_cache['policies'] ?? [];
$gaps       = $scan_cache['gaps'] ?? [];
$support_file = $config['support_file'] ?? '';

if ( empty( $support_file ) && ! empty( $scan_cache ) ) {
    $support_file = AgentClerk_Scanner::build_support_file( $scan_cache );
}
?>
<div class="wrap agentclerk-onboarding">
    <h1>AgentClerk Setup — Step 3: Review &amp; Gap Fill</h1>

    <div class="agentclerk-two-col">
        <div class="agentclerk-col-left">
            <div class="agentclerk-card">
                <h2>Scan Results</h2>

                <?php if ( ! empty( $products ) ) : ?>
                    <h3>Products Found</h3>
                    <ul>
                        <?php foreach ( $products as $p ) : ?>
                            <li><strong><?php echo esc_html( $p['name'] ); ?></strong> — $<?php echo esc_html( $p['price'] ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3>Policies</h3>
                <ul>
                    <li>Refund: <?php echo ! empty( $policies['refund'] ) ? '<span style="color:green;">Found</span>' : '<span style="color:#e67e22;">Not found</span>'; ?></li>
                    <li>License: <?php echo ! empty( $policies['license'] ) ? '<span style="color:green;">Found</span>' : '<span style="color:#e67e22;">Not found</span>'; ?></li>
                    <li>Delivery: <?php echo ! empty( $policies['delivery'] ) ? '<span style="color:green;">Found</span>' : '<span style="color:#e67e22;">Not found</span>'; ?></li>
                </ul>

                <?php if ( ! empty( $gaps ) ) : ?>
                    <h3>Gaps Identified</h3>
                    <ul>
                        <?php foreach ( $gaps as $gap ) : ?>
                            <li style="color:#e67e22;">&#9888; <?php echo esc_html( $gap ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="agentclerk-card">
                <h3>Support Knowledge File</h3>
                <p>This is what your AI agent will know. Edit as needed — the chat assistant can also update it.</p>
                <textarea id="support-file" rows="15" class="large-text"><?php echo esc_textarea( $support_file ); ?></textarea>
            </div>
        </div>

        <div class="agentclerk-col-right">
            <div class="agentclerk-card">
                <h2>Setup Chat</h2>
                <p>I'll help you fill in the gaps. Let's get started!</p>
                <div id="onboarding-chat-messages" class="agentclerk-chat-messages"></div>
                <div class="agentclerk-chat-input">
                    <input type="text" id="onboarding-chat-input" placeholder="Type your response..." />
                    <button class="button button-primary" id="onboarding-chat-send">Send</button>
                </div>
            </div>
        </div>
    </div>

    <p style="margin-top:20px;">
        <button class="button button-primary button-hero" id="step3-continue">Continue to Catalog</button>
    </p>
</div>

<script>
jQuery(function($) {
    var chatHistory = [];
    var $messages = $('#onboarding-chat-messages');
    var $input = $('#onboarding-chat-input');

    function addMessage(role, text) {
        var cls = role === 'user' ? 'agentclerk-msg-user' : 'agentclerk-msg-assistant';
        $messages.append('<div class="' + cls + '">' + $('<span>').text(text).html() + '</div>');
        $messages.scrollTop($messages[0].scrollHeight);
    }

    // Auto-start with gap fill opening
    var gaps = <?php echo wp_json_encode( $gaps ); ?>;
    if (gaps.length > 0) {
        var opening = "I found some gaps in your store information: " + gaps.join(', ') + ". Let me ask you about each one. First — how would you like escalations to be handled? (email notification, etc.)";
        addMessage('assistant', opening);
    } else {
        addMessage('assistant', "Your site looks well-configured! Let me ask a couple of quick questions. How should customer escalations be handled?");
    }

    $('#onboarding-chat-send').on('click', sendMessage);
    $input.on('keypress', function(e) { if (e.which === 13) sendMessage(); });

    function sendMessage() {
        var text = $input.val().trim();
        if (!text) return;
        $input.val('');
        addMessage('user', text);
        chatHistory.push({ role: 'user', content: text });

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_onboarding_chat',
            nonce: agentclerk.nonce,
            message: text,
            context: 'gap_fill',
            history: JSON.stringify(chatHistory)
        }, function(resp) {
            if (resp.success) {
                addMessage('assistant', resp.data.message);
                chatHistory.push({ role: 'assistant', content: resp.data.message });

                // Check if response contains support file updates
                if (resp.data.message.toLowerCase().indexOf('updated') !== -1 ||
                    resp.data.message.toLowerCase().indexOf('support file') !== -1) {
                    // Agent may have suggested updates — user can manually update
                }
            } else {
                addMessage('assistant', 'Error: ' + (resp.data.message || 'Something went wrong.'));
            }
        });
    }

    $('#step3-continue').on('click', function() {
        var supportFile = $('#support-file').val();
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_agent_config',
            nonce: agentclerk.nonce,
            support_file: supportFile
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
