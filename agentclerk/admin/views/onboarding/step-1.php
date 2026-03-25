<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step cur"><div class="ac-step-n">1</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">2</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span><?php echo esc_html( 'Placement' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-pt"><?php echo esc_html( 'Welcome to AgentClerk' ); ?></div>
    <div class="ac-ps"><?php echo esc_html( 'An AI seller agent for your store. You only pay when it makes a sale.' ); ?></div>

    <?php if ( isset( $_GET['turnkey_cancelled'] ) ) : ?>
        <div class="ac-co am"><span class="ac-co-i">&#9888;</span><span><?php echo esc_html( 'Payment was cancelled. Please try again.' ); ?></span></div>
    <?php endif; ?>

    <div class="ac-tier-grid" id="ac-tier-selection">
        <div class="ac-tier-card sel" id="ac-tc-byok" data-tier="byok">
            <div class="ac-sel-ring">&#10003;</div>
            <div class="ac-tier-name"><?php echo esc_html( 'BYOK' ); ?></div>
            <div class="ac-tier-price"><?php echo esc_html( 'Free to install · no monthly fee' ); ?></div>
            <div style="margin-bottom:11px">
                <span class="ac-fee-pill"><?php echo esc_html( '1% or $1.00 min per sale' ); ?></span>
                <div style="font-size:12px;font-weight:700;color:var(--elec-dk);margin-top:7px;padding:6px 9px;background:var(--elec-lt);border-radius:5px;border:1px solid #6EE7D7;line-height:1.4"><?php echo esc_html( 'Only charged on sales your agent closes — free products and all other WooCommerce sales are never charged.' ); ?></div>
            </div>
            <div class="ac-tier-f"><?php echo esc_html( 'Bring your own Anthropic API key' ); ?></div>
            <div class="ac-tier-f"><?php echo esc_html( 'Full seller agent, all features included' ); ?></div>
            <div class="ac-tier-f"><?php echo esc_html( 'Best for developers and technical sellers' ); ?></div>
        </div>
        <div class="ac-tier-card" id="ac-tc-turnkey" data-tier="turnkey">
            <div class="ac-sel-ring">&#10003;</div>
            <div class="ac-tier-name"><?php echo esc_html( 'TurnKey' ); ?></div>
            <div class="ac-tier-price"><?php echo esc_html( '$99 one-time setup · no monthly fee' ); ?></div>
            <div style="margin-bottom:11px">
                <span class="ac-fee-pill"><?php echo esc_html( '1.5% or $1.99 min per sale' ); ?></span>
                <div style="font-size:12px;font-weight:700;color:var(--elec-dk);margin-top:7px;padding:6px 9px;background:var(--elec-lt);border-radius:5px;border:1px solid #6EE7D7;line-height:1.4"><?php echo esc_html( 'Only charged on sales your agent closes — free products and all other WooCommerce sales are never charged.' ); ?></div>
            </div>
            <div class="ac-tier-f"><?php echo esc_html( 'No API key needed — we manage everything' ); ?></div>
            <div class="ac-tier-f"><?php echo esc_html( 'Guided setup, no technical steps' ); ?></div>
            <div class="ac-tier-f"><?php echo esc_html( 'Best for non-technical sellers' ); ?></div>
        </div>
    </div>

    <div class="ac-ltm-cta" id="ac-lifetime-cta-bar">
        <span style="font-size:16px">&#9889;</span>
        <span style="flex:1;color:var(--text)"><?php echo wp_kses( 'Pay once, sell forever. <strong style="color:var(--elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale transaction fees.', array( 'strong' => array( 'style' => array() ) ) ); ?></span>
        <span class="ac-ltm-btn"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
    </div>

    <div class="ac-card" id="ac-sec-byok">
        <div class="ac-card-head"><h2><?php echo esc_html( 'Enter your Anthropic API key' ); ?></h2></div>
        <div class="ac-card-body">
            <div class="ac-fg">
                <label class="ac-fl"><?php echo esc_html( 'API Key' ); ?></label>
                <div class="ac-fr">
                    <input type="password" id="ac-api-key" placeholder="<?php echo esc_attr( 'sk-ant-...' ); ?>" style="flex:1;font-family:'DM Mono',monospace;font-size:12px">
                    <button class="ac-btn ac-btn-g ac-btn-sm" id="ac-show-key"><?php echo esc_html( 'Show' ); ?></button>
                    <button class="ac-btn ac-btn-p ac-btn-sm" id="ac-validate-api-key"><?php echo esc_html( 'Validate' ); ?> &rarr;</button>
                </div>
                <div class="ac-fn"><?php echo esc_html( 'Stored encrypted on your own server. AgentClerk never receives it.' ); ?><br>
                    <a href="<?php echo esc_url( 'https://console.anthropic.com' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'Get an API key from Anthropic' ); ?></a> &nbsp;&middot;&nbsp;
                    <a href="<?php echo esc_url( 'https://docs.anthropic.com/en/api/getting-started' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'How to get started (5 min)' ); ?></a>
                </div>
            </div>
            <div id="ac-api-key-status-box"></div>
            <div class="ac-fg" style="margin-top:16px">
                <label class="ac-fl"><?php echo esc_html( 'Payment Method' ); ?></label>
                <div class="ac-fn" style="margin-top:0;margin-bottom:8px"><?php echo esc_html( 'A card on file is required for transaction fee billing.' ); ?></div>
                <div id="ac-stripe-card-element" style="padding:10px;border:1px solid var(--border2);border-radius:var(--r2)"></div>
                <div id="ac-stripe-card-errors" role="alert" style="color:#EF4444;font-size:12px;margin-top:4px"></div>
            </div>
        </div>
    </div>

    <div class="ac-card" id="ac-sec-turnkey" style="display:none">
        <div class="ac-card-head"><h2><?php echo esc_html( 'Complete setup payment' ); ?></h2></div>
        <div class="ac-card-body">
            <p style="font-size:13px;color:var(--text2);margin-bottom:13px"><?php echo esc_html( 'One-time $99 setup fee. No monthly charge. You pay 1.5% only when a sale closes through your agent.' ); ?></p>
            <div id="ac-stripe-card-element-turnkey" style="padding:10px;border:1px solid var(--border2);border-radius:var(--r2)"></div>
            <div id="ac-stripe-card-errors-turnkey" role="alert" style="color:#EF4444;font-size:12px;margin-top:4px"></div>
            <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-submit-turnkey" style="width:100%;justify-content:center;margin-top:10px"><?php echo esc_html( 'Pay $99 and continue' ); ?> &rarr;</button>
            <div class="ac-fn" style="text-align:center;margin-top:7px"><?php echo esc_html( 'Secured by Stripe.' ); ?></div>
        </div>
    </div>

    <div class="ac-fr">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-submit-byok"><?php echo esc_html( 'Scan my site and start setup' ); ?> &rarr;</button>
        <span style="font-size:12px;color:var(--text3)"><?php echo esc_html( 'Takes 1–2 minutes' ); ?></span>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
