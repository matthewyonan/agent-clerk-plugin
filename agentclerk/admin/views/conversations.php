<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt"><?php echo esc_html( 'Conversations' ); ?></div>
            <div class="ac-ps"><?php echo esc_html( 'Every seller agent conversation. Click a row to review the transcript.' ); ?></div>
        </div>
        <div class="ac-fr">
            <select id="ac-convo-filter" style="width:auto;font-size:12px;padding:5px 10px">
                <option value=""><?php echo esc_html( 'All conversations' ); ?></option>
                <option value="ai_agent"><?php echo esc_html( 'AI agent buyers' ); ?></option>
                <option value="human"><?php echo esc_html( 'Human buyers' ); ?></option>
                <option value="escalated"><?php echo esc_html( 'Escalated' ); ?></option>
            </select>
            <input type="text" id="ac-convo-search" placeholder="<?php echo esc_attr( 'Search…' ); ?>" style="width:150px;font-size:12px;padding:5px 10px">
        </div>
    </div>

    <div class="ac-stat-grid" style="grid-template-columns:repeat(5,1fr)">
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-total">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Total conversations' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-setup">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Helped with install / setup' ); ?></div>
            <div class="ac-stat-sub"><?php echo esc_html( 'no human needed' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-support">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Handled support' ); ?></div>
            <div class="ac-stat-sub"><?php echo esc_html( 'no human needed' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-cart">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'In cart' ); ?></div>
            <div class="ac-stat-sub" style="color:var(--amber)"><?php echo esc_html( 'quote sent, unpaid' ); ?></div>
        </div>
        <div class="ac-stat-box">
            <div class="ac-stat-val" id="ac-cs-escalated">&mdash;</div>
            <div class="ac-stat-lbl"><?php echo esc_html( 'Escalated to you' ); ?></div>
            <div style="margin-top:4px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=agentclerk-support' ) ); ?>" style="font-size:11px;color:var(--elec-dk)"><?php echo esc_html( 'View in Support' ); ?> &rarr;</a></div>
        </div>
    </div>

    <div class="ac-card"><table class="ac-dt">
        <thead><tr><th><?php echo esc_html( 'Date' ); ?></th><th><?php echo esc_html( 'Buyer type' ); ?></th><th><?php echo esc_html( 'Started with' ); ?></th><th><?php echo esc_html( 'Outcome' ); ?></th><th><?php echo esc_html( 'Product' ); ?></th><th><?php echo esc_html( 'Value' ); ?></th></tr></thead>
        <tbody id="ac-convo-tbody">
            <tr><td colspan="6" style="color:var(--text3)"><?php echo esc_html( 'Loading...' ); ?></td></tr>
        </tbody>
    </table></div>

    <div id="ac-convo-pagination" style="margin-top:10px"></div>

    <!-- Transcript Modal -->
    <div class="ac-modal-ov" id="ac-transcript-modal">
        <div class="ac-modal-box" style="width:600px">
            <div class="ac-modal-hd">
                <h3><?php echo esc_html( 'Conversation Transcript' ); ?></h3>
                <span class="ac-modal-x" id="ac-close-transcript">&times;</span>
            </div>
            <div class="ac-modal-body" id="ac-transcript-content" style="max-height:60vh;overflow-y:auto"></div>
        </div>
    </div>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
