<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$tier     = get_option( 'agentclerk_tier', 'byok' );
$license  = get_option( 'agentclerk_license_status', 'none' );
$fee_rate = ( $tier === 'turnkey' ) ? '1.5% or $1.99 min' : '1% or $1.00 min';
?>
<div class="wrap ac-wrap">
    <div class="ac-flex-between ac-mb">
        <div>
            <h1 class="ac-page-title"><?php echo esc_html( 'Sales' ); ?></h1>
            <p class="ac-page-subtitle"><?php echo esc_html( 'Agent-closed sales only. Fees apply only to transactions completed through AgentClerk.' ); ?></p>
        </div>
        <div class="ac-flex">
            <span class="ac-badge ac-badge-green">&#9679; <?php echo esc_html( 'Active' ); ?></span>
            <div class="ac-period-toggle">
                <button class="ac-period-btn active" id="ac-tog-month" data-period="month"><?php echo esc_html( 'This month' ); ?></button>
                <button class="ac-period-btn" id="ac-tog-all" data-period="all"><?php echo esc_html( 'All time' ); ?></button>
            </div>
        </div>
    </div>

    <?php if ( $license !== 'active' ) : ?>
        <div class="ac-lifetime-cta" id="ac-sales-lifetime-cta">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--ac-text)" id="ac-ltm-cta-text">
                <?php
                echo wp_kses(
                    'You\'ve accrued fees this month. <strong style="color:var(--ac-electric-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.',
                    array( 'strong' => array( 'style' => array() ) )
                );
                ?>
            </span>
            <span class="ac-lifetime-btn" id="ac-sales-lifetime-btn"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
        </div>
    <?php endif; ?>

    <div class="ac-stat-grid ac-stat-grid-4">
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-ss-gross">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Gross sales via agent' ); ?></div>
            <div class="ac-stat-sub" id="ac-ss-period-label"><?php echo esc_html( 'this month' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-ss-count">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Billed transactions' ); ?></div>
            <div class="ac-stat-sub"><?php echo esc_html( 'agent-closed only' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-ss-avg">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Average order value' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-ss-fees">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'AgentClerk fees accrued' ); ?></div>
            <div class="ac-stat-sub"><?php echo esc_html( 'of $20 threshold' ); ?></div>
        </div>
    </div>

    <div class="ac-grid-2 ac-mb">
        <!-- Billing threshold card -->
        <div class="ac-card">
            <div class="ac-card-head">
                <h2><?php echo esc_html( 'Billing threshold' ); ?></h2>
            </div>
            <div class="ac-card-body">
                <div style="margin-bottom:10px">
                    <div class="ac-score-track" style="height:6px;margin-bottom:6px">
                        <div class="ac-score-fill" id="ac-billing-progress" style="width:0%;background:var(--ac-electric-dk)"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--ac-text3)">
                        <span id="ac-billing-accrued">$0.00</span>
                        <span>$20.00</span>
                    </div>
                </div>
                <div class="ac-callout ac-callout-blue" style="margin-bottom:0">
                    <span>&#8505;</span>
                    <span><?php echo esc_html( 'Auto-billed when fees reach threshold, or at end of month.' ); ?></span>
                </div>
            </div>
        </div>

        <!-- What gets charged card -->
        <div class="ac-card">
            <div class="ac-card-head">
                <h2><?php echo esc_html( 'What gets charged' ); ?></h2>
            </div>
            <div class="ac-card-body">
                <div class="ac-toggle-row">
                    <div>
                        <div style="font-size:13px;font-weight:500"><?php echo esc_html( 'Sales via agent' ); ?></div>
                        <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( 'When agent generates a quote and buyer completes checkout' ); ?></div>
                    </div>
                    <span class="ac-fee-pill"><?php echo esc_html( $fee_rate ); ?></span>
                </div>
                <div class="ac-toggle-row">
                    <div>
                        <div style="font-size:13px;font-weight:500"><?php echo esc_html( 'Free products' ); ?></div>
                        <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( '$0 transactions, always' ); ?></div>
                    </div>
                    <span class="ac-badge ac-badge-green"><?php echo esc_html( 'No charge' ); ?></span>
                </div>
                <div class="ac-toggle-row">
                    <div>
                        <div style="font-size:13px;font-weight:500"><?php echo esc_html( 'Direct WooCommerce checkout' ); ?></div>
                        <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( 'Sales that didn\'t go through the agent' ); ?></div>
                    </div>
                    <span class="ac-badge ac-badge-green"><?php echo esc_html( 'No charge' ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction history -->
    <div class="ac-card">
        <div class="ac-card-head">
            <h2><?php echo esc_html( 'Transaction history' ); ?></h2>
        </div>
        <table class="ac-table">
            <thead>
                <tr>
                    <th><?php echo esc_html( 'Date' ); ?></th>
                    <th><?php echo esc_html( 'Product' ); ?></th>
                    <th><?php echo esc_html( 'Sale amount' ); ?></th>
                    <th><?php echo esc_html( 'AgentClerk fee' ); ?></th>
                    <th><?php echo esc_html( 'Buyer type' ); ?></th>
                </tr>
            </thead>
            <tbody id="ac-tx-tbody">
                <tr>
                    <td colspan="5" style="color:var(--ac-text3)"><?php echo esc_html( 'Loading...' ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var period = 'month';

    function loadSales() {
        $.get(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_sales_data',
            nonce: agentclerk.nonce,
            period: period
        }, function(r) {
            if (!r.success) return;
            var d = r.data;

            $('#ac-ss-gross').text('$' + parseFloat(d.gross || 0).toFixed(2));
            $('#ac-ss-count').text(d.count || 0);
            $('#ac-ss-avg').text('$' + parseFloat(d.average || 0).toFixed(2));
            $('#ac-ss-fees').text('$' + parseFloat(d.accrued_fees || 0).toFixed(2));
            $('#ac-ss-period-label').text(period === 'month' ? 'this month' : 'all time');

            /* Billing progress bar */
            var feePct = Math.min(100, (parseFloat(d.accrued_fees || 0) / 20) * 100);
            $('#ac-billing-progress').css('width', feePct + '%');
            $('#ac-billing-accrued').text('$' + parseFloat(d.accrued_fees || 0).toFixed(2));

            /* Transaction table */
            var html = '';
            $.each(d.transactions || [], function(i, t) {
                var buyerBadge = t.buyer_type === 'ai_agent'
                    ? '<span class="ac-badge ac-badge-electric">AI agent</span>'
                    : '<span class="ac-badge ac-badge-slate">Human</span>';
                html += '<tr>';
                html += '<td style="font-family:\'DM Mono\',monospace;font-size:11px;color:var(--ac-text3)">' + $('<span>').text(t.updated_at || '').html() + '</td>';
                html += '<td>' + $('<span>').text(t.product_name || 'Unknown').html() + '</td>';
                html += '<td style="font-family:\'DM Mono\',monospace">$' + parseFloat(t.sale_amount || 0).toFixed(2) + '</td>';
                html += '<td style="font-family:\'DM Mono\',monospace;color:var(--ac-text2)">$' + parseFloat(t.acclerk_fee || 0).toFixed(2) + '</td>';
                html += '<td>' + buyerBadge + '</td>';
                html += '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="5" style="color:var(--ac-text3)">No transactions yet.</td></tr>';
            }
            $('#ac-tx-tbody').html(html);
        });
    }

    loadSales();

    /* Period toggle */
    $('#ac-tog-month, #ac-tog-all').on('click', function() {
        period = $(this).data('period');
        $('#ac-tog-month, #ac-tog-all').removeClass('active');
        $(this).addClass('active');
        loadSales();
    });

    /* Lifetime CTA */
    $('#ac-sales-lifetime-btn, #ac-sales-lifetime-cta').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_purchase_lifetime',
            nonce: agentclerk.nonce
        }, function(r) {
            if (r.success && r.data.checkoutUrl) {
                window.location.href = r.data.checkoutUrl;
            }
        });
    });
});
</script>
