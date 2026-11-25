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

For staging-only OTP testing, set `AUTH_DEV_CODE` to a test code such as `123456`. Do not set it in production. Production OTP delivery requires `AUTH_OTP_WEBHOOK_URL`, `AUTH_OTP_WEBHOOK_TOKEN`, and `AUTH_OTP_SENDER`; the PHP cURL extension must be enabled. Without a provider or the staging variable, challenge creation intentionally returns HTTP 503.

Set `API_PUBLIC_BASE_URL` to the public versioned API base, for example `https://api.askustours.com/v1`. `CALENDAR_HORIZON_DAYS` defaults to 120.

For mobile Firebase Authentication, set `FIREBASE_PROJECT_ID` and `FIREBASE_WEB_API_KEY` from the HALA SPAIN Firebase project, then run `database/migration_2026_11_19_firebase_auth.sql`. Enable Phone and Google providers in Firebase Authentication before exposing `POST /v1/auth/firebase-session`. The administrator dashboard continues to use its existing administrator login.

Set `UPLOAD_STORAGE_PATH` to an absolute writable directory outside `public_html` and outside the Git checkout. If omitted, the application uses the sibling directory `askustours-storage/uploads` next to the project directory. New dashboard and user uploads are written there. Reads retain a fallback to the legacy project `uploads/` directory so existing files can be migrated without downtime.

Set `CORS_ALLOWED_ORIGINS` to the comma-separated HTTPS origins allowed to run the Flutter web client. Native Android/iOS requests normally have no browser `Origin` header. Database credentials must be supplied through environment variables or the untracked `config.local.php`; no production database password is stored in the repository. Rotate any credential that was previously committed.

Do not set `APP_DEBUG=1` in production.

## New installation

Import `database/schema.sql`, then `database/seed.sql`. Initial administrator credentials are `admin@askustours.com` / `password`; change the password immediately.

## Existing installation

Do not import the full seed again. Back up the database and apply these migrations in order:

1. `database/migration_2026_11_17_mobile_handoff.sql`
2. `database/migration_2026_11_17_home_banners.sql`
3. `database/migration_2026_11_17_home_offers.sql`
4. `database/migration_2026_11_18_verification_fixes.sql`

Upload the application files, preserve current data, and update the administrator password through a freshly generated `password_hash()` value if needed. The final migration only completes the known synthetic travel-package fixture; it does not create commercial inventory.

## Smoke checks

1. `GET https://api.askustours.com/v1/home` returns `data` and `meta.requestId`.
2. Admin login redirects to `dashboard.php`.
3. Every sidebar page returns HTTP 200.
4. A published service can create a quote.
5. Reusing an Idempotency-Key with the same reservation body returns the original request; changing the body returns HTTP 409.
6. Admin can assign only an approved collaborator.
7. The collaborator can accept or decline only an assigned request with the current version.
8. Cursor pagination returns a non-null `nextCursor` when more reservation, draft, or job records exist.
9. An uploaded partner image can be previewed through its authenticated media URL.
10. The availability dashboard populates an explicit range that appears in the driver calendar response.
11. Upload two home slides and two home offers with different display-order values. Confirm `/v1/home` returns separate `banners` and `offers` collections with absolute image URLs in ascending order.
12. Request every `imageUrl` returned by `/v1/home` and confirm it returns HTTP 200 with an image content type. Missing local slide files must be omitted from the API and marked for re-upload in the dashboard.
13. Confirm the published demo travel-package collection and detail responses contain `destinations`, `image`, `imageUrl`, `itinerary`, `terms`, and `availability`.
14. Upload a temporary dashboard image and a private user image. Deploy a new commit, then confirm both files still exist, the public image returns HTTP 200 through `media.php`, and the private image still requires its owner's Bearer token.

Before running the mobile staging matrix, approve a collaborator, review its service drafts, publish reviewed catalog records, populate explicit availability, and assign a request only to an approved collaborator. These dashboard mutations are recorded in `audit_log`.

## Production boundaries

- No hotel inventory or hotel reservation contract exists yet.
- Payment status remains `notCollected`; no card data is accepted.
- Public fixture reviews and catalog records must not be presented as verified real records.
- Keep customer, collaborator and administrator authentication separate.

## Ampliación del registro profesional

El contrato aditivo y los controles del registro de siete pasos, documentos privados, vehículos, proyección pública, requisitos y comisiones se describen en [PARTNER_REGISTRATION_HANDOFF.md](PARTNER_REGISTRATION_HANDOFF.md). Se conserva schemaVersion 1 y el comportamiento de perfiles históricos. Firebase continúa como autenticación; PHP/MySQL conserva los datos. No requiere migración SQL. Los perfiles nuevos no pueden enviarse hasta publicar las condiciones legales y revisar los requisitos desde administración.

Actualización 0.1.9: la UI profesional usa tres pantallas y cuatro apartados documentales. Se conserva el contrato y los índices persistidos 0–6; los nuevos requisitos y sus validaciones están documentados en PARTNER_REGISTRATION_HANDOFF.md. Sin migración de base de datos.
