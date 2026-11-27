# Customer traveler requirements and planning basket — 2026-11-28

Customer traveler policy version 3 requires firstName, lastName, birthDate, gender, nationality, phone, email, address, city, postalCode, region and billing country before requests/basket writes. Residence country is no longer requested. Passport remains conditional on service requirements; tax number remains optional. Existing authentication and professional onboarding are unchanged.

PUT /me/basket/items/{id} accepts type=planning and plan=<TripPlan snapshot>. It validates customer ownership/profile, item ID, payload size, destinations, counts and lodging. Server home_config controls planningFee. Stored paymentStatus is always notCollected; no payments or reservations are created. Existing GET/DELETE basket routes handle planning items. Repeated PUT updates the same owner-scoped item/revision.

Apply database/migration_2026_11_28_planning_basket.sql once. The owner-only planning-setup.php page applies this additive basket enum migration with CSRF protection. It preserves current rows. Payments are explicitly deferred to a future Redsys integration.

Validation: tests/planning-basket-test.php uses local isolated MariaDB, creates synthetic users only, cleans fixtures, checks required billing fields, conditional passport, idempotence, fee authority and rejected malformed/unauthorized requests.
