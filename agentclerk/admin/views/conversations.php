<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt">Conversations</div>
            <div class="ac-ps">Every seller agent conversation. Click a row to review the transcript.</div>
        </div>
        <div class="ac-fr">
            <select id="convo-filter" style="width:auto">
                <option value="">All conversations</option>
                <option value="browsing">Browsing</option>
                <option value="quote">Quote sent</option>
                <option value="purchased">Purchased</option>
                <option value="setup">Setup helped</option>
                <option value="support">Support resolved</option>
                <option value="abandoned">Abandoned</option>
                <option value="escalated">Escalated</option>
            </select>
            <input type="text" id="convo-search" placeholder="Search&hellip;" style="width:150px">
        </div>
    </div>

    <div class="ac-stat-grid ac-stat-grid-5">
        <div class="ac-stat-box"><div class="ac-stat-val" id="cs-total">&mdash;</div><div class="ac-stat-lbl">Total conversations</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="cs-setup">&mdash;</div><div class="ac-stat-lbl">Helped with install / setup</div><div class="ac-stat-sub">no human needed</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="cs-support">&mdash;</div><div class="ac-stat-lbl">Handled support</div><div class="ac-stat-sub">no human needed</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="cs-cart">&mdash;</div><div class="ac-stat-lbl">In cart</div><div class="ac-stat-sub" style="color:var(--ac-amber)">quote sent, unpaid</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="cs-escalated">&mdash;</div><div class="ac-stat-lbl">Escalated to you</div></div>
    </div>

    <div class="ac-card">
        <table class="ac-dt">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Buyer type</th>
                    <th>Started with</th>
                    <th>Outcome</th>
                    <th>Product</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody id="convo-tbody"></tbody>
        </table>
    </div>

    <div id="convo-pagination" style="margin-top:10px;"></div>

    <!-- Transcript Modal -->
    <div class="ac-modal-ov" id="transcript-modal">
        <div class="ac-modal-box" style="width:600px">
            <div class="ac-modal-hd">
                <h3>Conversation Transcript</h3>
                <button class="ac-modal-x" id="close-modal">&times;</button>
            </div>
            <div class="ac-modal-body" id="transcript-content" style="max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    function loadStats() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_get_stats', nonce: agentclerk.nonce }, function(r) {
            if (!r.success) return;
            $('#cs-total').text(r.data.total);
            $('#cs-setup').text(r.data.setup);
            $('#cs-support').text(r.data.support);
            $('#cs-cart').text(r.data.in_cart);
            $('#cs-escalated').text(r.data.escalated);
        });
    }

    function badgeCls(val) {
        var map = { purchased: 'ac-b-g', 'setup helped': 'ac-b-g', 'support resolved': 'ac-b-g', escalated: 'ac-b-a', browsing: 'ac-b-s', quote: 'ac-b-e', abandoned: 'ac-b-s' };
        return map[val] || 'ac-b-s';
    }

    function buyerBadge(type) {
        return type === 'ai_agent' ? '<span class="ac-b ac-b-e">AI agent</span>' : '<span class="ac-b ac-b-s">Human</span>';
    }

    function loadConversations(page) {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversations',
            nonce: agentclerk.nonce,
            outcome: $('#convo-filter').val(),
            paged: page || 1
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.conversations, function(i, c) {
                html += '<tr style="cursor:pointer" data-id="' + c.id + '">';
                html += '<td class="ac-mono" style="font-size:11px;color:var(--ac-text3)">' + (c.started_at || '') + '</td>';
                html += '<td>' + buyerBadge(c.buyer_type) + '</td>';
                html += '<td style="color:var(--ac-text2);font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (c.first_message || '') + '</td>';
                html += '<td><span class="ac-b ' + badgeCls(c.outcome) + '">' + (c.outcome || 'browsing') + '</span></td>';
                html += '<td style="font-size:12px">' + (c.product_name || '&mdash;') + '</td>';
                html += '<td class="ac-mono" style="font-weight:500">' + (c.sale_amount ? '$' + parseFloat(c.sale_amount).toFixed(0) : '&mdash;') + '</td>';
                html += '</tr>';
            });
            $('#convo-tbody').html(html || '<tr><td colspan="6" style="color:var(--ac-text3)">No conversations found.</td></tr>');
        });
    }

    loadStats();
    loadConversations(1);

    $('#convo-filter').on('change', function() { loadConversations(1); });

    $(document).on('click', '#convo-tbody tr', function() {
        var id = $(this).data('id');
        if (!id) return;
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversation_messages',
            nonce: agentclerk.nonce,
            conversation_id: id
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.messages, function(i, m) {
                var cls = m.role === 'user' ? 'us' : 'ag';
                var av = m.role === 'user' ? 'You' : 'AC';
                html += '<div class="ac-msg ' + cls + '" style="max-width:95%"><div class="ac-mav">' + av + '</div><div class="ac-mbub">' + $('<span>').text(m.content).html() + '</div></div>';
            });
            $('#transcript-content').html('<div style="display:flex;flex-direction:column;gap:10px">' + (html || '<p style="color:var(--ac-text3)">No messages.</p>') + '</div>');
            $('#transcript-modal').addClass('open');
        });
    });

    $('#close-modal').on('click', function() { $('#transcript-modal').removeClass('open'); });
    $('#transcript-modal').on('click', function(e) { if (e.target === this) $(this).removeClass('open'); });
});
</script>
