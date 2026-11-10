# FTS Google Business Profile Access Onboarding

A small Laravel 13 app for Firm Tech Services (FTS). Staff add a client, copy a unique link and send it. The client signs in with Google, picks their business, approves one request, and FTS receives **Manager** access to the profile. The dashboard shows the status until Google confirms the access.

```
Add client -> Copy link -> Client signs in with Google -> Picks business -> Authorizes
           -> Invitation sent to the FTS Location Group -> Verified with Google -> Connected
```

There is also a manual **agency request** fallback that works with no Google API approval at all.

## What is built

| Area | Where |
| --- | --- |
| Staff dashboard, client pages, settings | `app/Http/Controllers/Admin`, `resources/views/admin` |
| Client onboarding (no login) | `app/Http/Controllers/Onboarding`, `resources/views/onboarding` |
| Google OAuth callback (one redirect URI) | `app/Http/Controllers/GoogleCallbackController.php` |
| Google services | `app/Services/Google/*` |
| Status state machine | `app/Enums/ClientStatus.php` |
| Background check and scheduler | `app/Jobs/VerifyAccessRequestJob.php`, `routes/console.php` |
| Tests (Pest, Google mocked) | `tests/` |

Google calls use Laravel's HTTP client, not `google/apiclient`. The Business Profile management APIs are plain REST, the OAuth flow is three requests, and the official package would pull in a large dependency tree for no benefit here. Everything runs server side.

## Requirements

PHP 8.3+, Composer, MySQL 5.7+/8, Node 20+ (to build CSS/JS), a public HTTPS domain for production.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* and the variables below in .env
php artisan migrate
npm install && npm run build
php artisan gbp:create-admin
```

`gbp:create-admin` creates (or updates) a staff login. There is no public registration. Only users with `is_admin = 1` can reach `/admin`.

## Environment variables

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public URL. Must be `https://...` in production. |
| `GBP_AGENCY_NAME`, `GBP_AGENCY_SHORT_NAME` | Branding shown to clients. |
| `GBP_SUPPORT_EMAIL`, `GBP_SUPPORT_PHONE` | Contact details on client pages. |
| `GBP_LINK_TTL_DAYS` | Link lifetime (default 14). |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | OAuth client from Google Cloud. |
| `GOOGLE_REDIRECT_URI` | Optional. Defaults to `APP_URL/google/callback`. |
| `TRUSTED_PROXIES` | Proxy IPs or `*` when behind a load balancer. |
| `MAIL_*`, `QUEUE_CONNECTION` | Mail and queue settings (emails and checks are queued). |

Google secrets live only in `.env`. The agency Location Group ID is **not** in code. It is stored in the database and edited on **Settings**.

## Google setup (do this once)

