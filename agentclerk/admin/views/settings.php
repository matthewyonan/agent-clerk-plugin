<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$config    = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
$tier      = get_option( 'agentclerk_tier', 'byok' );
$license   = get_option( 'agentclerk_license_status', 'none' );
$site_url  = get_site_url();
$last_scan = get_option( 'agentclerk_last_scan_date', '' );
?>
<div class="wrap ac-wrap">
    <div class="ac-flex-between ac-mb">
        <div>
            <h1 class="ac-page-title"><?php echo esc_html( 'Settings' ); ?></h1>
            <p class="ac-page-subtitle"><?php echo esc_html( 'Manage your agent, catalog, placement, and escalation.' ); ?></p>
        </div>
        <div class="ac-flex">
            <span class="ac-badge ac-badge-green">&#9679; <?php echo esc_html( 'Live' ); ?></span>
            <span class="ac-badge ac-badge-slate"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
            <?php if ( $license === 'active' ) : ?>
                <span class="ac-badge ac-badge-electric"><?php echo esc_html( 'Lifetime' ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ac-settings-tabs">
        <button class="ac-settings-tab active" data-tab="ac-tp-agent"><?php echo esc_html( 'Business & Agent' ); ?></button>
        <button class="ac-settings-tab" data-tab="ac-tp-catalog"><?php echo esc_html( 'Catalog' ); ?></button>
        <button class="ac-settings-tab" data-tab="ac-tp-placement"><?php echo esc_html( 'Placement' ); ?></button>
        <?php if ( $tier === 'byok' ) : ?>
            <button class="ac-settings-tab" data-tab="ac-tp-api"><?php echo esc_html( 'API Key' ); ?></button>
        <?php endif; ?>
        <button class="ac-settings-tab" data-tab="ac-tp-support"><?php echo esc_html( 'Support & Escalation' ); ?></button>
    </div>

    <div class="ac-tab-panel active" id="ac-tp-agent">
        <div class="ac-grid-2">
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Business' ); ?></h2></div>
                <div class="ac-card-body">
                    <div class="ac-field-group">
                        <label class="ac-label"><?php echo esc_html( 'Site name' ); ?></label>
                        <input type="text" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" readonly>
                        <div class="ac-note"><?php printf( wp_kses( 'Pulled from WordPress. <a href="%s">Change in General Settings &rarr;</a>', array( 'a' => array( 'href' => array() ) ) ), esc_url( admin_url( 'options-general.php' ) ) ); ?></div>
                    </div>
                    <div class="ac-field-group">
                        <label class="ac-label"><?php echo esc_html( 'Business description (used by agent)' ); ?></label>
                        <textarea id="ac-s-biz-desc" style="min-height:70px"><?php echo esc_textarea( $config['business_desc'] ?? '' ); ?></textarea>
                    </div>
                </div>
            </div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Agent' ); ?></h2></div>
                <div class="ac-card-body">
                    <div class="ac-field-group">
                        <label class="ac-label"><?php echo esc_html( 'Agent name (shown to buyers)' ); ?></label>
                        <input type="text" id="ac-s-agent-name" value="<?php echo esc_attr( $config['agent_name'] ?? 'AgentClerk' ); ?>">
                    </div>
                    <div class="ac-field-group">
                        <label class="ac-label"><?php echo esc_html( 'Re-scan site' ); ?></label>
                        <div class="ac-flex">
                            <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-rescan-btn">&#8635; <?php echo esc_html( 'Scan now' ); ?></button>
                            <span style="font-size:11px;color:var(--ac-text3)"><?php echo $last_scan ? esc_html( sprintf( 'Last scanned %s', $last_scan ) ) : esc_html( 'Picks up new products and policy changes.' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div style="text-align:right;margin-top:12px"><button class="ac-btn ac-btn-primary" id="ac-save-business"><?php echo esc_html( 'Save changes' ); ?></button></div>
    </div>

    <div class="ac-tab-panel" id="ac-tp-catalog">
        <div class="ac-flex-between ac-mb">
            <span id="ac-catalog-count" style="font-size:13px;color:var(--ac-text2)"></span>
            <div class="ac-flex">
                <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-sync-wc">&#8635; <?php echo esc_html( 'Sync WooCommerce' ); ?></button>
                <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-show-add-product">+ <?php echo esc_html( 'Add product' ); ?></button>
            </div>
        </div>
        <div class="ac-callout ac-callout-blue ac-mb"><span>&#8505;</span><span><?php echo esc_html( 'Edit product details in WooCommerce — changes sync here automatically. Only agent visibility is managed here.' ); ?></span></div>
        <div class="ac-card">
            <table class="ac-table" id="ac-settings-catalog-table">
                <thead><tr><th><?php echo esc_html( 'Product' ); ?></th><th><?php echo esc_html( 'Type' ); ?></th><th><?php echo esc_html( 'Price' ); ?></th><th><?php echo esc_html( 'WooCommerce' ); ?></th><th><?php echo esc_html( 'Agent can sell this' ); ?></th></tr></thead>
                <tbody>
                    <?php
                    $product_count = 0;
                    if ( function_exists( 'wc_get_products' ) ) {
                        $wc_products   = wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) );
                        $visibility    = $config['product_visibility'] ?? array();
                        $product_count = count( $wc_products );
                        foreach ( $wc_products as $p ) {
                            $checked    = ! isset( $visibility[ $p->get_id() ] ) || $visibility[ $p->get_id() ];
                            $type_class = ( $p->get_type() === 'simple' ) ? 'ac-badge-electric' : 'ac-badge-amber';
                            echo '<tr>';
                            echo '<td style="font-weight:500">' . esc_html( $p->get_name() ) . '</td>';
                            echo '<td><span class="ac-badge ' . esc_attr( $type_class ) . '">' . esc_html( ucfirst( $p->get_type() ) ) . '</span></td>';
                            echo '<td style="font-family:\'DM Mono\',monospace">$' . esc_html( number_format( (float) $p->get_price(), 2 ) ) . '</td>';
                            echo '<td><span class="ac-badge ac-badge-green">' . esc_html( 'Published' ) . '</span></td>';
                            echo '<td><div class="ac-toggle ac-catalog-toggle ' . ( $checked ? 'on' : '' ) . '" data-id="' . esc_attr( $p->get_id() ) . '"></div></td>';
                            echo '</tr>';
                        }
                    }
                    if ( 0 === $product_count ) {
                        echo '<tr><td colspan="5" style="color:var(--ac-text3)">' . esc_html( 'No WooCommerce products found.' ) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ac-tab-panel" id="ac-tp-placement">
        <div class="ac-placement-grid ac-mb">
            <div class="ac-placement-card <?php echo ( $placement['widget'] ?? true ) ? 'on' : ''; ?>" id="ac-s-pl-widget">
                <div style="font-size:24px;margin-bottom:6px">&#128172;</div>
                <div style="font-size:13px;font-weight:600"><?php echo esc_html( 'Floating widget' ); ?></div>
                <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( 'All pages, bottom-right' ); ?></div>
            </div>
            <div class="ac-placement-card <?php echo ( $placement['product_page'] ?? true ) ? 'on' : ''; ?>" id="ac-s-pl-product">
                <div style="font-size:24px;margin-bottom:6px">&#128230;</div>
                <div style="font-size:13px;font-weight:600"><?php echo esc_html( 'Product pages' ); ?></div>
                <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( 'Below Add to Cart' ); ?></div>
            </div>
            <div class="ac-placement-card <?php echo ( $placement['clerk_page'] ?? true ) ? 'on' : ''; ?>" id="ac-s-pl-clerk">
                <div style="font-size:24px;margin-bottom:6px">&#128279;</div>
                <div style="font-size:13px;font-weight:600"><?php echo esc_html( 'Dedicated /clerk page' ); ?></div>
                <div style="font-size:11px;color:var(--ac-text3)"><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) . '/clerk' ); ?></div>
            </div>
        </div>
        <div class="ac-card">
            <div class="ac-card-head"><h2><?php echo esc_html( 'Widget settings' ); ?></h2></div>
            <div class="ac-card-body">
                <div class="ac-grid-2">
                    <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Button label' ); ?></label><input type="text" id="ac-s-btn-label" value="<?php echo esc_attr( $placement['button_label'] ?? 'Ask AgentClerk' ); ?>"></div>
                    <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Position' ); ?></label><select id="ac-s-position"><option value="bottom-right" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-right' ); ?>><?php echo esc_html( 'Bottom right' ); ?></option><option value="bottom-left" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-left' ); ?>><?php echo esc_html( 'Bottom left' ); ?></option></select></div>
                </div>
                <div class="ac-callout ac-callout-green" style="margin-bottom:0"><span>&#10003;</span><div><?php printf( wp_kses( 'Manifest always active: <code style="font-family:\'DM Mono\',monospace;font-size:11px">%s/ai-manifest.json</code>', array( 'code' => array( 'style' => array() ) ) ), esc_html( $site_url ) ); ?></div></div>
            </div>
        </div>
        <div style="text-align:right;margin-top:8px"><button class="ac-btn ac-btn-primary" id="ac-save-placement"><?php echo esc_html( 'Save' ); ?></button></div>
    </div>

    <?php if ( $tier === 'byok' ) : ?>
    <div class="ac-tab-panel" id="ac-tp-api">
        <div class="ac-card" style="max-width:500px">
            <div class="ac-card-head"><h2><?php echo esc_html( 'Anthropic API Key' ); ?></h2></div>
            <div class="ac-card-body">
                <div class="ac-field-group">
                    <label class="ac-label"><?php echo esc_html( 'API Key' ); ?></label>
                    <div class="ac-flex">
                        <input type="password" id="ac-s-api-key" placeholder="<?php echo esc_attr( 'sk-ant-...' ); ?>" style="flex:1;font-family:'DM Mono',monospace;font-size:12px">
                        <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-show-api-key"><?php echo esc_html( 'Show' ); ?></button>
                        <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-validate-api-key"><?php echo esc_html( 'Validate' ); ?></button>
                        <button class="ac-btn ac-btn-primary ac-btn-sm" id="ac-save-api-key"><?php echo esc_html( 'Update' ); ?></button>
                    </div>
                    <div class="ac-note"><a href="<?php echo esc_url( 'https://console.anthropic.com' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'Anthropic console' ); ?></a> &nbsp;&middot;&nbsp; <a href="<?php echo esc_url( 'https://docs.anthropic.com/en/api/getting-started' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'API key guide' ); ?></a></div>
                </div>
                <div id="ac-api-key-validation-status"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="ac-tab-panel" id="ac-tp-support">
        <div class="ac-grid-2">
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Support knowledge file' ); ?></h2></div>
                    <div class="ac-card-body">
                        <div class="ac-callout ac-callout-slate ac-mb"><span>&#8505;</span><span><?php echo esc_html( 'Edit directly, or type an instruction: e.g. "Add a question about version compatibility."' ); ?></span></div>
                        <textarea id="ac-s-support-file" style="min-height:200px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $config['support_file'] ?? '' ); ?></textarea>
                    </div>
                </div>
            </div>
            <div>
                <div class="ac-card ac-mb">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Escalation' ); ?></h2></div>
                    <div class="ac-card-body">
                        <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Notification method' ); ?></label>
                            <select id="ac-s-notification-method">
                                <option value="email" <?php selected( $config['notification_method'] ?? 'email', 'email' ); ?>><?php echo esc_html( 'Email' ); ?></option>
                                <option value="wp_admin" <?php selected( $config['notification_method'] ?? 'email', 'wp_admin' ); ?>><?php echo esc_html( 'WP admin notification' ); ?></option>
                                <option value="both" <?php selected( $config['notification_method'] ?? 'email', 'both' ); ?>><?php echo esc_html( 'Both email and WP notification' ); ?></option>
                            </select>
                        </div>
                        <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Notification email' ); ?></label><input type="email" id="ac-s-escalation-email" value="<?php echo esc_attr( $config['escalation_email'] ?? '' ); ?>"></div>
                        <hr style="border:none;border-top:1px solid var(--ac-border);margin:13px 0">
                        <div class="ac-field-group" style="margin-bottom:0"><label class="ac-label"><?php echo esc_html( 'Message shown to buyer when escalated' ); ?></label><textarea id="ac-s-escalation-msg" style="min-height:60px;font-size:12px"><?php echo esc_textarea( $config['escalation_message'] ?? '' ); ?></textarea></div>
                    </div>
                </div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Always escalate these topics' ); ?></h2></div>
                    <div class="ac-card-body">
                        <div style="font-size:12px;color:var(--ac-text3);margin-bottom:9px"><?php echo esc_html( 'Agent never attempts to handle these.' ); ?></div>
                        <div id="ac-topics-list" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:9px">
                            <?php foreach ( ( $config['escalation_topics'] ?? array() ) as $topic ) : ?>
                                <span class="ac-badge ac-badge-slate" style="cursor:pointer" data-topic="<?php echo esc_attr( $topic ); ?>"><?php echo esc_html( $topic ); ?> &times;</span>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" id="ac-s-new-topic" placeholder="<?php echo esc_attr( 'Add topic and press Enter...' ); ?>" style="font-size:12px">
                    </div>
                </div>
            </div>
        </div>
        <div style="text-align:right;margin-top:12px"><button class="ac-btn ac-btn-primary" id="ac-save-support"><?php echo esc_html( 'Save' ); ?></button></div>
    </div>
