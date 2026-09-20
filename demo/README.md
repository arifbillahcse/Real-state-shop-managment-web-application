# Front-end Demo (GitHub Pages)

A static, database-free version of the Rod & Cement Shop Management System,
built so the interface can be explored live without PHP or MySQL.

The PHP application in the repository root is unchanged — this folder is a
separate front-end prototype that reuses its markup, CSS and page scripts.

## How it works

The PHP app is already AJAX-driven: every page calls `api/*.php` and every
endpoint returns the same `{ success, message, data }` JSON. The demo keeps
that contract and swaps only the transport.

```
page script (customers.js, unchanged)
        │  fetch('./api/get_customers.php')
        ▼
mockApi.js        intercepts window.fetch for /api/* URLs
        ▼
apiRouter.js      one JS handler per PHP endpoint
        ▼
db.js             localStorage, with auto-increment ids
        ▲
seed.js           demo data, generated relative to today
compute.js        ports the SQL views (current stock, customer dues)
```

Because the interception happens at the `fetch` layer, the page scripts are
byte-for-byte copies of the originals in `../assets/js/`.

## Files

| File | Replaces |
|---|---|
| `assets/js/demo/seed.js` | `sql/schema.sql` sample data |
| `assets/js/demo/db.js` | `classes/Database.php` |
| `assets/js/demo/compute.js` | `vw_current_stock`, `vw_customer_dues` |
| `assets/js/demo/apiRouter.js` | `api/_guard.php` (login + admin checks) |
| `assets/js/demo/api/*.js` | `api/*.php`, one file per domain |
| `assets/js/demo/mockApi.js` | Apache + PHP request handling |
| `assets/js/demo/auth.js` | PHP session in `includes/init.php` |
| `assets/js/demo/layout.js` | `includes/header.php`, `includes/sidebar.php` |

## Demo accounts

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Staff | `staff` | `staff123` |

These are printed on the login screen on purpose. Nothing here protects real
data — it is a public prototype. The navbar menu also switches role in place,
so the Admin and Staff sidebars can be compared without logging out.

## Data

All records live in `localStorage` under `rcshop_demo_v1` and survive a
refresh. They are per-browser and never leave the visitor's machine. Use
**ডেমো ডেটা রিসেট** in the navbar menu to restore the seed.

Sale and payment dates are generated relative to the day the demo is opened,
so the dashboard chart and "today's sales" are always populated.

## Running locally

Any static server works:

```bash
cd demo
python3 -m http.server 8000
```

Then open <http://127.0.0.1:8000>.

Opening `index.html` directly from the filesystem also works, because the seed
data is a JavaScript file rather than a JSON file fetched over HTTP.

## Deploying

Two options in Settings → Pages:

- Source folder `/demo` — the app is served at the Pages root URL.
- Source folder `/ (root)` — the app is served under `/demo/`, and the Pages
  root shows the repository README instead.

Either works; the root README links to the second form.

## Status

- [x] Phase 1 — foundation, login, dashboard, customers
- [x] Phase 2 — products, suppliers, users
- [x] Phase 3 — stock
- [x] Phase 4 — sales
- [x] Phase 5 — payments / khata
- [x] Phase 6 — reports
- [x] Phase 7 — settings page, screenshots, README

Adding a page means copying its PHP markup to HTML, copying its script
unchanged, and registering its endpoints in a new `assets/js/demo/api/*.js`
with `ApiRouter.register({ ... })`.

`assets/js/products.js` and `assets/js/stock.js` are the page scripts that
needed changing: `pages/products.php` and `pages/stock.php` rendered their
tables — and, for stock, the low-stock banner and the modal's product and
supplier dropdowns — in PHP. The demo versions fetch that data and render it
before binding the row buttons. Every other page script is a byte-for-byte
copy.

`pages/payments.php` rendered its summary cards, due list and three customer
dropdowns in PHP; the demo builds them in the page and re-renders them on every
tab switch, so a recorded payment shows up without a reload.

Passwords are stored as plain text here because there is no server to verify
a bcrypt hash against. The PHP app hashes them properly in `classes/User.php`.
