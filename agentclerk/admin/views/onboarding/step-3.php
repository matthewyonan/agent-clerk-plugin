<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$agentclerk_scan_cache   = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );
$agentclerk_config       = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$agentclerk_products     = $agentclerk_scan_cache['products'] ?? array();
$agentclerk_policies     = $agentclerk_scan_cache['policies'] ?? array();
$agentclerk_gaps         = $agentclerk_scan_cache['gaps'] ?? array();
$agentclerk_support_file = $agentclerk_config['support_file'] ?? '';

if ( empty( $agentclerk_support_file ) && ! empty( $agentclerk_scan_cache ) && class_exists( 'AgentClerk_Scanner' ) ) {
    $agentclerk_support_file = AgentClerk_Scanner::build_support_file( $agentclerk_scan_cache );
}
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span><?php echo esc_html( 'Placement' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-pt"><?php echo esc_html( 'Review and fill any gaps' ); ?></div>
    <div class="ac-ps"><?php echo esc_html( "The scan found most of what's needed. The agent will ask about what it couldn't find automatically." ); ?></div>

    <div class="ac-g2">
        <div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'What the scan found' ); ?></h2></div>
                <div class="ac-card-body" style="padding:14px 17px">
                    <?php if ( ! empty( $agentclerk_products ) ) :
                        $agentclerk_names = array_map( function( $agentclerk_p ) { return esc_html( $agentclerk_p['name'] ) . ' $' . esc_html( number_format( (float) $agentclerk_p['price'], 0 ) ); }, array_slice( $agentclerk_products, 0, 3 ) );
                    ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( count( $agentclerk_products ) . ' products' ); ?></strong> &mdash; <?php echo wp_kses_post( implode( ', ', $agentclerk_names ) ); ?></div></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $agentclerk_policies['refund'] ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( 'Refund policy' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $agentclerk_policies['refund'], 10 ) ); ?></div></div>
                    <?php else : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( 'Refund policy' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $agentclerk_policies['license'] ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( 'License terms' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $agentclerk_policies['license'], 10 ) ); ?></div></div>
                    <?php else : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( 'License terms' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $agentclerk_policies['delivery'] ) ) : ?>
                        <div class="ac-finding"><span class="ac-finding-ok">&#10003;</span><div><strong><?php echo esc_html( 'Delivery' ); ?></strong> &mdash; <?php echo esc_html( wp_trim_words( $agentclerk_policies['delivery'], 10 ) ); ?></div></div>
                    <?php else : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( 'Delivery method' ); ?></strong> &mdash; <?php echo esc_html( 'not found' ); ?></div></div>
                    <?php endif; ?>
                    <?php foreach ( $agentclerk_gaps as $agentclerk_gap ) : ?>
                        <div class="ac-finding"><span class="ac-finding-warn">&#9888;</span><div><strong><?php echo esc_html( $agentclerk_gap ); ?></strong> &mdash; <?php echo esc_html( 'asking now' ); ?> &rarr;</div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ac-card">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Auto-drafted support file' ); ?></h2><span class="ac-b ac-b-a"><?php echo esc_html( 'Draft' ); ?></span></div>
                <div class="ac-card-body">
                    <div class="ac-co sl" style="margin-bottom:10px"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'Built from your product pages and blog content. Edit directly, or tell the agent what to change in the chat.' ); ?></span></div>
                    <textarea id="ac-support-file" style="min-height:130px;font-size:12px;font-family:'DM Mono',monospace;line-height:1.65"><?php echo esc_textarea( $agentclerk_support_file ); ?></textarea>
                    <div class="ac-fn"><?php echo esc_html( 'Refine this any time in Settings → Support & Escalation.' ); ?></div>
                </div>
            </div>
        </div>
        <div>
            <div class="ac-card" style="display:flex;flex-direction:column;height:560px;max-height:70vh">
                <div class="ac-card-head"><h2><?php echo esc_html( 'Fill the gaps' ); ?></h2><?php if ( ! empty( $agentclerk_gaps ) ) : ?><span class="ac-b ac-b-a"><?php echo esc_html( count( $agentclerk_gaps ) . ' questions' ); ?></span><?php endif; ?></div>
                <div class="ac-chat-shell" style="border:none;border-radius:0;flex:1;min-height:0">
                    <div class="ac-msgs" id="ac-chat-messages" style="overflow-y:auto"></div>
                    <div class="ac-chips-row" id="ac-chat-chips"></div>
                    <div class="ac-chat-inp-row">
                        <textarea class="ac-chat-inp" id="ac-chat-input" rows="1" placeholder="<?php echo esc_attr( 'Type or choose above…' ); ?>"></textarea>
                        <button class="ac-send-btn" id="ac-chat-send">&#10148;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ac-fr ac-mt">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-step3-continue"><?php echo esc_html( 'Continue to catalog' ); ?> &rarr;</button>
    </div>
    <script type="application/json" id="ac-gaps-data"><?php echo wp_json_encode( $agentclerk_gaps ); ?></script>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
