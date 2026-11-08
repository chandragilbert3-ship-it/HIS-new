# Deploying SIMRS to Vercel

The app is unchanged for XAMPP. The Vercel-only behaviour switches on automatically when the app detects it is running on Vercel.

## What was added / changed

| File | Why |
|---|---|
| `vercel.json` | Tells Vercel to run PHP (`vercel-php` community runtime, PHP 8.3), serve `simrs/assets/*` as static files, and send every other URL to `api/index.php`. Region `sin1` = Singapore. |
| `api/index.php` | Front controller. Vercel only executes PHP inside `api/`, so this file receives every request and runs the matching page in `simrs/` (like Apache does in XAMPP). Blocks `includes/`, `config/`, `storage/`. `/` redirects to `/simrs/`. |
| `.vercelignore` | Keeps `simrs_db.sql` and `storage/` out of the deployment (`simrs_db_tidb.sql` is included for the auto-import). |
| `simrs/config/database.php` | Uses the built-in TiDB login on Vercel (or `DB_*` Environment Variables if set), XAMPP defaults locally; auto-imports the database on first visit, supports TLS (`DB_SSL=true`), sets DB time zone to +07:00. |
| `simrs/includes/session_db.php` + `bootstrap.php` | With `SESSION_DRIVER=db`, logins are stored in the `sessions` table instead of files, so users don't get logged out at random between Vercel instances. `APP_DEBUG` now comes from an env var. |
| `simrs/includes/radiology.php` + `modules/radiology/image.php` | With `STORAGE_DRIVER=db`, uploaded radiology images are stored in the `rad_image_blobs` table (Vercel can't keep files written to disk). |
| `simrs_db_tidb.sql` | Same data as `simrs_db.sql`, rewritten for TiDB: primary keys, indexes and AUTO_INCREMENT inside `CREATE TABLE` (TiDB can't add AUTO_INCREMENT via `ALTER`), safe to re-run (`IF NOT EXISTS`, `INSERT IGNORE`), foreign keys left out so the import is fast, plus the `sessions` and `rad_image_blobs` tables. |

## How to deploy (database is already configured)

The TiDB Cloud login is built into `simrs/config/database.php` (`VERCEL_DEFAULTS`), so **no Environment Variables are needed**. On the very first visit, the app imports `simrs_db_tidb.sql` into TiDB by itself (takes ~10–30 s once). If that first visit is interrupted, the next visit continues where it stopped.

1. Upload all files from this package to the GitHub repo (keep the folders), and commit to `main`.
2. Vercel redeploys automatically. Check **Settings → Build & Deployment**: Framework Preset **Other**, Root Directory **empty**.
3. Open `https://simrs-xi.vercel.app/` and wait for the first load. You should land on `/simrs/login.php`.

Environment Variables in Vercel (`DB_HOST`, `DB_PASS`, …) still override the built-in values if you ever change database.

**Security:** the repo is public, so anyone can read the database password in `config/database.php`. Make the repo private (GitHub → Settings → Danger Zone → Change visibility); Vercel keeps working.

### Manual import (optional, instead of auto-import)

```bat
C:\xampp\mysql\bin\mysql.exe -h gateway01.ap-southeast-1.prod.aws.tidbcloud.com -P 4000 -u "WyEp8FuiAxUT2zT.root" -p --ssl < simrs_db_tidb.sql
```

## Troubleshooting

- **"Koneksi database gagal" (500)** → TiDB login changed (e.g. password reset: update `VERCEL_DEFAULTS` or set `DB_PASS` in Vercel), or the first auto-import timed out — just reload. Details: Vercel → Deployment → **Logs**.
- **Still 404 on `/`** → Root Directory is set to `simrs` or `vercel.json` isn't at the repo root.
- **Radiology upload fails on Vercel for big images** → Vercel limits a request body to about 4.5 MB, so keep uploads under ~4 MB there (XAMPP still allows 5 MB).
- **Pages are slow** → database and Vercel function are in different regions; both should be Singapore.

## Testing locally without Apache

```bash
php -S localhost:8000 api/index.php
# open http://localhost:8000/
```
