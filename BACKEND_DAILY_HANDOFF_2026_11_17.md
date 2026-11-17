# Backend daily handoff — 17 November 2026

## Purpose

This document consolidates all backend and admin-dashboard work completed today after reviewing:

- `MOBILE_BACKEND_REMAINING_WORK.md`
- `MOBILE_API_INTEGRATION_CODEX.md`
- `BACKEND_API_REQUIREMENTS.md`
- The mobile home-screen structure confirmed from the latest screenshot

It records what the mobile developer expected, what was implemented, what was verified, and what still requires external configuration or mobile-side work.

## Summary

The requested backend code changes are implemented and pushed to `main`. The live public home endpoint responds successfully. The large home slides and the smaller **Ofertas destacadas** cards are now separate collections and have separate dashboard managers.

This is not a commercial-production approval. Real OTP delivery, approved legal copy, verified inventory, signed mobile releases, and the complete private staging matrix still require work outside this repository.

## Requested work and backend response

| ID / area | What mobile expected | What backend/dashboard now provides | Current status |
|---|---|---|---|
| B1 — OTP | Real challenge delivery, resend control, and rate limits | Configurable authenticated OTP webhook, 60-second resend delay, and five requests per destination per hour | Code complete; provider configuration and real delivery test pending |
| H7 — Calendar | More than two explicit dates and a documented horizon | `from`/`to` calendar queries, configured horizon metadata, and an admin availability page for opening or closing date ranges | Code/dashboard complete; real production availability must be maintained |
| H8 — Partner media | Both `uploadId` and a usable media URL | Owned upload references include ID, URL, purpose, metadata, and authenticated media reads | Complete |
| M2 — Catalog codes | Stable city/category values with no invented fallback | `GET /metadata/catalog` publishes supported cities, driver categories, languages, and currencies; publication validation rejects unsupported values | Contract complete |
| M3 — Wallets | Stable read-only wallet DTO | Schema version 1 and stable movement fields for customer and partner wallets | Complete for Release 1 read-only scope |
| M6 — Pagination | Real cursor pagination for private collections | Opaque cursor, `nextCursor`, `hasMore`, and filtered `total` on reservations, partner drafts, and partner jobs | Complete |
| M7 — Partner profile | A clear supported schema boundary | Profile schema version 1 is explicit and validated | Supported basic profile complete; regulated document schema remains a product/legal decision |
| M8 — Travel packages | Ordered destinations, image, and itinerary content | Publication requires ordered destinations, media, and itinerary content; the demo fixture demonstrates the full shape | Contract/fixture complete; real commercial content pending |
| H6 — Dashboard lifecycle | Admin approval, publication, availability, assignment, and audited mutations | The dashboard supports those operations and records relevant mutations in `audit_log` | Code complete; shared staging role matrix pending |
| H5 — Deployment configuration | Restricted CORS, stable public URLs, TLS-safe configuration | Origin allow-list, public API/media URL configuration, and production deployment documentation | Partially operational; observability, backup restoration, and credential rotation still pending |
| Home slides | Large carousel below the profile header | Separate **Home slides** manager with image upload, visibility, editing, deletion, and numeric display order | Deployed |
| Featured offers | Small **Ofertas destacadas** cards below the shortcut menu | Separate **Home offers** manager with image upload, title, subtitle, optional tap link, visibility, and numeric display order | Deployed; no production offer cards entered yet |

## Important correction to the home contract

The mobile home screen contains two independent image collections:

1. `data.banners` — large slides below the profile header and above the shortcut menu.
2. `data.offers` — smaller **Ofertas destacadas** cards below the shortcut menu.

The mobile client must not reuse `data.offers` for the large carousel. Each array is already sorted by `displayOrder` ascending and must retain server order.

The exact mobile mapping and example payloads are documented in `MOBILE_HOME_CONTENT_SEPARATION.md`.

## Dashboard pages

- Home slides: `https://admin.askustours.com/content.php?type=banners`
- Home offers: `https://admin.askustours.com/content.php?type=offers`
- Availability calendar: `https://admin.askustours.com/calendar.php`
- Mobile catalog: `https://admin.askustours.com/catalog.php?view=drivers`

The legacy `catalog.php?view=banners` route now redirects to the correct slide manager. An unconditional redirect that previously made `content.php` unreachable was removed.

## API behavior changed today

### `GET /v1/home`

- Returns separate `banners` and `offers` arrays.
- Returns absolute image URLs.
- Orders both collections by `displayOrder`, followed by stable ID order.
- Keeps shortcuts, trip ideas, destinations, experiences, and planning-fee configuration in the home response.

### Authentication

- OTP challenge delivery supports a configured webhook.
- Requests enforce resend and hourly destination limits.
- Production without a configured provider returns a configuration/provider error instead of pretending delivery succeeded.

### Private collections

- `/me/reservations`
- `/me/partner-services`
- `/me/partner-jobs`

These endpoints now expose stable cursor pagination.

### Media

- Upload responses expose stable IDs and URLs.
- `GET /uploads/{uploadId}` verifies ownership.
- Partner profile and service reads include media references suitable for edit previews.

### Catalog and packages

- `GET /metadata/catalog` publishes supported codes.
- Driver and guide publication validates supported codes.
- Demo records cannot be published as commercial inventory.
- Travel-package publication requires ordered destinations, an image, and itinerary content.

### Wallet and profile boundaries

- Wallet reads use schema version 1 with stable movement fields.
- Partner profiles use schema version 1 and do not invent unapproved regulatory-document fields.

## Database changes

For an existing installation, back up the database and apply these migrations in order:

