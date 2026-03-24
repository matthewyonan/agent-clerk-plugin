<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step cur"><div class="ac-step-n">1</div><span>Choose tier</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">2</div><span>Scan site</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">3</div><span>Review</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span>Catalog</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span>Placement</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">6</div><span>Go live</span></div>
    </div>

    <div class="ac-pt">Welcome to AgentClerk</div>
    <div class="ac-ps">An AI seller agent for your store. You only pay when it makes a sale.</div>

    <?php if ( isset( $_GET['turnkey_cancelled'] ) ) : ?>
        <div class="ac-co am"><span class="ac-co-i">&#9888;</span><span>Payment was cancelled. Please try again.</span></div>
    <?php endif; ?>

    <div class="ac-tier-grid" id="tier-selection">
        <div class="ac-tier-card sel" id="tc-byok" data-tier="byok">
            <div class="ac-sel-ring">&#10003;</div>
            <div class="ac-tier-name">BYOK</div>
            <div class="ac-tier-price">Free to install &middot; no monthly fee</div>
            <div style="margin-bottom:11px">
                <span class="ac-fee-pill">1% or $1.00 min per sale</span>
                <div style="font-size:12px;font-weight:700;color:var(--ac-elec-dk);margin-top:7px;padding:6px 9px;background:var(--ac-elec-lt);border-radius:5px;border:1px solid #6EE7D7;line-height:1.4">Only charged on sales your agent closes &mdash; free products and all other WooCommerce sales are never charged.</div>
            </div>
            <div class="ac-tier-f">Bring your own Anthropic API key</div>
            <div class="ac-tier-f">Full seller agent, all features included</div>
            <div class="ac-tier-f">Best for developers and technical sellers</div>
        </div>

        <div class="ac-tier-card" id="tc-tk" data-tier="turnkey">
            <div class="ac-sel-ring">&#10003;</div>
            <div class="ac-tier-name">TurnKey</div>
            <div class="ac-tier-price">$99 one-time setup &middot; no monthly fee</div>
            <div style="margin-bottom:11px">
                <span class="ac-fee-pill">1.5% or $1.99 min per sale</span>
                <div style="font-size:12px;font-weight:700;color:var(--ac-elec-dk);margin-top:7px;padding:6px 9px;background:var(--ac-elec-lt);border-radius:5px;border:1px solid #6EE7D7;line-height:1.4">Only charged on sales your agent closes &mdash; free products and all other WooCommerce sales are never charged.</div>
            </div>
            <div class="ac-tier-f">No API key needed &mdash; we manage everything</div>
            <div class="ac-tier-f">Guided setup, no technical steps</div>
            <div class="ac-tier-f">Best for non-technical sellers</div>
        </div>
    </div>

    <div class="ac-ltm-cta" id="lifetime-cta-bar">
        <span style="font-size:16px">&#9889;</span>
        <span style="flex:1;color:var(--ac-text)">Pay once, sell forever. <strong style="color:var(--ac-elec-dk)">Lifetime license &mdash; $49</strong> eliminates all per-sale transaction fees.</span>
        <span class="ac-ltm-btn">Upgrade &rarr;</span>
    </div>

    <div class="ac-card" id="sec-byok">
        <div class="ac-card-head"><h2>Enter your Anthropic API key</h2></div>
        <div class="ac-card-body">
            <div class="ac-fg">
                <label class="ac-fl">API Key</label>
                <div class="ac-fr">
                    <input type="password" id="api-key" placeholder="sk-ant-..." style="flex:1;font-family:'DM Mono',monospace;font-size:12px">
                    <button class="ac-btn ac-btn-p ac-btn-sm" id="validate-api-key">Validate &rarr;</button>
                </div>
                <div class="ac-fn">Stored encrypted on your own server. AgentClerk never receives it.<br>
                    <a href="https://console.anthropic.com" target="_blank">&rarr; Get an API key from Anthropic</a> &nbsp;&middot;&nbsp;
                    <a href="https://docs.anthropic.com/en/api/getting-started" target="_blank">&rarr; How to get started (5 min)</a>
                </div>
            </div>
            <div id="api-key-status-box"></div>

            <div class="ac-fg" style="margin-top:16px">
                <label class="ac-fl">Payment Method</label>
                <div class="ac-fn" style="margin-top:0;margin-bottom:8px">A card on file is required for transaction fee billing.</div>
                <div id="stripe-card-element" style="padding:10px;border:1px solid var(--ac-border2);border-radius:var(--ac-r2)"></div>
                <div id="stripe-card-errors" role="alert" style="color:#EF4444;font-size:12px;margin-top:4px"></div>
            </div>

            <button class="ac-btn ac-btn-e ac-btn-lg" id="submit-byok" disabled>Scan my site and start setup &rarr;</button>
        </div>
    </div>

    <div class="ac-card" id="sec-tk" style="display:none">
        <div class="ac-card-head"><h2>Complete setup payment</h2></div>
        <div class="ac-card-body">
            <p style="font-size:13px;color:var(--ac-text2);margin-bottom:13px">One-time $99 setup fee. No monthly charge. You pay 1.5% only when a sale closes through your agent.</p>

            <div class="ac-fg">
                <label class="ac-fl">Payment Method</label>
                <div id="stripe-card-element-turnkey" style="padding:10px;border:1px solid var(--ac-border2);border-radius:var(--ac-r2)"></div>
                <div id="stripe-card-errors-turnkey" role="alert" style="color:#EF4444;font-size:12px;margin-top:4px"></div>
            </div>

            <button class="ac-btn ac-btn-e ac-btn-lg" id="submit-turnkey">Pay $99 and continue &rarr;</button>
            <div class="ac-fn" style="margin-top:7px">Secured by Stripe.</div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var selectedTier = 'byok';
    var apiKeyValid = false;
    var stripe, cardElement, cardElementTurnkey;

    if (typeof Stripe !== 'undefined' && typeof agentclerkStripe !== 'undefined') {
        stripe = Stripe(agentclerkStripe.publishableKey);
        var elements = stripe.elements();
        cardElement = elements.create('card');
        cardElementTurnkey = elements.create('card');
        cardElement.mount('#stripe-card-element');
    }

    $('.ac-tier-card').on('click', function() {
        selectedTier = $(this).data('tier');
        $('.ac-tier-card').removeClass('sel');
        $(this).addClass('sel');

        if (selectedTier === 'byok') {
            $('#sec-byok').show();
            $('#sec-tk').hide();
            if (cardElement) cardElement.mount('#stripe-card-element');
        } else {
            $('#sec-byok').hide();
            $('#sec-tk').show();
            if (cardElementTurnkey) cardElementTurnkey.mount('#stripe-card-element-turnkey');
        }
    });

    $('#validate-api-key').on('click', function() {
        var key = $('#api-key').val();
        var box = $('#api-key-status-box');
        box.html('<div class="ac-co sl"><span class="ac-co-i"><span class="ac-spinner"></span></span><span>Validating API key...</span></div>');

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_validate_api_key',
            nonce: agentclerk.nonce,
            api_key: key
        }, function(resp) {
            if (resp.success) {
                box.html('<div class="ac-co gn"><span class="ac-co-i">&#10003;</span><span>API key validated. Model access confirmed.</span></div>');
                apiKeyValid = true;
                $('#submit-byok').prop('disabled', false);
            } else {
                box.html('<div class="ac-co am"><span class="ac-co-i">&#10008;</span><span>' + (resp.data.message || 'Invalid API key.') + '</span></div>');
                apiKeyValid = false;
                $('#submit-byok').prop('disabled', true);
            }
        });
    });

    $('#submit-byok').on('click', function() {
        if (!apiKeyValid) return;
        var btn = $(this).prop('disabled', true).text('Processing...');

        if (stripe && cardElement) {
            stripe.createPaymentMethod({ type: 'card', card: cardElement }).then(function(result) {
                if (result.error) {
                    $('#stripe-card-errors').text(result.error.message);
                    btn.prop('disabled', false).text('Scan my site and start setup →');
                    return;
                }
                submitRegistration('byok', result.paymentMethod.id, $('#api-key').val());
            });
        } else {
            submitRegistration('byok', '', $('#api-key').val());
        }
    });

    $('#submit-turnkey').on('click', function() {
        var btn = $(this).prop('disabled', true).text('Processing...');

        if (stripe && cardElementTurnkey) {
            stripe.createPaymentMethod({ type: 'card', card: cardElementTurnkey }).then(function(result) {
                if (result.error) {
                    $('#stripe-card-errors-turnkey').text(result.error.message);
                    btn.prop('disabled', false).text('Pay $99 and continue →');
                    return;
                }
                submitRegistration('turnkey', result.paymentMethod.id, '');
            });
        } else {
            submitRegistration('turnkey', '', '');
        }
    });

    function submitRegistration(tier, paymentMethodId, apiKey) {
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_register_install',
            nonce: agentclerk.nonce,
            tier: tier,
            stripe_payment_method_id: paymentMethodId,
            api_key: apiKey
        }, function(resp) {
            if (resp.success) {
                if (resp.data.redirect) {
                    window.location.href = resp.data.redirect;
                } else {
                    window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
                }
            } else {
                alert(resp.data.message || 'Registration failed.');
                $('.ac-btn').prop('disabled', false);
            }
        });
    }
});
</script>
