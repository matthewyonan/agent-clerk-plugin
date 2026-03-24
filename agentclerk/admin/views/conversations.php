<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-flex-between ac-mb">
        <div>
            <h1 class="ac-page-title"><?php echo esc_html( 'Conversations' ); ?></h1>
            <p class="ac-page-subtitle"><?php echo esc_html( 'Every seller agent conversation. Click a row to review the transcript.' ); ?></p>
        </div>
        <div class="ac-flex">
            <select id="ac-convo-filter" style="width:auto">
                <option value=""><?php echo esc_html( 'All conversations' ); ?></option>
                <option value="ai_agent"><?php echo esc_html( 'AI agent buyers' ); ?></option>
                <option value="human"><?php echo esc_html( 'Human buyers' ); ?></option>
                <option value="escalated"><?php echo esc_html( 'Escalated' ); ?></option>
            </select>
            <input type="text" id="ac-convo-search" placeholder="<?php echo esc_attr( 'Search...' ); ?>" style="width:160px">
        </div>
    </div>

    <div class="ac-stat-grid ac-stat-grid-5">
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-total">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Total' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-setup">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Helped with install / setup' ); ?></div>
            <div class="ac-stat-sub"><?php echo esc_html( 'no human needed' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-support">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Handled support' ); ?></div>
            <div class="ac-stat-sub"><?php echo esc_html( 'no human needed' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-cart">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'In cart' ); ?></div>
            <div class="ac-stat-sub" style="color:var(--ac-amber)"><?php echo esc_html( 'quote sent, unpaid' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-escalated">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Escalated to you' ); ?></div>
            <div class="ac-stat-sub"><a href="<?php echo esc_url( admin_url( 'admin.php?page=agentclerk-support' ) ); ?>"><?php echo esc_html( 'View in Support' ); ?> &rarr;</a></div>
        </div>
    </div>

    <div class="ac-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th><?php echo esc_html( 'Date' ); ?></th>
                    <th><?php echo esc_html( 'Buyer type' ); ?></th>
                    <th><?php echo esc_html( 'Started with' ); ?></th>
                    <th><?php echo esc_html( 'Outcome' ); ?></th>
                    <th><?php echo esc_html( 'Product' ); ?></th>
                    <th><?php echo esc_html( 'Value' ); ?></th>
                </tr>
            </thead>
            <tbody id="ac-convo-tbody">
                <tr>
                    <td colspan="6" style="color:var(--ac-text3)"><?php echo esc_html( 'Loading...' ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="ac-convo-pagination" style="margin-top:10px"></div>

    <!-- Transcript Modal -->
    <div class="ac-modal-overlay" id="ac-transcript-modal">
        <div class="ac-modal" style="width:600px">
            <div class="ac-modal-header">
                <h3><?php echo esc_html( 'Conversation Transcript' ); ?></h3>
                <button class="ac-modal-close" id="ac-close-transcript">&times;</button>
            </div>
            <div class="ac-modal-body" id="ac-transcript-content" style="max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    function loadStats() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversation_stats',
            nonce: agentclerk.nonce
        }, function(r) {
            if (!r.success) return;
            var d = r.data;
            $('#ac-cs-total').text(d.total || 0);
            $('#ac-cs-setup').text(d.setup || 0);
            $('#ac-cs-support').text(d.support || 0);
            $('#ac-cs-cart').text(d.in_cart || 0);
            $('#ac-cs-escalated').text(d.escalated || 0);
        });
    }

    function outcomeBadgeClass(outcome) {
        var green  = ['purchased', 'setup helped', 'support resolved'];
        var amber  = ['escalated', 'in cart'];
        var slate  = ['browsing', 'abandoned'];
        if (green.indexOf(outcome) !== -1) return 'ac-badge-green';
        if (amber.indexOf(outcome) !== -1) return 'ac-badge-amber';
        return 'ac-badge-slate';
    }

    function buyerBadge(type) {
        if (type === 'ai_agent') {
            return '<span class="ac-badge ac-badge-electric">' + 'AI agent' + '</span>';
        }
        return '<span class="ac-badge ac-badge-slate">' + 'Human' + '</span>';
    }

    function loadConversations(page) {
        var filter = $('#ac-convo-filter').val();
        var search = $.trim($('#ac-convo-search').val());

        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversations',
            nonce: agentclerk.nonce,
            filter: filter,
            search: search,
            paged: page || 1
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.conversations || [], function(i, c) {
                html += '<tr style="cursor:pointer" data-conversation-id="' + c.id + '">';
                html += '<td style="font-family:\'DM Mono\',monospace;font-size:11px;color:var(--ac-text3)">' + $('<span>').text(c.started_at || '').html() + '</td>';
                html += '<td>' + buyerBadge(c.buyer_type) + '</td>';
                html += '<td style="color:var(--ac-text2);font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + $('<span>').text(c.first_message || '').html() + '</td>';
                html += '<td><span class="ac-badge ' + outcomeBadgeClass(c.outcome) + '">' + $('<span>').text(c.outcome || 'browsing').html() + '</span></td>';
                html += '<td style="font-size:12px">' + $('<span>').text(c.product_name || '').html() + '</td>';
                html += '<td style="font-family:\'DM Mono\',monospace;font-weight:500">' + (c.sale_amount ? '$' + parseFloat(c.sale_amount).toFixed(0) : '&mdash;') + '</td>';
                html += '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="6" style="color:var(--ac-text3)">No conversations found.</td></tr>';
            }
            $('#ac-convo-tbody').html(html);
        });
    }

    loadStats();
    loadConversations(1);

    $('#ac-convo-filter').on('change', function() { loadConversations(1); });
    var searchTimer;
    $('#ac-convo-search').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { loadConversations(1); }, 400);
    });

    /* Transcript modal */
    $(document).on('click', '#ac-convo-tbody tr[data-conversation-id]', function() {
        var id = $(this).data('conversation-id');
        if (!id) return;
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_conversation_messages',
            nonce: agentclerk.nonce,
            conversation_id: id
        }, function(r) {
            if (!r.success) return;
            var html = '';
            $.each(r.data.messages || [], function(i, m) {
                var cls = m.role === 'user' ? 'ac-msg ac-msg-user' : 'ac-msg ac-msg-agent';
                var av  = m.role === 'user' ? 'You' : 'AC';
                html += '<div class="' + cls + '">';
                html += '<div class="ac-msg-avatar">' + av + '</div>';
                html += '<div class="ac-msg-bubble">' + $('<span>').text(m.content).html() + '</div>';
                html += '</div>';
            });
            if (!html) {
                html = '<p style="color:var(--ac-text3)">No messages.</p>';
            }
            $('#ac-transcript-content').html('<div style="display:flex;flex-direction:column;gap:10px">' + html + '</div>');
            $('#ac-transcript-modal').addClass('active');
        });
    });

    $('#ac-close-transcript').on('click', function() {
        $('#ac-transcript-modal').removeClass('active');
    });
    $('#ac-transcript-modal').on('click', function(e) {
        if (e.target === this) $(this).removeClass('active');
    });
});
</script>
