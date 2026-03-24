<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-flex-between ac-mb">
        <div>
            <h1 class="ac-page-title"><?php echo esc_html( 'Support' ); ?></h1>
            <p class="ac-page-subtitle"><?php echo esc_html( 'Buyer escalations on the left. Ask us about the plugin on the right.' ); ?></p>
        </div>
        <div class="ac-flex">
            <span class="ac-badge ac-badge-amber" id="ac-open-count"><?php echo esc_html( '0 open' ); ?></span>
        </div>
    </div>

    <div class="ac-grid-2">
        <!-- Left column: Buyer Escalations -->
        <div>
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ac-text3);margin-bottom:8px"><?php echo esc_html( 'BUYER ESCALATIONS' ); ?></div>
            <div id="ac-escalation-list">
                <div class="ac-callout ac-callout-slate"><span>&#8987;</span><span><?php echo esc_html( 'Loading...' ); ?></span></div>
            </div>
            <a href="#" id="ac-view-resolved" style="display:none;font-size:12px;margin-top:8px"><?php echo esc_html( 'View resolved escalations' ); ?> &rarr;</a>
        </div>

        <!-- Right column: AgentClerk Help -->
        <div>
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ac-text3);margin-bottom:8px"><?php echo esc_html( 'AGENTCLERK HELP' ); ?></div>
            <div class="ac-chat-shell" style="min-height:420px">
                <div class="ac-chat-header">
                    <div class="ac-chat-avatar">&#9889;</div>
                    <div>
                        <div class="ac-chat-name"><?php echo esc_html( 'AgentClerk Support' ); ?></div>
                        <div class="ac-chat-status"><?php echo esc_html( 'Ask us anything about the plugin' ); ?></div>
                    </div>
                </div>
                <div class="ac-messages" id="ac-support-msgs" style="height:280px"></div>
                <div style="padding:6px 12px 2px;display:flex;flex-wrap:wrap;gap:4px">
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'How do I update my support file?' ); ?>"><?php echo esc_html( 'How do I update my support file?' ); ?></span>
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'Why did a transaction not get billed?' ); ?>"><?php echo esc_html( 'Why did a transaction not get billed?' ); ?></span>
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'How do I handle the refund request?' ); ?>"><?php echo esc_html( 'How do I handle the refund request?' ); ?></span>
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'Re-send a download link to a buyer' ); ?>"><?php echo esc_html( 'Re-send a download link to a buyer' ); ?></span>
                </div>
                <div class="ac-chat-input-row">
                    <input type="text" class="ac-chat-input" id="ac-support-input" placeholder="<?php echo esc_attr( 'Ask about AgentClerk...' ); ?>">
                    <button class="ac-send-btn" id="ac-support-send">&#10148;</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    function loadEscalations() {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_toggle_escalation_read',
            nonce: agentclerk.nonce,
            list: 1
        }, function(r) {
            if (!r.success) return;
            var items = r.data.escalations || [];
            var openCount = 0;
            var html = '';
            $.each(items, function(i, e) {
                if (!e.read) openCount++;
                var cls = e.read ? '' : ' unread';
                html += '<div class="ac-escalation-card' + cls + '" data-id="' + e.id + '">';
                html += '<div class="ac-flex-between" style="margin-bottom:6px">';
                html += '<strong style="font-size:13px">' + $('<span>').text(e.email || 'No email').html() + '</strong>';
                html += '<button class="ac-btn ac-btn-ghost ac-btn-sm ac-toggle-read" data-id="' + e.id + '">' + (e.read ? 'Mark unread' : 'Mark read') + '</button>';
                html += '</div>';
                html += '<div style="font-size:12px;color:var(--ac-text2);margin-bottom:4px">' + $('<span>').text(e.first_message || '(no message)').html() + '</div>';
                html += '<div style="font-size:11px;color:var(--ac-text3)">' + $('<span>').text(e.created_at || '').html() + '</div>';
                html += '</div>';
            });
            $('#ac-open-count').text(openCount + ' open');
            $('#ac-escalation-list').html(html || '<div style="color:var(--ac-text3);font-size:13px;padding:10px 0">No escalated conversations.</div>');
            if (r.data.resolved_count && r.data.resolved_count > 0) {
                $('#ac-view-resolved').show().text('View ' + r.data.resolved_count + ' resolved escalations \u2192');
            }
        });
    }
    loadEscalations();

    $(document).on('click', '.ac-toggle-read', function(e) {
        e.stopPropagation();
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_toggle_escalation_read', nonce: agentclerk.nonce, conversation_id: $(this).data('id') }, function() { loadEscalations(); });
    });

    var supportHistory = [];
    function addMsg(role, text) {
        var cls = role === 'assistant' ? 'ac-msg ac-msg-agent' : 'ac-msg ac-msg-user';
        var av  = role === 'assistant' ? 'AC' : 'You';
        $('#ac-support-msgs').append('<div class="' + cls + '"><div class="ac-msg-avatar">' + av + '</div><div class="ac-msg-bubble">' + text + '</div></div>');
        var el = $('#ac-support-msgs')[0];
        if (el) el.scrollTop = el.scrollHeight;
    }
    addMsg('assistant', 'Hi! I can help with AgentClerk plugin questions. What do you need help with?');

    function sendSupport() {
        var txt = $.trim($('#ac-support-input').val());
        if (!txt) return;
        addMsg('user', $('<span>').text(txt).html());
        supportHistory.push({ role: 'user', content: txt });
        $('#ac-support-input').val('');
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_send_plugin_support', nonce: agentclerk.nonce, message: txt, history: JSON.stringify(supportHistory) }, function(r) {
            if (r.success) { addMsg('assistant', r.data.message); supportHistory.push({ role: 'assistant', content: r.data.message }); }
            else { addMsg('assistant', 'Error: ' + (r.data ? r.data.message : 'Something went wrong.')); }
        });
    }
    $('#ac-support-send').on('click', sendSupport);
    $('#ac-support-input').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); sendSupport(); } });
    $('.ac-chip').on('click', function() { $('#ac-support-input').val($(this).data('q')); sendSupport(); });
});
</script>