Read these first; Google changes them: [prerequisites](https://developers.google.com/my-business/content/prereqs), [basic setup](https://developers.google.com/my-business/content/basic-setup), [OAuth](https://developers.google.com/my-business/content/implement-oauth), [locations.admins](https://developers.google.com/my-business/reference/accountmanagement/rest/v1/locations.admins).

1. **Agency organization and Location Group.** Create the FTS organization at business.google.com/agencysignup, then a business group (Location Group) inside it. Note the Location Group's account ID. The app never creates organizations or groups.
2. **Google Cloud project.** Create a project and note its **project number**.
3. **Request Basic API access.** Enabling an API is not enough. Submit the "Application for Basic API Access" form from the prerequisites page, using an email that owns or manages an eligible profile (confirmed and active for 60+ days, with a website). Until approved, the project quota is **0 QPM** and every call fails. Approval shows **300 QPM**.
4. **Enable the APIs** in the project: Google My Business API (visible only after approval), My Business Account Management API, My Business Business Information API, plus Lodging, Place Actions, Notifications and Verifications as listed in the basic setup guide.
5. **OAuth consent screen.** App name, support email, logo, privacy policy URL and terms URL on a domain you verify. Scope: `https://www.googleapis.com/auth/business.manage`. This is a sensitive scope, so for real clients you need **Google's OAuth verification**. Unverified apps show a warning screen and are capped at 100 test users.
6. **OAuth client ID.** Type "Web application". Authorized redirect URI: the exact value shown on **Settings > Google Cloud values**, e.g. `https://your-domain.com/google/callback`. Put the ID and secret in `.env`.
7. In the app open **Settings**: enter organization name/ID, the Location Group ID, the project number, set approval to **Approved** once Google confirms, and optionally press **Connect FTS Google account** (use the account that manages the organization). The page then lists detected groups so you can pick the Location Group instead of typing it, and shows a readiness checklist.

## How the automatic flow works

1. Client opens `/gbp/connect/{token}`. The token is random (256 bits). Only its SHA-256 hash is used for lookup. An encrypted copy lets staff copy the link again.
2. "Continue with Google" starts the server-side authorization-code flow with `access_type=offline`, a single-use `state` bound to the session, and `prompt=select_account consent`. Offline access is needed because status checks and retries happen later.
3. After the callback the app lists accounts (`accounts.list`) and locations (`accounts/{id}/locations`). One location is auto-selected with a confirmation step; several are shown as a list; none shows a friendly explanation.
4. After an explicit checkbox and button, the app calls, **with the client's own token**:

   ```
   POST https://mybusinessaccountmanagement.googleapis.com/v1/locations/{locationId}/admins
   {"account": "accounts/FTS_LOCATION_GROUP_ID", "role": "MANAGER"}
   ```

   Per Google, when `account` is set the `admin` email is ignored, so no email is sent. A Location Group ID is never mixed up with a personal account ID.
5. The result is **verified** by reading `locations/{id}/admins` with the client token, or by checking the Location Group's locations with the FTS token. `CONNECTED` is set only when Google shows the group as Manager (or higher) with no pending invitation. Otherwise the client stays `PENDING_APPROVAL`.
6. Once connected, the client's Google token is revoked and deleted.

**Agency-side approval.** An invitation to a Location Group may need FTS to approve it. With the FTS Google account connected, the **Invitations** tab lists them with an Approve button (`accounts/{id}/invitations` and `:accept`). Approving re-checks Google at once and moves the matching client to **Connected** when Google confirms the access. Without the connected account, approve in Business Profile Manager (Manage invites > Approve), then press **Refresh status** on the client. The scheduler also checks every 30 minutes.

**Who can invite.** Google only lets an Owner or Primary Owner of the location add a manager. A client without that role gets a failure (`PERMISSION_DENIED`) and staff see a plain-English reason. The client sees only the friendly error message.

## Manual agency fallback

Pick **Agency request (manual)** when creating a client (or switch on the client page). Steps shown in the app: open Business Profile Manager (Manage invites > Request access), search the business, choose the FTS business group, send. Then press **I've sent the request**. The client page becomes "awaiting approval" and the client gets a branded page (and optional email) explaining how to approve. **Check and mark connected** asks Google first, using the connected FTS account to look for the business inside the Location Group. If Google can't be asked, staff must tick a box confirming they saw the access, and the confirmation is logged under their name. Google offers no public API for starting this request, so none is faked.

## Statuses

`NEW`, `LINK_SENT`, `LINK_OPENED`, `OAUTH_CONNECTED`, `PROFILE_SELECTED`, `REQUEST_SUBMITTED`, `PENDING_APPROVAL`, `CONNECTED`, `FAILED`, `EXPIRED`, `REJECTED`, `REVOKED`. Allowed moves are defined in `ClientStatus::allowedTransitions()`; `transitionTo()` throws on anything else.

## Security notes

HTTPS forced in production; session-bound single-use OAuth state; CSRF on every form; SHA-256 hashed link tokens; links expire, can be revoked, and replace each other; unknown/expired/revoked tokens return one identical page; rate limits on onboarding (40/min/IP) and login (5/min); Google tokens encrypted with `APP_KEY` and hidden from serialization; tokens never logged; agency and client tokens are never interchangeable (enforced in code and tested); raw Google errors never reach clients; `Referrer-Policy: no-referrer` and `noindex` on onboarding pages. **Back up `APP_KEY`.** Losing it makes stored tokens and link copies unreadable.

## Production deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Serve over HTTPS with the web root at `public/`. Keep the sessions and queue tables (`database` drivers) or switch to Redis.

**Queue worker** (emails and verification jobs), kept alive by Supervisor/systemd:

```bash
php artisan queue:work --tries=2 --max-time=3600
```

**Scheduler**, one cron entry:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

It runs `gbp:verify-pending` every 30 minutes (only for requests with valid authorization; others report "could not verify") and `gbp:expire-links` hourly.

## Tests

```bash
php artisan test
```

Pest feature tests use an in-memory database and mock every Google endpoint with `Http::fake` (`tests/Support/FakeGoogle.php`). They cover: client creation, link generation, no-login access, expired and revoked links, token enumeration, one client reaching another's record, consent granted and denied, forged or reused state, multiple profiles and selection, authorization checkbox, the exact invitation request and token used, pending/accepted/missing verification, duplicate prevention, API not approved or not enabled, rate limits, revoked tokens, agency invitation approval, the manual fallback, and the state machine.

Google offers no sandbox. Before going live, run one **controlled live test** with a real test profile you own: create a client, complete the flow, and confirm in Business Profile Manager. Live behavior not provable from documentation alone is listed under Known limits.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| Settings says "quota is 0" / "rate limit" on the first call | Basic API access not approved yet. Wait for Google, then set approval to Approved. |
| "API not enabled" | Enable the missing Business Profile API in the Cloud project. |
| `redirect_uri_mismatch` at Google | The redirect URI in Cloud Console must match **Settings** exactly, scheme and path included. |
| "Google hasn't verified this app" screen | OAuth verification for the sensitive scope is pending; add clients as test users meanwhile. |
| Client sees the friendly error | Open the client page: the access card shows the exact cause (permission, approval, rate limit, revoked token). |
| "Retry failed request" says authorization is gone | Press **Regenerate link** and ask the client to connect again. |
| Stuck on "awaiting approval" | Approve the invitation (Settings > Invitations, or Business Profile Manager), then **Refresh status**. |
| Links open on localhost | `APP_URL` is wrong; set it and run `php artisan config:cache`. |

## Known limits (be aware)

- **Not tested against live Google.** All Google behavior is unit/feature-tested with mocked responses built from Google's reference docs. The accept-invitation URL (`POST /v1/{accounts/*/invitations/*}:accept`) and the exact shape of the invitation list were not confirmed on a live account. Check them in your controlled test.
- Whether an invitation to a Location Group needs agency-side approval can depend on your organization setup. Both outcomes are handled: if Google shows the group as an active Manager right away, it is verified and marked connected; if not, it is pending.
- The invitation list from Google identifies a location by business name and address, not its ID, so matching pending invitations to a client uses the name.
- If the client's authorization is revoked or expires before the request is confirmed, checks can only run from the agency side. Connect the FTS Google account in Settings so verification keeps working in that case.
