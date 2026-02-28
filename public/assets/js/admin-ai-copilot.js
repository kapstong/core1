(function () {
    'use strict';

    const ALLOWED_ROLES = new Set(['admin', 'inventory_manager', 'purchasing_officer']);
    const API_ENDPOINT = '/ai/admin-copilot.php';

    const state = {
        ready: false,
        isOpen: false,
        isBusy: false,
        feedbackByResponseId: {}
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
                <button type="button" class="admin-ai-chip" data-ai-mode="history">Historical Trends</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="forecast">Trend Forecast</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="reorder">Reorder Suggestions</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="anomalies">Anomaly Scan</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="memory">Session Memory</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="evaluate">Self Check</button>
            </div>

            <div class="admin-ai-feed" id="admin-ai-feed"></div>

            <form class="admin-ai-form" id="admin-ai-form">
                <input
                    type="text"
                    id="admin-ai-input"
                    class="admin-ai-input"
                    maxlength="700"
                    placeholder="Ask in English or Filipino about inventory, purchasing, risk, or trends..."
                    autocomplete="off"
                />
                <button type="submit" class="admin-ai-send" id="admin-ai-send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        `;

        document.body.appendChild(launcher);
        document.body.appendChild(panel);

    }

    function bindEvents() {
        const launcher = document.getElementById('admin-ai-launcher');
        const closeBtn = document.getElementById('admin-ai-close');
        const panel = document.getElementById('admin-ai-panel');
        const form = document.getElementById('admin-ai-form');
        const input = document.getElementById('admin-ai-input');
        const feed = feedElement();

        if (!launcher || !closeBtn || !panel || !form || !input || !feed) {
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

        feed.addEventListener('click', async (event) => {
            const feedbackBtn = event.target.closest('.admin-ai-feedback-btn');
            if (feedbackBtn) {
                const responseId = feedbackBtn.getAttribute('data-response-id');
                const rating = feedbackBtn.getAttribute('data-rating');
                if (!responseId || !rating) {
                    return;
                }
                await submitFeedback(responseId, rating, feedbackBtn);
                return;
            }

            const followUpBtn = event.target.closest('.admin-ai-followup-btn');
            if (followUpBtn) {
                const question = followUpBtn.getAttribute('data-question');
                if (!question || state.isBusy) {
                    return;
                }
                await askCopilot(question);
            }
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
            return null;
        }

        const row = document.createElement('div');
        row.className = `admin-ai-msg admin-ai-msg-${type}`;
        row.innerHTML = html;
        feed.appendChild(row);
        feed.scrollTop = feed.scrollHeight;
        return row;
    }

    function appendSystemMessage(text) {
        appendMessage('system', `<div class="admin-ai-bubble">${escapeHtml(text)}</div>`);
    }

    function appendUserMessage(text) {
        appendMessage('user', `<div class="admin-ai-bubble">${escapeHtml(text)}</div>`);
    }

    function appendAssistantMessage(html, options = {}) {
        const metaHtml = options.metaHtml ? `<div class="admin-ai-meta">${options.metaHtml}</div>` : '';
        const followUpHtml = Array.isArray(options.followUps) ? renderFollowUpButtons(options.followUps) : '';
        const feedbackHtml = options.responseId ? renderFeedbackControls(options.responseId) : '';
        appendMessage('assistant', `<div class="admin-ai-bubble">${html}${metaHtml}${followUpHtml}${feedbackHtml}</div>`);
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
            const sourceMap = {
                llm: 'LLM',
                hybrid: 'Hybrid',
                rules_fallback: 'Rules fallback',
                rules: 'Rules'
            };
            const source = sourceMap[data.response_source] || 'Rules';
            const language = escapeHtml(data.language || 'en');
            const strategy = escapeHtml(data.router?.strategy || 'rules');
            appendAssistantMessage(reply.replace(/\n/g, '<br>'), {
                responseId: data.response_id || '',
                followUps: Array.isArray(data.follow_up_actions) ? data.follow_up_actions : [],
                metaHtml: `Source: ${source} | Intent: ${escapeHtml(data.intent || 'general')} | Lang: ${language} | Router: ${strategy}`
            });
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
        if (mode === 'history') {
            return renderHistory(data.history || {});
        }
        if (mode === 'forecast') {
            return renderForecast(data.forecast || {});
        }
        if (mode === 'reorder') {
            return renderReorder(data.suggestions || []);
        }
        if (mode === 'anomalies') {
            return renderAnomalies(data.anomalies || {});
        }
        if (mode === 'memory') {
            return renderMemory(data.memory || {});
        }
        if (mode === 'evaluate') {
            return renderEvaluation(data.evaluation || {});
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
                <td>${escapeHtml(item.optimal_reorder_date || 'N/A')}</td>
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
                            <th>Order By</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function renderHistory(history) {
        const totals = history.totals || {};
        const averages = history.averages || {};
        const trend = history.trend || {};
        const windowDays = Number(history.window_days || 0);

        return `
            <div class="admin-ai-block-title">Historical Analytics (${windowDays || 0} days)</div>
            <div class="admin-ai-kpi-grid">
                <div class="admin-ai-kpi"><span>Total Orders</span><strong>${Number(totals.orders || 0)}</strong></div>
                <div class="admin-ai-kpi"><span>Total Revenue</span><strong>${formatCurrency(totals.revenue || 0)}</strong></div>
                <div class="admin-ai-kpi"><span>7d Orders/Day</span><strong>${Number(averages.orders_per_day_7d || 0).toFixed(1)}</strong></div>
                <div class="admin-ai-kpi"><span>7d Revenue/Day</span><strong>${formatCurrency(averages.revenue_per_day_7d || 0)}</strong></div>
            </div>
            <p class="admin-ai-meta">Trend: ${escapeHtml(trend.direction || 'stable')} | Orders 7d change: ${Number(trend.orders_change_7d_pct || 0).toFixed(1)}% | Revenue 7d change: ${Number(trend.revenue_change_7d_pct || 0).toFixed(1)}%</p>
        `;
    }

    function renderForecast(forecast) {
        const projection = forecast.projection || {};
        const trend = escapeHtml(forecast.trend || 'stable');
        const confidence = escapeHtml(forecast.confidence || 'medium');
        const previewRows = Array.isArray(forecast.series) ? forecast.series.slice(0, 7) : [];

        const rows = previewRows.map((row) => `
            <tr>
                <td>${escapeHtml(row.date || '')}</td>
                <td>${Number(row.orders || 0).toFixed(1)}</td>
                <td>${formatCurrency(row.revenue || 0)}</td>
            </tr>
        `).join('');

        return `
            <div class="admin-ai-block-title">Trend Forecast (${Number(forecast.horizon_days || 0)} days)</div>
            <p>${escapeHtml(forecast.summary || 'No forecast summary available.')}</p>
            <div class="admin-ai-kpi-grid">
                <div class="admin-ai-kpi"><span>Trend</span><strong>${trend}</strong></div>
                <div class="admin-ai-kpi"><span>Confidence</span><strong>${confidence}</strong></div>
                <div class="admin-ai-kpi"><span>Orders Next 7d</span><strong>${Number(projection.orders_next_7d || 0).toFixed(0)}</strong></div>
                <div class="admin-ai-kpi"><span>Revenue Next 7d</span><strong>${formatCurrency(projection.revenue_next_7d || 0)}</strong></div>
            </div>
            <div class="admin-ai-table-wrap">
                <table class="admin-ai-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Revenue</th>
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

    function renderMemory(memory) {
        const rows = Array.isArray(memory.recent_messages) ? memory.recent_messages.slice(-6).reverse() : [];
        const items = rows.map((row) => `
            <li>
                <strong>${escapeHtml(row.intent || 'general')}</strong> (${escapeHtml(row.language || 'en')})
                <br>
                <span>${escapeHtml(row.message || '')}</span>
            </li>
        `).join('');

        return `
            <div class="admin-ai-block-title">Session Memory</div>
            <p class="admin-ai-meta">Last intent: ${escapeHtml(memory.last_intent || 'n/a')} | Last language: ${escapeHtml(memory.last_language || 'n/a')} | Feedback: ${Number(memory.feedback_count || 0)}</p>
            ${items ? `<ul class="admin-ai-anomaly-list">${items}</ul>` : '<p>No prior turns recorded in this session.</p>'}
        `;
    }

    function renderEvaluation(evaluation) {
        const summary = evaluation.summary || {};
        const cases = Array.isArray(evaluation.cases) ? evaluation.cases : [];
        const rows = cases.map((item) => `
            <tr>
                <td>${escapeHtml(item.input || '')}</td>
                <td>${escapeHtml(item.actual_intent || '')}</td>
                <td>${escapeHtml(item.router_strategy || '')}</td>
                <td>${item.passed ? 'PASS' : 'FAIL'}</td>
            </tr>
        `).join('');

        return `
            <div class="admin-ai-block-title">Copilot Self Check</div>
            <p>Score: ${Number(summary.score_pct || 0).toFixed(1)}% (${Number(summary.passed || 0)} / ${Number(summary.total || 0)})</p>
            <div class="admin-ai-table-wrap">
                <table class="admin-ai-table">
                    <thead>
                        <tr>
                            <th>Input</th>
                            <th>Intent</th>
                            <th>Router</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function renderFollowUpButtons(actions) {
        const clean = actions
            .filter((item) => typeof item === 'string')
            .map((item) => item.trim())
            .filter(Boolean)
            .slice(0, 5);
        if (clean.length === 0) {
            return '';
        }

        const buttons = clean.map((action) => `
            <button type="button" class="admin-ai-followup-btn" data-question="${escapeHtml(action)}">
                ${escapeHtml(action)}
            </button>
        `).join('');

        return `<div class="admin-ai-followups">${buttons}</div>`;
    }

    function renderFeedbackControls(responseId) {
        const escapedId = escapeHtml(responseId);
        return `
            <div class="admin-ai-feedback" data-response-id="${escapedId}">
                <span class="admin-ai-feedback-label">Was this helpful?</span>
                <button type="button" class="admin-ai-feedback-btn" data-response-id="${escapedId}" data-rating="up" aria-label="Helpful"><i class="fas fa-thumbs-up"></i></button>
                <button type="button" class="admin-ai-feedback-btn" data-response-id="${escapedId}" data-rating="down" aria-label="Not helpful"><i class="fas fa-thumbs-down"></i></button>
                <span class="admin-ai-feedback-status"></span>
            </div>
        `;
    }

    async function submitFeedback(responseId, rating, triggerButton) {
        if (state.feedbackByResponseId[responseId]) {
            return;
        }

        const wrap = triggerButton.closest('.admin-ai-feedback');
        if (!wrap) {
            return;
        }

        const status = wrap.querySelector('.admin-ai-feedback-status');
        const buttons = wrap.querySelectorAll('.admin-ai-feedback-btn');
        buttons.forEach((btn) => {
            btn.disabled = true;
        });
        if (status) {
            status.textContent = 'Sending...';
        }

        try {
            const payload = await requestJson(`${window.API_BASE}${API_ENDPOINT}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'feedback',
                    response_id: responseId,
                    rating
                })
            });

            if (!payload.success) {
                throw new Error(payload.message || 'Feedback failed');
            }

            state.feedbackByResponseId[responseId] = rating;
            triggerButton.classList.add('active');
            if (status) {
                status.textContent = 'Thanks';
            }
        } catch (error) {
            if (status) {
                status.textContent = 'Failed';
            }
            buttons.forEach((btn) => {
                btn.disabled = false;
            });
        }
    }

    async function requestJson(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        const raw = await response.text();
        let payload = {};
        try {
            payload = raw ? JSON.parse(raw) : {};
        } catch (error) {
            const message = response.ok ? 'Invalid API response' : `HTTP ${response.status}`;
            throw new Error(message);
        }

        if (!response.ok && !payload.success) {
            throw new Error(payload.message || `HTTP ${response.status}`);
        }

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
