// Customer Management Functions

function openCustomerModal() {
    const modalHtml = `
        <div class="modal-header">
            <h5 class="modal-title">Customer Management</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="search-tab" data-toggle="tab" href="#search" role="tab">Search Customer</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="add-tab" data-toggle="tab" href="#add" role="tab">Add New Customer</a>
                </li>
            </ul>
            <div class="tab-content mt-3" id="customerTabContent">
                <div class="tab-pane fade show active" id="search" role="tabpanel">
                    <div class="form-group mb-3">
                        <input type="text" class="form-control" id="customerSearch" placeholder="Search customers...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="customerTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="customerTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="add" role="tabpanel">
                    <form id="addCustomerForm">
                        <div class="form-group mb-3">
                            <label for="customerName">Name *</label>
                            <input type="text" class="form-control" id="customerName" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="customerEmail">Email</label>
                            <input type="email" class="form-control" id="customerEmail">
                        </div>
                        <div class="form-group mb-3">
                            <label for="customerPhone">Phone *</label>
                            <input type="tel" class="form-control" id="customerPhone" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="customerAddress">Address</label>
                            <textarea class="form-control" id="customerAddress" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </form>
                </div>
            </div>
        </div>
    `;

    showModal('Customer Management', modalHtml);
    initializeCustomerManagement();
}

function initializeCustomerManagement() {
    const searchInput = document.getElementById('customerSearch');
    const form = document.getElementById('addCustomerForm');

    // Initialize search functionality
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            searchCustomers(searchInput.value);
        }, 300));
    }

    // Initialize form submission
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            addNewCustomer();
        });
    }

    // Load initial customer list
    searchCustomers('');
}

function searchCustomers(query) {
    fetch(`/backend/api/customers/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('customerTableBody');
                tbody.innerHTML = '';

                data.customers.forEach(customer => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(customer.name)}</td>
                        <td>${escapeHtml(customer.email || '-')}</td>
                        <td>${escapeHtml(customer.phone)}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="selectCustomer(${customer.id})">
                                Select
                            </button>
                            <button class="btn btn-sm btn-info" onclick="editCustomer(${customer.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                showError(data.message || 'Failed to fetch customers');
            }
        })
        .catch(error => {
            console.error('Error searching customers:', error);
            showError('Failed to search customers');
        });
}

function addNewCustomer() {
    const data = {
        name: document.getElementById('customerName').value,
        email: document.getElementById('customerEmail').value,
        phone: document.getElementById('customerPhone').value,
        address: document.getElementById('customerAddress').value
    };

    fetch('/backend/api/customers/create.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('Customer added successfully');
                document.querySelector('#customerTabs #search-tab').click();
                searchCustomers('');
            } else {
                showError(data.message || 'Failed to add customer');
            }
        })
        .catch(error => {
            console.error('Error adding customer:', error);
            showError('Failed to add customer');
        });
}

function selectCustomer(customerId) {
    fetch(`/backend/api/customers/show.php?id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store customer data
                sessionStorage.setItem('selectedCustomer', JSON.stringify(data.customer));
                
                // Update UI
                document.getElementById('selected-customer').textContent = data.customer.name;
                
                // Close modal
                closeModal();
                
                // Show success message
                showSuccess('Customer selected successfully');
            } else {
                showError(data.message || 'Failed to select customer');
            }
        })
        .catch(error => {
            console.error('Error selecting customer:', error);
            showError('Failed to select customer');
        });
}

function editCustomer(customerId) {
    window.location.href = `/public/customer-edit.php?id=${customerId}`;
}