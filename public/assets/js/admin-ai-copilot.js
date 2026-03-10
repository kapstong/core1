(function () {
    'use strict';

    const ALLOWED_ROLES = new Set(['admin', 'inventory_manager', 'purchasing_officer', 'staff']);
    const API_ENDPOINT = '/ai/admin-copilot.php';

    const state = {
        ready: false,
        isOpen: false,
        isMaximized: false,
        isBusy: false,
        feedbackByResponseId: {},
        approvalsByResponseId: {},
        hasShownWelcome: false
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
                    <p class="admin-ai-subtitle">AI-generated operations assistant. Human approval required before action.</p>
                </div>
                <div class="admin-ai-header-actions">
                    <button type="button" class="admin-ai-maximize" id="admin-ai-maximize" aria-label="Enlarge AI panel" title="Enlarge">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button type="button" class="admin-ai-close" id="admin-ai-close" aria-label="Close AI panel" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="admin-ai-quick-actions">
                <button type="button" class="admin-ai-chip" data-ai-mode="summary">Daily Summary</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="history">Historical Trends</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="forecast">Trend Forecast</button>
                <button type="button" class="admin-ai-chip" data-ai-mode="reorder">Reorder Suggestions</button>
            </div>
            <div class="admin-ai-disclosure">Every assistant response is marked with source and can be approved/rejected for audit logging.</div>

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
        const maximizeBtn = document.getElementById('admin-ai-maximize');
        const closeBtn = document.getElementById('admin-ai-close');
        const panel = document.getElementById('admin-ai-panel');
        const form = document.getElementById('admin-ai-form');
        const input = document.getElementById('admin-ai-input');
        const feed = feedElement();

        if (!launcher || !maximizeBtn || !closeBtn || !panel || !form || !input || !feed) {
            return;
        }

        launcher.addEventListener('click', () => {
            if (state.isOpen) {
                closePanel();
            } else {
                openPanel();
            }
        });

        maximizeBtn.addEventListener('click', () => {
            toggleMaximized();
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
            const approvalBtn = event.target.closest('.admin-ai-approve-btn');
            if (approvalBtn) {
                const responseId = approvalBtn.getAttribute('data-response-id');
                const decision = approvalBtn.getAttribute('data-decision');
                if (!responseId || !decision) {
                    return;
                }
                await submitApproval(responseId, decision, approvalBtn);
                return;
            }

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

        if (!state.hasShownWelcome) {
            const user = getCurrentUser();
            const role = user?.role ? String(user.role).replace(/_/g, ' ') : 'user';
            appendSystemMessage(`Conversational mode is ready. I will adapt insights for your ${role} role and ask clarifying questions when needed.`);
            state.hasShownWelcome = true;
        }
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

    function toggleMaximized(force) {
        const panel = document.getElementById('admin-ai-panel');
        const maximizeBtn = document.getElementById('admin-ai-maximize');
        if (!panel || !maximizeBtn) {
            return;
        }

        const nextState = typeof force === 'boolean' ? force : !state.isMaximized;
        state.isMaximized = nextState;
        panel.classList.toggle('maximized', nextState);

        maximizeBtn.innerHTML = nextState ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
        maximizeBtn.setAttribute('aria-label', nextState ? 'Restore AI panel size' : 'Enlarge AI panel');
        maximizeBtn.setAttribute('title', nextState ? 'Restore' : 'Enlarge');
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
        const sourcePill = renderSourcePill(options.responseSource || 'rules', options.aiGenerated !== false);
        const metaHtml = options.metaHtml ? `<div class="admin-ai-meta">${options.metaHtml}</div>` : '';
        const followUpHtml = Array.isArray(options.followUps) ? renderFollowUpButtons(options.followUps) : '';
        const approvalHtml = options.responseId ? renderApprovalControls(options.responseId, options.responseSource || 'rules', options.intent || 'general') : '';
        const feedbackHtml = options.responseId ? renderFeedbackControls(options.responseId) : '';
        appendMessage('assistant', `<div class="admin-ai-bubble">${sourcePill}${html}${metaHtml}${followUpHtml}${approvalHtml}${feedbackHtml}</div>`);
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
            const responseId = String(payload?.data?.response_id || '');
            const responseSource = String(payload?.data?.response_source || 'rules');
            appendAssistantMessage(html, {
                responseId,
                responseSource,
                intent: mode,
                aiGenerated: true,
                metaHtml: `Source: ${escapeHtml(responseSource)} | Intent: ${escapeHtml(mode)} | AI-generated: yes | Human approval: required`
            });
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
        const user = getCurrentUser();

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
                        url: window.location.href || '',
                        role: user?.role || '',
                        user_name: user?.full_name || user?.username || ''
                    }
                })
            });

            if (!payload.success) {
                throw new Error(payload.message || 'Failed to get AI response');
            }

            const data = payload.data || {};
            const rawReply = String(data.reply || 'No response available.');
            const replyHtml = formatAssistantReply(rawReply);
            const responseSourceRaw = String(data.response_source || 'rules');
            const sourceMap = {
                llm: 'LLM',
                hybrid: 'Hybrid',
                rules_fallback: 'Rules fallback',
                rules: 'Rules',
                clarification: 'Clarification'
            };
            const source = sourceMap[responseSourceRaw] || 'Rules';
            const language = escapeHtml(data.language || 'en');
            const strategy = escapeHtml(data.router?.strategy || 'rules');
            const roleLens = escapeHtml(data.role_profile?.role_label || user?.role || '');
            const clarificationFlag = data.needs_clarification ? ' | Clarifying: yes' : '';

            let followUpActions = [];
            if (data.needs_clarification && Array.isArray(data.clarification?.options)) {
                followUpActions = data.clarification.options
                    .map((option) => {
                        if (!option || typeof option !== 'object') {
                            return '';
                        }
                        return String(option.label || option.query || '').trim();
                    })
                    .filter(Boolean);
            } else {
                followUpActions = Array.isArray(data.follow_up_actions) ? data.follow_up_actions : [];
            }

            appendAssistantMessage(replyHtml, {
                responseId: data.needs_clarification ? '' : (data.response_id || ''),
                responseSource: responseSourceRaw,
                intent: data.intent || 'general',
                aiGenerated: true,
                followUps: followUpActions,
                metaHtml: `Source: ${source} | Intent: ${escapeHtml(data.intent || 'general')} | Lang: ${language} | Role: ${roleLens} | Router: ${strategy}${clarificationFlag} | AI-generated: yes`
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
            <button type="button" class="admin-ai-followup-btn" data-question="${escapeHtml(action)}" title="${escapeHtml(action)}">
                ${escapeHtml(formatActionLabel(action))}
            </button>
        `).join('');

        return `<div class="admin-ai-followups">${buttons}</div>`;
    }

    function renderFeedbackControls(responseId) {
        const escapedId = escapeHtml(responseId);
        return `
            <div class="admin-ai-feedback" data-response-id="${escapedId}">
                <span class="admin-ai-feedback-label">Was this helpful?</span>
                <button type="button" class="admin-ai-feedback-btn" data-response-id="${escapedId}" data-rating="up" aria-label="Helpful">Yes</button>
                <button type="button" class="admin-ai-feedback-btn" data-response-id="${escapedId}" data-rating="down" aria-label="Not helpful">No</button>
                <span class="admin-ai-feedback-status"></span>
            </div>
        `;
    }

    function renderSourcePill(responseSource, aiGenerated = true) {
        const labelMap = {
            llm: 'LLM',
            hybrid: 'Hybrid',
            rules: 'Rules',
            rules_fallback: 'Rules fallback',
            clarification: 'Clarification'
        };
        const source = String(responseSource || 'rules');
        const sourceLabel = labelMap[source] || source;
        const typeLabel = aiGenerated ? 'AI-generated' : 'System-generated';
        return `<div class="admin-ai-source-pill">${escapeHtml(typeLabel)} | Source: ${escapeHtml(sourceLabel)}</div>`;
    }

    function renderApprovalControls(responseId, responseSource, intent) {
        const escapedId = escapeHtml(responseId);
        return `
            <div class="admin-ai-approval" data-response-id="${escapedId}" data-response-source="${escapeHtml(responseSource || 'rules')}" data-intent="${escapeHtml(intent || 'general')}">
                <span class="admin-ai-feedback-label">Decision</span>
                <button type="button" class="admin-ai-approve-btn" data-response-id="${escapedId}" data-decision="approved" aria-label="Approve AI recommendation">Approve</button>
                <button type="button" class="admin-ai-approve-btn" data-response-id="${escapedId}" data-decision="rejected" aria-label="Reject AI recommendation">Reject</button>
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

    async function submitApproval(responseId, decision, triggerButton) {
        if (state.approvalsByResponseId[responseId]) {
            return;
        }

        const wrap = triggerButton.closest('.admin-ai-approval');
        if (!wrap) {
            return;
        }

        const status = wrap.querySelector('.admin-ai-feedback-status');
        const buttons = wrap.querySelectorAll('.admin-ai-approve-btn');
        const responseSource = wrap.getAttribute('data-response-source') || 'rules';
        const intent = wrap.getAttribute('data-intent') || 'general';

        buttons.forEach((btn) => {
            btn.disabled = true;
        });
        if (status) {
            status.textContent = 'Saving...';
        }

        try {
            const payload = await requestJson(`${window.API_BASE}${API_ENDPOINT}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'approve',
                    response_id: responseId,
                    decision,
                    response_source: responseSource,
                    intent
                })
            });

            if (!payload.success) {
                throw new Error(payload.message || 'Approval failed');
            }

            state.approvalsByResponseId[responseId] = decision;
            triggerButton.classList.add('active');
            if (status) {
                status.textContent = decision === 'approved' ? 'Approved' : 'Rejected';
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

    function formatAssistantReply(text) {
        const input = String(text || '').replace(/\r\n/g, '\n').trim();
        if (!input) {
            return '<p class="admin-ai-rich-paragraph">No response available.</p>';
        }

        const lines = input.split('\n');
        const blocks = [];
        let i = 0;

        while (i < lines.length) {
            const raw = lines[i] || '';
            const line = raw.trim();

            if (!line) {
                i += 1;
                continue;
            }

            const headingMatch = line.match(/^(#{1,3})\s+(.+)$/);
            if (headingMatch) {
                const level = headingMatch[1].length;
                blocks.push(`<div class="admin-ai-rich-heading level-${level}">${renderInlineRichText(headingMatch[2])}</div>`);
                i += 1;
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                const items = [];
                while (i < lines.length) {
                    const candidate = (lines[i] || '').trim();
                    if (!/^[-*]\s+/.test(candidate)) {
                        break;
                    }
                    items.push(candidate.replace(/^[-*]\s+/, '').trim());
                    i += 1;
                }
                blocks.push(`<ul class="admin-ai-rich-list">${items.map((item) => `<li>${renderInlineRichText(item)}</li>`).join('')}</ul>`);
                continue;
            }

            if (/^\d+\.\s+/.test(line)) {
                const items = [];
                while (i < lines.length) {
                    const candidate = (lines[i] || '').trim();
                    if (!/^\d+\.\s+/.test(candidate)) {
                        break;
                    }
                    items.push(candidate.replace(/^\d+\.\s+/, '').trim());
                    i += 1;
                }
                blocks.push(`<ol class="admin-ai-rich-list ordered">${items.map((item) => `<li>${renderInlineRichText(item)}</li>`).join('')}</ol>`);
                continue;
            }

            if (isKeyValueLine(line)) {
                const entries = [];
                while (i < lines.length) {
                    const candidate = (lines[i] || '').trim();
                    if (!isKeyValueLine(candidate)) {
                        break;
                    }
                    const split = candidate.split(':');
                    const key = split.shift() || '';
                    const value = split.join(':');
                    entries.push({
                        key: key.trim(),
                        value: value.trim()
                    });
                    i += 1;
                }

                if (entries.length >= 2) {
                    blocks.push(`
                        <div class="admin-ai-rich-kv">
                            ${entries.map((entry) => `
                                <div class="admin-ai-rich-kv-row">
                                    <span class="admin-ai-rich-kv-key">${renderInlineRichText(entry.key)}</span>
                                    <span class="admin-ai-rich-kv-value">${renderInlineRichText(entry.value)}</span>
                                </div>
                            `).join('')}
                        </div>
                    `);
                    continue;
                }

                const entry = entries[0];
                blocks.push(`<p class="admin-ai-rich-paragraph"><strong>${renderInlineRichText(entry.key)}:</strong> ${renderInlineRichText(entry.value)}</p>`);
                continue;
            }

            const paragraphLines = [];
            while (i < lines.length) {
                const candidate = (lines[i] || '').trim();
                if (!candidate) {
                    break;
                }
                if (/^(#{1,3})\s+/.test(candidate) || /^[-*]\s+/.test(candidate) || /^\d+\.\s+/.test(candidate)) {
                    break;
                }
                paragraphLines.push(candidate);
                i += 1;
            }

            if (paragraphLines.length > 0) {
                blocks.push(`<p class="admin-ai-rich-paragraph">${renderInlineRichText(paragraphLines.join(' '))}</p>`);
                continue;
            }

            i += 1;
        }

        return blocks.join('');
    }

    function renderInlineRichText(text) {
        let safe = escapeHtml(text || '');
        safe = safe.replace(/`([^`]+)`/g, '<code class="admin-ai-inline-code">$1</code>');
        safe = safe.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        safe = safe.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
        return safe;
    }

    function isKeyValueLine(text) {
        if (!text || !text.includes(':')) {
            return false;
        }
        const parts = text.split(':');
        if (parts.length < 2) {
            return false;
        }
        const key = (parts.shift() || '').trim();
        const value = parts.join(':').trim();
        return key.length >= 2 && key.length <= 48 && value.length > 0;
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatActionLabel(action) {
        const text = String(action || '').trim();
        if (!text) {
            return '';
        }
        if (text.length <= 54) {
            return text;
        }
        return `${text.slice(0, 51)}...`;
    }

    window.AdminAICopilot = {
        init,
        open: openPanel,
        toggleSize: toggleMaximized
    };
})();
