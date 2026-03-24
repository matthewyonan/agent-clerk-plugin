<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$config    = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
$tier      = get_option( 'agentclerk_tier', 'byok' );
$license   = get_option( 'agentclerk_license_status', 'none' );
$pages     = get_pages();
$site_url  = get_site_url();
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt">Settings</div>
            <div class="ac-ps">Manage your agent, catalog, placement, and escalation.</div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; Live</span>
            <span class="ac-b ac-b-s"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
            <?php if ( $license === 'active' ) : ?><span class="ac-b ac-b-e">Lifetime</span><?php endif; ?>
        </div>
    </div>

    <div class="ac-stabs">
        <button class="ac-stab active" data-tab="tp-agent">Business &amp; Agent</button>
        <button class="ac-stab" data-tab="tp-catalog">Catalog</button>
        <button class="ac-stab" data-tab="tp-placement">Placement</button>
        <?php if ( $tier === 'byok' ) : ?>
            <button class="ac-stab" data-tab="tp-api">API Key</button>
        <?php endif; ?>
        <button class="ac-stab" data-tab="tp-support">Support &amp; Escalation</button>
    </div>

    <!-- Business & Agent -->
    <div class="ac-tp active" id="tp-agent">
        <div class="ac-g2">
            <div class="ac-card"><div class="ac-card-head"><h2>Business</h2></div><div class="ac-card-body">
                <div class="ac-fg"><label class="ac-fl">Site name</label><input type="text" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" readonly><div class="ac-fn">Pulled from WordPress. <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">Change in General Settings &rarr;</a></div></div>
                <div class="ac-fg"><label class="ac-fl">Business description (used by agent)</label><textarea id="s-biz-desc" style="min-height:70px"><?php echo esc_textarea( $config['business_desc'] ?? '' ); ?></textarea></div>
            </div></div>
            <div class="ac-card"><div class="ac-card-head"><h2>Agent</h2></div><div class="ac-card-body">
                <div class="ac-fg"><label class="ac-fl">Agent name (shown to buyers)</label><input type="text" id="s-agent-name" value="<?php echo esc_attr( $config['agent_name'] ?? 'AgentClerk' ); ?>"></div>
                <div class="ac-fg"><label class="ac-fl">Re-scan site</label>
                    <div class="ac-fr"><button class="ac-btn ac-btn-g ac-btn-sm" id="rescan-btn">&#8635; Scan now</button><span style="font-size:12px;color:var(--ac-text3)">Picks up new products and policy changes.</span></div>
                </div>
            </div></div>
        </div>
        <div class="ac-g2" style="margin-top:13px">
            <div class="ac-card"><div class="ac-card-head"><h2>Policies</h2></div><div class="ac-card-body">
                <div class="ac-fg"><label class="ac-fl">Refund policy</label><textarea id="s-policies-refund" rows="3"><?php echo esc_textarea( $config['policies']['refund'] ?? '' ); ?></textarea></div>
                <div class="ac-fg"><label class="ac-fl">License policy</label><textarea id="s-policies-license" rows="3"><?php echo esc_textarea( $config['policies']['license'] ?? '' ); ?></textarea></div>
                <div class="ac-fg"><label class="ac-fl">Delivery policy</label><textarea id="s-policies-delivery" rows="3"><?php echo esc_textarea( $config['policies']['delivery'] ?? '' ); ?></textarea></div>
            </div></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button class="ac-btn ac-btn-p" id="save-business">Save changes</button></div>
    </div>

    <!-- Catalog -->
    <div class="ac-tp" id="tp-catalog">
        <div class="ac-co bl ac-mb"><span class="ac-co-i">&#8505;</span><span>Edit product details in WooCommerce &mdash; changes sync here automatically. Only agent visibility is managed here.</span></div>
        <div id="catalog-settings">
            <div class="ac-card">
                <table class="ac-dt" id="settings-catalog-table">
                    <thead><tr><th>Product</th><th>Type</th><th>Price</th><th>WooCommerce</th><th>Agent can sell this</th></tr></thead>
                    <tbody>
                        <?php
                        if ( function_exists( 'wc_get_products' ) ) {
                            $wc_products = wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] );
                            $visibility  = $config['product_visibility'] ?? [];
                            foreach ( $wc_products as $p ) {
                                $checked = ! isset( $visibility[ $p->get_id() ] ) || $visibility[ $p->get_id() ];
                                $type_class = $p->get_type() === 'simple' ? 'ac-b-e' : 'ac-b-a';
                                echo '<tr>';
                                echo '<td style="font-weight:500">' . esc_html( $p->get_name() ) . '</td>';
                                echo '<td><span class="ac-b ' . esc_attr( $type_class ) . '">' . esc_html( ucfirst( $p->get_type() ) ) . '</span></td>';
                                echo '<td class="ac-mono">$' . esc_html( number_format( (float) $p->get_price(), 2 ) ) . '</td>';
                                echo '<td><span class="ac-b ac-b-g">Published</span></td>';
                                echo '<td><div class="ac-tog catalog-tog ' . ( $checked ? 'on' : '' ) . '" data-id="' . esc_attr( $p->get_id() ) . '"></div></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div style="text-align:right;margin-top:12px"><button class="ac-btn ac-btn-p" id="save-catalog">Save</button></div>
    </div>

    <!-- Placement -->
    <div class="ac-tp" id="tp-placement">
        <div class="ac-pl-grid ac-mb">
            <div class="ac-pl-card <?php echo ( $placement['widget'] ?? true ) ? 'on' : ''; ?>" id="s-pl-widget">
                <div class="ac-pl-card-icon">&#128172;</div>
                <div class="ac-pl-card-title">Floating widget</div>
                <div class="ac-pl-card-desc">All pages, bottom-right</div>
            </div>
            <div class="ac-pl-card <?php echo ( $placement['product_page'] ?? true ) ? 'on' : ''; ?>" id="s-pl-product">
                <div class="ac-pl-card-icon">&#128230;</div>
                <div class="ac-pl-card-title">Product pages</div>
                <div class="ac-pl-card-desc">Below Add to Cart</div>
            </div>
            <div class="ac-pl-card <?php echo ( $placement['clerk_page'] ?? true ) ? 'on' : ''; ?>" id="s-pl-clerk">
                <div class="ac-pl-card-icon">&#128279;</div>
                <div class="ac-pl-card-title">Dedicated /clerk page</div>
                <div class="ac-pl-card-desc"><?php echo esc_html( parse_url( $site_url, PHP_URL_HOST ) ); ?>/clerk</div>
            </div>
        </div>
        <div class="ac-card"><div class="ac-card-head"><h2>Widget settings</h2></div><div class="ac-card-body">
            <div class="ac-g2">
                <div class="ac-fg"><label class="ac-fl">Button label</label><input type="text" id="s-btn-label" value="<?php echo esc_attr( $placement['button_label'] ?? 'Ask AgentClerk' ); ?>"></div>
                <div class="ac-fg"><label class="ac-fl">Position</label><select id="s-position"><option value="bottom-right" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-right' ); ?>>Bottom right</option><option value="bottom-left" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-left' ); ?>>Bottom left</option></select></div>
            </div>
            <div class="ac-co gn" style="margin-bottom:0"><span class="ac-co-i">&#10003;</span><div>Manifest always active: <code style="font-family:'DM Mono',monospace;font-size:11px"><?php echo esc_html( $site_url ); ?>/ai-manifest.json</code></div></div>
        </div></div>
        <div style="text-align:right;margin-top:8px"><button class="ac-btn ac-btn-p" id="save-placement">Save</button></div>
    </div>

    <!-- API Key -->
    <?php if ( $tier === 'byok' ) : ?>
    <div class="ac-tp" id="tp-api">
        <div class="ac-card" style="max-width:500px"><div class="ac-card-head"><h2>Anthropic API Key</h2></div><div class="ac-card-body">
            <div class="ac-fg">
                <label class="ac-fl">API Key</label>
                <div class="ac-fr">
                    <input type="password" id="s-api-key" placeholder="sk-ant-..." style="flex:1;font-family:'DM Mono',monospace;font-size:12px">
                    <button class="ac-btn ac-btn-p ac-btn-sm" id="save-api-key">Update</button>
                </div>
                <div class="ac-fn"><a href="https://console.anthropic.com" target="_blank">&rarr; Anthropic console</a> &nbsp;&middot;&nbsp; <a href="https://docs.anthropic.com/en/api/getting-started" target="_blank">&rarr; API key guide</a></div>
            </div>
        </div></div>
    </div>
    <?php endif; ?>

    <!-- Support & Escalation -->
    <div class="ac-tp" id="tp-support">
        <div class="ac-g2">
            <div><div class="ac-card"><div class="ac-card-head"><h2>Support knowledge file</h2></div><div class="ac-card-body">
                <div class="ac-co sl ac-mb"><span class="ac-co-i">&#8505;</span><span>Edit directly, or type an instruction: e.g. "Add a question about version compatibility."</span></div>
                <textarea id="s-support-file" style="min-height:200px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $config['support_file'] ?? '' ); ?></textarea>
            </div></div></div>
            <div>
                <div class="ac-card ac-mb"><div class="ac-card-head"><h2>Escalation</h2></div><div class="ac-card-body">
                    <div class="ac-fg"><label class="ac-fl">Notification email</label><input type="email" id="s-escalation-email" value="<?php echo esc_attr( $config['escalation_email'] ?? '' ); ?>"></div>
                    <hr>
                    <div class="ac-fg" style="margin-bottom:0"><label class="ac-fl">Message shown to buyer when escalated</label><textarea id="s-escalation-msg" style="min-height:60px;font-size:12px"><?php echo esc_textarea( $config['escalation_message'] ?? '' ); ?></textarea></div>
                </div></div>
                <div class="ac-card"><div class="ac-card-head"><h2>Always escalate these topics</h2></div><div class="ac-card-body">
                    <div style="font-size:12px;color:var(--ac-text3);margin-bottom:9px">Agent never attempts to handle these.</div>
                    <div id="topics-list" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:9px">
                        <?php foreach ( ( $config['escalation_topics'] ?? [] ) as $topic ) : ?>
                            <span class="ac-b ac-b-s" style="cursor:pointer" data-topic="<?php echo esc_attr( $topic ); ?>"><?php echo esc_html( $topic ); ?> &times;</span>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" id="s-new-topic" placeholder="Add topic and press Enter&hellip;" style="font-size:12px">
                </div></div>
            </div>
        </div>
        <div style="text-align:right;margin-top:12px"><button class="ac-btn ac-btn-p" id="save-support">Save</button></div>
    </div>
