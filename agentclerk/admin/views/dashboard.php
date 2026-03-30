<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$ac_status       = get_option( 'agentclerk_plugin_status', 'active' );
$ac_tier         = get_option( 'agentclerk_tier', 'byok' );
$ac_license      = get_option( 'agentclerk_license_status', 'none' );
$ac_accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt"><?php echo esc_html( 'AgentClerk' ); ?></div>
            <div class="ac-ps"><?php echo esc_html( "Overview of your AI seller agent's performance." ); ?></div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; <?php echo esc_html( 'Live' ); ?></span>
            <span class="ac-b ac-b-s"><?php echo esc_html( strtoupper( $ac_tier ) ); ?></span>
            <?php if ( $ac_license === 'active' ) : ?>
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

    <?php if ( $ac_license !== 'active' && $ac_accrued_fees > 0 ) : ?>
        <div class="ac-ltm-cta" id="ac-lifetime-cta-bar">
            <span style="font-size:16px">&#9889;</span>
            <span style="flex:1;color:var(--text)">
                <?php
                printf(
                    wp_kses(
                        'You\'ve accrued <strong>$%s</strong> in fees. <strong style="color:var(--elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale fees permanently.',
                        array( 'strong' => array( 'style' => array() ) )
                    ),
                    esc_html( number_format( $ac_accrued_fees, 2 ) )
                );
                ?>
            </span>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                <a href="#" class="ac-promo-toggle" style="font-size:12px;color:var(--ac-text2);white-space:nowrap"><?php echo esc_html( 'Promo code?' ); ?></a>
                <input type="text" id="ac-promo-dash" class="ac-promo-input" placeholder="<?php echo esc_attr( 'Code' ); ?>" style="display:none;width:100px;font-size:12px;font-family:'DM Mono',monospace;padding:6px 10px;text-transform:uppercase">
                <span class="ac-ltm-btn" id="ac-lifetime-license-cta"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
            </div>
        </div>
    <?php endif; ?>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
