# AGENTS.md

## What this is

Manufacturing ERP system (China import business). Plain PHP + MySQLi, no framework. Runs on XAMPP (`localhost`). Database: `china_import`.

## Stack

- **PHP** (procedural, no Composer, no autoloader)
- **MySQLi** with prepared statements — helper functions in `includes/database.php`
- **Bootstrap 5** + **jQuery 3.7** + **Font Awesome 6** + **DataTables 1.13** (all via CDN, loaded per-page)
- **No build step, no npm, no linting, no tests**

## Architecture

```
index.php              ← Dashboard (entry point after login)
login.php / logout.php ← Auth (plain-text passwords by design)
includes/              ← Shared PHP: database, header, sidebar, footer, print helpers
pages/                 ← All feature pages (flat structure, no subdirs)
```

- Every page includes `includes/database.php` first (session + DB + helpers).
- Layout: `header.php` + `sidebar.php` + page content + `footer.php`.
- `sidebar.php` auto-detects root vs `pages/` inclusion via `$_SERVER['PHP_SELF']`; uses `$p`/`$r` for link prefix.
- `print_*.php` pages are standalone (no header/sidebar), use A4 print layout.
- `client_config.php` defines `CLIENT_NAME`, `CLIENT_NAME_SHORT`, `CLIENT_PERSON`, `CLIENT_ADDRESS`, `CLIENT_PHONE`. **`CLIENT_REG` is NOT defined** — used in `print_header.php` but will produce a PHP notice.

## Key conventions

- **No framework patterns.** Each page is standalone with inline SQL at top, then HTML.
- **Database helpers:** `getRow()`, `getRows()`, `insertData()`, `modifyData()`, `getExchangeRate()`, `generateCode()`, `formatDate()`. Always use these.
- **Currency:** PKR for local, CNY for Chinese imports. `chinese_suppliers` uses `current_balance` (CNY).
- **Two supplier systems:** `local_suppliers` (PKR) and `chinese_suppliers` (CNY). Never mix queries.
- **Payment methods for new forms: only `cash` and `bank` (lowercase).** Never use `bank_transfer`, `cheque`, `online`, `telegraphic_transfer` in new forms. WHERE clauses for old data use `IN ('bank', 'bank_transfer', 'cheque')` for backward compat.
- **No banks table.** Cash/Bank selection only — no dropdown for bank name. Payments appear in Cash Book/Bank Book automatically.
- **All payments insert into `accounts`** (General Ledger). `account_type` = `'Cash'` for cash, `'Bank'` for bank. Get last balance first: `SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1`. Deleting a payment must also delete its `accounts` entry.
- **Supplier/customer `current_balance` must be updated on payment.** `local_suppliers`/`chinese_suppliers`: decrease on payment, restore on delete. `customers`: decrease on receipt, restore on delete.
- **Column naming:** `customer_receipts` uses `payment_method`, `supplier_payments`/`chinese_supplier_payments` use `payment_type`, `expenses` uses `paid_by`.
- **`parties` table may not exist.** Guard with `SHOW TABLES LIKE` pattern (see `index.php:25-29`).
- **`$pageTitle` variable** controls top navbar heading — set before including `sidebar.php`.
- **Flash messages:** `setFlash($msg, $type)` / `getFlash()` from `database.php`.
- **Auth:** `requireLogin()` at top of every protected page. Passwords stored as plain text (intentional).
- **`generateCode()`** uses `rand()` — not crypto-secure, but only for display codes.

## Sidebar gotcha

`sidebar.php` has hardcoded page arrays (`$localPurchases`, `$importPurchases`, etc.) for active-state detection. If you add a new page, add it to the relevant array or the nav highlight won't work. Avoid variable names that shadow these arrays (they collide with query result variables).

## DataTables pattern

Many pages initialize jQuery DataTables via CDN loaded in the page body (not head):
```php
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>$('#tableId').DataTable({ pageLength:25, order:[[0,'desc']] });</script>
```

## Offline timer quirk

`includes/database.php` has a `cache/_.tmp` file that tracks time since first page load. If > 300 days pass, the DB connection switches to `Diesel_Db_Software` (effectively a trial-expiry mechanism). This can cause confusing "table not found" errors on stale installs.

## Print pages

Print pages (`accounts_print.php`, `cashbook_print.php`, `bankbook_print.php`, `import_purchase_print.php`, etc.) are standalone — they do NOT include `header.php`/`sidebar.php`. They include `print_branding.php` and/or `print_header.php` for company branding, and use A4 print CSS. Some use `html2pdf.js` from CDN for PDF download.
