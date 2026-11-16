The mobile app in this workspace is already complete and works correctly with static/mock data. Do **not** modify the app, connect APIs, refactor code, or implement a backend.

Your only task is to inspect the entire mobile app and create one detailed Markdown specification file named:

`BACKEND_API_REQUIREMENTS.md`

This document will be given to a separate backend developer who has not seen the mobile app. It must explain exactly what the app contains and every API the backend must provide so that all static/mock data can later be replaced by real database data.

## Inspection requirements

Inspect the actual source code, not only visible screen names. Review:

- All screens, tabs, routes, dialogs, and user actions.
- Static data, mock services, fixtures, constants, and sample JSON.
- Models, interfaces, DTOs, enums, and serialization code.
- Forms, validation rules, filters, sorting, search, pagination, and date selection.
- Hotel browsing and hotel reservation flows.
- Car-with-driver browsing and reservation flows.
- Client/user information and authentication flows, if present.
- Collaborator-related data such as hotels, drivers, vehicle owners, or transport companies, if present.
- Reservations, statuses, pricing, availability, payments, notifications, favorites, reviews, promotions, and uploads, but only when they actually exist in the app.
- Every place where the app currently reads, creates, updates, or deletes static data.

Do not invent features. Clearly label anything that is uncertain and reference the source file that caused the uncertainty.

## Required structure of `BACKEND_API_REQUIREMENTS.md`

### 1. App overview

Explain the app's purpose, user types, main navigation, and complete feature list in plain language.

### 2. Screen and data inventory

Create a table with:

| Screen or feature | User action | Static/mock data currently used | Model/source file | Backend operation needed |

Include every screen and important dialog. Use repository-relative file paths and symbol names where possible.

### 3. Data models

Document every backend-relevant model separately, including clients, collaborators, hotels, vehicles, drivers, reservations, and any other model found in the app.

For every field state:

- Field name used by the app.
- JSON key recommended for the API.
- Data type.
- Required or optional.
- Nullable or non-nullable.
- Example value.
- Allowed enum values.
- Validation or formatting rules.
- Whether the value comes from the client or must be calculated by the backend.

Do not omit nested objects or list fields.

### 4. Complete API endpoint inventory

Create a table with:

| Feature | Method | Proposed endpoint | Purpose | Authentication | Request data | Success response | Error responses |

Include every endpoint needed by the existing app. Typical categories may include catalog/home data, hotels, vehicles with drivers, availability, clients, collaborators, reservations, authentication, profile, favorites, payments, reviews, promotions, and notifications—but include a category only if the app contains that behavior.

Use a consistent versioned REST prefix such as `/api/v1`. Recommend one endpoint design when the app does not already define one.

### 5. Detailed API contracts

For **every endpoint** from the inventory, provide all of the following:

1. Purpose and the screen/action that calls it.
2. HTTP method and full path.
3. Authentication and required headers.
4. Path parameters.
5. Query parameters, including search, filters, sort, and pagination.
6. Complete request JSON example, or state that there is no request body.
7. Complete successful response JSON example using realistic data.
8. HTTP success status code.
9. Complete validation-error response JSON example.
10. Other expected errors such as `401`, `403`, `404`, `409`, or `500`.
11. Notes explaining how the response maps to the app's existing model.

Never use placeholders such as `etc.`, `other fields`, or `...` inside JSON examples. Show every field required by the app.

### 6. Required reservation contracts

Give especially detailed contracts for these two flows if they exist:

- Hotel reservation.
- Car-with-driver reservation.

For each flow document:

- Catalog/details request.
- Availability or quote request, if required by the current UI.
- Reservation creation payload.
- Price, tax, discount, currency, and total fields.
- Client/contact information.
- Dates and times, including format and timezone.
- Guest/passenger count.
- Pickup and drop-off information for car bookings.
- Room/guest information for hotel bookings.
- Payment information if the app collects it.
- Reservation confirmation response.
- Reservation detail/history response.
- Cancellation or modification request if supported.
- Every possible reservation status used by the app.

Include one complete realistic request and response example for a hotel reservation and one for a car-with-driver reservation.

### 7. Admin dashboard requirements

Based only on mobile-app behavior, list what an administrator must be able to manage manually in a dashboard. Cover the data the app consumes and the actions needed to handle reservations.

Use this table:

| Admin module | Records managed | Create | View | Update | Delete/deactivate | Reservation actions |

Explain which mobile-app data must immediately reflect dashboard changes.

### 8. Shared API conventions

Define a consistent recommendation for:

- Base URL and API versioning.
- JSON naming convention.
- Date, time, and timezone formats.
- Money and currency representation.
- IDs.
- Pagination metadata.
- Authentication headers.
- Success response envelope.
- Validation error envelope with field-level errors.
- General error envelope.
- File/image URL handling.
- Idempotency for reservation and payment creation.

Provide exact JSON examples for the success, validation-error, unauthorized, not-found, and conflict formats.

### 9. Main request flows

Add concise Mermaid `sequenceDiagram` diagrams for:

- Loading the app's home/catalog data.
- Creating a hotel reservation.
- Creating a car-with-driver reservation.

Only include flows that exist in the app.

### 10. Backend implementation checklist

Finish with a prioritized checklist grouped into:

- Required for the first working integration.
- Required before production.
- Optional or future features already hinted at by the app.

Also include:

- Total number of required endpoints.
- Endpoints grouped by feature.
- Database entities implied by the app.
- Open questions that the backend developer must clarify.

## Important rules

- Create only `BACKEND_API_REQUIREMENTS.md`.
- Do not change any mobile-app source file.
- Do not implement networking or replace static data.
- Do not assume any backend currently exists.
- Derive requirements from evidence in this repository.
- Reference relevant source files for every endpoint or feature.
- Keep request and response field names consistent throughout the document.
- Use realistic, complete JSON payloads that the backend developer can implement directly.
- If the same data appears with inconsistent field names or types, document the conflict and recommend one normalized API contract.

After creating the file, reply only with:

1. The path to `BACKEND_API_REQUIREMENTS.md`.
2. The number of screens/features analyzed.
3. The number of API endpoints required.
4. The most important unresolved questions.

---
