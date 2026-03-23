<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
$card_last4   = get_option( 'agentclerk_billing_card_last4', '' );
?>
<div class="wrap agentclerk-suspended">
    <div class="agentclerk-card" style="max-width:600px;margin:40px auto;text-align:center;">
        <h1 style="color:#d32f2f;">Account Suspended</h1>
        <p>Your AgentClerk account has been suspended due to a billing issue. All buyer-facing agent services are currently disabled.</p>

        <?php if ( $accrued_fees > 0 ) : ?>
            <div style="margin:20px 0;padding:15px;background:#fff3cd;border-radius:6px;">
                <h3>Outstanding Fees: $<?php echo esc_html( number_format( $accrued_fees, 2 ) ); ?></h3>
                <?php if ( $card_last4 ) : ?>
                    <p>Card on file: **** <?php echo esc_html( $card_last4 ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p>Please update your payment method to restore service.</p>
        <button class="button button-primary button-hero" id="update-payment">Update Payment Card</button>
    </div>
</div>

<script>
jQuery(function($) {
    $('#update-payment').on('click', function() {
        $(this).prop('disabled', true).text('Loading...');
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_get_billing_portal',
            nonce: agentclerk.nonce
        }, function(r) {
            if (r.success && r.data.url) {
                window.location.href = r.data.url;
            } else {
                alert('Could not load billing portal. Please try again.');
                $('#update-payment').prop('disabled', false).text('Update Payment Card');
            }
        });
    });
});
</script>
