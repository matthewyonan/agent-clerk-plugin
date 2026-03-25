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
            <div class="ac-pt"><?php echo esc_html( 'AgentClerk' ); ?></div>
            <div class="ac-ps"><?php echo esc_html( "Overview of your AI seller agent's performance." ); ?></div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; <?php echo esc_html( 'Live' ); ?></span>
            <span class="ac-b ac-b-s"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
            <?php if ( $license === 'active' ) : ?>
                <span class="ac-b ac-b-e"><?php echo esc_html( 'Lifetime' ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ac-stat-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-dash-convos-today">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Conversations today' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-dash-sales-today">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Sales today' ); ?></div>
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
        <div class="ac-ltm-cta" id="ac-lifetime-cta-bar">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--text)">
                <?php
                printf(
                    wp_kses(
                        'You\'ve accrued <strong>$%s</strong> in fees. <strong style="color:var(--elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.',
                        array( 'strong' => array( 'style' => array() ) )
                    ),
                    esc_html( number_format( $accrued_fees, 2 ) )
                );
                ?>
            </span>
            <span class="ac-ltm-btn" id="ac-lifetime-license-cta"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
        </div>
    <?php endif; ?>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
