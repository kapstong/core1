<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Terms of Service & Privacy Policy - PC Parts Central</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Print Styles */
        @media print {
            /* Hide non-essential elements */
            .nav, .nav-tabs, .tab-content > .tab-pane:not(.active), .btn, .card-header, .card-footer,
            .toast-container, script, .container > .row > .col-lg-10 > .text-center {
                display: none !important;
            }

            /* Page setup */
            @page {
                margin: 1in;
                size: letter;
            }

            /* Body styling */
            body {
                background: white !important;
                color: #000000 !important;
                font-family: 'Times New Roman', serif !important;
                line-height: 1.5 !important;
                font-size: 12pt !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* FORCE ALL TEXT TO BE PURE BLACK - MAXIMUM PRIORITY */
            * {
                color: #000000 !important;
                background: transparent !important;
                border-color: #000000 !important;
                text-decoration-color: #000000 !important;
                outline-color: #000000 !important;
            }

            /* Specific override for any remaining elements */
            h1, h2, h3, h4, h5, h6, p, span, div, li, strong, b, em, i, a, td, th, label, input, textarea, select {
                color: #000000 !important;
            }

            /* Legal content specific override */
            .legal-content * {
                color: #000000 !important;
            }

            /* Print header specific override */
            .print-header * {
                color: #000000 !important;
            }

            /* Header styling */
            .print-header {
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
                margin-bottom: 30px;
                page-break-after: avoid;
            }

            .print-header h1 {
                font-size: 24pt !important;
                font-weight: bold !important;
                margin: 0 0 10px 0 !important;
                text-transform: uppercase;
                letter-spacing: 2px;
            }

            .print-header .subtitle {
                font-size: 14pt !important;
                font-style: italic;
                margin: 0 !important;
            }

            /* Content styling */
            .card {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .card-body {
                padding: 0 !important;
            }

            .legal-content h4 {
                font-size: 16pt !important;
                font-weight: bold !important;
                margin: 30px 0 15px 0 !important;
                page-break-after: avoid;
                border-bottom: 1px solid #666;
                padding-bottom: 5px;
            }

            .legal-content h5 {
                font-size: 14pt !important;
                font-weight: bold !important;
                margin: 20px 0 10px 0 !important;
                page-break-after: avoid;
            }

            .legal-content p {
                margin: 0 0 12pt 0 !important;
                text-align: justify;
                orphans: 3;
                widows: 3;
            }

            .legal-content ul {
                margin: 10pt 0 15pt 20pt !important;
            }

            .legal-content li {
                margin-bottom: 6pt !important;
                text-align: justify;
            }

            /* Contact info box */
            .legal-content div[style*="background"] {
                background: #f8f8f8 !important;
                border: 1px solid #ccc !important;
                padding: 15pt !important;
                margin: 20pt 0 !important;
                page-break-inside: avoid;
            }

            /* Page breaks - only for major sections */
            .legal-content h4:nth-of-type(4),
            .legal-content h4:nth-of-type(7) {
                page-break-before: always;
            }

            /* Allow content to flow naturally */
            .legal-content h4,
            .legal-content h5,
            .legal-content p,
            .legal-content ul,
            .legal-content li {
                page-break-inside: avoid;
            }

            /* Footer for each page */
            @page :first {
                @bottom-center {
                    content: "PC Parts Central - Legal Agreement Document";
                    font-size: 10pt;
                    font-family: Arial, sans-serif;
                }
            }

            @page {
                @bottom-right {
                    content: "Page " counter(page) " of " counter(pages);
                    font-size: 10pt;
                    font-family: Arial, sans-serif;
                }
            }
        }

        /* Show print header only when printing */
        .print-header {
            display: none;
        }

        @media print {
            .print-header {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <!-- Background Effects -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; background: var(--bg-primary); background-image: radial-gradient(circle at 20% 30%, rgba(0, 102, 255, 0.15) 0%, transparent 50%), radial-gradient(circle at 80% 70%, rgba(0, 245, 255, 0.1) 0%, transparent 50%); background-attachment: fixed;"></div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <!-- Header Section -->
                <div class="text-center mb-5">
                    <div class="display-1 mb-3" style="background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 3rem;">
                        <i class="fas fa-file-contract me-3"></i>PC Parts Central
                    </div>
                    <h1 class="mb-3" style="color: var(--text-primary); font-weight: 600;">Legal Agreements</h1>
                    <p class="text-muted lead">Terms of Service and Privacy Policy for Suppliers</p>
                    <small class="text-muted">Last updated: <?php echo date('F j, Y'); ?></small>
                </div>

                <!-- Print Header (Hidden on screen, shown when printing) -->
                <div class="print-header" style="display: none !important;">
                    <h1 style="color: #000000 !important;">PC PARTS CENTRAL</h1>
                    <p class="subtitle" style="color: #000000 !important;">SUPPLIER LEGAL AGREEMENT DOCUMENTS</p>
                    <p style="color: #000000 !important;">Terms of Service & Privacy Policy</p>
                    <p style="color: #000000 !important;">Effective Date: <?php echo date('F j, Y'); ?></p>
                </div>

                <!-- Navigation Tabs -->
                <div class="mb-4">
                    <ul class="nav nav-pills justify-content-center" id="legal-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="terms-tab" data-bs-toggle="pill" data-bs-target="#terms" type="button" role="tab" aria-controls="terms" aria-selected="true" style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); margin: 0 0.25rem; transition: all var(--transition-fast);">
                                <i class="fas fa-gavel me-2"></i>Terms of Service
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="privacy-tab" data-bs-toggle="pill" data-bs-target="#privacy" type="button" role="tab" aria-controls="privacy" aria-selected="false" style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); margin: 0 0.25rem; transition: all var(--transition-fast);">
                                <i class="fas fa-shield-alt me-2"></i>Privacy Policy
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Content Cards -->
                <div class="tab-content" id="legal-tabContent">
                    <!-- Terms of Service Tab -->
                    <div class="tab-pane fade show active" id="terms" role="tabpanel" aria-labelledby="terms-tab">
                        <div class="card" style="background: var(--bg-glass); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: var(--shadow-xl); overflow: hidden;">
                            <div class="card-header" style="background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-card) 100%); border-bottom: 1px solid var(--border-color);">
                                <h3 class="mb-0" style="color: var(--accent); font-weight: 600;">
                                    <i class="fas fa-gavel me-2"></i>Terms of Service
                                </h3>
                            </div>
                            <div class="card-body p-4" style="color: var(--text-primary);">
                                <div class="legal-content">
                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin-bottom: 1.5rem;">1. Introduction</h4>
                                    <p>Welcome to PC Parts Central's Supplier Program. These Terms of Service ("Terms") govern your relationship with PC Parts Central ("we," "us," or "our") and your participation as a supplier in our inventory management system.</p>

                                    <p>By registering as a supplier and accessing our services, you agree to be bound by these Terms. If you disagree with any part of these terms, you may not access our services.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">2. Supplier Responsibilities</h4>

                                    <h5 style="color: var(--text-secondary);">2.1 Product Quality</h5>
                                    <p>All products supplied must meet industry standards and specifications. You are responsible for ensuring that all products are:</p>
                                    <ul>
                                        <li>Genuine and authentic</li>
                                        <li>Free from defects and damage</li>
                                        <li>Properly packaged and labeled</li>
                                        <li>Compliant with all applicable laws and regulations</li>
                                    </ul>

                                    <h5 style="color: var(--text-secondary);">2.2 Pricing and Terms</h5>
                                    <p>You agree to provide accurate pricing information and honor all quoted prices. Any changes to pricing must be communicated in advance and approved by PC Parts Central.</p>

                                    <h5 style="color: var(--text-secondary);">2.3 Delivery and Logistics</h5>
                                    <p>You are responsible for timely delivery of products according to agreed schedules. Delays must be communicated immediately with valid reasons.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">3. Our Responsibilities</h4>

                                    <h5 style="color: var(--text-secondary);">3.1 Payment Terms</h5>
                                    <p>We agree to pay for products according to the payment terms specified in individual purchase orders. Payments will be made within the agreed timeframe after receipt and verification of products.</p>

                                    <h5 style="color: var(--text-secondary);">3.2 Order Management</h5>
                                    <p>We will provide clear purchase orders and maintain accurate records of all transactions. Any discrepancies will be communicated promptly for resolution.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">4. Intellectual Property</h4>
                                    <p>You retain ownership of your trademarks and product designs. However, by supplying products to us, you grant us permission to use your product images and descriptions for marketing and sales purposes within our platform.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">5. Termination</h4>
                                    <p>Either party may terminate this agreement with 30 days written notice. We reserve the right to immediately terminate your supplier status for violations of these terms, quality issues, or other breaches of agreement.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">6. Limitation of Liability</h4>
                                    <p>PC Parts Central's liability is limited to the value of the specific transaction in question. We are not liable for indirect, incidental, or consequential damages.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">7. Governing Law</h4>
                                    <p>These Terms are governed by the laws of the Philippines. Any disputes will be resolved through the appropriate courts in the Philippines.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">8. Changes to Terms</h4>
                                    <p>We reserve the right to modify these Terms at any time. Suppliers will be notified of significant changes via email or through our supplier portal.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Policy Tab -->
                    <div class="tab-pane fade" id="privacy" role="tabpanel" aria-labelledby="privacy-tab">
                        <div class="card" style="background: var(--bg-glass); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: var(--shadow-xl); overflow: hidden;">
                            <div class="card-header" style="background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-card) 100%); border-bottom: 1px solid var(--border-color);">
                                <h3 class="mb-0" style="color: var(--accent); font-weight: 600;">
                                    <i class="fas fa-shield-alt me-2"></i>Privacy Policy
                                </h3>
                            </div>
                            <div class="card-body p-4" style="color: var(--text-primary);">
                                <div class="legal-content">
                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin-bottom: 1.5rem;">1. Information We Collect</h4>

                                    <h5 style="color: var(--text-secondary);">1.1 Business Information</h5>
                                    <ul>
                                        <li>Company name and registration details</li>
                                        <li>Contact person information</li>
                                        <li>Business address and contact numbers</li>
                                        <li>Tax identification numbers</li>
                                        <li>Banking information for payments</li>
                                    </ul>

                                    <h5 style="color: var(--text-secondary);">1.2 Transaction Data</h5>
                                    <ul>
                                        <li>Purchase orders and invoices</li>
                                        <li>Product catalogs and pricing</li>
                                        <li>Delivery schedules and tracking</li>
                                        <li>Quality control records</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">2. How We Use Your Information</h4>

                                    <h5 style="color: var(--text-secondary);">2.1 Business Operations</h5>
                                    <p>Your information is used to:</p>
                                    <ul>
                                        <li>Process orders and manage inventory</li>
                                        <li>Facilitate payments and financial transactions</li>
                                        <li>Communicate about orders and deliveries</li>
                                        <li>Maintain supplier relationships</li>
                                    </ul>

                                    <h5 style="color: var(--text-secondary);">2.2 Quality Assurance</h5>
                                    <ul>
                                        <li>Verify supplier credentials</li>
                                        <li>Conduct quality control checks</li>
                                        <li>Maintain product standards</li>
                                        <li>Resolve disputes and issues</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">3. Information Sharing</h4>
                                    <p>We do not sell or rent your business information to third parties. Information may be shared only in the following circumstances:</p>
                                    <ul>
                                        <li>With your explicit consent</li>
                                        <li>For legal compliance and regulatory requirements</li>
                                        <li>With service providers who assist our operations (under NDA)</li>
                                        <li>In connection with business transfers or acquisitions</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">4. Data Security</h4>
                                    <p>We implement appropriate technical and organizational measures to protect your information:</p>
                                    <ul>
                                        <li>Encrypted data transmission and storage</li>
                                        <li>Access controls and authentication</li>
                                        <li>Regular security audits</li>
                                        <li>Secure backup procedures</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">5. Data Retention</h4>
                                    <p>We retain your information for as long as necessary to:</p>
                                    <ul>
                                        <li>Fulfill our business relationship</li>
                                        <li>Comply with legal obligations</li>
                                        <li>Resolve disputes and enforce agreements</li>
                                        <li>Maintain business records</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">6. Your Rights</h4>
                                    <p>As a supplier, you have the right to:</p>
                                    <ul>
                                        <li>Access your personal/business information</li>
                                        <li>Correct inaccurate information</li>
                                        <li>Request deletion of information (subject to legal requirements)</li>
                                        <li>Object to certain processing activities</li>
                                        <li>Data portability</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">7. Cookies and Tracking</h4>
                                    <p>Our supplier portal may use cookies and similar technologies to:</p>
                                    <ul>
                                        <li>Maintain login sessions</li>
                                        <li>Remember preferences</li>
                                        <li>Track usage for system improvement</li>
                                        <li>Enhance security</li>
                                    </ul>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">8. International Data Transfers</h4>
                                    <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place for such transfers.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">9. Changes to Privacy Policy</h4>
                                    <p>We may update this Privacy Policy periodically. Suppliers will be notified of material changes via email or through our supplier portal.</p>

                                    <h4 style="color: var(--text-primary); border-bottom: 2px solid var(--accent); padding-bottom: 0.5rem; margin: 2rem 0 1.5rem 0;">10. Contact Information</h4>
                                    <p>For privacy-related questions or concerns, please contact our Data Protection Officer at:</p>
                                    <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color);">
                                        <p style="margin: 0;"><strong>Email:</strong> privacy@pcpartscentral.com</p>
                                        <p style="margin: 0;"><strong>Phone:</strong> +63 912 345 6789</p>
                                        <p style="margin: 0;"><strong>Address:</strong> PC Parts Central, Data Protection Office</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center mt-4">
                    <div class="d-flex justify-content-center gap-3">
                        <a href="../supplier/signup.php" class="btn btn-accent btn-lg" style="padding: 0.875rem 2rem; font-weight: 600; box-shadow: var(--shadow-glow);">
                            <i class="fas fa-arrow-left me-2"></i>Back to Registration
                        </a>
                        <a href="print-terms-privacy.php" target="_blank" class="btn btn-outline-secondary btn-lg" style="padding: 0.875rem 2rem; background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <i class="fas fa-print me-2"></i>Print Document
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching effects
        document.querySelectorAll('.nav-link').forEach(tab => {
            tab.addEventListener('click', function() {
                // Reset all tabs
                document.querySelectorAll('.nav-link').forEach(t => {
                    t.style.background = 'var(--bg-tertiary)';
                    t.style.color = 'var(--text-primary)';
                });

                // Highlight active tab
                this.style.background = 'var(--accent)';
                this.style.color = 'white';
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 32px rgba(0, 245, 255, 0.1)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'none';
                this.style.boxShadow = 'var(--shadow-xl)';
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
