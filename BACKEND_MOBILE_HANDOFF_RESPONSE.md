# Backend response to MOBILE_BACKEND_REMAINING_WORK

Date: 2026-11-17. This response records repository changes made after reviewing `MOBILE_BACKEND_REMAINING_WORK.md`. It does not claim that production credentials, legal approval, commercial inventory, or device tests have been completed.

## Implemented backend and dashboard work

| Item | Status | Backend result |
|---|---|---|
| B1 OTP provider | Code complete; configuration pending | `/auth/challenges` can deliver a six-digit code through a configurable authenticated webhook. It enforces a 60-second resend delay and five requests per destination per hour. `AUTH_DEV_CODE` remains staging-only. Production still needs provider credentials and delivery tests. |
| H7 calendar horizon | Code and dashboard complete; operational data pending | Calendar reads support explicit `from`/`to` ranges and return configured horizon metadata. `calendar.php` lets administrators populate or close ranges. The staging seed supplies 120 explicit demo days. Production must load real availability. |
| H8 partner media | Complete | Uploads return stable ID and authenticated URL. Partner profile and service-draft reads return `uploadId` plus URL/media metadata. Media reads verify Bearer ownership. |
| M2 catalog codes | Contract complete | `/metadata/catalog` publishes supported city/category/language/currency codes. Publication validation rejects unsupported driver/guide codes and demo commercial records. |
| M3 wallet DTO | Complete for Release 1 read-only scope | Customer and partner wallet responses now have schema version 1 and stable movement fields. No recharge, payout, or spend operation was introduced. |
| M6 cursor pagination | Complete | Customer reservation history, partner service drafts, and partner jobs implement stable opaque cursor pagination with `nextCursor`, `hasMore`, and filtered `total`. |
| M7 partner profile | Version boundary complete | Profile schema version 1 is explicit and validated. Vehicle documents and regulatory metadata were not invented because retention, review access, and required fields remain a product/legal decision. |
| M8 travel packages | Contract and staging fixture complete | Publication now requires ordered destinations, itinerary content, and image metadata. The staging package demonstrates destinations, media, a three-day itinerary, terms, and availability. Production content remains required. |
| H6 dashboard lifecycle | Code complete; staging verification pending | Collaborator approval, draft review, catalog publication, availability, assignment, and request status changes are available and audited. A full mobile/staging run still needs OTP accounts and deployed infrastructure. |
| H5 configuration hardening | Code complete; deployment verification pending | Browser origins are allow-listed through `CORS_ALLOWED_ORIGINS`, public media URLs use `API_PUBLIC_BASE_URL`, and tracked production-looking database defaults were removed. Previously exposed credentials must be rotated. |
| Home banners and ordering | Restored | The dashboard can upload, edit, show/hide, delete, and numerically order home banners. `/home` projects active banner images into `offers` in ascending display order. Existing banner rows and upload paths are preserved by an additive migration. Mobile must keep server order. |

## Items requiring external or production input

- B1 provider URL/token, sender registration, staging recipients, and observed SMS/email delivery.
- B2 approved privacy and terms copy in Spanish, English, and Arabic.
- B3 verified non-demo drivers, guides, packages, prices, licensed photos, and operating conditions.
- H5 deployed staging URL, restricted CORS policy, TLS, logging/alerting, and backup restoration evidence.
- H7 real per-service calendar data maintained for the chosen operational horizon.
- M7 an approved private-document schema, retention period, reviewer permissions, and deletion policy before collection is enabled.
- Mobile-owned H1–H4, M1, M4–M5, and F4 remain with the mobile project.
- Payments, planning submission, reservation cancellation/modification, service deletion/publication from mobile, and review creation remain future scope.

## Deployment order

1. Back up the database and apply `database/migration_2026_11_17_mobile_handoff.sql`, then `database/migration_2026_11_17_home_banners.sql`.
2. Deploy the PHP changes and configure `API_PUBLIC_BASE_URL`, OTP webhook variables, and the calendar horizon.
3. Publish approved legal content and replace every `demo: true` catalog entry with reviewed inventory.
4. Populate explicit driver availability from the dashboard.
5. Run the customer/partner role matrix against staging, including cursor pagination, media previews, 401/403/409/422/429 behavior, approval, publication, assignment, and job acceptance.

The backend is ready for that staging verification after the external inputs above are supplied. It is not yet ready for a commercial production launch.
