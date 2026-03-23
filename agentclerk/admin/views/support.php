<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap agentclerk-support">
    <h1>Support</h1>

    <div class="agentclerk-two-col">
        <div class="agentclerk-col-left">
            <h2>Escalated Conversations</h2>
            <div id="escalation-list"></div>
        </div>

        <div class="agentclerk-col-right">
            <div class="agentclerk-card">
                <h2>AgentClerk Plugin Help</h2>
                <p>Need help with the plugin? Chat with our support assistant.</p>
                <div id="support-chat-messages" class="agentclerk-chat-messages"></div>
                <div class="agentclerk-chat-input">
                    <input type="text" id="support-chat-input" placeholder="Ask about AgentClerk..." />
                    <button class="button button-primary" id="support-chat-send">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    // Load escalations
    function loadEscalations() {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_escalations',
            nonce: agentclerk.nonce
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.escalations, function(i, e) {
                var readCls = e.read ? 'agentclerk-escalation-read' : 'agentclerk-escalation-unread';
                html += '<div class="agentclerk-card ' + readCls + '" data-id="' + e.id + '">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                html += '<strong>' + (e.email || 'No email') + '</strong>';
                html += '<button class="button toggle-read" data-id="' + e.id + '">' + (e.read ? 'Mark Unread' : 'Mark Read') + '</button>';
                html += '</div>';
                html += '<p>' + $('<span>').text(e.first_message || '(no message)').html() + '</p>';
                html += '<small>' + e.created_at + '</small>';
                html += ' | <a href="#" class="view-transcript" data-id="' + e.id + '">View Transcript</a>';
                html += '</div>';
            });
            $('#escalation-list').html(html || '<p>No escalated conversations.</p>');
        });
    }

    loadEscalations();

    $(document).on('click', '.toggle-read', function() {
        var id = $(this).data('id');
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_toggle_read',
            nonce: agentclerk.nonce,
            conversation_id: id
        }, function() { loadEscalations(); });
    });

    // Plugin support chat
    var supportHistory = [];
    var $msgs = $('#support-chat-messages');
    var $input = $('#support-chat-input');

    function addSupportMsg(role, text) {
        var cls = role === 'user' ? 'agentclerk-msg-user' : 'agentclerk-msg-assistant';
        $msgs.append('<div class="' + cls + '">' + $('<span>').text(text).html() + '</div>');
        $msgs.scrollTop($msgs[0].scrollHeight);
    }

    addSupportMsg('assistant', 'Hi! I can help with AgentClerk plugin questions. What do you need help with?');

    function sendSupportMsg() {
        var text = $input.val().trim();
        if (!text) return;
        $input.val('');
        addSupportMsg('user', text);
        supportHistory.push({ role: 'user', content: text });

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_support_chat',
            nonce: agentclerk.nonce,
            message: text,
            history: JSON.stringify(supportHistory)
        }, function(r) {
            if (r.success) {
                addSupportMsg('assistant', r.data.message);
                supportHistory.push({ role: 'assistant', content: r.data.message });
            } else {
                addSupportMsg('assistant', 'Error: ' + (r.data.message || 'Something went wrong.'));
            }
        });
    }

    $('#support-chat-send').on('click', sendSupportMsg);
    $input.on('keypress', function(e) { if (e.which === 13) sendSupportMsg(); });
});
</script>
