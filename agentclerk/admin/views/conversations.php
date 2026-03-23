<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap agentclerk-conversations">
    <h1>Conversations</h1>

    <div class="agentclerk-stat-grid" id="convo-stats">
        <div class="agentclerk-stat-card"><h3>Total</h3><div class="agentclerk-stat-value" id="cs-total">—</div></div>
        <div class="agentclerk-stat-card"><h3>Setup Helped</h3><div class="agentclerk-stat-value" id="cs-setup">—</div></div>
        <div class="agentclerk-stat-card"><h3>Support Resolved</h3><div class="agentclerk-stat-value" id="cs-support">—</div></div>
        <div class="agentclerk-stat-card" style="background:#fff3cd;"><h3>In Cart</h3><div class="agentclerk-stat-value" id="cs-cart">—</div></div>
        <div class="agentclerk-stat-card"><h3>Escalated</h3><div class="agentclerk-stat-value"><a href="<?php echo esc_url( admin_url( 'admin.php?page=agentclerk-support' ) ); ?>" id="cs-escalated">—</a></div></div>
    </div>

    <div style="margin:15px 0;">
        <label>Filter:
            <select id="convo-filter">
                <option value="">All</option>
                <option value="browsing">Browsing</option>
                <option value="quote">Quote</option>
                <option value="purchased">Purchased</option>
                <option value="setup">Setup</option>
                <option value="support">Support</option>
                <option value="abandoned">Abandoned</option>
                <option value="escalated">Escalated</option>
            </select>
        </label>
    </div>

    <table class="wp-list-table widefat fixed striped" id="convo-table">
        <thead>
            <tr>
                <th>Session</th>
                <th>Buyer Type</th>
                <th>Outcome</th>
                <th>Sale</th>
                <th>Started</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="convo-tbody"></tbody>
    </table>

    <div id="convo-pagination" style="margin-top:10px;"></div>

    <!-- Transcript Modal -->
    <div id="transcript-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;">
        <div style="background:#fff;max-width:600px;margin:50px auto;padding:20px;border-radius:8px;max-height:80vh;overflow-y:auto;">
            <h2>Conversation Transcript <button class="button" id="close-modal" style="float:right;">Close</button></h2>
            <div id="transcript-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var currentPage = 1;

    function loadStats() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_get_stats', nonce: agentclerk.nonce }, function(r) {
            if (r.success) {
                $('#cs-total').text(r.data.total);
                $('#cs-setup').text(r.data.setup);
                $('#cs-support').text(r.data.support);
                $('#cs-cart').text(r.data.in_cart);
                $('#cs-escalated').text(r.data.escalated);
            }
        });
    }

    function loadConversations(page) {
        var filter = $('#convo-filter').val();
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversations',
            nonce: agentclerk.nonce,
            outcome: filter,
            paged: page || 1
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.conversations, function(i, c) {
                html += '<tr>';
                html += '<td>' + c.session_id.substring(0, 12) + '...</td>';
                html += '<td>' + c.buyer_type + '</td>';
                html += '<td>' + c.outcome + '</td>';
                html += '<td>' + (c.sale_amount ? '$' + parseFloat(c.sale_amount).toFixed(2) : '—') + '</td>';
                html += '<td>' + c.started_at + '</td>';
                html += '<td>' + c.updated_at + '</td>';
                html += '<td><button class="button view-transcript" data-id="' + c.id + '">View</button></td>';
                html += '</tr>';
            });
            $('#convo-tbody').html(html || '<tr><td colspan="7">No conversations found.</td></tr>');
        });
    }

    loadStats();
    loadConversations(1);

    $('#convo-filter').on('change', function() { loadConversations(1); });

    $(document).on('click', '.view-transcript', function() {
        var id = $(this).data('id');
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversation_messages',
            nonce: agentclerk.nonce,
            conversation_id: id
        }, function(r) {
            if (r.success) {
                var html = '';
                $.each(r.data.messages, function(i, m) {
                    html += '<div style="margin:8px 0;padding:8px;background:' + (m.role === 'user' ? '#e3f2fd' : '#f5f5f5') + ';border-radius:6px;">';
                    html += '<strong>' + m.role + '</strong> <small>' + m.created_at + '</small><br>' + $('<span>').text(m.content).html();
                    html += '</div>';
                });
                $('#transcript-content').html(html || '<p>No messages.</p>');
                $('#transcript-modal').show();
            }
        });
    });

    $('#close-modal').on('click', function() { $('#transcript-modal').hide(); });
    $('#transcript-modal').on('click', function(e) { if (e.target === this) $(this).hide(); });
});
</script>
