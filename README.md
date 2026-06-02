# রড সিমেন্ট ম্যানেজমেন্ট — Rod & Cement Shop Management System

A full-featured, multi-branch retail shop management system built for **steel rod and cement** businesses. Manage inventory, sales, customer credit, supplier purchases, expenses, quotations, installments, and financial reports — all in one place, with a Bengali-language interface.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [User Roles](#user-roles)
- [Module Guide](#module-guide)
  - [Dashboard](#dashboard)
  - [Products](#products)
  - [Stock Management](#stock-management)
  - [Sales](#sales)
  - [Quotations / Estimates](#quotations--estimates)
  - [Customers & Credit](#customers--credit)
  - [Customer Ledger (খাতা)](#customer-ledger-খাতা)
  - [Payments](#payments)
  - [Installments](#installments)
  - [Expenses](#expenses)
  - [Branches](#branches)
  - [Suppliers](#suppliers)
  - [Reports](#reports)
  - [User Management](#user-management)
  - [Notes](#notes)
  - [Backup & Restore](#backup--restore)
  - [Settings](#settings)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Security](#security)
- [Migrations](#migrations)

---

## Overview

This system was built for a building materials business that operates from a **central warehouse** with **multiple delivery branches**. A single admin/manager handles operations; branch staff handle delivery and local stock. The system tracks:

- What stock is in each branch
- Which sales came from which branch
- How much each customer owes (with full ledger history)
- Profit/loss across a date range
- Business expenses and overheads
- Installment payment plans for large purchases
- Quotations and estimates before finalizing sales

The UI is entirely in **Bengali (বাংলা)** to suit local staff.

---

## Features

### Core

| Feature | Description |
|---|---|
| **Multi-branch support** | Separate stock, sales, and staff per branch |
| **Product catalog** | Rod (রড) and Cement (সিমেন্ট) with size/brand tracking |
| **Stock management** | Purchase inbound, manual adjustments (+/−), branch-to-branch transfers |
| **Sales & invoicing** | Line-item invoices with auto-calculated totals, discount, partial payment |
| **Quotations / Estimates** | Create, edit, print, and convert quotations to sales |
| **Customer credit (বাকি)** | Track due amounts per customer, full payment ledger |
| **Customer Ledger (খাতা)** | Chronological account book with printable A4 statement |
| **Payment collection** | Record payments against specific invoices or apply FIFO across all dues |
| **Installments** | Multi-instalment payment schedules with due dates and status tracking |
| **Expenses** | Record and track business expenses by category |
| **Supplier tracking** | Link stock purchases to suppliers, view total spend |
| **Reports & analytics** | Date-range sales summaries, top products, profit estimates, stock valuation |
| **User accounts** | Admin, Manager, and Staff roles with branch-scoped access for staff |
| **Invoice printing** | Printable invoice with gradient header, tear-line divider, paid/due stamp |
| **Quotation printing** | Printable A4 estimate/quotation with shop branding |
| **Ledger printing** | Printable A4 customer account statement |
| **Low stock alerts** | Dashboard and stock page warn when stock falls below minimum threshold |
| **Notes** | Internal sticky notes / reminders for the team |
| **Backup & Restore** | Full SQL dump export/import from within the app |
| **Audit log** | Every create/update/delete action is logged with user and timestamp |

### Invoice Design
- Gradient red header with shop name and contact
- Tear-line dashed divider (receipt style)
- Two-column layout: invoice details + customer info
- Alternating-row items table with red header
- Color-coded totals block (green = paid, red = due)
- Diagonal watermark (বাকি আছে / পরিশোধিত / বাতিল)
- Dark footer with thank-you message
- Fully printable with background colors preserved (`print-color-adjust: exact`)

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.4 (OOP, no framework) |
| **Database** | MySQL 8 (PDO, prepared statements) |
| **Frontend** | Bootstrap 5.3 + Bootstrap Icons |
| **Charts** | Chart.js 4.4 |
| **Select UI** | Tom Select (searchable dropdowns) |
| **AJAX** | Vanilla JS Fetch API |
| **Session** | PHP native sessions (2-hour lifetime) |
| **Passwords** | bcrypt (cost 12) via `password_hash()` |
| **Language** | Bengali UI (UTF-8 / utf8mb4) |

---

## Project Structure

```
rod-cement-shop/
├── index.php                        # Login page
├── config/
│   ├── app.php                      # App constants, timezone, BASE_URL
│   └── database.php                 # DB credentials
├── includes/
│   ├── init.php                     # Session bootstrap, helper functions
│   ├── header.php                   # HTML <head>, Bootstrap, navbar
│   ├── sidebar.php                  # Navigation menu (role-aware)
│   └── footer.php                   # Page close tags
├── classes/
│   ├── Database.php                 # PDO singleton wrapper
│   ├── BaseModel.php                # Abstract base (find, log, soft-delete)
│   ├── User.php                     # Auth, accounts, roles
│   ├── Product.php                  # Product catalog
│   ├── Branch.php                   # Multi-branch management
│   ├── Customer.php                 # Customer CRM
│   ├── Supplier.php                 # Supplier management
│   ├── Sale.php                     # Sales transactions, invoice generation
│   ├── Payment.php                  # Payment recording, FIFO application
│   ├── Stock.php                    # Inbound, adjustments, transfers
│   ├── Report.php                   # Analytics queries
│   └── Setting.php                  # Key-value settings store
├── pages/
│   ├── dashboard.php                # Main dashboard
│   ├── products.php                 # Product management (Admin/Manager)
│   ├── stock.php                    # Stock management
│   ├── sales.php                    # Sales & invoice history
│   ├── quotations.php               # Quotations & estimates
│   ├── customers.php                # Customer management
│   ├── khata.php                    # Customer ledger / account book
│   ├── payments.php                 # Payment tracking (Admin/Manager)
│   ├── installments.php             # Installment schedules
│   ├── expenses.php                 # Expense tracking
│   ├── branches.php                 # Branch management (Admin)
│   ├── suppliers.php                # Supplier management (Admin)
│   ├── reports.php                  # Reports & analytics (Admin/Manager)
│   ├── users.php                    # User management (Admin)
│   ├── notes.php                    # Internal notes / reminders
│   ├── backup.php                   # Database backup & restore (Admin)
│   ├── settings.php                 # App settings (Admin)
│   └── logout.php                   # Session destroy
├── api/                             # AJAX endpoint files
│   ├── _guard.php                   # Auth/method guard for all APIs
│   ├── export_db.php                # SQL dump download
│   ├── import_db.php                # SQL dump restore
│   └── ...                          # All other CRUD/query endpoints
├── assets/
│   ├── css/style.css                # Custom styles
│   └── js/
│       ├── app.js                   # Shared: Tom Select init, toast, ajaxPost
│       ├── dashboard.js
│       ├── products.js
│       ├── stock.js
│       ├── sales.js
│       ├── quotations.js
│       ├── customers.js
│       ├── khata.js
│       ├── payments.js
│       ├── installments.js
│       ├── expenses.js
│       ├── branches.js
│       ├── reports.js
│       ├── notes.js
│       └── users.js
├── sql/
│   ├── schema.sql                           # Full database schema + seed data
│   ├── migration_v2_branches.sql            # Add branch support
│   ├── migration_v4_stock_features.sql      # Add adjustments & transfers
│   ├── migration_v5_expenses.sql            # Add expenses module
│   ├── migration_v6_categories.sql          # Add product categories
│   ├── migration_v7_quotations.sql          # Add quotations module
│   ├── migration_v8_installments.sql        # Add installments module
│   ├── migration_v9_notes.sql               # Add notes module
│   ├── migration_v10_manager_role.sql       # Add manager role
│   └── fix_views_after_v6.sql              # Rebuild views after category migration
└── uploads/                         # File upload directory
```

---

## Installation

### Requirements

- PHP 8.1 or higher
- MySQL 8.0 or higher
- A web server: Apache (with mod_rewrite) or Nginx
- PHP extensions: `pdo_mysql`, `mbstring`, `session`

### Steps

**1. Clone the repository**

```bash
git clone https://github.com/arifbillahcse/real-state-shop-managment-web-application.git
cd real-state-shop-managment-web-application
```

**2. Configure the database**

Edit the database config file:

```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rod_cement_shop');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');
```

**3. Set up the database**

```bash
mysql -u root -p < sql/schema.sql
```

This creates the database, all tables, views, and seeds sample data including the default admin account.

**4. Configure Apache (if using .htaccess)**

Make sure `mod_rewrite` is enabled and `AllowOverride All` is set for the project directory.

**5. Set BASE_URL (if in a subdirectory)**

```php
// config/app.php — update if not running at web root:
define('BASE_URL', 'http://localhost/rod-cement-shop');
```

**6. Set directory permissions**

```bash
chmod 755 uploads/
```

**7. Open in browser**

Navigate to your configured URL. Log in with the default admin credentials.

---

## Database Setup

### Fresh Installation

Run the full schema (creates database, tables, views, and seed data):

```bash
mysql -u root -p < sql/schema.sql
```

### Upgrading an Existing Installation

Run migrations in order:

```bash
# v2 — Branch support
mysql -u root -p rod_cement_shop < sql/migration_v2_branches.sql

# v4 — Stock adjustments & transfers
mysql -u root -p rod_cement_shop < sql/migration_v4_stock_features.sql

# v5 — Expenses module
mysql -u root -p rod_cement_shop < sql/migration_v5_expenses.sql

# v6 — Product categories
mysql -u root -p rod_cement_shop < sql/migration_v6_categories.sql

# Fix views after v6 (required after v6)
mysql -u root -p rod_cement_shop < sql/fix_views_after_v6.sql

# v7 — Quotations module
mysql -u root -p rod_cement_shop < sql/migration_v7_quotations.sql

# v8 — Installments module
mysql -u root -p rod_cement_shop < sql/migration_v8_installments.sql

# v9 — Notes module
mysql -u root -p rod_cement_shop < sql/migration_v9_notes.sql

# v10 — Manager role
mysql -u root -p rod_cement_shop < sql/migration_v10_manager_role.sql
```

---

## Configuration

### `config/app.php`

| Constant | Default | Description |
|---|---|---|
| `APP_NAME` | `রড সিমেন্ট ম্যানেজমেন্ট` | Application name |
| `APP_VERSION` | `1.0.0` | Version string |
| `BASE_URL` | Auto-detected | Base URL (set manually for subdirectory installs) |
| `SESSION_LIFETIME` | `7200` | Session timeout in seconds (2 hours) |
| Timezone | `Asia/Dhaka` | PHP default timezone |

### `config/database.php`

| Constant | Description |
|---|---|
| `DB_HOST` | MySQL host (usually `localhost`) |
| `DB_NAME` | Database name (`rod_cement_shop`) |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password |
| `DB_CHARSET` | Character set (`utf8mb4`) |

### In-App Settings (Settings page)

| Setting | Description |
|---|---|
| Shop Name | Displayed on invoices, khata prints, and dashboard |
| Shop Address | Shown on printed invoices |
| Shop Phone | Shown on invoices and invoice footer |
| Shop Email | Contact email |
| Currency | Default: BDT |
| Invoice Prefix | Prefix for invoice numbers (default: `INV`) |

---

## User Roles

### Admin (অ্যাডমিন)

Full access to all pages and features:
- All pages including Products, Branches, Suppliers, Reports, Users, Backup, Settings
- Create, edit, and delete all records
- View all branches' data simultaneously
- Global stock view and branch comparison matrix

### Manager (ম্যানেজার)

Same access as Admin except:
- Cannot access User Management (`/pages/users.php`)
- Cannot access Backup & Restore (`/pages/backup.php`)
- Cannot access Settings (`/pages/settings.php`)

### Staff (স্টাফ)

Restricted, branch-scoped access:
- **Can view:** Dashboard (their branch only), Stock (their branch), Sales history
- **Cannot access:** Products, Payments, Branches, Reports, Users, Backup, Settings
- Dashboard shows only their branch's stats
- Stock shows only their assigned branch's inventory

---

## Module Guide

### Dashboard

The main overview page with key metrics at a glance.

**Admin/Manager view:**
- Today's sales count, revenue, and amount collected
- Today's payment collections
- Total outstanding customer dues
- Total stock valuation (cost basis of all inventory)
- Low stock product alerts (items at or below minimum threshold)
- 7-day sales trend bar chart
- 5 most recent completed sales

**Staff view:**
- Branch-scoped sales statistics
- Branch stock valuation
- Low stock alerts for their branch only
- Recent sales for their branch

---

### Products

**Path:** `/pages/products.php` — Admin/Manager

Manage the product catalog organized into categories.

**Each product has:**
- Name, category, size/brand
- Unit: `ton`, `bag`, `pcs`, `kg`, `liter`, `meter`, or `other` (অন্যান্য)
- Buy price and sell price
- Minimum stock alert level

**Safeguards:**
- Duplicate prevention (same name + category combination blocked)
- Cannot delete if product has stock entries or sales history

---

### Stock Management

**Path:** `/pages/stock.php`

**Admin/Manager Tabs:**

| Tab | Description |
|---|---|
| **বর্তমান স্টক** | Global stock table: all products with current quantity, min level, buy price, total inventory value |
| **ব্রাঞ্চ স্টক — তুলনা** | Comparison matrix: all products × all branches side-by-side |
| **ব্রাঞ্চ স্টক — আলাদা ব্রাঞ্চ** | Deep-dive for one branch: inbound, adjustments, transfers, sold, current stock |
| **ক্রয় ইতিহাস** | All stock purchases with supplier, branch, price |
| **সংশোধন ইতিহাস** | Audit log of all manual stock corrections |
| **ট্রান্সফার ইতিহাস** | Audit log of all inter-branch transfers |

**Action Buttons (Admin/Manager only):**

- **পণ্য কেনা (Stock In):** Record a purchase — product, quantity, price, supplier, branch, date.
- **স্টক সংশোধন (Adjustment):** Manual correction with direction (+/−), reason, optional note.
- **ব্রাঞ্চ ট্রান্সফার (Transfer):** Move stock between branches.

**Stock Formula:**

```
Current Stock = Inbound + Adjustments In − Adjustments Out + Transfers In − Transfers Out − Sold
```

---

### Sales

**Path:** `/pages/sales.php`

**Creating a New Sale (Admin/Manager):**

1. Select customer (or leave as Walk-in)
2. Select branch (required when branches exist)
3. Set date and payment method
4. Add line items — product dropdown with branch-specific stock; price auto-fills
5. Set discount and amount paid upfront
6. Submit — invoice is auto-numbered (`INV-YYYYMMDD-XXXX`)

**Payment Methods:**
- **নগদ (Cash)**
- **বাকি (Credit)** — records as outstanding due
- **মোবাইল ব্যাংকিং** — bKash, Nagad, etc.
- **চেক (Cheque)**

**Invoice Design:**
- Red gradient header with shop name
- Dashed tear-line divider with semicircle cutouts
- Invoice details + customer info side-by-side
- Red-header items table with alternating rows
- Color-coded totals: green for paid, red for outstanding due
- Diagonal watermark stamp in background
- Dark footer: "ধন্যবাদ আপনার কেনাকাটার জন্য"
- Background colors preserved when printing (`print-color-adjust: exact`)

---

### Quotations / Estimates

**Path:** `/pages/quotations.php`

Create estimates before finalizing a sale.

- Add line items with products and prices
- Quotations can be printed as A4 estimates with shop branding
- Active quotations can be edited or converted directly to a sale
- Converted quotations are marked as `converted`; cancelled ones as `cancelled`
- Print output includes full items table, totals, and optional notes

---

### Customers & Credit

**Path:** `/pages/customers.php` — Admin/Manager

- Full CRUD for customer records (name, phone, address)
- Each customer card shows: total purchases, amount paid, outstanding due
- Default "Walk-in Customer" is protected (cannot be edited or deleted)
- Customers with existing sales cannot be deleted

---

### Customer Ledger (খাতা)

**Path:** `/pages/khata.php`

A chronological account book (খাতা) for each customer.

- Select any customer to load their full transaction history
- Shows all sales and all payments in date order
- Summary panel: total purchased / total paid / current due
- **Print Button:** Opens a new window with a formatted A4 statement featuring:
  - Shop branding at top
  - Customer info box (name, phone, address)
  - Summary cards (purchase / paid / due)
  - Full transactions table with serial numbers
  - Footer with print date
  - Auto-triggers print dialog

---

### Payments

**Path:** `/pages/payments.php` — Admin/Manager

Record payments from customers:

- Select customer to see their outstanding balance
- Enter payment amount, method, optional reference number, date, and note
- Optionally link payment to a specific invoice, or leave unlinked for auto-application

**FIFO Auto-Application:**

When a payment is not linked to a specific invoice, it is automatically applied to the **oldest outstanding sales first**. Each sale's `paid_amount` and `due_amount` are updated until the payment is fully allocated.

---

### Installments

**Path:** `/pages/installments.php`

Manage multi-installment payment plans for large purchases.

- Create an installment plan linked to a sale or customer
- Define total amount, number of installments, and due dates
- Track each installment: pending / paid / overdue
- Mark individual installments as paid with payment date and note
- Overview shows: total, paid so far, remaining balance

---

### Expenses

**Path:** `/pages/expenses.php`

Track business overheads and operational costs.

- Record expenses by category (rent, salary, utilities, transport, other)
- Filter by date range and category
- Summary totals per category and overall
- Expense data feeds into the profit calculation in Reports

---

### Branches

**Path:** `/pages/branches.php` — Admin

Manage physical store/delivery locations:

- Create, edit, delete branches (name, address, phone)
- Each branch card shows: number of staff assigned, stock inbound entries, total sales count
- **Delete protection:** Cannot delete a branch that has stock or sales records

---

### Suppliers

**Path:** `/pages/suppliers.php` — Admin/Manager

Manage vendors and purchase sources:

- Create, edit, delete suppliers (name, phone, address)
- View total amount purchased from each supplier
- **Delete protection:** Cannot delete a supplier that has purchase history

---

### Reports

**Path:** `/pages/reports.php` — Admin/Manager

Date-range analytics with from/to date filters.

| Report | What It Shows |
|---|---|
| **Sales Summary** | Invoice count, subtotal, discount, revenue, collected, outstanding |
| **Daily Sales Chart** | Bar chart of daily sales volume for the selected period |
| **Top Products** | Top 10 by quantity sold and by revenue |
| **Purchase Summary** | Total stock purchased (quantity + cost) in the period |
| **Payment Collections** | Total received, broken down by payment method |
| **Stock Valuation** | Current inventory: cost value vs market (sell) value per product |
| **Customer Dues** | All customers with outstanding balance, sorted by amount owed |
| **Expense Summary** | Total expenses by category for the period |
| **Profit Estimate** | Revenue − Cost of Goods Sold − Expenses = Net Profit |

---

### User Management

**Path:** `/pages/users.php` — Admin only

Manage all system accounts:

- Create users: name, username, password, role (Admin / Manager / Staff)
- Staff must be assigned to a branch; Admin and Manager have no branch restriction
- Admin can reset any user's password
- Enable/disable accounts (disabled users cannot log in)

**Safeguards:**
- Cannot delete your own account
- Cannot disable or demote the last active admin
- Usernames must be unique

---

### Notes

**Path:** `/pages/notes.php`

Internal team notes and reminders:

- Create, edit, delete notes (title + body)
- Notes are visible to all logged-in users
- Useful for shift handover messages, reminders, and internal communication

---

### Backup & Restore

**Path:** `/pages/backup.php` — Admin only

Protect your data with full database backups.

**Export (Download SQL Dump):**
- Generates a complete `.sql` file with all table structures and data
- Includes `DROP TABLE IF EXISTS` + `CREATE TABLE` + batched `INSERT` statements
- Filename: `backup_dbname_YYYYMMDD_HHMMSS.sql`
- Download directly from browser

**Import (Restore from SQL Dump):**
- Upload a previously exported `.sql` file (max 50 MB)
- Executes all statements with `FOREIGN_KEY_CHECKS = 0`
- Reports total number of statements executed on success
- **Warning:** Restoring overwrites all current data. Always export first.

---

### Settings

**Path:** `/pages/settings.php` — Admin only

Configure shop identity used in invoices and throughout the app:

| Field | Used In |
|---|---|
| Shop Name | Invoice header, khata print header, browser tab title |
| Address | Invoice header |
| Phone | Invoice header and thank-you footer |
| Email | Contact information display |
| Currency | Price formatting site-wide |
| Invoice Prefix | Invoice number format (e.g., `INV-20260601-0001`) |

---

## Database Schema

### Tables

| Table | Purpose |
|---|---|
| `branches` | Branch/store locations |
| `users` | User accounts (admin/manager/staff), branch assignment |
| `product_categories` | Product category groupings |
| `products` | Product catalog, pricing, min stock |
| `suppliers` | Vendor contacts |
| `customers` | Buyer records |
| `sales` | Sale headers: invoice, customer, branch, totals, status |
| `sale_items` | Line items per sale (product, qty, price) |
| `quotations` | Quotation headers |
| `quotation_items` | Line items per quotation |
| `stock_inbound` | Stock purchase records per branch |
| `payments` | Payment receipts from customers |
| `stock_adjustments` | Manual stock corrections (+/−) with reason |
| `stock_transfers` | Branch-to-branch stock movements |
| `installments` | Installment plan headers |
| `installment_items` | Individual installment schedule entries |
| `expenses` | Business expense records |
| `expense_categories` | Expense category definitions |
| `notes` | Internal team notes |
| `settings` | Key-value app configuration |
| `activity_logs` | Full audit trail of all system actions |

### Views

| View | Formula |
|---|---|
| `vw_current_stock` | `inbound + adjustments − sold` (global, per product) |
| `vw_branch_stock` | `inbound + adjustments + transfers_in − transfers_out − sold` (per branch per product) |
| `vw_customer_dues` | Aggregated total due/paid/purchased per customer |

### Default Credentials

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `admin123` |
| Role | Admin |

> **Change the default password immediately after first login.**

---

## API Reference

All endpoints return JSON: `{ "success": true/false, "message": "...", ... }`

POST endpoints require an active session. Admin-only endpoints additionally verify the admin role.

### Products

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_products.php` | GET | All | List products (optional `?category_id=`) |
| `add_product.php` | POST | Admin/Manager | Create product |
| `update_product.php` | POST | Admin/Manager | Update product |
| `delete_product.php` | POST | Admin/Manager | Soft-delete product |
| `get_categories.php` | GET | All | List product categories |
| `add_category.php` | POST | Admin/Manager | Create category |
| `update_category.php` | POST | Admin/Manager | Update category |
| `delete_category.php` | POST | Admin/Manager | Delete category (blocked if has products) |

### Sales

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `create_sale.php` | POST | Admin/Manager | Create sale with line items + stock deduction |
| `get_sales.php` | GET | All | List sales (date, customer, branch, status filters) |
| `get_sale_detail.php` | GET | All | Full sale with items |
| `update_sale.php` | POST | Admin/Manager | Edit a completed sale |
| `cancel_sale.php` | POST | Admin/Manager | Cancel a completed sale |

### Quotations

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_quotations.php` | GET | Admin/Manager | List quotations |
| `create_quotation.php` | POST | Admin/Manager | Create quotation |
| `update_quotation.php` | POST | Admin/Manager | Edit quotation |
| `convert_quotation.php` | POST | Admin/Manager | Convert to sale |
| `cancel_quotation.php` | POST | Admin/Manager | Cancel quotation |

### Customers

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_customers.php` | GET | All | List customers with due summary |
| `add_customer.php` | POST | Admin/Manager | Create customer |
| `update_customer.php` | POST | Admin/Manager | Update customer |
| `delete_customer.php` | POST | Admin/Manager | Soft-delete customer |
| `get_customer_ledger.php` | GET | Admin/Manager | Full sales + payments ledger |

### Payments

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `add_payment.php` | POST | Admin/Manager | Record payment (FIFO or specific invoice) |
| `get_payments.php` | GET | Admin/Manager | Payment history |
| `get_outstanding_sales.php` | GET | Admin/Manager | Unpaid invoices for a customer |

### Installments

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_installments.php` | GET | Admin/Manager | List installment plans |
| `add_installment.php` | POST | Admin/Manager | Create installment plan |
| `pay_installment_item.php` | POST | Admin/Manager | Mark an instalment as paid |

### Expenses

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_expenses.php` | GET | Admin/Manager | List expenses (filterable) |
| `add_expense.php` | POST | Admin/Manager | Record expense |
| `update_expense.php` | POST | Admin/Manager | Edit expense |
| `delete_expense.php` | POST | Admin/Manager | Delete expense |

### Stock

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `add_stock_inbound.php` | POST | Admin/Manager | Record purchase |
| `update_stock_inbound.php` | POST | Admin/Manager | Edit purchase record |
| `delete_stock_inbound.php` | POST | Admin/Manager | Delete (blocked if stock would go negative) |
| `get_stock_inbound.php` | GET | Admin/Manager | Purchase history |
| `get_stock.php` | GET | All | Global stock levels |
| `get_branch_stock.php` | GET | All | Stock for one branch |
| `get_all_branch_stock.php` | GET | All | All branches × all products |
| `add_stock_adjustment.php` | POST | Admin/Manager | Manual +/− stock correction |
| `get_stock_adjustments.php` | GET | Admin/Manager | Adjustment history |
| `add_stock_transfer.php` | POST | Admin/Manager | Move stock between branches |
| `get_stock_transfers.php` | GET | Admin/Manager | Transfer history |

### Branches

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_branches.php` | GET | All | List branches with stats |
| `add_branch.php` | POST | Admin | Create branch |
| `update_branch.php` | POST | Admin | Update branch |
| `delete_branch.php` | POST | Admin | Delete (blocked if has stock/sales) |

### Suppliers

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_suppliers.php` | GET | Admin/Manager | List suppliers |
| `add_supplier.php` | POST | Admin/Manager | Create supplier |
| `update_supplier.php` | POST | Admin/Manager | Update supplier |
| `delete_supplier.php` | POST | Admin/Manager | Delete (blocked if has purchase history) |

### Users

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_users.php` | GET | Admin | List users with branch info |
| `add_user.php` | POST | Admin | Create user |
| `update_user.php` | POST | Admin | Update name/role/branch |
| `toggle_user.php` | POST | Admin | Enable/disable account |
| `reset_password.php` | POST | Admin | Admin password reset |

### Notes

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_notes.php` | GET | All | List notes |
| `add_note.php` | POST | All | Create note |
| `update_note.php` | POST | All | Edit own note |
| `delete_note.php` | POST | All | Delete own note |

### Backup

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `export_db.php` | GET | Admin | Download full SQL dump |
| `import_db.php` | POST | Admin | Restore from uploaded SQL file |

### Reports & Settings

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_report.php` | GET | Admin/Manager | Analytics data (type + date range) |
| `save_settings.php` | POST | Admin | Save app settings |

---

## Security

| Measure | Implementation |
|---|---|
| **Password hashing** | `password_hash()` with `PASSWORD_BCRYPT`, cost 12 |
| **SQL injection prevention** | PDO prepared statements used throughout all queries |
| **XSS prevention** | All output passed through `e()` → `htmlspecialchars(ENT_QUOTES)` |
| **Session security** | Session ID regenerated every 5 minutes; strict cookie parameters |
| **Access control** | `requireLogin()` on every page; `requireAdmin()` on admin pages; `requireAdminApi()` on admin APIs |
| **Safe deletes** | All deletes are soft (set `is_active = 0`) except stock inbound |
| **Delete protection** | Referential integrity enforced in PHP before any delete |
| **Error exposure** | DB errors logged to PHP error log only — never exposed in API responses |
| **Last admin guard** | Cannot disable or demote the last active admin account |
| **Backup access** | Export/import endpoints restricted to Admin role only |

---

## Migrations

Run migrations in order on an existing installation.

### v2 — Branch Support

Adds the `branches` table and `branch_id` columns to `users`, `stock_inbound`, and `sales`.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v2_branches.sql
```

### v4 — Stock Adjustments & Transfers

Adds `stock_adjustments` and `stock_transfers` tables. Updates views to include adjustments and transfers in the stock formula.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v4_stock_features.sql
```

### v5 — Expenses

Adds `expenses` and `expense_categories` tables.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v5_expenses.sql
```

### v6 — Product Categories

Adds `product_categories` table and links products to categories. Removes the `type` column from products.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v6_categories.sql
mysql -u root -p rod_cement_shop < sql/fix_views_after_v6.sql
```

### v7 — Quotations

Adds `quotations` and `quotation_items` tables.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v7_quotations.sql
```

### v8 — Installments

Adds `installments` and `installment_items` tables.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v8_installments.sql
```

### v9 — Notes

Adds `notes` table.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v9_notes.sql
```

### v10 — Manager Role

Adds the `manager` role value to the `users.role` ENUM. Existing admin accounts are unaffected.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v10_manager_role.sql
```

---

## Helper Functions

Defined in `includes/init.php`, available on every page:

| Function | Returns | Description |
|---|---|---|
| `redirect($url)` | void | Header redirect and exit |
| `isLoggedIn()` | bool | Check if user session is active |
| `requireLogin()` | void | Redirect to login if not authenticated |
| `requireAdmin()` | void | Redirect to dashboard if not admin |
| `isStaff()` | bool | True if current user role is `staff` |
| `isManager()` | bool | True if current user role is `manager` |
| `getSessionBranchId()` | `?int` | Returns branch_id from session (null for admin/manager) |
| `jsonResponse($ok, $msg, $data)` | void | Output JSON response and exit |
| `e($string)` | string | HTML-escape string for safe output |
| `money($float)` | string | Format as `1,234.00 ৳` |
| `today()` | string | Return current date as `Y-m-d` |

---

*Built with PHP 8.4 · MySQL 8 · Bootstrap 5.3 · Chart.js 4.4 · Tom Select*
