# AGENTS.md — Yourlist Backend (`y-backend-server`)

Handover for Cursor Agents and developers joining this repo. Prefer verifying behaviour in code over assuming this file is complete. Do **not** invent columns, endpoints, or business rules.

---

## 1. Project overview

**Yourlist** is a multi-tenant event ticketing platform.

| Actor | Role |
|---|---|
| **Customer** | Browses public events, OTP login, books tickets, wishlists, downloads PDFs |
| **Organisation (organiser)** | Venues, events, tickets/offers, customers, refunds, Stripe Connect, Sites |
| **Platform (super admin)** | Approves organisations, platform settings / permissions |

**Business model (implemented):** customers pay ticket price + platform fee. Organisers earn the discounted ticket amount (not reduced by the fee). Funds settle via Stripe; organiser balances use a ledger with reserved → available after event hold days.

**Main modules:** Auth, Organisations/Staff/Roles, Venues, Events/Tickets/Offers, Public catalog + booking, Orders/Payments/Refunds, Media, Stripe Connect, **Sites** (club / white-label microsites + Vercel custom domains).

---

## 2. Technology stack

| Layer | Choice |
|---|---|
| Language | PHP `^8.3` |
| Framework | Laravel `^13` (`composer.json`) |
| API style | Stateless JSON under `/api/*` |
| Auth | Custom JWT (`firebase/php-jwt`, `AuthenticateJwt`, `config/jwt.php`) |
| DB | MySQL (Eloquent); migrations in `database/migrations/` |
| Payments | Stripe PHP SDK + Connect (`config/stripe.php`, `StripeService`) |
| Domains | Vercel Domains API (`VercelService`, `config/services.php` → `vercel`) |
| Media | `MediaService` + `config/media.php` — drivers: `local`, `cloudinary`, `aws` |
| PDF / QR | `barryvdh/laravel-dompdf`, `bacon/bacon-qr-code` |
| Tests | Pest (`tests/`) |
| Style / static | Pint, Larastan/PHPStan |

Also present: Inertia/Fortify (web admin shell), Log Viewer. **No Agora** integration in this codebase.

---

## 3. Architecture conventions

### Layering (follow existing patterns)

```
routes/api.php
  → Http/Controllers/Api/{Public|Organisation|Platform|Webhooks}
  → FormRequest validation
  → Services/... (business rules; throw ServiceException via Service::fail)
  → Models + Enums
  → Json Resources + ApiResponse
```

- Controllers stay thin: call a service, return `$this->success()` / `$this->failed()`.
- Business logic lives in `app/Services/` (often namespaced `Organisation/`, `Public/`, `Platform/`).
- Domain enums live in `app/Enum/`.
- Shared response shape: `App\Http\Responses\ApiResponse`  
  - Success: `{ success: true, message, data }`  
  - Error: `{ success: false, message, error }`  
  - Lists: prefer `ApiResponse::paginated` / cursor pagination helpers where already used.

### Middleware aliases (`bootstrap/app.php`)

| Alias | Purpose |
|---|---|
| `jwt` | Authenticated user (Bearer JWT) |
| `organisation.approved` | Org must be approved |
| `organisation.permission:{key}` | Org RBAC |
| `platform` | Platform staff user |
| `platform.permission:{key}` | Platform RBAC |

API middleware prepends `ForceJsonResponse`.

### Agent / coding preferences (owner)

- Follow existing structure, naming, and service boundaries.
- Keep code simple; avoid new abstractions unless necessary.
- Reuse existing services/components; inspect before changing.
- Do not invent DB columns, APIs, or rules.
- No unrelated refactors; preserve behaviour.
- Do not commit secrets (`.env`, tokens).
- Only commit / PR when asked.
- Explain important decisions in plain English when changing money, auth, or domain flows.

---

## 4. Authentication & tenancy

### Scopes

