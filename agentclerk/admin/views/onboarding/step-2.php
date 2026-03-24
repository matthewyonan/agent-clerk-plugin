<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">2</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">4</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-explain-box" id="ac-scan-intro">
        <div style="font-size:28px;margin-bottom:10px">&#128269;</div>
        <h3><?php echo esc_html( 'We\'re reading your store so you don\'t have to type any of it' ); ?></h3>
        <p><?php echo esc_html( 'AgentClerk scans everything it can find on your site — products, descriptions, pricing, policies, FAQs, blog posts. The goal is to build your seller agent\'s knowledge automatically, so it answers buyer questions accurately from day one.' ); ?></p>
        <p style="margin:10px 0;font-size:12px;font-weight:500;color:var(--ac-text2)"><?php echo esc_html( 'What happens during the scan:' ); ?></p>
        <div class="ac-explain-step"><div class="ac-explain-step-num">1</div><div><?php echo wp_kses( '<strong>Sitemap crawl</strong> — every page your site publishes is queued.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-explain-step"><div class="ac-explain-step-num">2</div><div><?php echo wp_kses( '<strong>Content extraction</strong> — products, prices, policies, FAQs, and support content are pulled and structured.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-explain-step"><div class="ac-explain-step-num">3</div><div><?php echo wp_kses( '<strong>Knowledge draft</strong> — your seller agent\'s initial knowledge base is built from what we found.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-explain-step" style="margin-bottom:14px"><div class="ac-explain-step-num">4</div><div><?php echo wp_kses( '<strong>Gap report</strong> — anything we couldn\'t find is flagged so you can fill it in on the next screen.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-flex">
            <button class="ac-btn ac-btn-electric" id="ac-start-scan"><?php echo esc_html( 'Scan my site and start setup' ); ?> &rarr;</button>
            <span style="font-size:12px;color:var(--ac-text3)"><?php echo esc_html( 'Takes 1-2 minutes' ); ?></span>
        </div>
    </div>

    <div id="ac-scan-running" style="display:none">
        <div class="ac-grid-2">
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Scan progress' ); ?></h2><span class="ac-badge ac-badge-amber" id="ac-scan-badge"><span class="ac-spinner"></span> <?php echo esc_html( 'Scanning...' ); ?></span></div>
                    <div class="ac-card-body">
                        <div class="ac-scan-bar-track"><div class="ac-scan-bar-fill" id="ac-scan-bar-fill" style="width:0%"></div></div>
                        <div class="ac-scan-log" id="ac-scan-log">
                            <div class="ac-scan-heading"><?php echo esc_html( 'preparing' ); ?></div>
                            <div><span class="ac-spinner"></span> <span class="ac-scan-dim"><?php echo esc_html( 'Starting scan...' ); ?></span></div>
                        </div>
                        <div style="font-size:11px;color:var(--ac-text3);margin-top:8px" id="ac-scan-page-counter"><?php echo esc_html( 'Preparing scan... Do not close this tab.' ); ?></div>
                    </div>
                </div>
            </div>
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Found so far' ); ?></h2></div>
                    <div class="ac-card-body" id="ac-scan-findings">
                        <div class="ac-callout ac-callout-slate"><span>&#8987;</span><span><?php echo esc_html( 'Waiting for scan results...' ); ?></span></div>
                    </div>
                </div>
                <div class="ac-callout ac-callout-slate"><span>&#8505;</span><span><?php echo esc_html( 'The chat to fill in any gaps will appear on the next screen once scanning finishes.' ); ?></span></div>
            </div>
        </div>
    </div>

    <div id="ac-scan-done" style="display:none;margin-top:8px">
        <button class="ac-btn ac-btn-electric" id="ac-scan-continue"><?php echo esc_html( 'Scan complete — review results' ); ?> &rarr;</button>
    </div>
</div>

<script>
jQuery(function($) {
    var pollTimer;
    $('#ac-start-scan').on('click', function() {
        $(this).prop('disabled', true);
        $('#ac-scan-intro').hide();
        $('#ac-scan-running').show();
        $.post(agentclerk.ajaxUrl, { action: 'agentclerk_start_scan', nonce: agentclerk.nonce }, function(resp) {
            if (resp.success) { clearInterval(pollTimer); $('#ac-scan-badge').html('&#10003; Complete').removeClass('ac-badge-amber').addClass('ac-badge-green'); $('#ac-scan-done').show(); $('#ac-scan-page-counter').text('Scan complete.'); updateFindings(resp.data); }
        });
        pollTimer = setInterval(function() {
            $.post(agentclerk.ajaxUrl, { action: 'agentclerk_scan_progress', nonce: agentclerk.nonce }, function(resp) {
                if (!resp.success || !resp.data) return;
                var d = resp.data, total = d.total||0, done = d.completed||0;
                if (total > 0) { $('#ac-scan-bar-fill').css('width', Math.round((done/total)*100)+'%'); $('#ac-scan-page-counter').text(done+' of '+total+' pages read. Do not close this tab.'); }
                var log = '<div class="ac-scan-heading">sitemap crawl</div><div><span class="ac-scan-ok">&#10003;</span> '+(total||'?')+' URLs found</div><br><div class="ac-scan-heading">pages read</div>';
                if (d.pages) { d.pages.forEach(function(p) { log += '<div><span class="ac-scan-ok">&#10003;</span> '+$('<span>').text(p).html()+'</div>'; }); }
                if (done < total) { log += '<div><span class="ac-spinner"></span> <span class="ac-scan-dim">Reading pages...</span></div>'; }
                $('#ac-scan-log').html(log);
                updateFindings(d);
                if (d.status === 'complete') { clearInterval(pollTimer); $('#ac-scan-badge').html('&#10003; Complete').removeClass('ac-badge-amber').addClass('ac-badge-green'); $('#ac-scan-bar-fill').css('width','100%'); $('#ac-scan-done').show(); }
            });
        }, 2000);
    });
    function updateFindings(d) {
        var h = '';
        if (d.products_found) h += '<div class="ac-callout ac-callout-green"><span>&#10003;</span><span><strong>'+d.products_found+' products</strong> with names, prices, and descriptions.</span></div>';
        if (d.refund_found) h += '<div class="ac-callout ac-callout-green"><span>&#10003;</span><span><strong>Refund policy</strong> found.</span></div>';
        if (d.license_found) h += '<div class="ac-callout ac-callout-green"><span>&#10003;</span><span><strong>License terms</strong> found.</span></div>';
        if (d.blog_found) h += '<div class="ac-callout ac-callout-green"><span>&#10003;</span><span><strong>Blog content</strong> found.</span></div>';
        if (d.gaps) { d.gaps.forEach(function(g) { h += '<div class="ac-callout ac-callout-amber"><span>&#9888;</span><span><strong>'+$('<span>').text(g).html()+'</strong></span></div>'; }); }
        if (h) $('#ac-scan-findings').html(h);
    }
    $('#ac-scan-continue').on('click', function() { window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php','admin.php?page=agentclerk-onboarding'); });
});
</script>
