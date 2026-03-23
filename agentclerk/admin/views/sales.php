<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$tier    = get_option( 'agentclerk_tier', 'byok' );
$license = get_option( 'agentclerk_license_status', 'none' );
?>
<div class="wrap agentclerk-sales">
    <h1>Sales</h1>

    <div style="margin-bottom:15px;">
        <button class="button" id="period-month">This Month</button>
        <button class="button" id="period-all">All Time</button>
    </div>

    <div class="agentclerk-stat-grid" id="sales-stats">
        <div class="agentclerk-stat-card"><h3>Gross Sales via Agent</h3><div class="agentclerk-stat-value" id="ss-gross">—</div></div>
        <div class="agentclerk-stat-card"><h3>Billed Transactions</h3><div class="agentclerk-stat-value" id="ss-count">—</div></div>
        <div class="agentclerk-stat-card"><h3>Average Order Value</h3><div class="agentclerk-stat-value" id="ss-avg">—</div></div>
        <div class="agentclerk-stat-card"><h3>Accrued Fees</h3><div class="agentclerk-stat-value" id="ss-fees">—</div></div>
    </div>

    <?php if ( $license !== 'active' ) : ?>
        <div class="agentclerk-card agentclerk-cta">
            <h2>Lifetime License</h2>
            <p>Eliminate all transaction fees with a one-time payment. Never pay per-sale fees again.</p>
            <button class="button button-primary" id="lifetime-cta">Get Lifetime License</button>
        </div>
    <?php endif; ?>

    <div style="margin:15px 0;">
        <button class="button" id="update-card">Update Payment Card</button>
    </div>

    <h2>Transaction History</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Session</th>
                <th>Sale Amount</th>
                <th>Fee</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="tx-tbody"></tbody>
    </table>
</div>

<script>
jQuery(function($) {
    var period = 'all';

    function loadSales() {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_sales',
            nonce: agentclerk.nonce,
            period: period
        }, function(r) {
            if (!r.success) return;
            var d = r.data;
            $('#ss-gross').text('$' + parseFloat(d.gross).toFixed(2));
            $('#ss-count').text(d.count);
            $('#ss-avg').text('$' + parseFloat(d.average).toFixed(2));
            $('#ss-fees').text('$' + parseFloat(d.accrued_fees).toFixed(2));

            var html = '';
            $.each(d.transactions, function(i, t) {
                html += '<tr>';
                html += '<td>' + t.id + '</td>';
                html += '<td>' + t.session_id.substring(0, 12) + '...</td>';
                html += '<td>$' + parseFloat(t.sale_amount).toFixed(2) + '</td>';
                html += '<td>$' + parseFloat(t.acclerk_fee).toFixed(2) + '</td>';
                html += '<td>' + t.updated_at + '</td>';
                html += '</tr>';
            });
            $('#tx-tbody').html(html || '<tr><td colspan="5">No transactions yet.</td></tr>');
        });
    }

    loadSales();

    $('#period-month').on('click', function() { period = 'month'; loadSales(); });
    $('#period-all').on('click', function() { period = 'all'; loadSales(); });

    $('#lifetime-cta').on('click', function() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_lifetime_checkout', nonce: agentclerk.nonce }, function(r) {
            if (r.success && r.data.checkoutUrl) window.location.href = r.data.checkoutUrl;
        });
    });

    $('#update-card').on('click', function() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_card_update', nonce: agentclerk.nonce }, function(r) {
            if (r.success && r.data.portalUrl) window.location.href = r.data.portalUrl;
        });
    });
});
</script>