1. **Customer** — public OTP login (`/api/public/send-login-otp`, `verify-login-otp`); JWT for booking/orders/wishlist.
2. **Organisation** — `/api/organisation/login`; routes under `/api/organisation/*` require JWT + often `organisation.approved` + permission.
3. **Platform** — `/api/platform/login`; routes under `/api/platform/*` + `platform` middleware.

### Organiser registration

- `/api/organiser/register` → `/api/organiser/verify-email`.
- Organisation created with `approve_status` (platform must approve before most org APIs work).
- Platform: `POST /api/platform/organisation/manage-approve-status`.

### Membership

- `organisations.owner_id` → users.
- Staff: `organiser_staff`, org `roles` / `permissions` / pivots.
- Parallel platform staff/roles/permissions tables exist for super-admin.

JWT config: `JWT_SECRET`, `JWT_TTL` (minutes). Do not log tokens.

---

## 5. Database map (high level)

Do not dump full schemas here — read migrations/models. Important groups:

| Area | Tables (indicative) |
|---|---|
| Identity | `users`, OTPs (`verification_otps`) |
| Org | `organisations`, staff/roles/permissions, `organiser_registrations` |
| Venues | `venues` + address/contact/images/facilities/accessibility/logistics/suitable-for |
| Events | `events`, categories, images, `event_tickets`, `event_ticket_offers` |
| Commerce | `orders`, `order_items`, `payments`, `refunds`, `issued_tickets`, `coupons`, `wishlists` |
| Money | `organiser_accounts`, `organiser_ledger_entries`, Stripe Connect account tables |
| Customers | `organisation_customers` |
| Platform | `platform_settings`, platform staff/roles |
| **Sites** | `site_templates`, `sites`, `site_domains`, `site_banners`, `site_contents`, `news`, `site_news`, `site_events` |

**Multi-tenancy:** almost all organiser data is scoped by `organisation_id`. Always resolve the actor’s organisation in services (see `OrganisationService::getOrganisationDetail`).

---

## 6. API surface (where to look)

Canonical routes: **`routes/api.php`**.

| Prefix | Responsibility |
|---|---|
| `/api/public/*` | Catalog, OTP auth, booking, order status, wishlist |
| `/api/organisation/venue/*` | Venue CRUD + facilities |
| `/api/organisation/event/*` | Events, tickets, offers, per-event orders/refunds |
| `/api/organisation/orders/*` | Issue refund, refund lists |
| `/api/organisation/stripe/*` | Connect account create / onboarding / detail |
| `/api/organisation/site/*` | Sites list/create/update/domain |
| `/api/organisation/domain/*` | Lower-level Vercel connect/status (hostname only; predates Sites) |
| `/api/organisation/customer/*` | Org CRM customers + overview |
| `/api/organisation/role-permission/*`, `staff-member/*` | RBAC + staff |
| `/api/platform/organisation/*` | Approve/list orgs |
| `/api/media/*` | Upload/delete (JWT) |
| Webhooks | `/api/account-webhooks`, `/api/stripe/payment-webhooks`, `/api/vercel/webhooks` |

Request validation: `app/Http/Requests/...`. Messages: `lang/en/messages.php`.

---

## 7. Commerce & money (implemented decisions)

### Platform fee (critical)

Source of truth: `App\Support\CheckoutPricing` + `BookingService`.

**Rule (agreed + implemented):**

1. Subtotal = ticket unit × qty  
2. Apply discount (line discount; live booking currently sets coupon fields to `null` — coupons table exists but checkout does not apply coupons yet)  
3. Platform fee = `%` of **discounted** amount (`platform_settings.booking_platform_fees_percentage`)  
4. **Fee is added to customer total**, not deducted from organiser  
5. Organiser share = discounted ticket amount  

Example: ticket £90, discount £10, fee 5% → fee £4, customer pays £84, organiser earns £80.

Stripe PaymentIntent is created for that same `total_amount`. Do not recalculate fee in the payment webhook.

### Organiser ledger

`OrganiserBalanceService` + `organiser_ledger_entries` / `organiser_accounts`:

