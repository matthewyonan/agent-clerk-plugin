<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt"><?php echo esc_html( 'Support' ); ?></div>
            <div class="ac-ps"><?php echo esc_html( 'Buyer escalations on the left. Ask us about the plugin on the right.' ); ?></div>
        </div>
        <span class="ac-b ac-b-a" id="ac-open-count"><?php echo esc_html( '0 open' ); ?></span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

        <!-- LEFT: Buyer Escalation Log -->
        <div>
            <div style="font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:10px"><?php echo esc_html( 'Buyer escalations' ); ?></div>
            <div id="ac-escalation-list">
                <div class="ac-co sl"><span class="ac-co-i">&#8987;</span><span><?php echo esc_html( 'Loading...' ); ?></span></div>
            </div>
            <div style="font-size:12px;color:var(--text3);text-align:center;padding:8px 0">
                <a href="#" id="ac-view-resolved" style="display:none;color:var(--elec-dk)"><?php echo esc_html( 'View resolved escalations' ); ?> &rarr;</a>
            </div>
        </div>

        <!-- RIGHT: AgentClerk Plugin Help -->
        <div>
            <div style="font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:10px"><?php echo esc_html( 'AgentClerk help' ); ?></div>
            <div class="ac-chat-shell" style="height:580px">
                <div class="ac-chat-hd">
                    <div class="ac-chat-av" style="background:var(--elec-lt);color:var(--elec-dk);font-size:11px;font-weight:700">AC</div>
                    <div>
                        <div class="ac-chat-nm"><?php echo esc_html( 'AgentClerk Support' ); ?></div>
                        <div class="ac-chat-st">&#9679; <?php echo esc_html( 'Ask us anything about the plugin' ); ?></div>
                    </div>
                </div>
                <div class="ac-msgs" id="ac-support-msgs" style="flex:1"></div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;padding:6px 12px 2px;background:var(--white)">
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'How do I update my support file?' ); ?>"><?php echo esc_html( 'How do I update my support file?' ); ?></span>
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'Why did a transaction not get billed?' ); ?>"><?php echo esc_html( 'Why did a transaction not get billed?' ); ?></span>
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'How do I handle the refund request?' ); ?>"><?php echo esc_html( 'How do I handle the refund request?' ); ?></span>
                    <span class="ac-chip" data-q="<?php echo esc_attr( 'Re-send a download link to a buyer' ); ?>"><?php echo esc_html( 'Re-send a download link to a buyer' ); ?></span>
                </div>
                <div class="ac-chat-inp-row">
                    <textarea class="ac-chat-inp" id="ac-support-input" rows="1" placeholder="<?php echo esc_attr( 'Ask about the plugin…' ); ?>"></textarea>
                    <button class="ac-send-btn" id="ac-support-send">&#10148;</button>
                </div>
            </div>
        </div>

    </div>
</div>
