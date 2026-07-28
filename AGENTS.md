# AGENTS.md

## What this is

Manufacturing ERP system (China import business). Plain PHP + MySQLi, no framework. Runs on XAMPP (`localhost`). Database: `china_import`.

## Stack

- **PHP** (procedural, no Composer, no autoloader)
- **MySQLi** with prepared statements — helper functions in `includes/database.php`
- **Bootstrap 5** + **jQuery 3.7** + **Font Awesome 6** (all via CDN)
- **No build step, no npm, no linting, no tests**

## Architecture

```
index.php              ← Dashboard (entry point after login)
login.php / logout.php ← Auth (plain-text passwords by design)
includes/              ← Shared PHP: database, header, sidebar, footer
pages/                 ← All feature pages (76 files, flat structure)
```

- Every page includes `includes/database.php` first (session + DB + helpers).
- Page layout = `header.php` + `sidebar.php` + page content + `footer.php`.
- `sidebar.php` auto-detects whether it's included from root or `pages/` and adjusts link paths via `$p` / `$r` variables.

## Key conventions

- **No framework patterns.** Each page is a standalone PHP file with inline SQL at the top, then HTML.
- **Database helper functions:** `getRow()`, `getRows()`, `insertData()`, `modifyData()`. Always use these — they handle prepared statements.
- **Currency:** PKR for local, CNY for Chinese imports. `chinese_suppliers` table uses `balance_cny` column (not `balance`). See `index.php:20`.
- **Two supplier systems:** `local_suppliers` (PKR) and `chinese_suppliers` (CNY). Never mix their queries.
- **`parties` table may not exist.** Code defensively around it (`SHOW TABLES LIKE` pattern — see `index.php:25-29`).
- **`$pageTitle` variable** controls the top navbar heading — set it before including `sidebar.php`.
- **Flash messages:** Use `setFlash($msg, $type)` / `getFlash()` from `database.php`.
- **Auth:** `requireLogin()` at top of every protected page. Passwords stored as plain text (intentional per codebase comment).

## Gotchas for agents

- No `composer.json`, no `package.json`, no config files — this is a bare PHP project.
- No automated tests or CI. Verify changes by visual inspection and ensuring PHP syntax is valid.
- SQL queries are written inline in each page file. There is no ORM or query builder.
- The `sidebar.php` has hardcoded page lists for active-state detection — if you add a new page, add it to the relevant array in `sidebar.php` or the nav highlight won't work.
- `generateCode()` in `database.php` uses `rand()` — not cryptographically secure, but used only for display codes.
- Some queries reference columns that may not exist in all table versions (e.g., `production_date` in `production`, `finished_good_id`). Check the actual DB schema before assuming column names.
