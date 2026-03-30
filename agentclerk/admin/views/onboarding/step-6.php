<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$ac_config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$ac_scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

$ac_config_fields = array( 'agent_name', 'business_name', 'business_desc', 'escalation_email', 'escalation_message' );
$ac_filled = 0;
foreach ( $ac_config_fields as $ac_f ) {
    if ( ! empty( $ac_config[ $ac_f ] ) ) { $ac_filled++; }
}
$ac_business_score = count( $ac_config_fields ) > 0 ? (int) round( ( $ac_filled / count( $ac_config_fields ) ) * 100 ) : 0;

$ac_products   = $ac_scan_cache['products'] ?? array();
$ac_visibility = $ac_config['product_visibility'] ?? array();
$ac_visible    = 0;
foreach ( $ac_products as $ac_p ) {
    if ( ! isset( $ac_visibility[ $ac_p['id'] ] ) || $ac_visibility[ $ac_p['id'] ] ) { $ac_visible++; }
}
$ac_catalog_score = count( $ac_products ) > 0 ? (int) round( ( $ac_visible / count( $ac_products ) ) * 100 ) : 0;

$ac_policies  = $ac_config['policies'] ?? array();
$ac_pol_count = 0;
if ( ! empty( $ac_policies['refund'] ) ) { $ac_pol_count++; }
if ( ! empty( $ac_policies['license'] ) ) { $ac_pol_count++; }
if ( ! empty( $ac_policies['delivery'] ) ) { $ac_pol_count++; }
$ac_policy_score = (int) round( ( $ac_pol_count / 3 ) * 100 );

$ac_support_len   = strlen( $ac_config['support_file'] ?? '' );
$ac_support_score = min( 100, (int) round( ( $ac_support_len / 200 ) * 100 ) );

if ( ! function_exists( 'ac_readiness_color' ) ) {
    function ac_readiness_color( $score ) {
        return $score >= 75 ? 'var(--green)' : 'var(--amber)';
    }
}
?>
<div class="wrap ac-wrap">
    <div class="ac-pt"><?php echo esc_html( 'Test your agent before going live' ); ?></div>
    <div class="ac-ps"><?php echo esc_html( 'Try it as a buyer would. No transaction is processed in test mode.' ); ?></div>

    <div class="ac-g2">
        <div>
            <div class="ac-chat-shell" style="height:400px;max-height:60vh">
                <div class="ac-chat-hd">
                    <div class="ac-chat-av">&#9889;</div>
                    <div>
                        <div class="ac-chat-nm"><?php echo esc_html( 'AgentClerk' ); ?> <span style="font-size:10px;background:rgba(245,158,11,0.2);color:var(--amber);padding:1px 6px;border-radius:3px;font-family:'DM Mono',monospace"><?php echo esc_html( 'TEST' ); ?></span></div>
                        <div class="ac-chat-st">&#9679; <?php echo esc_html( 'Ready' ); ?></div>
                    </div>
                </div>
                <div class="ac-msgs" id="ac-test-msgs"></div>
                <div class="ac-chat-inp-row">
                    <textarea class="ac-chat-inp" id="ac-test-input" rows="1" placeholder="<?php echo esc_attr( 'Ask as a buyer would…' ); ?>"></textarea>
                    <button class="ac-send-btn" id="ac-test-send">&#10148;</button>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:7px">
                <span class="ac-chip" data-q="<?php echo esc_attr( 'What products do you have?' ); ?>"><?php echo esc_html( 'What products do you have?' ); ?></span>
                <span class="ac-chip" data-q="<?php echo esc_attr( "What's your refund policy?" ); ?>"><?php echo esc_html( "What's your refund policy?" ); ?></span>
                <span class="ac-chip" data-q="<?php echo esc_attr( 'I need help choosing' ); ?>"><?php echo esc_html( 'I need help choosing' ); ?></span>
            </div>
        </div>
        <div>
            <div class="ac-card ac-mb">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Readiness' ); ?></h2></div>
                <div class="ac-card-body">
                    <div class="ac-sc-row"><div class="ac-sc-lbl"><?php echo esc_html( 'Business context' ); ?></div><div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo (int) $ac_business_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $ac_business_score ) ); ?>"></div></div><div class="ac-sc-val" style="color:<?php echo esc_attr( ac_readiness_color( $ac_business_score ) ); ?>"><?php echo (int) $ac_business_score; ?>%</div></div>
                    <div class="ac-sc-row"><div class="ac-sc-lbl"><?php echo esc_html( 'Catalog' ); ?></div><div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo (int) $ac_catalog_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $ac_catalog_score ) ); ?>"></div></div><div class="ac-sc-val" style="color:<?php echo esc_attr( ac_readiness_color( $ac_catalog_score ) ); ?>"><?php echo (int) $ac_catalog_score; ?>%</div></div>
                    <div class="ac-sc-row"><div class="ac-sc-lbl"><?php echo esc_html( 'Policies' ); ?></div><div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo (int) $ac_policy_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $ac_policy_score ) ); ?>"></div></div><div class="ac-sc-val" style="color:<?php echo esc_attr( ac_readiness_color( $ac_policy_score ) ); ?>"><?php echo (int) $ac_policy_score; ?>%</div></div>
                    <div class="ac-sc-row"><div class="ac-sc-lbl"><?php echo esc_html( 'Support file' ); ?></div><div class="ac-sc-track"><div class="ac-sc-fill" style="width:<?php echo (int) $ac_support_score; ?>%;background:<?php echo esc_attr( ac_readiness_color( $ac_support_score ) ); ?>"></div></div><div class="ac-sc-val" style="color:<?php echo esc_attr( ac_readiness_color( $ac_support_score ) ); ?>"><?php echo (int) $ac_support_score; ?>%</div></div>
                    <?php if ( $ac_support_score < 75 ) : ?>
                        <hr>
                        <div class="ac-co am" style="margin-bottom:0"><span class="ac-co-i">&#9888;</span><span><?php echo esc_html( 'Support file is a draft. Improve it any time in Settings → Support & Escalation.' ); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ac-card">
                <div class="ac-card-body" style="text-align:center;padding:20px 17px">
                    <div style="font-size:26px;margin-bottom:7px">&#128640;</div>
                    <div style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:5px"><?php echo esc_html( 'Ready to go live' ); ?></div>
                    <div style="font-size:12px;color:var(--text3);margin-bottom:13px"><?php echo esc_html( 'Agent activates across all placements immediately.' ); ?></div>
                    <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-go-live" style="width:100%"><?php echo esc_html( 'Go live' ); ?> &rarr;</button>
                    <div style="font-size:11px;color:var(--text3);margin-top:8px"><?php echo esc_html( 'Adjust everything in Settings at any time' ); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
