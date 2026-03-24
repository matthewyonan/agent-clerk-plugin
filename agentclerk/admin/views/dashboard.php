<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$status       = get_option( 'agentclerk_plugin_status', 'active' );
$tier         = get_option( 'agentclerk_tier', 'byok' );
$license      = get_option( 'agentclerk_license_status', 'none' );
$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
?>
<div class="wrap ac-wrap">
    <div class="ac-flex-between ac-mb">
        <div>
            <h1 class="ac-page-title"><?php echo esc_html( 'AgentClerk' ); ?></h1>
            <p class="ac-page-subtitle"><?php echo esc_html( 'Overview of your AI seller agent\'s performance.' ); ?></p>
        </div>
        <div class="ac-flex">
            <span class="ac-badge ac-badge-green">&#9679; <?php echo esc_html( 'Live' ); ?></span>
            <span class="ac-badge ac-badge-slate"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
            <?php if ( $license === 'active' ) : ?>
                <span class="ac-badge ac-badge-electric"><?php echo esc_html( 'Lifetime' ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ac-stat-grid ac-stat-grid-4">
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-dash-convos-today">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Conversations today' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-dash-sales-today">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Sales today' ); ?></div>
            <div class="ac-stat-sub">$</div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-dash-total-convos">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Total conversations' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-dash-escalated">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Escalated' ); ?></div>
        </div>
    </div>

    <?php if ( $license !== 'active' && $accrued_fees > 0 ) : ?>
        <div class="ac-lifetime-cta" id="ac-lifetime-cta-bar">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--ac-text)">
                <?php
                printf(
                    /* translators: %s: accrued fee amount */
                    wp_kses(
                        __( 'You\'ve accrued <strong>$%s</strong> in fees. <strong style="color:var(--ac-electric-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.', 'agentclerk' ),
                        array(
                            'strong' => array( 'style' => array() ),
                        )
                    ),
                    esc_html( number_format( $accrued_fees, 2 ) )
                );
                ?>
            </span>
            <span class="ac-lifetime-btn" id="ac-lifetime-license-cta"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    $.post(agentclerk.ajaxUrl, {
        action: 'agentclerk_get_conversation_stats',
        nonce: agentclerk.nonce
    }, function(resp) {
        if (resp.success) {
            var d = resp.data;
            $('#ac-dash-convos-today').text(d.today);
            $('#ac-dash-sales-today').text('$' + parseFloat(d.sales_today || 0).toFixed(2));
            $('#ac-dash-total-convos').text(d.total);
            $('#ac-dash-escalated').text(d.escalated);
        }
    });

    $('#ac-lifetime-license-cta, #ac-lifetime-cta-bar').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_purchase_lifetime',
            nonce: agentclerk.nonce
        }, function(resp) {
            if (resp.success && resp.data.checkoutUrl) {
                window.location.href = resp.data.checkoutUrl;
            }
        });
    });
});
</script>
