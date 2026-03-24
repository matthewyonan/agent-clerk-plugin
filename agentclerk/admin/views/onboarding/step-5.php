<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
$site_url  = get_site_url();
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Choose tier</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Scan site</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Review</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Catalog</span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">5</div><span>Placement</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">6</div><span>Go live</span></div>
    </div>

    <div class="ac-page-title">Where should your agent appear?</div>
    <div class="ac-page-subtitle">All three on by default. Change any time in Settings.</div>

    <div class="ac-placement-grid ac-mb">
        <div class="ac-placement-card <?php echo ( $placement['widget'] ?? true ) ? 'on' : ''; ?>" id="pl-widget">
            <div class="ac-placement-card-icon">&#128172;</div>
            <div class="ac-placement-card-title">Floating widget</div>
            <div class="ac-placement-card-desc">Chat button on every page</div>
        </div>
        <div class="ac-placement-card <?php echo ( $placement['product_page'] ?? true ) ? 'on' : ''; ?>" id="pl-product">
            <div class="ac-placement-card-icon">&#128230;</div>
            <div class="ac-placement-card-title">Product pages</div>
            <div class="ac-placement-card-desc">Below Add to Cart</div>
        </div>
        <div class="ac-placement-card <?php echo ( $placement['clerk_page'] ?? true ) ? 'on' : ''; ?>" id="pl-clerk">
            <div class="ac-placement-card-icon">&#128279;</div>
            <div class="ac-placement-card-title">Dedicated page</div>
            <div class="ac-placement-card-desc"><?php echo esc_html( parse_url( $site_url, PHP_URL_HOST ) ); ?>/clerk</div>
        </div>
    </div>

    <div class="ac-card ac-mb">
        <div class="ac-card-head"><h2>Widget appearance</h2></div>
        <div class="ac-card-body">
            <div class="ac-grid-2">
                <div class="ac-field-group">
                    <label class="ac-label">Button label</label>
                    <input type="text" id="button-label" value="<?php echo esc_attr( $placement['button_label'] ?? 'Ask AgentClerk' ); ?>">
                </div>
                <div class="ac-field-group">
                    <label class="ac-label">Agent name (visible to buyers)</label>
                    <input type="text" id="agent-name" value="<?php echo esc_attr( $placement['agent_name'] ?? 'AgentClerk' ); ?>">
                </div>
            </div>
            <div class="ac-field-group">
                <label class="ac-label">Position</label>
                <select id="widget-position">
                    <option value="bottom-right" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-right' ); ?>>Bottom right</option>
                    <option value="bottom-left" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-left' ); ?>>Bottom left</option>
                </select>
            </div>
        </div>
    </div>

    <div class="ac-callout ac-callout-green ac-mb"><span class="ac-callout-icon">&#10003;</span><div>Always discoverable at <code style="font-family:'DM Mono',monospace;font-size:11px;background:var(--ac-electric-lt);padding:1px 5px;border-radius:3px"><?php echo esc_html( $site_url ); ?>/ai-manifest.json</code> &mdash; active regardless of widget placement.</div></div>

    <button class="ac-btn ac-btn-electric ac-btn-lg" id="step5-continue">Test and go live &rarr;</button>
</div>

<script>
jQuery(function($) {
    $('.ac-placement-card').on('click', function() { $(this).toggleClass('on'); });

    $('#step5-continue').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_placement',
            nonce: agentclerk.nonce,
            widget: $('#pl-widget').hasClass('on') ? 1 : 0,
            product_page: $('#pl-product').hasClass('on') ? 1 : 0,
            clerk_page: $('#pl-clerk').hasClass('on') ? 1 : 0,
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
