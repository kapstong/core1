<?php
/**
 * Admin AI Copilot API
 * GET  /backend/api/ai/admin-copilot.php?mode=status|summary|reorder|anomalies|history|forecast|insights
 * POST /backend/api/ai/admin-copilot.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        handleGetRequest();
    }

    if ($method === 'POST') {
        handlePostRequest();
    }

    Response::error('Method not allowed', 405);
} catch (Exception $e) {
    error_log('Admin AI copilot error: ' . $e->getMessage());
    Response::serverError('Unable to process AI copilot request right now.');
}

function handleGetRequest(): void {
    $mode = strtolower(trim((string)($_GET['mode'] ?? 'status')));
    $db = Database::getInstance();

    switch ($mode) {
        case 'status':
            $llmConfig = getAdminAiLlmConfig();
            Response::success([
                'name' => 'Admin AI Copilot',
                'version' => '1.2',
                'read_only' => true,
                'capabilities' => [
                    'daily_summary',
                    'historical_analytics',
                    'trend_forecasting',
                    'reorder_suggestions',
                    'anomaly_detection',
                    'bilingual_qa'
                ],
                'languages' => [
                    'en',
                    'fil'
                ],
                'endpoints' => [
                    'status' => 'GET ?mode=status',
                    'summary' => 'GET ?mode=summary',
                    'history' => 'GET ?mode=history&days=90',
                    'forecast' => 'GET ?mode=forecast&days=14',
                    'reorder' => 'GET ?mode=reorder',
                    'anomalies' => 'GET ?mode=anomalies',
                    'insights' => 'GET ?mode=insights',
                    'ask' => 'POST body: { message, context }'
                ],
                'llm' => [
                    'enabled' => $llmConfig['enabled'],
                    'configured' => $llmConfig['configured'],
                    'provider' => $llmConfig['provider'],
                    'model' => $llmConfig['model']
                ]
            ], 'Admin AI copilot is ready');
            return;

        case 'summary':
            $summary = buildDailySummaryPayload($db);
            logAiActivity('summary', ['summary_date' => $summary['date'] ?? null]);
            Response::success([
                'mode' => 'summary',
                'summary' => $summary
            ], 'Daily summary generated');
            return;

        case 'history':
            $historyDays = max(30, min(365, (int)($_GET['days'] ?? 90)));
            $history = buildHistoricalAnalyticsPayload($db, $historyDays);
            logAiActivity('history', ['days' => $historyDays]);
            Response::success([
                'mode' => 'history',
                'days' => $historyDays,
                'history' => $history
            ], 'Historical analytics generated');
            return;

        case 'forecast':
            $forecastDays = max(7, min(90, (int)($_GET['days'] ?? 14)));
            $history = buildHistoricalAnalyticsPayload($db, 120);
            $forecast = buildTrendForecastPayload($history, $forecastDays);
            logAiActivity('forecast', ['days' => $forecastDays, 'trend' => $forecast['trend'] ?? 'stable']);
            Response::success([
                'mode' => 'forecast',
                'days' => $forecastDays,
                'forecast' => $forecast
            ], 'Trend forecast generated');
            return;

        case 'reorder':
            $suggestions = buildReorderSuggestions($db, 20);
            logAiActivity('reorder', ['suggestion_count' => count($suggestions)]);
            Response::success([
                'mode' => 'reorder',
                'suggestions' => $suggestions,
                'count' => count($suggestions)
            ], 'Reorder suggestions generated');
            return;

        case 'anomalies':
            $anomalies = detectOperationalAnomalies($db, 25);
            logAiActivity('anomalies', [
                'total_anomalies' => count($anomalies['items']),
                'critical' => $anomalies['counts']['critical'] ?? 0
            ]);
            Response::success([
                'mode' => 'anomalies',
                'anomalies' => $anomalies
            ], 'Anomaly scan complete');
            return;

        case 'insights':
            $summary = buildDailySummaryPayload($db);
            $history = buildHistoricalAnalyticsPayload($db, 90);
            $forecast = buildTrendForecastPayload($history, 14);
            $reorder = buildReorderSuggestions($db, 12);
            $anomalies = detectOperationalAnomalies($db, 12);

            logAiActivity('insights', [
                'trend' => $forecast['trend'] ?? 'stable',
                'reorder_count' => count($reorder),
                'critical_anomalies' => (int)($anomalies['counts']['critical'] ?? 0)
            ]);

            Response::success([
                'mode' => 'insights',
                'summary' => $summary,
                'history' => $history,
                'forecast' => $forecast,
                'reorder_suggestions' => $reorder,
                'anomalies' => $anomalies
            ], 'Integrated AI insights generated');
            return;

        default:
            Response::error('Invalid mode. Use status, summary, history, forecast, reorder, anomalies, or insights.', 400);
            return;
    }
}

function handlePostRequest(): void {
    enforceAdminAiRateLimit();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $message = trim((string)($input['message'] ?? ''));
    if ($message === '') {
        Response::error('Message is required', 400);
    }
    if (strlen($message) > 700) {
        Response::error('Message is too long (max 700 characters)', 400);
    }

    $context = sanitizeAdminAiContext(isset($input['context']) && is_array($input['context']) ? $input['context'] : []);
    $db = Database::getInstance();

    $reply = buildAdminCopilotReply($db, $message, $context);
    logAiActivity('ask', [
        'intent' => $reply['intent'] ?? 'general',
        'response_source' => $reply['response_source'] ?? 'rules'
    ]);

    Response::success($reply, 'AI response generated');
}

function enforceAdminAiRateLimit(int $windowSeconds = 60, int $maxRequests = 24): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $bucket = $_SESSION['admin_ai_rate_limit'] ?? [];
    if (!is_array($bucket)) {
        $bucket = [];
    }

    $now = time();
    $fresh = [];
    foreach ($bucket as $timestamp) {
        if (!is_int($timestamp) && !is_numeric($timestamp)) {
            continue;
        }
        $value = (int)$timestamp;
        if ($value > ($now - $windowSeconds)) {
            $fresh[] = $value;
        }
    }

    if (count($fresh) >= $maxRequests) {
        Response::error('Too many AI requests. Please wait a moment and try again.', 429);
    }

    $fresh[] = $now;
    $_SESSION['admin_ai_rate_limit'] = array_slice($fresh, -$maxRequests);
}

function sanitizeAdminAiContext(array $context): array {
    $sanitized = [];
    $limits = [
        'page' => 64,
        'title' => 160,
        'pathname' => 220,
        'url' => 400
    ];

    foreach ($limits as $key => $maxLen) {
        if (!array_key_exists($key, $context) || !is_scalar($context[$key])) {
            continue;
        }

        $value = trim((string)$context[$key]);
        if ($value === '') {
            continue;
        }

        if (strlen($value) > $maxLen) {
            $value = substr($value, 0, $maxLen);
        }

        $sanitized[$key] = $value;
    }

    return $sanitized;
}

function buildAdminCopilotReply(Database $db, string $message, array $context): array {
    $normalized = normalizeAdminAiMessage($message);
    $language = detectAdminLanguage($normalized);
    $intent = detectAdminIntent($normalized);

    $summary = buildDailySummaryPayload($db);
    $history = buildHistoricalAnalyticsPayload($db, 90);
    $forecast = buildTrendForecastPayload($history, 14);
    $reorder = buildReorderSuggestions($db, 8);
    $anomalies = detectOperationalAnomalies($db, 10);

    $replyText = '';
    $followUp = [];
    $responseSource = 'rules';

    if ($intent === 'summary') {
        $replyText = buildSummaryIntentReply($summary, $forecast, $language);
        $followUp = [
            'Show reorder suggestions',
            'Show anomalies',
            'Show trend forecast'
        ];
    } elseif ($intent === 'history') {
        $replyText = buildHistoryIntentReply($history, $language);
        $followUp = [
            'Show trend forecast',
            'Show anomalies',
            'Show reorder suggestions'
        ];
    } elseif ($intent === 'forecast') {
        $replyText = buildForecastIntentReply($forecast, $language);
        $followUp = [
            'Show reorder suggestions',
            'Show anomalies',
            'Daily summary'
        ];
    } elseif ($intent === 'reorder') {
        if (empty($reorder)) {
            $replyText = $language === 'fil'
                ? 'Walang urgent na reorder suggestion mula sa kasalukuyang inventory at demand history.'
                : 'No urgent reorder suggestions were found from current inventory and demand history.';
        } else {
            $top = array_slice($reorder, 0, 3);
            $lines = [];
            foreach ($top as $item) {
                $cover = $item['days_of_cover'] !== null
                    ? number_format((float)$item['days_of_cover'], 1) . ($language === 'fil' ? ' araw' : ' days')
                    : ($language === 'fil' ? 'walang demand history' : 'no demand history');
                $dateLabel = (string)($item['optimal_reorder_date'] ?? 'N/A');
                if ($language === 'fil') {
                    $lines[] = $item['name'] . ' (' . $item['sku'] . '): mag-order ng ' .
                        $item['suggested_order_qty'] . ' units bago ' . $dateLabel . ', cover ' . $cover . '.';
                } else {
                    $lines[] = $item['name'] . ' (' . $item['sku'] . '): order ' .
                        $item['suggested_order_qty'] . ' units by ' . $dateLabel . ', cover ' . $cover . '.';
                }
            }
            $replyText = ($language === 'fil' ? "Top na reorder priorities:\n- " : "Top reorder priorities:\n- ") . implode("\n- ", $lines);
        }
        $followUp = [
            'Show trend forecast',
            'Show anomalies',
            'What is the reorder logic?'
        ];
    } elseif ($intent === 'anomalies') {
        if (empty($anomalies['items'])) {
            $replyText = $language === 'fil'
                ? 'Walang major anomalies na na-detect sa kasalukuyang mga rule.'
                : 'No major anomalies were detected with the current rules.';
        } else {
            $top = array_slice($anomalies['items'], 0, 4);
            $lines = [];
            foreach ($top as $item) {
                $lines[] = '[' . strtoupper($item['severity']) . '] ' . $item['title'];
            }
            $replyText = ($language === 'fil' ? "Mga kasalukuyang anomaly na na-detect:\n- " : "Current anomalies detected:\n- ") . implode("\n- ", $lines);
        }
        $followUp = [
            'Show critical anomalies only',
            'Show reorder suggestions',
            'Show trend forecast'
        ];
    } else {
        $replyText = buildRulesBasedGeneralReply($message, $summary, $reorder, $anomalies, $forecast, $language);
        $llmReply = maybeGenerateAdminLlmReply($message, $context, $summary, $reorder, $anomalies, $history, $forecast, $language);
        if ($llmReply !== '') {
            $replyText = $llmReply;
            $responseSource = 'llm';
        }
        $followUp = [
            'Daily summary',
            'Historical trends',
            'Trend forecast',
            'Reorder suggestions',
            'Anomaly scan'
        ];
    }

    return [
        'intent' => $intent,
        'language' => $language,
        'response_source' => $responseSource,
        'reply' => $replyText,
        'summary' => $summary,
        'history' => $history,
        'forecast' => $forecast,
        'reorder_suggestions' => $reorder,
        'anomalies' => $anomalies,
        'follow_up_actions' => $followUp,
        'generated_at' => gmdate('c'),
        'context' => [
            'page' => $context['page'] ?? null,
            'title' => $context['title'] ?? null
        ]
    ];
}

function normalizeAdminAiMessage(string $message): string {
    $normalized = strtolower(trim($message));
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return $normalized;
}

function detectAdminLanguage(string $normalized): string {
    if ($normalized === '') {
        return 'en';
    }

    if (preg_match('/\b(tagalog|filipino|filipina)\b/u', $normalized) === 1) {
        return 'fil';
    }

    $filipinoMarkers = [
        'ano', 'paano', 'kailan', 'magkano', 'gaano', 'bakit', 'saan',
        'ngayon', 'kahapon', 'bukas', 'benta', 'imbentaryo', 'stock',
        'kulang', 'sobra', 'order', 'reorder', 'anomaly', 'problema',
        'pakita', 'ipakita', 'buod', 'trend', 'forecast', 'taas', 'baba'
    ];

    $hits = 0;
    foreach ($filipinoMarkers as $token) {
        if (preg_match('/\b' . preg_quote($token, '/') . '\b/u', $normalized) === 1) {
            $hits++;
        }
    }

    if ($hits >= 2 || preg_match('/\b(ng|sa|mga|nang|para|kasi|lang|naman)\b/u', $normalized) === 1) {
        return 'fil';
    }

    return 'en';
}

function detectAdminIntent(string $normalized): string {
    if ($normalized === '') {
        return 'general';
    }

    if (preg_match('/\b(summary|daily brief|daily report|operations summary|what happened today|buod|daily buod|ulat ngayong araw)\b/iu', $normalized) === 1) {
        return 'summary';
    }

    if (preg_match('/\b(history|historical|past|last \d+ days|nakaraan|huling mga araw|trend history)\b/iu', $normalized) === 1) {
        return 'history';
    }

    if (preg_match('/\b(forecast|projection|projected|predict|prediction|trend forecast|susunod na trend|tinatayang benta)\b/iu', $normalized) === 1) {
        return 'forecast';
    }

    if (preg_match('/\b(reorder|stockout|restock|low stock|inventory risk|safety stock|replenish|kulang stock|muling order)\b/iu', $normalized) === 1) {
        return 'reorder';
    }

    if (preg_match('/\b(anomal(y|ies)|suspicious|fraud|mismatch|outlier|risk alert|issues|abnormal|kakaiba|problema)\b/iu', $normalized) === 1) {
        return 'anomalies';
    }

    return 'general';
}

function isAdminLanguageCapabilityQuestion(string $normalized): bool {
    if ($normalized === '') {
        return false;
    }

    if (preg_match('/\b(can you|do you|kaya mo|naiintindihan mo|marunong ka)\b.*\b(tagalog|filipino|english)\b/iu', $normalized) === 1) {
        return true;
    }

    if (preg_match('/\b(understand|speak|reply|respond|naiintindihan|sumagot)\b.*\b(tagalog|filipino|english)\b/iu', $normalized) === 1) {
        return true;
    }

    if (preg_match('/\b(tagalog|filipino)\b.*\?$/iu', $normalized) === 1) {
        return true;
    }

    return false;
}

function buildSummaryIntentReply(array $summary, array $forecast, string $language = 'en'): string {
    $ordersToday = (int)($summary['metrics']['orders_today'] ?? 0);
    $revenueToday = (float)($summary['metrics']['revenue_today'] ?? 0);
    $orderDelta = (float)($summary['metrics']['orders_delta_pct'] ?? 0.0);
    $revenueDelta = (float)($summary['metrics']['revenue_delta_pct'] ?? 0.0);
    $trend = (string)($forecast['trend'] ?? 'stable');

    if ($language === 'fil') {
        $trendText = $trend === 'upward' ? 'tumataas' : ($trend === 'downward' ? 'bumababa' : 'stable');
        $lines = [];
        $lines[] = 'Buod ng operations ngayon:';
        $lines[] = '- Orders: ' . $ordersToday . ' (' . formatSignedPercentage($orderDelta) . ' vs kahapon).';
        $lines[] = '- Revenue: PHP ' . number_format($revenueToday, 2) . ' (' . formatSignedPercentage($revenueDelta) . ' vs kahapon).';
        $lines[] = '- Forecast trend: ' . $trendText . '.';
        return implode("\n", $lines);
    }

    $lines = [];
    $lines[] = 'Daily operations summary:';
    $lines[] = '- Orders: ' . $ordersToday . ' (' . formatSignedPercentage($orderDelta) . ' vs yesterday).';
    $lines[] = '- Revenue: PHP ' . number_format($revenueToday, 2) . ' (' . formatSignedPercentage($revenueDelta) . ' vs yesterday).';
    $lines[] = '- Forecast trend: ' . $trend . '.';
    return implode("\n", $lines);
}

function buildHistoryIntentReply(array $history, string $language = 'en'): string {
    $days = (int)($history['window_days'] ?? 90);
    $totals = $history['totals'] ?? [];
    $averages = $history['averages'] ?? [];
    $trend = $history['trend'] ?? [];

    $totalOrders = (int)($totals['orders'] ?? 0);
    $totalRevenue = (float)($totals['revenue'] ?? 0.0);
    $avgOrders7d = (float)($averages['orders_per_day_7d'] ?? 0.0);
    $avgRevenue7d = (float)($averages['revenue_per_day_7d'] ?? 0.0);
    $direction = (string)($trend['direction'] ?? 'stable');

    if ($language === 'fil') {
        $lines = [];
        $lines[] = 'Historical analysis (' . $days . ' araw):';
        $lines[] = '- Total orders: ' . $totalOrders . '.';
        $lines[] = '- Total revenue: PHP ' . number_format($totalRevenue, 2) . '.';
        $lines[] = '- 7-day avg: ' . number_format($avgOrders7d, 1) . ' orders/day, PHP ' . number_format($avgRevenue7d, 2) . '/day.';
        $lines[] = '- Trend direction: ' . $direction . '.';
        return implode("\n", $lines);
    }

    $lines = [];
    $lines[] = 'Historical analysis (' . $days . ' days):';
    $lines[] = '- Total orders: ' . $totalOrders . '.';
    $lines[] = '- Total revenue: PHP ' . number_format($totalRevenue, 2) . '.';
    $lines[] = '- 7-day avg: ' . number_format($avgOrders7d, 1) . ' orders/day, PHP ' . number_format($avgRevenue7d, 2) . '/day.';
    $lines[] = '- Trend direction: ' . $direction . '.';
    return implode("\n", $lines);
}

function buildForecastIntentReply(array $forecast, string $language = 'en'): string {
    $horizon = (int)($forecast['horizon_days'] ?? 14);
    $projection = $forecast['projection'] ?? [];
    $ordersNext7 = (float)($projection['orders_next_7d'] ?? 0.0);
    $revenueNext7 = (float)($projection['revenue_next_7d'] ?? 0.0);
    $ordersNextHorizon = (float)($projection['orders_next_horizon'] ?? 0.0);
    $revenueNextHorizon = (float)($projection['revenue_next_horizon'] ?? 0.0);
    $trend = (string)($forecast['trend'] ?? 'stable');
    $confidence = (string)($forecast['confidence'] ?? 'medium');

    if ($language === 'fil') {
        $lines = [];
        $lines[] = 'Trend forecast (susunod na ' . $horizon . ' araw):';
        $lines[] = '- Trend: ' . $trend . ' (confidence: ' . $confidence . ').';
        $lines[] = '- Inaasahang 7-araw: ~' . number_format($ordersNext7, 0) . ' orders, PHP ' . number_format($revenueNext7, 2) . '.';
        $lines[] = '- Inaasahang ' . $horizon . '-araw: ~' . number_format($ordersNextHorizon, 0) . ' orders, PHP ' . number_format($revenueNextHorizon, 2) . '.';
        return implode("\n", $lines);
    }

    $lines = [];
    $lines[] = 'Trend forecast (next ' . $horizon . ' days):';
    $lines[] = '- Trend: ' . $trend . ' (confidence: ' . $confidence . ').';
    $lines[] = '- Expected next 7 days: ~' . number_format($ordersNext7, 0) . ' orders, PHP ' . number_format($revenueNext7, 2) . '.';
    $lines[] = '- Expected next ' . $horizon . ' days: ~' . number_format($ordersNextHorizon, 0) . ' orders, PHP ' . number_format($revenueNextHorizon, 2) . '.';
    return implode("\n", $lines);
}

function buildHistoricalAnalyticsPayload(Database $db, int $days = 90): array {
    $days = max(30, min(365, $days));
    $intervalDays = max(1, $days - 1);

    $salesRows = safeFetchAll($db, "
        SELECT
            activity_date,
            SUM(order_count) AS order_count,
            SUM(revenue_amount) AS revenue_amount
        FROM (
            SELECT
                DATE(co.order_date) AS activity_date,
                COUNT(*) AS order_count,
                COALESCE(SUM(co.total_amount), 0) AS revenue_amount
            FROM customer_orders co
            WHERE DATE(co.order_date) >= DATE_SUB(CURDATE(), INTERVAL {$intervalDays} DAY)
              AND co.status <> 'cancelled'
            GROUP BY DATE(co.order_date)

            UNION ALL

            SELECT
                DATE(s.sale_date) AS activity_date,
                COUNT(*) AS order_count,
                COALESCE(SUM(s.total_amount), 0) AS revenue_amount
            FROM sales s
            WHERE DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL {$intervalDays} DAY)
              AND s.payment_status <> 'failed'
            GROUP BY DATE(s.sale_date)
        ) x
        GROUP BY activity_date
        ORDER BY activity_date ASC
    ");

    $outboundRows = safeFetchAll($db, "
        SELECT
            activity_date,
            SUM(units) AS units_sold
        FROM (
            SELECT
                DATE(co.order_date) AS activity_date,
                SUM(coi.quantity) AS units
            FROM customer_order_items coi
            INNER JOIN customer_orders co ON co.id = coi.order_id
            WHERE DATE(co.order_date) >= DATE_SUB(CURDATE(), INTERVAL {$intervalDays} DAY)
              AND co.status <> 'cancelled'
            GROUP BY DATE(co.order_date)

            UNION ALL

            SELECT
                DATE(s.sale_date) AS activity_date,
                SUM(si.quantity) AS units
            FROM sale_items si
            INNER JOIN sales s ON s.id = si.sale_id
            WHERE DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL {$intervalDays} DAY)
              AND s.payment_status <> 'failed'
            GROUP BY DATE(s.sale_date)
        ) x
        GROUP BY activity_date
        ORDER BY activity_date ASC
    ");

    $inboundRows = safeFetchAll($db, "
        SELECT
            DATE(grn.received_date) AS activity_date,
            SUM(COALESCE(gi.quantity_accepted, 0)) AS units_received
        FROM grn_items gi
        INNER JOIN goods_received_notes grn ON grn.id = gi.grn_id
        WHERE DATE(grn.received_date) >= DATE_SUB(CURDATE(), INTERVAL {$intervalDays} DAY)
        GROUP BY DATE(grn.received_date)
        ORDER BY activity_date ASC
    ");

    $adjustmentRows = safeFetchAll($db, "
        SELECT
            DATE(sa.adjustment_date) AS activity_date,
            SUM(sa.quantity_adjusted) AS net_adjustment,
            COUNT(*) AS adjustment_count
        FROM stock_adjustments sa
        WHERE DATE(sa.adjustment_date) >= DATE_SUB(CURDATE(), INTERVAL {$intervalDays} DAY)
        GROUP BY DATE(sa.adjustment_date)
        ORDER BY activity_date ASC
    ");

    $salesMap = [];
    foreach ($salesRows as $row) {
        $date = (string)($row['activity_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $salesMap[$date] = [
            'orders' => (int)round((float)($row['order_count'] ?? 0)),
            'revenue' => (float)($row['revenue_amount'] ?? 0.0)
        ];
    }

    $outboundMap = [];
    foreach ($outboundRows as $row) {
        $date = (string)($row['activity_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $outboundMap[$date] = (int)round((float)($row['units_sold'] ?? 0));
    }

    $inboundMap = [];
    foreach ($inboundRows as $row) {
        $date = (string)($row['activity_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $inboundMap[$date] = (int)round((float)($row['units_received'] ?? 0));
    }

    $adjustmentMap = [];
    $adjustmentCountMap = [];
    foreach ($adjustmentRows as $row) {
        $date = (string)($row['activity_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $adjustmentMap[$date] = (int)round((float)($row['net_adjustment'] ?? 0));
        $adjustmentCountMap[$date] = (int)round((float)($row['adjustment_count'] ?? 0));
    }

    $startDate = new DateTimeImmutable('today -' . $intervalDays . ' day');
    $series = [];
    for ($i = 0; $i <= $intervalDays; $i++) {
        $date = $startDate->modify('+' . $i . ' day')->format('Y-m-d');
        $orders = (int)($salesMap[$date]['orders'] ?? 0);
        $revenue = (float)($salesMap[$date]['revenue'] ?? 0.0);
        $outboundUnits = (int)($outboundMap[$date] ?? 0);
        $inboundUnits = (int)($inboundMap[$date] ?? 0);
        $netAdjustment = (int)($adjustmentMap[$date] ?? 0);
        $adjustmentCount = (int)($adjustmentCountMap[$date] ?? 0);
        $netInventoryFlow = $inboundUnits + $netAdjustment - $outboundUnits;

        $series[] = [
            'date' => $date,
            'orders' => $orders,
            'revenue' => round($revenue, 2),
            'outbound_units' => $outboundUnits,
            'inbound_units' => $inboundUnits,
            'net_adjustment' => $netAdjustment,
            'adjustment_count' => $adjustmentCount,
            'net_inventory_flow' => $netInventoryFlow
        ];
    }

    $ordersSeries = array_map(static function (array $row): float {
        return (float)($row['orders'] ?? 0);
    }, $series);
    $revenueSeries = array_map(static function (array $row): float {
        return (float)($row['revenue'] ?? 0);
    }, $series);
    $outboundSeries = array_map(static function (array $row): float {
        return (float)($row['outbound_units'] ?? 0);
    }, $series);
    $inboundSeries = array_map(static function (array $row): float {
        return (float)($row['inbound_units'] ?? 0);
    }, $series);
    $netFlowSeries = array_map(static function (array $row): float {
        return (float)($row['net_inventory_flow'] ?? 0);
    }, $series);

    $ordersAvg7d = calculateAverage(array_slice($ordersSeries, -7));
    $ordersPrev7d = calculateAverage(array_slice($ordersSeries, -14, 7));
    $revenueAvg7d = calculateAverage(array_slice($revenueSeries, -7));
    $revenuePrev7d = calculateAverage(array_slice($revenueSeries, -14, 7));

    $ordersChange7dPct = calculatePercentageDelta($ordersAvg7d, $ordersPrev7d);
    $revenueChange7dPct = calculatePercentageDelta($revenueAvg7d, $revenuePrev7d);
    $dominantDelta = abs($revenueChange7dPct) >= abs($ordersChange7dPct) ? $revenueChange7dPct : $ordersChange7dPct;
    $trendDirection = $dominantDelta > 8 ? 'upward' : ($dominantDelta < -8 ? 'downward' : 'stable');

    return [
        'window_days' => $days,
        'generated_at' => gmdate('c'),
        'series' => $series,
        'totals' => [
            'orders' => (int)round(array_sum($ordersSeries)),
            'revenue' => round(array_sum($revenueSeries), 2),
            'outbound_units' => (int)round(array_sum($outboundSeries)),
            'inbound_units' => (int)round(array_sum($inboundSeries)),
            'net_inventory_flow' => (int)round(array_sum($netFlowSeries))
        ],
        'averages' => [
            'orders_per_day_7d' => round($ordersAvg7d, 2),
            'orders_per_day_30d' => round(calculateAverage(array_slice($ordersSeries, -30)), 2),
            'revenue_per_day_7d' => round($revenueAvg7d, 2),
            'revenue_per_day_30d' => round(calculateAverage(array_slice($revenueSeries, -30)), 2),
            'outbound_units_per_day_30d' => round(calculateAverage(array_slice($outboundSeries, -30)), 2),
            'inbound_units_per_day_30d' => round(calculateAverage(array_slice($inboundSeries, -30)), 2)
        ],
        'trend' => [
            'direction' => $trendDirection,
            'orders_change_7d_pct' => round($ordersChange7dPct, 2),
            'revenue_change_7d_pct' => round($revenueChange7dPct, 2)
        ]
    ];
}

function buildTrendForecastPayload(array $history, int $horizonDays = 14): array {
    $horizonDays = max(7, min(90, $horizonDays));
    $series = isset($history['series']) && is_array($history['series']) ? $history['series'] : [];

    $ordersHistory = array_map(static function (array $row): float {
        return max(0.0, (float)($row['orders'] ?? 0));
    }, $series);
    $revenueHistory = array_map(static function (array $row): float {
        return max(0.0, (float)($row['revenue'] ?? 0));
    }, $series);

    $ordersModel = fitHoltLinearModel($ordersHistory);
    $revenueModel = fitHoltLinearModel($revenueHistory);

    $ordersForecast = generateHoltForecast($ordersModel, $horizonDays);
    $revenueForecast = generateHoltForecast($revenueModel, $horizonDays);

    $startDate = new DateTimeImmutable('tomorrow');
    $forecastSeries = [];
    for ($i = 0; $i < $horizonDays; $i++) {
        $date = $startDate->modify('+' . $i . ' day')->format('Y-m-d');
        $ordersPoint = $ordersForecast[$i] ?? ['value' => 0.0, 'lower' => 0.0, 'upper' => 0.0];
        $revenuePoint = $revenueForecast[$i] ?? ['value' => 0.0, 'lower' => 0.0, 'upper' => 0.0];

        $forecastSeries[] = [
            'date' => $date,
            'orders' => round($ordersPoint['value'], 2),
            'orders_lower' => round($ordersPoint['lower'], 2),
            'orders_upper' => round($ordersPoint['upper'], 2),
            'revenue' => round($revenuePoint['value'], 2),
            'revenue_lower' => round($revenuePoint['lower'], 2),
            'revenue_upper' => round($revenuePoint['upper'], 2)
        ];
    }

    $ordersNext7d = 0.0;
    $revenueNext7d = 0.0;
    $ordersNextHorizon = 0.0;
    $revenueNextHorizon = 0.0;
    foreach ($forecastSeries as $index => $item) {
        $ordersNextHorizon += (float)$item['orders'];
        $revenueNextHorizon += (float)$item['revenue'];
        if ($index < 7) {
            $ordersNext7d += (float)$item['orders'];
            $revenueNext7d += (float)$item['revenue'];
        }
    }

    $actualOrders7dAvg = calculateAverage(array_slice($ordersHistory, -7));
    $actualRevenue7dAvg = calculateAverage(array_slice($revenueHistory, -7));
    $forecastOrders7dAvg = $ordersNext7d / min(7, max(1, $horizonDays));
    $forecastRevenue7dAvg = $revenueNext7d / min(7, max(1, $horizonDays));

    $orderChangePct = calculatePercentageDelta($forecastOrders7dAvg, $actualOrders7dAvg);
    $revenueChangePct = calculatePercentageDelta($forecastRevenue7dAvg, $actualRevenue7dAvg);
    $dominantDelta = abs($revenueChangePct) >= abs($orderChangePct) ? $revenueChangePct : $orderChangePct;
    $trend = $dominantDelta > 8 ? 'upward' : ($dominantDelta < -8 ? 'downward' : 'stable');

    $ordersCv = $ordersModel['residual_std'] / max(1.0, calculateAverage(array_slice($ordersHistory, -30)));
    $revenueCv = $revenueModel['residual_std'] / max(1.0, calculateAverage(array_slice($revenueHistory, -30)));
    $noiseLevel = max($ordersCv, $revenueCv);
    $confidence = $noiseLevel <= 0.20 ? 'high' : ($noiseLevel <= 0.40 ? 'medium' : 'low');

    $summary = 'Forecast indicates a ' . $trend . ' trend over the next ' . $horizonDays .
        ' days. Expected ~' . number_format($ordersNext7d, 0) .
        ' orders and PHP ' . number_format($revenueNext7d, 2) . ' revenue in the next 7 days.';

    return [
        'horizon_days' => $horizonDays,
        'generated_at' => gmdate('c'),
        'trend' => $trend,
        'confidence' => $confidence,
        'summary' => $summary,
        'projection' => [
            'orders_next_7d' => round($ordersNext7d, 2),
            'revenue_next_7d' => round($revenueNext7d, 2),
            'orders_next_horizon' => round($ordersNextHorizon, 2),
            'revenue_next_horizon' => round($revenueNextHorizon, 2),
            'orders_change_vs_recent_7d_pct' => round($orderChangePct, 2),
            'revenue_change_vs_recent_7d_pct' => round($revenueChangePct, 2)
        ],
        'model' => [
            'method' => 'holt_linear_exponential_smoothing',
            'orders' => [
                'alpha' => round((float)$ordersModel['alpha'], 3),
                'beta' => round((float)$ordersModel['beta'], 3),
                'residual_std' => round((float)$ordersModel['residual_std'], 3)
            ],
            'revenue' => [
                'alpha' => round((float)$revenueModel['alpha'], 3),
                'beta' => round((float)$revenueModel['beta'], 3),
                'residual_std' => round((float)$revenueModel['residual_std'], 3)
            ],
            'best_practices' => [
                'Use rolling retraining daily with at least 90 days of history.',
                'Monitor forecast drift using rolling MAPE and residual variance.',
                'Escalate to model fine-tuning only after collecting labeled feedback.'
            ]
        ],
        'series' => $forecastSeries
    ];
}

function fitHoltLinearModel(array $values): array {
    $clean = [];
    foreach ($values as $value) {
        $clean[] = max(0.0, (float)$value);
    }

    $count = count($clean);
    if ($count === 0) {
        return [
            'alpha' => 0.4,
            'beta' => 0.2,
            'level' => 0.0,
            'trend' => 0.0,
            'residual_std' => 0.0
        ];
    }

    if ($count === 1) {
        return [
            'alpha' => 0.4,
            'beta' => 0.2,
            'level' => $clean[0],
            'trend' => 0.0,
            'residual_std' => 0.0
        ];
    }

    $alphas = [0.2, 0.35, 0.5, 0.65, 0.8];
    $betas = [0.05, 0.15, 0.25, 0.35, 0.5];

    $best = [
        'alpha' => 0.4,
        'beta' => 0.2,
        'level' => $clean[$count - 1],
        'trend' => 0.0,
        'residual_std' => 0.0,
        'mse' => INF
    ];

    foreach ($alphas as $alpha) {
        foreach ($betas as $beta) {
            $level = $clean[0];
            $trend = $clean[1] - $clean[0];
            $errors = [];

            for ($i = 1; $i < $count; $i++) {
                $forecast = $level + $trend;
                $actual = $clean[$i];
                $errors[] = $actual - $forecast;

                $prevLevel = $level;
                $level = ($alpha * $actual) + ((1 - $alpha) * ($level + $trend));
                $trend = ($beta * ($level - $prevLevel)) + ((1 - $beta) * $trend);
            }

            $mse = calculateAverage(array_map(static function (float $error): float {
                return $error * $error;
            }, $errors));
            if ($mse < (float)$best['mse']) {
                $best = [
                    'alpha' => $alpha,
                    'beta' => $beta,
                    'level' => $level,
                    'trend' => $trend,
                    'residual_std' => sqrt(max(0.0, $mse)),
                    'mse' => $mse
                ];
            }
        }
    }

    return $best;
}

function generateHoltForecast(array $model, int $horizonDays): array {
    $horizonDays = max(1, min(180, $horizonDays));
    $level = (float)($model['level'] ?? 0.0);
    $trend = (float)($model['trend'] ?? 0.0);
    $residualStd = max(0.0, (float)($model['residual_std'] ?? 0.0));
    $z = 1.28; // Approx 80% confidence interval

    $forecast = [];
    for ($m = 1; $m <= $horizonDays; $m++) {
        $value = max(0.0, $level + ($trend * $m));
        $errorBand = $z * $residualStd * sqrt($m);
        $lower = max(0.0, $value - $errorBand);
        $upper = max($lower, $value + $errorBand);

        $forecast[] = [
            'value' => $value,
            'lower' => $lower,
            'upper' => $upper
        ];
    }

    return $forecast;
}

function detectTimeSeriesAnomalyItems(array $history, int $maxItems = 8): array {
    $maxItems = max(1, min(30, $maxItems));
    $series = isset($history['series']) && is_array($history['series']) ? $history['series'] : [];
    if (count($series) < 21) {
        return [];
    }

    $definitions = [
        [
            'field' => 'orders',
            'type' => 'orders_timeseries_outlier',
            'label' => 'order volume',
            'format' => 'count'
        ],
        [
            'field' => 'revenue',
            'type' => 'revenue_timeseries_outlier',
            'label' => 'revenue',
            'format' => 'currency'
        ],
        [
            'field' => 'net_inventory_flow',
            'type' => 'inventory_flow_outlier',
            'label' => 'inventory net flow',
            'format' => 'count'
        ]
    ];

    $items = [];
    foreach ($definitions as $definition) {
        $metricItems = detectMetricAnomalyItems($series, $definition['field'], $definition['type'], $definition['label'], $definition['format']);
        foreach ($metricItems as $item) {
            $items[] = $item;
        }
    }

    usort($items, static function (array $a, array $b): int {
        $severityScore = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $aSeverity = $severityScore[$a['severity'] ?? 'low'] ?? 0;
        $bSeverity = $severityScore[$b['severity'] ?? 'low'] ?? 0;
        if ($aSeverity === $bSeverity) {
            return ((float)($b['anomaly_score'] ?? 0.0)) <=> ((float)($a['anomaly_score'] ?? 0.0));
        }
        return $bSeverity <=> $aSeverity;
    });

    $items = array_slice($items, 0, $maxItems);
    foreach ($items as &$item) {
        unset($item['anomaly_score']);
    }
    unset($item);

    return $items;
}

function detectMetricAnomalyItems(array $series, string $field, string $type, string $label, string $format): array {
    $values = [];
    $dates = [];

    foreach ($series as $row) {
        $value = (float)($row[$field] ?? 0.0);
        if ($field !== 'net_inventory_flow') {
            $value = max(0.0, $value);
        }
        $values[] = $value;
        $dates[] = (string)($row['date'] ?? '');
    }

    $count = count($values);
    if ($count < 21) {
        return [];
    }

    $baselineValues = array_slice($values, 0, max(1, $count - 7));
    $median = calculateMedian($baselineValues);
    $absDeviations = array_map(static function (float $value) use ($median): float {
        return abs($value - $median);
    }, $baselineValues);
    $mad = calculateMedian($absDeviations);
    $std = calculateStandardDeviation($baselineValues);
    $scale = $mad > 0.0001 ? $mad : max(0.0001, $std);
    $useMad = $mad > 0.0001;

    $items = [];
    $startIndex = max(0, $count - 21);
    for ($i = $startIndex; $i < $count; $i++) {
        $value = $values[$i];
        $zScore = $useMad
            ? (0.6745 * ($value - $median) / $scale)
            : (($value - $median) / $scale);
        $absZ = abs($zScore);
        if ($absZ < 3.0) {
            continue;
        }

        $severity = $absZ >= 4.5 ? 'critical' : 'high';
        $direction = $zScore > 0 ? 'spike' : 'drop';

        $formattedValue = $format === 'currency'
            ? ('PHP ' . number_format($value, 2))
            : number_format($value, 0);

        $detail = ucfirst($label) . ' ' . $direction . ' on ' . ($dates[$i] ?: 'unknown date') .
            ': ' . $formattedValue . ' (z=' . number_format($zScore, 2) . ').';

        $items[] = [
            'type' => $type,
            'severity' => $severity,
            'title' => ucfirst($label) . ' anomaly',
            'detail' => $detail,
            'recommendation' => 'Review related transactions and confirm whether this is expected seasonality or an operational issue.',
            'anomaly_score' => round($absZ, 3),
            'data' => [
                'date' => $dates[$i],
                'value' => round($value, 2),
                'median_baseline' => round($median, 2),
                'z_score' => round($zScore, 3)
            ]
        ];
    }

    return $items;
}

function calculateMedian(array $values): float {
    $clean = [];
    foreach ($values as $value) {
        if (is_numeric($value)) {
            $clean[] = (float)$value;
        }
    }

    $count = count($clean);
    if ($count === 0) {
        return 0.0;
    }

    sort($clean, SORT_NUMERIC);
    $mid = intdiv($count, 2);
    if (($count % 2) === 1) {
        return $clean[$mid];
    }
    return ($clean[$mid - 1] + $clean[$mid]) / 2.0;
}

function calculateStandardDeviation(array $values): float {
    $clean = [];
    foreach ($values as $value) {
        if (is_numeric($value)) {
            $clean[] = (float)$value;
        }
    }

    $count = count($clean);
    if ($count <= 1) {
        return 0.0;
    }

    $mean = array_sum($clean) / $count;
    $variance = 0.0;
    foreach ($clean as $value) {
        $variance += ($value - $mean) * ($value - $mean);
    }
    return sqrt($variance / ($count - 1));
}

function calculateAverage(array $values): float {
    $clean = [];
    foreach ($values as $value) {
        if (is_numeric($value)) {
            $clean[] = (float)$value;
        }
    }
    if (empty($clean)) {
        return 0.0;
    }
    return array_sum($clean) / count($clean);
}

function buildDailySummaryPayload(Database $db): array {
    $today = gmdate('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime('-1 day'));

    $ordersToday = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM customer_orders
        WHERE DATE(order_date) = :today
          AND status <> 'cancelled'
    ", [':today' => $today], 0);

    $ordersYesterday = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM customer_orders
        WHERE DATE(order_date) = :yesterday
          AND status <> 'cancelled'
    ", [':yesterday' => $yesterday], 0);

    $revenueToday = (float)safeFetchValue($db, "
        SELECT COALESCE(SUM(total_amount), 0)
        FROM customer_orders
        WHERE DATE(order_date) = :today
          AND status <> 'cancelled'
    ", [':today' => $today], 0.0);

    $revenueYesterday = (float)safeFetchValue($db, "
        SELECT COALESCE(SUM(total_amount), 0)
        FROM customer_orders
        WHERE DATE(order_date) = :yesterday
          AND status <> 'cancelled'
    ", [':yesterday' => $yesterday], 0.0);

    $failedPaymentsToday = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM customer_orders
        WHERE DATE(order_date) = :today
          AND payment_status = 'failed'
    ", [':today' => $today], 0);

    $lowStockItems = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE p.is_active = 1
          AND COALESCE(i.quantity_available, 0) <= p.reorder_level
    ", [], 0);

    $outOfStockItems = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE p.is_active = 1
          AND COALESCE(i.quantity_available, 0) <= 0
    ", [], 0);

    $pendingPoStatuses = ['draft', 'pending_supplier', 'approved', 'ordered', 'partially_received'];
    $pendingPoPlaceholders = [];
    $pendingPoParams = [];
    foreach ($pendingPoStatuses as $index => $status) {
        $key = ':status_' . $index;
        $pendingPoPlaceholders[] = $key;
        $pendingPoParams[$key] = $status;
    }

    $pendingPoCount = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM purchase_orders
        WHERE status IN (" . implode(', ', $pendingPoPlaceholders) . ")
    ", $pendingPoParams, 0);

    $overduePoCount = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM purchase_orders
        WHERE expected_delivery_date IS NOT NULL
          AND expected_delivery_date < CURDATE()
          AND status NOT IN ('received', 'cancelled', 'rejected')
    ", [], 0);

    $adjustmentsToday = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM stock_adjustments
        WHERE DATE(adjustment_date) = :today
    ", [':today' => $today], 0);

    $criticalAdjustmentsToday = (int)safeFetchValue($db, "
        SELECT COUNT(*)
        FROM stock_adjustments
        WHERE DATE(adjustment_date) = :today
          AND ABS(quantity_adjusted) >= 20
    ", [':today' => $today], 0);

    $orderGrowth = calculatePercentageDelta($ordersToday, $ordersYesterday);
    $revenueGrowth = calculatePercentageDelta($revenueToday, $revenueYesterday);

    $ordersLast7d = (int)safeFetchValue($db, "
        SELECT COALESCE(SUM(day_orders), 0)
        FROM (
            SELECT DATE(order_date) AS day, COUNT(*) AS day_orders
            FROM customer_orders
            WHERE DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND status <> 'cancelled'
            GROUP BY DATE(order_date)
            UNION ALL
            SELECT DATE(sale_date) AS day, COUNT(*) AS day_orders
            FROM sales
            WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND payment_status <> 'failed'
            GROUP BY DATE(sale_date)
        ) x
    ", [], 0);

    $revenueLast7d = (float)safeFetchValue($db, "
        SELECT COALESCE(SUM(day_revenue), 0)
        FROM (
            SELECT DATE(order_date) AS day, SUM(total_amount) AS day_revenue
            FROM customer_orders
            WHERE DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND status <> 'cancelled'
            GROUP BY DATE(order_date)
            UNION ALL
            SELECT DATE(sale_date) AS day, SUM(total_amount) AS day_revenue
            FROM sales
            WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND payment_status <> 'failed'
            GROUP BY DATE(sale_date)
        ) x
    ", [], 0.0);

    $ordersLast30d = (int)safeFetchValue($db, "
        SELECT COALESCE(SUM(day_orders), 0)
        FROM (
            SELECT DATE(order_date) AS day, COUNT(*) AS day_orders
            FROM customer_orders
            WHERE DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND status <> 'cancelled'
            GROUP BY DATE(order_date)
            UNION ALL
            SELECT DATE(sale_date) AS day, COUNT(*) AS day_orders
            FROM sales
            WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND payment_status <> 'failed'
            GROUP BY DATE(sale_date)
        ) x
    ", [], 0);

    $revenueLast30d = (float)safeFetchValue($db, "
        SELECT COALESCE(SUM(day_revenue), 0)
        FROM (
            SELECT DATE(order_date) AS day, SUM(total_amount) AS day_revenue
            FROM customer_orders
            WHERE DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND status <> 'cancelled'
            GROUP BY DATE(order_date)
            UNION ALL
            SELECT DATE(sale_date) AS day, SUM(total_amount) AS day_revenue
            FROM sales
            WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND payment_status <> 'failed'
            GROUP BY DATE(sale_date)
        ) x
    ", [], 0.0);

    $reorderPreview = array_slice(buildReorderSuggestions($db, 5), 0, 3);
    $anomalySnapshot = detectOperationalAnomalies($db, 10);
    $criticalAnomalies = (int)($anomalySnapshot['counts']['critical'] ?? 0);
    $highAnomalies = (int)($anomalySnapshot['counts']['high'] ?? 0);

    $highlights = [];
    $highlights[] = 'Orders: ' . $ordersToday . ' today (' . formatSignedPercentage($orderGrowth) . ' vs yesterday).';
    $highlights[] = 'Revenue: PHP ' . number_format($revenueToday, 2) . ' (' . formatSignedPercentage($revenueGrowth) . ' vs yesterday).';
    $highlights[] = 'Rolling 7d: ' . $ordersLast7d . ' orders, PHP ' . number_format($revenueLast7d, 2) . ' revenue.';
    $highlights[] = 'Inventory risk: ' . $lowStockItems . ' low-stock items, ' . $outOfStockItems . ' out-of-stock items.';
    $highlights[] = 'Purchasing queue: ' . $pendingPoCount . ' open purchase orders, ' . $overduePoCount . ' overdue.';

    if ($failedPaymentsToday > 0) {
        $highlights[] = 'Failed payments today: ' . $failedPaymentsToday . '.';
    }

    if ($criticalAnomalies > 0 || $highAnomalies > 0) {
        $highlights[] = 'Anomaly scan: ' . $criticalAnomalies . ' critical and ' . $highAnomalies . ' high-risk flags.';
    }

    $summaryText = "Daily operations summary for {$today}:\n- " . implode("\n- ", $highlights);

    return [
        'date' => $today,
        'generated_at' => gmdate('c'),
        'summary_text' => $summaryText,
        'highlights' => $highlights,
        'metrics' => [
            'orders_today' => $ordersToday,
            'orders_yesterday' => $ordersYesterday,
            'orders_delta_pct' => $orderGrowth,
            'orders_last_7d' => $ordersLast7d,
            'orders_last_30d' => $ordersLast30d,
            'revenue_today' => round($revenueToday, 2),
            'revenue_yesterday' => round($revenueYesterday, 2),
            'revenue_delta_pct' => $revenueGrowth,
            'revenue_last_7d' => round($revenueLast7d, 2),
            'revenue_last_30d' => round($revenueLast30d, 2),
            'failed_payments_today' => $failedPaymentsToday,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'pending_purchase_orders' => $pendingPoCount,
            'overdue_purchase_orders' => $overduePoCount,
            'stock_adjustments_today' => $adjustmentsToday,
            'critical_adjustments_today' => $criticalAdjustmentsToday
        ],
        'reorder_preview' => $reorderPreview,
        'anomaly_counts' => $anomalySnapshot['counts']
    ];
}

function buildReorderSuggestions(Database $db, int $limit = 20): array {
    $rows = safeFetchAll($db, "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.reorder_level,
            COALESCE(i.quantity_available, 0) AS quantity_available,
            COALESCE(demand.total_30d, 0) AS demand_30d,
            COALESCE(demand.total_7d, 0) AS demand_7d,
            COALESCE(demand.avg_daily_demand, 0) AS avg_daily_demand,
            COALESCE(demand.avg_daily_demand_7d, 0) AS avg_daily_demand_7d,
            COALESCE(lead.avg_lead_days, 7) AS avg_lead_days
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        LEFT JOIN (
            SELECT
                product_id,
                SUM(CASE WHEN activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qty ELSE 0 END) AS total_30d,
                SUM(CASE WHEN activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN qty ELSE 0 END) AS total_7d,
                SUM(CASE WHEN activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qty ELSE 0 END) / 30 AS avg_daily_demand,
                SUM(CASE WHEN activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN qty ELSE 0 END) / 7 AS avg_daily_demand_7d
            FROM (
                SELECT
                    coi.product_id AS product_id,
                    DATE(co.order_date) AS activity_date,
                    SUM(coi.quantity) AS qty
                FROM customer_order_items coi
                INNER JOIN customer_orders co ON co.id = coi.order_id
                WHERE DATE(co.order_date) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                  AND co.status <> 'cancelled'
                GROUP BY coi.product_id, DATE(co.order_date)

                UNION ALL

                SELECT
                    si.product_id AS product_id,
                    DATE(s.sale_date) AS activity_date,
                    SUM(si.quantity) AS qty
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                WHERE DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                  AND s.payment_status <> 'failed'
                GROUP BY si.product_id, DATE(s.sale_date)
            ) q
            GROUP BY product_id
        ) demand ON demand.product_id = p.id
        LEFT JOIN (
            SELECT
                poi.product_id,
                AVG(GREATEST(DATEDIFF(grn.received_date, po.order_date), 1)) AS avg_lead_days
            FROM purchase_order_items poi
            INNER JOIN purchase_orders po ON po.id = poi.po_id
            INNER JOIN goods_received_notes grn ON grn.po_id = po.id
            WHERE po.order_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
            GROUP BY poi.product_id
        ) lead ON lead.product_id = p.id
        WHERE p.is_active = 1
        ORDER BY quantity_available ASC, avg_daily_demand DESC, p.reorder_level DESC
        LIMIT 300
    ");

    $suggestions = [];
    $today = new DateTimeImmutable('today');
    foreach ($rows as $row) {
        $quantityAvailable = (int)($row['quantity_available'] ?? 0);
        $reorderLevel = max(1, (int)($row['reorder_level'] ?? 1));
        $avgDailyDemand30d = max(0.0, (float)($row['avg_daily_demand'] ?? 0.0));
        $avgDailyDemand7d = max(0.0, (float)($row['avg_daily_demand_7d'] ?? 0.0));
        $leadDays = max(1, (int)round((float)($row['avg_lead_days'] ?? 7)));
        $reviewPeriodDays = 14;
        $serviceLevel = 0.95;
        $zValue = 1.65; // Approx z-score for 95% service level

        $trendFactor = 1.0;
        if ($avgDailyDemand30d > 0.0001) {
            $trendFactor = $avgDailyDemand7d / $avgDailyDemand30d;
            $trendFactor = max(0.6, min(1.8, $trendFactor));
        } elseif ($avgDailyDemand7d > 0) {
            $trendFactor = 1.2;
        }

        $baseDemand = $avgDailyDemand30d > 0 ? $avgDailyDemand30d : $avgDailyDemand7d;
        if ($baseDemand > 0) {
            $baseDemand = ($baseDemand * 0.7) + ($avgDailyDemand7d * 0.3);
        }

        $forecastDailyDemand = $baseDemand * $trendFactor;
        $forecastDailyDemand = max(0.0, $forecastDailyDemand);

        $daysOfCover = null;
        if ($forecastDailyDemand > 0) {
            $daysOfCover = $quantityAvailable / $forecastDailyDemand;
        }

        $demandStdDevEstimate = sqrt(max(1.0, $forecastDailyDemand * max(1, $leadDays)));
        $safetyStock = (int)ceil($zValue * $demandStdDevEstimate);
        $pipelineDemandStock = (int)ceil($forecastDailyDemand * ($leadDays + $reviewPeriodDays));
        $minimumBufferStock = max($reorderLevel * 2, $reorderLevel + $safetyStock);
        $targetStock = max($pipelineDemandStock + $safetyStock, $minimumBufferStock);

        $suggestedOrderQty = max(0, $targetStock - $quantityAvailable);

        if ($forecastDailyDemand <= 0 && $quantityAvailable <= $reorderLevel) {
            $suggestedOrderQty = max($suggestedOrderQty, $reorderLevel);
        }

        $reorderPoint = (int)ceil(($forecastDailyDemand * $leadDays) + $safetyStock);
        $daysUntilReorder = null;
        $daysUntilStockout = null;
        $optimalReorderDate = null;
        $projectedStockoutDate = null;

        if ($forecastDailyDemand > 0) {
            $daysUntilReorder = (int)floor(($quantityAvailable - $reorderPoint) / $forecastDailyDemand);
            $daysUntilStockout = (int)floor($quantityAvailable / $forecastDailyDemand);

            $clampedReorderDays = max(0, $daysUntilReorder);
            $clampedStockoutDays = max(0, $daysUntilStockout);
            $optimalReorderDate = $today->modify('+' . $clampedReorderDays . ' day')->format('Y-m-d');
            $projectedStockoutDate = $today->modify('+' . $clampedStockoutDays . ' day')->format('Y-m-d');
        } elseif ($quantityAvailable <= $reorderLevel) {
            $daysUntilReorder = 0;
            $optimalReorderDate = $today->format('Y-m-d');
        }

        $priority = 'low';
        $priorityScore = 1;
        if ($quantityAvailable <= 0 || ($daysUntilStockout !== null && $daysUntilStockout <= $leadDays)) {
            $priority = 'critical';
            $priorityScore = 4;
        } elseif ($daysUntilReorder !== null && $daysUntilReorder <= 0) {
            $priority = 'critical';
            $priorityScore = 4;
        } elseif ($quantityAvailable <= $reorderLevel || ($daysUntilStockout !== null && $daysUntilStockout < 7)) {
            $priority = 'high';
            $priorityScore = 3;
        } elseif ($daysUntilStockout !== null && $daysUntilStockout < 14) {
            $priority = 'medium';
            $priorityScore = 2;
        }

        if ($suggestedOrderQty <= 0 && $quantityAvailable > $reorderLevel) {
            continue;
        }

        $suggestions[] = [
            'product_id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'sku' => (string)($row['sku'] ?? ''),
            'quantity_available' => $quantityAvailable,
            'reorder_level' => $reorderLevel,
            'demand_30d' => (int)round((float)($row['demand_30d'] ?? 0)),
            'demand_7d' => (int)round((float)($row['demand_7d'] ?? 0)),
            'avg_daily_demand' => round($forecastDailyDemand, 3),
            'avg_daily_demand_30d' => round($avgDailyDemand30d, 3),
            'avg_daily_demand_7d' => round($avgDailyDemand7d, 3),
            'trend_factor' => round($trendFactor, 3),
            'avg_lead_days' => $leadDays,
            'days_of_cover' => $daysOfCover !== null ? round($daysOfCover, 2) : null,
            'reorder_point' => $reorderPoint,
            'safety_stock' => $safetyStock,
            'days_until_reorder' => $daysUntilReorder,
            'days_until_stockout' => $daysUntilStockout,
            'optimal_reorder_date' => $optimalReorderDate,
            'projected_stockout_date' => $projectedStockoutDate,
            'service_level' => $serviceLevel,
            'suggested_order_qty' => $suggestedOrderQty,
            'priority' => $priority,
            'priority_score' => $priorityScore,
            'rationale' => buildReorderRationale(
                $quantityAvailable,
                $reorderLevel,
                $daysOfCover,
                $leadDays,
                $daysUntilReorder,
                $daysUntilStockout,
                $trendFactor
            )
        ];
    }

    usort($suggestions, static function (array $a, array $b): int {
        if ($a['priority_score'] === $b['priority_score']) {
            return $b['suggested_order_qty'] <=> $a['suggested_order_qty'];
        }
        return $b['priority_score'] <=> $a['priority_score'];
    });

    $limit = max(1, min(100, $limit));
    $result = array_slice($suggestions, 0, $limit);

    return array_map(static function (array $item): array {
        unset($item['priority_score']);
        return $item;
    }, $result);
}

function buildReorderRationale(
    int $quantityAvailable,
    int $reorderLevel,
    ?float $daysOfCover,
    int $leadDays,
    ?int $daysUntilReorder,
    ?int $daysUntilStockout,
    float $trendFactor
): string {
    if ($quantityAvailable <= 0) {
        return 'Out of stock now; immediate replenishment recommended.';
    }

    if ($daysUntilReorder !== null && $daysUntilReorder <= 0) {
        return 'Reorder threshold has already been reached based on lead time and safety stock.';
    }

    if ($daysUntilStockout !== null && $daysUntilStockout <= $leadDays) {
        return 'Projected stockout happens before lead-time replenishment can arrive.';
    }

    if ($daysOfCover !== null && $daysOfCover < $leadDays) {
        return 'Projected stock cover (' . number_format($daysOfCover, 1) . ' days) is below lead time (' . $leadDays . ' days).';
    }

    if ($quantityAvailable <= $reorderLevel) {
        return 'Current stock is at or below reorder level.';
    }

    if ($trendFactor >= 1.25) {
        return 'Demand is accelerating versus baseline; replenish earlier to avoid stockout risk.';
    }

    if ($daysOfCover !== null && $daysOfCover < 14) {
        return 'Stock cover is less than 14 days based on recent demand.';
    }

    return 'Buffer restock to maintain target service level and safety stock.';
}

function detectOperationalAnomalies(Database $db, int $limit = 25): array {
    $limit = max(1, min(100, $limit));
    $items = [];

    $priceVarianceRows = safeFetchAll($db, "
        SELECT
            poi.id AS po_item_id,
            po.po_number,
            p.id AS product_id,
            p.name AS product_name,
            p.sku,
            p.cost_price,
            poi.unit_cost,
            po.created_at
        FROM purchase_order_items poi
        INNER JOIN purchase_orders po ON po.id = poi.po_id
        INNER JOIN products p ON p.id = poi.product_id
        WHERE p.cost_price > 0
          AND poi.unit_cost > p.cost_price * 1.25
        ORDER BY (poi.unit_cost / p.cost_price) DESC
        LIMIT 10
    ");

    foreach ($priceVarianceRows as $row) {
        $baseline = max(0.01, (float)($row['cost_price'] ?? 0));
        $unitCost = (float)($row['unit_cost'] ?? 0);
        $increasePct = (($unitCost - $baseline) / $baseline) * 100;
        $severity = $increasePct >= 60 ? 'critical' : 'high';
        $items[] = [
            'type' => 'purchase_price_spike',
            'severity' => $severity,
            'title' => 'Purchase cost spike for ' . (string)($row['product_name'] ?? 'product'),
            'detail' => 'PO ' . (string)($row['po_number'] ?? '-') . ' unit cost is ' .
                number_format($increasePct, 1) . '% above baseline cost.',
            'recommendation' => 'Verify supplier quote and approval history before receiving future orders.',
            'data' => [
                'po_item_id' => (int)($row['po_item_id'] ?? 0),
                'po_number' => (string)($row['po_number'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'product_name' => (string)($row['product_name'] ?? ''),
                'sku' => (string)($row['sku'] ?? ''),
                'baseline_cost' => round($baseline, 2),
                'unit_cost' => round($unitCost, 2),
                'increase_pct' => round($increasePct, 2)
            ]
        ];
    }

    $grnMismatchRows = safeFetchAll($db, "
        SELECT
            grn.grn_number,
            po.po_number,
            p.id AS product_id,
            p.name AS product_name,
            p.sku,
            gi.quantity_received,
            gi.quantity_accepted
        FROM grn_items gi
        INNER JOIN goods_received_notes grn ON grn.id = gi.grn_id
        INNER JOIN purchase_orders po ON po.id = grn.po_id
        INNER JOIN products p ON p.id = gi.product_id
        WHERE gi.quantity_received > 0
          AND (gi.quantity_accepted / gi.quantity_received) < 0.8
        ORDER BY (gi.quantity_received - gi.quantity_accepted) DESC
        LIMIT 10
    ");

    foreach ($grnMismatchRows as $row) {
        $received = max(1, (int)($row['quantity_received'] ?? 0));
        $accepted = max(0, (int)($row['quantity_accepted'] ?? 0));
        $rejected = max(0, $received - $accepted);
        $rejectRate = ($rejected / $received) * 100;
        $severity = $rejectRate >= 50 ? 'critical' : 'high';

        $items[] = [
            'type' => 'grn_rejection_rate',
            'severity' => $severity,
            'title' => 'High rejection rate in GRN ' . (string)($row['grn_number'] ?? '-'),
            'detail' => $rejected . ' of ' . $received . ' units rejected (' . number_format($rejectRate, 1) . '%).',
            'recommendation' => 'Review supplier quality controls and inspect this line item for recurring defects.',
            'data' => [
                'grn_number' => (string)($row['grn_number'] ?? ''),
                'po_number' => (string)($row['po_number'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'product_name' => (string)($row['product_name'] ?? ''),
                'sku' => (string)($row['sku'] ?? ''),
                'quantity_received' => $received,
                'quantity_accepted' => $accepted,
                'rejected_qty' => $rejected,
                'rejection_rate_pct' => round($rejectRate, 2)
            ]
        ];
    }

    $adjustmentRows = safeFetchAll($db, "
        SELECT
            sa.id,
            sa.adjustment_number,
            sa.adjustment_type,
            sa.quantity_before,
            sa.quantity_adjusted,
            sa.quantity_after,
            sa.reason,
            sa.adjustment_date,
            p.id AS product_id,
            p.name AS product_name,
            p.sku
        FROM stock_adjustments sa
        INNER JOIN products p ON p.id = sa.product_id
        WHERE DATE(sa.adjustment_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND (
              ABS(sa.quantity_adjusted) >= 20
              OR (sa.quantity_before > 0 AND ABS(sa.quantity_adjusted) >= (sa.quantity_before * 0.5))
          )
        ORDER BY ABS(sa.quantity_adjusted) DESC
        LIMIT 10
    ");

    foreach ($adjustmentRows as $row) {
        $quantityAdjusted = (int)($row['quantity_adjusted'] ?? 0);
        $quantityBefore = max(0, (int)($row['quantity_before'] ?? 0));
        $impactRatio = $quantityBefore > 0 ? abs($quantityAdjusted) / $quantityBefore : 1.0;
        $severity = (abs($quantityAdjusted) >= 50 || $impactRatio >= 1.0) ? 'critical' : 'medium';

        $items[] = [
            'type' => 'large_stock_adjustment',
            'severity' => $severity,
            'title' => 'Large stock adjustment ' . (string)($row['adjustment_number'] ?? '#'),
            'detail' => 'Adjusted ' . $quantityAdjusted . ' units for ' . (string)($row['product_name'] ?? 'product') . '.',
            'recommendation' => 'Validate adjustment reason and reconcile against source documents.',
            'data' => [
                'adjustment_id' => (int)($row['id'] ?? 0),
                'adjustment_number' => (string)($row['adjustment_number'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'product_name' => (string)($row['product_name'] ?? ''),
                'sku' => (string)($row['sku'] ?? ''),
                'adjustment_type' => (string)($row['adjustment_type'] ?? ''),
                'quantity_before' => $quantityBefore,
                'quantity_adjusted' => $quantityAdjusted,
                'quantity_after' => (int)($row['quantity_after'] ?? 0),
                'reason' => (string)($row['reason'] ?? '')
            ]
        ];
    }

    $overdueRows = safeFetchAll($db, "
        SELECT
            po.id,
            po.po_number,
            po.expected_delivery_date,
            DATEDIFF(CURDATE(), po.expected_delivery_date) AS overdue_days,
            COALESCE(u.full_name, u.username, 'Supplier') AS supplier_name
        FROM purchase_orders po
        LEFT JOIN users u ON u.id = po.supplier_id
        WHERE po.expected_delivery_date IS NOT NULL
          AND po.expected_delivery_date < CURDATE()
          AND po.status NOT IN ('received', 'cancelled', 'rejected')
        ORDER BY overdue_days DESC
        LIMIT 10
    ");

    foreach ($overdueRows as $row) {
        $overdueDays = max(1, (int)($row['overdue_days'] ?? 0));
        $severity = $overdueDays >= 14 ? 'high' : 'medium';
        $items[] = [
            'type' => 'overdue_purchase_order',
            'severity' => $severity,
            'title' => 'Overdue purchase order ' . (string)($row['po_number'] ?? ''),
            'detail' => 'Expected delivery was ' . (string)($row['expected_delivery_date'] ?? '-') .
                ' (' . $overdueDays . ' days overdue).',
            'recommendation' => 'Follow up with supplier and update expected delivery ETA.',
            'data' => [
                'po_id' => (int)($row['id'] ?? 0),
                'po_number' => (string)($row['po_number'] ?? ''),
                'supplier_name' => (string)($row['supplier_name'] ?? ''),
                'expected_delivery_date' => (string)($row['expected_delivery_date'] ?? ''),
                'overdue_days' => $overdueDays
            ]
        ];
    }

    // Add time-series anomalies from historical sales/inventory movement.
    $historySnapshot = buildHistoricalAnalyticsPayload($db, 60);
    $timeSeriesAnomalies = detectTimeSeriesAnomalyItems($historySnapshot, 8);
    if (!empty($timeSeriesAnomalies)) {
        $items = array_merge($items, $timeSeriesAnomalies);
    }

    usort($items, static function (array $a, array $b): int {
        $score = [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1
        ];
        $aScore = $score[$a['severity']] ?? 0;
        $bScore = $score[$b['severity']] ?? 0;
        return $bScore <=> $aScore;
    });

    $items = array_slice($items, 0, $limit);

    $counts = [
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0
    ];

    foreach ($items as $item) {
        $severity = (string)($item['severity'] ?? 'low');
        if (!array_key_exists($severity, $counts)) {
            $counts[$severity] = 0;
        }
        $counts[$severity]++;
    }

    return [
        'items' => $items,
        'counts' => $counts,
        'generated_at' => gmdate('c')
    ];
}

function calculatePercentageDelta(float $current, float $previous): float {
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return (($current - $previous) / $previous) * 100;
}

function formatSignedPercentage(float $value): string {
    $prefix = $value > 0 ? '+' : '';
    return $prefix . number_format($value, 1) . '%';
}

function safeFetchValue(Database $db, string $sql, array $params = [], $default = 0) {
    try {
        $value = $db->fetchValue($sql, $params);
        return $value !== null ? $value : $default;
    } catch (Exception $e) {
        error_log('Admin AI safeFetchValue fallback: ' . $e->getMessage());
        return $default;
    }
}

function safeFetchAll(Database $db, string $sql, array $params = []): array {
    try {
        return $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log('Admin AI safeFetchAll fallback: ' . $e->getMessage());
        return [];
    }
}

function buildRulesBasedGeneralReply(string $message, array $summary, array $reorder, array $anomalies, array $forecast, string $language = 'en'): string {
    $normalized = normalizeAdminAiMessage($message);

    if (isAdminLanguageCapabilityQuestion($normalized)) {
        return "Oo, naiintindihan ko ang Filipino/Tagalog at English.\n" .
            "Pwede kang magtanong sa kahit alin sa dalawa tungkol sa sales, inventory, reorder timing, anomalies, at forecast.";
    }

    $critical = (int)($anomalies['counts']['critical'] ?? 0);
    $high = (int)($anomalies['counts']['high'] ?? 0);
    $topReorder = count($reorder);
    $ordersToday = (int)($summary['metrics']['orders_today'] ?? 0);
    $revenueToday = (float)($summary['metrics']['revenue_today'] ?? 0);
    $trend = (string)($forecast['trend'] ?? 'stable');
    $forecastOrders7d = (float)($forecast['projection']['orders_next_7d'] ?? 0.0);
    $forecastRevenue7d = (float)($forecast['projection']['revenue_next_7d'] ?? 0.0);

    $lines = [];
    if ($language === 'fil') {
        $trendText = $trend === 'upward' ? 'tumataas' : ($trend === 'downward' ? 'bumababa' : 'stable');
        $lines[] = 'Snapshot ng operations:';
        $lines[] = '- Orders ngayon: ' . $ordersToday . '.';
        $lines[] = '- Revenue ngayon: PHP ' . number_format($revenueToday, 2) . '.';
        $lines[] = '- Reorder candidates: ' . $topReorder . '.';
        $lines[] = '- Anomalies: ' . $critical . ' critical, ' . $high . ' high.';
        $lines[] = '- Trend forecast (7 araw): ' . $trendText . ', ~' . number_format($forecastOrders7d, 0) . ' orders at PHP ' . number_format($forecastRevenue7d, 2) . '.';
        $lines[] = '';
        $lines[] = 'Subukan: "daily summary", "historical trends", "trend forecast", "reorder suggestions", o "anomaly scan".';
    } else {
        $lines[] = 'Current operations snapshot:';
        $lines[] = '- Orders today: ' . $ordersToday . '.';
        $lines[] = '- Revenue today: PHP ' . number_format($revenueToday, 2) . '.';
        $lines[] = '- Reorder candidates: ' . $topReorder . '.';
        $lines[] = '- Anomalies: ' . $critical . ' critical, ' . $high . ' high.';
        $lines[] = '- Trend forecast (next 7 days): ' . $trend . ', ~' . number_format($forecastOrders7d, 0) . ' orders and PHP ' . number_format($forecastRevenue7d, 2) . '.';
        $lines[] = '';
        $lines[] = 'Ask for "daily summary", "historical trends", "trend forecast", "reorder suggestions", or "anomaly scan" for a focused report.';
    }

    return implode("\n", $lines);
}

function maybeGenerateAdminLlmReply(
    string $message,
    array $context,
    array $summary,
    array $reorder,
    array $anomalies,
    array $history,
    array $forecast,
    string $language = 'en'
): string {
    $llmConfig = getAdminAiLlmConfig();
    if (!$llmConfig['enabled']) {
        return '';
    }

    try {
        $messages = buildAdminLlmMessages($message, $context, $summary, $reorder, $anomalies, $history, $forecast, $llmConfig, $language);
        $reply = callOpenAiCompatibleChat($messages, $llmConfig);
        return sanitizeLlmReply($reply);
    } catch (Exception $e) {
        error_log('Admin AI LLM fallback used: ' . $e->getMessage());
        return '';
    }
}

function buildAdminLlmMessages(
    string $message,
    array $context,
    array $summary,
    array $reorder,
    array $anomalies,
    array $history,
    array $forecast,
    array $llmConfig,
    string $language = 'en'
): array {
    $languageInstruction = $language === 'fil'
        ? 'Reply in Filipino (Taglish is acceptable if it improves clarity for business users).'
        : 'Reply in English unless the user explicitly uses Filipino.';

    $systemPrompt = implode("\n", [
        'You are the Admin AI Copilot for an inventory and e-commerce operations dashboard.',
        'You are read-only: never claim to execute approvals, stock edits, or financial transactions.',
        'Be concise, factual, and action-oriented.',
        'Use practical analytics from trend, history, forecast, and anomaly signals.',
        'When suggesting actions, prioritize risk reduction and operational throughput.',
        'State assumptions and confidence when forecasting.',
        'Use only grounded values from provided JSON context. If missing, say it is unavailable.',
        'If asked about unrelated topics, redirect to operations, purchasing, inventory, orders, and audit monitoring.',
        'Use short bullets and keep responses typically under 190 words.',
        $languageInstruction
    ]);

    if (!empty($llmConfig['system_prompt'])) {
        $systemPrompt .= "\nAdditional instruction:\n" . $llmConfig['system_prompt'];
    }

    $knowledge = [
        'page_context' => [
            'page' => $context['page'] ?? '',
            'title' => $context['title'] ?? '',
            'pathname' => $context['pathname'] ?? '',
            'url' => $context['url'] ?? ''
        ],
        'daily_summary' => [
            'date' => $summary['date'] ?? null,
            'highlights' => $summary['highlights'] ?? [],
            'metrics' => $summary['metrics'] ?? []
        ],
        'history' => [
            'window_days' => $history['window_days'] ?? null,
            'totals' => $history['totals'] ?? [],
            'averages' => $history['averages'] ?? [],
            'trend' => $history['trend'] ?? []
        ],
        'forecast' => [
            'horizon_days' => $forecast['horizon_days'] ?? null,
            'trend' => $forecast['trend'] ?? 'stable',
            'projection' => $forecast['projection'] ?? [],
            'summary' => $forecast['summary'] ?? '',
            'series_preview' => array_slice($forecast['series'] ?? [], 0, 7)
        ],
        'reorder_top' => array_slice($reorder, 0, 6),
        'anomalies' => [
            'counts' => $anomalies['counts'] ?? [],
            'top_items' => array_slice($anomalies['items'] ?? [], 0, 8)
        ]
    ];

    return [
        [
            'role' => 'system',
            'content' => $systemPrompt
        ],
        [
            'role' => 'system',
            'content' => "Grounding context (JSON):\n" .
                json_encode($knowledge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
        ],
        [
            'role' => 'user',
            'content' => $message
        ]
    ];
}

function getAdminAiLlmConfig(): array {
    $apiKey = trim((string)(Env::get(
        'ADMIN_AI_API_KEY',
        Env::get('CHATBOT_LLM_API_KEY', Env::get('OPENAI_API_KEY', ''))
    ) ?? ''));

    $enabledFlag = Env::get('ADMIN_AI_ENABLED', Env::get('CHATBOT_LLM_ENABLED', null));
    $configured = $apiKey !== '';
    $enabled = $configured && parseBooleanEnvFlag($enabledFlag, true);

    $baseUrl = trim((string)(Env::get(
        'ADMIN_AI_BASE_URL',
        Env::get('CHATBOT_LLM_BASE_URL', Env::get('OPENAI_BASE_URL', 'https://api.openai.com/v1'))
    ) ?? 'https://api.openai.com/v1'));
    $baseUrl = rtrim($baseUrl, '/');

    return [
        'provider' => (string)(Env::get('ADMIN_AI_PROVIDER', Env::get('CHATBOT_LLM_PROVIDER', 'openai_compatible')) ?? 'openai_compatible'),
        'api_key' => $apiKey,
        'base_url' => $baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1',
        'model' => (string)(Env::get('ADMIN_AI_MODEL', Env::get('CHATBOT_LLM_MODEL', 'gpt-4o-mini')) ?? 'gpt-4o-mini'),
        'temperature' => (float)(Env::get('ADMIN_AI_TEMPERATURE', 0.2) ?? 0.2),
        'max_tokens' => (int)(Env::get('ADMIN_AI_MAX_TOKENS', 320) ?? 320),
        'timeout_seconds' => (int)(Env::get('ADMIN_AI_TIMEOUT_SECONDS', 20) ?? 20),
        'configured' => $configured,
        'enabled' => $enabled,
        'system_prompt' => trim((string)(Env::get('ADMIN_AI_SYSTEM_PROMPT', '') ?? ''))
    ];
}

function parseBooleanEnvFlag($value, bool $default): bool {
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string)$value));
    if ($normalized === '') {
        return $default;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function callOpenAiCompatibleChat(array $messages, array $config): string {
    if (empty($config['api_key'])) {
        throw new Exception('LLM API key is not configured');
    }

    $endpoint = rtrim((string)$config['base_url'], '/') . '/chat/completions';
    $payload = [
        'model' => (string)$config['model'],
        'messages' => $messages,
        'temperature' => max(0, min(1, (float)$config['temperature'])),
        'max_tokens' => max(64, min(1400, (int)$config['max_tokens']))
    ];

    $response = httpPostJsonWithCurl(
        $endpoint,
        $payload,
        [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json'
        ],
        max(5, (int)$config['timeout_seconds'])
    );

    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded)) {
        throw new Exception('Invalid LLM response payload');
    }

    if ($response['status_code'] < 200 || $response['status_code'] >= 300) {
        $errorMessage = $decoded['error']['message'] ?? ('HTTP ' . $response['status_code']);
        throw new Exception('LLM API error: ' . $errorMessage);
    }

    $content = extractChatCompletionContent($decoded);
    if ($content === '') {
        throw new Exception('Empty LLM response');
    }

    return $content;
}

function httpPostJsonWithCurl(string $url, array $payload, array $headers, int $timeoutSeconds): array {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new Exception('Failed to encode LLM request payload');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL request failed: ' . $error);
        }

        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'body' => (string)$body
        ];
    }

    $contextOptions = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $json,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true
        ]
    ]);

    $body = @file_get_contents($url, false, $contextOptions);
    if ($body === false) {
        throw new Exception('HTTP request failed');
    }

    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#HTTP/\\S+\\s+(\\d{3})#', $headerLine, $matches)) {
                $statusCode = (int)$matches[1];
                break;
            }
        }
    }

    return [
        'status_code' => $statusCode,
        'body' => (string)$body
    ];
}

function extractChatCompletionContent(array $decoded): string {
    $content = $decoded['choices'][0]['message']['content'] ?? '';

    if (is_string($content)) {
        return trim($content);
    }

    if (is_array($content)) {
        $parts = [];
        foreach ($content as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['type'] ?? '') === 'text' && isset($item['text']) && is_string($item['text'])) {
                $parts[] = $item['text'];
                continue;
            }
            if (isset($item['text']) && is_string($item['text'])) {
                $parts[] = $item['text'];
            }
        }
        return trim(implode("\n", $parts));
    }

    return '';
}

function sanitizeLlmReply(string $reply): string {
    $reply = trim($reply);
    if ($reply === '') {
        return '';
    }

    if (strlen($reply) > 1800) {
        $reply = substr($reply, 0, 1800);
        $lastSentence = strrpos($reply, '.');
        if ($lastSentence !== false && $lastSentence > 200) {
            $reply = substr($reply, 0, $lastSentence + 1);
        }
    }

    return trim($reply);
}

function logAiActivity(string $mode, array $meta = []): void {
    $userId = Auth::userId();
    $role = Auth::userRole();

    $description = 'Admin AI copilot request: ' . $mode;
    if (!empty($meta)) {
        $description .= ' (' . json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ')';
    }

    AuditLogger::log(
        'ai_copilot',
        'admin_ai',
        null,
        $description,
        null,
        [
            'mode' => $mode,
            'role' => $role
        ],
        $userId
    );
}
