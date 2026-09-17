# SafeRoad AI - Final Render Setup

This package is prepared for the existing Render Docker service.

## Important path rule

The project uses exactly this folder and file name:

`Config/db.php`

All PHP files now use the same uppercase `Config` path with `__DIR__`, so Linux/Render case-sensitivity will not break includes.

## Required Render environment variables

Keep these in **Safe_RoadAI -> Environment**:

- `DB_HOST` = current Aiven **MySQL Host**
- `DB_PORT` = current Aiven MySQL Port
- `DB_USER` = Aiven MySQL user, commonly `avnadmin`
- `DB_PASSWORD` = Aiven MySQL password
- `DB_NAME` = `saferoad_ai`

Never put the real password inside GitHub code.

If Aiven recreates or powers down the database and the hostname stops resolving, open Aiven, make sure MySQL is Running, copy its current connection information, and update the Render environment values.

## Deployment

The `Dockerfile` installs the required PHP extensions, enables Apache, respects Render's runtime `PORT`, and keeps PHP errors out of the public page.

After pushing to GitHub, Render should auto-deploy the latest commit. If auto-deploy is disabled, use **Manual Deploy -> Deploy latest commit**.

## Database initialization

No public `import_db.php` is needed. After a successful connection, `Config/db.php` calls the schema migration once and creates/updates the required tables and demo accounts.

Demo accounts:
- Admin: `admin@saferoad.test` / `admin123`
- Citizen: `citizen@saferoad.test` / `user123`

Health endpoint:
- `/health.php`

## Render Free note

Database records persist in Aiven, but files uploaded to Render's local filesystem are not durable on an ephemeral/free web service. Uploaded evidence may disappear after a restart or redeploy unless object storage is added later.
