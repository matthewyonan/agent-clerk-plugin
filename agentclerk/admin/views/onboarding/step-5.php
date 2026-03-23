<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
?>
<div class="wrap agentclerk-onboarding">
    <h1>AgentClerk Setup — Step 5: Placement</h1>
    <p>Choose where your AI agent appears on your site.</p>

    <div class="agentclerk-placement-cards">
        <div class="agentclerk-card">
            <h3>Floating Widget</h3>
            <p>A chat button that appears on every page of your store.</p>
            <label><input type="checkbox" id="placement-widget" <?php checked( $placement['widget'] ?? true ); ?> /> Enable</label>
        </div>

        <div class="agentclerk-card">
            <h3>Product Page Embed</h3>
            <p>Inline chat panel below the Add to Cart button on product pages.</p>
            <label><input type="checkbox" id="placement-product-page" <?php checked( $placement['product_page'] ?? true ); ?> /> Enable</label>
        </div>

        <div class="agentclerk-card">
            <h3>Dedicated /clerk Page</h3>
            <p>A full-page chat experience at your-store.com/clerk.</p>
            <label><input type="checkbox" id="placement-clerk-page" <?php checked( $placement['clerk_page'] ?? true ); ?> /> Enable</label>
        </div>
    </div>

    <div class="agentclerk-card" style="margin-top:20px;">
        <div class="agentclerk-field">
            <label for="button-label">Button Label</label>
            <input type="text" id="button-label" class="regular-text" value="<?php echo esc_attr( $placement['button_label'] ?? 'Get Help' ); ?>" />
        </div>
        <div class="agentclerk-field">
            <label for="agent-name">Agent Name</label>
            <input type="text" id="agent-name" class="regular-text" value="<?php echo esc_attr( $placement['agent_name'] ?? 'AgentClerk' ); ?>" />
        </div>
        <div class="agentclerk-field">
            <label for="widget-position">Widget Position</label>
            <select id="widget-position">
                <option value="bottom-right" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-right' ); ?>>Bottom Right</option>
                <option value="bottom-left" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-left' ); ?>>Bottom Left</option>
            </select>
        </div>
    </div>

    <p style="margin-top:20px;">
        <button class="button button-primary button-hero" id="step5-continue">Continue to Test</button>
    </p>
</div>

<script>
jQuery(function($) {
    $('#step5-continue').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_placement',
            nonce: agentclerk.nonce,
            widget: $('#placement-widget').is(':checked') ? 1 : 0,
            product_page: $('#placement-product-page').is(':checked') ? 1 : 0,
            clerk_page: $('#placement-clerk-page').is(':checked') ? 1 : 0,
            button_label: $('#button-label').val(),
            agent_name: $('#agent-name').val(),
            position: $('#widget-position').val()
        }, function() {
            $.post(agentclerk.ajaxUrl, {
                action: 'agentclerk_save_onboarding_step',
                nonce: agentclerk.nonce,
                step: 6
            }, function() {
                window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
            });
        });
    });
});
</script>
