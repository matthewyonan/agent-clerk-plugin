<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
$card_last4   = get_option( 'agentclerk_billing_card_last4', '' );
?>
<div class="wrap ac-wrap">
    <div class="ac-card" style="max-width:600px;margin:40px auto;text-align:center;padding:0">
        <div style="background:var(--ac-slate);padding:24px 20px">
            <div style="font-size:28px;margin-bottom:8px">&#9888;</div>
            <h1 style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:#EF4444;margin:0">Account Suspended</h1>
            <p style="font-size:13px;color:var(--ac-muted);margin-top:6px">Your AgentClerk account has been suspended due to a billing issue. All buyer-facing agent services are currently disabled.</p>
        </div>
        <div style="padding:24px 20px">
            <?php if ( $accrued_fees > 0 ) : ?>
                <div style="margin-bottom:16px;padding:15px;background:var(--ac-amber-lt);border:1px solid #FCD34D;border-radius:var(--ac-r2)">
                    <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:#92400E">Outstanding Fees: $<?php echo esc_html( number_format( $accrued_fees, 2 ) ); ?></div>
                    <?php if ( $card_last4 ) : ?>
                        <div style="font-size:12px;color:#92400E;margin-top:4px">Card on file: **** <?php echo esc_html( $card_last4 ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="font-size:13px;color:var(--ac-text2);margin-bottom:16px">Please update your payment method to restore service.</p>
            <button class="ac-btn ac-btn-e ac-btn-lg" id="update-payment" style="width:100%">Update Payment Card</button>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    $('#update-payment').on('click', function() {
        $(this).prop('disabled', true).text('Loading...');
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_card_update',
            nonce: agentclerk.nonce
        }, function(r) {
            if (r.success && r.data.portalUrl) {
                window.location.href = r.data.portalUrl;
            } else {
                alert('Could not load billing portal. Please try again.');
                $('#update-payment').prop('disabled', false).text('Update Payment Card');
            }
        });
    });
});
</script>
