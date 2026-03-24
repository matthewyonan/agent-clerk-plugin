<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
$site_url  = get_site_url();
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <h1 class="ac-page-title"><?php echo esc_html( 'Where should your agent appear?' ); ?></h1>
    <p class="ac-page-subtitle"><?php echo esc_html( 'All three on by default. Change any time in Settings.' ); ?></p>

    <div class="ac-placement-grid ac-mb">
        <div class="ac-placement-card <?php echo ( $placement['widget'] ?? true ) ? 'on' : ''; ?>" id="ac-pl-widget">
            <div style="font-size:24px;margin-bottom:6px">&#128172;</div>
            <div style="font-size:13px;font-weight:600"><?php echo esc_html( 'Floating widget' ); ?></div>
            <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( 'Chat button on every page' ); ?></div>
        </div>
        <div class="ac-placement-card <?php echo ( $placement['product_page'] ?? true ) ? 'on' : ''; ?>" id="ac-pl-product">
            <div style="font-size:24px;margin-bottom:6px">&#128230;</div>
            <div style="font-size:13px;font-weight:600"><?php echo esc_html( 'Product pages' ); ?></div>
            <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( 'Below Add to Cart' ); ?></div>
        </div>
        <div class="ac-placement-card <?php echo ( $placement['clerk_page'] ?? true ) ? 'on' : ''; ?>" id="ac-pl-clerk">
            <div style="font-size:24px;margin-bottom:6px">&#128279;</div>
            <div style="font-size:13px;font-weight:600"><?php echo esc_html( 'Dedicated page' ); ?></div>
            <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) . '/clerk' ); ?></div>
        </div>
    </div>

    <div class="ac-card ac-mb">
        <div class="ac-card-head"><h2><?php echo esc_html( 'Widget appearance' ); ?></h2></div>
        <div class="ac-card-body">
            <div class="ac-grid-2">
                <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Button label' ); ?></label><input type="text" id="ac-button-label" value="<?php echo esc_attr( $placement['button_label'] ?? 'Ask AgentClerk' ); ?>"></div>
                <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Agent name (visible to buyers)' ); ?></label><input type="text" id="ac-agent-name" value="<?php echo esc_attr( $placement['agent_name'] ?? 'AgentClerk' ); ?>"></div>
            </div>
        </div>
    </div>

    <div class="ac-callout ac-callout-green ac-mb"><span>&#10003;</span><div><?php printf( wp_kses( 'Always discoverable at <code style="font-family:\'DM Mono\',monospace;font-size:11px;background:var(--ac-electric-lt);padding:1px 5px;border-radius:3px">%s/ai-manifest.json</code> &mdash; active regardless of widget placement.', array( 'code' => array( 'style' => array() ) ) ), esc_html( $site_url ) ); ?></div></div>

    <button class="ac-btn ac-btn-electric" id="ac-step5-continue"><?php echo esc_html( 'Test and go live' ); ?> &rarr;</button>
</div>

<script>
jQuery(function($) {
    $('.ac-placement-card').on('click', function() { $(this).toggleClass('on'); });
    $('#ac-step5-continue').on('click', function() {
        $.post(agentclerk.ajaxUrl, { action:'agentclerk_save_placement', nonce:agentclerk.nonce, widget:$('#ac-pl-widget').hasClass('on')?1:0, product_page:$('#ac-pl-product').hasClass('on')?1:0, clerk_page:$('#ac-pl-clerk').hasClass('on')?1:0, button_label:$('#ac-button-label').val(), agent_name:$('#ac-agent-name').val() }, function() {
            $.post(agentclerk.ajaxUrl, {action:'agentclerk_save_onboarding_step',nonce:agentclerk.nonce,step:6}, function() { window.location.href=agentclerk.ajaxUrl.replace('admin-ajax.php','admin.php?page=agentclerk-onboarding'); });
        });
    });
});
</script>
