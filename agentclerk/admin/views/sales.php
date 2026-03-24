<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$tier    = get_option( 'agentclerk_tier', 'byok' );
$license = get_option( 'agentclerk_license_status', 'none' );
$fee_rate = $tier === 'turnkey' ? '1.5% or $1.99 min' : '1% or $1.00 min';
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt">Sales</div>
            <div class="ac-ps">Agent-closed sales only. Fees apply only to transactions completed through AgentClerk.</div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; Active</span>
            <div class="ac-period-toggle">
                <button class="ac-period-btn active" id="tog-month" data-period="month">This month</button>
                <button class="ac-period-btn" id="tog-all" data-period="all">All time</button>
            </div>
        </div>
    </div>

    <?php if ( $license !== 'active' ) : ?>
        <div class="ac-ltm-cta" id="lifetime-cta-bar">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--ac-text)" id="ltm-cta-text">You've accrued fees this month. <strong style="color:var(--ac-elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.</span>
            <span class="ac-ltm-btn" id="lifetime-cta">Upgrade &rarr;</span>
        </div>
    <?php endif; ?>

    <div class="ac-stat-grid ac-stat-grid-4">
        <div class="ac-stat-box"><div class="ac-stat-val" id="ss-gross">&mdash;</div><div class="ac-stat-lbl">Gross sales via agent</div><div class="ac-stat-sub" id="ss-period-label">this month</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="ss-count">&mdash;</div><div class="ac-stat-lbl">Billed transactions</div><div class="ac-stat-sub">agent-closed only</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="ss-avg">&mdash;</div><div class="ac-stat-lbl">Average order value</div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="ss-fees">&mdash;</div><div class="ac-stat-lbl">AgentClerk fees accrued</div></div>
    </div>

    <div class="ac-g2 ac-mb">
        <div class="ac-card">
            <div class="ac-card-head"><h2>Billing</h2></div>
            <div class="ac-card-body">
                <div style="font-size:12px;color:var(--ac-text2);margin-bottom:10px">Auto-billed when fees reach threshold, or end of month.</div>
                <div class="ac-co bl" style="margin-bottom:0"><span class="ac-co-i">&#8505;</span><span><a href="#" id="update-card">Update payment method &rarr;</a></span></div>
            </div>
        </div>
        <div class="ac-card">
            <div class="ac-card-head"><h2>What gets charged</h2></div>
            <div class="ac-card-body">
                <div class="ac-tog-row"><div><div class="ac-tog-lbl">Sales via agent</div><div class="ac-tog-desc">When agent generates a quote and buyer completes checkout</div></div><span class="ac-fee-pill"><?php echo esc_html( $fee_rate ); ?></span></div>
                <div class="ac-tog-row"><div><div class="ac-tog-lbl">Free products</div><div class="ac-tog-desc">$0 transactions, always</div></div><span class="ac-b ac-b-g">No charge</span></div>
                <div class="ac-tog-row"><div><div class="ac-tog-lbl">Direct WooCommerce checkout</div><div class="ac-tog-desc">Sales that didn't go through the agent</div></div><span class="ac-b ac-b-g">No charge</span></div>
            </div>
        </div>
    </div>

    <div class="ac-card">
        <div class="ac-card-head"><h2>Transaction history</h2></div>
        <table class="ac-dt">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Sale amount</th>
                    <th>AgentClerk fee</th>
                    <th>Buyer type</th>
                </tr>
            </thead>
            <tbody id="tx-tbody"></tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var period = 'month';

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
            $('#ss-period-label').text(period === 'month' ? 'this month' : 'all time');

            var html = '';
            $.each(d.transactions, function(i, t) {
                var buyerBadge = t.buyer_type === 'ai_agent' ? '<span class="ac-b ac-b-e">AI agent</span>' : '<span class="ac-b ac-b-s">Human</span>';
                html += '<tr>';
                html += '<td class="ac-mono" style="font-size:11px;color:var(--ac-text3)">' + (t.updated_at || '') + '</td>';
                html += '<td>' + (t.product_name || 'Unknown') + '</td>';
                html += '<td class="ac-mono">$' + parseFloat(t.sale_amount).toFixed(2) + '</td>';
                html += '<td class="ac-mono" style="color:var(--ac-text2)">$' + parseFloat(t.acclerk_fee).toFixed(2) + '</td>';
                html += '<td>' + buyerBadge + '</td>';
                html += '</tr>';
            });
            $('#tx-tbody').html(html || '<tr><td colspan="5" style="color:var(--ac-text3)">No transactions yet.</td></tr>');
        });
    }

    loadSales();

    $('.ac-period-btn').on('click', function() {
        period = $(this).data('period');
        $('.ac-period-btn').removeClass('active');
        $(this).addClass('active');
        loadSales();
    });

    $('#lifetime-cta, #lifetime-cta-bar').on('click', function() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_lifetime_checkout', nonce: agentclerk.nonce }, function(r) {
            if (r.success && r.data.checkoutUrl) window.location.href = r.data.checkoutUrl;
        });
    });

    $('#update-card').on('click', function(e) {
        e.preventDefault();
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_card_update', nonce: agentclerk.nonce }, function(r) {
            if (r.success && r.data.portalUrl) window.location.href = r.data.portalUrl;
        });
    });
});
</script>
