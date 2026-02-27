(function () {
    'use strict';

    const ALLOWED_ROLES = new Set(['admin', 'inventory_manager', 'purchasing_officer']);
    const API_ENDPOINT = '/ai/admin-copilot.php';

    const state = {
        ready: false,
        isOpen: false,
        isBusy: false
    };

    function getCurrentUser() {
        if (typeof currentUser !== 'undefined' && currentUser) {
            return currentUser;
        }
        if (typeof window.currentUser !== 'undefined' && window.currentUser) {
            return window.currentUser;
        }
        return null;
    }

    function canUseCopilot() {
        const user = getCurrentUser();
        return Boolean(user && ALLOWED_ROLES.has(user.role));
    }

    function init() {
        if (!canUseCopilot()) {
            return;
        }
        if (!window.API_BASE) {
            return;
        }
        if (document.getElementById('admin-ai-launcher')) {
            return;
        }

        renderShell();
        bindEvents();
        warmupStatus();
    }

    function renderShell() {
        const launcher = document.createElement('button');
        launcher.id = 'admin-ai-launcher';
        launcher.className = 'admin-ai-launcher';
        launcher.type = 'button';
        launcher.setAttribute('aria-label', 'Open admin AI copilot');
        launcher.innerHTML = '<i class="fas fa-robot"></i><span>AI</span>';

        const panel = document.createElement('section');
        panel.id = 'admin-ai-panel';
        panel.className = 'admin-ai-panel';
        panel.setAttribute('aria-hidden', 'true');
        panel.innerHTML = `
            <div class="admin-ai-header">
                <div class="admin-ai-title-wrap">
                    <h3 class="admin-ai-title"><i class="fas fa-robot"></i> Admin AI Copilot</h3>
                    <p class="admin-ai-subtitle">Read-only operations assistant</p>
                </div>
                <button type="button" class="admin-ai-close" id="admin-ai-close" aria-label="Close AI panel">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="admin-ai-quick-actions">
                <button type="button" class="admin-ai-chip" data-ai-mode="summary">Daily Summary</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="reorder">Reorder Suggestions</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="anomalies">Anomaly Scan</button>
            </div>

            <div class="admin-ai-feed" id="admin-ai-feed"></div>

            <form class="admin-ai-form" id="admin-ai-form">
                <input
                    type="text"
                    id="admin-ai-input"
                    class="admin-ai-input"
                    maxlength="700"
                    placeholder="Ask about inventory, purchasing, risks, or daily ops..."
                    autocomplete="off"
                />
                <button type="submit" class="admin-ai-send" id="admin-ai-send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        `;

        document.body.appendChild(launcher);
        document.body.appendChild(panel);

        appendSystemMessage('Copilot ready. Use a quick action or ask a question.');
    }

    function bindEvents() {
        const launcher = document.getElementById('admin-ai-launcher');
        const closeBtn = document.getElementById('admin-ai-close');
        const panel = document.getElementById('admin-ai-panel');
        const form = document.getElementById('admin-ai-form');
        const input = document.getElementById('admin-ai-input');

        if (!launcher || !closeBtn || !panel || !form || !input) {
            return;
        }

        launcher.addEventListener('click', () => {
            if (state.isOpen) {
                closePanel();
            } else {
                openPanel();
            }
        });

        closeBtn.addEventListener('click', closePanel);

        panel.querySelectorAll('[data-ai-mode]').forEach((button) => {
            button.addEventListener('click', async () => {
                const mode = button.getAttribute('data-ai-mode');
                if (!mode) {
                    return;
                }
                await runQuickAction(mode);
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = input.value.trim();
            if (!message) {
                return;
            }
            input.value = '';
            await askCopilot(message);
        });
    }

    function openPanel() {
        const panel = document.getElementById('admin-ai-panel');
        if (!panel) {
            return;
        }
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        state.isOpen = true;
    }

    function closePanel() {
        const panel = document.getElementById('admin-ai-panel');
        if (!panel) {
            return;
        }
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        state.isOpen = false;
    }

    function feedElement() {
        return document.getElementById('admin-ai-feed');
    }

    function appendMessage(type, html) {
        const feed = feedElement();
        if (!feed) {
            return;
        }

        const row = document.createElement('div');
        row.className = `admin-ai-msg admin-ai-msg-${type}`;
        row.innerHTML = html;
        feed.appendChild(row);
        feed.scrollTop = feed.scrollHeight;
    }

    function appendSystemMessage(text) {
        appendMessage('system', `<div class="admin-ai-bubble">${escapeHtml(text)}</div>`);
    }

    function appendUserMessage(text) {
        appendMessage('user', `<div class="admin-ai-bubble">${escapeHtml(text)}</div>`);
    }

    function appendAssistantMessage(html) {
        appendMessage('assistant', `<div class="admin-ai-bubble">${html}</div>`);
    }

    function setBusy(isBusy) {
        state.isBusy = isBusy;
        const sendButton = document.getElementById('admin-ai-send');
        const input = document.getElementById('admin-ai-input');

        if (sendButton) {
            sendButton.disabled = isBusy;
            sendButton.innerHTML = isBusy ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-paper-plane"></i>';
        }
        if (input) {
            input.disabled = isBusy;
        }
    }

    async function warmupStatus() {
        try {
            const payload = await requestJson(`${window.API_BASE}${API_ENDPOINT}?mode=status`, {
                method: 'GET'
            });
            if (!payload.success) {
                return;
            }
            state.ready = true;
            if (payload.data?.llm?.enabled) {
                appendSystemMessage('LLM enhancement is enabled for richer answers.');
            }
        } catch (error) {
            appendSystemMessage('AI endpoint is currently unavailable.');
        }
    }

    async function runQuickAction(mode) {
        if (state.isBusy) {
            return;
        }

        openPanel();
        setBusy(true);
        appendSystemMessage(`Running ${mode}...`);

        try {
            const payload = await requestJson(`${window.API_BASE}${API_ENDPOINT}?mode=${encodeURIComponent(mode)}`, {
                method: 'GET'
            });

            if (!payload.success) {
                throw new Error(payload.message || 'Request failed');
            }

            const html = renderModeResult(mode, payload.data || {});
            appendAssistantMessage(html);
        } catch (error) {
            appendSystemMessage(error.message || 'Failed to run AI action.');
        } finally {
            setBusy(false);
        }
    }

    async function askCopilot(message) {
        if (state.isBusy) {
            return;
        }

        openPanel();
        appendUserMessage(message);
        setBusy(true);

        try {
            const payload = await requestJson(`${window.API_BASE}${API_ENDPOINT}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message,
                    context: {
                        page: window.currentPage || 'home',
                        title: document.getElementById('page-title-top')?.textContent || '',
                        pathname: window.location.pathname || '',
                        url: window.location.href || ''
                    }
                })
            });

            if (!payload.success) {
                throw new Error(payload.message || 'Failed to get AI response');
            }

            const data = payload.data || {};
            const reply = escapeHtml(data.reply || 'No response available.');
            const source = data.response_source === 'llm' ? 'LLM' : 'Rules';
            const meta = `<div class="admin-ai-meta">Source: ${source} | Intent: ${escapeHtml(data.intent || 'general')}</div>`;
            appendAssistantMessage(`${reply.replace(/\n/g, '<br>')}${meta}`);
        } catch (error) {
            appendSystemMessage(error.message || 'Failed to get AI response.');
        } finally {
            setBusy(false);
        }
    }

    function renderModeResult(mode, data) {
        if (mode === 'summary') {
            return renderSummary(data.summary || {});
        }
        if (mode === 'reorder') {
            return renderReorder(data.suggestions || []);
        }
        if (mode === 'anomalies') {
            return renderAnomalies(data.anomalies || {});
        }
        return `<div>${escapeHtml(JSON.stringify(data))}</div>`;
    }

    function renderSummary(summary) {
        const highlights = Array.isArray(summary.highlights) ? summary.highlights : [];
        const metrics = summary.metrics || {};
        const listHtml = highlights.length
            ? `<ul>${highlights.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
            : '<p>No highlights available.</p>';

        return `
            <div class="admin-ai-block-title">Daily Summary (${escapeHtml(summary.date || 'N/A')})</div>
            <p>${escapeHtml(summary.summary_text || 'No summary text available.').replace(/\n/g, '<br>')}</p>
            ${listHtml}
            <div class="admin-ai-kpi-grid">
                <div class="admin-ai-kpi"><span>Orders</span><strong>${Number(metrics.orders_today || 0)}</strong></div>
                <div class="admin-ai-kpi"><span>Revenue</span><strong>${formatCurrency(metrics.revenue_today || 0)}</strong></div>
                <div class="admin-ai-kpi"><span>Low Stock</span><strong>${Number(metrics.low_stock_items || 0)}</strong></div>
                <div class="admin-ai-kpi"><span>Overdue POs</span><strong>${Number(metrics.overdue_purchase_orders || 0)}</strong></div>
            </div>
        `;
    }

    function renderReorder(suggestions) {
        if (!Array.isArray(suggestions) || suggestions.length === 0) {
            return '<div class="admin-ai-block-title">Reorder Suggestions</div><p>No urgent reorder items found.</p>';
        }

        const top = suggestions.slice(0, 8);
        const rows = top.map((item) => `
            <tr>
                <td>${escapeHtml(item.name || '')}</td>
                <td>${escapeHtml(item.sku || '')}</td>
                <td>${escapeHtml((item.priority || '').toUpperCase())}</td>
                <td>${Number(item.quantity_available || 0)}</td>
                <td>${Number(item.suggested_order_qty || 0)}</td>
            </tr>
        `).join('');

        return `
            <div class="admin-ai-block-title">Reorder Suggestions</div>
            <div class="admin-ai-table-wrap">
                <table class="admin-ai-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Priority</th>
                            <th>Avail.</th>
                            <th>Suggest</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function renderAnomalies(anomalies) {
        const items = Array.isArray(anomalies.items) ? anomalies.items : [];
        const counts = anomalies.counts || {};
        if (items.length === 0) {
            return '<div class="admin-ai-block-title">Anomaly Scan</div><p>No major anomalies detected.</p>';
        }

        const top = items.slice(0, 8);
        const list = top.map((item) => `
            <li>
                <strong>[${escapeHtml((item.severity || 'low').toUpperCase())}] ${escapeHtml(item.title || '')}</strong><br>
                <span>${escapeHtml(item.detail || '')}</span>
            </li>
        `).join('');

        return `
            <div class="admin-ai-block-title">Anomaly Scan</div>
            <p>Critical: ${Number(counts.critical || 0)} | High: ${Number(counts.high || 0)} | Medium: ${Number(counts.medium || 0)}</p>
            <ul class="admin-ai-anomaly-list">${list}</ul>
        `;
    }

    async function requestJson(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });
        const payload = await response.json();
        return payload;
    }

    function formatCurrency(value) {
        const amount = Number(value || 0);
        return `PHP ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    window.AdminAICopilot = {
        init,
        open: openPanel
    };
})();
