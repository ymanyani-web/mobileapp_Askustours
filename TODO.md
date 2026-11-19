# AskUs Tours — TODO

Last updated: 19 November 2026

This file tracks work that is still pending. Do not mark an item complete without evidence from the appropriate environment.

## Blocking before commercial launch

- [ ] **Publish approved legal content** — Owner: Product/Operations
  - Supply approved privacy and terms text in Spanish, English, and Arabic.
  - Publish production version identifiers through the content manager.
  - Verify `GET /v1/content/privacy` and `GET /v1/content/terms` no longer return `draft-1`.

- [ ] **Configure and verify the production OTP provider** — Owner: Backend/Infrastructure
  - Configure `AUTH_OTP_WEBHOOK_URL`, `AUTH_OTP_WEBHOOK_TOKEN`, and `AUTH_OTP_SENDER` outside Git.
  - Verify delivery, expiry, resend timing, rate limits, invalid-code handling, refresh, and session revocation with authorized recipients.
  - Never place OTP codes or provider secrets in Markdown, logs, or commits.

- [ ] **Complete production credential rotation** — Owner: Infrastructure
  - Rotate the database credential that appeared in repository history.
  - Store the replacement in server environment variables or untracked `config.local.php`.
  - Confirm the deployed application works, then remove the tracked compatibility fallback.

- [ ] **Replace demo catalogs with reviewed commercial inventory** — Owner: Product/Operations
  - Add real driver services, guide services, packages, prices, licensed images, availability, and commercial conditions.
  - Remove or unpublish records marked `demo: true` before commercial launch.
  - Do not present the synthetic package as bookable commercial inventory.

## High priority

- [ ] **Restore the three home slides** — Owner: Product/Operations
  - Open `content.php?type=banners` in the admin dashboard.
  - Edit each record marked **Missing image — edit and re-upload**.
  - Upload the intended JPG, PNG, or WebP image and confirm display order.
  - Verify every returned `data.banners[].imageUrl` responds HTTP 200 with an image content type.

- [ ] **Make uploaded media persistent across deployments** — Owner: Infrastructure
  - Preserve the production `uploads/` directory when Git deployments replace application files.
  - Prefer a persistent volume, deployment-excluded directory, or object storage with a documented migration plan.
  - Back up both uploaded files and their database metadata.
  - Perform and document a restore test so database paths cannot outlive their physical files again.

- [ ] **Apply the outstanding production data migration** — Owner: Backend/Infrastructure
  - Back up the production database.
  - Apply `database/migration_2026_11_18_verification_fixes.sql` once through the approved database deployment process.
  - Confirm the stored demo package contains destinations, image, itinerary, terms, and availability.
  - The API currently normalizes the fixture at runtime; Git deployment alone does not apply SQL.

- [ ] **Provide private staging access** — Owner: Backend/Infrastructure
  - Provide the staging API URL plus one customer and one collaborator account through a private channel.
  - Include an approved collaborator and owned test uploads without placing credentials in Git.
  - Keep staging isolated from production customer data.

- [ ] **Run the private API staging matrix** — Owner: Backend + Mobile
  - Verify `/me/wallet`, `/me/partner-wallet`, `/me/partner-profile`, `/me/partner-services/{id}`, `/uploads`, and `/uploads/{id}`.
  - Test customer versus collaborator permissions, cross-owner media access, missing files, revision conflicts, and schema conflicts.
  - Record PASS/FAIL evidence without tokens, private media URLs, or personal data.

- [ ] **Verify production operations** — Owner: Infrastructure
  - Confirm CORS for the deployed web client.
  - Configure monitoring, error alerts, access logs, database backups, upload backups, and restore procedures.
  - Confirm private media responses do not redirect and remain owner scoped.

## Mobile and release work

- [ ] Configure Android release signing and store distribution — Owner: Mobile/Infrastructure.
- [ ] Build and test iOS with Runner signing and Keychain on macOS — Owner: Mobile/Infrastructure.
- [ ] Test secure credentials, private images, wallets, logout, RTL, accessibility, offline behavior, and retries on real Android and iPhone devices — Owner: Mobile.
- [ ] Persist and reconcile uncertain idempotent actions after process termination — Owner: Mobile.
- [ ] Add explicit reload/compare UX after `SERVICE_REVISION_CONFLICT` — Owner: Mobile.
- [ ] Complete field-level OTP errors and expiry-state presentation — Owner: Mobile.

## Future scope requiring an approved contract

- [ ] Regulated collaborator documents and retention/deletion rules.
- [ ] Payments, wallet recharge, spending, and payout operations.
- [ ] Hotel inventory and reservation operations.
- [ ] Cancellation and modification workflows.
- [ ] Review creation and moderation writes.
- [ ] Persistent web authentication through a secure cookie or BFF design.

## Completion rule

When an item is completed:

1. Record the verification evidence.
2. Move it to [DONE.md](./DONE.md).
3. Update the applicable API, deployment, and mobile handoff documents.
4. Never mark configuration or operational data complete based only on local code tests.
