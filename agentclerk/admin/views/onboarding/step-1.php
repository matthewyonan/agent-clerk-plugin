<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step current"><div class="ac-step-num">1</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">2</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">4</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <h1 class="ac-page-title"><?php echo esc_html( 'Welcome to AgentClerk' ); ?></h1>
    <p class="ac-page-subtitle"><?php echo esc_html( 'An AI seller agent for your store. You only pay when it makes a sale.' ); ?></p>

    <?php if ( isset( $_GET['turnkey_cancelled'] ) ) : ?>
        <div class="ac-callout ac-callout-amber"><span>&#9888;</span><span><?php echo esc_html( 'Payment was cancelled. Please try again.' ); ?></span></div>
    <?php endif; ?>

    <div class="ac-tier-grid" id="ac-tier-selection">
        <div class="ac-tier-card selected" id="ac-tc-byok" data-tier="byok">
            <div class="ac-tier-check">&#10003;</div>
            <div class="ac-tier-name"><?php echo esc_html( 'BYOK' ); ?></div>
            <div class="ac-tier-price"><?php echo esc_html( 'Free to install · no monthly fee' ); ?></div>
            <div style="margin-bottom:11px">
                <span class="ac-fee-pill"><?php echo esc_html( '1% or $1.00 min per sale' ); ?></span>
                <div class="ac-callout ac-callout-green" style="margin-top:7px;margin-bottom:0;font-size:12px;font-weight:700"><?php echo esc_html( 'Only charged on sales your agent closes — free products and all other WooCommerce sales are never charged.' ); ?></div>
            </div>
            <div class="ac-tier-feature"><?php echo esc_html( 'Bring your own Anthropic API key' ); ?></div>
            <div class="ac-tier-feature"><?php echo esc_html( 'Full seller agent, all features included' ); ?></div>
            <div class="ac-tier-feature"><?php echo esc_html( 'Best for developers and technical sellers' ); ?></div>
        </div>
        <div class="ac-tier-card" id="ac-tc-turnkey" data-tier="turnkey">
            <div class="ac-tier-check">&#10003;</div>
            <div class="ac-tier-name"><?php echo esc_html( 'TurnKey' ); ?></div>
            <div class="ac-tier-price"><?php echo esc_html( '$99 one-time setup · no monthly fee' ); ?></div>
            <div style="margin-bottom:11px">
                <span class="ac-fee-pill"><?php echo esc_html( '1.5% or $1.99 min per sale' ); ?></span>
                <div class="ac-callout ac-callout-green" style="margin-top:7px;margin-bottom:0;font-size:12px;font-weight:700"><?php echo esc_html( 'Only charged on sales your agent closes — free products and all other WooCommerce sales are never charged.' ); ?></div>
            </div>
            <div class="ac-tier-feature"><?php echo esc_html( 'No API key needed — we manage everything' ); ?></div>
            <div class="ac-tier-feature"><?php echo esc_html( 'Guided setup, no technical steps' ); ?></div>
            <div class="ac-tier-feature"><?php echo esc_html( 'Best for non-technical sellers' ); ?></div>
        </div>
    </div>

    <div class="ac-lifetime-cta" id="ac-lifetime-cta-bar">
        <span style="font-size:16px">&#9889;</span>
        <span style="flex:1;color:var(--ac-text)"><?php echo wp_kses( 'Pay once, sell forever. <strong style="color:var(--ac-electric-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale transaction fees.', array( 'strong' => array( 'style' => array() ) ) ); ?></span>
        <span class="ac-lifetime-btn"><?php echo esc_html( 'Upgrade' ); ?> &rarr;</span>
    </div>

    <div class="ac-card" id="ac-sec-byok">
        <div class="ac-card-head"><h2><?php echo esc_html( 'Enter your Anthropic API key' ); ?></h2></div>
        <div class="ac-card-body">
            <div class="ac-field-group">
                <label class="ac-label"><?php echo esc_html( 'API Key' ); ?></label>
                <div class="ac-flex">
                    <input type="password" id="ac-api-key" placeholder="<?php echo esc_attr( 'sk-ant-...' ); ?>" style="flex:1;font-family:'DM Mono',monospace;font-size:12px">
                    <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-show-key"><?php echo esc_html( 'Show' ); ?></button>
                    <button class="ac-btn ac-btn-primary ac-btn-sm" id="ac-validate-api-key"><?php echo esc_html( 'Validate' ); ?> &rarr;</button>
                </div>
                <div class="ac-note"><?php echo esc_html( 'Stored encrypted on your own server. AgentClerk never receives it.' ); ?><br><a href="<?php echo esc_url( 'https://console.anthropic.com' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'Get an API key from Anthropic' ); ?></a> &nbsp;&middot;&nbsp; <a href="<?php echo esc_url( 'https://docs.anthropic.com/en/api/getting-started' ); ?>" target="_blank">&rarr; <?php echo esc_html( 'How to get started (5 min)' ); ?></a></div>
            </div>
            <div id="ac-api-key-status-box"></div>
            <div class="ac-field-group" style="margin-top:16px">
                <label class="ac-label"><?php echo esc_html( 'Payment Method' ); ?></label>
                <div class="ac-note" style="margin-top:0;margin-bottom:8px"><?php echo esc_html( 'A card on file is required for transaction fee billing.' ); ?></div>
                <div id="ac-stripe-card-element" style="padding:10px;border:1px solid var(--ac-border2);border-radius:var(--ac-radius2)"></div>
                <div id="ac-stripe-card-errors" role="alert" style="color:var(--ac-red);font-size:12px;margin-top:4px"></div>
            </div>
            <button class="ac-btn ac-btn-electric" id="ac-submit-byok" disabled style="width:100%;justify-content:center;padding:12px;margin-top:8px"><?php echo esc_html( 'Scan my site and start setup' ); ?> &rarr;</button>
            <div class="ac-note" style="text-align:center;margin-top:6px"><?php echo esc_html( 'Takes 1-2 minutes' ); ?></div>
        </div>
    </div>

    <div class="ac-card" id="ac-sec-turnkey" style="display:none">
        <div class="ac-card-head"><h2><?php echo esc_html( 'Complete setup payment' ); ?></h2></div>
        <div class="ac-card-body">
            <p style="font-size:13px;color:var(--ac-text2);margin-bottom:13px"><?php echo esc_html( 'One-time $99 setup fee. No monthly charge. You pay 1.5% only when a sale closes through your agent.' ); ?></p>
            <div class="ac-field-group">
                <label class="ac-label"><?php echo esc_html( 'Payment Method' ); ?></label>
                <div id="ac-stripe-card-element-turnkey" style="padding:10px;border:1px solid var(--ac-border2);border-radius:var(--ac-radius2)"></div>
                <div id="ac-stripe-card-errors-turnkey" role="alert" style="color:var(--ac-red);font-size:12px;margin-top:4px"></div>
            </div>
            <button class="ac-btn ac-btn-electric" id="ac-submit-turnkey" style="width:100%;justify-content:center;padding:12px"><?php echo esc_html( 'Pay $99 and continue' ); ?> &rarr;</button>
            <div class="ac-note" style="text-align:center;margin-top:6px"><?php echo esc_html( 'Secured by Stripe.' ); ?></div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var selectedTier = 'byok', apiKeyValid = false, stripe, cardElement, cardElementTurnkey;
    if (typeof Stripe !== 'undefined' && typeof agentclerkStripe !== 'undefined') {
        stripe = Stripe(agentclerkStripe.publishableKey);
        var elements = stripe.elements();
        cardElement = elements.create('card');
        cardElementTurnkey = elements.create('card');
        cardElement.mount('#ac-stripe-card-element');
    }
    $('.ac-tier-card').on('click', function() {
        selectedTier = $(this).data('tier');
        $('.ac-tier-card').removeClass('selected'); $(this).addClass('selected');
        if (selectedTier === 'byok') { $('#ac-sec-byok').show(); $('#ac-sec-turnkey').hide(); if (cardElement) cardElement.mount('#ac-stripe-card-element'); }
        else { $('#ac-sec-byok').hide(); $('#ac-sec-turnkey').show(); if (cardElementTurnkey) cardElementTurnkey.mount('#ac-stripe-card-element-turnkey'); }
    });
    $('#ac-show-key').on('click', function() {
        var $i = $('#ac-api-key');
        if ($i.attr('type') === 'password') { $i.attr('type','text'); $(this).text('Hide'); } else { $i.attr('type','password'); $(this).text('Show'); }
    });
    $('#ac-validate-api-key').on('click', function() {
        var key = $('#ac-api-key').val(), $box = $('#ac-api-key-status-box');
        $box.html('<div class="ac-callout ac-callout-slate"><span class="ac-spinner"></span> <span>Validating API key...</span></div>');
        $.post(agentclerk.ajaxUrl, { action:'agentclerk_validate_api_key', nonce:agentclerk.nonce, api_key:key }, function(r) {
            if (r.success) { $box.html('<div class="ac-callout ac-callout-green"><span>&#10003;</span> <span>API key validated. Model access confirmed.</span></div>'); apiKeyValid=true; $('#ac-submit-byok').prop('disabled',false); }
            else { $box.html('<div class="ac-callout ac-callout-amber"><span>&#10008;</span> <span>' + $('<span>').text(r.data?r.data.message:'Invalid API key.').html() + '</span></div>'); apiKeyValid=false; $('#ac-submit-byok').prop('disabled',true); }
        });
    });
    $('#ac-submit-byok').on('click', function() {
        if (!apiKeyValid) return;
        var btn = $(this).prop('disabled',true).text('Processing...');
        if (stripe && cardElement) { stripe.createPaymentMethod({type:'card',card:cardElement}).then(function(r) { if(r.error){$('#ac-stripe-card-errors').text(r.error.message);btn.prop('disabled',false).html('Scan my site and start setup &rarr;');return;} submitReg('byok',r.paymentMethod.id,$('#ac-api-key').val()); }); }
        else { submitReg('byok','', $('#ac-api-key').val()); }
    });
    $('#ac-submit-turnkey').on('click', function() {
        var btn = $(this).prop('disabled',true).text('Processing...');
        if (stripe && cardElementTurnkey) { stripe.createPaymentMethod({type:'card',card:cardElementTurnkey}).then(function(r) { if(r.error){$('#ac-stripe-card-errors-turnkey').text(r.error.message);btn.prop('disabled',false).html('Pay $99 and continue &rarr;');return;} submitReg('turnkey',r.paymentMethod.id,''); }); }
        else { submitReg('turnkey','',''); }
    });
    function submitReg(tier,pmId,apiKey) {
        $.post(agentclerk.ajaxUrl, { action:'agentclerk_register_install', nonce:agentclerk.nonce, tier:tier, stripe_payment_method_id:pmId, api_key:apiKey }, function(r) {
            if (r.success) { window.location.href = r.data.redirect || agentclerk.ajaxUrl.replace('admin-ajax.php','admin.php?page=agentclerk-onboarding'); }
            else { alert(r.data?r.data.message:'Registration failed.'); $('.ac-btn').prop('disabled',false); }
        });
    }
});
</script>
