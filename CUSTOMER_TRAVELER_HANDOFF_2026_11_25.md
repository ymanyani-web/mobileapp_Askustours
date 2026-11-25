# Customer traveler profile


## Customer traveler details — 25 November 2026

Additive, owner-scoped extension; no SQL migration. `GET /me`, `PATCH /me` and authenticated session user include `travelerDetails` for customers only. Common names/contact/billing fields retain their existing flat keys. Extra keys: birthDate, gender (male/female/unspecified), nationality, residenceCountry, documentType (passport/id), documentNumber, documentCountry, documentIssueDate, documentExpiryDate, region, taxNumber. Values are strings, optional when editing a profile, maximum 100 characters. Dates use YYYY-MM-DD; invalid/future birth or issue dates and expiry <= issue are rejected. Empty email/phone becomes SQL NULL. Unknown extra keys are rejected; collaborators cannot write this customer-only object. No verification flag or UID can be changed through it.

Quotes add `requiredTravelerFields`. Driver and guide defaults: firstName, lastName, phone. A trusted service JSON may declare an allowlisted `travelerRequirements` list; unrelated document data is never required by default. Reservation creation revalidates the current service policy and saved owner profile on the server, returning 422 TRAVELER_DETAILS_REQUIRED with travelerDetails.FIELD errors when missing. Existing verified-phone enforcement remains. Reservation snapshots copy only required traveler fields, not the entire optional document profile. Profiles can be cleared by the owner; account deletion follows existing account deletion/backup retention rather than pretending to erase backups immediately.

Flutter reuses the existing traveler form route, lets customers save incomplete details, and opens that form before a booking when required fields are missing. Customer profile displays the entry; professional onboarding is unchanged. Document numbers are excluded from local preview preference serialization. Phone strings and their prefixes use LTR and ASCII digits under Arabic RTL.

Validation: 13 isolated PHP policy/date/ownership-boundary checks, syntax checks, and focused Flutter tests. Production persistence and deployment require verification; passing a local helper test is not proof of live MySQL behavior.
