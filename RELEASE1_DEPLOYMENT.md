# Release 1 deployment

## Scope

Release 1 implements the operational path: catalog → verified session → server quote → idempotent service request → admin assignment → collaborator accept/decline → customer history. It does not collect payments or book hotels.

## Required environment

- PHP 8.1+ with PDO MySQL, JSON and Fileinfo
- MySQL 8+ or MariaDB 10.5+
- HTTPS for both subdomains
- `admin.askustours.com` document root: project root
- `api.askustours.com` document root: `api/`

Create `config.local.php` from `config.local.php.example` on Hostinger and insert the rotated database credentials. Never upload that file to a public repository.

For staging-only OTP testing, set `AUTH_DEV_CODE` to a test code such as `123456`. Do not set it in production. Without a real email/SMS provider or this staging variable, challenge creation intentionally returns HTTP 503.

Do not set `APP_DEBUG=1` in production.

## New installation

Import `database/schema.sql`, then `database/seed.sql`. Initial administrator credentials are `admin@askustours.com` / `password`; change the password immediately.

## Existing installation

Do not import the full seed again. Upload the application files, preserve your current database, and update the administrator password through a freshly generated `password_hash()` value if needed.

## Smoke checks

1. `GET https://api.askustours.com/v1/home` returns `data` and `meta.requestId`.
2. Admin login redirects to `dashboard.php`.
3. Every sidebar page returns HTTP 200.
4. A published service can create a quote.
5. Reusing an Idempotency-Key with the same reservation body returns the original request; changing the body returns HTTP 409.
6. Admin can assign only an approved collaborator.
7. The collaborator can accept or decline only an assigned request with the current version.

## Production boundaries

- No hotel inventory or hotel reservation contract exists yet.
- Payment status remains `notCollected`; no card data is accepted.
- Public fixture reviews and catalog records must not be presented as verified real records.
- Keep customer, collaborator and administrator authentication separate.
