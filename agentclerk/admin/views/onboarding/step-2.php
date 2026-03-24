<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">2</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">3</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">4</div><span><?php echo esc_html( 'Placement' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-explain-box" id="ac-scan-intro">
        <div style="font-size:28px;margin-bottom:10px">&#128269;</div>
        <h3><?php echo esc_html( "We're reading your store so you don't have to type any of it" ); ?></h3>
        <p><?php echo esc_html( "AgentClerk scans everything it can find on your site via your sitemap — products, descriptions, pricing, policies, FAQs, blog posts. The goal is to build your seller agent's knowledge automatically, so it answers buyer questions accurately from day one." ); ?></p>
        <p style="margin:10px 0 10px;font-size:12px;font-weight:500;color:var(--text2)"><?php echo esc_html( 'What happens during the scan:' ); ?></p>
        <div class="ac-es"><div class="ac-es-n">1</div><div><?php echo wp_kses( '<strong>Sitemap crawl</strong> — every page your site publishes is queued.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-es"><div class="ac-es-n">2</div><div><?php echo wp_kses( '<strong>Content extraction</strong> — products, prices, policies, FAQs, and support content are pulled and structured.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-es"><div class="ac-es-n">3</div><div><?php echo wp_kses( '<strong>Knowledge draft</strong> — your seller agent\'s initial knowledge base is built from what we found.', array( 'strong' => array() ) ); ?></div></div>
        <div class="ac-es" style="margin-bottom:0"><div class="ac-es-n">4</div><div><?php echo wp_kses( '<strong>Gap report</strong> — anything we couldn\'t find is flagged so you can fill it in on the next screen.', array( 'strong' => array() ) ); ?></div></div>
    </div>

    <div id="ac-scan-running" style="display:none">
        <div class="ac-g2">
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Scan progress' ); ?></h2><span class="ac-b ac-b-a" id="ac-scan-badge"><span class="ac-spinner"></span><?php echo esc_html( 'Scanning…' ); ?></span></div>
                    <div class="ac-card-body" style="padding:14px 16px">
                        <div class="ac-scan-bar-track"><div class="ac-scan-bar-fill" id="ac-scan-bar-fill" style="width:0%"></div></div>
                        <div class="ac-scan-log" id="ac-scan-log">
                            <div class="ac-sl-h"><?php echo esc_html( 'preparing' ); ?></div>
                            <div><span class="ac-spinner"></span><span class="ac-sl-d"><?php echo esc_html( 'Starting scan...' ); ?></span></div>
                        </div>
                        <div style="font-size:11px;color:var(--text3);margin-top:8px" id="ac-scan-page-counter"><?php echo esc_html( 'Preparing scan… Do not close this tab.' ); ?></div>
                    </div>
                </div>
            </div>
            <div>
                <div class="ac-card">
                    <div class="ac-card-head"><h2><?php echo esc_html( 'Found so far' ); ?></h2></div>
                    <div class="ac-card-body" id="ac-scan-findings">
                        <div class="ac-co sl"><span class="ac-co-i">&#8987;</span><span><?php echo esc_html( 'Waiting for scan results...' ); ?></span></div>
                    </div>
                </div>
                <div class="ac-co sl"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'The chat to fill in any gaps will appear on the next screen once scanning finishes. You\'ll only be asked about things we couldn\'t find automatically.' ); ?></span></div>
            </div>
        </div>
    </div>

    <div id="ac-scan-done" style="display:none;margin-top:4px">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-scan-continue"><?php echo esc_html( 'Scan complete — review results' ); ?> &rarr;</button>
    </div>

    <div id="ac-scan-start-row" style="margin-top:16px">
        <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-start-scan"><?php echo esc_html( 'Start scanning' ); ?> &rarr;</button>
    </div>
</div>
