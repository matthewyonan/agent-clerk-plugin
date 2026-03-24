<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
$site_url  = get_site_url();
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-pt"><?php echo esc_html( 'Where should your agent appear?' ); ?></div>
    <div class="ac-ps"><?php echo esc_html( 'All three on by default. Change any time in Settings.' ); ?></div>

    <div class="ac-pl-grid ac-mb">
        <div class="ac-pl-card <?php echo ( $placement['widget'] ?? true ) ? 'on' : ''; ?>" id="ac-pl-widget">
            <div style="font-size:24px;margin-bottom:6px">&#128172;</div>
            <div style="font-size:12px;font-weight:500;margin-bottom:3px"><?php echo esc_html( 'Floating widget' ); ?></div>
            <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( 'Chat button on every page' ); ?></div>
        </div>
        <div class="ac-pl-card <?php echo ( $placement['product_page'] ?? true ) ? 'on' : ''; ?>" id="ac-pl-product">
            <div style="font-size:24px;margin-bottom:6px">&#128230;</div>
            <div style="font-size:12px;font-weight:500;margin-bottom:3px"><?php echo esc_html( 'Product pages' ); ?></div>
            <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( 'Below Add to Cart' ); ?></div>
        </div>
        <div class="ac-pl-card <?php echo ( $placement['clerk_page'] ?? true ) ? 'on' : ''; ?>" id="ac-pl-clerk">
            <div style="font-size:24px;margin-bottom:6px">&#128279;</div>
            <div style="font-size:12px;font-weight:500;margin-bottom:3px"><?php echo esc_html( 'Dedicated page' ); ?></div>
            <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) . '/clerk' ); ?></div>
        </div>
    </div>

    <div class="ac-card ac-mb">
        <div class="ac-card-head"><h2><?php echo esc_html( 'Widget appearance' ); ?></h2></div>
        <div class="ac-card-body">
            <div class="ac-g2">
                <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Button label' ); ?></label><input type="text" id="ac-button-label" value="<?php echo esc_attr( $placement['button_label'] ?? 'Get Help' ); ?>"></div>
                <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Agent name (visible to buyers)' ); ?></label><input type="text" id="ac-agent-name" value="<?php echo esc_attr( $placement['agent_name'] ?? 'AgentClerk' ); ?>"></div>
            </div>
        </div>
    </div>

    <div class="ac-co gn ac-mb"><span class="ac-co-i">&#10003;</span><div><?php printf( wp_kses( 'Always discoverable at <code style="font-family:\'DM Mono\',monospace;font-size:11px;background:var(--elec-lt);padding:1px 5px;border-radius:3px">%s/ai-manifest.json</code> — active regardless of widget placement.', array( 'code' => array( 'style' => array() ) ) ), esc_html( $site_url ) ); ?></div></div>

    <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-step5-continue"><?php echo esc_html( 'Test and go live' ); ?> &rarr;</button>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
