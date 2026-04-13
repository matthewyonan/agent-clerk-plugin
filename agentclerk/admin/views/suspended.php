<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$agentclerk_accrued_fees = (float) get_option( 'agentclerk_accrued_fees', 0 );
$agentclerk_grace_days   = (int) get_option( 'agentclerk_grace_days_remaining', 0 );
?>
<div class="wrap ac-wrap">
    <div class="ac-card" style="max-width:560px;margin:40px auto;text-align:center;padding:0">
        <div style="background:var(--slate);padding:28px 24px">
            <div style="font-size:32px;margin-bottom:10px">&#9432;</div>
            <h1 style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:#F59E0B;margin:0"><?php echo esc_html( 'Payment issue detected' ); ?></h1>
            <p style="font-size:13px;color:var(--muted);margin-top:8px;line-height:1.55"><?php echo esc_html( 'There is a billing issue with your AgentClerk account. Please update your payment method. Your agent continues to operate normally.' ); ?></p>
        </div>
        <div style="padding:24px">
            <?php if ( $agentclerk_grace_days > 0 ) : ?>
                <div style="margin-bottom:18px;padding:14px;background:var(--amber-lt);border:1px solid #FCD34D;border-radius:var(--r2)">
                    <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#92400E"><?php printf( esc_html( '%d grace days remaining' ), intval( $agentclerk_grace_days ) ); ?></div>
                    <?php if ( $agentclerk_accrued_fees > 0 ) : ?>
                        <div style="font-size:12px;color:#92400E;margin-top:4px"><?php printf( esc_html( 'Outstanding balance: $%s' ), esc_html( number_format( $agentclerk_accrued_fees, 2 ) ) ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="font-size:13px;color:var(--text2);margin-bottom:18px"><?php echo esc_html( 'Please update your payment method at your earliest convenience.' ); ?></p>
            <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-update-payment" style="width:100%;justify-content:center"><?php echo esc_html( 'Update payment method' ); ?></button>
            <div style="margin-top:12px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=agentclerk-support' ) ); ?>" style="font-size:12px;color:var(--text3)"><?php echo esc_html( 'Contact support' ); ?></a></div>
        </div>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
