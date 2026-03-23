(function() {
    'use strict';

    var config = window.agentclerkWidget || {};
    var chatHistory = [];
    var widgetOpen = false;

    function init() {
        if (config.showWidget) {
            createFloatingWidget();
        }
        bindProductEmbed();
        bindFullPage();
    }

    // -- Floating Widget --
    function createFloatingWidget() {
        var position = config.position || 'bottom-right';
        var posStyle = position === 'bottom-left' ? 'left:20px;right:auto;' : 'right:20px;left:auto;';

        var btn = document.createElement('div');
        btn.id = 'agentclerk-widget-btn';
        btn.className = 'agentclerk-widget-btn';
        btn.style.cssText = posStyle + 'bottom:20px;';
        btn.innerHTML = '<span>' + escapeHtml(config.buttonLabel || 'Get Help') + '</span>';
        btn.addEventListener('click', toggleWidget);
        document.body.appendChild(btn);

        var panel = document.createElement('div');
        panel.id = 'agentclerk-widget-panel';
        panel.className = 'agentclerk-widget-panel';
        panel.style.cssText = posStyle + 'bottom:80px;display:none;';
        panel.innerHTML =
            '<div class="agentclerk-widget-header">' +
                '<span>' + escapeHtml(config.agentName || 'AgentClerk') + '</span>' +
                '<button class="agentclerk-widget-close" id="agentclerk-widget-close">&times;</button>' +
            '</div>' +
            '<div class="agentclerk-widget-messages" id="agentclerk-widget-messages"></div>' +
            '<div class="agentclerk-widget-input-wrap">' +
                '<input type="text" id="agentclerk-widget-input" placeholder="Type a message..." />' +
                '<button id="agentclerk-widget-send">Send</button>' +
            '</div>' +
            '<div class="agentclerk-widget-escalation" id="agentclerk-widget-escalation" style="display:none;">' +
                '<p>Need human help? Enter your email:</p>' +
                '<input type="email" id="agentclerk-widget-esc-email" placeholder="your@email.com" />' +
                '<button id="agentclerk-widget-esc-confirm">Confirm</button>' +
            '</div>';
        document.body.appendChild(panel);

        document.getElementById('agentclerk-widget-close').addEventListener('click', toggleWidget);
        document.getElementById('agentclerk-widget-send').addEventListener('click', function() {
            sendMessage('agentclerk-widget-input', 'agentclerk-widget-messages');
        });
        document.getElementById('agentclerk-widget-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage('agentclerk-widget-input', 'agentclerk-widget-messages');
        });
        document.getElementById('agentclerk-widget-esc-confirm').addEventListener('click', function() {
            handleEscalation('agentclerk-widget-esc-email', 'agentclerk-widget-messages');
        });
    }

    function toggleWidget() {
        widgetOpen = !widgetOpen;
        var panel = document.getElementById('agentclerk-widget-panel');
        if (panel) {
            panel.style.display = widgetOpen ? 'flex' : 'none';
        }
    }

    // -- Product Page Embed --
    function bindProductEmbed() {
        var sendBtn = document.getElementById('agentclerk-product-send');
        var input = document.getElementById('agentclerk-product-input');
        if (!sendBtn || !input) return;

        sendBtn.addEventListener('click', function() {
            sendMessage('agentclerk-product-input', 'agentclerk-product-messages');
        });
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage('agentclerk-product-input', 'agentclerk-product-messages');
        });
    }

    // -- Full Page --
    function bindFullPage() {
        var sendBtn = document.getElementById('agentclerk-fullpage-send');
        var input = document.getElementById('agentclerk-fullpage-input');
        if (!sendBtn || !input) return;

        sendBtn.addEventListener('click', function() {
            sendMessage('agentclerk-fullpage-input', 'agentclerk-fullpage-messages');
        });
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage('agentclerk-fullpage-input', 'agentclerk-fullpage-messages');
        });

        var escConfirm = document.getElementById('agentclerk-escalation-confirm');
        if (escConfirm) {
            escConfirm.addEventListener('click', function() {
                handleEscalation('agentclerk-escalation-email', 'agentclerk-fullpage-messages');
            });
        }
    }

    // -- Chat Logic --
    function sendMessage(inputId, messagesId) {
        var input = document.getElementById(inputId);
        var text = input.value.trim();
        if (!text) return;
        input.value = '';

        appendMessage(messagesId, 'user', text);

        var formData = new FormData();
        formData.append('action', 'agentclerk_chat');
        formData.append('nonce', config.nonce);
        formData.append('message', text);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                appendMessage(messagesId, 'assistant', resp.data.message);
                checkForEscalation(resp.data.message, messagesId);
            } else {
                appendMessage(messagesId, 'assistant', 'Sorry, something went wrong. Please try again.');
            }
        })
        .catch(function() {
            appendMessage(messagesId, 'assistant', 'Connection error. Please try again.');
        });
    }

    function appendMessage(containerId, role, text) {
        var container = document.getElementById(containerId);
        if (!container) return;

        var div = document.createElement('div');
        div.className = 'agentclerk-msg agentclerk-msg-' + role;
        div.textContent = text;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function checkForEscalation(text, messagesId) {
        var lowerText = text.toLowerCase();
        if (lowerText.indexOf('escalat') !== -1 || lowerText.indexOf('human') !== -1 || lowerText.indexOf('speak to') !== -1) {
            var escPanel = null;
            if (messagesId === 'agentclerk-widget-messages') {
                escPanel = document.getElementById('agentclerk-widget-escalation');
            } else if (messagesId === 'agentclerk-fullpage-messages') {
                escPanel = document.getElementById('agentclerk-escalation');
            }
            if (escPanel) {
                escPanel.style.display = 'block';
            }
        }
    }

    function handleEscalation(emailInputId, messagesId) {
        var emailInput = document.getElementById(emailInputId);
        var email = emailInput ? emailInput.value.trim() : '';
        if (!email || email.indexOf('@') === -1) {
            alert('Please enter a valid email address.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'agentclerk_escalate');
        formData.append('nonce', config.nonce);
        formData.append('email', email);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                appendMessage(messagesId, 'assistant', resp.data.message || 'Your request has been escalated. We\'ll follow up via email.');
                var escPanels = document.querySelectorAll('.agentclerk-widget-escalation, #agentclerk-escalation');
                escPanels.forEach(function(p) { p.style.display = 'none'; });
            } else {
                appendMessage(messagesId, 'assistant', 'Error: ' + (resp.data.message || 'Could not escalate.'));
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
