# Front-end Demo (GitHub Pages)

A static, database-free build of the shop management system, so the interface
can be explored live without PHP or MySQL.

The PHP application in the repository root is unchanged — this folder is a
separate front-end prototype that reuses its markup, CSS and page scripts.

## How it works

The PHP app is already AJAX-driven: pages call `api/*.php` and every endpoint
returns the same `{ success, message, ... }` envelope. The demo keeps that
contract and swaps only the transport.

```
page script (sales.js, stock.js — copied unchanged)
        │  fetch('./api/get_sales.php')
        ▼
mockApi.js        intercepts window.fetch for /api/* URLs
        ▼
apiRouter.js      dispatch + the guards from api/_guard.php
   api/*.js       one handler file per domain, mirroring classes/
        ▼
db.js             localStorage, with auto-increment ids
        ▲
seed.js           demo data for all 38 tables, dated relative to today
compute.js        ports the four SQL views
```

Because the interception happens at the `fetch` layer, page scripts can be
copied byte-for-byte from `../assets/js/`.

## Files

| File | Replaces |
|---|---|
| `assets/js/demo/seed.js` | `sql/install.sql` sample data |
| `assets/js/demo/db.js` | `classes/Database.php` |
| `assets/js/demo/compute.js` | `vw_current_stock`, `vw_branch_stock`, `vw_customer_dues`, `vw_customer_ledger_balance` |
| `assets/js/demo/auth.js` | the PHP session and role helpers in `includes/init.php` |
| `assets/js/demo/apiRouter.js` | `api/_guard.php` |
| `assets/js/demo/api/*.js` | `api/*.php`, one file per domain |
| `assets/js/demo/mockApi.js` | Apache + PHP request handling |
| `assets/js/demo/layout.js` | `includes/header.php`, `includes/sidebar.php` |

## Demo accounts

| Role | Username | Password | Scope |
|---|---|---|---|
| Admin | `admin` | `admin123` | everything |
| Manager | `manager` | `manager123` | all branches, no user/settings/backup |
| Assistant Manager | `asst` | `asst123` | Mirpur branch only |
| Staff | `staff` | `staff123` | Mirpur branch, sales and stock only |

The credentials are printed on the login screen on purpose — nothing here
protects real data. The navbar menu also switches role in place, so the four
sidebars can be compared without logging out.

Branch scoping is real: an assistant manager and a staff member see only their
own branch's sales and stock, and only the menu items `includes/sidebar.php`
grants their role.

## Data

Everything lives in `localStorage` under `niharika_demo_v3` and survives a
refresh. It is per-browser and never leaves the visitor's machine. Use
**ডেমো ডেটা রিসেট** in the navbar menu to restore the seed.

Sale, payment and stock dates are generated relative to the day the demo is
opened, so the dashboard chart and "today's sales" are never empty.

## Running locally

```bash
cd demo
python3 -m http.server 8000
```

Then open <http://127.0.0.1:8000>.

## Deploying

Two options in Settings → Pages:

- Source folder `/demo` — the app is served at the Pages root URL.
- Source folder `/ (root)` — the app is served under `/demo/`, and the Pages
  root shows the repository README instead.

## Status

Ported against `main` (25 pages, 125 endpoints, 38 tables).

- [x] Foundation — storage, views, roles, branch scoping, layout, login, dashboard
- [ ] Products, categories, stock, alert centre
- [ ] Transfers, branches
- [ ] Sales and returns
- [ ] Customers, customer account, khata, payments, installments
- [ ] Expenses, notes, daily statement, staff panel, quotations
- [ ] Reports, users, settings, suppliers, backup, migrate

Menu entries for pages that are not built yet are shown greyed out with an
hourglass, so the sidebar always matches the real application.

Adding a page means copying its PHP markup to HTML, copying its script
unchanged, and registering its endpoints in `assets/js/demo/api/*.js` with
`ApiRouter.register({ ... })`, then adding the file name to `Layout.built`.

Passwords are stored as plain text here because there is no server to verify a
bcrypt hash against. The PHP app hashes them properly in `classes/User.php`.
