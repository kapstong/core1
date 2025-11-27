/**
 * User Manual System
 * Displays role-specific help and documentation
 */

class UserManual {
    constructor() {
        this.modal = null;
        this.manualData = null;
        this.init();
    }

    init() {
        this.createModal();
        this.attachEventListeners();
    }

    createModal() {
        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="userManualModal" tabindex="-1" aria-labelledby="userManualModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content manual-modal-content">
                        <div class="modal-header manual-header">
                            <div>
                                <h5 class="modal-title" id="userManualModalLabel">
                                    <i class="fas fa-book me-2"></i>
                                    <span id="manualTitle">User Manual</span>
                                </h5>
                                <p class="text-muted mb-0 mt-1" id="manualWelcome"></p>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body manual-body">
                            <div class="row">
                                <!-- Sidebar Navigation -->
                                <div class="col-lg-3">
                                    <div class="manual-sidebar">
                                        <h6 class="manual-sidebar-title">Contents</h6>
                                        <div class="list-group manual-nav" id="manualNavigation">
                                            <!-- Navigation items will be inserted here -->
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Area -->
                                <div class="col-lg-9">
                                    <div class="manual-content" id="manualContent">
                                        <div class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-3 text-muted">Loading manual...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer manual-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Append to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = new bootstrap.Modal(document.getElementById('userManualModal'));
    }

    attachEventListeners() {
        // Listen for modal show event
        document.getElementById('userManualModal').addEventListener('show.bs.modal', () => {
            this.loadManual();
        });
    }

    async loadManual() {
        try {
            // Use BASE_PATH if available (for local dev), otherwise use root path
            const basePath = typeof window.BASE_PATH !== 'undefined' ? window.BASE_PATH : '';
            const response = await fetch(`${basePath}/backend/api/user/manual.php`, {
                credentials: 'include' // Include cookies for session authentication
            });
            const result = await response.json();

            if (result.success && result.data) {
                this.manualData = result.data;
                this.renderManual();
            } else {
                this.showError('Failed to load manual');
            }
        } catch (error) {
            console.error('Error loading manual:', error);
            this.showError('Error loading manual. Please try again.');
        }
    }

    renderManual() {
        // Update title and welcome message
        document.getElementById('manualTitle').textContent = this.manualData.title;
        document.getElementById('manualWelcome').textContent = this.manualData.welcome;

        // Render navigation
        const navigation = document.getElementById('manualNavigation');
        navigation.innerHTML = '';

        this.manualData.sections.forEach((section, index) => {
            const navItem = document.createElement('button');
            navItem.className = 'list-group-item list-group-item-action manual-nav-item';
            navItem.dataset.sectionIndex = index;
            navItem.innerHTML = `
                <i class="${section.icon} me-2"></i>
                ${section.title}
            `;

            navItem.addEventListener('click', () => {
                this.showSection(index);
                // Update active state
                navigation.querySelectorAll('.list-group-item').forEach(item => {
                    item.classList.remove('active');
                });
                navItem.classList.add('active');
            });

            navigation.appendChild(navItem);
        });

        // Show first section by default
        if (this.manualData.sections.length > 0) {
            navigation.querySelector('.list-group-item').classList.add('active');
            this.showSection(0);
        }
    }

    showSection(index) {
        const section = this.manualData.sections[index];
        const contentDiv = document.getElementById('manualContent');

        contentDiv.innerHTML = `
            <div class="manual-section">
                <div class="manual-section-header">
                    <i class="${section.icon} manual-section-icon"></i>
                    <h3>${section.title}</h3>
                </div>
                <div class="manual-section-content">
                    ${section.content}
                </div>
            </div>
        `;

        // Scroll to top of content
        contentDiv.scrollTop = 0;
    }

    showError(message) {
        const contentDiv = document.getElementById('manualContent');
        contentDiv.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p class="text-muted">${message}</p>
            </div>
        `;
    }

    show() {
        this.modal.show();
    }
}

// Initialize manual system when DOM is loaded
let userManual;
document.addEventListener('DOMContentLoaded', () => {
    userManual = new UserManual();
});

// Global function to open manual
function openUserManual() {
    if (userManual) {
        userManual.show();
    }
}
