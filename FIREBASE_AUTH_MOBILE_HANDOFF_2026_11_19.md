# HALA SPAIN Firebase Authentication mobile handoff

## Decision

Firebase Authentication will authenticate mobile customers and collaborators with Phone OTP and Google. Dashboard administrators keep the existing web login.

The mobile application authenticates with Firebase, obtains a Firebase ID token, and exchanges it once with the HALA SPAIN backend. The backend returns the existing `accessToken` and `refreshToken`, so all existing private API requests, refresh behavior, uploads, reservations and owner scoping remain unchanged.

## Firebase Console setup

1. Create a Firebase project for HALA SPAIN.
2. Enable **Phone** and **Google** under Authentication > Sign-in method.
3. Register the Android app using the real application ID and add its SHA-1 and SHA-256 fingerprints.
4. Register the iOS app using the real bundle ID. Configure APNs for phone verification and the reCAPTCHA fallback.
5. Download `google-services.json` for Android and `GoogleService-Info.plist` for iOS into the mobile project. Do not put either file in this backend repository.
6. Add production phone numbers only after Firebase billing, SMS regions, quotas and abuse protection are reviewed. Use Firebase test phone numbers during development.

## Backend activation

Run:

```text
database/migration_2026_11_19_firebase_auth.sql
```

Configure these server values:

```text
FIREBASE_PROJECT_ID=the-firebase-project-id
FIREBASE_WEB_API_KEY=the-project-web-api-key
FIREBASE_TIMEOUT_SECONDS=8
```

The backend validates the Firebase ID token through Google Identity Toolkit. It never trusts a client-supplied UID, email or phone number without that validation.

## Exchange endpoint

`POST /v1/auth/firebase-session`

First-time customer request:

```json
{
  "idToken": "firebase-id-token",
  "firstName": "Sara",
  "lastName": "Lopez",
  "accountRole": "customer"
}
```

First-time collaborator request uses `"accountRole": "partner"`. `firstName` and `lastName` are required when Firebase does not provide a complete display name. After the first successful link, only `idToken` is required.

Success is HTTP 201 and preserves the current session shape:

```json
{
  "data": {
    "accessToken": "backend-access-token",
    "refreshToken": "backend-refresh-token",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "user": {},
    "authentication": {
      "source": "firebase",
      "providers": ["phone", "google.com"]
    }
  },
  "meta": { "requestId": "req_..." }
}
```

Continue sending the backend access token as `Authorization: Bearer ...`. Continue using `POST /auth/session-refresh` for backend session rotation.

## Provider linking rule

Phone and Google must be linked with Firebase Auth `linkWithCredential` for the signed-in Firebase user. Do not independently sign in with Phone and Google and assume the backend will merge unrelated Firebase UIDs. A linked Firebase account has one stable UID and avoids duplicate HALA SPAIN profiles.

## Logout

1. Call `DELETE /auth/session` with the HALA SPAIN access token.
2. Call Firebase `signOut()`.
3. Delete both HALA SPAIN tokens from secure device storage.

## Errors

| Code | Meaning | Mobile behavior |
|---|---|---|
| `FIREBASE_NOT_CONFIGURED` | Backend project settings are missing | Block Firebase login and show service unavailable |
| `FIREBASE_UNAVAILABLE` | Firebase could not be reached | Offer a retry without creating a second account |
| `INVALID_FIREBASE_TOKEN` | ID token expired or is invalid | Refresh/re-authenticate with Firebase and retry once |
| `FIREBASE_IDENTITY_INCOMPLETE` | No verified phone or email was supplied | Require a supported verified provider |
| `FIREBASE_IDENTITY_CONFLICT` | Verified identifiers or UID point to different accounts | Stop and send the case to support; never auto-merge |
| `VALIDATION_ERROR` | Required registration profile fields are missing | Show field-level errors |

## Required verification response

Return one Markdown file named `MOBILE_FIREBASE_AUTH_VERIFICATION_RESPONSE.md` containing:

- Firebase project and app registration status without exposing keys or configuration files.
- Phone OTP tests on real Android and iPhone devices.
- Google sign-in tests on Android and iPhone.
- Phone-to-Google and Google-to-Phone provider-linking tests proving the same Firebase UID and HALA SPAIN user ID.
- First registration, returning login, token refresh, logout and account-switch tests.
- Customer versus collaborator role tests.
- Suspended collaborator and cross-account upload/data isolation tests.
- Every remaining task grouped by mobile, backend, Firebase Console or operations owner.
