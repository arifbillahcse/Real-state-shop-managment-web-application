# রড সিমেন্ট ম্যানেজমেন্ট — Rod & Cement Shop Management System

A full-featured, multi-branch retail shop management system built for **steel rod and cement** businesses. Manage inventory, sales, customer credit, supplier purchases, branch operations, and financial reports — all in one place, with a Bengali-language interface.

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
  - [Customers & Credit](#customers--credit)
  - [Payments](#payments)
  - [Branches](#branches)
  - [Suppliers](#suppliers)
  - [Reports](#reports)
  - [User Management](#user-management)
  - [Settings](#settings)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Security](#security)
- [Migrations](#migrations)

---

## Overview

This system was built for a building materials business that operates from a **central warehouse** with **multiple delivery branches**. A single manager handles all orders; branch staff handle delivery and local stock. The system tracks:

- What stock is in each branch
- Which sales came from which branch
- How much each customer owes
- Profit/loss across a date range

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
| **Customer credit (বাকি)** | Track due amounts per customer, full payment ledger |
| **Payment collection** | Record payments against specific invoices or apply FIFO across all dues |
| **Supplier tracking** | Link stock purchases to suppliers, view total spend |
| **Reports & analytics** | Date-range sales summaries, top products, profit estimates, stock valuation |
| **User accounts** | Admin and Staff roles with branch-scoped access for staff |
| **Invoice printing** | Printable invoice with gradient header, tear-line divider, paid/due stamp |
| **Low stock alerts** | Dashboard and stock page warn when stock falls below minimum threshold |
| **Audit log** | Every create/update/delete action is logged with user and timestamp |

### Invoice Design
- Gradient red header with shop name and contact
- Tear-line dashed divider (receipt style)
- Two-column layout: invoice details + customer info
- Alternating-row items table with red header
- Color-coded totals block (green = paid, red = due)
- Diagonal watermark (বাকি আছে / পরিশোধিত / বাতিল)
- Dark footer with thank-you message
- Fully printable (opens print dialog automatically)

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.4 (OOP, no framework) |
| **Database** | MySQL 8 (PDO, prepared statements) |
| **Frontend** | Bootstrap 5.3 + Bootstrap Icons |
| **Charts** | Chart.js 4.4 |
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
│   ├── products.php                 # Product management (Admin)
│   ├── stock.php                    # Stock management
│   ├── sales.php                    # Sales & invoice history
│   ├── customers.php                # Customer management (Admin)
│   ├── payments.php                 # Payment tracking (Admin)
│   ├── branches.php                 # Branch management (Admin)
│   ├── suppliers.php                # Supplier management (Admin)
│   ├── reports.php                  # Reports & analytics (Admin)
│   ├── users.php                    # User management (Admin)
│   ├── settings.php                 # App settings (Admin)
│   └── logout.php                   # Session destroy
├── api/                             # 43 AJAX endpoint files
│   └── _guard.php                   # Auth/method guard for all APIs
├── assets/
│   ├── css/style.css                # Custom styles
│   └── js/
│       ├── app.js                   # Shared: toast, ajaxPost
│       ├── dashboard.js
│       ├── products.js
│       ├── stock.js
│       ├── sales.js
│       ├── customers.js
│       ├── payments.js
│       ├── branches.js
│       ├── reports.js
│       └── users.js
├── sql/
│   ├── schema.sql                   # Full database schema + seed data
│   ├── migration_v2_branches.sql    # Add branch support to existing DB
│   └── migration_v4_stock_features.sql  # Add adjustments & transfers
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

### Upgrading from v1 (no branch support)

```bash
mysql -u root -p rod_cement_shop < sql/migration_v2_branches.sql
```

### Upgrading from v2 (no stock adjustment/transfer)

```bash
mysql -u root -p rod_cement_shop < sql/migration_v4_stock_features.sql
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
| Shop Name | Displayed on invoices and dashboard |
| Shop Address | Shown on printed invoices |
| Shop Phone | Shown on invoices and invoice footer |
| Shop Email | Contact email |
| Currency | Default: BDT |
| Invoice Prefix | Prefix for invoice numbers (default: `INV`) |

---

## User Roles

### Admin (ম্যানেজার)

Full access to all pages and features:
- All pages including Products, Branches, Suppliers, Reports, Users, Settings
- Create, edit, and delete all records
- View all branches' data simultaneously
- Global stock view and branch comparison matrix

### Staff (স্টাফ)

Restricted, branch-scoped access:
- **Can view:** Dashboard (their branch only), Stock (their branch), Sales history, Customer list
- **Cannot access:** Create new orders, Products, Payments, Branches, Reports, Users, Settings
- Dashboard shows only their branch's stats
- Stock shows only their assigned branch's inventory

---

## Module Guide

### Dashboard

The main overview page with key metrics at a glance.

**Admin view:**
- Today's sales count, revenue, and amount collected
- Total stock valuation (cost basis of all inventory)
- Low stock product alerts (items at or below minimum threshold)
- 7-day sales trend chart
- 5 most recent sales with invoice number and status
- Total outstanding customer dues
- Today's payment collections

**Staff view:**
- Branch-scoped sales statistics
- Branch stock summary
- Low stock alerts for their branch only
- Recent sales for their branch

---

### Products

**Path:** `/pages/products.php` — Admin only

Manage the product catalog organized into two types:

- **রড (Rod):** Steel rods identified by size (e.g., 8mm, 10mm, 12mm, 16mm)
- **সিমেন্ট (Cement):** Cement bags identified by brand (e.g., LAFARGE, HOLCIM, HEIDELBERG, SHAH)

**Each product has:**
- Name, type (rod/cement), size or brand
- Unit: `ton`, `bag`, or `pcs`
- Buy price and sell price
- Minimum stock alert level

**Safeguards:**
- Duplicate prevention (same type + name + size/brand combination blocked)
- Cannot delete if product has stock entries or sales history (soft-delete only)

---

### Stock Management

**Path:** `/pages/stock.php`

**Admin Tabs:**

| Tab | Description |
|---|---|
| **বর্তমান স্টক** | Global stock table: all products with current quantity, min level, buy price, total inventory value |
| **ব্রাঞ্চ স্টক — তুলনা** | Comparison matrix: all products × all branches side-by-side. Summary cards show total value and low-stock count per branch |
| **ব্রাঞ্চ স্টক — আলাদা ব্রাঞ্চ** | Deep-dive for one branch: 10 columns (total inbound, adjustments, transfer-in, transfer-out, sold, current stock, status) |
| **ক্রয় ইতিহাস** | All stock purchases with supplier, branch, price, editable |
| **সংশোধন ইতিহাস** | Audit log of all manual stock corrections |
| **ট্রান্সফার ইতিহাস** | Audit log of all inter-branch transfers |

**Action Buttons (Admin only):**

- **পণ্য কেনা (Stock In):** Record a purchase — product, quantity, price, supplier, branch, date. Live total cost preview.
- **স্টক সংশোধন (Adjustment):** Manual correction with direction (+/−), reason (damage / count correction / return / other), optional branch scope, and note.
- **ব্রাঞ্চ ট্রান্সফার (Transfer):** Move stock between branches. Shows live available stock at source. Blocked if insufficient.

**Stock Formula:**

```
Current Stock = Inbound + Adjustments + Transfers In − Transfers Out − Sold
```

**Safeguards:**
- Deleting inbound is blocked if it would result in negative stock
- Transfer is blocked if source branch has insufficient stock
- Negative adjustment is blocked if it would make stock go below zero

---

### Sales

**Path:** `/pages/sales.php`

**Creating a New Sale (Admin only):**

1. Select customer (or leave as Walk-in)
2. Select branch (required when branches exist)
3. Set date and payment method
4. Add line items — product dropdown shows branch-specific stock; price auto-fills from catalog
5. Set discount and amount paid upfront
6. Submit — invoice is auto-numbered (`INV-YYYYMMDD-XXXX`)

**Payment Methods:**
- **নগদ (Cash)**
- **বাকি (Credit)** — records as outstanding due
- **মোবাইল ব্যাংকিং** — bKash, Nagad, etc.
- **চেক (Cheque)**

**Sales History (visible to all users):**

Filterable by: date range, customer, branch, status (completed/cancelled).

Each row: Invoice #, Date, Customer, Branch, Item count, Total, Paid, Due, Status, View/Cancel actions.

**Invoice (Premium Receipt design):**
- Red gradient header with shop name
- Dashed tear-line divider with semicircle cutouts
- Invoice details + customer info side-by-side
- Red-header items table with alternating rows
- Color-coded totals: green for paid, red for outstanding due
- Diagonal watermark stamp in background
- Dark footer: "ধন্যবাদ আপনার কেনাকাটার জন্য"
- One-click printing via browser

---

### Customers & Credit

**Path:** `/pages/customers.php` — Admin only

- Full CRUD for customer records (name, phone, address)
- Each customer card shows: total purchases, amount paid, outstanding due
- Default "Walk-in Customer" is protected (cannot be edited or deleted)
- Customers with existing sales cannot be deleted

**Customer Ledger:**

A chronological account statement showing:
- All sales (date, invoice number, amount)
- All payments received (date, amount, method)
- Running balance after each transaction
- Summary totals: purchased / paid / due

---

### Payments

**Path:** `/pages/payments.php` — Admin only

Record payments from customers:

- Select customer to see their outstanding balance
- Enter payment amount, method, optional reference number, date, and note
- Optionally link payment to a specific invoice, or leave unlinked for auto-application

**FIFO Auto-Application:**

When a payment is not linked to a specific invoice, it is automatically applied to the **oldest outstanding sales first** (First In, First Out). Each sale's `paid_amount` and `due_amount` are updated until the payment is fully allocated.

**Payment History:**

Full list of all payments with: date, customer, linked invoice (if any), amount, payment method, recorded by.

---

### Branches

**Path:** `/pages/branches.php` — Admin only

Manage physical store/delivery locations:

- Create, edit, delete branches (name, address, phone)
- Each branch card shows: number of staff assigned, stock inbound entries, and total sales count
- Staff users are assigned to exactly one branch; admins have no branch restriction
- **Delete protection:** Cannot delete a branch that has stock inbound records or sales

---

### Suppliers

**Path:** `/pages/suppliers.php` — Admin only

Manage vendors and purchase sources:

- Create, edit, delete suppliers (name, phone, address)
- View total amount purchased from each supplier (calculated from stock inbound records)
- **Delete protection:** Cannot delete a supplier that has purchase history

---

### Reports

**Path:** `/pages/reports.php` — Admin only

Date-range analytics with from/to date filters.

| Report | What It Shows |
|---|---|
| **Sales Summary** | Invoice count, subtotal, discount, revenue, collected, outstanding |
| **Daily Sales Chart** | Bar chart of daily sales volume for the selected period |
| **Top Products** | Top 10 by quantity sold and by revenue |
| **Rod vs Cement** | Revenue and count breakdown by product type |
| **Purchase Summary** | Total stock purchased (quantity + cost) in the period |
| **Payment Collections** | Total received, broken down by payment method |
| **Stock Valuation** | Current inventory: cost value vs market (sell) value per product |
| **Customer Dues** | All customers with outstanding balance, sorted by amount owed |
| **Profit Estimate** | Revenue − Cost of Goods Sold = Gross Profit for the period |

---

### User Management

**Path:** `/pages/users.php` — Admin only

Manage all system accounts:

- Create users: name, username, password, role (Admin or Staff)
- Staff must be assigned to a branch; Admins have no branch
- Admin can reset any user's password
- Enable/disable accounts (disabled users cannot log in)

**Safeguards:**
- Cannot delete your own account
- Cannot disable or demote the last active admin
- Usernames must be unique

---

### Settings

**Path:** `/pages/settings.php` — Admin only

Configure shop identity used in invoices and throughout the app:

| Field | Used In |
|---|---|
| Shop Name | Invoice header, browser tab title |
| Address | Invoice header |
| Phone | Invoice header and thank-you footer |
| Email | Contact information display |
| Currency | Price formatting site-wide |
| Invoice Prefix | Invoice number format (e.g., `INV-20260531-0001`) |

---

## Database Schema

### Tables

| Table | Purpose |
|---|---|
| `branches` | Branch/store locations |
| `users` | User accounts (admin/staff), branch assignment |
| `products` | Product catalog (rod/cement), pricing, min stock |
| `suppliers` | Vendor contacts |
| `customers` | Buyer records |
| `sales` | Sale headers: invoice, customer, branch, totals, status |
| `sale_items` | Line items per sale (product, qty, price) |
| `stock_inbound` | Stock purchase records per branch |
| `payments` | Payment receipts from customers |
| `stock_adjustments` | Manual stock corrections (+/−) with reason |
| `stock_transfers` | Branch-to-branch stock movements |
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
| `get_products.php` | GET | All | List products (optional `?type=rod\|cement`) |
| `add_product.php` | POST | Admin | Create product |
| `update_product.php` | POST | Admin | Update product |
| `delete_product.php` | POST | Admin | Soft-delete product |

### Sales

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `create_sale.php` | POST | Admin | Create sale with line items + stock deduction |
| `get_sales.php` | GET | All | List sales (date, customer, branch, status filters) |
| `get_sale_detail.php` | GET | All | Full sale with items |
| `cancel_sale.php` | POST | Admin | Cancel a completed sale |

### Customers

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_customers.php` | GET | All | List customers with due summary |
| `add_customer.php` | POST | Admin | Create customer |
| `update_customer.php` | POST | Admin | Update customer |
| `delete_customer.php` | POST | Admin | Soft-delete customer |
| `get_customer_ledger.php` | GET | Admin | Full sales + payments ledger |

### Payments

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `add_payment.php` | POST | Admin | Record payment (FIFO or specific invoice) |
| `get_payments.php` | GET | Admin | Payment history |
| `get_outstanding_sales.php` | GET | Admin | Unpaid invoices for a customer |

### Stock

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `add_stock_inbound.php` | POST | Admin | Record purchase |
| `update_stock_inbound.php` | POST | Admin | Edit purchase record |
| `delete_stock_inbound.php` | POST | Admin | Delete (blocked if stock would go negative) |
| `get_stock_inbound.php` | GET | Admin | Purchase history |
| `get_stock.php` | GET | All | Global stock levels |
| `get_branch_stock.php` | GET | All | Stock for one branch |
| `get_all_branch_stock.php` | GET | All | All branches × all products |
| `add_stock_adjustment.php` | POST | Admin | Manual +/− stock correction |
| `get_stock_adjustments.php` | GET | Admin | Adjustment history |
| `add_stock_transfer.php` | POST | Admin | Move stock between branches |
| `get_stock_transfers.php` | GET | Admin | Transfer history |

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
| `get_suppliers.php` | GET | Admin | List suppliers |
| `add_supplier.php` | POST | Admin | Create supplier |
| `update_supplier.php` | POST | Admin | Update supplier |
| `delete_supplier.php` | POST | Admin | Delete (blocked if has purchase history) |

### Users

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_users.php` | GET | Admin | List users with branch info |
| `add_user.php` | POST | Admin | Create user |
| `update_user.php` | POST | Admin | Update name/role/branch |
| `toggle_user.php` | POST | Admin | Enable/disable account |
| `reset_password.php` | POST | Admin | Admin password reset |

### Reports & Settings

| Endpoint | Method | Access | Description |
|---|---|---|---|
| `get_report.php` | GET | Admin | Analytics data (type + date range) |
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

---

## Migrations

Run migrations in order on an existing installation.

### v2 — Branch Support

Adds the `branches` table and `branch_id` columns to `users`, `stock_inbound`, and `sales`. Creates the `vw_branch_stock` view.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v2_branches.sql
```

### v4 — Stock Adjustments & Transfers

Adds `stock_adjustments` and `stock_transfers` tables. Updates `vw_current_stock` and `vw_branch_stock` to include adjustments and transfers in the stock formula.

```bash
mysql -u root -p rod_cement_shop < sql/migration_v4_stock_features.sql
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
| `getSessionBranchId()` | `?int` | Returns branch_id from session (null for admin) |
| `jsonResponse($ok, $msg, $data)` | void | Output JSON response and exit |
| `e($string)` | string | HTML-escape string for safe output |
| `money($float)` | string | Format as `1,234.00 ৳` |
| `today()` | string | Return current date as `Y-m-d` |

---

*Built with PHP 8.4 · MySQL 8 · Bootstrap 5.3 · Chart.js 4.4*
