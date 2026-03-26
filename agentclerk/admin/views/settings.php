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
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt"><?php echo esc_html( 'Settings' ); ?></div>
            <div class="ac-ps"><?php echo esc_html( 'Manage your agent, catalog, placement, and escalation.' ); ?></div>
        </div>
        <div class="ac-fr">
            <span class="ac-b ac-b-g">&#9679; <?php echo esc_html( 'Live' ); ?></span>
            <span class="ac-b ac-b-s"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
            <?php if ( $license === 'active' ) : ?>
                <span class="ac-b ac-b-e"><?php echo esc_html( 'Lifetime' ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ac-stabs" id="ac-settings-tabs">
        <div class="ac-stab active" data-tab="ac-tp-agent"><?php echo esc_html( 'Business & Agent' ); ?></div>
        <div class="ac-stab" data-tab="ac-tp-catalog"><?php echo esc_html( 'Catalog' ); ?></div>
        <div class="ac-stab" data-tab="ac-tp-placement"><?php echo esc_html( 'Placement' ); ?></div>
        <?php if ( $tier === 'byok' ) : ?>
            <div class="ac-stab" data-tab="ac-tp-api"><?php echo esc_html( 'API Key' ); ?></div>
        <?php endif; ?>
        <div class="ac-stab" data-tab="ac-tp-support"><?php echo esc_html( 'Support & Escalation' ); ?></div>
    </div>

    <!-- Business & Agent tab -->
    <div class="ac-tp active" id="ac-tp-agent">
        <div class="ac-g2">
            <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Business' ); ?></h2></div><div class="ac-card-body">
                <div class="ac-fg">
                    <label class="ac-fl"><?php echo esc_html( 'Site name' ); ?></label>
                    <input type="text" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" readonly>
                    <div class="ac-fn"><?php printf( wp_kses( 'Pulled from WordPress. <a href="%s">Change in WordPress General Settings &rarr;</a>', array( 'a' => array( 'href' => array() ) ) ), esc_url( admin_url( 'options-general.php' ) ) ); ?></div>
                </div>
                <div class="ac-fg">
                    <label class="ac-fl"><?php echo esc_html( 'Business description (used by agent)' ); ?></label>
                    <textarea id="ac-s-biz-desc" style="min-height:70px"><?php echo esc_textarea( $config['business_desc'] ?? '' ); ?></textarea>
                </div>
            </div></div>
            <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Agent' ); ?></h2></div><div class="ac-card-body">
                <div class="ac-fg">
                    <label class="ac-fl"><?php echo esc_html( 'Agent name (shown to buyers)' ); ?></label>
                    <input type="text" id="ac-s-agent-name" value="<?php echo esc_attr( $config['agent_name'] ?? 'AgentClerk' ); ?>">
                </div>
                <div class="ac-fg">
                    <label class="ac-fl"><?php echo esc_html( 'Re-scan site' ); ?></label>
                    <div class="ac-fr">
                        <button class="ac-btn ac-btn-g ac-btn-sm" id="ac-rescan-btn">&#8635; <?php echo esc_html( 'Scan now' ); ?></button>
                        <span style="font-size:12px;color:var(--text3)"><?php echo $last_scan ? esc_html( sprintf( 'Last scanned: %s', $last_scan ) ) : ''; ?></span>
                    </div>
                    <div class="ac-fn"><?php echo esc_html( 'Run a new scan to pick up updated product descriptions or policy changes.' ); ?></div>
                </div>
            </div></div>
        </div>
        <div style="text-align:right"><button class="ac-btn ac-btn-p" id="ac-save-business"><?php echo esc_html( 'Save changes' ); ?></button></div>
        <div class="ac-card" style="margin-top:20px;border:1px solid var(--ac-border)">
            <div class="ac-card-head"><h2><?php echo esc_html( 'Restart Setup' ); ?></h2></div>
            <div class="ac-card-body">
                <div style="font-size:12px;color:var(--ac-text2);margin-bottom:10px"><?php echo esc_html( 'Re-run the onboarding wizard from step 1. Your existing settings will be preserved — only the setup status is reset.' ); ?></div>
                <button class="ac-btn ac-btn-g ac-btn-sm" id="ac-restart-setup">&#8635; <?php echo esc_html( 'Restart setup wizard' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Catalog tab -->
    <div class="ac-tp" id="ac-tp-catalog">
        <div class="ac-fb ac-mb">
            <span id="ac-catalog-count" style="font-size:13px;color:var(--text2)"></span>
            <div class="ac-fr">
                <button class="ac-btn ac-btn-g ac-btn-sm" id="ac-sync-wc">&#8635; <?php echo esc_html( 'Sync WooCommerce' ); ?></button>
                <button class="ac-btn ac-btn-p ac-btn-sm" id="ac-show-add-product">+ <?php echo esc_html( 'Add product' ); ?></button>
            </div>
        </div>
        <div class="ac-co bl ac-mb"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'Edit product details in WooCommerce — changes sync here automatically. Only agent visibility is managed here.' ); ?></span></div>
        <div class="ac-card"><table class="ac-dt">
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
                        $type_badge = ( $p->get_type() === 'simple' ) ? 'ac-b-e' : 'ac-b-a';
                        echo '<tr>';
                        echo '<td style="font-weight:500">' . esc_html( $p->get_name() ) . '</td>';
                        echo '<td><span class="ac-b ' . esc_attr( $type_badge ) . '">' . esc_html( ucfirst( $p->get_type() ) ) . '</span></td>';
                        echo '<td style="font-family:\'DM Mono\',monospace;font-size:12px">$' . esc_html( number_format( (float) $p->get_price(), 2 ) ) . '</td>';
                        echo '<td><span class="ac-b ac-b-g">' . esc_html( 'Published' ) . '</span></td>';
                        echo '<td><div class="ac-tog ac-catalog-toggle ' . ( $checked ? 'on' : '' ) . '" data-id="' . esc_attr( $p->get_id() ) . '"></div></td>';
                        echo '</tr>';
                    }
                }
                if ( 0 === $product_count ) {
                    echo '<tr><td colspan="5" style="color:var(--text3)">' . esc_html( 'No WooCommerce products found.' ) . '</td></tr>';
                }
                ?>
            </tbody>
        </table></div>
    </div>

    <!-- Placement tab -->
    <div class="ac-tp" id="ac-tp-placement">
        <div class="ac-pl-grid ac-mb">
            <div class="ac-pl-card <?php echo ( $placement['widget'] ?? true ) ? 'on' : ''; ?>" id="ac-s-pl-widget">
                <div style="font-size:24px;margin-bottom:6px">&#128172;</div>
                <div style="font-size:12px;font-weight:500;margin-bottom:3px"><?php echo esc_html( 'Floating widget' ); ?></div>
                <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( 'All pages, bottom-right' ); ?></div>
            </div>
            <div class="ac-pl-card <?php echo ( $placement['product_page'] ?? true ) ? 'on' : ''; ?>" id="ac-s-pl-product">
                <div style="font-size:24px;margin-bottom:6px">&#128230;</div>
                <div style="font-size:12px;font-weight:500;margin-bottom:3px"><?php echo esc_html( 'Product pages' ); ?></div>
                <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( 'Below Add to Cart' ); ?></div>
            </div>
            <div class="ac-pl-card <?php echo ( $placement['clerk_page'] ?? true ) ? 'on' : ''; ?>" id="ac-s-pl-clerk">
                <div style="font-size:24px;margin-bottom:6px">&#128279;</div>
                <div style="font-size:12px;font-weight:500;margin-bottom:3px"><?php echo esc_html( 'Dedicated /clerk page' ); ?></div>
                <div style="font-size:11px;color:var(--text3)"><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) . '/clerk' ); ?></div>
            </div>
        </div>
        <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Widget settings' ); ?></h2></div><div class="ac-card-body">
            <div class="ac-g2">
                <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Button label' ); ?></label><input type="text" id="ac-s-btn-label" value="<?php echo esc_attr( $placement['button_label'] ?? 'Get Help' ); ?>"></div>
                <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Position' ); ?></label><select id="ac-s-position"><option value="bottom-right" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-right' ); ?>><?php echo esc_html( 'Bottom right' ); ?></option><option value="bottom-left" <?php selected( $placement['position'] ?? 'bottom-right', 'bottom-left' ); ?>><?php echo esc_html( 'Bottom left' ); ?></option></select></div>
            </div>
            <div class="ac-co gn" style="margin-bottom:0"><span class="ac-co-i">&#10003;</span><div><?php printf( wp_kses( 'Manifest always active: <code style="font-family:\'DM Mono\',monospace;font-size:11px">%s/ai-manifest.json</code>', array( 'code' => array( 'style' => array() ) ) ), esc_html( $site_url ) ); ?></div></div>
        </div></div>
        <div style="text-align:right;margin-top:8px"><button class="ac-btn ac-btn-p" id="ac-save-placement"><?php echo esc_html( 'Save' ); ?></button></div>
    </div>

    <!-- API Key tab -->
    <?php if ( $tier === 'byok' ) : ?>
    <div class="ac-tp" id="ac-tp-api">
        <div class="ac-card" style="max-width:500px"><div class="ac-card-head"><h2><?php echo esc_html( 'Anthropic API Key' ); ?></h2><span class="ac-b ac-b-g">&#10003; <?php echo esc_html( 'Valid' ); ?></span></div><div class="ac-card-body">
            <div class="ac-fg">
                <label class="ac-fl"><?php echo esc_html( 'API Key' ); ?></label>
                <div class="ac-fr">
                    <input type="password" id="ac-s-api-key" placeholder="<?php echo esc_attr( 'sk-ant-...' ); ?>" style="flex:1;font-family:'DM Mono',monospace;font-size:12px">
                    <button class="ac-btn ac-btn-g ac-btn-sm" id="ac-show-api-key"><?php echo esc_html( 'Show' ); ?></button>
                    <button class="ac-btn ac-btn-p ac-btn-sm" id="ac-save-api-key"><?php echo esc_html( 'Update' ); ?></button>
                </div>
                <div class="ac-fn"><a href="<?php echo esc_url( 'https://console.anthropic.com' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'Anthropic console' ); ?></a> &nbsp;&middot;&nbsp; <a href="<?php echo esc_url( 'https://docs.anthropic.com/en/api/getting-started' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'API key guide' ); ?></a></div>
            </div>
            <div id="ac-api-key-validation-status"></div>
        </div></div>
    </div>
    <?php endif; ?>

    <!-- Support & Escalation tab -->
    <div class="ac-tp" id="ac-tp-support">
        <div class="ac-g2">
            <div><div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Support knowledge file' ); ?></h2><span class="ac-b ac-b-a"><?php echo esc_html( 'Draft' ); ?></span></div><div class="ac-card-body">
                <div class="ac-co sl ac-mb"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'Edit directly, or type an instruction in the chat: e.g. "Add a question about Figma version compatibility."' ); ?></span></div>
                <textarea id="ac-s-support-file" style="min-height:200px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $config['support_file'] ?? '' ); ?></textarea>
            </div></div></div>
            <div>
                <div class="ac-card ac-mb"><div class="ac-card-head"><h2><?php echo esc_html( 'Escalation' ); ?></h2></div><div class="ac-card-body">
                    <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Notify me when agent can\'t help a buyer' ); ?></label>
                        <select id="ac-s-notification-method">
                            <option value="both" <?php selected( $config['notification_method'] ?? 'both', 'both' ); ?>><?php echo esc_html( 'Both email and WP admin notification' ); ?></option>
                            <option value="email" <?php selected( $config['notification_method'] ?? 'both', 'email' ); ?>><?php echo esc_html( 'Email only' ); ?></option>
                            <option value="wp_admin" <?php selected( $config['notification_method'] ?? 'both', 'wp_admin' ); ?>><?php echo esc_html( 'WP admin only' ); ?></option>
                        </select>
                    </div>
                    <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Notification email' ); ?></label><input type="email" id="ac-s-escalation-email" value="<?php echo esc_attr( $config['escalation_email'] ?? '' ); ?>"></div>
                    <hr>
                    <div class="ac-fg" style="margin-bottom:0"><label class="ac-fl"><?php echo esc_html( 'Message shown to buyer when escalated' ); ?></label><textarea id="ac-s-escalation-msg" style="min-height:60px;font-size:12px"><?php echo esc_textarea( $config['escalation_message'] ?? '' ); ?></textarea></div>
                </div></div>
                <div class="ac-card"><div class="ac-card-head"><h2><?php echo esc_html( 'Always escalate these topics' ); ?></h2></div><div class="ac-card-body">
                    <div style="font-size:12px;color:var(--text3);margin-bottom:9px"><?php echo esc_html( 'Agent never attempts to handle these.' ); ?></div>
                    <div id="ac-topics-list" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:9px">
                        <?php foreach ( ( $config['escalation_topics'] ?? array() ) as $topic ) : ?>
                            <span class="ac-b ac-b-s" style="cursor:pointer" data-topic="<?php echo esc_attr( $topic ); ?>"><?php echo esc_html( $topic ); ?> &times;</span>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" id="ac-s-new-topic" placeholder="<?php echo esc_attr( 'Add topic and press Enter…' ); ?>" style="font-size:12px">
                </div></div>
            </div>
        </div>
        <div style="text-align:right"><button class="ac-btn ac-btn-p" id="ac-save-support"><?php echo esc_html( 'Save' ); ?></button></div>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