1. `database/migration_2026_11_17_mobile_handoff.sql`
2. `database/migration_2026_11_17_home_banners.sql`
3. `database/migration_2026_11_17_home_offers.sql`

The **Home offers** dashboard also creates its additive table if it is missing, allowing the manager to open safely after file deployment. The SQL migration remains the required deployment record.

## Deployment commits

| Commit | Purpose |
|---|---|
| `5c0ae21` | Main mobile backend handoff implementation |
| `89ef7a3` | Restore compatibility with the existing production database configuration |
| `0df9da1` | Redirect the legacy banner catalog URL |
| `2c4d76b` | Remove the redirect that blocked the banner manager |
| `5b55f3a` | Separate large home slides from featured offer cards |

## Verification completed today

- PHP 8.3 syntax validation passed for all 28 PHP files.
- Editor diagnostics report no errors.
- `git diff --check` passed before each production commit.
- Canonical schema, seed, and backend smoke validation passed in an isolated MySQL database during the main handoff implementation.
- An isolated API test inserted slides and offers in reverse input order and confirmed:
  - `banners` and `offers` remain separate.
  - Each collection is returned in its own ascending display order.
  - IDs use separate `banner_` and `offer_` prefixes.
  - Images are returned as absolute URLs.
- Live `GET https://api.askustours.com/v1/home` returned HTTP 200 after deployment.
- Final live observation after commit `5b55f3a`: `banners` contained 3 slides and `offers` contained 0 cards. Administrators still need to enter the real offer cards.

## Items that remain outside the completed code

### Backend/infrastructure/product

- Configure the production SMS/email OTP provider and test real delivery, resend, expiration, and rate limits.
- Rotate the database credential previously committed to repository history and move the replacement into Hostinger environment variables or an untracked `config.local.php`.
- Publish approved privacy and terms content in Spanish, English, and Arabic.
- Replace all demo catalog entries with reviewed commercial inventory, prices, licensed images, and operating conditions.
- Populate and maintain real driver availability for the agreed horizon.
- Verify logs, alerts, CORS origins, TLS behavior, database backups, and restoration.
- Define any future regulated partner-document schema, permissions, retention, and deletion policy before collecting those documents.

### Mobile-owned work from the previous handoff

- H1: verify secure credential persistence on real Android and iPhone devices.
- H2: run the customer/partner role matrix against the deployed backend with real staging accounts.
- H3: persist and reconcile uncertain idempotent actions after process termination.
- H4: implement a clear conflict compare/reload experience for stale partner-service revisions.
- M1: bind and localize server field errors and OTP expiration states.
- M4: adapt the full reviews experience to remote data.
- M5: complete accessibility, keyboard, RTL, large-text, and offline testing.
- F4: decide whether web session persistence needs a secure cookie/BFF design.
- Update the home screen so the top carousel reads `data.banners` and **Ofertas destacadas** reads `data.offers`.

## Required verification from the mobile developer

Please pull the latest backend contract and verify all affected mobile flows. At minimum, check:

- [ ] The top home carousel reads `data.banners` only.
- [ ] **Ofertas destacadas** reads `data.offers` only.
- [ ] Empty `offers` shows a correct empty state and does not reuse banner slides.
- [ ] Banner and offer images load from their absolute `imageUrl` values.
- [ ] Both collections preserve server display order.
- [ ] Home shortcuts and the rest of the home response still render correctly.
- [ ] Cursor pagination loads more reservations, partner drafts, and partner jobs without duplicates.
- [ ] Partner media previews use the returned `uploadId` and URL correctly.
- [ ] Calendar date ranges respect explicit dates and the configured horizon.
- [ ] Unknown catalog codes are handled without silently mapping them to a different city/category.
- [ ] Wallet movement labels and profile schema version 1 still map correctly.
- [ ] Travel packages consume ordered destinations, image metadata, and itinerary content.
- [ ] 401, 403, 409, 422, 429, and OTP provider/configuration errors display appropriate mobile states.
- [ ] `dart analyze`, Flutter tests, Android build, and any available iOS/web checks still pass.

## Required response file

After verification, please respond with one Markdown file named:

`MOBILE_BACKEND_VERIFICATION_RESPONSE.md`

Use this structure:

```md
# Mobile verification response to backend handoff

Date:
Mobile commit:
Backend commit tested: 5b55f3a
API base URL tested:

## Result summary

- Overall result: PASS / PARTIAL / FAIL
- Analyze result:
- Test result:
- Android build result:
- iOS/web result, if available:

## Verification matrix

| Area | Result (PASS/FAIL/NOT TESTED) | Evidence | Remaining work | Owner | Priority |
|---|---|---|---|---|---|
| Home banners | | | | | |
| Featured offers | | | | | |
| Authentication/OTP | | | | | |
| Pagination | | | | | |
| Partner media | | | | | |
| Calendar | | | | | |
| Catalog metadata | | | | | |
| Wallet/profile | | | | | |
| Travel packages | | | | | |
| Error handling | | | | | |

## Backend issues still found

For every issue include:

- ID:
- Priority: Blocking / High / Medium / Future
- Endpoint:
- Exact request shape, with secrets removed:
- HTTP status and error code:
- Expected behavior:
- Actual behavior:
- Reproduction steps:
- Mobile file/symbol affected:
- Requested backend change:

## Mobile work still required

List every remaining mobile-owned item with priority, file/symbol, and concrete next action.

## Final question

Is the backend contract sufficient for the next mobile integration stage? Yes / No
If no, list the exact missing contract fields or endpoints.
```

Do not report a backend item as complete based only on a mocked server. Clearly distinguish isolated tests, staging tests, and production reads. Remove tokens, OTP codes, credentials, personal data, and private upload URLs from the response.
