# AskUs Tours backend and dashboard agent guide

## Purpose

This repository contains the PHP/MySQL backend API and administrator dashboard used by the AskUs Tours mobile application.

Future coding agents must preserve the API contract shared with the mobile project, keep dashboard operations auditable, avoid exposing credentials, and clearly distinguish completed code from production configuration or operational work.

Read these documents before changing an API or mobile-facing response:

- `BACKEND_API_REQUIREMENTS.md`
- `MOBILE_API_INTEGRATION_CODEX.md`
- `MOBILE_BACKEND_REMAINING_WORK.md`
- `BACKEND_DAILY_HANDOFF_2026_11_17.md`
- `MOBILE_HOME_CONTENT_SEPARATION.md`
- `MOBILE_PRIVATE_API_V1_EXAMPLES.md`
- `RELEASE1_API.md`
- `RELEASE1_DEPLOYMENT.md`

## Repository responsibilities

The backend/dashboard owns:

- Public home configuration and catalogs.
- Authentication challenges, sessions, and refresh/revocation behavior.
- Owner-scoped customer and collaborator data.
- Upload ownership and protected media access.
- Quotes, reservation requests, assignments, and collaborator job decisions.
- Cursor pagination for private collections.
- Explicit service availability.
- Read-only Release 1 wallet projections.
- Administrator approval, publication, assignment, and audit history.
- Database schema, additive migrations, deployment documentation, and smoke checks.

The mobile project owns device UI, secure native credential storage, client-side caching, accessibility, RTL behavior, offline behavior, mobile builds, and store distribution.

Do not claim that mobile/device work is complete from backend-only tests.

## Canonical API

The active API is under `/api/v1` and is routed through `api/index.php`.

Successful responses use:

```json
{
  "data": {},
  "meta": {
    "requestId": "req_..."
  }
}
```

Errors use:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable message",
    "fields": {}
  },
  "meta": {
    "requestId": "req_..."
  }
}
```

Keep wire values stable. When changing a response, update `RELEASE1_API.md`, the relevant handoff document, fixtures, migrations, and smoke checks in the same change.

## Home-screen content contract

The mobile home screen contains two independent image collections.

### Large slides

- Dashboard page: `content.php?type=banners`
- API field: `data.banners`
- Mobile placement: below the profile header and above the shortcut menu
- Dashboard label: **Home slides**

Each item contains a stable `banner_` ID, absolute `imageUrl`, localized `title`, localized `subtitle`, optional `linkUrl`, and `displayOrder`.

### Featured offer cards

- Dashboard page: `content.php?type=offers`
- API field: `data.offers`
- Mobile placement: **Ofertas destacadas**, below the shortcut menu
- Dashboard label: **Home offers**

Each item contains a stable `offer_` ID, absolute `imageUrl`, localized `title`, localized `description`, offer-detail fields, optional `linkUrl`, and `displayOrder`.

Never map banner rows into `data.offers`. Never instruct the mobile client to reuse offers as the large slide source. Preserve each collection's server order.

At the end of 17 November 2026, the live API returned three banner slides and zero featured offers. Administrators still needed to create the real offer cards.

## Dashboard content behavior

The home content manager must support:

- Image upload in JPG, PNG, or WebP.
- Maximum upload size enforced by `upload_image`.
- Required title and image for slides/offers.
- Non-negative numeric display order.
- Visible/hidden state.
- Edit, toggle, and delete actions.
- Audit records for save, visibility, and delete mutations.

Lower display-order values appear first. Ties use stable ascending IDs in the public API.

The optional offer link controls what happens when a user taps the card. It may be empty for a display-only card.

Legacy `catalog.php?view=banners` and `catalog.php?view=offers` requests redirect to the matching content manager. Do not reintroduce an unconditional redirect at the beginning of `content.php`; that previously made the manager unreachable.

## Authentication and OTP

`POST /auth/challenges` supports a configurable authenticated webhook.

Required production settings:

- `AUTH_OTP_WEBHOOK_URL`
- `AUTH_OTP_WEBHOOK_TOKEN`
- `AUTH_OTP_SENDER`
- PHP cURL extension

Rules:

- One challenge per destination per minute.
- No more than five challenges per destination per hour.
- `AUTH_DEV_CODE` is staging-only.
- Production must never claim a message was delivered when no provider is configured.
- Do not log OTP codes, bearer tokens, refresh tokens, private payloads, or provider secrets.

Real provider delivery remains an external configuration and verification task until evidence is recorded.

## Pagination

These private collections use stable opaque cursor pagination:

- `GET /me/reservations`
- `GET /me/partner-services`
- `GET /me/partner-jobs`

Return `meta.pagination.limit`, `nextCursor`, `hasMore`, and `total`. Treat cursors as opaque client values. Do not replace them with offsets or expose internal SQL details.

## Media and uploads

Do not publish a local media reference unless its physical file exists. Preserve the stored ID for repair, return nullable preview fields, and mark missing home assets in the dashboard.

New runtime media must be written through `upload_storage_root()`. Its default is the sibling `askustours-storage/uploads` directory outside the Git checkout, and production can override it with `UPLOAD_STORAGE_PATH`. Never change writes back to the repository `uploads/` directory. `stored_asset_file()` retains read-only legacy fallback during migration. Public catalog images are streamed through `media.php`; private user images remain owner scoped through `/v1/uploads/{uploadId}`.

Uploads belong to the authenticated user.

- Upload responses expose both `id`/`uploadId` and a usable URL.
- `GET /uploads/{uploadId}` must verify ownership.
- Partner profile and service responses include media references suitable for edit previews.
- Do not expose another user's upload by guessing an ID.
- Absolute public home image URLs use the configured admin base URL.

## Calendar

Driver calendars contain explicit availability records.

- Missing dates are unavailable.
- Public reads accept validated `from` and `to` ranges.
- Ranges cannot exceed `CALENDAR_HORIZON_DAYS`.
- Administrators maintain availability through `calendar.php`.
- Demo dates are not proof of real operational availability.

## Catalog and publication

`GET /metadata/catalog` publishes supported city, category, language, and currency codes.

Publication rules include:

- No unsupported city/category fallback.
- Demo records cannot be published as commercial inventory.
- Driver services require supported city/category and package content.
- Guide services require a supported city.
- Travel packages require ordered destinations, image metadata, and itinerary content.

Real inventory, prices, licensed photos, availability, and conditions require Product/Operations review.

## Profiles and wallets

Partner profiles and wallet responses use explicit schema version 1 boundaries.

Do not add vehicle identity documents, regulatory documents, recharge, payout, or spending operations without an approved contract covering permissions, retention, deletion, reconciliation, and legal requirements.

Release 1 wallet endpoints are read-only. A displayed balance is not proof that funds are withdrawable or spendable.

## Reservations and payments

Release 1 supports server quotes and reservation requests. It does not collect card payments.

- Preserve integer minor-unit money values.
- Preserve server quote expiry and availability decisions.
- Require `Idempotency-Key` where documented.
- Replaying the same key/body returns the original result.
- Reusing a key with a different body returns a conflict.
- `paymentStatus: notCollected` is not a completed sale.
- Never accept card details through the current API.

Cancellation, modification, reviews creation, and payment operations require separate contracts before implementation.

## Database changes

Use additive migrations for existing installations. Do not reimport full seeds into production.

Current migration order:

1. `database/migration_2026_11_17_mobile_handoff.sql`
2. `database/migration_2026_11_17_home_banners.sql`
3. `database/migration_2026_11_17_home_offers.sql`
4. `database/migration_2026_11_18_verification_fixes.sql`

The canonical new-install schema is `database/schema.sql` followed by `database/seed.sql`.

Before a production database change:

- Back up the database.
- Use `CREATE TABLE IF NOT EXISTS` only where repeatability is intended.
- Preserve existing records and upload paths.
- Test the migration in an isolated database.
- Update `api/release1-smoke.php` when a table or route becomes required.

## Configuration and secrets

Prefer environment variables or an untracked `config.local.php` for secrets.

Never commit new production passwords, OTP tokens, API keys, signing credentials, or personal data.

The database credential that existed in repository history must be rotated. A temporary compatibility fallback was restored because the Hostinger production environment did not yet provide the required database environment settings. Future work should move the rotated credential into server configuration and remove the tracked fallback without causing an outage.

Production configuration includes:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_URL`
- `API_PUBLIC_BASE_URL`
- `CORS_ALLOWED_ORIGINS`
- OTP webhook settings
- `CALENDAR_HORIZON_DAYS`

