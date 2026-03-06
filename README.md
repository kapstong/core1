# PC Parts E-Commerce System

A comprehensive inventory management and e-commerce system designed for PC parts retailers, featuring supplier management, purchase orders, goods received notes, point-of-sale, and customer ordering capabilities.

## Features

- **User Management & Authentication**: Role-based access control (Admin, Inventory Manager, Purchasing Officer, Staff, Suppliers)
- **Supplier Management**: Supplier registration, approval workflow, and supplier portal
- **Purchase Orders (PO)**: Create and manage purchase orders from suppliers
- **Goods Received Notes (GRN)**: Track incoming inventory and quality control
- **Inventory Management**: Real-time stock tracking, reordering alerts, stock adjustments
- **Direct Adjustment Controls**: One-click `+1/-1` stock adjustments from the Products table
- **Point of Sale (POS)**: In-store sales processing and receipt generation
- **Customer E-Commerce**: Online ordering system with customer accounts
- **Audit Logging**: Comprehensive activity tracking and system auditing
- **API Integration**: RESTful APIs for all major functionalities
- **Multi-language Support**: Localized interface (English/Spanish)
- **Email Notifications**: Automated notifications for orders, approvals, etc.

## System Architecture

- **Frontend**: PHP/HTML/CSS/JavaScript (public/, supplier/)
- **Backend**: PHP APIs (backend/api/)
- **Database**: MySQL
- **Authentication**: Session-based with 2FA support
- **File Storage**: Local uploads with organized category structure

## Directory Structure

```
/
├── backend/          # API endpoints and business logic
├── data/            # System configuration files
├── database/        # Database schema and setup files
├── languages/       # Localization files
├── logs/            # System logs (errors, audits)
├── public/          # Main web application
├── supplier/        # Supplier portal interface
└── uploads/         # File uploads (products, categories)
```

## Requirements

- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx web server
- Composer (for PHP dependencies)
- Node.js (for frontend assets, if applicable)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd core1
   ```

2. **Configure environment**
   - Copy `.env.example` to `.env` and update database credentials
   - Set proper file permissions for uploads/ and logs/ directories

3. **Database setup**
   - Import `database/schema.sql` to create the database structure
   - The system includes sample data for development/testing

4. **Web server configuration**
   - Point document root to `public/` directory
   - Ensure proper URL rewriting for clean URLs

## Usage

- **Admin Dashboard**: Access via `public/` with admin credentials
- **Supplier Portal**: Suppliers access via `supplier/` for order management
- **API Access**: RESTful APIs available under `backend/api/`

## Admin AI Copilot Setup

- Place `.env` at project root (recommended) or one level above it.
- Keep `.env` outside any publicly browsable directory when possible.
- Required for LLM responses:
  - `CHATBOT_LLM_ENABLED=true`
  - `OPENAI_API_KEY=...` (or `ADMIN_AI_API_KEY=...`)
  - `CHATBOT_LLM_MODEL=gpt-4o-mini` (or `ADMIN_AI_MODEL=...`)
- Optional tuning for richer answers:
  - `CHATBOT_LLM_TEMPERATURE=0.35`
  - `CHATBOT_LLM_MAX_TOKENS=700`
  - `CHATBOT_LLM_TIMEOUT_SECONDS=35`
  - `ADMIN_AI_SYSTEM_PROMPT=...` (extra guardrails/instructions)

### Admin AI API Endpoints

- `GET /backend/api/ai/admin-copilot.php?mode=status`
- `GET /backend/api/ai/admin-copilot.php?mode=summary`
- `GET /backend/api/ai/admin-copilot.php?mode=history&days=90`
- `GET /backend/api/ai/admin-copilot.php?mode=forecast&days=14`
- `GET /backend/api/ai/admin-copilot.php?mode=reorder`
- `GET /backend/api/ai/admin-copilot.php?mode=anomalies`
- `GET /backend/api/ai/admin-copilot.php?mode=memory`
- `GET /backend/api/ai/admin-copilot.php?mode=evaluate`
- `POST /backend/api/ai/admin-copilot.php` with `{ "message": "...", "context": {...} }`
- `POST /backend/api/ai/admin-copilot.php` with `{ "action":"feedback", "response_id":"...", "rating":"up|down", "comment":"..." }`
- `POST /backend/api/ai/admin-copilot.php` with `{ "action":"approve", "response_id":"...", "decision":"approved|rejected", "response_source":"...", "intent":"..." }`

## Inventory Automation Setup

The inventory automation runner can automatically:
- detect low-stock and critical-stock products,
- create draft purchase orders for high-priority replenishment,
- send summary alerts to `admin`, `inventory_manager`, and `purchasing_officer` accounts.

Run manually:
```bash
php backend/utils/automate_inventory_ops.php
```

Dry-run validation (no DB writes, no real email sends):
```bash
php backend/utils/automate_inventory_ops.php --dry-run
```

Environment toggles:
- `INVENTORY_AUTOMATION_ENABLED=true`
- `INVENTORY_AUTOMATION_AUTO_PO_DRAFTS=true`
- `INVENTORY_AUTOMATION_ALERT_EMAILS=true`
- `INVENTORY_AUTOMATION_MAX_ITEMS=40`
- `INVENTORY_AUTOMATION_CRITICAL_COVER_DAYS=3`
- `INVENTORY_AUTOMATION_LEAD_DAYS=7`
- `INVENTORY_AUTOMATION_REVIEW_DAYS=14`
- `INVENTORY_AUTOMATION_ACTOR_USER_ID=0`
- `INVENTORY_AUTOMATION_DEFAULT_SUPPLIER_ID=0`

Windows Task Scheduler helper:
```powershell
powershell -ExecutionPolicy Bypass -File backend/utils/setup_inventory_automation_task.ps1
```

## Security Features

- CSRF protection
- XSS prevention
- SQL injection protection
- Role-based permissions
- Session management
- Audit logging

## Deployment

- Ensure production-grade database configuration
- Enable SSL/HTTPS
- Set proper file permissions
- Configure log rotation
- Set up automated backups

## Support

For technical support or issues, please refer to the system logs in `logs/` directory or contact the development team.

## License

This project is licensed under the MIT License.
