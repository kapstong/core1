<?php
// Prevent any output before JSON
ob_start();

// Enable error logging but disable display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../middleware/Auth.php';

    // Clear any previous output
    ob_end_clean();

    header('Content-Type: application/json');

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check authentication
    $user = Auth::user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $role = $user['role'] ?? 'staff';
} catch (Exception $e) {
    // Clear output buffer and send error response
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Manual content structure for each role
$manuals = [
    'admin' => [
        'title' => 'Administrator Manual',
        'welcome' => 'Welcome to the Administrator Manual. As an admin, you have full access to all system features and settings.',
        'sections' => [
            [
                'title' => 'Dashboard Overview',
                'icon' => 'fas fa-chart-line',
                'content' => 'Your dashboard provides real-time insights into:
                    <ul>
                        <li><strong>Sales Metrics:</strong> Daily, weekly, and monthly revenue</li>
                        <li><strong>Inventory Status:</strong> Stock levels and low stock alerts</li>
                        <li><strong>Order Management:</strong> Pending, processing, and completed orders</li>
                        <li><strong>User Activity:</strong> Active users and recent activities</li>
                    </ul>'
            ],
            [
                'title' => 'User Management',
                'icon' => 'fas fa-users',
                'content' => 'Manage all staff accounts and permissions:
                    <ul>
                        <li><strong>Create Users:</strong> Add new staff members with specific roles</li>
                        <li><strong>Edit Users:</strong> Update user information and permissions</li>
                        <li><strong>Deactivate Users:</strong> Disable access without deleting accounts</li>
                        <li><strong>Role Assignment:</strong> Assign roles (Admin, Inventory Manager, Purchasing Officer, Staff)</li>
                    </ul>
                    <strong>Data Flow:</strong> User data is stored in the <code>users</code> table. When you create/modify a user, the system logs the action in <code>activity_logs</code>.'
            ],
            [
                'title' => 'Product & Inventory Management',
                'icon' => 'fas fa-boxes',
                'content' => 'Complete control over products and stock:
                    <ul>
                        <li><strong>Add Products:</strong> Create new products with SKU, pricing, and descriptions</li>
                        <li><strong>Categories:</strong> Organize products into categories and subcategories</li>
                        <li><strong>Stock Levels:</strong> Monitor and adjust inventory quantities</li>
                        <li><strong>Low Stock Alerts:</strong> Automatic notifications when stock falls below threshold</li>
                    </ul>
                    <strong>Data Flow:</strong> Products → Categories → Inventory → Purchase Orders → GRN (Goods Received Notes) → Stock Updates'
            ],
            [
                'title' => 'Purchase Order Management',
                'icon' => 'fas fa-file-invoice',
                'content' => 'Create and manage purchase orders:
                    <ul>
                        <li><strong>Create PO:</strong> Generate purchase orders for suppliers</li>
                        <li><strong>Approve PO:</strong> Review and approve pending orders</li>
                        <li><strong>Track Status:</strong> Monitor order progress (Pending → Approved → Shipped → Received)</li>
                        <li><strong>GRN Processing:</strong> Record goods received against POs</li>
                    </ul>
                    <strong>Data Flow:</strong> Create PO → Send to Supplier → Supplier Ships → Create GRN → Update Inventory → Close PO'
            ],
            [
                'title' => 'Supplier Management',
                'icon' => 'fas fa-truck',
                'content' => 'Manage supplier relationships:
                    <ul>
                        <li><strong>Add Suppliers:</strong> Register new vendors</li>
                        <li><strong>Approve Suppliers:</strong> Review and approve supplier registrations</li>
                        <li><strong>Contact Management:</strong> Maintain supplier contact information</li>
                        <li><strong>Performance Tracking:</strong> Monitor delivery times and quality</li>
                    </ul>'
            ],
            [
                'title' => 'Sales & Orders',
                'icon' => 'fas fa-shopping-cart',
                'content' => 'Monitor and manage customer orders:
                    <ul>
                        <li><strong>Order Processing:</strong> View and process incoming orders</li>
                        <li><strong>Order Status:</strong> Update order status (Processing → Shipped → Delivered)</li>
                        <li><strong>POS Access:</strong> Use Point of Sale for in-store transactions</li>
                        <li><strong>Refunds:</strong> Process returns and refunds</li>
                    </ul>
                    <strong>Data Flow:</strong> Customer Order → Payment → Inventory Deduction → Order Fulfillment → Shipping → Delivery'
            ],
            [
                'title' => 'Reports & Analytics',
                'icon' => 'fas fa-chart-bar',
                'content' => 'Access comprehensive business reports:
                    <ul>
                        <li><strong>Sales Reports:</strong> Revenue breakdown by period, product, category</li>
                        <li><strong>Inventory Reports:</strong> Stock valuation, movement, aging</li>
                        <li><strong>Purchase Reports:</strong> Supplier performance, PO history</li>
                        <li><strong>User Activity:</strong> Staff performance and activity logs</li>
                    </ul>'
            ],
            [
                'title' => 'System Settings',
                'icon' => 'fas fa-cog',
                'content' => 'Configure system-wide settings:
                    <ul>
                        <li><strong>Company Info:</strong> Business name, logo, contact details</li>
                        <li><strong>Tax Settings:</strong> Configure tax rates and calculations</li>
                        <li><strong>Email Templates:</strong> Customize notification emails</li>
                        <li><strong>Security:</strong> Session timeout, password policies, 2FA</li>
                        <li><strong>Backup & Restore:</strong> Database backup management</li>
                    </ul>'
            ],
            [
                'title' => 'Audit Logs',
                'icon' => 'fas fa-history',
                'content' => 'Track all system activities:
                    <ul>
                        <li><strong>User Actions:</strong> Login, logout, data modifications</li>
                        <li><strong>Data Changes:</strong> Who changed what and when</li>
                        <li><strong>Security Events:</strong> Failed login attempts, permission changes</li>
                        <li><strong>Export Logs:</strong> Download audit trails for compliance</li>
                    </ul>'
            ]
        ]
    ],

    'inventory_manager' => [
        'title' => 'Inventory Manager Manual',
        'welcome' => 'Welcome to the Inventory Manager Manual. You have control over products, stock levels, and purchase orders.',
        'sections' => [
            [
                'title' => 'Dashboard Overview',
                'icon' => 'fas fa-chart-line',
                'content' => 'Your dashboard shows:
                    <ul>
                        <li><strong>Inventory Value:</strong> Total stock worth</li>
                        <li><strong>Low Stock Items:</strong> Products needing reorder</li>
                        <li><strong>Pending POs:</strong> Purchase orders awaiting action</li>
                        <li><strong>Recent GRNs:</strong> Latest goods received</li>
                    </ul>'
            ],
            [
                'title' => 'Product Management',
                'icon' => 'fas fa-box',
                'content' => 'Manage product catalog:
                    <ul>
                        <li><strong>Add Products:</strong> Create new products with SKU, barcode, pricing</li>
                        <li><strong>Edit Products:</strong> Update details, images, descriptions</li>
                        <li><strong>Product Variants:</strong> Manage sizes, colors, options</li>
                        <li><strong>Pricing:</strong> Set cost price, selling price, discount rates</li>
                    </ul>
                    <strong>Data Flow:</strong> Product Creation → Category Assignment → Initial Stock Entry → Available for Sale'
            ],
            [
                'title' => 'Category Management',
                'icon' => 'fas fa-tags',
                'content' => 'Organize products effectively:
                    <ul>
                        <li><strong>Create Categories:</strong> Main product categories</li>
                        <li><strong>Subcategories:</strong> Hierarchical organization</li>
                        <li><strong>Assign Products:</strong> Link products to categories</li>
                        <li><strong>Category Images:</strong> Visual representation</li>
                    </ul>'
            ],
            [
                'title' => 'Inventory Control',
                'icon' => 'fas fa-warehouse',
                'content' => 'Monitor and adjust stock levels:
                    <ul>
                        <li><strong>Stock Levels:</strong> View current quantities for all products</li>
                        <li><strong>Stock Adjustments:</strong> Manual adjustments for discrepancies</li>
                        <li><strong>Low Stock Alerts:</strong> Set minimum thresholds and receive alerts</li>
                        <li><strong>Stock Movement:</strong> Track incoming and outgoing inventory</li>
                    </ul>
                    <strong>Data Flow:</strong> Stock Adjustment Entry → Inventory Update → Activity Log → Alert if Below Threshold'
            ],
            [
                'title' => 'Purchase Orders',
                'icon' => 'fas fa-file-invoice',
                'content' => 'Create and manage purchase orders:
                    <ul>
                        <li><strong>Create PO:</strong> Select supplier, add products, specify quantities</li>
                        <li><strong>PO Status:</strong> Track status (Draft → Pending → Approved → Ordered)</li>
                        <li><strong>Modify PO:</strong> Edit pending orders before approval</li>
                        <li><strong>Cancel PO:</strong> Cancel orders if needed</li>
                    </ul>
                    <strong>Data Flow:</strong> Identify Low Stock → Create PO → Submit for Approval → Send to Supplier → Track Delivery'
            ],
            [
                'title' => 'Goods Received Notes (GRN)',
                'icon' => 'fas fa-clipboard-check',
                'content' => 'Process incoming shipments:
                    <ul>
                        <li><strong>Create GRN:</strong> Record goods received against PO</li>
                        <li><strong>Verify Quantities:</strong> Match received vs ordered quantities</li>
                        <li><strong>Quality Check:</strong> Note any damaged or incorrect items</li>
                        <li><strong>Update Inventory:</strong> Automatically increase stock levels</li>
                    </ul>
                    <strong>Data Flow:</strong> Goods Arrive → Create GRN → Verify Items → Update Inventory → Close PO (if complete)'
            ],
            [
                'title' => 'Supplier Information',
                'icon' => 'fas fa-truck',
                'content' => 'View and work with suppliers:
                    <ul>
                        <li><strong>Supplier List:</strong> View all approved suppliers</li>
                        <li><strong>Contact Info:</strong> Access supplier contact details</li>
                        <li><strong>Order History:</strong> Review past orders with each supplier</li>
                        <li><strong>Performance:</strong> Check delivery times and reliability</li>
                    </ul>
                    <strong>Note:</strong> You can view suppliers but only admins can add or modify them.'
            ],
            [
                'title' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'content' => 'Access inventory reports:
                    <ul>
                        <li><strong>Stock Valuation:</strong> Total inventory value</li>
                        <li><strong>Stock Movement:</strong> Products in/out over time</li>
                        <li><strong>Low Stock Report:</strong> Items needing reorder</li>
                        <li><strong>Dead Stock:</strong> Items with no movement</li>
                        <li><strong>Purchase History:</strong> PO and GRN reports</li>
                    </ul>'
            ]
        ]
    ],

    'purchasing_officer' => [
        'title' => 'Purchasing Officer Manual',
        'welcome' => 'Welcome to the Purchasing Officer Manual. You manage purchase orders and supplier relationships.',
        'sections' => [
            [
                'title' => 'Dashboard Overview',
                'icon' => 'fas fa-chart-line',
                'content' => 'Your dashboard displays:
                    <ul>
                        <li><strong>Pending POs:</strong> Purchase orders awaiting approval</li>
                        <li><strong>Active Orders:</strong> Orders in progress</li>
                        <li><strong>Supplier Status:</strong> Active supplier count</li>
                        <li><strong>Spending:</strong> Purchase totals and budgets</li>
                    </ul>'
            ],
            [
                'title' => 'Purchase Order Creation',
                'icon' => 'fas fa-file-invoice',
                'content' => 'Create new purchase orders:
                    <ul>
                        <li><strong>Select Supplier:</strong> Choose from approved suppliers</li>
                        <li><strong>Add Products:</strong> Select products and quantities needed</li>
                        <li><strong>Pricing:</strong> Enter unit prices and verify totals</li>
                        <li><strong>Delivery Date:</strong> Set expected delivery date</li>
                        <li><strong>Notes:</strong> Add special instructions</li>
                    </ul>
                    <strong>Data Flow:</strong> Create PO → Review Details → Submit → Approval (if required) → Send to Supplier → Track'
            ],
            [
                'title' => 'Purchase Order Management',
                'icon' => 'fas fa-tasks',
                'content' => 'Manage existing orders:
                    <ul>
                        <li><strong>View All POs:</strong> See all purchase orders</li>
                        <li><strong>Filter & Search:</strong> Find specific orders by date, supplier, status</li>
                        <li><strong>Edit Pending POs:</strong> Modify orders before sending to supplier</li>
                        <li><strong>Cancel Orders:</strong> Cancel POs if necessary</li>
                        <li><strong>Track Status:</strong> Monitor progress from creation to delivery</li>
                    </ul>
                    <strong>PO Status Flow:</strong> Draft → Pending Approval → Approved → Sent to Supplier → In Transit → Delivered → Closed'
            ],
            [
                'title' => 'Supplier Management',
                'icon' => 'fas fa-truck',
                'content' => 'Manage supplier relationships:
                    <ul>
                        <li><strong>Add Suppliers:</strong> Register new vendors with contact info</li>
                        <li><strong>Edit Suppliers:</strong> Update supplier information</li>
                        <li><strong>Supplier Status:</strong> Activate or deactivate suppliers</li>
                        <li><strong>Contact Management:</strong> Maintain email, phone, address</li>
                        <li><strong>Payment Terms:</strong> Set credit terms and payment methods</li>
                    </ul>
                    <strong>Data Flow:</strong> Supplier Registration → Verification → Approval → Available for PO Creation'
            ],
            [
                'title' => 'Supplier Performance',
                'icon' => 'fas fa-star',
                'content' => 'Monitor supplier reliability:
                    <ul>
                        <li><strong>Delivery Performance:</strong> On-time delivery rates</li>
                        <li><strong>Order Accuracy:</strong> Correct items and quantities</li>
                        <li><strong>Quality Issues:</strong> Track defects or problems</li>
                        <li><strong>Communication:</strong> Responsiveness and cooperation</li>
                    </ul>'
            ],
            [
                'title' => 'Inventory Overview',
                'icon' => 'fas fa-boxes',
                'content' => 'View inventory status:
                    <ul>
                        <li><strong>Current Stock:</strong> See available quantities</li>
                        <li><strong>Low Stock Items:</strong> Products needing reorder</li>
                        <li><strong>Reorder Points:</strong> Minimum stock thresholds</li>
                        <li><strong>Lead Times:</strong> Typical supplier delivery times</li>
                    </ul>
                    <strong>Note:</strong> You can view inventory but cannot modify stock levels directly. Use POs and GRNs to affect inventory.'
            ],
            [
                'title' => 'Reports & Analytics',
                'icon' => 'fas fa-chart-bar',
                'content' => 'Access purchasing reports:
                    <ul>
                        <li><strong>Purchase History:</strong> All POs by date range</li>
                        <li><strong>Supplier Comparison:</strong> Pricing and performance across suppliers</li>
                        <li><strong>Spending Analysis:</strong> Purchase totals by category, supplier</li>
                        <li><strong>Outstanding POs:</strong> Open orders and their status</li>
                    </ul>'
            ]
        ]
    ],

    'staff' => [
        'title' => 'Staff Member Manual',
        'welcome' => 'Welcome to the Staff Manual. You can manage customers, process orders, and use the Point of Sale system.',
        'sections' => [
            [
                'title' => 'Dashboard Overview',
                'icon' => 'fas fa-chart-line',
                'content' => 'Your dashboard shows:
                    <ul>
                        <li><strong>Daily Sales:</strong> Today\'s transaction totals</li>
                        <li><strong>Recent Orders:</strong> Latest customer orders</li>
                        <li><strong>Pending Tasks:</strong> Orders needing attention</li>
                    </ul>'
            ],
            [
                'title' => 'Customer Management',
                'icon' => 'fas fa-users',
                'content' => 'Manage customer accounts:
                    <ul>
                        <li><strong>View Customers:</strong> See all registered customers</li>
                        <li><strong>Customer Details:</strong> View contact info and order history</li>
                        <li><strong>Search Customers:</strong> Find customers by name, email, phone</li>
                        <li><strong>Customer Status:</strong> Check account status (active/inactive)</li>
                    </ul>
                    <strong>Note:</strong> You can view customer information but cannot create or delete customer accounts.'
            ],
            [
                'title' => 'Order Processing',
                'icon' => 'fas fa-shopping-cart',
                'content' => 'Handle customer orders:
                    <ul>
                        <li><strong>View Orders:</strong> See all customer orders</li>
                        <li><strong>Order Details:</strong> View items, quantities, totals</li>
                        <li><strong>Update Status:</strong> Change order status (Processing → Shipped → Delivered)</li>
                        <li><strong>Order Notes:</strong> Add internal notes about orders</li>
                    </ul>
                    <strong>Order Flow:</strong> New Order → Payment Confirmed → Processing → Packed → Shipped → Delivered → Completed'
            ],
            [
                'title' => 'Point of Sale (POS)',
                'icon' => 'fas fa-cash-register',
                'content' => 'Process in-store transactions:
                    <ul>
                        <li><strong>Product Search:</strong> Find products by name, SKU, or barcode</li>
                        <li><strong>Add to Cart:</strong> Build transaction with multiple items</li>
                        <li><strong>Quantities:</strong> Adjust item quantities</li>
                        <li><strong>Payment:</strong> Accept cash or card payments</li>
                        <li><strong>Receipt:</strong> Print or email customer receipts</li>
                        <li><strong>Returns:</strong> Process returns and refunds</li>
                    </ul>
                    <strong>Data Flow:</strong> Scan/Search Product → Add to Cart → Review Total → Process Payment → Update Inventory → Generate Receipt'
            ],
            [
                'title' => 'Product Catalog (View Only)',
                'icon' => 'fas fa-box',
                'content' => 'Browse product information:
                    <ul>
                        <li><strong>View Products:</strong> See all available products</li>
                        <li><strong>Product Details:</strong> Prices, descriptions, stock levels</li>
                        <li><strong>Search Products:</strong> Find items quickly</li>
                        <li><strong>Stock Check:</strong> Verify product availability</li>
                    </ul>
                    <strong>Note:</strong> You can view products but cannot add, edit, or delete them. Contact your manager for product changes.'
            ],
            [
                'title' => 'Profile & Settings',
                'icon' => 'fas fa-user-cog',
                'content' => 'Manage your account:
                    <ul>
                        <li><strong>Update Profile:</strong> Change your name, email, phone</li>
                        <li><strong>Change Password:</strong> Update your login password</li>
                        <li><strong>Enable 2FA:</strong> Add two-factor authentication for security</li>
                        <li><strong>Activity History:</strong> View your login history</li>
                    </ul>'
            ]
        ]
    ],

    'supplier' => [
        'title' => 'Supplier Portal Manual',
        'welcome' => 'Welcome to the Supplier Portal. Manage your purchase orders and shipments.',
        'sections' => [
            [
                'title' => 'Dashboard Overview',
                'icon' => 'fas fa-chart-line',
                'content' => 'Your dashboard displays:
                    <ul>
                        <li><strong>Active POs:</strong> Purchase orders sent to you</li>
                        <li><strong>Pending Shipments:</strong> Orders ready to ship</li>
                        <li><strong>Completed Orders:</strong> Successfully delivered orders</li>
                        <li><strong>Notifications:</strong> New POs and messages</li>
                    </ul>'
            ],
            [
                'title' => 'Purchase Orders',
                'icon' => 'fas fa-file-invoice',
                'content' => 'View and manage your purchase orders:
                    <ul>
                        <li><strong>View POs:</strong> See all purchase orders sent to you</li>
                        <li><strong>PO Details:</strong> Review items, quantities, delivery dates</li>
                        <li><strong>Confirm Orders:</strong> Acknowledge receipt of PO</li>
                        <li><strong>Download PO:</strong> Export PO details as PDF</li>
                    </ul>
                    <strong>PO Flow:</strong> Receive PO → Review → Confirm → Prepare Items → Ship → Update Tracking'
            ],
            [
                'title' => 'Shipment Management',
                'icon' => 'fas fa-shipping-fast',
                'content' => 'Track and update shipments:
                    <ul>
                        <li><strong>Create Shipment:</strong> Register outgoing shipments</li>
                        <li><strong>Tracking Info:</strong> Add carrier and tracking number</li>
                        <li><strong>Update Status:</strong> Mark as shipped, in transit, delivered</li>
                        <li><strong>Shipping Documents:</strong> Upload packing lists, invoices</li>
                    </ul>
                    <strong>Data Flow:</strong> Pack Order → Create Shipment → Add Tracking → Notify Customer → Delivery → GRN Created'
            ],
            [
                'title' => 'Product Catalog',
                'icon' => 'fas fa-boxes',
                'content' => 'View products you supply:
                    <ul>
                        <li><strong>Your Products:</strong> See products linked to your account</li>
                        <li><strong>Pricing:</strong> View agreed pricing</li>
                        <li><strong>Stock Info:</strong> Current customer inventory levels</li>
                    </ul>'
            ],
            [
                'title' => 'Communications',
                'icon' => 'fas fa-comments',
                'content' => 'Stay connected with the purchasing team:
                    <ul>
                        <li><strong>Messages:</strong> Receive and send messages</li>
                        <li><strong>PO Questions:</strong> Ask about order details</li>
                        <li><strong>Notifications:</strong> Email alerts for new POs</li>
                    </ul>'
            ],
            [
                'title' => 'Profile & Settings',
                'icon' => 'fas fa-user-cog',
                'content' => 'Manage your supplier account:
                    <ul>
                        <li><strong>Company Info:</strong> Update business details</li>
                        <li><strong>Contact Details:</strong> Maintain email, phone, address</li>
                        <li><strong>Change Password:</strong> Update login credentials</li>
                        <li><strong>Payment Info:</strong> Bank account for payments</li>
                    </ul>'
            ]
        ]
    ]
];

// Return the manual for the user's role
try {
    if (isset($manuals[$role])) {
        echo json_encode([
            'success' => true,
            'data' => $manuals[$role]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'title' => 'User Manual',
                'welcome' => 'Welcome to the system.',
                'sections' => []
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error generating manual',
        'message' => $e->getMessage()
    ]);
}
