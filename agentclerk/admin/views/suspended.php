<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
$grace_days   = (int) get_option( 'agentclerk_grace_days_remaining', 0 );
?>
<div class="wrap ac-wrap">
    <div class="ac-card" style="max-width:560px;margin:40px auto;text-align:center;padding:0">
        <div style="background:var(--ac-slate);padding:28px 24px">
            <div style="font-size:32px;margin-bottom:10px">&#9888;</div>
            <h1 style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--ac-red);margin:0"><?php echo esc_html( 'Plugin suspended' ); ?></h1>
            <p style="font-size:13px;color:var(--ac-muted);margin-top:8px;line-height:1.55"><?php echo esc_html( 'Your AgentClerk account has been suspended due to a failed payment. All buyer-facing agent services are currently disabled.' ); ?></p>
        </div>
        <div style="padding:24px">
            <?php if ( $grace_days > 0 ) : ?>
                <div style="margin-bottom:18px;padding:14px;background:var(--ac-amber-lt);border:1px solid #FCD34D;border-radius:var(--ac-radius2)">
                    <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#92400E"><?php printf( esc_html( '%d grace days remaining' ), $grace_days ); ?></div>
                    <?php if ( $accrued_fees > 0 ) : ?>
                        <div style="font-size:12px;color:#92400E;margin-top:4px"><?php printf( esc_html( 'Outstanding balance: $%s' ), esc_html( number_format( $accrued_fees, 2 ) ) ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="font-size:13px;color:var(--ac-text2);margin-bottom:18px"><?php echo esc_html( 'Please update your payment method to restore service.' ); ?></p>
            <button class="ac-btn ac-btn-electric" id="ac-update-payment" style="width:100%;justify-content:center;padding:12px"><?php echo esc_html( 'Update payment method' ); ?></button>
            <div style="margin-top:12px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=agentclerk-support' ) ); ?>" style="font-size:12px;color:var(--ac-text3)"><?php echo esc_html( 'Contact support' ); ?></a></div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    $('#ac-update-payment').on('click', function() {
        $(this).prop('disabled', true).text('Loading...');
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_update_card', nonce: agentclerk.nonce }, function(r) {
            if (r.success && r.data.portalUrl) { window.location.href = r.data.portalUrl; }
            else { alert('Could not load billing portal. Please try again.'); $('#ac-update-payment').prop('disabled', false).text('Update payment method'); }
        });
    });
});
</script>