| Entry type | Meaning |
|---|---|
| `earning` | Sale paid; amount held (reserved) |
| `payout_released` | After event completed + `booking_payout_hold_days`, moves to available |
| `payout_reserved` / `payout_paid` | Payout request / completed transfer (ledger types exist) |
| `refund` | Reduces organiser entitlement on successful refund |
| `adjustment` | Manual correction |

**Note:** Ledger enum comments still describe an older “fee taken from organiser” example — **ignore those comments**; follow `CheckoutPricing`.

**Partial payouts:** ledger supports reserving a portion of available balance (`PAYOUT_RESERVED`). There is **no** dedicated organiser “request payout” controller route in `routes/api.php` as of this writing — transfer helpers exist on `StripeService`. Treat organiser self-serve payout UX as **partially implemented / planned** unless you find a newer route.

### Refunds

- Org: `POST /api/organisation/orders/{order_id}/issue-refund` (+ permission).
- Stripe refund via `StripeService`; local settlement in `RefundSettlementService` (order status, issued tickets, ledger debit, email).
- Orders can be `partially_refunded`.

### Stripe Connect

- Custom Connect accounts (default GB / GBP — `config/stripe.php`).
- Org endpoints under `/api/organisation/stripe/account/*`.
- Webhooks: account capabilities vs payment intents (separate secrets possible: `STRIPE_PAYMENT_WEBHOOK_SECRET`).

---

## 8. Sites / white-label / domains (implemented + decisions)

### Product intent

Organisers build **Sites** (formerly “Clubsite”): club microsite and white-label. Frontend is expected on **Vercel**; API/backend is separate (e.g. AWS). Custom hostnames are attached to the **Vercel project**, not to the API host. SSL is handled by Vercel for those domains. ACM on EC2 was discussed historically as optional for API — **not** part of current site domain flow.

### Schema decisions (verify in migration `2026_11_01_141600_create_site_tables.php`)

- **`sites`:** identity, template, type, name/slug, status, logo/favicon, **`header`/`footer` JSON** (merged onto site; no separate `site_header_footers` table anymore), audit fields.
- **`site_domains`:** **1:1** with `sites` (`site_id` unique). One hostname per site; same content must not serve on two hosts.
- Supporting CMS tables exist: banners, contents (`page_slug`, `featured_image_url`), news + pivots, site_events.
- Types (`SiteTypeEnum`): `marketplace`, `club`, `white_label`. Create API currently allows **club** and **white_label** only; one **club** site per organisation.

### Site API behaviour (current)

Base: `/api/organisation/site` (approved org JWT).

| Endpoint | Behaviour |
|---|---|
| `GET /list` | Org sites + domain |
| `POST /create` | **Draft only**; stores domain locally; **does not** call Vercel |
| `POST /update/{site_id}` | Assets / branding / optional status. Publish with `status=active` requires `isBrandingComplete()` (logo, favicon, header, footer), then connects domain on Vercel and returns DNS |
| `POST /{site_id}/update-domain` | If **draft**: update `site_domains.hostname` only; message *“Domain updated successfully.”* — **no** DNS payload. If **published**: delete previous hostname on Vercel, add new, return `dns_records` + DNS-oriented message |
| `GET .../domain-status`, `POST .../refresh-domain` | Require `vercel_added`; sync status from Vercel |

Vercel helpers: `VercelService::connectDomain`, `removeDomain`, `getDomainStatus`. Webhook: `POST /api/vercel/webhooks` (handles domain verified/deleted/certificate events).

**Also:** `/api/organisation/domain/connect|status` still exposes raw Vercel connect/status without a `sites` row — older helper; prefer Site APIs for product flows.

### Sites CMS APIs

Tables/models for banners, contents, news exist. **CRUD HTTP APIs for those CMS pieces are not wired in `routes/api.php` yet** — treat as **schema ready / product planned**.

---

## 9. Media

- `POST /api/media/upload`, `DELETE /api/media` (JWT).
- Upload to tmp; site update moves owned tmp paths into `sites/{id}/...` via `MediaService`.
- Driver from `MEDIA_DRIVER` — do not hardcode Cloudinary/AWS in callers.

