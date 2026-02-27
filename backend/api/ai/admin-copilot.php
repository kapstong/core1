<?php
/**
 * Admin AI Copilot API
 * GET  /backend/api/ai/admin-copilot.php?mode=status|summary|reorder|anomalies
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
                'version' => '1.0',
                'read_only' => true,
                'capabilities' => [
                    'daily_summary',
                    'reorder_suggestions',
                    'anomaly_detection',
                    'natural_language_qa'
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

        default:
            Response::error('Invalid mode. Use status, summary, reorder, or anomalies.', 400);
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
    $intent = detectAdminIntent($normalized);

    $summary = buildDailySummaryPayload($db);
    $reorder = buildReorderSuggestions($db, 8);
    $anomalies = detectOperationalAnomalies($db, 10);

    $replyText = '';
    $followUp = [];
    $responseSource = 'rules';

    if ($intent === 'summary') {
        $replyText = $summary['summary_text'];
        $followUp = [
            'Show reorder suggestions',
            'Show anomalies',
            'Which metric changed the most?'
        ];
    } elseif ($intent === 'reorder') {
        if (empty($reorder)) {
            $replyText = 'No urgent reorder suggestions were found from current inventory and demand history.';
        } else {
            $top = array_slice($reorder, 0, 3);
            $lines = [];
            foreach ($top as $item) {
                $cover = $item['days_of_cover'] !== null ? number_format((float)$item['days_of_cover'], 1) . ' days' : 'no demand history';
                $lines[] = $item['name'] . ' (' . $item['sku'] . '): suggest ordering ' . $item['suggested_order_qty'] . ' units, cover ' . $cover . '.';
            }
            $replyText = "Top reorder priorities:\n- " . implode("\n- ", $lines);
        }
        $followUp = [
            'Show full reorder list',
            'Show anomalies',
            'What is the reorder logic?'
        ];
    } elseif ($intent === 'anomalies') {
        if (empty($anomalies['items'])) {
            $replyText = 'No major anomalies were detected with the current rules.';
        } else {
            $top = array_slice($anomalies['items'], 0, 4);
            $lines = [];
            foreach ($top as $item) {
                $lines[] = '[' . strtoupper($item['severity']) . '] ' . $item['title'];
            }
            $replyText = "Current anomalies detected:\n- " . implode("\n- ", $lines);
        }
        $followUp = [
            'Show critical anomalies only',
            'Show reorder suggestions',
            'Daily summary'
        ];
    } else {
        $replyText = buildRulesBasedGeneralReply($message, $summary, $reorder, $anomalies);
        $llmReply = maybeGenerateAdminLlmReply($message, $context, $summary, $reorder, $anomalies);
        if ($llmReply !== '') {
            $replyText = $llmReply;
            $responseSource = 'llm';
        }
        $followUp = [
            'Daily summary',
            'Reorder suggestions',
            'Anomaly scan'
        ];
    }

    return [
        'intent' => $intent,
        'response_source' => $responseSource,
        'reply' => $replyText,
        'summary' => $summary,
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

function detectAdminIntent(string $normalized): string {
    if ($normalized === '') {
        return 'general';
    }

    if (preg_match('/\b(summary|daily brief|daily report|operations summary|what happened today)\b/i', $normalized) === 1) {
        return 'summary';
    }

    if (preg_match('/\b(reorder|stockout|restock|low stock|inventory risk|forecast)\b/i', $normalized) === 1) {
        return 'reorder';
    }

    if (preg_match('/\b(anomal(y|ies)|suspicious|fraud|mismatch|outlier|risk alert|issues)\b/i', $normalized) === 1) {
        return 'anomalies';
    }

    return 'general';
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

    $reorderPreview = array_slice(buildReorderSuggestions($db, 5), 0, 3);
    $anomalySnapshot = detectOperationalAnomalies($db, 10);
    $criticalAnomalies = (int)($anomalySnapshot['counts']['critical'] ?? 0);
    $highAnomalies = (int)($anomalySnapshot['counts']['high'] ?? 0);

    $highlights = [];
    $highlights[] = 'Orders: ' . $ordersToday . ' today (' . formatSignedPercentage($orderGrowth) . ' vs yesterday).';
    $highlights[] = 'Revenue: PHP ' . number_format($revenueToday, 2) . ' (' . formatSignedPercentage($revenueGrowth) . ' vs yesterday).';
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
            'revenue_today' => round($revenueToday, 2),
            'revenue_yesterday' => round($revenueYesterday, 2),
            'revenue_delta_pct' => $revenueGrowth,
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
            COALESCE(demand.avg_daily_demand, 0) AS avg_daily_demand,
            COALESCE(lead.avg_lead_days, 7) AS avg_lead_days
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        LEFT JOIN (
            SELECT
                product_id,
                SUM(qty) AS total_30d,
                SUM(qty) / 30 AS avg_daily_demand
            FROM (
                SELECT
                    coi.product_id AS product_id,
                    SUM(coi.quantity) AS qty
                FROM customer_order_items coi
                INNER JOIN customer_orders co ON co.id = coi.order_id
                WHERE DATE(co.order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  AND co.status <> 'cancelled'
                GROUP BY coi.product_id

                UNION ALL

                SELECT
                    si.product_id AS product_id,
                    SUM(si.quantity) AS qty
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                WHERE DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY si.product_id
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
    foreach ($rows as $row) {
        $quantityAvailable = (int)($row['quantity_available'] ?? 0);
        $reorderLevel = max(1, (int)($row['reorder_level'] ?? 1));
        $avgDailyDemand = max(0.0, (float)($row['avg_daily_demand'] ?? 0.0));
        $leadDays = max(1, (int)round((float)($row['avg_lead_days'] ?? 7)));

        $daysOfCover = null;
        if ($avgDailyDemand > 0) {
            $daysOfCover = $quantityAvailable / $avgDailyDemand;
        }

        $targetCoverageDays = max(14, $leadDays * 2);
        $targetDemandStock = (int)ceil($avgDailyDemand * $targetCoverageDays);
        $minimumBufferStock = $reorderLevel * 2;
        $targetStock = max($targetDemandStock, $minimumBufferStock);

        $suggestedOrderQty = max(0, $targetStock - $quantityAvailable);

        if ($avgDailyDemand <= 0 && $quantityAvailable <= $reorderLevel) {
            $suggestedOrderQty = max($suggestedOrderQty, $reorderLevel);
        }

        $priority = 'low';
        $priorityScore = 1;
        if ($quantityAvailable <= 0 || ($daysOfCover !== null && $daysOfCover < $leadDays)) {
            $priority = 'critical';
            $priorityScore = 4;
        } elseif ($quantityAvailable <= $reorderLevel || ($daysOfCover !== null && $daysOfCover < 7)) {
            $priority = 'high';
            $priorityScore = 3;
        } elseif ($daysOfCover !== null && $daysOfCover < 14) {
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
            'avg_daily_demand' => round($avgDailyDemand, 3),
            'avg_lead_days' => $leadDays,
            'days_of_cover' => $daysOfCover !== null ? round($daysOfCover, 2) : null,
            'suggested_order_qty' => $suggestedOrderQty,
            'priority' => $priority,
            'priority_score' => $priorityScore,
            'rationale' => buildReorderRationale($quantityAvailable, $reorderLevel, $daysOfCover, $leadDays)
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

function buildReorderRationale(int $quantityAvailable, int $reorderLevel, ?float $daysOfCover, int $leadDays): string {
    if ($quantityAvailable <= 0) {
        return 'Out of stock now; immediate replenishment recommended.';
    }

    if ($daysOfCover !== null && $daysOfCover < $leadDays) {
        return 'Projected stock cover (' . number_format($daysOfCover, 1) . ' days) is below lead time (' . $leadDays . ' days).';
    }

    if ($quantityAvailable <= $reorderLevel) {
        return 'Current stock is at or below reorder level.';
    }

    if ($daysOfCover !== null && $daysOfCover < 14) {
        return 'Stock cover is less than 14 days based on recent demand.';
    }

    return 'Buffer restock to maintain safety stock.';
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

function buildRulesBasedGeneralReply(string $message, array $summary, array $reorder, array $anomalies): string {
    $critical = (int)($anomalies['counts']['critical'] ?? 0);
    $high = (int)($anomalies['counts']['high'] ?? 0);
    $topReorder = count($reorder);
    $ordersToday = (int)($summary['metrics']['orders_today'] ?? 0);
    $revenueToday = (float)($summary['metrics']['revenue_today'] ?? 0);

    $lines = [];
    $lines[] = 'Current operations snapshot:';
    $lines[] = '- Orders today: ' . $ordersToday . '.';
    $lines[] = '- Revenue today: PHP ' . number_format($revenueToday, 2) . '.';
    $lines[] = '- Reorder candidates: ' . $topReorder . '.';
    $lines[] = '- Anomalies: ' . $critical . ' critical, ' . $high . ' high.';
    $lines[] = '';
    $lines[] = 'Ask for "daily summary", "reorder suggestions", or "anomaly scan" for a focused report.';

    return implode("\n", $lines);
}

function maybeGenerateAdminLlmReply(string $message, array $context, array $summary, array $reorder, array $anomalies): string {
    $llmConfig = getAdminAiLlmConfig();
    if (!$llmConfig['enabled']) {
        return '';
    }

    try {
        $messages = buildAdminLlmMessages($message, $context, $summary, $reorder, $anomalies, $llmConfig);
        $reply = callOpenAiCompatibleChat($messages, $llmConfig);
        return sanitizeLlmReply($reply);
    } catch (Exception $e) {
        error_log('Admin AI LLM fallback used: ' . $e->getMessage());
        return '';
    }
}

function buildAdminLlmMessages(string $message, array $context, array $summary, array $reorder, array $anomalies, array $llmConfig): array {
    $systemPrompt = implode("\n", [
        'You are the Admin AI Copilot for an inventory and e-commerce operations dashboard.',
        'You are read-only: never claim to execute approvals, stock edits, or financial transactions.',
        'Be concise, factual, and action-oriented.',
        'When suggesting actions, prioritize risk reduction and operational throughput.',
        'Use only grounded values from provided JSON context. If missing, say it is unavailable.',
        'If asked about unrelated topics, redirect to operations, purchasing, inventory, orders, and audit monitoring.',
        'Use short bullets and keep responses typically under 170 words.'
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
