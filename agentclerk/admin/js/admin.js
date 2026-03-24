/**
 * AgentClerk Admin JS
 *
 * Single file handling all admin interactions: onboarding (steps 1-6),
 * dashboard, conversations, sales, support, settings, and suspended pages.
 *
 * Depends on jQuery (WordPress built-in) and the `agentclerk` localized object
 * provided by wp_localize_script in class-admin.php.
 *
 * @since 1.0.0
 */
(function($) {
    'use strict';

    /* =========================================================================
     * Section 1 - Toast Notification System
     * ====================================================================== */

    /**
     * Show a toast notification that auto-dismisses after 3 seconds.
     *
     * @param {string} message - The message to display.
     * @param {string} type    - 'success' (default), 'error', or 'info'.
     */
    function showToast(message, type) {
        type = type || 'success';

        var iconMap = {
            success: '&#10003;',
            error:   '&#10008;',
            info:    '&#8505;'
        };
        var clsMap = {
            success: 'ac-callout-green',
            error:   'ac-callout-amber',
            info:    'ac-callout-slate'
        };

        var icon = iconMap[type] || iconMap.success;
        var cls  = clsMap[type] || clsMap.success;

        var $toast = $(
            '<div class="ac-callout ' + cls + '" style="position:fixed;top:40px;right:20px;z-index:100002;min-width:200px;box-shadow:0 4px 12px rgba(0,0,0,.15);transition:opacity .3s">' +
                '<span class="ac-callout-icon">' + icon + '</span>' +
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
     * Section 2 - AJAX Helper
     * ====================================================================== */

    /**
     * Send an AJAX request to the WordPress admin-ajax endpoint.
     * Automatically includes the nonce.
     *
     * @param {string} action - The AJAX action name (without agentclerk_ prefix).
     * @param {Object} data   - Additional data to send.
     * @returns {Promise} Resolves with the parsed response, rejects on error.
     */
    function acAjax(action, data) {
        data = data || {};
        data.action = 'agentclerk_' + action;
        data.nonce  = agentclerk.nonce;

        return new Promise(function(resolve, reject) {
            $.post(agentclerk.ajaxUrl, data, function(resp) {
                if (resp.success) {
                    resolve(resp.data);
                } else {
                    reject(resp.data || { message: 'Request failed.' });
                }
            }).fail(function(xhr, status, err) {
                reject({ message: err || 'Network error.' });
            });
        });
    }

    /**
     * Send a GET AJAX request.
     *
     * @param {string} action - The AJAX action name (without agentclerk_ prefix).
     * @param {Object} data   - Additional query data.
     * @returns {Promise}
     */
    function acAjaxGet(action, data) {
        data = data || {};
        data.action = 'agentclerk_' + action;
        data.nonce  = agentclerk.nonce;

        return new Promise(function(resolve, reject) {
            $.get(agentclerk.ajaxUrl, data, function(resp) {
                if (resp.success) {
                    resolve(resp.data);
                } else {
                    reject(resp.data || { message: 'Request failed.' });
                }
            }).fail(function(xhr, status, err) {
                reject({ message: err || 'Network error.' });
            });
        });
    }

    /* =========================================================================
     * Section 3 - Shared Helpers
     * ====================================================================== */

    /**
     * Escape a string for safe HTML insertion.
     *
     * @param {string} str - Raw string.
     * @return {string} HTML-safe string.
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Navigate to an admin page by slug.
     *
     * @param {string} page - The page slug (e.g. 'agentclerk-onboarding').
     */
    function goToPage(page) {
        window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=' + page);
    }

    /**
     * Add a chat message bubble to a container.
     *
     * @param {string} containerId - jQuery selector for the messages container.
     * @param {string} role        - 'assistant' or 'user'.
     * @param {string} text        - HTML content of the message.
     */
    function addChatMessage(containerId, role, text) {
        var cls = role === 'assistant' ? 'ac-msg-agent' : 'ac-msg-user';
        var av  = role === 'assistant' ? 'AC' : 'You';
        var $container = $(containerId);
        $container.append(
            '<div class="ac-msg ' + cls + '">' +
                '<div class="ac-msg-avatar">' + av + '</div>' +
                '<div class="ac-msg-bubble">' + text + '</div>' +
            '</div>'
        );
        $container.scrollTop($container[0].scrollHeight);
    }

    /**
     * Render clickable chip buttons into a container.
     *
     * @param {string}   containerId - jQuery selector for the chips container.
     * @param {string[]} chips       - Array of chip label strings.
     * @param {Function} onClick     - Callback invoked with the chip text.
     */
    function setChips(containerId, chips, onClick) {
        var $row = $(containerId).empty();
        if (!chips || !chips.length) return;
        chips.forEach(function(label) {
            $('<span class="ac-chip">' + label + '</span>').on('click', function() {
                if (typeof onClick === 'function') onClick(label);
            }).appendTo($row);
        });
    }

    /* =========================================================================
     * Section 4 - URL Parameter Handlers (global)
     * ====================================================================== */

    var urlParams = new URLSearchParams(window.location.search);

    // Handle TurnKey success return -- advance to step 2
    if (urlParams.get('turnkey_success') === '1') {
        acAjax('save_onboarding_step', { step: 2 }).then(function() {
            goToPage('agentclerk-onboarding');
        });
    }

    // Handle license activation return
    if (urlParams.get('license_success') === '1') {
        $('<div class="notice notice-success is-dismissible"><p>Lifetime license activated. No more transaction fees.</p></div>')
            .insertAfter('.wrap h1:first');
    }

    // Refresh billing status on sales page visit
    if (urlParams.get('page') === 'agentclerk-sales' && !urlParams.get('license_success')) {
        acAjax('get_stats', {});
    }

    /* =========================================================================
     * Section 5 - Onboarding Step 1: Tier Selection & Payment
     * ====================================================================== */

    (function initStep1() {
        if (!$('#tier-selection').length) return;

        var selectedTier = 'byok';
        var apiKeyValid  = false;
        var stripe, cardElement, cardElementTurnkey;

        // -- Stripe Elements initialization --
        if (typeof Stripe !== 'undefined' && typeof agentclerkStripe !== 'undefined') {
            stripe = Stripe(agentclerkStripe.publishableKey);
            var elements = stripe.elements();
            cardElement        = elements.create('card');
            cardElementTurnkey = elements.create('card');
            cardElement.mount('#stripe-card-element');
        }

        // -- Tier card toggle --
        $('.ac-tier-card').on('click', function() {
            selectedTier = $(this).data('tier');
            $('.ac-tier-card').removeClass('sel');
            $(this).addClass('sel');

            if (selectedTier === 'byok') {
                $('#sec-byok').show();
                $('#sec-tk').hide();
                if (cardElement) cardElement.mount('#stripe-card-element');
            } else {
                $('#sec-byok').hide();
                $('#sec-tk').show();
                if (cardElementTurnkey) cardElementTurnkey.mount('#stripe-card-element-turnkey');
            }
        });

        // -- API Key show/hide toggle --
        $(document).on('click', '.ac-key-toggle', function() {
            var $inp = $('#api-key');
            if ($inp.attr('type') === 'password') {
                $inp.attr('type', 'text');
                $(this).text('Hide');
            } else {
                $inp.attr('type', 'password');
                $(this).text('Show');
            }
        });

        // -- Validate API Key --
        $('#validate-api-key').on('click', function() {
            var key = $('#api-key').val();
            if (!key) {
                showToast('Please enter an API key.', 'error');
                return;
            }
            var $box = $('#api-key-status-box');
            $box.html('<div class="ac-callout ac-callout-slate"><span class="ac-callout-icon"><span class="ac-spinner"></span></span><span>Validating API key...</span></div>');

            acAjax('validate_api_key', { api_key: key }).then(function() {
                $box.html('<div class="ac-callout ac-callout-green"><span class="ac-callout-icon">&#10003;</span><span>API key validated. Model access confirmed.</span></div>');
                apiKeyValid = true;
                $('#submit-byok').prop('disabled', false);
            }).catch(function(err) {
                $box.html('<div class="ac-callout ac-callout-amber"><span class="ac-callout-icon">&#10008;</span><span>' + (err.message || 'Invalid API key.') + '</span></div>');
                apiKeyValid = false;
                $('#submit-byok').prop('disabled', true);
            });
        });

        // -- Submit BYOK registration --
        $('#submit-byok').on('click', function() {
            if (!apiKeyValid) return;
            var $btn = $(this).prop('disabled', true).text('Processing...');

            if (stripe && cardElement) {
                stripe.createPaymentMethod({ type: 'card', card: cardElement }).then(function(result) {
                    if (result.error) {
                        $('#stripe-card-errors').text(result.error.message);
                        $btn.prop('disabled', false).html('Scan my site and start setup &rarr;');
                        return;
                    }
                    submitRegistration('byok', result.paymentMethod.id, $('#api-key').val());
                });
            } else {
                submitRegistration('byok', '', $('#api-key').val());
            }
        });

        // -- Submit TurnKey registration --
        $('#submit-turnkey').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Processing...');

            if (stripe && cardElementTurnkey) {
                stripe.createPaymentMethod({ type: 'card', card: cardElementTurnkey }).then(function(result) {
                    if (result.error) {
                        $('#stripe-card-errors-turnkey').text(result.error.message);
                        $btn.prop('disabled', false).html('Pay $99 and continue &rarr;');
                        return;
                    }
                    submitRegistration('turnkey', result.paymentMethod.id, '');
                });
            } else {
                submitRegistration('turnkey', '', '');
            }
        });

        // -- Lifetime license CTA --
        $('#lifetime-cta-bar .ac-lifetime-btn').on('click', function() {
            acAjax('lifetime_checkout', {}).then(function(data) {
                if (data && data.checkoutUrl) {
                    window.location.href = data.checkoutUrl;
                }
            }).catch(function() {
                showToast('Could not start checkout. Please try again.', 'error');
            });
        });

        /**
         * Submit tier registration to the backend.
         */
        function submitRegistration(tier, paymentMethodId, apiKey) {
            acAjax('register_install', {
                tier: tier,
                stripe_payment_method_id: paymentMethodId,
                api_key: apiKey
            }).then(function(data) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    goToPage('agentclerk-onboarding');
                }
            }).catch(function(err) {
                showToast(err.message || 'Registration failed.', 'error');
                $('.ac-btn').prop('disabled', false);
            });
        }
    })();

    /* =========================================================================
     * Section 6 - Onboarding Step 2: Site Scan
     * ====================================================================== */

    (function initStep2() {
        if (!$('#start-scan').length && !$('#scan-running').length) return;

        var pollTimer = null;

        // -- Start scan --
        $('#start-scan').on('click', function() {
            $(this).prop('disabled', true);
            $('#scan-intro').hide();
            $('#scan-running').show();

            acAjax('start_scan', {}).then(function(data) {
                // Scan completed synchronously
                clearPoll();
                markScanComplete(data);
            }).catch(function() {
                // Scan started asynchronously, polling will track it
            });

            startPolling();
        });

        // -- Scan continue button --
        $('#scan-continue').on('click', function() {
            goToPage('agentclerk-onboarding');
        });

        /**
         * Start polling scan progress every 2 seconds.
         */
        function startPolling() {
            pollTimer = setInterval(function() {
                acAjax('scan_progress', {}).then(function(d) {
                    if (!d) return;

                    var total = d.total || 0;
                    var done  = d.completed || 0;

                    // Update counter text
                    if (total > 0) {
                        $('#scan-status-text').text(done + ' of ' + total + ' pages read. Do not close this tab.');
                    }

                    // Update scan log
                    var log = '<div class="ac-scan-heading">sitemap crawl</div>';
                    log += '<div><span class="ac-scan-ok">&#10003;</span> ' + (total || '?') + ' URLs found</div>';
                    log += '<br><div class="ac-scan-heading">pages read</div>';
                    if (d.pages) {
                        d.pages.forEach(function(p) {
                            log += '<div><span class="ac-scan-ok">&#10003;</span> ' + p + '</div>';
                        });
                    }
                    if (done < total) {
                        log += '<div><span class="ac-spinner"></span><span class="ac-scan-dim">Reading pages&hellip;</span></div>';
                    }
                    $('#scan-log').html(log);

                    // Update findings panel
                    updateFindings(d);

                    // Check completion
                    if (d.status === 'complete') {
                        clearPoll();
                        markScanComplete(d);
                    }
                });
            }, 2000);
        }

        /**
         * Clear the polling timer.
         */
        function clearPoll() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        /**
         * Mark the scan as complete in the UI.
         */
        function markScanComplete(data) {
            $('#scan-badge').html('&#10003; Complete').removeClass('ac-badge-amber').addClass('ac-badge-green');
            $('#scan-done').show();
            $('#scan-status-text').text('Scan complete.');
            if (data) updateFindings(data);
        }

        /**
         * Update the "Found so far" findings panel.
         */
        function updateFindings(d) {
            var html = '';
            if (d.products_found) {
                html += '<div class="ac-callout ac-callout-green"><span class="ac-callout-icon">&#10003;</span><span><strong>' + d.products_found + ' products</strong> with names, prices, and descriptions.</span></div>';
            }
            if (d.refund_found) {
                html += '<div class="ac-callout ac-callout-green"><span class="ac-callout-icon">&#10003;</span><span><strong>Refund policy</strong> found.</span></div>';
            }
            if (d.license_found) {
                html += '<div class="ac-callout ac-callout-green"><span class="ac-callout-icon">&#10003;</span><span><strong>License terms</strong> found.</span></div>';
            }
            if (d.blog_found) {
                html += '<div class="ac-callout ac-callout-green"><span class="ac-callout-icon">&#10003;</span><span><strong>Blog content</strong> found.</span></div>';
            }
            if (d.gaps) {
                d.gaps.forEach(function(g) {
                    html += '<div class="ac-callout ac-callout-amber"><span class="ac-callout-icon">&#9888;</span><span><strong>' + g + '</strong></span></div>';
                });
            }
            if (html) $('#scan-findings').html(html);
        }
    })();

    /* =========================================================================
     * Section 7 - Onboarding Step 3: Review & Gap Fill
     * ====================================================================== */

    (function initStep3() {
        if (!$('#chat-messages').length || !$('#step3-continue').length) return;

        var chatHistory = [];

        // Parse gaps from the embedded PHP data (set in script tag on the page)
        // We detect them by the presence of the gap-fill chat interface
        var gaps = [];
        try {
            // Gaps are injected as a JS variable in the PHP template
            if (typeof window.acGaps !== 'undefined') {
                gaps = window.acGaps;
            }
        } catch (e) { /* ignore */ }

        // Initial assistant message
        if (gaps.length > 0) {
            addChatMessage('#chat-messages', 'assistant',
                'The scan went well &mdash; I found your products, pricing, and policies. Just a few things I couldn\'t find automatically.<br><br>First: <strong>how do you want me to notify you when a buyer has a question I can\'t resolve?</strong>'
            );
            setChips('#chat-chips', ['Both email and WP notification', 'Email only', 'WP admin only'], function(text) {
                $('#chat-input').val(text);
                sendStep3Message();
            });
        } else {
            addChatMessage('#chat-messages', 'assistant',
                'Your site looks well-configured! I found everything I need. You can edit the support file on the left, or continue to the catalog.'
            );
        }

        /**
         * Send a message in the step 3 gap-fill chat.
         */
        function sendStep3Message() {
            var txt = $.trim($('#chat-input').val());
            if (!txt) return;

            addChatMessage('#chat-messages', 'user', txt);
            chatHistory.push({ role: 'user', content: txt });
            $('#chat-input').val('');
            $('#chat-chips').empty();

            acAjax('onboarding_chat', {
                message: txt,
                context: 'gap_fill',
                history: JSON.stringify(chatHistory)
            }).then(function(data) {
                if (data && data.message) {
                    addChatMessage('#chat-messages', 'assistant', data.message);
                    chatHistory.push({ role: 'assistant', content: data.message });
                    if (data.chips) {
                        setChips('#chat-chips', data.chips, function(chipText) {
                            $('#chat-input').val(chipText);
                            sendStep3Message();
                        });
                    }
                    // If the response updates agent config, store it locally
                    if (data.agent_config) {
                        window.acAgentConfig = data.agent_config;
                    }
                }
            }).catch(function(err) {
                addChatMessage('#chat-messages', 'assistant', 'Error: ' + (err.message || 'Something went wrong.'));
            });
        }

        // -- Chat send button and Enter key --
        $('#chat-send').on('click', sendStep3Message);
        $('#chat-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendStep3Message();
            }
        });

        // -- Continue to catalog --
        $('#step3-continue').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Saving...');
            var saveData = {
                support_file: $('#support-file').val()
            };

            // Include agent config if it was updated by chat
            if (window.acAgentConfig) {
                saveData.agent_config = JSON.stringify(window.acAgentConfig);
            }

            acAjax('save_agent_config', saveData).then(function() {
                return acAjax('save_onboarding_step', { step: 4 });
            }).then(function() {
                goToPage('agentclerk-onboarding');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save.', 'error');
                $btn.prop('disabled', false).html('Continue to catalog &rarr;');
            });
        });
    })();

    /* =========================================================================
     * Section 8 - Onboarding Step 4: Catalog
     * ====================================================================== */

    (function initStep4() {
        if (!$('#step4-continue').length) return;

        // -- Toggle switches --
        $(document).on('click', '.ac-toggle', function() {
            $(this).toggleClass('on');
        });

        // -- Show add product form --
        $('#show-add-product').on('click', function() {
            $('#add-product-form').toggle();
        });

        // -- Add product --
        $('#add-product').on('click', function() {
            var name = $.trim($('#new-product-name').val());
            if (!name) {
                showToast('Product name is required.', 'error');
                return;
            }

            var $btn = $(this).prop('disabled', true).text('Adding...');

            acAjax('add_product', {
                name:        name,
                type:        'simple',
                price:       $('#new-product-price').val(),
                description: $('#new-product-desc').val()
            }).then(function() {
                showToast('Product added.');
                location.reload();
            }).catch(function(err) {
                showToast(err.message || 'Failed to add product.', 'error');
                $btn.prop('disabled', false).text('Add Product');
            });
        });

        // -- Continue to placement --
        $('#step4-continue').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Saving...');
            var visibility = {};
            $('.ac-toggle').each(function() {
                visibility[$(this).data('id')] = $(this).hasClass('on');
            });

            acAjax('save_catalog', {
                visibility: JSON.stringify(visibility)
            }).then(function() {
                return acAjax('save_onboarding_step', { step: 5 });
            }).then(function() {
                goToPage('agentclerk-onboarding');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save catalog.', 'error');
                $btn.prop('disabled', false).html('Continue to placement &rarr;');
            });
        });
    })();

    /* =========================================================================
     * Section 9 - Onboarding Step 5: Placement
     * ====================================================================== */

    (function initStep5() {
        if (!$('#step5-continue').length) return;

        // -- Placement card toggles --
        $('.ac-placement-card').on('click', function() {
            $(this).toggleClass('on');
        });

        // -- Continue to test & go live --
        $('#step5-continue').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Saving...');

            acAjax('save_placement', {
                widget:       $('#pl-widget').hasClass('on') ? 1 : 0,
                product_page: $('#pl-product').hasClass('on') ? 1 : 0,
                clerk_page:   $('#pl-clerk').hasClass('on') ? 1 : 0,
                button_label: $('#button-label').val(),
                agent_name:   $('#agent-name').val(),
                position:     $('#widget-position').val()
            }).then(function() {
                return acAjax('save_onboarding_step', { step: 6 });
            }).then(function() {
                goToPage('agentclerk-onboarding');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save placement.', 'error');
                $btn.prop('disabled', false).html('Test and go live &rarr;');
            });
        });
    })();

    /* =========================================================================
     * Section 10 - Onboarding Step 6: Test & Go Live
     * ====================================================================== */

    (function initStep6() {
        if (!$('#test-messages').length || !$('#go-live').length) return;

        // -- Initial greeting --
        addChatMessage('#test-messages', 'assistant', 'Hi! I can help you find the right product. What are you looking for?');

        /**
         * Send a test chat message.
         */
        function sendTestMessage() {
            var txt = $.trim($('#test-input').val());
            if (!txt) return;

            addChatMessage('#test-messages', 'user', txt);
            $('#test-input').val('');

            acAjax('chat', {
                message:   txt,
                test_mode: '1'
            }).then(function(data) {
                addChatMessage('#test-messages', 'assistant', data.message);
            }).catch(function(err) {
                addChatMessage('#test-messages', 'assistant', 'Error: ' + (err.message || 'Something went wrong.'));
            });
        }

        // -- Send button and Enter key --
        $('#test-send').on('click', sendTestMessage);
        $('#test-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendTestMessage();
            }
        });

        // -- Sample prompt chips --
        $('.ac-chip[data-q]').on('click', function() {
            $('#test-input').val($(this).data('q'));
            sendTestMessage();
        });

        // -- Go live button --
        $('#go-live').on('click', function() {
            if (!confirm('Ready to go live? Your AI agent will start handling real conversations.')) return;

            var $btn = $(this).prop('disabled', true).text('Going live...');

            acAjax('go_live', {}).then(function(data) {
                if (data && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    goToPage('agentclerk');
                }
            }).catch(function(err) {
                showToast('Failed: ' + (err.message || 'Unknown error'), 'error');
                $btn.prop('disabled', false).html('Go live &rarr;');
            });
        });
    })();

    /* =========================================================================
     * Section 11 - Settings Page (5 Tabs)
     * ====================================================================== */

    (function initSettings() {
        if (!$('.ac-settings-tabs').length) return;

        // -- Tab switching --
        $('.ac-settings-tab').on('click', function() {
            $('.ac-settings-tab').removeClass('active');
            $(this).addClass('active');
            $('.ac-tab-panel').removeClass('active');
            $('#' + $(this).data('tab')).addClass('active');
        });

        // -- Toggle switches and placement cards in settings --
        $(document).on('click', '.ac-toggle, .ac-placement-card', function() {
            $(this).toggleClass('on');
        });

        // -- Topic tags: add on Enter --
        $('#s-new-topic').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var val = $.trim($(this).val());
                if (!val) return;
                $('#topics-list').append(
                    '<span class="ac-badge ac-badge-slate" style="cursor:pointer" data-topic="' + $('<span>').text(val).html() + '">' + $('<span>').text(val).html() + ' &times;</span>'
                );
                $(this).val('');
            }
        });

        // -- Topic tags: remove on click --
        $(document).on('click', '#topics-list .ac-badge', function() {
            $(this).remove();
        });

        // -- Tab 1: Business & Agent: Save --
        $('#save-business').on('click', function() {
            acAjax('save_agent_config', {
                agent_name:    $('#s-agent-name').val(),
                business_desc: $('#s-biz-desc').val(),
                policies: JSON.stringify({
                    refund:   $('#s-policies-refund').val(),
                    license:  $('#s-policies-license').val(),
                    delivery: $('#s-policies-delivery').val()
                })
            }).then(function() {
                showToast('Settings saved.');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save.', 'error');
            });
        });

        // -- Tab 1: Rescan --
        $('#rescan-btn').on('click', function() {
            var $btn = $(this).text('Scanning...').prop('disabled', true);
            acAjax('start_scan', {}).then(function() {
                showToast('Scan complete.');
                location.reload();
            }).catch(function(err) {
                showToast(err.message || 'Scan failed.', 'error');
                $btn.text('\u21BB Scan now').prop('disabled', false);
            });
        });

        // -- Tab 2: Catalog: Save --
        $('#save-catalog').on('click', function() {
            var vis = {};
            $('.catalog-tog').each(function() {
                vis[$(this).data('id')] = $(this).hasClass('on');
            });
            acAjax('save_catalog', {
                visibility: JSON.stringify(vis)
            }).then(function() {
                showToast('Catalog saved.');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save catalog.', 'error');
            });
        });

        // -- Tab 3: Placement: Save --
        $('#save-placement').on('click', function() {
            acAjax('save_placement', {
                widget:       $('#s-pl-widget').hasClass('on') ? 1 : 0,
                product_page: $('#s-pl-product').hasClass('on') ? 1 : 0,
                clerk_page:   $('#s-pl-clerk').hasClass('on') ? 1 : 0,
                button_label: $('#s-btn-label').val(),
                position:     $('#s-position').val()
            }).then(function() {
                showToast('Placement saved.');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save placement.', 'error');
            });
        });

        // -- Tab 4: API Key: Save --
        $('#save-api-key').on('click', function() {
            var key = $('#s-api-key').val();
            if (!key) {
                showToast('Enter an API key.', 'error');
                return;
            }
            acAjax('save_settings', {
                tab:     'api_key',
                api_key: key
            }).then(function() {
                showToast('API key updated.');
                $('#s-api-key').val('');
            }).catch(function(err) {
                showToast(err.message || 'Failed to update API key.', 'error');
            });
        });

        // -- Tab 4: API Key show/hide toggle --
        $(document).on('click', '.ac-key-toggle', function() {
            var $inp = $('#s-api-key');
            if ($inp.attr('type') === 'password') {
                $inp.attr('type', 'text');
                $(this).text('Hide');
            } else {
                $inp.attr('type', 'password');
                $(this).text('Show');
            }
        });

        // -- Tab 5: Support & Escalation: Save --
        $('#save-support').on('click', function() {
            var topics = [];
            $('#topics-list .ac-badge').each(function() {
                topics.push($(this).data('topic'));
            });
            acAjax('save_agent_config', {
                escalation_email:   $('#s-escalation-email').val(),
                escalation_message: $('#s-escalation-msg').val(),
                escalation_topics:  JSON.stringify(topics),
                support_file:       $('#s-support-file').val()
            }).then(function() {
                showToast('Support settings saved.');
            }).catch(function(err) {
                showToast(err.message || 'Failed to save.', 'error');
            });
        });
    })();

    /* =========================================================================
     * Section 12 - Conversations Page
     * ====================================================================== */

    (function initConversations() {
        if (!$('#convo-tbody').length) return;

        var searchTimer = null;

        /**
         * Map outcome value to badge CSS class.
         */
        function badgeCls(val) {
            var map = {
                purchased:          'ac-badge-green',
                'setup helped':     'ac-badge-green',
                'support resolved': 'ac-badge-green',
                escalated:          'ac-badge-amber',
                browsing:           'ac-badge-slate',
                quote:              'ac-badge-electric',
                abandoned:          'ac-badge-slate'
            };
            return map[val] || 'ac-badge-slate';
        }

        /**
         * Render buyer type badge.
         */
        function buyerBadge(type) {
            return type === 'ai_agent'
                ? '<span class="ac-badge ac-badge-electric">AI agent</span>'
                : '<span class="ac-badge ac-badge-slate">Human</span>';
        }

        /**
         * Load conversation stats.
         */
        function loadStats() {
            acAjax('get_stats', {}).then(function(d) {
                $('#cs-total').text(d.total);
                $('#cs-setup').text(d.setup);
                $('#cs-support').text(d.support);
                $('#cs-cart').text(d.in_cart);
                $('#cs-escalated').text(d.escalated);
            });
        }

        /**
         * Load conversations list with current filter, search, and page.
         */
        function loadConversations(page) {
            var params = {
                outcome: $('#convo-filter').val(),
                search:  $('#convo-search').val() || '',
                paged:   page || 1
            };

            acAjaxGet('get_conversations', params).then(function(data) {
                var html = '';
                var convos = data.conversations || [];

                $.each(convos, function(i, c) {
                    html += '<tr style="cursor:pointer" data-id="' + c.id + '">';
                    html += '<td class="ac-mono" style="font-size:11px;color:var(--ac-text3)">' + (c.started_at || '') + '</td>';
                    html += '<td>' + buyerBadge(c.buyer_type) + '</td>';
                    html += '<td style="color:var(--ac-text2);font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(c.first_message || '') + '</td>';
                    html += '<td><span class="ac-badge ' + badgeCls(c.outcome) + '">' + escHtml(c.outcome || 'browsing') + '</span></td>';
                    html += '<td style="font-size:12px">' + escHtml(c.product_name || '—') + '</td>';
                    html += '<td class="ac-mono" style="font-weight:500">' + (c.sale_amount ? '$' + parseFloat(c.sale_amount).toFixed(0) : '&mdash;') + '</td>';
                    html += '</tr>';
                });

                $('#convo-tbody').html(html || '<tr><td colspan="6" style="color:var(--ac-text3)">No conversations found.</td></tr>');

                // Pagination
                if (data.total_pages && data.total_pages > 1) {
                    var pagHtml = '';
                    for (var p = 1; p <= data.total_pages; p++) {
                        var activeCls = p === (page || 1) ? ' style="font-weight:700"' : '';
                        pagHtml += '<a href="#" class="convo-page" data-page="' + p + '"' + activeCls + '>' + p + '</a> ';
                    }
                    $('#convo-pagination').html(pagHtml);
                } else {
                    $('#convo-pagination').empty();
                }
            });
        }

        // -- Initial load --
        loadStats();
        loadConversations(1);

        // -- Filter change --
        $('#convo-filter').on('change', function() {
            loadConversations(1);
        });

        // -- Search input (debounced) --
        $('#convo-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                loadConversations(1);
            }, 400);
        });

        // -- Pagination clicks --
        $(document).on('click', '.convo-page', function(e) {
            e.preventDefault();
            loadConversations($(this).data('page'));
        });

        // -- Row click: open transcript modal --
        $(document).on('click', '#convo-tbody tr', function() {
            var id = $(this).data('id');
            if (!id) return;

            acAjaxGet('get_conversation_messages', { conversation_id: id }).then(function(data) {
                var html = '';
                var messages = data.messages || [];

                $.each(messages, function(i, m) {
                    var cls = m.role === 'user' ? 'ac-msg-user' : 'ac-msg-agent';
                    var av  = m.role === 'user' ? 'You' : 'AC';
                    html += '<div class="ac-msg ' + cls + '" style="max-width:95%">' +
                        '<div class="ac-msg-avatar">' + av + '</div>' +
                        '<div class="ac-msg-bubble">' + $('<span>').text(m.content).html() + '</div>' +
                    '</div>';
                });

                $('#transcript-content').html(
                    '<div style="display:flex;flex-direction:column;gap:10px">' +
                    (html || '<p style="color:var(--ac-text3)">No messages.</p>') +
                    '</div>'
                );
                $('#transcript-modal').addClass('active');
            });
        });

        // -- Modal close --
        $('#close-modal').on('click', function() {
            $('#transcript-modal').removeClass('active');
        });
        $('#transcript-modal').on('click', function(e) {
            if (e.target === this) $(this).removeClass('active');
        });
    })();

    /* =========================================================================
     * Section 13 - Sales Page
     * ====================================================================== */

    (function initSales() {
        if (!$('#tx-tbody').length) return;

        var period = 'month';

        /**
         * Load sales data for the selected period.
         */
        function loadSales() {
            acAjaxGet('get_sales', { period: period }).then(function(d) {
                $('#ss-gross').text('$' + parseFloat(d.gross).toFixed(2));
                $('#ss-count').text(d.count);
                $('#ss-avg').text('$' + parseFloat(d.average).toFixed(2));
                $('#ss-fees').text('$' + parseFloat(d.accrued_fees).toFixed(2));
                $('#ss-period-label').text(period === 'month' ? 'this month' : 'all time');

                var html = '';
                $.each(d.transactions || [], function(i, t) {
                    var buyerBadge = t.buyer_type === 'ai_agent'
                        ? '<span class="ac-badge ac-badge-electric">AI agent</span>'
                        : '<span class="ac-badge ac-badge-slate">Human</span>';
                    html += '<tr>';
                    html += '<td class="ac-mono" style="font-size:11px;color:var(--ac-text3)">' + (t.updated_at || '') + '</td>';
                    html += '<td>' + (t.product_name || 'Unknown') + '</td>';
                    html += '<td class="ac-mono">$' + parseFloat(t.sale_amount).toFixed(2) + '</td>';
                    html += '<td class="ac-mono" style="color:var(--ac-text2)">$' + parseFloat(t.acclerk_fee).toFixed(2) + '</td>';
                    html += '<td>' + buyerBadge + '</td>';
                    html += '</tr>';
                });

                $('#tx-tbody').html(html || '<tr><td colspan="5" style="color:var(--ac-text3)">No transactions yet.</td></tr>');
            });
        }

        // -- Initial load --
        loadSales();

        // -- Period toggle --
        $('.ac-period-btn').on('click', function() {
            period = $(this).data('period');
            $('.ac-period-btn').removeClass('active');
            $(this).addClass('active');
            loadSales();
        });

        // -- Lifetime license CTA --
        $('#lifetime-cta, #lifetime-cta-bar').on('click', function() {
            acAjax('lifetime_checkout', {}).then(function(data) {
                if (data && data.checkoutUrl) {
                    window.location.href = data.checkoutUrl;
                }
            }).catch(function() {
                showToast('Could not start checkout.', 'error');
            });
        });

        // -- Update payment method --
        $('#update-card').on('click', function(e) {
            e.preventDefault();
            acAjax('card_update', {}).then(function(data) {
                if (data && data.portalUrl) {
                    window.location.href = data.portalUrl;
                }
            }).catch(function() {
                showToast('Could not load billing portal.', 'error');
            });
        });
    })();

    /* =========================================================================
     * Section 14 - Support Page
     * ====================================================================== */

    (function initSupport() {
        if (!$('#escalation-list').length || !$('#support-messages').length) return;

        var supportHistory = [];

        // -- Load escalations --
        function loadEscalations(filter) {
            acAjaxGet('get_escalations', { filter: filter || '' }).then(function(data) {
                var escalations = data.escalations || [];
                var html = '';

                $.each(escalations, function(i, e) {
                    var readCls = e.read ? 'read' : '';
                    html += '<div class="ac-escalation-card ' + readCls + '" data-id="' + e.id + '">';
                    html += '<div class="ac-flex-between" style="margin-bottom:6px">';
                    html += '<strong style="font-size:13px">' + (e.email || 'No email') + '</strong>';
                    html += '<button class="ac-btn ac-btn-ghost ac-btn-sm toggle-read" data-id="' + e.id + '">' + (e.read ? 'Mark Unread' : 'Mark Read') + '</button>';
                    html += '</div>';
                    html += '<div style="font-size:12px;color:var(--ac-text2);margin-bottom:4px">' + $('<span>').text(e.first_message || '(no message)').html() + '</div>';
                    html += '<div style="font-size:11px;color:var(--ac-text3)">' + e.created_at + '</div>';
                    if (e.conversation_id) {
                        html += '<a href="#" class="view-transcript" data-id="' + e.conversation_id + '" style="font-size:11px;margin-top:4px;display:inline-block">View full transcript</a>';
                    }
                    html += '</div>';
                });

                $('#escalation-list').html(html || '<div style="color:var(--ac-text3);font-size:13px;padding:10px 0">No escalated conversations.</div>');
            });
        }

        loadEscalations();

        // -- Select escalation card --
        $(document).on('click', '.ac-escalation-card', function() {
            $('.ac-escalation-card').removeClass('selected');
            $(this).addClass('selected');
        });

        // -- Toggle read status --
        $(document).on('click', '.toggle-read', function(e) {
            e.stopPropagation();
            var cardId = $(this).data('id');
            acAjax('toggle_read', { conversation_id: cardId }).then(function() {
                loadEscalations();
            });
        });

        // -- View full transcript link --
        $(document).on('click', '.view-transcript', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var convId = $(this).data('id');

            acAjaxGet('get_conversation_messages', { conversation_id: convId }).then(function(data) {
                var html = '';
                var messages = data.messages || [];
                $.each(messages, function(i, m) {
                    var cls = m.role === 'user' ? 'ac-msg-user' : 'ac-msg-agent';
                    var av  = m.role === 'user' ? 'You' : 'AC';
                    html += '<div class="ac-msg ' + cls + '" style="max-width:95%">' +
                        '<div class="ac-msg-avatar">' + av + '</div>' +
                        '<div class="ac-msg-bubble">' + $('<span>').text(m.content).html() + '</div>' +
                    '</div>';
                });
                $('#transcript-content').html(
                    '<div style="display:flex;flex-direction:column;gap:10px">' +
                    (html || '<p style="color:var(--ac-text3)">No messages.</p>') +
                    '</div>'
                );
                $('#transcript-modal').addClass('active');
            });
        });

        // -- View resolved filter --
        $(document).on('click', '.view-resolved', function(e) {
            e.preventDefault();
            loadEscalations('resolved');
        });

        // -- Plugin support chat --
        addChatMessage('#support-messages', 'assistant', 'Hi! I can help with AgentClerk plugin questions. What do you need help with?');

        // Support chat chip buttons for common questions
        var supportChips = [
            'How do I update my API key?',
            'How does billing work?',
            'How to customize the widget?'
        ];
        if ($('#support-chips').length) {
            setChips('#support-chips', supportChips, function(text) {
                $('#support-input').val(text);
                sendSupportMessage();
            });
        }

        /**
         * Send a plugin support chat message.
         */
        function sendSupportMessage() {
            var txt = $.trim($('#support-input').val());
            if (!txt) return;

            addChatMessage('#support-messages', 'user', txt);
            supportHistory.push({ role: 'user', content: txt });
            $('#support-input').val('');

            acAjax('support_chat', {
                message: txt,
                history: JSON.stringify(supportHistory)
            }).then(function(data) {
                addChatMessage('#support-messages', 'assistant', data.message);
                supportHistory.push({ role: 'assistant', content: data.message });
            }).catch(function(err) {
                addChatMessage('#support-messages', 'assistant', 'Error: ' + (err.message || 'Something went wrong.'));
            });
        }

        // -- Send button and Enter key --
        $('#support-send').on('click', sendSupportMessage);
        $('#support-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendSupportMessage();
            }
        });
    })();

    /* =========================================================================
     * Section 15 - Dashboard
     * ====================================================================== */

    (function initDashboard() {
        if (!$('#dashboard-stats').length) return;

        // -- Load dashboard stats --
        acAjax('get_stats', {}).then(function(d) {
            $('#stat-today').text(d.today);
            $('#stat-sales-today').text('$' + parseFloat(d.sales_today || 0).toFixed(2));
            $('#stat-total').text(d.total);
            $('#stat-escalated').text(d.escalated);
        });

        // -- Lifetime license CTA --
        $('#lifetime-license-cta, #lifetime-cta-bar').on('click', function() {
            acAjax('lifetime_checkout', {}).then(function(data) {
                if (data && data.checkoutUrl) {
                    window.location.href = data.checkoutUrl;
                }
            }).catch(function() {
                showToast('Could not start checkout.', 'error');
            });
        });
    })();

    /* =========================================================================
     * Section 16 - Suspended Page
     * ====================================================================== */

    (function initSuspended() {
        if (!$('#update-payment').length) return;

        $('#update-payment').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Loading...');

            acAjax('card_update', {}).then(function(data) {
                if (data && data.portalUrl) {
                    window.location.href = data.portalUrl;
                } else {
                    showToast('Could not load billing portal. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Update Payment Card');
                }
            }).catch(function() {
                showToast('Could not load billing portal. Please try again.', 'error');
                $btn.prop('disabled', false).text('Update Payment Card');
            });
        });
    })();

    /* =========================================================================
     * Section 17 - Transcript Modal (shared across pages)
     * ====================================================================== */

    (function initModals() {
        // Close transcript modal via X button
        $(document).on('click', '#close-modal, .ac-modal-close', function() {
            $('#transcript-modal').removeClass('active');
        });

        // Close modal by clicking overlay background
        $(document).on('click', '.ac-modal-overlay', function(e) {
            if (e.target === this) {
                $(this).removeClass('active');
            }
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.ac-modal-overlay.active').removeClass('active');
            }
        });
    })();

})(jQuery);
