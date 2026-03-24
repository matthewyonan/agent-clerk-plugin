(function () {
	'use strict';

	var cfg = window.agentclerkWidget || {};
	var placement = cfg.placement || {};
	var SESSION_COOKIE = 'acw_session';
	var SESSION_TTL = 24 * 60 * 60 * 1000; // 24 hours

	/* ───────────────────────────────────────────────
	 *  Cookie / Session Helpers
	 * ─────────────────────────────────────────────── */

	function setCookie(name, value, ms) {
		var expires = new Date(Date.now() + ms).toUTCString();
		document.cookie = name + '=' + encodeURIComponent(value) +
			';expires=' + expires + ';path=/;SameSite=Lax';
	}

	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function uuidv4() {
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = (Math.random() * 16) | 0;
			var v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	function getSessionId() {
		var sid = getCookie(SESSION_COOKIE);
		if (!sid) {
			sid = uuidv4();
			setCookie(SESSION_COOKIE, sid, SESSION_TTL);
		}
		return sid;
	}

	/* ───────────────────────────────────────────────
	 *  Text Helpers
	 * ─────────────────────────────────────────────── */

	function escapeHtml(text) {
		var el = document.createElement('span');
		el.textContent = text;
		return el.innerHTML;
	}

	/**
	 * Markdown-lite: bold, links, line breaks.
	 */
	function renderMarkdown(text) {
		var safe = escapeHtml(text);
		// Bold: **text**
		safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
		// Links: [text](url)
		safe = safe.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
		// Bare URLs
		safe = safe.replace(/(^|[^"'>])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');
		// Line breaks
		safe = safe.replace(/\n/g, '<br>');
		return safe;
	}

	/* ───────────────────────────────────────────────
	 *  DOM: Message Rendering
	 * ─────────────────────────────────────────────── */

	/**
	 * Create and append a message element.
	 *
	 * @param {string} role      'agent' or 'user'
	 * @param {string} content   Message text
	 * @param {Element} container Messages container element
	 */
	function createMessage(role, content, container) {
		if (!container) return;

		var msg = document.createElement('div');
		msg.className = 'acw-msg acw-msg--' + role;

		var avatar = document.createElement('div');
		avatar.className = 'acw-msg-avatar';
		avatar.textContent = role === 'agent' ? '\u26A1' : 'Y';

		var bubble = document.createElement('div');
		bubble.className = 'acw-msg-bubble';
		bubble.innerHTML = renderMarkdown(content);

		msg.appendChild(avatar);
		msg.appendChild(bubble);
		container.appendChild(msg);

		container.scrollTop = container.scrollHeight;
	}

	/**
	 * Show typing indicator in container. Returns element for removal.
	 */
	function showTyping(container) {
		if (!container) return null;

		var wrapper = document.createElement('div');
		wrapper.className = 'acw-typing';

		var avatar = document.createElement('div');
		avatar.className = 'acw-msg-avatar';
		avatar.style.background = '#1C2333';
		avatar.style.color = '#00E5C8';
		avatar.textContent = '\u26A1';

		var dots = document.createElement('div');
		dots.className = 'acw-typing-dots';
		for (var i = 0; i < 3; i++) {
			var dot = document.createElement('div');
			dot.className = 'acw-typing-dot';
			dots.appendChild(dot);
		}

		wrapper.appendChild(avatar);
		wrapper.appendChild(dots);
		container.appendChild(wrapper);
		container.scrollTop = container.scrollHeight;

		return wrapper;
	}

	function removeTyping(el) {
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}

	/* ───────────────────────────────────────────────
	 *  Escalation Panel
	 * ─────────────────────────────────────────────── */

	/**
	 * Create and insert an escalation panel after the messages container.
	 */
	function createEscalationPanel(container) {
		var existing = container.parentNode.querySelector('.acw-escalation');
		if (existing) {
			existing.style.display = 'block';
			return;
		}

		var panel = document.createElement('div');
		panel.className = 'acw-escalation';

		var title = document.createElement('p');
		title.className = 'acw-escalation-title';
		title.textContent = 'Need human assistance?';

		var text = document.createElement('p');
		text.className = 'acw-escalation-text';
		text.textContent = 'Enter your email and we\u2019ll have someone follow up with you.';

		var input = document.createElement('input');
		input.type = 'email';
		input.className = 'acw-escalation-input';
		input.placeholder = 'your@email.com';

		var btn = document.createElement('button');
		btn.className = 'acw-escalation-confirm';
		btn.textContent = 'Send to a Human';

		btn.addEventListener('click', function () {
			var email = input.value.trim();
			if (!email || email.indexOf('@') === -1 || email.indexOf('.') === -1) {
				input.style.borderColor = '#EF4444';
				return;
			}
			input.style.borderColor = '';
			escalateConversation(email, container, panel);
		});

		panel.appendChild(title);
		panel.appendChild(text);
		panel.appendChild(input);
		panel.appendChild(btn);

		// Insert into the messages container so it scrolls.
		container.appendChild(panel);
		container.scrollTop = container.scrollHeight;
	}

	function escalateConversation(email, messagesContainer, escalationPanel) {
		var formData = new FormData();
		formData.append('action', 'agentclerk_escalate');
		formData.append('nonce', cfg.nonce);
		formData.append('email', email);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		})
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				if (resp.success) {
					if (escalationPanel && escalationPanel.parentNode) {
						escalationPanel.parentNode.removeChild(escalationPanel);
					}
					createMessage('agent', resp.data.message || 'Your request has been escalated. We\u2019ll follow up via email.', messagesContainer);
				} else {
					createMessage('agent', 'Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Could not escalate. Please try again.'), messagesContainer);
				}
			})
			.catch(function () {
				createMessage('agent', 'Connection error. Please try again.', messagesContainer);
			});
	}

	/* ───────────────────────────────────────────────
	 *  Chat: Send Message
	 * ─────────────────────────────────────────────── */

	/**
	 * @param {string}  surface       'widget' | 'product' | 'fullpage' | 'support'
	 * @param {string}  message       User's text
	 * @param {Element} container     Messages container
	 * @param {object}  [extra]       Extra form data fields
	 */
	function sendMessage(surface, message, container, extra) {
		if (!message || !container) return;

		createMessage('user', message, container);

		var typing = showTyping(container);

		var formData = new FormData();
		formData.append('action', 'agentclerk_chat');
		formData.append('nonce', cfg.nonce);
		formData.append('message', message);
		formData.append('session_id', getSessionId());
		formData.append('surface', surface);

		if (extra) {
			for (var key in extra) {
				if (extra.hasOwnProperty(key)) {
					formData.append(key, extra[key]);
				}
			}
		}

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		})
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				removeTyping(typing);
				if (resp.success) {
					createMessage('agent', resp.data.message, container);
					// Check for escalation markers.
					checkEscalation(resp.data.message, container);
				} else {
					createMessage('agent', 'Sorry, something went wrong. Please try again.', container);
				}
			})
			.catch(function () {
				removeTyping(typing);
				createMessage('agent', 'Connection error. Please try again.', container);
			});
	}

	/**
	 * Detect escalation markers in an agent response.
	 */
	function checkEscalation(text, container) {
		if (!text) return;
		var lower = text.toLowerCase();
		if (
			lower.indexOf('escalat') !== -1 ||
			lower.indexOf('speak to a human') !== -1 ||
			lower.indexOf('connect you with') !== -1 ||
			lower.indexOf('human support') !== -1 ||
			lower.indexOf('transfer you') !== -1
		) {
			createEscalationPanel(container);
		}
	}

	/**
	 * Bind input + send button for a chat surface.
	 */
	function bindChat(surface, inputId, sendBtnId, containerId, extra) {
		var input = document.getElementById(inputId);
		var sendBtn = document.getElementById(sendBtnId);
		var container = document.getElementById(containerId);

		if (!input || !sendBtn || !container) return;

		function doSend() {
			var text = input.value.trim();
			if (!text) return;
			input.value = '';
			sendMessage(surface, text, container, extra);
		}

		sendBtn.addEventListener('click', doSend);
		input.addEventListener('keypress', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				doSend();
			}
		});
	}

	/* ───────────────────────────────────────────────
	 *  Floating Widget
	 * ─────────────────────────────────────────────── */

	function createFloatingWidget() {
		if (!placement.widget) return;

		var isLeft = placement.position === 'bottom-left';

		// Button
		var btn = document.createElement('button');
		btn.className = 'acw-float-btn' + (isLeft ? ' acw-float-btn--left' : '');
		btn.innerHTML =
			'<span class="acw-float-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></span>' +
			'<span>' + escapeHtml(placement.button_label || cfg.agentName || 'Get Help') + '</span>';
		document.body.appendChild(btn);

		// Panel
		var panel = document.createElement('div');
		panel.className = 'acw-panel' + (isLeft ? ' acw-panel--left' : '');
		panel.id = 'acw-panel';

		panel.innerHTML =
			'<div class="acw-header">' +
				'<div class="acw-header-left">' +
					'<div class="acw-avatar acw-avatar--electric"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>' +
					'<div class="acw-header-info">' +
						'<span class="acw-header-name">' + escapeHtml(cfg.agentName || 'AgentClerk') + '</span>' +
						'<span class="acw-header-status">&#9679; Online</span>' +
					'</div>' +
				'</div>' +
				'<button class="acw-close-btn" id="acw-close">&times;</button>' +
			'</div>' +
			'<div class="acw-messages" id="acw-widget-messages"></div>' +
			'<div class="acw-input-row">' +
				'<input type="text" class="acw-input" id="acw-widget-input" placeholder="Type a message\u2026" />' +
				'<button class="acw-send-btn" id="acw-widget-send"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9z"/></svg></button>' +
			'</div>';

		document.body.appendChild(panel);

		// Toggle
		var isOpen = false;
		btn.addEventListener('click', function () {
			isOpen = !isOpen;
			if (isOpen) {
				panel.classList.add('open');
			} else {
				panel.classList.remove('open');
			}
		});

		document.getElementById('acw-close').addEventListener('click', function () {
			isOpen = false;
			panel.classList.remove('open');
		});

		// Chat binding
		bindChat('widget', 'acw-widget-input', 'acw-widget-send', 'acw-widget-messages');

		// Auto-greeting
		var widgetMessages = document.getElementById('acw-widget-messages');
		if (widgetMessages && !widgetMessages.hasChildNodes()) {
			createMessage('agent', 'Hi! I\u2019m ' + (cfg.agentName || 'AgentClerk') + '. How can I help you today?', widgetMessages);
		}
	}

	/* ───────────────────────────────────────────────
	 *  Product Page Embed
	 * ─────────────────────────────────────────────── */

	function initProductEmbed() {
		var container = document.getElementById('acw-product-messages');
		if (!container) return;

		var extra = {};
		if (cfg.currentProductId) {
			extra.product_id = cfg.currentProductId;
		}

		bindChat('product', 'acw-product-input', 'acw-product-send', 'acw-product-messages', extra);

		// Auto-greeting with product name.
		var name = cfg.currentProductName || 'this product';
		var price = cfg.currentProductPrice ? ' ($' + cfg.currentProductPrice + ')' : '';
		createMessage('agent', 'Have questions about **' + name + '**' + price + '? Ask away!', container);
	}

	/* ───────────────────────────────────────────────
	 *  /clerk Full Page
	 * ─────────────────────────────────────────────── */

	function initFullPage() {
		var container = document.getElementById('acw-fullpage-messages');
		if (!container) return;

		bindChat('fullpage', 'acw-fullpage-input', 'acw-fullpage-send', 'acw-fullpage-messages');

		// Generic greeting.
		createMessage('agent', 'Welcome! I\u2019m ' + (cfg.agentName || 'AgentClerk') + '. I can help you find the right product, answer questions, or assist with an order. What can I do for you?', container);
	}

	/* ───────────────────────────────────────────────
	 *  Support Page
	 * ─────────────────────────────────────────────── */

	function initSupportChat() {
		var container = document.getElementById('acw-support-messages');
		if (!container) return;

		bindChat('support', 'acw-support-input', 'acw-support-send', 'acw-support-messages');

		// Support-specific greeting.
		createMessage('agent', 'Hello! I\u2019m here to help with any support questions. Describe your issue and I\u2019ll do my best to resolve it. If I can\u2019t, I\u2019ll connect you with a human.', container);
	}

	/* ───────────────────────────────────────────────
	 *  Init
	 * ─────────────────────────────────────────────── */

	function init() {
		// Ensure session exists.
		getSessionId();

		// Floating widget (all pages except when we're on a dedicated surface page).
		createFloatingWidget();

		// Product page embed.
		initProductEmbed();

		// /clerk full page.
		initFullPage();

		// Support page.
		initSupportChat();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
