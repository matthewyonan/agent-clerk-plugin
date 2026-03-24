<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span>Choose tier</span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">2</div><span>Scan site</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">3</div><span>Review</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span>Catalog</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span>Placement</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">6</div><span>Go live</span></div>
    </div>

    <div class="ac-explain-box" id="scan-intro">
        <div style="font-size:28px;margin-bottom:10px">&#128269;</div>
        <h3>We're reading your store so you don't have to type any of it</h3>
        <p>AgentClerk scans everything it can find on your site &mdash; products, descriptions, pricing, policies, FAQs, blog posts. The goal is to build your seller agent's knowledge automatically, so it answers buyer questions accurately from day one.</p>
        <p style="margin:10px 0;font-size:12px;font-weight:500;color:var(--ac-text2)">What happens during the scan:</p>
        <div class="ac-es"><div class="ac-es-n">1</div><div><strong>Sitemap crawl</strong> &mdash; every page your site publishes is queued.</div></div>
        <div class="ac-es"><div class="ac-es-n">2</div><div><strong>Content extraction</strong> &mdash; products, prices, policies, FAQs, and support content are pulled and structured.</div></div>
        <div class="ac-es"><div class="ac-es-n">3</div><div><strong>Knowledge draft</strong> &mdash; your seller agent's initial knowledge base is built from what we found.</div></div>
        <div class="ac-es" style="margin-bottom:14px"><div class="ac-es-n">4</div><div><strong>Gap report</strong> &mdash; anything we couldn't find is flagged so you can fill it in on the next screen.</div></div>
        <div class="ac-fr">
            <button class="ac-btn ac-btn-e ac-btn-lg" id="start-scan">Scan my site and start setup &rarr;</button>
            <span style="font-size:12px;color:var(--ac-text3)">Takes 1&ndash;2 minutes</span>
        </div>
    </div>

    <div id="scan-running" style="display:none">
        <div class="ac-g2">
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2>Scan progress</h2><span class="ac-b ac-b-a" id="scan-badge"><span class="ac-spinner"></span>Scanning&hellip;</span></div>
                    <div class="ac-card-body">
                        <div class="ac-scan-log" id="scan-log">
                            <div class="ac-sl-h">preparing</div>
                            <div><span class="ac-spinner"></span><span class="ac-sl-d">Starting scan&hellip;</span></div>
                        </div>
                        <div style="font-size:11px;color:var(--ac-text3);margin-top:8px" id="scan-status-text">Preparing scan&hellip; Do not close this tab.</div>
                    </div>
                </div>
            </div>
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2>Found so far</h2></div>
                    <div class="ac-card-body" id="scan-findings">
                        <div class="ac-co sl"><span class="ac-co-i">&#8987;</span><span>Waiting for scan results&hellip;</span></div>
                    </div>
                </div>
                <div class="ac-co sl"><span class="ac-co-i">&#8505;</span><span>The chat to fill in any gaps will appear on the next screen once scanning finishes.</span></div>
            </div>
        </div>
    </div>

    <div id="scan-done" style="display:none;margin-top:8px">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="scan-continue">Scan complete &mdash; review results &rarr;</button>
    </div>
</div>

<script>
jQuery(function($) {
    var pollTimer;

    $('#start-scan').on('click', function() {
        $(this).prop('disabled', true);
        $('#scan-intro').hide();
        $('#scan-running').show();

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_start_scan',
            nonce: agentclerk.nonce
        }, function(resp) {
            // Scan started or completed synchronously
            if (resp.success) {
                clearInterval(pollTimer);
                $('#scan-badge').html('&#10003; Complete').removeClass('ac-b-a').addClass('ac-b-g');
                $('#scan-done').show();
                $('#scan-status-text').text('Scan complete.');
                updateFindings(resp.data);
            }
        });

        pollTimer = setInterval(function() {
            $.post(agentclerk.ajaxUrl, {
                action: 'agentclerk_scan_progress',
                nonce: agentclerk.nonce
            }, function(resp) {
                if (!resp.success || !resp.data) return;
                var d = resp.data;
                var total = d.total || 0;
                var done = d.completed || 0;
                if (total > 0) {
                    $('#scan-status-text').text(done + ' of ' + total + ' pages read. Do not close this tab.');
                }

                var log = '<div class="ac-sl-h">sitemap crawl</div>';
                log += '<div><span class="ac-sl-ok">&#10003;</span> ' + (total || '?') + ' URLs found</div>';
                log += '<br><div class="ac-sl-h">pages read</div>';
                if (d.pages) {
                    d.pages.forEach(function(p) {
                        log += '<div><span class="ac-sl-ok">&#10003;</span> ' + p + '</div>';
                    });
                }
                if (done < total) {
                    log += '<div><span class="ac-spinner"></span><span class="ac-sl-d">Reading pages&hellip;</span></div>';
                }
                $('#scan-log').html(log);

                updateFindings(d);

                if (d.status === 'complete') {
                    clearInterval(pollTimer);
                    $('#scan-badge').html('&#10003; Complete').removeClass('ac-b-a').addClass('ac-b-g');
                    $('#scan-done').show();
                }
            });
        }, 2000);
    });

    function updateFindings(d) {
        var html = '';
        if (d.products_found) html += '<div class="ac-co gn"><span class="ac-co-i">&#10003;</span><span><strong>' + d.products_found + ' products</strong> with names, prices, and descriptions.</span></div>';
        if (d.refund_found) html += '<div class="ac-co gn"><span class="ac-co-i">&#10003;</span><span><strong>Refund policy</strong> found.</span></div>';
        if (d.license_found) html += '<div class="ac-co gn"><span class="ac-co-i">&#10003;</span><span><strong>License terms</strong> found.</span></div>';
        if (d.blog_found) html += '<div class="ac-co gn"><span class="ac-co-i">&#10003;</span><span><strong>Blog content</strong> found.</span></div>';
        if (d.gaps) {
            d.gaps.forEach(function(g) {
                html += '<div class="ac-co am"><span class="ac-co-i">&#9888;</span><span><strong>' + g + '</strong></span></div>';
            });
        }
        if (html) $('#scan-findings').html(html);
    }

    $('#scan-continue').on('click', function() {
        window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
    });
});
</script>
