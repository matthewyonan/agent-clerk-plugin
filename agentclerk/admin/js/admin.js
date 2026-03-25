/**
 * AgentClerk Admin JS
 *
 * Single file handling all admin interactions: onboarding (steps 1-6),
 * dashboard, conversations, sales, support, settings, and suspended pages.
 *
 * Uses wireframe-matching class names with ac- prefix.
 * Depends on jQuery (WordPress built-in) and the `agentclerk` localized object.
 *
 * @since 1.0.0
 */
(function($) {
    'use strict';

    /* =========================================================================
     * Toast Notification System
     * ====================================================================== */

    function showToast(message, type) {
        type = type || 'success';
        var clsMap = { success: 'gn', error: 'am', info: 'sl' };
        var iconMap = { success: '\u2713', error: '\u2718', info: '\u2139' };
        var cls = clsMap[type] || 'gn';
        var icon = iconMap[type] || iconMap.success;

        var $toast = $(
            '<div class="ac-co ' + cls + '" style="position:fixed;top:40px;right:20px;z-index:100002;min-width:200px;box-shadow:0 4px 12px rgba(0,0,0,.15);transition:opacity .3s">' +
                '<span class="ac-co-i">' + icon + '</span>' +
                '<span>' + message + '</span>' +
            '</div>'
        );
        $('body').append($toast);
        setTimeout(function() {
            $toast.css('opacity', '0');
            setTimeout(function() { $toast.remove(); }, 300);
        }, 3000);
    }

    /* =========================================================================
     * AJAX Helpers
     * ====================================================================== */

    function acAjax(action, data) {
        data = data || {};
        data.action = 'agentclerk_' + action;
        data.nonce  = agentclerk.nonce;
        return new Promise(function(resolve, reject) {
            $.post(agentclerk.ajaxUrl, data, function(resp) {
                if (resp.success) resolve(resp.data);
                else reject(resp.data || { message: 'Request failed.' });
            }).fail(function(xhr, status, err) {
                reject({ message: err || 'Network error.' });
            });
        });
    }

    function acAjaxGet(action, data) {
        data = data || {};
        data.action = 'agentclerk_' + action;
        data.nonce  = agentclerk.nonce;
        return new Promise(function(resolve, reject) {
            $.get(agentclerk.ajaxUrl, data, function(resp) {
                if (resp.success) resolve(resp.data);
                else reject(resp.data || { message: 'Request failed.' });
            }).fail(function(xhr, status, err) {
                reject({ message: err || 'Network error.' });
            });
        });
    }

    /* =========================================================================
     * Shared Helpers
     * ====================================================================== */

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function goToPage(page) {
        window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=' + page);
    }

    function addChatMessage(containerId, role, text) {
        var cls = role === 'assistant' ? 'ag' : 'us';
        var av  = role === 'assistant' ? 'AC' : 'You';
        var $container = $(containerId);
        $container.append(
            '<div class="ac-msg ' + cls + '">' +
                '<div class="ac-mav">' + av + '</div>' +
                '<div class="ac-mbub">' + text + '</div>' +
            '</div>'
        );
        $container.scrollTop($container[0].scrollHeight);
    }

    function setChips(containerId, chips, onClick) {
        var $row = $(containerId).empty();
        if (!chips || !chips.length) return;
        chips.forEach(function(label) {
            $('<span class="ac-chip">' + escHtml(label) + '</span>').on('click', function() {
                if (typeof onClick === 'function') onClick(label);
            }).appendTo($row);
        });
    }

    /* =========================================================================
     * URL Parameter Handlers
     * ====================================================================== */

    var urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('turnkey_success') === '1') {
        acAjax('save_onboarding_step', { step: 2 }).then(function() {
            goToPage('agentclerk-onboarding');
        });
    }

    if (urlParams.get('license_success') === '1') {
        $('<div class="notice notice-success is-dismissible"><p>Lifetime license activated. No more transaction fees.</p></div>')
            .insertAfter('.wrap h1:first');
    }

    /* =========================================================================
     * Global Click Handlers
     * ====================================================================== */

    // Toggle switches
    $(document).on('click', '.ac-tog', function() {
        $(this).toggleClass('on');
    });

    // Placement cards
    $(document).on('click', '.ac-pl-card', function() {
        $(this).toggleClass('on');
    });

    /* =========================================================================
     * Onboarding Step 1: Tier Selection & Payment
     * ====================================================================== */

    (function initStep1() {
        if (!$('#ac-tier-selection').length) return;

        var selectedTier = 'byok';
        var apiKeyValid  = false;
        var stripe, cardElement, cardElementTurnkey;

        // Stripe Elements
        if (typeof Stripe !== 'undefined' && typeof agentclerkStripe !== 'undefined') {
            stripe = Stripe(agentclerkStripe.publishableKey);
            var elements = stripe.elements();
            cardElement        = elements.create('card');
            cardElementTurnkey = elements.create('card');
            cardElement.mount('#ac-stripe-card-element');
        }

        // Tier card toggle
        $('.ac-tier-card').on('click', function() {
            selectedTier = $(this).data('tier');
            $('.ac-tier-card').removeClass('sel');
            $(this).addClass('sel');

            if (selectedTier === 'byok') {
                $('#ac-sec-byok').show();
                $('#ac-sec-turnkey').hide();
                if (cardElement) cardElement.mount('#ac-stripe-card-element');
            } else {
                $('#ac-sec-byok').hide();
                $('#ac-sec-turnkey').show();
                if (cardElementTurnkey) cardElementTurnkey.mount('#ac-stripe-card-element-turnkey');
            }
        });

        // API Key show/hide
        $('#ac-show-key').on('click', function() {
            var $inp = $('#ac-api-key');
            if ($inp.attr('type') === 'password') {
                $inp.attr('type', 'text');
                $(this).text('Hide');
            } else {
                $inp.attr('type', 'password');
                $(this).text('Show');
            }
        });

        // Validate API Key
        $('#ac-validate-api-key').on('click', function() {
            var key = $('#ac-api-key').val();
            if (!key) { showToast('Please enter an API key.', 'error'); return; }

            var $box = $('#ac-api-key-status-box');
            $box.html('<div class="ac-co gn" style="margin-bottom:0"><span class="ac-co-i"><span class="ac-spinner"></span></span><span>Validating API key...</span></div>');

            acAjax('validate_api_key', { api_key: key }).then(function() {
                $box.html('<div class="ac-co gn" style="margin-bottom:0"><span class="ac-co-i">\u2713</span><span>API key validated. Model access confirmed.</span></div>');
                apiKeyValid = true;
                $('#ac-submit-byok').prop('disabled', false);
            }).catch(function(err) {
                $box.html('<div class="ac-co am" style="margin-bottom:0"><span class="ac-co-i">\u2718</span><span>' + escHtml(err.message || 'Invalid API key.') + '</span></div>');
                apiKeyValid = false;
                $('#ac-submit-byok').prop('disabled', true);
            });
        });

        function submitRegistration(tier, pmId, apiKey) {
            acAjax('register_install', {
                tier: tier,
                stripe_payment_method_id: pmId,
                api_key: apiKey
            }).then(function(data) {
                if (data.redirect) window.location.href = data.redirect;
                else goToPage('agentclerk-onboarding');
            }).catch(function(err) {
                alert(err.message || 'Registration failed.');
                $('.ac-btn').prop('disabled', false);
            });
        }

        // Submit BYOK
        $('#ac-submit-byok').on('click', function() {
            if (!apiKeyValid) return;
            var $btn = $(this).prop('disabled', true).text('Processing...');

            if (stripe && cardElement) {
                stripe.createPaymentMethod({ type: 'card', card: cardElement }).then(function(result) {
                    if (result.error) {
                        $('#ac-stripe-card-errors').text(result.error.message);
                        $btn.prop('disabled', false).html('Scan my site and start setup &rarr;');
                        return;
                    }
                    submitRegistration('byok', result.paymentMethod.id, $('#ac-api-key').val());
                });
            } else {
                submitRegistration('byok', '', $('#ac-api-key').val());
            }
        });

        // Submit TurnKey
        $('#ac-submit-turnkey').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Processing...');

            if (stripe && cardElementTurnkey) {
                stripe.createPaymentMethod({ type: 'card', card: cardElementTurnkey }).then(function(result) {
                    if (result.error) {
                        $('#ac-stripe-card-errors-turnkey').text(result.error.message);
                        $btn.prop('disabled', false).html('Pay $99 and continue &rarr;');
                        return;
                    }
                    submitRegistration('turnkey', result.paymentMethod.id, '');
                });
            } else {
                submitRegistration('turnkey', '', '');
            }
        });

        // Lifetime CTA
        $('#ac-lifetime-cta-bar').on('click', function() {
            acAjax('lifetime_checkout').then(function(data) {
                if (data.checkoutUrl) window.location.href = data.checkoutUrl;
            });
        });
    })();

    /* =========================================================================
     * Onboarding Step 2: Site Scan
     * ====================================================================== */

    (function initStep2() {
        if (!$('#ac-start-scan').length && !$('#ac-scan-running').length) return;

        var pollTimer;

        function updateFindings(d) {
            var h = '';
            if (d.products_found) h += '<div class="ac-co gn"><span class="ac-co-i">\u2713</span><span><strong>' + d.products_found + ' products</strong> with names, prices, and descriptions.</span></div>';
            if (d.refund_found) h += '<div class="ac-co gn"><span class="ac-co-i">\u2713</span><span><strong>Refund policy</strong> found.</span></div>';
            if (d.license_found) h += '<div class="ac-co gn"><span class="ac-co-i">\u2713</span><span><strong>License terms</strong> found.</span></div>';
            if (d.blog_found) h += '<div class="ac-co gn"><span class="ac-co-i">\u2713</span><span><strong>Blog content</strong> found.</span></div>';
            if (d.gaps) {
                d.gaps.forEach(function(g) {
                    h += '<div class="ac-co am"><span class="ac-co-i">\u26A0</span><span><strong>' + escHtml(g) + '</strong></span></div>';
                });
            }
            if (h) $('#ac-scan-findings').html(h);
        }

        $('#ac-start-scan').on('click', function() {
            $(this).prop('disabled', true);
            $('#ac-scan-intro, #ac-scan-start-row').hide();
            $('#ac-scan-running').show();

            acAjax('start_scan').then(function(data) {
                clearInterval(pollTimer);
                $('#ac-scan-badge').html('\u2713 Complete').removeClass('ac-b-a').addClass('ac-b-g');
                $('#ac-scan-bar-fill').css('width', '100%');
                $('#ac-scan-done').show();
                $('#ac-scan-page-counter').text('Scan complete.');
                updateFindings(data);
            });

            pollTimer = setInterval(function() {
                acAjax('scan_progress').then(function(d) {
                    var total = d.total || 0;
                    var done = d.completed || 0;
                    if (total > 0) {
                        $('#ac-scan-bar-fill').css('width', Math.round((done / total) * 100) + '%');
                        $('#ac-scan-page-counter').text(done + ' of ' + total + ' pages read. Do not close this tab.');
                    }

                    var log = '<div class="ac-sl-h">sitemap crawl</div>';
                    log += '<div><span class="ac-sl-ok">\u2713</span> ' + (total || '?') + ' URLs found</div><br>';
                    log += '<div class="ac-sl-h">pages read</div>';
                    if (d.pages) {
                        d.pages.forEach(function(p) {
                            log += '<div><span class="ac-sl-ok">\u2713</span> ' + escHtml(p) + '</div>';
                        });
                    }
                    if (done < total) {
                        log += '<div><span class="ac-spinner"></span><span class="ac-sl-d">Reading pages...</span></div>';
                    }
                    $('#ac-scan-log').html(log);
                    updateFindings(d);

                    if (d.status === 'complete') {
                        clearInterval(pollTimer);
                        $('#ac-scan-badge').html('\u2713 Complete').removeClass('ac-b-a').addClass('ac-b-g');
                        $('#ac-scan-bar-fill').css('width', '100%');
                        $('#ac-scan-done').show();
                    }
                });
            }, 2000);
        });

        $('#ac-scan-continue').on('click', function() {
            goToPage('agentclerk-onboarding');
        });
    })();

    /* =========================================================================
     * Onboarding Step 3: Review & Gap Fill Chat
     * ====================================================================== */

    (function initStep3() {
        if (!$('#ac-chat-messages').length) return;

        var chatHistory = [];
        var gaps = [];

        // Try parsing gaps from embedded data
        try {
            var gapEl = document.getElementById('ac-gaps-data');
            if (gapEl) gaps = JSON.parse(gapEl.textContent);
        } catch (e) {}

        if (gaps.length > 0) {
            addChatMessage('#ac-chat-messages', 'assistant',
                'The scan went well \u2014 I found your products, pricing, policies, and some useful blog content. Just a few things I couldn\'t find.<br><br>First: <strong>how do you want me to notify you when a buyer has a question I can\'t resolve?</strong>');
            setChips('#ac-chat-chips', ['Both email and WP notification', 'Email only', 'WP admin only'], function(label) {
                $('#ac-chat-input').val(label);
                sendMessage();
            });
        } else {
            addChatMessage('#ac-chat-messages', 'assistant',
                'Your site looks well-configured! I found everything I need. You can edit the support file on the left, or continue to the catalog.');
        }

        var sending = false;

        function setSending(active) {
            sending = active;
            $('#ac-chat-send').prop('disabled', active);
            $('#ac-chat-input').prop('disabled', active);
            if (active) {
                addChatMessage('#ac-chat-messages', 'assistant', '<em>Thinking\u2026</em>');
            }
        }

        function removeThinking() {
            $('#ac-chat-messages .ac-msg.ag:last-child .ac-mbub:contains("Thinking")').closest('.ac-msg').remove();
        }

        function sendMessage() {
            var txt = $.trim($('#ac-chat-input').val());
            if (!txt || sending) return;

            addChatMessage('#ac-chat-messages', 'user', escHtml(txt));
            var historyToSend = JSON.stringify(chatHistory);
            chatHistory.push({ role: 'user', content: txt });
            $('#ac-chat-input').val('');
            $('#ac-chat-chips').empty();
            setSending(true);

            acAjax('onboarding_chat', {
                message: txt,
                context: 'gap_fill',
                history: historyToSend
            }).then(function(data) {
                removeThinking();
                setSending(false);
                if (data && data.message) {
                    addChatMessage('#ac-chat-messages', 'assistant', data.message);
                    chatHistory.push({ role: 'assistant', content: data.message });
                    if (data.chips) {
                        setChips('#ac-chat-chips', data.chips, function(label) {
                            $('#ac-chat-input').val(label);
                            sendMessage();
                        });
                    }
                    // Show confirmation when config was saved
                    if (data.saved_fields && data.saved_fields.length) {
                        showToast('Saved: ' + data.saved_fields.join(', '), 'success');
                    }
                } else {
                    console.error('AgentClerk: empty response from onboarding_chat', data);
                    addChatMessage('#ac-chat-messages', 'assistant', 'No response received \u2014 please try again.');
                }
            }).catch(function(err) {
                removeThinking();
                setSending(false);
                var msg = (err && err.message) ? err.message : 'Unknown error';
                console.error('AgentClerk: onboarding_chat failed', err);
                addChatMessage('#ac-chat-messages', 'assistant', 'Error: ' + escHtml(msg) + ' \u2014 please try again.');
            });
        }

        $('#ac-chat-send').on('click', sendMessage);
        $('#ac-chat-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });

        $('#ac-step3-continue').on('click', function() {
            $(this).prop('disabled', true).text('Saving...');
            acAjax('save_agent_config', { support_file: $('#ac-support-file').val() }).then(function() {
                return acAjax('save_onboarding_step', { step: 4 });
            }).then(function() {
                goToPage('agentclerk-onboarding');
            });
        });
    })();

    /* =========================================================================
     * Onboarding Step 4: Catalog
     * ====================================================================== */

    (function initStep4() {
        if (!$('#ac-step4-continue').length) return;

        // Add product modal
        $('#ac-show-add-product').on('click', function() {
            $('#ac-add-product-modal').addClass('active');
        });
        $('#ac-close-add-product').on('click', function() {
            $('#ac-add-product-modal').removeClass('active');
        });
        $('#ac-add-product-modal').on('click', function(e) {
            if (e.target === this) $(this).removeClass('active');
        });
        $('#ac-add-product').on('click', function() {
            var name = $.trim($('#ac-new-product-name').val());
            if (!name) { alert('Product name is required.'); return; }
            acAjax('add_product', {
                name: name,
                type: $('#ac-new-product-type').val(),
                price: $('#ac-new-product-price').val(),
                description: $('#ac-new-product-desc').val()
            }).then(function() {
                location.reload();
            }).catch(function(err) {
                alert(err.message || 'Failed to add product.');
            });
        });

        $('#ac-step4-continue').on('click', function() {
            var vis = {};
            $('.ac-tog').each(function() { vis[$(this).data('id')] = $(this).hasClass('on'); });
            acAjax('save_catalog', { visibility: JSON.stringify(vis) }).then(function() {
                return acAjax('save_onboarding_step', { step: 5 });
            }).then(function() {
                goToPage('agentclerk-onboarding');
            });
        });
    })();

    /* =========================================================================
     * Onboarding Step 5: Placement
     * ====================================================================== */

    (function initStep5() {
        if (!$('#ac-step5-continue').length) return;

        $('#ac-step5-continue').on('click', function() {
            acAjax('save_placement', {
                widget: $('#ac-pl-widget').hasClass('on') ? 1 : 0,
                product_page: $('#ac-pl-product').hasClass('on') ? 1 : 0,
                clerk_page: $('#ac-pl-clerk').hasClass('on') ? 1 : 0,
                button_label: $('#ac-button-label').val(),
                agent_name: $('#ac-agent-name').val()
            }).then(function() {
                return acAjax('save_onboarding_step', { step: 6 });
            }).then(function() {
                goToPage('agentclerk-onboarding');
            });
        });
    })();

    /* =========================================================================
     * Onboarding Step 6: Test & Go Live
     * ====================================================================== */

    (function initStep6() {
        if (!$('#ac-test-msgs').length) return;

        addChatMessage('#ac-test-msgs', 'assistant',
            'Hi! I can help you find the right product. What are you looking for?');

        var sending = false;

        function sendTest() {
            var txt = $.trim($('#ac-test-input').val());
            if (!txt || sending) return;
            addChatMessage('#ac-test-msgs', 'user', escHtml(txt));
            $('#ac-test-input').val('');
            sending = true;
            $('#ac-test-send').prop('disabled', true);
            addChatMessage('#ac-test-msgs', 'assistant', '<em>Thinking\u2026</em>');

            acAjax('chat', { message: txt, test_mode: '1' }).then(function(data) {
                $('#ac-test-msgs .ac-msg.ag:last-child .ac-mbub:contains("Thinking")').closest('.ac-msg').remove();
                sending = false;
                $('#ac-test-send').prop('disabled', false);
                if (data && data.message) {
                    addChatMessage('#ac-test-msgs', 'assistant', data.message);
                } else {
                    console.error('AgentClerk: empty test chat response', data);
                    addChatMessage('#ac-test-msgs', 'assistant', 'No response received \u2014 please try again.');
                }
            }).catch(function(err) {
                $('#ac-test-msgs .ac-msg.ag:last-child .ac-mbub:contains("Thinking")').closest('.ac-msg').remove();
                sending = false;
                $('#ac-test-send').prop('disabled', false);
                var msg = (err && err.message) ? err.message : 'Unknown error';
                console.error('AgentClerk: test chat failed', err);
                addChatMessage('#ac-test-msgs', 'assistant', 'Error: ' + escHtml(msg));
            });
        }

        $('#ac-test-send').on('click', sendTest);
        $('#ac-test-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendTest(); }
        });
        $('.ac-chip').on('click', function() {
            $('#ac-test-input').val($(this).data('q') || $(this).text());
            sendTest();
        });

        $('#ac-go-live').on('click', function() {
            if (!confirm('Ready to go live? Your AI agent will start handling real conversations.')) return;
            $(this).prop('disabled', true).text('Going live...');
            acAjax('go_live').then(function(data) {
                if (data.redirect) window.location.href = data.redirect;
                else goToPage('agentclerk');
            }).catch(function(err) {
                alert('Failed: ' + (err.message || 'Unknown error'));
                $('#ac-go-live').prop('disabled', false).html('Go live &rarr;');
            });
        });
    })();

    /* =========================================================================
     * Settings Page
     * ====================================================================== */

    (function initSettings() {
        if (!$('#ac-settings-tabs').length) return;

        // Tab switching
        $('.ac-stab').on('click', function() {
            $('.ac-stab').removeClass('active');
            $(this).addClass('active');
            $('.ac-tp').removeClass('active');
            $('#' + $(this).data('tab')).addClass('active');
        });

        // Topic management
        $(document).on('click', '#ac-topics-list .ac-b', function() { $(this).remove(); });
        $('#ac-s-new-topic').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var val = $.trim($(this).val());
                if (!val) return;
                $('#ac-topics-list').append('<span class="ac-b ac-b-s" style="cursor:pointer" data-topic="' + escHtml(val) + '">' + escHtml(val) + ' &times;</span>');
                $(this).val('');
            }
        });

        // Save Business & Agent
        $('#ac-save-business').on('click', function() {
            acAjax('save_agent_config', {
                agent_name: $('#ac-s-agent-name').val(),
                business_desc: $('#ac-s-biz-desc').val()
            }).then(function() { showToast('Settings saved.'); });
        });

        // Catalog toggles auto-save
        $(document).on('click', '.ac-catalog-toggle', function() {
            var vis = {};
            $('.ac-catalog-toggle').each(function() { vis[$(this).data('id')] = $(this).hasClass('on'); });
            acAjax('save_catalog', { visibility: JSON.stringify(vis) });
        });

        // Sync WooCommerce
        $('#ac-sync-wc').on('click', function() {
            $(this).text('Syncing...').prop('disabled', true);
            acAjax('start_scan').then(function() { location.reload(); });
        });

        // Add product modal (settings)
        $('#ac-show-add-product').on('click', function() {
            $('#ac-add-product-modal').addClass('active');
        });

        // Save Placement
        $('#ac-save-placement').on('click', function() {
            acAjax('save_placement', {
                widget: $('#ac-s-pl-widget').hasClass('on') ? 1 : 0,
                product_page: $('#ac-s-pl-product').hasClass('on') ? 1 : 0,
                clerk_page: $('#ac-s-pl-clerk').hasClass('on') ? 1 : 0,
                button_label: $('#ac-s-btn-label').val(),
                position: $('#ac-s-position').val()
            }).then(function() { showToast('Settings saved.'); });
        });

        // API Key show/hide
        $('#ac-show-api-key').on('click', function() {
            var $inp = $('#ac-s-api-key');
            if ($inp.attr('type') === 'password') {
                $inp.attr('type', 'text');
                $(this).text('Hide');
            } else {
                $inp.attr('type', 'password');
                $(this).text('Show');
            }
        });

        // Save API Key
        $('#ac-save-api-key').on('click', function() {
            var key = $('#ac-s-api-key').val();
            if (!key) { alert('Enter an API key.'); return; }
            acAjax('save_settings', { tab: 'api_key', api_key: key }).then(function() {
                showToast('API key updated.');
            });
        });

        // Save Support & Escalation
        $('#ac-save-support').on('click', function() {
            var topics = [];
            $('#ac-topics-list .ac-b').each(function() { topics.push($(this).data('topic')); });
            acAjax('save_agent_config', {
                escalation_email: $('#ac-s-escalation-email').val(),
                escalation_message: $('#ac-s-escalation-msg').val(),
                escalation_topics: JSON.stringify(topics),
                notification_method: $('#ac-s-notification-method').val(),
                support_file: $('#ac-s-support-file').val()
            }).then(function() { showToast('Settings saved.'); });
        });

        // Rescan
        $('#ac-rescan-btn').on('click', function() {
            $(this).text('Scanning...').prop('disabled', true);
            acAjax('start_scan').then(function() { location.reload(); });
        });

        // Catalog count
        var count = $('.ac-catalog-toggle').length;
        if (count > 0) { $('#ac-catalog-count').text(count + ' products \u00b7 synced from WooCommerce'); }
    })();

    /* =========================================================================
     * Conversations Page
     * ====================================================================== */

    (function initConversations() {
        if (!$('#ac-convo-tbody').length) return;

        function loadStats() {
            acAjax('get_stats').then(function(d) {
                $('#ac-cs-total').text(d.total || 0);
                $('#ac-cs-setup').text(d.setup || 0);
                $('#ac-cs-support').text(d.support || 0);
                $('#ac-cs-cart').text(d.in_cart || 0);
                $('#ac-cs-escalated').text(d.escalated || 0);
            });
        }

        function outcomeBadge(outcome) {
            var green = ['purchased', 'setup helped', 'support resolved'];
            var amber = ['escalated', 'in cart'];
            var cls = green.indexOf(outcome) !== -1 ? 'ac-b-g' : (amber.indexOf(outcome) !== -1 ? 'ac-b-a' : 'ac-b-s');
            return '<span class="ac-b ' + cls + '">' + escHtml(outcome || 'browsing') + '</span>';
        }

        function buyerBadge(type) {
            return type === 'ai_agent'
                ? '<span class="ac-b ac-b-e">AI agent</span>'
                : '<span class="ac-b ac-b-s">Human</span>';
        }

        function loadConversations(page) {
            var filter = $('#ac-convo-filter').val();
            var search = $.trim($('#ac-convo-search').val());

            acAjaxGet('get_conversations', { filter: filter, search: search, paged: page || 1 }).then(function(data) {
                var html = '';
                $.each(data.conversations || [], function(i, c) {
                    html += '<tr style="cursor:pointer" data-conversation-id="' + c.id + '">';
                    html += '<td style="font-family:\'DM Mono\',monospace;font-size:11px;color:var(--text3)">' + escHtml(c.started_at || '') + '</td>';
                    html += '<td>' + buyerBadge(c.buyer_type) + '</td>';
                    html += '<td style="color:var(--text2);font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(c.first_message || '') + '</td>';
                    html += '<td>' + outcomeBadge(c.outcome) + '</td>';
                    html += '<td style="font-size:12px">' + escHtml(c.product_name || '') + '</td>';
                    html += '<td style="font-family:\'DM Mono\',monospace;font-size:12px;font-weight:500">' + (c.sale_amount ? '$' + parseFloat(c.sale_amount).toFixed(0) : '\u2014') + '</td>';
                    html += '</tr>';
                });
                if (!html) {
                    html = '<tr><td colspan="6" style="color:var(--text3)">No conversations found.</td></tr>';
                }
                $('#ac-convo-tbody').html(html);
            });
        }

        loadStats();
        loadConversations(1);

        $('#ac-convo-filter').on('change', function() { loadConversations(1); });
        var searchTimer;
        $('#ac-convo-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() { loadConversations(1); }, 400);
        });

        // Transcript modal
        $(document).on('click', '#ac-convo-tbody tr[data-conversation-id]', function() {
            var id = $(this).data('conversation-id');
            if (!id) return;

            acAjaxGet('get_conversation_messages', { conversation_id: id }).then(function(data) {
                var html = '';
                $.each(data.messages || [], function(i, m) {
                    var cls = m.role === 'user' ? 'us' : 'ag';
                    var av = m.role === 'user' ? 'You' : 'AC';
                    html += '<div class="ac-msg ' + cls + '">';
                    html += '<div class="ac-mav">' + av + '</div>';
                    html += '<div class="ac-mbub">' + escHtml(m.content) + '</div>';
                    html += '</div>';
                });
                if (!html) html = '<p style="color:var(--text3)">No messages.</p>';
                $('#ac-transcript-content').html('<div style="display:flex;flex-direction:column;gap:10px">' + html + '</div>');
                $('#ac-transcript-modal').addClass('active');
            });
        });

        $('#ac-close-transcript').on('click', function() {
            $('#ac-transcript-modal').removeClass('active');
        });
        $('#ac-transcript-modal').on('click', function(e) {
            if (e.target === this) $(this).removeClass('active');
        });
    })();

    /* =========================================================================
     * Sales Page
     * ====================================================================== */

    (function initSales() {
        if (!$('#ac-tx-tbody').length) return;

        var period = 'month';

        function loadSales() {
            acAjaxGet('get_sales_data', { period: period }).then(function(d) {
                $('#ac-ss-gross').text('$' + parseFloat(d.gross || 0).toFixed(0));
                $('#ac-ss-count').text(d.count || 0);
                $('#ac-ss-avg').text('$' + parseFloat(d.average || 0).toFixed(2));
                $('#ac-ss-fees').text('$' + parseFloat(d.accrued_fees || 0).toFixed(2));
                $('#ac-ss-period-label').text(period === 'month' ? 'this month' : 'all time');

                // Billing progress
                var feePct = Math.min(100, (parseFloat(d.accrued_fees || 0) / 20) * 100);
                $('#ac-billing-progress').css('width', feePct + '%');
                $('#ac-billing-accrued').text('$' + parseFloat(d.accrued_fees || 0).toFixed(2) + ' of $20');
                $('#ac-billing-pct').text(Math.round(feePct) + '%');

                // Transaction table
                var html = '';
                $.each(d.transactions || [], function(i, t) {
                    var buyerBdg = t.buyer_type === 'ai_agent'
                        ? '<span class="ac-b ac-b-e">AI agent</span>'
                        : '<span class="ac-b ac-b-s">Human</span>';
                    html += '<tr>';
                    html += '<td style="font-family:\'DM Mono\',monospace;font-size:11px;color:var(--text3)">' + escHtml(t.updated_at || '') + '</td>';
                    html += '<td>' + escHtml(t.product_name || 'Unknown') + '</td>';
                    html += '<td style="font-family:\'DM Mono\',monospace;font-size:12px">$' + parseFloat(t.sale_amount || 0).toFixed(2) + '</td>';
                    html += '<td style="font-family:\'DM Mono\',monospace;font-size:12px;color:var(--text2)">$' + parseFloat(t.acclerk_fee || 0).toFixed(2) + '</td>';
                    html += '<td>' + buyerBdg + '</td>';
                    html += '</tr>';
                });
                if (!html) {
                    html = '<tr><td colspan="5" style="color:var(--text3)">No transactions yet.</td></tr>';
                }
                $('#ac-tx-tbody').html(html);
            });
        }

        loadSales();

        // Period toggle
        $('.ac-period-btn').on('click', function() {
            period = $(this).data('period');
            $('.ac-period-btn').removeClass('active');
            $(this).addClass('active');
            loadSales();
        });

        // Lifetime CTA
        $('#ac-sales-lifetime-btn, #ac-sales-lifetime-cta').on('click', function() {
            acAjax('lifetime_checkout').then(function(data) {
                if (data.checkoutUrl) window.location.href = data.checkoutUrl;
            });
        });
    })();

    /* =========================================================================
     * Support Page
     * ====================================================================== */

    (function initSupport() {
        if (!$('#ac-support-msgs').length) return;

        // Load escalations
        function loadEscalations() {
            acAjaxGet('toggle_escalation_read', { list: 1 }).then(function(data) {
                var items = data.escalations || [];
                var openCount = 0;
                var html = '';

                $.each(items, function(i, e) {
                    if (!e.read) openCount++;
                    var borderStyle = !e.read
                        ? 'border:2px solid var(--electric);background:var(--elec-lt);'
                        : 'border:1px solid var(--border);background:var(--white);';

                    html += '<div class="ac-escalation-card" style="' + borderStyle + 'border-radius:var(--r);padding:14px 15px;margin-bottom:10px;cursor:pointer" data-id="' + e.id + '">';
                    html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">';
                    html += '<div style="display:flex;align-items:center;gap:8px">';
                    html += '<span class="ac-b ac-b-a">' + (e.read ? 'Resolved' : 'Open') + '</span>';
                    html += '<span style="font-size:11px;color:var(--text3);font-family:\'DM Mono\',monospace">' + escHtml(e.created_at || '') + '</span>';
                    html += '</div>';
                    html += '<button class="ac-btn ac-btn-g ac-btn-sm ac-toggle-read" data-id="' + e.id + '">' + (e.read ? 'Mark unread' : 'Mark read') + '</button>';
                    html += '</div>';
                    html += '<div style="font-size:13px;font-weight:500;color:var(--text);margin-bottom:4px">' + escHtml(e.subject || e.email || 'No subject') + '</div>';
                    html += '<div style="font-size:12px;color:var(--text2);margin-bottom:8px;line-height:1.5">' + escHtml(e.first_message || '(no message)') + '</div>';
                    if (e.email) {
                        html += '<div style="font-size:11px;color:var(--text3);display:flex;align-items:center;gap:6px">';
                        html += '<span>\uD83D\uDCE7</span><span style="font-family:\'DM Mono\',monospace">' + escHtml(e.email) + '</span>';
                        html += '</div>';
                    }
                    html += '</div>';
                });

                $('#ac-open-count').text(openCount + ' open');
                $('#ac-escalation-list').html(html || '<div style="color:var(--text3);font-size:13px;padding:10px 0">No escalated conversations.</div>');

                if (data.resolved_count && data.resolved_count > 0) {
                    $('#ac-view-resolved').show().text('View ' + data.resolved_count + ' resolved escalations \u2192');
                }
            });
        }

        loadEscalations();

        $(document).on('click', '.ac-toggle-read', function(e) {
            e.stopPropagation();
            acAjax('toggle_read', { conversation_id: $(this).data('id') }).then(function() {
                loadEscalations();
            });
        });

        // Plugin help chat
        var supportHistory = [];
        addChatMessage('#ac-support-msgs', 'assistant',
            'Hi! I can help you with any AgentClerk questions \u2014 setup, settings, billing, troubleshooting, or how to handle specific buyer situations.<br><br>What do you need?');

        function sendSupport() {
            var txt = $.trim($('#ac-support-input').val());
            if (!txt) return;
            addChatMessage('#ac-support-msgs', 'user', escHtml(txt));
            supportHistory.push({ role: 'user', content: txt });
            $('#ac-support-input').val('');

            acAjax('support_chat', {
                message: txt,
                history: JSON.stringify(supportHistory)
            }).then(function(data) {
                addChatMessage('#ac-support-msgs', 'assistant', data.message);
                supportHistory.push({ role: 'assistant', content: data.message });
            }).catch(function(err) {
                addChatMessage('#ac-support-msgs', 'assistant', 'Error: ' + escHtml(err.message || 'Something went wrong.'));
            });
        }

        $('#ac-support-send').on('click', sendSupport);
        $('#ac-support-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendSupport(); }
        });
        $('.ac-chip').on('click', function() {
            $('#ac-support-input').val($(this).data('q') || $(this).text());
            sendSupport();
        });
    })();

    /* =========================================================================
     * Dashboard Page
     * ====================================================================== */

    (function initDashboard() {
        if (!$('#ac-dash-convos-today').length) return;

        acAjax('get_stats').then(function(d) {
            $('#ac-dash-convos-today').text(d.today || 0);
            $('#ac-dash-sales-today').text('$' + parseFloat(d.sales_today || 0).toFixed(2));
            $('#ac-dash-total-convos').text(d.total || 0);
            $('#ac-dash-escalated').text(d.escalated || 0);
        });

        // Lifetime CTA
        $('#ac-lifetime-license-cta, #ac-lifetime-cta-bar').on('click', function() {
            acAjax('lifetime_checkout').then(function(data) {
                if (data.checkoutUrl) window.location.href = data.checkoutUrl;
            });
        });
    })();

    /* =========================================================================
     * Suspended Page
     * ====================================================================== */

    (function initSuspended() {
        if (!$('#ac-update-payment').length) return;

        $('#ac-update-payment').on('click', function() {
            $(this).prop('disabled', true).text('Loading...');
            acAjax('card_update').then(function(data) {
                if (data.portalUrl) window.location.href = data.portalUrl;
                else {
                    alert('Could not load billing portal. Please try again.');
                    $('#ac-update-payment').prop('disabled', false).text('Update payment method');
                }
            }).catch(function() {
                alert('Could not load billing portal. Please try again.');
                $('#ac-update-payment').prop('disabled', false).text('Update payment method');
            });
        });
    })();

})(jQuery);
