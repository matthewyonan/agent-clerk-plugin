<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$status       = get_option( 'agentclerk_plugin_status', 'active' );
$tier         = get_option( 'agentclerk_tier', 'byok' );
$license      = get_option( 'agentclerk_license_status', 'none' );
$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
?>
<div class="wrap agentclerk-dashboard">
    <h1>AgentClerk Dashboard</h1>

    <div class="agentclerk-status-bar">
        <span class="agentclerk-badge agentclerk-badge-<?php echo esc_attr( $status ); ?>">
            <?php echo esc_html( ucfirst( $status ) ); ?>
        </span>
        <span class="agentclerk-tier"><?php echo esc_html( strtoupper( $tier ) ); ?> plan</span>
    </div>

    <div class="agentclerk-stat-grid" id="dashboard-stats">
        <div class="agentclerk-stat-card">
            <h3>Conversations Today</h3>
            <div class="agentclerk-stat-value" id="stat-today">—</div>
        </div>
        <div class="agentclerk-stat-card">
            <h3>Sales Today</h3>
            <div class="agentclerk-stat-value" id="stat-sales-today">—</div>
        </div>
        <div class="agentclerk-stat-card">
            <h3>Total Conversations</h3>
            <div class="agentclerk-stat-value" id="stat-total">—</div>
        </div>
        <div class="agentclerk-stat-card">
            <h3>Escalated</h3>
            <div class="agentclerk-stat-value" id="stat-escalated">—</div>
        </div>
    </div>

    <?php if ( $tier === 'byok' && $license !== 'active' && $accrued_fees > 0 ) : ?>
        <div class="agentclerk-card agentclerk-cta">
            <h2>Eliminate Transaction Fees</h2>
            <p>You've accrued $<?php echo esc_html( number_format( $accrued_fees, 2 ) ); ?> in fees. Get a lifetime license to pay zero fees going forward.</p>
            <button class="button button-primary" id="lifetime-license-cta">Get Lifetime License</button>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    $.post(agentclerk.ajaxUrl, {
        action: 'agentclerk_get_stats',
        nonce: agentclerk.nonce
    }, function(resp) {
        if (resp.success) {
            var d = resp.data;
            $('#stat-today').text(d.today);
            $('#stat-sales-today').text('$' + parseFloat(d.sales_today).toFixed(2));
            $('#stat-total').text(d.total);
            $('#stat-escalated').text(d.escalated);
        }
    });

    $('#lifetime-license-cta').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_lifetime_checkout',
            nonce: agentclerk.nonce
        }, function(resp) {
            if (resp.success && resp.data.checkoutUrl) {
                window.location.href = resp.data.checkoutUrl;
            }
        });
    });
});
</script>
