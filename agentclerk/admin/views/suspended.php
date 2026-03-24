<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
$grace_days   = (int) get_option( 'agentclerk_grace_days_remaining', 0 );
?>
<div class="wrap ac-wrap">
    <div class="ac-card" style="max-width:560px;margin:40px auto;text-align:center;padding:0">
        <div style="background:var(--slate);padding:28px 24px">
            <div style="font-size:32px;margin-bottom:10px">&#9888;</div>
            <h1 style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:#EF4444;margin:0"><?php echo esc_html( 'Plugin suspended' ); ?></h1>
            <p style="font-size:13px;color:var(--muted);margin-top:8px;line-height:1.55"><?php echo esc_html( 'Your AgentClerk account has been suspended due to a failed payment. All buyer-facing agent services are currently disabled.' ); ?></p>
        </div>
        <div style="padding:24px">
            <?php if ( $grace_days > 0 ) : ?>
                <div style="margin-bottom:18px;padding:14px;background:var(--amber-lt);border:1px solid #FCD34D;border-radius:var(--r2)">
                    <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#92400E"><?php printf( esc_html( '%d grace days remaining' ), $grace_days ); ?></div>
                    <?php if ( $accrued_fees > 0 ) : ?>
                        <div style="font-size:12px;color:#92400E;margin-top:4px"><?php printf( esc_html( 'Outstanding balance: $%s' ), esc_html( number_format( $accrued_fees, 2 ) ) ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="font-size:13px;color:var(--text2);margin-bottom:18px"><?php echo esc_html( 'Please update your payment method to restore service.' ); ?></p>
            <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-update-payment" style="width:100%;justify-content:center"><?php echo esc_html( 'Update payment method' ); ?></button>
            <div style="margin-top:12px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=agentclerk-support' ) ); ?>" style="font-size:12px;color:var(--text3)"><?php echo esc_html( 'Contact support' ); ?></a></div>
        </div>
    </div>
</div>
