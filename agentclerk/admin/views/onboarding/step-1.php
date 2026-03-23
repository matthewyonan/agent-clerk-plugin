<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap agentclerk-onboarding">
    <h1>AgentClerk Setup — Step 1: Choose Your Plan</h1>
    <p>Select how you'd like to power your AI agent.</p>

    <?php if ( isset( $_GET['turnkey_cancelled'] ) ) : ?>
        <div class="notice notice-warning"><p>Payment was cancelled. Please try again.</p></div>
    <?php endif; ?>

    <div class="agentclerk-tier-cards" id="tier-selection">
        <div class="agentclerk-card" data-tier="byok">
            <h2>BYOK (Bring Your Own Key)</h2>
            <p class="price">$0/mo + 1% per sale</p>
            <ul>
                <li>Use your own Anthropic API key</li>
                <li>Pay only for what you use</li>
                <li>1% transaction fee (min $1.00)</li>
            </ul>
            <button class="button button-primary agentclerk-select-tier" data-tier="byok">Select BYOK</button>
        </div>

        <div class="agentclerk-card" data-tier="turnkey">
            <h2>TurnKey</h2>
            <p class="price">$99 setup + 1.5% per sale</p>
            <ul>
                <li>No API key needed — we handle everything</li>
                <li>One-time $99 setup fee</li>
                <li>1.5% transaction fee (min $1.99)</li>
            </ul>
            <button class="button button-primary agentclerk-select-tier" data-tier="turnkey">Select TurnKey</button>
        </div>
    </div>

    <div id="byok-form" style="display:none;" class="agentclerk-form-section">
        <h3>Enter Your Anthropic API Key</h3>
        <div class="agentclerk-field">
            <label for="api-key">API Key</label>
            <input type="password" id="api-key" class="regular-text" placeholder="sk-ant-..." />
            <button class="button" id="validate-api-key">Validate</button>
            <span id="api-key-status"></span>
        </div>

        <h3>Payment Method</h3>
        <p>A card on file is required for transaction fee billing.</p>
        <div id="stripe-card-element"></div>
        <div id="stripe-card-errors" role="alert"></div>

        <p><button class="button button-primary" id="submit-byok" disabled>Continue</button></p>
    </div>

    <div id="turnkey-form" style="display:none;" class="agentclerk-form-section">
        <h3>Payment Method</h3>
        <p>You'll be charged a one-time $99 setup fee, then transaction fees on sales.</p>
        <div id="stripe-card-element-turnkey"></div>
        <div id="stripe-card-errors-turnkey" role="alert"></div>

        <p><button class="button button-primary" id="submit-turnkey">Continue to Payment</button></p>
    </div>
</div>

<script>
jQuery(function($) {
    var selectedTier = '';
    var apiKeyValid = false;
    var stripe, cardElement, cardElementTurnkey;

    if (typeof Stripe !== 'undefined' && typeof agentclerkStripe !== 'undefined') {
        stripe = Stripe(agentclerkStripe.publishableKey);
        var elements = stripe.elements();
        cardElement = elements.create('card');
        cardElementTurnkey = elements.create('card');
    }

    $('.agentclerk-select-tier').on('click', function() {
        selectedTier = $(this).data('tier');
        $('.agentclerk-card').removeClass('selected');
        $(this).closest('.agentclerk-card').addClass('selected');

        $('#byok-form, #turnkey-form').hide();

        if (selectedTier === 'byok') {
            $('#byok-form').show();
            if (cardElement) cardElement.mount('#stripe-card-element');
        } else {
            $('#turnkey-form').show();
            if (cardElementTurnkey) cardElementTurnkey.mount('#stripe-card-element-turnkey');
        }
    });

    $('#validate-api-key').on('click', function() {
        var key = $('#api-key').val();
        $('#api-key-status').text('Validating...');
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_validate_api_key',
            nonce: agentclerk.nonce,
            api_key: key
        }, function(resp) {
            if (resp.success) {
                $('#api-key-status').html('<span style="color:green;">&#10004; Valid</span>');
                apiKeyValid = true;
                $('#submit-byok').prop('disabled', false);
            } else {
                $('#api-key-status').html('<span style="color:red;">&#10008; ' + resp.data.message + '</span>');
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
                    btn.prop('disabled', false).text('Continue');
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
                    btn.prop('disabled', false).text('Continue to Payment');
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
                $('.agentclerk-form-section button').prop('disabled', false);
            }
        });
    }
});
</script>
