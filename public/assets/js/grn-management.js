// GRN Management Functions

function openGRNModal() {
    const modalHtml = `
        <div class="modal-header">
            <h5 class="modal-title">Create GRN</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="grnForm">
                <div class="form-group mb-3">
                    <label for="supplier">Supplier *</label>
                    <select class="form-control" id="supplier" required>
                        <option value="">Select Supplier</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label for="purchaseOrder">Purchase Order</label>
                    <select class="form-control" id="purchaseOrder">
                        <option value="">Select Purchase Order</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label for="reference">Reference Number *</label>
                    <input type="text" class="form-control" id="reference" required>
                </div>
                <div class="form-group mb-3">
                    <label>Items</label>
                    <div class="table-responsive">
                        <table class="table table-hover" id="grnItemsTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Expiry Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grnItemsBody">
                                <tr id="noItemsRow">
                                    <td colspan="5" class="text-center">No items added</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addGRNItem()">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                <div class="form-group mb-3">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create GRN</button>
            </form>
        </div>
    `;

    showModal('Create GRN', modalHtml);
    initializeGRNForm();
}

function initializeGRNForm() {
    loadSuppliers();
    initializeGRNFormListeners();
}

function loadSuppliers() {
    fetch('/backend/api/suppliers/list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('supplier');
                data.suppliers.forEach(supplier => {
                    const option = document.createElement('option');
                    option.value = supplier.id;
                    option.textContent = supplier.name;
                    select.appendChild(option);
                });
            } else {
                showError(data.message || 'Failed to load suppliers');
            }
        })
        .catch(error => {
            console.error('Error loading suppliers:', error);
            showError('Failed to load suppliers');
        });
}

function loadPurchaseOrders(supplierId) {
    fetch(`/backend/api/purchase_orders/index.php?supplier_id=${supplierId}&status=approved`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('purchaseOrder');
                select.innerHTML = '<option value="">Select Purchase Order</option>';

                data.purchase_orders.forEach(po => {
                    const option = document.createElement('option');
                    option.value = po.id;
                    option.textContent = `${po.po_number} (${formatDate(po.created_at)})`;
                    select.appendChild(option);
                });
            } else {
                showError(data.message || 'Failed to load purchase orders');
            }
        })
        .catch(error => {
            console.error('Error loading purchase orders:', error);
            showError('Failed to load purchase orders');
        });
}

function addGRNItem() {
    const tbody = document.getElementById('grnItemsBody');
    const noItemsRow = document.getElementById('noItemsRow');
    if (noItemsRow) {
        noItemsRow.remove();
    }

    const rowId = 'item_' + Date.now();
    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
        <td>
            <select class="form-control product-select" required>
                <option value="">Select Product</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control" min="1" required>
        </td>
        <td>
            <input type="number" class="form-control" min="0" step="0.01" required>
        </td>
        <td>
            <input type="date" class="form-control">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeGRNItem('${rowId}')">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    // Load products for the new row
    loadProductsForSelect(tr.querySelector('.product-select'));
}

function removeGRNItem(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        row.remove();
    }

    const tbody = document.getElementById('grnItemsBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="noItemsRow">
                <td colspan="5" class="text-center">No items added</td>
            </tr>
        `;
    }
}

function loadProductsForSelect(select) {
    fetch('/backend/api/products/list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = `${product.name} (${product.sku})`;
                    select.appendChild(option);
                });
            } else {
                showError(data.message || 'Failed to load products');
            }
        })
        .catch(error => {
            console.error('Error loading products:', error);
            showError('Failed to load products');
        });
}

function initializeGRNFormListeners() {
    const form = document.getElementById('grnForm');
    const supplierSelect = document.getElementById('supplier');

    supplierSelect.addEventListener('change', (e) => {
        if (e.target.value) {
            loadPurchaseOrders(e.target.value);
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        submitGRN();
    });
}

function submitGRN() {
    const data = {
        supplier_id: document.getElementById('supplier').value,
        purchase_order_id: document.getElementById('purchaseOrder').value || null,
        reference: document.getElementById('reference').value,
        notes: document.getElementById('notes').value,
        items: []
    };

    // Collect items data
    const itemRows = document.querySelectorAll('#grnItemsBody tr:not(#noItemsRow)');
    itemRows.forEach(row => {
        data.items.push({
            product_id: row.querySelector('.product-select').value,
            quantity: parseInt(row.querySelector('input[type="number"]:first-of-type').value),
            unit_cost: parseFloat(row.querySelector('input[type="number"]:last-of-type').value),
            expiry_date: row.querySelector('input[type="date"]').value || null
        });
    });

    fetch('/backend/api/grn/create.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('GRN created successfully');
                closeModal();
                if (typeof loadGRNList === 'function') {
                    loadGRNList(); // Refresh GRN list if we're on the GRN page
                }
            } else {
                showError(data.message || 'Failed to create GRN');
            }
        })
        .catch(error => {
            console.error('Error creating GRN:', error);
            showError('Failed to create GRN');
        });
}