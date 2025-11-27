<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Supplier Registration - PC Parts Central</title>
    <link rel="stylesheet" href="../public/assets/css/main.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-check-input:checked {
            background-color: var(--accent) !important;
            border-color: var(--accent) !important;
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 245, 255, 0.25) !important;
        }
    </style>
</head>
<body>
    <!-- Background Effects -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; background: var(--bg-primary); background-image: radial-gradient(circle at 20% 30%, rgba(0, 102, 255, 0.15) 0%, transparent 50%), radial-gradient(circle at 80% 70%, rgba(0, 245, 255, 0.1) 0%, transparent 50%); background-attachment: fixed;"></div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <!-- Header Section -->
                <div class="text-center mb-5">
                    <div class="display-1 mb-3" style="background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 3rem;">
                        <i class="fas fa-truck me-3"></i>PC Parts Central
                    </div>
                    <h2 class="mb-3" style="color: var(--text-primary); font-weight: 600;">Supplier Registration</h2>
                    <p class="text-muted lead">Join our supplier network and start providing quality PC components</p>
                </div>

                <!-- Registration Card -->
                <div class="card" style="background: var(--bg-glass); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: var(--shadow-xl); overflow: hidden;">
                    <!-- Card Header -->
                    <div class="card-header text-center py-4" style="background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-card) 100%); border-bottom: 1px solid var(--border-color);">
                        <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div>
                                <h4 class="mb-0" style="color: var(--text-primary);">Create Supplier Account</h4>
                                <small class="text-muted">Fill in your company details below</small>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body p-4">
                        <!-- Alert Container -->
                        <div id="alert" class="mb-4"></div>

                        <form id="signup-form">
                            <!-- Company Information Section -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: var(--accent); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-building"></i>Company Information
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Company / Supplier Name *</label>
                                        <input type="text" class="form-control" name="name" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast);" placeholder="Enter your company name">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Contact Person</label>
                                        <input type="text" class="form-control" name="contact_person" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast);" placeholder="Primary contact">
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information Section -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: var(--accent); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-address-card"></i>Contact Information
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Email Address *</label>
                                        <input type="email" class="form-control" name="email" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast);" placeholder="company@example.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast);" placeholder="+63 912 345 6789">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Business Address</label>
                                    <textarea class="form-control" name="address" rows="3" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast); resize: vertical;" placeholder="Complete business address including city, province, and postal code"></textarea>
                                </div>
                            </div>

                            <!-- Account Credentials Section -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: var(--accent); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-shield-alt"></i>Account Credentials
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Username *</label>
                                        <input type="text" class="form-control" name="username" required pattern="[a-zA-Z0-9_]{3,50}" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast);" placeholder="Choose a username">
                                        <small class="text-muted mt-1">3-50 characters, letters, numbers, and underscores only</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="password" required minlength="8" id="password-input" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); padding: 0.875rem 1.25rem; transition: all var(--transition-fast); border-right: none;" placeholder="Create a strong password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-left: none; color: var(--text-muted);">
                                                <i class="fas fa-eye" id="password-toggle-icon"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted mt-1">Minimum 8 characters with mixed case, numbers, and symbols</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms-check" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); accent-color: var(--accent);">
                                    <label class="form-check-label" for="terms-check" style="color: var(--text-secondary); font-size: 0.9rem;">
                                        I agree to the <a href="../public/terms-privacy.php#terms" target="_blank" style="color: var(--accent); text-decoration: none;">Terms of Service</a> and <a href="../public/terms-privacy.php#privacy" target="_blank" style="color: var(--accent); text-decoration: none;">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid gap-3">
                                <button class="btn btn-accent btn-lg" type="submit" id="submit-btn" style="padding: 1rem; font-size: 1.1rem; font-weight: 600; box-shadow: var(--shadow-glow);">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Registration Request
                                </button>
                                <div class="text-center">
                                    <a href="index.php" class="btn btn-ghost" style="color: var(--text-muted); text-decoration: none;">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Card Footer -->
                    <div class="card-footer text-center py-3" style="background: var(--bg-tertiary); border-top: 1px solid var(--border-color);">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Your registration will be reviewed by our administrators. You'll receive an email confirmation once approved.
                        </small>
                    </div>
                </div>


            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const BASE_PATH = IS_DEVELOPMENT ? '' : '/core1';
        const API_BASE = BASE_PATH + '/backend/api';

        // Password toggle functionality
        function togglePassword() {
            const input = document.getElementById('password-input');
            const icon = document.getElementById('password-toggle-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icons[type] || icons.info}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            `;

            toastContainer.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('removing');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }

        // Form validation and submission
        document.getElementById('signup-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submit-btn');
            const originalText = submitBtn.innerHTML;

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            try {
                const form = new FormData(this);
                const payload = {};
                form.forEach((v, k) => payload[k] = v);

                // Validate password strength
                if (payload.password.length < 8) {
                    showToast('Password must be at least 8 characters long', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }

                const response = await fetch(`${API_BASE}/suppliers/register.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.message || 'Registration submitted successfully! Please check your email for confirmation.', 'success');
                    this.reset();
                    document.getElementById('terms-check').checked = false;

                    // Redirect to login after success
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 3000);
                } else {
                    showToast(data.message || 'Registration failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Registration error:', error);
                showToast('Connection error. Please check your internet connection and try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        // Real-time form validation
        document.querySelectorAll('input[required], textarea[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = 'var(--danger)';
                    this.style.boxShadow = '0 0 0 3px rgba(255, 51, 102, 0.1)';
                } else {
                    this.style.borderColor = 'var(--success)';
                    this.style.boxShadow = '0 0 0 3px rgba(0, 255, 136, 0.1)';
                }
            });

            field.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = 'var(--success)';
                    this.style.boxShadow = '0 0 0 3px rgba(0, 255, 136, 0.1)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                    this.style.boxShadow = 'none';
                }
            });
        });

        // Username validation
        document.querySelector('input[name="username"]').addEventListener('input', function() {
            const username = this.value;
            const isValid = /^[a-zA-Z0-9_]{3,50}$/.test(username);

            if (username && !isValid) {
                this.style.borderColor = 'var(--danger)';
                this.style.boxShadow = '0 0 0 3px rgba(255, 51, 102, 0.1)';
            } else if (username && isValid) {
                this.style.borderColor = 'var(--success)';
                this.style.boxShadow = '0 0 0 3px rgba(0, 255, 136, 0.1)';
            } else {
                this.style.borderColor = 'var(--border-color)';
                this.style.boxShadow = 'none';
            }
        });

        // Password strength indicator
        document.querySelector('input[name="password"]').addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            if (strength < 3) {
                this.style.borderColor = 'var(--danger)';
                this.style.boxShadow = '0 0 0 3px rgba(255, 51, 102, 0.1)';
            } else if (strength < 5) {
                this.style.borderColor = 'var(--warning)';
                this.style.boxShadow = '0 0 0 3px rgba(255, 170, 0, 0.1)';
            } else {
                this.style.borderColor = 'var(--success)';
                this.style.boxShadow = '0 0 0 3px rgba(0, 255, 136, 0.1)';
            }
        });

        // Add focus effects to form controls
        document.querySelectorAll('.form-control').forEach(control => {
            control.addEventListener('focus', function() {
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = '0 4px 12px rgba(0, 245, 255, 0.15)';
            });

            control.addEventListener('blur', function() {
                this.style.transform = 'none';
                this.style.boxShadow = 'none';
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 8px 32px rgba(0, 245, 255, 0.1)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'none';
                this.style.boxShadow = 'var(--shadow-md)';
            });
        });
    </script>
</body>
</html>