</div>

<script>
jQuery(function($) {
    $('.ac-settings-tab').on('click', function() {
        $('.ac-settings-tab').removeClass('active');
        $(this).addClass('active');
        $('.ac-tab-panel').removeClass('active');
        $('#' + $(this).data('tab')).addClass('active');
    });

    $(document).on('click', '.ac-toggle, .ac-placement-card', function() { $(this).toggleClass('on'); });

    $(document).on('click', '#ac-topics-list .ac-badge', function() { $(this).remove(); });
    $('#ac-s-new-topic').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var val = $.trim($(this).val());
            if (!val) return;
            $('#ac-topics-list').append('<span class="ac-badge ac-badge-slate" style="cursor:pointer" data-topic="' + $('<span>').text(val).html() + '">' + $('<span>').text(val).html() + ' &times;</span>');
            $(this).val('');
        }
    });

    function showSaved() {
        var $t = $('<div class="ac-toast success show">Settings saved.</div>');
        $('body').append($t);
        setTimeout(function() { $t.removeClass('show'); setTimeout(function() { $t.remove(); }, 400); }, 2000);
    }

    $('#ac-save-business').on('click', function() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_save_agent_config', nonce: agentclerk.nonce, agent_name: $('#ac-s-agent-name').val(), business_desc: $('#ac-s-biz-desc').val() }, showSaved);
    });

    $(document).on('click', '.ac-catalog-toggle', function() {
        var vis = {};
        $('.ac-catalog-toggle').each(function() { vis[$(this).data('id')] = $(this).hasClass('on'); });
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_save_catalog', nonce: agentclerk.nonce, visibility: JSON.stringify(vis) });
    });

    $('#ac-sync-wc').on('click', function() {
        $(this).text('Syncing...').prop('disabled', true);
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_rescan', nonce: agentclerk.nonce }, function() { location.reload(); });
    });

    $('#ac-save-placement').on('click', function() {
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_save_placement', nonce: agentclerk.nonce, widget: $('#ac-s-pl-widget').hasClass('on') ? 1 : 0, product_page: $('#ac-s-pl-product').hasClass('on') ? 1 : 0, clerk_page: $('#ac-s-pl-clerk').hasClass('on') ? 1 : 0, button_label: $('#ac-s-btn-label').val(), position: $('#ac-s-position').val() }, showSaved);
    });

    $('#ac-show-api-key').on('click', function() {
        var $inp = $('#ac-s-api-key');
        if ($inp.attr('type') === 'password') { $inp.attr('type', 'text'); $(this).text('Hide'); }
        else { $inp.attr('type', 'password'); $(this).text('Show'); }
    });

    $('#ac-validate-api-key').on('click', function() {
        var key = $('#ac-s-api-key').val();
        if (!key) return;
        var $box = $('#ac-api-key-validation-status');
        $box.html('<div class="ac-callout ac-callout-slate"><span class="ac-spinner"></span> <span>Validating...</span></div>');
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_validate_api_key', nonce: agentclerk.nonce, api_key: key }, function(resp) {
            if (resp.success) { $box.html('<div class="ac-callout ac-callout-green"><span>&#10003;</span> <span>API key validated.</span></div>'); }
            else { $box.html('<div class="ac-callout ac-callout-amber"><span>&#10008;</span> <span>' + $('<span>').text(resp.data ? resp.data.message : 'Invalid API key.').html() + '</span></div>'); }
        });
    });

    $('#ac-save-api-key').on('click', function() {
        var key = $('#ac-s-api-key').val();
        if (!key) { alert('Enter an API key.'); return; }
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_save_settings', nonce: agentclerk.nonce, tab: 'api_key', api_key: key }, showSaved);
    });

    $('#ac-save-support').on('click', function() {
        var topics = [];
        $('#ac-topics-list .ac-badge').each(function() { topics.push($(this).data('topic')); });
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_save_agent_config', nonce: agentclerk.nonce, escalation_email: $('#ac-s-escalation-email').val(), escalation_message: $('#ac-s-escalation-msg').val(), escalation_topics: JSON.stringify(topics), notification_method: $('#ac-s-notification-method').val(), support_file: $('#ac-s-support-file').val() }, showSaved);
    });

    $('#ac-rescan-btn').on('click', function() {
        $(this).text('Scanning...').prop('disabled', true);
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_rescan', nonce: agentclerk.nonce }, function() { location.reload(); });
    });

    var count = $('.ac-catalog-toggle').length;
    if (count > 0) { $('#ac-catalog-count').text(count + ' products from WooCommerce'); }
});
</script>
