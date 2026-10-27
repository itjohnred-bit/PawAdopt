# DEPLOY.md — Deploying PawAdopt on Render

PawAdopt is a **PHP 8 + MySQL/MariaDB** application. Render has **no native PHP
runtime** and **no managed MySQL** (only managed PostgreSQL), so the deployment
consists of two parts:

1. **PawAdopt web service** — Docker image built from `Dockerfile` (php:8.2-apache + pdo_mysql).
2. **A MySQL/MariaDB database** hosted elsewhere — an external managed MySQL such as
   [Aiven's free tier](https://aiven.io/free-mysql-database), or (paid Render plans only)
   a Render **private service** running MySQL with a persistent disk.

> Reference facts verified against Render docs:
> - PHP must be deployed via Docker on Render (no native PHP runtime).
> - Render's managed database is PostgreSQL only; MySQL is available as a
>   private-service template or via external providers.
> - Web services must bind the HTTP server to the `PORT` environment variable.
> - Free-tier service filesystems are **ephemeral** — files written at runtime
>   are lost on every deploy/restart unless you attach a persistent disk (paid).

---

## Step 1 — Put the code in a Git repository

1. Extract this project (or copy the `PawAdopt/` folder) and create a Git repo:
   ```bash
   git init
   git add .
   git commit -m "PawAdopt secured build"
   ```
2. Push to a fresh GitHub repository (private is fine).
   **Do NOT commit a real `.env` file** — `.dockerignore` already excludes it
   from the Docker build, but keep it out of Git too (add `.env` to `.gitignore`).

## Step 2 — Create a free MySQL database (external provider)

Using Aiven as the example (other MySQL providers work identically — you only
need host, port, database name, user, password):

1. Create an account at aiven.io → **MySQL** service on the **free plan**.
2. Note the **Service URI / connection details**: host, port (usually 3306),
   default database (`defaultdb`), username (`avnadmin`), and password.
3. Connect once with any MySQL client (or their web console) and run the
   migration files from `sql/` **in this exact order**:

   ```
   1. sql/pawadopt.sql                      (base schema: users, pets, applications, ...)
   2. sql/appointments.sql
   3. sql/feedback_reports.sql
   4. sql/password_reset_otps.sql
   5. sql/account_deletion_requests.sql
   6. sql/user_prefs.sql                    (fixed to skip legacy table when absent)
   7. sql/security_hardening.sql            (ownership + duplicate-prevention indexes)
   8. sql/ui_customization.sql              (per-user UI customization columns)
   ```

   All files are idempotent / safe to re-run. Migrations were validated on
   MariaDB 10.11 (see DATABASE_AUDIT.md for the test record).

## Step 3 — Create the web service on Render

**Option A — Blueprint (recommended):** Render Dashboard → **New +** →
**Blueprint** → select the repo. Render reads `render.yaml` and creates the
`pawadopt-web` Docker web service with `healthCheckPath: /health.php`.

**Option B — Manual:** New + → **Web Service** → select the repo →
Runtime: **Docker** → Dockerfile path: `Dockerfile` → plan: Free.

## Step 4 — Set environment variables

Render Dashboard → **pawadopt-web** → **Environment** → add:

| Variable | Required | Sensitive | Example / notes |
|---|---|---|---|
| `DB_HOST` | **Yes** | yes | Aiven host, e.g. `mydb-mysql.a.aivencloud.com` |
| `DB_PORT` | Yes | no | `3306` (Aiven free often uses a custom port — copy theirs) |
| `DB_NAME` | Yes | no | `defaultdb` (Aiven) or `pawadopt` |
| `DB_USER` | **Yes** | yes | least-privilege runtime user (not admin if possible) |
| `DB_PASS` | **Yes** | yes | database password |
| `APP_URL` | Optional | no | auto-derived on Render; set explicitly for a custom domain |
| `APP_ENV` | Optional | no | `production` (default) |
| `UPLOAD_PATH` | Optional | no | `/var/www/html/uploads` when using a mounted disk |
| `GEMINI_API_KEY` | For PawBot chat | **yes** | server-side only; rotate the previously exposed key |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_ENCRYPTION` | For e-mail | no | `smtp.gmail.com` / `587` / `tls` |
| `SMTP_USERNAME` / `SMTP_PASSWORD` | For e-mail | **yes** | Gmail app-password; the old one was exposed — revoke it |
| `SMTP_FROM_EMAIL` / `SMTP_FROM_NAME` | For e-mail | no | sender identity |

Full documented reference with purposes and required-flags: `.env.example`.

If you used the Blueprint, variables marked `sync: false` are placeholders —
fill their real values in the dashboard after the first sync.

## Step 5 — Deploy and verify

1. Render builds the Docker image and starts the container. The entrypoint
   (`docker/start.sh`) rewrites Apache's `Listen` directive to Render's `$PORT`
   on every boot, then starts Apache.
2. Check **pawadopt-web → Events** until the deploy is live. The deploy only
   goes live if `GET /health.php` returns 200 (it pings the database with
   `SELECT 1`).
3. Open your service URL (`https://pawadopt-web.onrender.com` or similar) —
   the public landing page should load.
4. Verify: register a test account, log in, browse pets, and (if configured)
   request a password-reset OTP e-mail.

## Step 6 — The ephemeral-uploads caveat (important)

On the **free plan**, anything written under `uploads/` (pet photos, avatars,
certificates) is **lost on every redeploy/restart**. Options:

- **Accept it** for a demo/evaluation environment (re-upload after deploys), or
- **Upgrade the service** and attach a Render **persistent disk** mounted at
  `/var/www/html/uploads` (uncomment the `disk:` block in `render.yaml`), or
- Point `UPLOAD_PATH` at an external object store (S3/GCS) — requires a small
  code change in `includes/functions.php` (documented in the README roadmap).

## Rollback and recovery

- **Code rollback:** Render → pawadopt-web → **Deploys** → pick the last good
  deploy → **Rollback**. Rollbacks are instant and don't touch the database.
- **Database rollback:** migrations are additive (new columns/tables/indexes).
  To undo `security_hardening.sql` manually:
  `ALTER TABLE adoption_applications DROP INDEX uq_active_application;`
  `ALTER TABLE adoption_applications DROP COLUMN is_active;`
  `ALTER TABLE appointments DROP INDEX uq_appointment_slot;`
  Never run destructive experiments against the production database — take a
  dump first (`mysqldump` from your DB provider's console) and restore into a
  scratch database if needed.
- **Database outage:** the app fails fast (5s connect timeout) with a generic
  JSON error, and `/health.php` reports 500 so Render shows the service as
  unhealthy; it recovers automatically when the database is reachable again.

## Security checklist before going live

- [ ] Rotate the previously exposed Gemini key and Gmail app-password.
- [ ] All `DB_*`, `GEMINI_API_KEY`, `SMTP_PASSWORD` set via the dashboard (never in code).
- [ ] No real `.env` in Git or in the Docker image.
- [ ] HTTPS active (Render provides it automatically).
- [ ] Demo accounts from the original README given new passwords or deleted.
