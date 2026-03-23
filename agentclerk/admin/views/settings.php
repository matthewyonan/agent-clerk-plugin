<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$config    = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
$tier      = get_option( 'agentclerk_tier', 'byok' );
$pages     = get_pages();
?>
<div class="wrap agentclerk-settings">
    <h1>Settings</h1>

    <h2 class="nav-tab-wrapper">
        <a href="#tab-business" class="nav-tab nav-tab-active" data-tab="business">Business &amp; Agent</a>
        <a href="#tab-catalog" class="nav-tab" data-tab="catalog">Catalog</a>
        <a href="#tab-placement" class="nav-tab" data-tab="placement">Placement</a>
        <?php if ( $tier === 'byok' ) : ?>
            <a href="#tab-api-key" class="nav-tab" data-tab="api_key">API Key</a>
        <?php endif; ?>
        <a href="#tab-support" class="nav-tab" data-tab="support">Support &amp; Escalation</a>
    </h2>

    <!-- Business & Agent Tab -->
    <div id="tab-business" class="agentclerk-tab-content">
        <table class="form-table">
            <tr>
                <th><label for="s-agent-name">Agent Name</label></th>
                <td><input type="text" id="s-agent-name" class="regular-text" value="<?php echo esc_attr( $config['agent_name'] ?? 'AgentClerk' ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="s-biz-name">Business Name</label></th>
                <td><input type="text" id="s-biz-name" class="regular-text" value="<?php echo esc_attr( $config['business_name'] ?? '' ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="s-biz-desc">Business Description</label></th>
                <td><textarea id="s-biz-desc" rows="3" class="large-text"><?php echo esc_textarea( $config['business_desc'] ?? '' ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="s-policies-refund">Refund Policy</label></th>
                <td><textarea id="s-policies-refund" rows="3" class="large-text"><?php echo esc_textarea( $config['policies']['refund'] ?? '' ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="s-policies-license">License Policy</label></th>
                <td><textarea id="s-policies-license" rows="3" class="large-text"><?php echo esc_textarea( $config['policies']['license'] ?? '' ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="s-policies-delivery">Delivery Policy</label></th>
                <td><textarea id="s-policies-delivery" rows="3" class="large-text"><?php echo esc_textarea( $config['policies']['delivery'] ?? '' ); ?></textarea></td>
            </tr>
        </table>
        <button class="button button-primary" id="save-business">Save Changes</button>
    </div>

    <!-- Catalog Tab -->
    <div id="tab-catalog" class="agentclerk-tab-content" style="display:none;">
        <p>Manage product visibility for your AI agent.</p>
        <div id="catalog-settings"></div>
        <button class="button button-primary" id="save-catalog">Save Changes</button>
    </div>

    <!-- Placement Tab -->
    <div id="tab-placement" class="agentclerk-tab-content" style="display:none;">
        <table class="form-table">
            <tr><th>Floating Widget</th><td><label><input type="checkbox" id="s-widget" <?php checked( $placement['widget'] ?? true ); ?> /> Enable</label></td></tr>
            <tr><th>Product Page Embed</th><td><label><input type="checkbox" id="s-product-page" <?php checked( $placement['product_page'] ?? true ); ?> /> Enable</label></td></tr>
            <tr><th>/clerk Page</th><td><label><input type="checkbox" id="s-clerk-page" <?php checked( $placement['clerk_page'] ?? true ); ?> /> Enable</label></td></tr>
            <tr><th>Button Label</th><td><input type="text" id="s-btn-label" class="regular-text" value="<?php echo esc_attr( $placement['button_label'] ?? 'Get Help' ); ?>" /></td></tr>
            <tr><th>Position</th><td>
                <select id="s-position">
                    <option value="bottom-right" <?php selected( $placement['position'] ?? '', 'bottom-right' ); ?>>Bottom Right</option>
                    <option value="bottom-left" <?php selected( $placement['position'] ?? '', 'bottom-left' ); ?>>Bottom Left</option>
                </select>
            </td></tr>
        </table>
        <button class="button button-primary" id="save-placement">Save Changes</button>
    </div>

    <!-- API Key Tab -->
    <?php if ( $tier === 'byok' ) : ?>
    <div id="tab-api-key" class="agentclerk-tab-content" style="display:none;">
        <table class="form-table">
            <tr>
                <th><label for="s-api-key">Anthropic API Key</label></th>
                <td>
                    <input type="password" id="s-api-key" class="regular-text" placeholder="sk-ant-..." />
                    <p class="description">Enter a new key to update. Leave blank to keep existing.</p>
                </td>
            </tr>
        </table>
        <button class="button button-primary" id="save-api-key">Update API Key</button>
    </div>
    <?php endif; ?>

    <!-- Support & Escalation Tab -->
    <div id="tab-support" class="agentclerk-tab-content" style="display:none;">
        <table class="form-table">
            <tr>
                <th><label for="s-escalation-email">Escalation Email</label></th>
                <td><input type="email" id="s-escalation-email" class="regular-text" value="<?php echo esc_attr( $config['escalation_email'] ?? '' ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="s-escalation-msg">Escalation Message (shown to buyer)</label></th>
                <td><textarea id="s-escalation-msg" rows="2" class="large-text"><?php echo esc_textarea( $config['escalation_message'] ?? '' ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="s-escalation-topics">Escalation Topics (comma-separated)</label></th>
                <td><input type="text" id="s-escalation-topics" class="large-text" value="<?php echo esc_attr( implode( ', ', $config['escalation_topics'] ?? [] ) ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="s-support-page">Support Page</label></th>
                <td>
                    <select id="s-support-page">
                        <option value="">None</option>
                        <?php foreach ( $pages as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $config['support_page_id'] ?? 0, $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="s-support-file">Support Knowledge File</label></th>
                <td><textarea id="s-support-file" rows="10" class="large-text"><?php echo esc_textarea( $config['support_file'] ?? '' ); ?></textarea></td>
            </tr>
        </table>
        <button class="button button-primary" id="save-support">Save Changes</button>
    </div>
</div>

<script>
jQuery(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.agentclerk-tab-content').hide();
        $('#tab-' + $(this).data('tab')).show();
    });

    function showSaved() {
        var $n = $('<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>');
        $('.agentclerk-settings h1').after($n);
        setTimeout(function() { $n.fadeOut(); }, 2000);
    }

    $('#save-business').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_agent_config',
            nonce: agentclerk.nonce,
            agent_name: $('#s-agent-name').val(),
            business_name: $('#s-biz-name').val(),
            business_desc: $('#s-biz-desc').val(),
            policies: JSON.stringify({
                refund: $('#s-policies-refund').val(),
                license: $('#s-policies-license').val(),
                delivery: $('#s-policies-delivery').val()
            })
        }, showSaved);
    });

    $('#save-placement').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_placement',
            nonce: agentclerk.nonce,
            widget: $('#s-widget').is(':checked') ? 1 : 0,
            product_page: $('#s-product-page').is(':checked') ? 1 : 0,
            clerk_page: $('#s-clerk-page').is(':checked') ? 1 : 0,
            button_label: $('#s-btn-label').val(),
            position: $('#s-position').val()
        }, showSaved);
    });

    $('#save-api-key').on('click', function() {
        var key = $('#s-api-key').val();
        if (!key) { alert('Enter an API key.'); return; }
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_settings',
            nonce: agentclerk.nonce,
            tab: 'api_key',
            api_key: key
        }, showSaved);
    });

    $('#save-support').on('click', function() {
        var topics = $('#s-escalation-topics').val().split(',').map(function(t) { return t.trim(); }).filter(Boolean);
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_agent_config',
            nonce: agentclerk.nonce,
            escalation_email: $('#s-escalation-email').val(),
            escalation_message: $('#s-escalation-msg').val(),
            escalation_topics: JSON.stringify(topics),
            support_file: $('#s-support-file').val(),
            support_page_id: $('#s-support-page').val()
        }, showSaved);
    });
});
</script>
