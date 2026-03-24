<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$tier     = get_option( 'agentclerk_tier', 'byok' );
$license  = get_option( 'agentclerk_license_status', 'none' );
$fee_rate = ( $tier === 'turnkey' ) ? '1.5% or $1.99 min' : '1% or $1.00 min';
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt"><?php echo esc_html( 'Sales' ); ?></div>
            <div class="ac-ps"><?php echo esc_html( 'Agent-closed sales only. Fees apply only to transactions completed through AgentClerk.' ); ?></div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; <?php echo esc_html( 'Active' ); ?></span>
            <div class="ac-period-toggle">
                <div class="ac-period-btn active" id="ac-tog-month" data-period="month"><?php echo esc_html( 'This month' ); ?></div>
                <div class="ac-period-btn" id="ac-tog-all" data-period="all"><?php echo esc_html( 'All time' ); ?></div>
            </div>
        </div>
    </div>

    <?php if ( $license !== 'active' ) : ?>
        <div class="ac-ltm-cta" id="ac-sales-lifetime-cta">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--text)" id="ac-ltm-cta-text">
                <?php
                echo wp_kses(
                    'You\'ve accrued fees this month. <strong style="color:var(--elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.',
                    array( 'strong' => array( 'style' => array() ) )
                );
                ?>
            </span>
            <span class="ac-ltm-btn" id="ac-sales-lifetime-btn"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
        </div>
    <?php endif; ?>

    <div class="ac-stat-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="ac-stat-box"><div class="ac-stat-val" id="ac-ss-gross">&mdash;</div><div class="ac-stat-lbl"><?php echo esc_html( 'Gross sales via agent' ); ?></div><div class="ac-stat-sub" id="ac-ss-period-label"><?php echo esc_html( 'this month' ); ?></div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="ac-ss-count">&mdash;</div><div class="ac-stat-lbl"><?php echo esc_html( 'Billed transactions' ); ?></div><div class="ac-stat-sub"><?php echo esc_html( 'agent-closed only' ); ?></div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="ac-ss-avg">&mdash;</div><div class="ac-stat-lbl"><?php echo esc_html( 'Average order value' ); ?></div></div>
        <div class="ac-stat-box"><div class="ac-stat-val" id="ac-ss-fees">&mdash;</div><div class="ac-stat-lbl"><?php echo esc_html( 'AgentClerk fees accrued' ); ?></div><div class="ac-stat-sub"><?php echo esc_html( 'of $20 threshold' ); ?></div></div>
    </div>

    <div class="ac-g2">
        <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Billing threshold' ); ?></h2></div><div class="ac-card-body">
            <div style="font-size:12px;color:var(--text2);margin-bottom:10px"><?php echo esc_html( 'Auto-billed when fees reach $20, or end of month — whichever comes first.' ); ?></div>
            <div class="ac-sc-row"><div class="ac-sc-lbl" id="ac-billing-accrued">$0 of $20</div><div class="ac-sc-track"><div class="ac-sc-fill" id="ac-billing-progress" style="width:0%;background:var(--elec-dk)"></div></div><div class="ac-sc-val" style="color:var(--text3)" id="ac-billing-pct">0%</div></div>
            <div class="ac-co bl" style="margin-top:10px;margin-bottom:0"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'Next billing date: end of month.' ); ?> <a href="#"><?php echo esc_html( 'Update payment method' ); ?></a></span></div>
        </div></div>
        <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'What gets charged' ); ?></h2></div><div class="ac-card-body">
            <div class="ac-tog-row"><div><div class="ac-tog-lbl"><?php echo esc_html( 'Sales via agent' ); ?></div><div class="ac-tog-desc"><?php echo esc_html( 'When agent generates a quote and buyer completes checkout' ); ?></div></div><span style="font-family:'DM Mono',monospace;font-size:11px;font-weight:500;color:var(--elec-dk)"><?php echo esc_html( $fee_rate ); ?></span></div>
            <div class="ac-tog-row"><div><div class="ac-tog-lbl"><?php echo esc_html( 'Free products' ); ?></div><div class="ac-tog-desc"><?php echo esc_html( '$0 transactions, always' ); ?></div></div><span class="ac-b ac-b-g"><?php echo esc_html( 'No charge' ); ?></span></div>
            <div class="ac-tog-row"><div><div class="ac-tog-lbl"><?php echo esc_html( 'Direct WooCommerce checkout' ); ?></div><div class="ac-tog-desc"><?php echo esc_html( "Sales that didn't go through the agent" ); ?></div></div><span class="ac-b ac-b-g"><?php echo esc_html( 'No charge' ); ?></span></div>
        </div></div>
    </div>

    <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Transaction history' ); ?></h2></div>
        <table class="ac-dt">
            <thead><tr><th><?php echo esc_html( 'Date' ); ?></th><th><?php echo esc_html( 'Product' ); ?></th><th><?php echo esc_html( 'Sale amount' ); ?></th><th><?php echo esc_html( 'AgentClerk fee' ); ?></th><th><?php echo esc_html( 'Buyer type' ); ?></th></tr></thead>
            <tbody id="ac-tx-tbody">
                <tr><td colspan="5" style="color:var(--text3)"><?php echo esc_html( 'Loading...' ); ?></td></tr>
            </tbody>
        </table>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
