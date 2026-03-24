<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$status       = get_option( 'agentclerk_plugin_status', 'active' );
$tier         = get_option( 'agentclerk_tier', 'byok' );
$license      = get_option( 'agentclerk_license_status', 'none' );
$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt">Dashboard</div>
            <div class="ac-ps">Overview of your AI seller agent's performance.</div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; Live</span>
            <span class="ac-b ac-b-s"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
            <?php if ( $license === 'active' ) : ?>
                <span class="ac-b ac-b-e">Lifetime</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ac-stat-grid ac-stat-grid-4" id="dashboard-stats">
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="stat-today">&mdash;</div>
            <div class="ac-stat-lbl">Conversations today</div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="stat-sales-today">&mdash;</div>
            <div class="ac-stat-lbl">Sales today</div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="stat-total">&mdash;</div>
            <div class="ac-stat-lbl">Total conversations</div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="stat-escalated">&mdash;</div>
            <div class="ac-stat-lbl">Escalated</div>
        </div>
    </div>

    <?php if ( $license !== 'active' && $accrued_fees > 0 ) : ?>
        <div class="ac-ltm-cta" id="lifetime-cta-bar">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--ac-text)">You've accrued <strong>$<?php echo esc_html( number_format( $accrued_fees, 2 ) ); ?></strong> in fees. <strong style="color:var(--ac-elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.</span>
            <span class="ac-ltm-btn" id="lifetime-license-cta">Upgrade &rarr;</span>
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

    $('#lifetime-license-cta, #lifetime-cta-bar').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_lifetime_checkout',
            nonce: agentclerk.nonce
        }, function(resp) {
            if (resp.success && resp.data.checkoutUrl) window.location.href = resp.data.checkoutUrl;
        });
    });
});
</script>
