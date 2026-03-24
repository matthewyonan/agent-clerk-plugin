<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ac-wrap">
    <div class="ac-pt">Support</div>
    <div class="ac-ps">Manage escalated conversations and get help with the plugin.</div>

    <div class="ac-g2">
        <div>
            <div class="ac-card">
                <div class="ac-card-head"><h2>Escalated Conversations</h2></div>
                <div class="ac-card-body" id="escalation-list">
                    <div class="ac-co sl"><span class="ac-co-i">&#8987;</span><span>Loading&hellip;</span></div>
                </div>
            </div>
        </div>

        <div>
            <div class="ac-card" style="display:flex;flex-direction:column">
                <div class="ac-card-head"><h2>AgentClerk Plugin Help</h2></div>
                <div class="ac-chat-shell" style="border:none;border-radius:0;flex:1">
                    <div class="ac-msgs" id="support-msgs" style="height:320px"></div>
                    <div class="ac-chat-inp-row">
                        <input type="text" class="ac-chat-inp" id="support-input" placeholder="Ask about AgentClerk&hellip;">
                        <button class="ac-send-btn" id="support-send">&#10148;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    // Escalations
    function loadEscalations() {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_escalations',
            nonce: agentclerk.nonce
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.escalations, function(i, e) {
                var readCls = e.read ? 'read' : '';
                html += '<div class="ac-escalation-card ' + readCls + '" data-id="' + e.id + '">';
                html += '<div class="ac-fb" style="margin-bottom:6px">';
                html += '<strong style="font-size:13px">' + (e.email || 'No email') + '</strong>';
                html += '<button class="ac-btn ac-btn-g ac-btn-sm toggle-read" data-id="' + e.id + '">' + (e.read ? 'Mark Unread' : 'Mark Read') + '</button>';
                html += '</div>';
                html += '<div style="font-size:12px;color:var(--ac-text2);margin-bottom:4px">' + $('<span>').text(e.first_message || '(no message)').html() + '</div>';
                html += '<div style="font-size:11px;color:var(--ac-text3)">' + e.created_at + '</div>';
                html += '</div>';
            });
            $('#escalation-list').html(html || '<div style="color:var(--ac-text3);font-size:13px;padding:10px 0">No escalated conversations.</div>');
        });
    }

    loadEscalations();

    $(document).on('click', '.toggle-read', function(e) {
        e.stopPropagation();
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_toggle_read',
            nonce: agentclerk.nonce,
            conversation_id: $(this).data('id')
        }, function() { loadEscalations(); });
    });

    // Support chat
    var supportHistory = [];
    function addMsg(role, text) {
        var cls = role === 'assistant' ? 'ag' : 'us';
        var av = role === 'assistant' ? 'AC' : 'You';
        $('#support-msgs').append('<div class="ac-msg ' + cls + '"><div class="ac-mav">' + av + '</div><div class="ac-mbub">' + text + '</div></div>');
        $('#support-msgs').scrollTop($('#support-msgs')[0].scrollHeight);
    }

    addMsg('assistant', 'Hi! I can help with AgentClerk plugin questions. What do you need help with?');

    function sendSupport() {
        var txt = $.trim($('#support-input').val());
        if (!txt) return;
        addMsg('user', txt);
        supportHistory.push({ role: 'user', content: txt });
        $('#support-input').val('');

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_support_chat',
            nonce: agentclerk.nonce,
            message: txt,
            history: JSON.stringify(supportHistory)
        }, function(r) {
            if (r.success) {
                addMsg('assistant', r.data.message);
                supportHistory.push({ role: 'assistant', content: r.data.message });
            } else {
                addMsg('assistant', 'Error: ' + (r.data ? r.data.message : 'Something went wrong.'));
            }
        });
    }

    $('#support-send').on('click', sendSupport);
    $('#support-input').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); sendSupport(); } });
});
</script>