Do not enable `APP_DEBUG=1` in production.

## Required validation before completing a change

Run checks appropriate to the change:

1. PHP syntax validation using the installed Wamp PHP executable.
2. Editor diagnostics for edited files.
3. `git diff --check`.
4. Isolated MySQL schema/migration validation for database changes.
5. Focused API behavior tests for modified routes.
6. `api/release1-smoke.php` when the canonical schema is available.
7. Live read-only checks after an authorized production push.

For home content changes, test banners and offers with deliberately different order values and confirm they remain separate.

Do not report production success solely from local fixtures. State exactly whether evidence came from isolated tests, staging, or production.

## Deployment behavior

The active Git branch is `main` and the remote is `origin`.

Pushing code does not guarantee that SQL migrations ran. Confirm both file deployment and database state. The public home endpoint is:

`https://api.askustours.com/v1/home`

After deployment, verify HTTP status and expected response keys. Avoid production mutations unless explicitly authorized and necessary.

## Mobile handoff workflow

When backend changes affect the mobile contract:

1. Update API documentation and examples.
2. Create or update a focused mobile handoff Markdown file.
3. State exact keys, types, ordering, authentication, error codes, and compatibility impact.
4. Ask the mobile developer to test against the deployed backend.
5. Require the mobile developer to distinguish mock, staging, and production evidence.
6. Request one Markdown response listing pass/fail status and every remaining item by owner and priority.

The current requested re-verification response filename is:

`MOBILE_BACKEND_REVERIFICATION_RESPONSE.md`

The complete response template and checklist are in `BACKEND_DAILY_HANDOFF_2026_11_17.md`.

## Reporting status accurately

Use these status meanings:

- **Complete**: implementation and appropriate automated verification passed.
- **Code complete; configuration pending**: code exists, but an external provider, credential, account, or environment is missing.
- **Staging verification pending**: isolated tests passed, but real shared infrastructure has not been exercised.
- **Operational data pending**: the feature works, but real inventory, availability, legal copy, or media has not been entered.
- **Future scope**: no contract authorizes implementation yet.

Never convert an external dependency into a false completed status.

## Current production blockers

- Real OTP delivery is not yet proven.
- Legal privacy/terms content requires approval and publication.
- Demo catalog data must be replaced with verified commercial inventory.
- Mobile signing, real-device secure storage, accessibility, RTL, and store distribution remain mobile/infrastructure tasks.
- Full customer/collaborator staging flows remain to be verified.
- Database credentials require rotation and server-side secret configuration.

Payments, recharge, hotel booking, planning submission, reservation cancellation/modification, and new review creation remain outside the current Release 1 contract.