---

## 10. Integrations checklist

| Integration | Status | Where |
|---|---|---|
| Stripe Payments + Connect | Implemented | `StripeService`, webhooks, org stripe routes |
| Vercel Domains | Implemented | `VercelService`, SiteService, DomainController, Vercel webhook |
| Media (local/Cloudinary/AWS) | Implemented (driver switch) | `MediaService`, `config/media.php` |
| Email / OTP | Implemented | `MailService`, verification OTPs, order/refund mails |
| Ticket PDF | Implemented | `TicketPdfService`, order `tickets_pdf_path` |
| Agora | **Not present** | — |
| ACM / Route53 for sites | Discussed historically; **not** in site flow | Prefer Vercel DNS instructions |

Never commit real secrets. Env keys are named in `config/*.php` — copy from a local `.env`, never paste values into docs.

---

## 11. Implemented vs planned vs open

### Implemented (backed by routes + services)

- Org/customer/platform auth; org approval  
- Venues, events, tickets, offers  
- Public list/detail/tickets; booking + PaymentIntent; order status; wishlists  
- Refunds + settlement + mails  
- Stripe Connect onboarding  
- Ledger earn/release/refund mechanics  
- Sites draft/create/update/publish domain + update-domain + status/refresh + Vercel webhook  
- Media upload pipeline  

### Agreed architectural decisions

- Platform fee **on top of** discounted price; organiser gets discounted ticket amount  
- Sites: draft first; Vercel only on publish / published domain change  
- One hostname per site (`site_domains.site_id` unique)  
- Header/footer JSON on `sites`  
- Cursor/json API envelope via `ApiResponse`  

### Planned / incomplete (do not assume done)

- Full Site CMS APIs (banners, pages, news attach)  
- Coupon application in live checkout (table exists; booking leaves coupon null)  
- Organiser self-serve payout request UI/API (ledger types ready; no obvious route)  
- Marketplace site type creation  
- ACM/API custom domain (out of scope for Vercel microsites)  

### Known discrepancies / clarify if needed

1. `OrganiserLedgerEntryTypeEnum` docblocks still describe fee-from-organiser math — **code path uses `CheckoutPricing`**.  
2. Dual domain entrypoints: Site APIs vs `/organisation/domain/*`.  
3. Compaction/summaries of older chats may omit details; **trust migrations + services** when in doubt.

---

## 12. How to work in this repo

1. Read `routes/api.php` and the matching `*Service` before changing behaviour.  
2. Mirror nearby FormRequest + Resource + message keys.  
3. Money/domain changes need careful idempotency (ledger keys, webhook handlers).  
4. Prefer extending `SiteService` / `VercelService` over new parallel domain stacks.  
5. Refresh a **single** migration only with care (`migrate:refresh --path=...` can affect migration bookkeeping — prefer targeted rollback of that migration’s tables when iterating locally).  
6. Run focused Pest tests when touching checkout, refunds, or events.

---

## 13. Quick file index

| Concern | Start here |
|---|---|
| Routes | `routes/api.php` |
| API envelope | `app/Http/Responses/ApiResponse.php` |
| Checkout math | `app/Support/CheckoutPricing.php` |
| Booking | `app/Services/Public/BookingService.php` |
| Organiser money | `app/Services/OrganiserBalanceService.php` |
| Refunds | `app/Services/Organisation/OrderService.php`, `RefundSettlementService.php` |
| Stripe | `app/Services/StripeService.php`, `config/stripe.php` |
| Sites | `app/Services/Organisation/SiteService.php` |
| Vercel | `app/Services/VercelService.php` |
| Media | `app/Services/MediaService.php`, `config/media.php` |
| Site schema | `database/migrations/2026_11_01_141600_create_site_tables.php` |

---

*Last verified against this repository’s source (Laravel API, migrations, and services). Update this file when you make durable product or architecture decisions.*