</div>

<script>
jQuery(function($) {
    // Tab switching
    $('.ac-stab').on('click', function() {
        $('.ac-stab').removeClass('active');
        $(this).addClass('active');
        $('.ac-tp').removeClass('active');
        $('#' + $(this).data('tab')).addClass('active');
    });

    // Toggles
    $(document).on('click', '.ac-tog, .ac-pl-card', function() { $(this).toggleClass('on'); });

    // Topic tags
    $(document).on('click', '#topics-list .ac-b', function() { $(this).remove(); });
    $('#s-new-topic').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var val = $.trim($(this).val());
            if (!val) return;
            $('#topics-list').append('<span class="ac-b ac-b-s" style="cursor:pointer" data-topic="' + val + '">' + val + ' &times;</span>');
            $(this).val('');
        }
    });

    function showSaved() {
        var $n = $('<div class="ac-co gn" style="position:fixed;top:40px;right:20px;z-index:100002"><span class="ac-co-i">&#10003;</span><span>Settings saved.</span></div>');
        $('body').append($n);
        setTimeout(function() { $n.fadeOut(function() { $n.remove(); }); }, 2000);
    }

    $('#save-business').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_agent_config',
            nonce: agentclerk.nonce,
            agent_name: $('#s-agent-name').val(),
            business_desc: $('#s-biz-desc').val(),
            policies: JSON.stringify({
                refund: $('#s-policies-refund').val(),
                license: $('#s-policies-license').val(),
                delivery: $('#s-policies-delivery').val()
            })
        }, showSaved);
    });

    $('#save-catalog').on('click', function() {
        var vis = {};
        $('.catalog-tog').each(function() { vis[$(this).data('id')] = $(this).hasClass('on'); });
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_catalog',
            nonce: agentclerk.nonce,
            visibility: JSON.stringify(vis)
        }, showSaved);
    });

    $('#save-placement').on('click', function() {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_placement',
            nonce: agentclerk.nonce,
            widget: $('#s-pl-widget').hasClass('on') ? 1 : 0,
            product_page: $('#s-pl-product').hasClass('on') ? 1 : 0,
            clerk_page: $('#s-pl-clerk').hasClass('on') ? 1 : 0,
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
        var topics = [];
        $('#topics-list .ac-b').each(function() { topics.push($(this).data('topic')); });
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_agent_config',
            nonce: agentclerk.nonce,
            escalation_email: $('#s-escalation-email').val(),
            escalation_message: $('#s-escalation-msg').val(),
            escalation_topics: JSON.stringify(topics),
            support_file: $('#s-support-file').val()
        }, showSaved);
    });

    $('#rescan-btn').on('click', function() {
        $(this).text('Scanning...').prop('disabled', true);
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_start_scan', nonce: agentclerk.nonce }, function() {
            location.reload();
        });
    });
});
</script>
