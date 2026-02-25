(function () {
    'use strict';

    if (window.PPCShopChatbotInitialized) {
        return;
    }
    window.PPCShopChatbotInitialized = true;

    var STORAGE_KEY = 'ppc_shop_chatbot_state_v1';
    var MAX_HISTORY = 30;
    var TYPING_MESSAGE_ID = '__typing__';
    var state = {
        open: false,
        maximized: false,
        pending: false,
        messages: []
    };

    function isDevelopmentHost() {
        return window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    }

    function getBasePath() {
        if (typeof window.BASE_PATH === 'string') {
            return window.BASE_PATH;
        }
        return isDevelopmentHost() ? '/core1' : '';
    }

    function getApiBase() {
        if (typeof window.API_BASE === 'string' && window.API_BASE) {
            return window.API_BASE.replace(/\/+$/, '');
        }
        return (getBasePath() + '/backend/api').replace(/\/+$/, '');
    }

    function getApiUrl() {
        if (window.ShopChatbotConfig && typeof window.ShopChatbotConfig.apiUrl === 'string') {
            return window.ShopChatbotConfig.apiUrl;
        }
        return getApiBase() + '/shop/chatbot.php';
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function safeParse(json) {
        try {
            return JSON.parse(json);
        } catch (error) {
            return null;
        }
    }

    function loadState() {
        var raw = window.sessionStorage ? sessionStorage.getItem(STORAGE_KEY) : null;
        if (!raw) {
            return;
        }
        var parsed = safeParse(raw);
        if (!parsed || !Array.isArray(parsed.messages)) {
            return;
        }
        state.open = !!parsed.open;
        state.maximized = !!parsed.maximized;
        state.messages = parsed.messages.slice(-MAX_HISTORY).filter(function (message) {
            return message && typeof message === 'object' && (message.role === 'user' || message.role === 'bot');
        });
    }

    function saveState() {
        if (!window.sessionStorage) {
            return;
        }
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                open: state.open,
                maximized: state.maximized,
                messages: state.messages.slice(-MAX_HISTORY)
            }));
        } catch (error) {
            // Ignore storage failures (quota/private mode)
        }
    }

    function nowTs() {
        return new Date().toISOString();
    }

    function createMessage(role, text, extras) {
        var message = {
            id: String(Date.now()) + '_' + Math.random().toString(36).slice(2, 8),
            role: role,
            text: String(text || ''),
            ts: nowTs()
        };
        if (extras && typeof extras === 'object') {
            if (Array.isArray(extras.quickReplies)) {
                message.quickReplies = extras.quickReplies.slice(0, 6);
            }
            if (Array.isArray(extras.links)) {
                message.links = extras.links.slice(0, 6);
            }
            if (Array.isArray(extras.products)) {
                message.products = extras.products.slice(0, 5);
            }
            if (extras.id) {
                message.id = extras.id;
            }
            if (extras.meta && typeof extras.meta === 'object') {
                message.meta = extras.meta;
            }
        }
        return message;
    }

    function truncateMessages() {
        if (state.messages.length > MAX_HISTORY) {
            state.messages = state.messages.slice(-MAX_HISTORY);
        }
    }

    function addMessage(message) {
        state.messages.push(message);
        truncateMessages();
        saveState();
        renderMessages();
    }

    function removeMessageById(id) {
        var before = state.messages.length;
        state.messages = state.messages.filter(function (message) {
            return message.id !== id;
        });
        if (state.messages.length !== before) {
            saveState();
            renderMessages();
        }
    }

    function ensureWelcomeMessage() {
        if (state.messages.length > 0) {
            return;
        }

        addMessage(createMessage(
            'bot',
            'Hi! I am the shop assistant. Ask about products, stock availability, checkout, shipping, payment methods, returns, or orders.',
            {
                quickReplies: ['Browse categories', 'Find a product', 'Shipping info', 'Payment methods'],
                links: [{ label: 'Shop Home', url: 'index.php' }]
            }
        ));
    }

    var refs = {
        root: null,
        panel: null,
        toggle: null,
        toggleLabel: null,
        closeBtn: null,
        maximizeBtn: null,
        clearBtn: null,
        statusDot: null,
        statusText: null,
        messages: null,
        form: null,
        input: null,
        sendBtn: null
    };

    function buildUi() {
        if (document.getElementById('ppc-shop-chatbot-root')) {
            return;
        }

        var root = document.createElement('div');
        root.id = 'ppc-shop-chatbot-root';
        root.className = 'ppc-chatbot-root';
        root.innerHTML = [
            '<section class="ppc-chatbot-panel" aria-label="Shop assistant chat panel" aria-hidden="true">',
            '  <div class="ppc-chatbot-panel-accent" aria-hidden="true"></div>',
            '  <div class="ppc-chatbot-header">',
            '    <div class="ppc-chatbot-title">',
            '      <div class="ppc-chatbot-title-badge" aria-hidden="true"><i class="fas fa-robot"></i></div>',
            '      <div class="ppc-chatbot-title-text">',
            '        <strong>AI Shop Assistant</strong>',
            '        <span>Products, orders, checkout, and support help</span>',
            '      </div>',
            '    </div>',
            '    <div class="ppc-chatbot-status" title="Assistant status">',
            '      <span class="ppc-chatbot-status-dot" data-chatbot-status-dot></span>',
            '      <span class="ppc-chatbot-status-text" data-chatbot-status-text>Ready</span>',
            '    </div>',
            '    <div class="ppc-chatbot-header-actions">',
            '      <button type="button" class="ppc-chatbot-icon-btn" data-chatbot-action="maximize" title="Expand chat" aria-label="Expand chat">',
            '        <i class="fas fa-expand"></i>',
            '      </button>',
            '      <button type="button" class="ppc-chatbot-icon-btn" data-chatbot-action="clear" title="Clear chat" aria-label="Clear chat">',
            '        <i class="fas fa-trash-alt"></i>',
            '      </button>',
            '      <button type="button" class="ppc-chatbot-icon-btn" data-chatbot-action="close" title="Close chat" aria-label="Close chat">',
            '        <i class="fas fa-times"></i>',
            '      </button>',
            '    </div>',
            '  </div>',
            '  <div class="ppc-chatbot-messages" aria-live="polite"></div>',
            '  <div class="ppc-chatbot-footer">',
            '    <form class="ppc-chatbot-form" autocomplete="off">',
            '      <input type="text" class="ppc-chatbot-input" name="message" maxlength="600" placeholder="Ask about products, orders, checkout..." />',
            '      <button type="submit" class="ppc-chatbot-send" aria-label="Send message">',
            '        <i class="fas fa-paper-plane"></i>',
            '      </button>',
            '    </form>',
            '    <div class="ppc-chatbot-footer-hint">Tip: Try "Find SSD under 5000" or "What payment methods are available?"</div>',
            '  </div>',
            '</section>',
            '<div class="ppc-chatbot-toggle-label" aria-hidden="true">Need help?</div>',
            '<button type="button" class="ppc-chatbot-toggle" aria-expanded="false" aria-controls="ppc-shop-chatbot-root" aria-label="Open shop assistant">',
            '  <span class="ppc-chatbot-toggle-glow" aria-hidden="true"></span>',
            '  <i class="fas fa-comments"></i>',
            '</button>'
        ].join('');

        document.body.appendChild(root);

        refs.root = root;
        refs.panel = root.querySelector('.ppc-chatbot-panel');
        refs.toggle = root.querySelector('.ppc-chatbot-toggle');
        refs.toggleLabel = root.querySelector('.ppc-chatbot-toggle-label');
        refs.closeBtn = root.querySelector('[data-chatbot-action="close"]');
        refs.maximizeBtn = root.querySelector('[data-chatbot-action="maximize"]');
        refs.clearBtn = root.querySelector('[data-chatbot-action="clear"]');
        refs.statusDot = root.querySelector('[data-chatbot-status-dot]');
        refs.statusText = root.querySelector('[data-chatbot-status-text]');
        refs.messages = root.querySelector('.ppc-chatbot-messages');
        refs.form = root.querySelector('.ppc-chatbot-form');
        refs.input = root.querySelector('.ppc-chatbot-input');
        refs.sendBtn = root.querySelector('.ppc-chatbot-send');

        bindUiEvents();
        renderOpenState();
        renderMessages();
    }

    function bindUiEvents() {
        refs.toggle.addEventListener('click', function () {
            state.open = !state.open;
            saveState();
            renderOpenState();
            if (state.open) {
                refs.input.focus();
                scrollMessagesToBottom();
            }
        });

        refs.closeBtn.addEventListener('click', function () {
            state.open = false;
            saveState();
            renderOpenState();
        });

        if (refs.maximizeBtn) {
            refs.maximizeBtn.addEventListener('click', function () {
                state.maximized = !state.maximized;
                saveState();
                renderPanelModeState();
                if (state.open) {
                    scrollMessagesToBottom();
                }
            });
        }

        refs.clearBtn.addEventListener('click', function () {
            state.messages = [];
            saveState();
            ensureWelcomeMessage();
            renderMessages();
        });

        refs.form.addEventListener('submit', function (event) {
            event.preventDefault();
            var raw = refs.input.value || '';
            var text = raw.trim();
            if (!text || state.pending) {
                return;
            }
            refs.input.value = '';
            sendUserMessage(text);
        });
    }

    function renderOpenState() {
        if (!refs.panel || !refs.toggle) {
            return;
        }
        refs.panel.classList.toggle('is-open', !!state.open);
        refs.panel.classList.toggle('is-opening', !!state.open);
        refs.panel.setAttribute('aria-hidden', state.open ? 'false' : 'true');
        refs.toggle.setAttribute('aria-expanded', state.open ? 'true' : 'false');
        if (refs.toggleLabel) {
            refs.toggleLabel.classList.toggle('ppc-chatbot-hidden', !!state.open);
        }
        refs.toggle.innerHTML = state.open
            ? '<span class="ppc-chatbot-toggle-glow" aria-hidden="true"></span><i class="fas fa-minus"></i>'
            : '<span class="ppc-chatbot-toggle-glow" aria-hidden="true"></span><i class="fas fa-comments"></i>';
        renderPanelModeState();
    }

    function renderPanelModeState() {
        if (!refs.root || !refs.panel) {
            return;
        }

        var canMaximize = !!state.open;
        var shouldMaximize = canMaximize && !!state.maximized;

        refs.root.classList.toggle('is-panel-maximized', shouldMaximize);
        refs.panel.classList.toggle('is-maximized', shouldMaximize);

        if (refs.maximizeBtn) {
            refs.maximizeBtn.classList.toggle('is-active', shouldMaximize);
            refs.maximizeBtn.title = shouldMaximize ? 'Restore chat size' : 'Expand chat';
            refs.maximizeBtn.setAttribute('aria-label', shouldMaximize ? 'Restore chat size' : 'Expand chat');
            refs.maximizeBtn.innerHTML = shouldMaximize
                ? '<i class="fas fa-compress"></i>'
                : '<i class="fas fa-expand"></i>';
        }
    }

    function scrollMessagesToBottom() {
        if (!refs.messages) {
            return;
        }
        refs.messages.scrollTop = refs.messages.scrollHeight;
    }

    function clearElement(el) {
        while (el.firstChild) {
            el.removeChild(el.firstChild);
        }
    }

    function appendTextParagraphs(container, text) {
        var lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
        var hasContent = lines.some(function (line) {
            return line.trim() !== '';
        });

        if (!hasContent) {
            var empty = document.createElement('p');
            empty.textContent = '';
            container.appendChild(empty);
            return;
        }

        var i = 0;
        while (i < lines.length) {
            var line = (lines[i] || '').trim();

            if (!line) {
                i += 1;
                continue;
            }

            var bulletMatch = line.match(/^[-*•]\s+(.+)$/);
            if (bulletMatch) {
                var ul = document.createElement('ul');
                while (i < lines.length) {
                    var bulletLine = (lines[i] || '').trim();
                    var bulletItemMatch = bulletLine.match(/^[-*•]\s+(.+)$/);
                    if (!bulletItemMatch) {
                        break;
                    }
                    var li = document.createElement('li');
                    li.textContent = bulletItemMatch[1];
                    ul.appendChild(li);
                    i += 1;
                }
                container.appendChild(ul);
                continue;
            }

            var numberedMatch = line.match(/^(\d+)[\.\)]\s+(.+)$/);
            if (numberedMatch) {
                var ol = document.createElement('ol');
                var startValue = parseInt(numberedMatch[1], 10);
                if (!Number.isNaN(startValue) && startValue > 1) {
                    ol.start = startValue;
                }

                while (i < lines.length) {
                    var numberedLine = (lines[i] || '').trim();
                    var numberedItemMatch = numberedLine.match(/^(\d+)[\.\)]\s+(.+)$/);
                    if (!numberedItemMatch) {
                        break;
                    }
                    var oli = document.createElement('li');
                    oli.textContent = numberedItemMatch[2];
                    ol.appendChild(oli);
                    i += 1;
                }
                container.appendChild(ol);
                continue;
            }

            var p = document.createElement('p');
            p.textContent = line;
            container.appendChild(p);
            i += 1;
        }
    }

    function renderTypingBubble(parentBubble) {
        var wrapper = document.createElement('div');
        wrapper.className = 'ppc-chatbot-typing';
        wrapper.setAttribute('aria-label', 'Assistant is typing');
        wrapper.innerHTML = [
            '<span class="ppc-chatbot-typing-text">Typing</span>',
            '<span class="ppc-chatbot-typing-dots" aria-hidden="true">',
            '  <span></span><span></span><span></span>',
            '</span>'
        ].join('');
        parentBubble.appendChild(wrapper);
    }

    function formatMessageTime(isoString) {
        if (!isoString) {
            return '';
        }
        var date = new Date(isoString);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function renderMessageExtras(bubble, message) {
        if (Array.isArray(message.products) && message.products.length) {
            var productsWrap = document.createElement('div');
            productsWrap.className = 'ppc-chatbot-products';

            message.products.forEach(function (product) {
                if (!product || typeof product !== 'object') {
                    return;
                }
                var productCard = document.createElement('div');
                productCard.className = 'ppc-chatbot-product';

                var top = document.createElement('div');
                top.className = 'ppc-chatbot-product-top';

                var left = document.createElement('div');
                var link = document.createElement('a');
                link.className = 'ppc-chatbot-product-name';
                link.href = normalizeHref(product.url || 'product.php');
                link.textContent = product.name || 'Product';
                left.appendChild(link);

                if (product.brand) {
                    var brand = document.createElement('div');
                    brand.className = 'ppc-chatbot-product-meta';
                    brand.textContent = product.brand;
                    left.appendChild(brand);
                }

                var price = document.createElement('div');
                price.className = 'ppc-chatbot-product-price';
                price.textContent = product.price_formatted || '';

                top.appendChild(left);
                top.appendChild(price);
                productCard.appendChild(top);

                var meta = document.createElement('div');
                meta.className = 'ppc-chatbot-product-meta';
                var stock = document.createElement('span');
                stock.className = 'ppc-chatbot-stock ' + (product.in_stock ? 'in' : 'out');
                stock.textContent = product.in_stock ? 'In stock' : 'Out of stock';
                meta.appendChild(stock);

                if (typeof product.quantity_available === 'number' && product.quantity_available >= 0) {
                    meta.appendChild(document.createTextNode(' • Qty: ' + product.quantity_available));
                }
                if (product.category) {
                    meta.appendChild(document.createTextNode(' • ' + product.category));
                }
                productCard.appendChild(meta);

                productsWrap.appendChild(productCard);
            });

            if (productsWrap.childElementCount > 0) {
                bubble.appendChild(productsWrap);
            }
        }

        if (Array.isArray(message.links) && message.links.length) {
            var linksWrap = document.createElement('div');
            linksWrap.className = 'ppc-chatbot-links';

            message.links.forEach(function (item) {
                if (!item || typeof item !== 'object') {
                    return;
                }
                var label = String(item.label || '').trim();
                var url = String(item.url || '').trim();
                if (!label || !url) {
                    return;
                }
                var anchor = document.createElement('a');
                anchor.className = 'ppc-chatbot-link-chip';
                anchor.href = normalizeHref(url);
                anchor.textContent = label;
                linksWrap.appendChild(anchor);
            });

            if (linksWrap.childElementCount > 0) {
                bubble.appendChild(linksWrap);
            }
        }

        if (Array.isArray(message.quickReplies) && message.quickReplies.length) {
            var quickWrap = document.createElement('div');
            quickWrap.className = 'ppc-chatbot-quick-replies';

            message.quickReplies.forEach(function (label) {
                if (typeof label !== 'string' || !label.trim()) {
                    return;
                }
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ppc-chatbot-quick-reply';
                btn.textContent = label.trim();
                btn.addEventListener('click', function () {
                    if (state.pending) {
                        return;
                    }
                    if (!state.open) {
                        state.open = true;
                        saveState();
                        renderOpenState();
                    }
                    sendUserMessage(btn.textContent);
                });
                quickWrap.appendChild(btn);
            });

            if (quickWrap.childElementCount > 0) {
                bubble.appendChild(quickWrap);
            }
        }
    }

    function renderMessages() {
        if (!refs.messages) {
            return;
        }

        clearElement(refs.messages);

        state.messages.forEach(function (message) {
            var row = document.createElement('div');
            row.className = 'ppc-chatbot-row ' + message.role;

            var avatar = document.createElement('div');
            avatar.className = 'ppc-chatbot-avatar ' + message.role;
            avatar.setAttribute('aria-hidden', 'true');
            avatar.innerHTML = message.role === 'bot'
                ? '<i class="fas fa-robot"></i>'
                : '<i class="fas fa-user"></i>';

            var stack = document.createElement('div');
            stack.className = 'ppc-chatbot-message-stack';

            var bubble = document.createElement('div');
            bubble.className = 'ppc-chatbot-bubble';

            if (message.id === TYPING_MESSAGE_ID) {
                renderTypingBubble(bubble);
            } else {
                appendTextParagraphs(bubble, message.text);
                renderMessageExtras(bubble, message);
            }

            stack.appendChild(bubble);

            if (message.id !== TYPING_MESSAGE_ID) {
                var meta = document.createElement('div');
                meta.className = 'ppc-chatbot-message-meta';
                var metaParts = [];
                if (message.role === 'bot') {
                    if (message.meta && message.meta.response_source === 'llm') {
                        metaParts.push('AI');
                    } else {
                        metaParts.push('Assistant');
                    }
                } else {
                    metaParts.push('You');
                }
                var timeLabel = formatMessageTime(message.ts);
                if (timeLabel) {
                    metaParts.push(timeLabel);
                }
                meta.textContent = metaParts.join(' • ');
                stack.appendChild(meta);
            }

            row.appendChild(avatar);
            row.appendChild(stack);
            refs.messages.appendChild(row);
        });

        scrollMessagesToBottom();
        updateSendState();
    }

    function updateSendState() {
        if (!refs.input || !refs.sendBtn) {
            return;
        }
        refs.input.disabled = !!state.pending;
        refs.sendBtn.disabled = !!state.pending;
    }

    function setAssistantStatus(text, mode) {
        if (refs.statusText) {
            refs.statusText.textContent = text || 'Ready';
        }
        if (refs.statusDot) {
            refs.statusDot.classList.remove('is-thinking', 'is-warning');
            if (mode === 'thinking') {
                refs.statusDot.classList.add('is-thinking');
            } else if (mode === 'warning') {
                refs.statusDot.classList.add('is-warning');
            }
        }
    }

    function normalizeHref(url) {
        var value = String(url || '').trim();
        if (!value) {
            return 'index.php';
        }
        return value;
    }

    function getPageContext() {
        var path = window.location.pathname || '';
        var fileName = path.split('/').pop() || 'index.php';
        var search = new URLSearchParams(window.location.search || '');

        return {
            page: fileName.replace(/\.php$/i, ''),
            pathname: path,
            title: document.title || '',
            url: window.location.href,
            product_id: search.get('id') || null
        };
    }

    function getConversationHistoryForApi() {
        return state.messages
            .filter(function (message) {
                return message && message.id !== TYPING_MESSAGE_ID && (message.role === 'user' || message.role === 'bot');
            })
            .slice(-8)
            .map(function (message) {
                var item = {
                    role: message.role,
                    text: String(message.text || '')
                };
                if (message.role === 'bot') {
                    if (Array.isArray(message.products) && message.products.length) {
                        item.products = message.products.slice(0, 5).map(function (product) {
                            return {
                                id: product.id,
                                name: product.name,
                                brand: product.brand,
                                sku: product.sku,
                                category: product.category,
                                price: product.price,
                                price_formatted: product.price_formatted,
                                in_stock: product.in_stock,
                                quantity_available: product.quantity_available,
                                url: product.url
                            };
                        });
                    }
                    if (message.meta && typeof message.meta === 'object') {
                        item.meta = {
                            intent: message.meta.intent || null,
                            response_source: message.meta.response_source || null
                        };
                    }
                }
                return item;
            });
    }

    async function requestChatbotReply(messageText, history) {
        var response = await fetch(getApiUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                message: messageText,
                context: getPageContext(),
                history: Array.isArray(history) ? history : []
            })
        });

        var payload = await response.json().catch(function () {
            return null;
        });

        if (!response.ok || !payload || payload.success !== true || !payload.data) {
            var fallbackMessage = (payload && payload.message) ? payload.message : 'Unable to contact the assistant right now.';
            throw new Error(fallbackMessage);
        }

        return payload.data;
    }

    function setPending(pending) {
        state.pending = !!pending;
        updateSendState();
        if (state.pending) {
            setAssistantStatus('Thinking...', 'thinking');
        }
    }

    function addTypingMessage() {
        addMessage(createMessage('bot', '', { id: TYPING_MESSAGE_ID }));
    }

    function removeTypingMessage() {
        removeMessageById(TYPING_MESSAGE_ID);
    }

    function sendUserMessage(text) {
        var clean = String(text || '').trim();
        if (!clean) {
            return;
        }

        if (!state.open) {
            state.open = true;
            saveState();
            renderOpenState();
        }

        var historyForApi = getConversationHistoryForApi();
        addMessage(createMessage('user', clean));
        setPending(true);
        addTypingMessage();

        requestChatbotReply(clean, historyForApi).then(function (data) {
            removeTypingMessage();
            addMessage(createMessage('bot', data.reply || 'I can help with shop-related questions.', {
                quickReplies: Array.isArray(data.quick_replies) ? data.quick_replies : [],
                links: Array.isArray(data.suggested_links) ? data.suggested_links : [],
                products: Array.isArray(data.product_matches) ? data.product_matches : [],
                meta: data.meta && typeof data.meta === 'object' ? data.meta : {}
            }));
            if (data.meta && data.meta.response_source === 'llm') {
                setAssistantStatus('AI online', 'ready');
            } else if (data.meta && data.meta.scope === 'out_of_scope') {
                setAssistantStatus('Scope guard', 'warning');
            } else {
                setAssistantStatus('Fallback mode', 'warning');
            }
        }).catch(function (error) {
            removeTypingMessage();
            addMessage(createMessage(
                'bot',
                'I could not reach the assistant right now. You can still browse products, check your cart, or try again in a moment. (' + (error && error.message ? error.message : 'Network error') + ')',
                {
                    quickReplies: ['Browse categories', 'Find a product', 'Cart', 'Checkout'],
                    links: [
                        { label: 'Shop Home', url: 'index.php' },
                        { label: 'Cart', url: 'cart.php' }
                    ]
                }
            ));
            setAssistantStatus('Offline fallback', 'warning');
        }).finally(function () {
            setPending(false);
            if (refs.input) {
                refs.input.focus();
            }
        });
    }

    function init() {
        if (!document.body) {
            return;
        }

        loadState();
        ensureWelcomeMessage();
        buildUi();
        setAssistantStatus('Ready', 'ready');

        if (state.open) {
            renderOpenState();
            scrollMessagesToBottom();
        }
    }

    onReady(init);
})();
