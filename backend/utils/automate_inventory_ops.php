<?php
/**
 * Inventory Automation Runner
 *
 * Automates low-stock monitoring, reorder analysis, PO draft generation, and alert delivery.
 *
 * Usage:
 *   php backend/utils/automate_inventory_ops.php
 *   php backend/utils/automate_inventory_ops.php --dry-run
 *   php backend/utils/automate_inventory_ops.php --force
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuditLogger.php';
require_once __DIR__ . '/../utils/Email.php';

try {
    $options = parseCliOptions($argv ?? []);
    $dryRun = isset($options['dry-run']);
    $force = isset($options['force']);

    $config = loadAutomationConfig();
    if (!$config['enabled'] && !$force) {
        echo "Inventory automation is disabled (INVENTORY_AUTOMATION_ENABLED=false)." . PHP_EOL;
        exit(0);
    }

    $runId = 'auto_' . gmdate('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $startedAt = gmdate('c');
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Throwable $dbError) {
        $summary = [
            'run_id' => $runId,
            'started_at' => $startedAt,
            'ended_at' => gmdate('c'),
            'dry_run' => $dryRun,
            'status' => 'skipped',
            'reason' => 'Database unavailable',
            'error' => $dbError->getMessage()
        ];
        writeAutomationLog($runId, $summary);
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $actorUserId = resolveAutomationActorUserId($db, (int)$config['actor_user_id']);
    $defaultSupplierId = resolveDefaultSupplierId($db, (int)$config['default_supplier_id']);

    $insights = collectLowStockInsights($db, $config);
    $criticalCandidates = array_values(array_filter($insights, static function (array $item): bool {
        return in_array((string)($item['priority'] ?? 'medium'), ['critical', 'high'], true);
    }));

    $createdDrafts = [];
    if ($config['auto_po_drafts']) {
        $createdDrafts = createDraftPurchaseOrders(
            $db,
            $criticalCandidates,
            $actorUserId,
            $defaultSupplierId,
            $config,
            $runId,
            $dryRun
        );
    }

    $alertStats = ['recipients' => 0, 'sent' => 0, 'failed' => 0];
    if ($config['alert_emails']) {
        $alertStats = sendAutomationSummaryEmails($db, $runId, $insights, $createdDrafts, $dryRun);
    }

    $summary = [
        'run_id' => $runId,
        'started_at' => $startedAt,
        'ended_at' => gmdate('c'),
        'dry_run' => $dryRun,
        'low_stock_count' => count($insights),
        'critical_candidates' => count($criticalCandidates),
        'auto_po_drafts_created' => count(array_filter($createdDrafts, static function (array $row): bool {
            return empty($row['simulated']);
        })),
        'auto_po_drafts_simulated' => count(array_filter($createdDrafts, static function (array $row): bool {
            return !empty($row['simulated']);
        })),
        'created_drafts' => $createdDrafts,
        'email_alerts' => $alertStats
    ];

    logAutomationRun($summary, $actorUserId);
    writeAutomationLog($runId, $summary);

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $error) {
    $message = 'Inventory automation failed: ' . $error->getMessage();
    error_log($message);
    echo $message . PHP_EOL;
    exit(1);
}

function parseCliOptions(array $argv): array {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!is_string($arg) || strpos($arg, '--') !== 0) {
            continue;
        }
        $pair = substr($arg, 2);
        if ($pair === '') {
            continue;
        }
        if (strpos($pair, '=') !== false) {
            [$key, $value] = explode('=', $pair, 2);
            $options[$key] = $value;
            continue;
        }
        $options[$pair] = true;
    }
    return $options;
}

function loadAutomationConfig(): array {
    return [
        'enabled' => (bool)(Env::get('INVENTORY_AUTOMATION_ENABLED', true) ?? true),
        'auto_po_drafts' => (bool)(Env::get('INVENTORY_AUTOMATION_AUTO_PO_DRAFTS', true) ?? true),
        'alert_emails' => (bool)(Env::get('INVENTORY_AUTOMATION_ALERT_EMAILS', true) ?? true),
        'max_items_per_run' => max(5, min(120, (int)(Env::get('INVENTORY_AUTOMATION_MAX_ITEMS', 40) ?? 40))),
        'critical_cover_days' => max(1, min(21, (int)(Env::get('INVENTORY_AUTOMATION_CRITICAL_COVER_DAYS', 3) ?? 3))),
        'lead_days' => max(1, min(60, (int)(Env::get('INVENTORY_AUTOMATION_LEAD_DAYS', 7) ?? 7))),
        'review_period_days' => max(1, min(60, (int)(Env::get('INVENTORY_AUTOMATION_REVIEW_DAYS', 14) ?? 14))),
        'actor_user_id' => (int)(Env::get('INVENTORY_AUTOMATION_ACTOR_USER_ID', 0) ?? 0),
        'default_supplier_id' => (int)(Env::get('INVENTORY_AUTOMATION_DEFAULT_SUPPLIER_ID', 0) ?? 0)
    ];
}

function resolveAutomationActorUserId(PDO $db, int $configuredUserId = 0): int {
    if ($configuredUserId > 0) {
        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE id = :id
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $configuredUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $stmt = $db->query("
        SELECT id
        FROM users
        WHERE is_active = 1
          AND role IN ('admin', 'inventory_manager', 'purchasing_officer')
        ORDER BY
            CASE role
                WHEN 'admin' THEN 1
                WHEN 'inventory_manager' THEN 2
                WHEN 'purchasing_officer' THEN 3
                ELSE 4
            END,
            id ASC
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['id'];
    }

    return 1;
}

function resolveDefaultSupplierId(PDO $db, int $configuredSupplierId = 0): ?int {
    if ($configuredSupplierId > 0) {
        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE id = :id
              AND role = 'supplier'
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $configuredSupplierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $stmt = $db->query("
        SELECT id
        FROM users
        WHERE role = 'supplier'
          AND is_active = 1
        ORDER BY id ASC
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['id'];
    }

    return null;
}

function collectLowStockInsights(PDO $db, array $config): array {
    $sql = "
        SELECT
            p.id AS product_id,
            p.name,
            p.sku,
            p.cost_price,
            p.reorder_level,
            COALESCE(i.quantity_available, COALESCE(i.quantity_on_hand, 0) - COALESCE(i.quantity_reserved, 0), 0) AS quantity_available,
            COALESCE(ABS(SUM(CASE WHEN sm.quantity < 0 THEN sm.quantity ELSE 0 END)) / 30, 0) AS avg_daily_demand
        FROM products p
        LEFT JOIN inventory i
            ON i.product_id = p.id
        LEFT JOIN stock_movements sm
            ON sm.product_id = p.id
           AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND sm.movement_type IN ('sale', 'customer_order')
        WHERE p.is_active = 1
        GROUP BY
            p.id, p.name, p.sku, p.cost_price, p.reorder_level,
            i.quantity_available, i.quantity_on_hand, i.quantity_reserved
        HAVING quantity_available <= p.reorder_level
        ORDER BY quantity_available ASC, p.reorder_level DESC, p.name ASC
        LIMIT :max_items
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':max_items', (int)$config['max_items_per_run'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insights = [];
    foreach ($rows as $row) {
        $quantityAvailable = (int)($row['quantity_available'] ?? 0);
        $reorderLevel = max(0, (int)($row['reorder_level'] ?? 0));
        $avgDailyDemand = max(0.0, (float)($row['avg_daily_demand'] ?? 0));

        $leadDays = (int)$config['lead_days'];
        $reviewDays = (int)$config['review_period_days'];
        $safetyStock = max(1, (int)ceil(max(0.5, $avgDailyDemand) * 2));
        $reorderPoint = max($reorderLevel, (int)ceil(($avgDailyDemand * $leadDays) + $safetyStock));
        $targetStock = max($reorderLevel * 2, $reorderPoint + (int)ceil($avgDailyDemand * $reviewDays));
        $suggestedOrderQty = max(1, $targetStock - $quantityAvailable);

        $daysOfCover = null;
        if ($avgDailyDemand > 0) {
            $daysOfCover = round($quantityAvailable / $avgDailyDemand, 1);
        }

        $priority = 'medium';
        if ($quantityAvailable <= 0 || ($daysOfCover !== null && $daysOfCover <= (float)$config['critical_cover_days'])) {
            $priority = 'critical';
        } elseif ($quantityAvailable <= max(1, (int)floor($reorderLevel * 0.5))) {
            $priority = 'high';
        }

        $insights[] = [
            'product_id' => (int)$row['product_id'],
            'name' => (string)$row['name'],
            'sku' => (string)$row['sku'],
            'cost_price' => max(0, (float)($row['cost_price'] ?? 0)),
            'quantity_available' => $quantityAvailable,
            'reorder_level' => $reorderLevel,
            'avg_daily_demand' => round($avgDailyDemand, 3),
            'days_of_cover' => $daysOfCover,
            'reorder_point' => $reorderPoint,
            'target_stock' => $targetStock,
            'suggested_order_qty' => $suggestedOrderQty,
            'priority' => $priority
        ];
    }

    usort($insights, static function (array $a, array $b): int {
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2];
        $ra = $rank[$a['priority']] ?? 99;
        $rb = $rank[$b['priority']] ?? 99;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return ($a['quantity_available'] ?? 0) <=> ($b['quantity_available'] ?? 0);
    });

    return $insights;
}

function createDraftPurchaseOrders(
    PDO $db,
    array $insights,
    int $actorUserId,
    ?int $defaultSupplierId,
    array $config,
    string $runId,
    bool $dryRun
): array {
    if (empty($insights)) {
        return [];
    }

    $grouped = [];
    foreach ($insights as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }
        $supplierId = resolvePreferredSupplierForProduct($db, $productId, $defaultSupplierId);
        if ($supplierId === null) {
            continue;
        }
        if (hasOpenPurchaseOrderForProduct($db, $supplierId, $productId)) {
            continue;
        }
        if (!isset($grouped[$supplierId])) {
            $grouped[$supplierId] = [];
        }
        $grouped[$supplierId][] = $item;
    }

    if (empty($grouped)) {
        return [];
    }

    $drafts = [];
    foreach ($grouped as $supplierId => $items) {
        if (empty($items)) {
            continue;
        }

        if ($dryRun) {
            $drafts[] = [
                'simulated' => true,
                'supplier_id' => (int)$supplierId,
                'item_count' => count($items),
                'items' => array_map(static function (array $item): array {
                    return [
                        'product_id' => (int)$item['product_id'],
                        'sku' => (string)$item['sku'],
                        'qty' => (int)$item['suggested_order_qty']
                    ];
                }, $items)
            ];
            continue;
        }

        $db->beginTransaction();
        try {
            $poNumber = generatePurchaseOrderNumber($db);
            $orderDate = gmdate('Y-m-d');
            $expectedDelivery = gmdate('Y-m-d', strtotime('+' . (int)$config['lead_days'] . ' days'));

            $totalAmount = 0.0;
            foreach ($items as $item) {
                $unitCost = max(0.0, (float)($item['cost_price'] ?? 0));
                $qty = max(1, (int)($item['suggested_order_qty'] ?? 1));
                $totalAmount += ($unitCost * $qty);
            }

            $poStmt = $db->prepare("
                INSERT INTO purchase_orders (
                    po_number, supplier_id, created_by, status, order_date, expected_delivery_date, total_amount, notes
                ) VALUES (
                    :po_number, :supplier_id, :created_by, 'draft', :order_date, :expected_delivery_date, :total_amount, :notes
                )
            ");
            $poStmt->execute([
                ':po_number' => $poNumber,
                ':supplier_id' => (int)$supplierId,
                ':created_by' => $actorUserId,
                ':order_date' => $orderDate,
                ':expected_delivery_date' => $expectedDelivery,
                ':total_amount' => round($totalAmount, 2),
                ':notes' => 'AUTO-DRAFT by inventory automation run ' . $runId . '. Review before supplier submission.'
            ]);

            $poId = (int)$db->lastInsertId();

            $itemStmt = $db->prepare("
                INSERT INTO purchase_order_items (
                    po_id, product_id, quantity_ordered, quantity_received, unit_cost, notes
                ) VALUES (
                    :po_id, :product_id, :quantity_ordered, 0, :unit_cost, :notes
                )
            ");

            foreach ($items as $item) {
                $qty = max(1, (int)($item['suggested_order_qty'] ?? 1));
                $unitCost = max(0.0, (float)($item['cost_price'] ?? 0));
                $itemStmt->execute([
                    ':po_id' => $poId,
                    ':product_id' => (int)$item['product_id'],
                    ':quantity_ordered' => $qty,
                    ':unit_cost' => $unitCost,
                    ':notes' => 'AUTO-DRAFT qty from inventory automation'
                ]);
            }

            $db->commit();

            $drafts[] = [
                'id' => $poId,
                'po_number' => $poNumber,
                'supplier_id' => (int)$supplierId,
                'item_count' => count($items),
                'total_amount' => round($totalAmount, 2),
                'simulated' => false
            ];

            AuditLogger::logCreate(
                'purchase_order',
                $poId,
                "Auto-created draft purchase order {$poNumber} from inventory automation run {$runId}",
                [
                    'po_number' => $poNumber,
                    'supplier_id' => (int)$supplierId,
                    'status' => 'draft',
                    'item_count' => count($items),
                    'total_amount' => round($totalAmount, 2),
                    'auto_generated' => true,
                    'automation_run_id' => $runId
                ]
            );
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    return $drafts;
}

function resolvePreferredSupplierForProduct(PDO $db, int $productId, ?int $defaultSupplierId): ?int {
    $stmt = $db->prepare("
        SELECT
            po.supplier_id,
            COUNT(*) AS usage_count,
            MAX(po.created_at) AS last_used_at
        FROM purchase_order_items poi
        INNER JOIN purchase_orders po
            ON po.id = poi.po_id
        INNER JOIN users supplier
            ON supplier.id = po.supplier_id
           AND supplier.role = 'supplier'
           AND supplier.is_active = 1
        WHERE poi.product_id = :product_id
        GROUP BY po.supplier_id
        ORDER BY usage_count DESC, last_used_at DESC
        LIMIT 1
    ");
    $stmt->execute([':product_id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['supplier_id'];
    }

    return $defaultSupplierId;
}

function hasOpenPurchaseOrderForProduct(PDO $db, int $supplierId, int $productId): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM purchase_order_items poi
        INNER JOIN purchase_orders po
            ON po.id = poi.po_id
        WHERE poi.product_id = :product_id
          AND po.supplier_id = :supplier_id
          AND po.status IN ('draft', 'pending_supplier', 'approved', 'ordered', 'partially_received')
    ");
    $stmt->execute([
        ':product_id' => $productId,
        ':supplier_id' => $supplierId
    ]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function generatePurchaseOrderNumber(PDO $db): string {
    $prefix = 'PO-' . gmdate('Y') . '-';
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'po_auto_number' LIMIT 1");
    $stmt->execute();
    $settingPrefix = $stmt->fetchColumn();
    if (is_string($settingPrefix) && trim($settingPrefix) !== '') {
        $prefix = trim($settingPrefix);
    }
    if (!preg_match('/[-_]$/', $prefix)) {
        $prefix .= '-';
    }

    $query = $db->prepare("
        SELECT po_number
        FROM purchase_orders
        WHERE po_number LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");
    $query->execute([':prefix' => $prefix . '%']);
    $lastPo = $query->fetchColumn();

    $nextNumber = 1;
    if (is_string($lastPo) && preg_match('/(\d+)\s*$/', $lastPo, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$nextNumber, 5, '0', STR_PAD_LEFT);
}

function sendAutomationSummaryEmails(PDO $db, string $runId, array $insights, array $drafts, bool $dryRun): array {
    $stmt = $db->query("
        SELECT id, full_name, email, role
        FROM users
        WHERE is_active = 1
          AND role IN ('admin', 'inventory_manager', 'purchasing_officer')
          AND email IS NOT NULL
          AND email <> ''
        ORDER BY id ASC
    ");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'recipients' => count($recipients),
        'sent' => 0,
        'failed' => 0
    ];

    if (empty($recipients)) {
        return $stats;
    }

    $subject = 'Inventory Automation Report ' . gmdate('Y-m-d H:i') . ' UTC';
    $message = buildAutomationEmailBody($runId, $insights, $drafts, $dryRun);
    $mailer = new Email();

    foreach ($recipients as $recipient) {
        if ($dryRun) {
            $stats['sent']++;
            continue;
        }

        $success = $mailer->send(
            (string)$recipient['email'],
            $subject,
            $message,
            (string)($recipient['full_name'] ?? '')
        );

        if ($success) {
            $stats['sent']++;
        } else {
            $stats['failed']++;
        }
    }

    return $stats;
}

function buildAutomationEmailBody(string $runId, array $insights, array $drafts, bool $dryRun): string {
    $critical = array_values(array_filter($insights, static function (array $item): bool {
        return (string)($item['priority'] ?? '') === 'critical';
    }));
    $high = array_values(array_filter($insights, static function (array $item): bool {
        return (string)($item['priority'] ?? '') === 'high';
    }));

    $topRows = array_slice($insights, 0, 10);
    $rowsHtml = '';
    foreach ($topRows as $item) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">' . esc((string)$item['name']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">' . esc((string)$item['sku']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;">' . (int)$item['quantity_available'] . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;">' . (int)$item['suggested_order_qty'] . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">' . strtoupper((string)$item['priority']) . '</td>'
            . '</tr>';
    }

    $draftRows = '';
    foreach ($drafts as $draft) {
        $identifier = !empty($draft['simulated']) ? 'SIMULATED' : (string)($draft['po_number'] ?? ('PO#' . (string)($draft['id'] ?? '')));
        $draftRows .= '<li>'
            . esc($identifier)
            . ' | Supplier #' . (int)($draft['supplier_id'] ?? 0)
            . ' | Items: ' . (int)($draft['item_count'] ?? 0)
            . '</li>';
    }
    if ($draftRows === '') {
        $draftRows = '<li>No draft POs created in this run.</li>';
    }

    $modeText = $dryRun ? 'DRY RUN (no DB/email side effects)' : 'LIVE RUN';

    return '
        <div style="font-family:Arial,sans-serif;line-height:1.45;color:#0f172a;">
            <h2 style="margin:0 0 8px;">Inventory Automation Report</h2>
            <p style="margin:0 0 12px;"><strong>Run ID:</strong> ' . esc($runId) . '<br><strong>Mode:</strong> ' . esc($modeText) . '</p>
            <p style="margin:0 0 12px;">
                <strong>Low stock items:</strong> ' . count($insights) . '<br>
                <strong>Critical:</strong> ' . count($critical) . ' | <strong>High:</strong> ' . count($high) . '
            </p>
            <h3 style="margin:14px 0 8px;">Top Low-Stock Items</h3>
            <table style="border-collapse:collapse;width:100%;font-size:13px;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #cbd5e1;">Product</th>
                        <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #cbd5e1;">SKU</th>
                        <th style="text-align:right;padding:6px 8px;border-bottom:2px solid #cbd5e1;">Avail.</th>
                        <th style="text-align:right;padding:6px 8px;border-bottom:2px solid #cbd5e1;">Suggested</th>
                        <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #cbd5e1;">Priority</th>
                    </tr>
                </thead>
                <tbody>' . $rowsHtml . '</tbody>
            </table>
            <h3 style="margin:14px 0 8px;">Auto PO Draft Results</h3>
            <ul style="margin:0;padding-left:18px;">' . $draftRows . '</ul>
            <p style="margin-top:14px;color:#475569;font-size:12px;">This is an automated operational report. Review draft POs before supplier submission.</p>
        </div>
    ';
}

function logAutomationRun(array $summary, int $actorUserId): void {
    AuditLogger::log(
        'inventory_automation',
        'inventory',
        null,
        'Inventory automation run completed',
        null,
        $summary,
        $actorUserId
    );
}

function writeAutomationLog(string $runId, array $summary): void {
    $rootDir = dirname(__DIR__, 2);
    $logDir = $rootDir . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    if (!is_dir($logDir) || !is_writable($logDir)) {
        return;
    }

    $line = '[' . gmdate('c') . '] ' . $runId . ' ' . json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($logDir . '/inventory-automation-' . gmdate('Ymd') . '.log', $line, FILE_APPEND);
}

function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
