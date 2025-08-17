# Photobooth Web App — Requirements & Task Plan (Laravel + Inertia.js)

> **Goal:** Build an open‑source photobooth web app using **Laravel** (backend) and **Inertia.js** (Vue by default) that can: (1) capture photos from the browser, (2) let users add decorative borders/frames, (3) **require payment via QRIS** before download/print, and (4) make **borders easy to contribute** by non‑programmers.

**Primary personas**

* **Guest/User (Kiosk mode):** takes a photo, picks a border, pays via QR, downloads or prints after payment.
* **Operator:** manages sessions on a booth, monitors payments/prints.
* **Admin:** configures prices, payment gateway keys, manages borders, reports.

**Non‑goals (v1):** multi‑tenant SaaS, offline payments, face detection/AR stickers, automatic background removal.

---

## 1) Technical Stack

* **Backend:** Laravel 11, PHP 8.2+
* **Frontend:** Inertia.js + Vue 3 + Vite, Tailwind CSS, Pinia (state)

  * (Optional alternative: React instead of Vue — keep Inertia layer stable.)
* **DB:** MySQL 8 / MariaDB 10.6+ (or PostgreSQL 14+)
* **Auth:** Laravel Breeze (Inertia stack)
* **Permissions:** spatie/laravel-permission
* **Image processing:** ext-imagick (preferred) or GD + `intervention/image`
* **Queue:** Redis + Laravel Queue for compositing tasks
* **Payments:** Pluggable gateway interface; example driver for Midtrans/Xendit QRIS (dynamic QR)
* **Storage:** local disk (public) for dev; S3-compatible in prod
* **Env & build:** Node 20+, PNPM/Yarn/NPM; Docker dev env (optional)

---

## 2) High-Level Architecture

```
Browser (Kiosk) ── Inertia/Vue ─┐
                               │  REST (JSON) + Webhooks + SSE/WS (status updates)
Laravel Controllers/Routes ─────┤
  ├─ PaymentGateway Interface    │
  │   ├─ MidtransDriver          │
  │   └─ XenditDriver            │
  ├─ Jobs: ComposeFinalImage     │
  ├─ Events: PaymentUpdated      │
  └─ Storage: Photos/Borders     │
DB (sessions, photos, payments, borders, roles)
```

**Core flows**

1. **Capture** → **Preview** → **Pick Border** → **Compose Final** (server) → **Checkout** → **Show QR** → **Webhook confirms** → **Unlock download/print**.
2. Admin manages **Borders** via drag‑and‑drop ZIP packs.

---

## 3) Roles & Permissions

Use `spatie/laravel-permission`.

**Roles**

* `admin` — full access
* `operator` — kiosk/session/payment monitoring, local print
* `guest` — kiosk flow only

**Example permissions** (expandable):

* `borders.view`, `borders.create`, `borders.update`, `borders.delete`
* `sessions.create`, `sessions.update`, `sessions.view`
* `payments.view`, `payments.refund`
* `settings.update`

Seed default roles + permissions; assign first user as `admin`.

---

## 4) Data Model (ER & Tables)

### Entities

* **users**: id, name, email, password, role
* **photo\_sessions**: id, code, status (`idle|capturing|review|checkout|paid|completed|expired`), total\_price, currency (`IDR`), kiosk\_label, expires\_at
* **photos**: id, session\_id, original\_path, processed\_path, width, height, meta (JSON)
* **borders**: id, slug, name, category\_id, aspect\_ratio, preview\_path, file\_path (PNG w/ transparency), manifest (JSON), is\_active
* **border\_categories**: id, name, slug
* **payments**: id, session\_id, provider (`midtrans|xendit|mock`), provider\_txn\_id, method (`qris`), amount, currency, status (`pending|paid|expired|failed|refunded`), qr\_string (EMV), qr\_image\_url, payload (JSON), expires\_at, paid\_at
* **print\_jobs** (optional v1): id, session\_id, copies, paper\_size, status, printed\_at, notes
* **settings**: key, value (JSON), group (e.g., `pricing`, `payment`, `kiosk`)

### Indices & constraints

* Unique: `photo_sessions.code`
* FK: photos.session\_id → sessions.id; payments.session\_id → sessions.id
* TTL housekeeping: cron to expire stale `pending` sessions/payments

**Migrations** must set enum values as constants in code for reuse.

---

## 5) Payment (QRIS) — Design

**Abstraction:**

* `PaymentGateway` interface: `createQrPayment(Session $s): Payment`, `getStatus(Payment $p): PaymentStatusDTO`, `handleWebhook(Request $r): PaymentUpdateDTO`.
* Drivers: `MidtransDriver`, `XenditDriver`, `MockDriver` (for E2E tests).

**Checkout flow:**

1. Client requests `POST /sessions/{code}/checkout` → server computes price (e.g., per photo) and creates `payments.pending`.
2. Server calls `gateway.createQrPayment()` → returns `qr_string` (EMV) and/or `qr_image_url`, `expires_at`.
3. Frontend displays QR (use provided PNG/URL; fallback: generate canvas QR from EMV string). Show countdown.
4. **Webhook** hits `/webhooks/{provider}`; verify signature; update `payments.status` to `paid` and mark session `paid`.
5. Client polls or receives SSE/WS event → unlock download/print.

**Edge cases**

* Payment expires → regenerate QR.
* Duplicate webhooks → idempotent updates by `provider_txn_id`.
* Amount mismatch → mark `failed` and show support prompt.

**Settings**

* `payment.price_per_photo` (integer IDR)
* `payment.driver` (`midtrans|xendit|mock`)
* Provider keys/secrets in `.env` (never in DB)

---

## 6) Camera & Capture

**Primary method (recommended):** Browser camera via `MediaDevices.getUserMedia` (video) for live preview + capture to canvas.

**Experimental (optional):** WebUSB for USB cameras/DSLRs that expose a compatible interface. Provide a `CameraProvider` interface with a default `BrowserCameraProvider` and stub `WebUSBCameraProvider` (feature‑flagged).

**Client features**

* Select camera device; resolution presets (e.g., 1920×1080)
* Countdown, shutter sound
* Retake / keep
* Crop/zoom UI (fit chosen border aspect ratio)

---

## 7) Borders/Frames — Contribution‑Friendly

**Border Pack format** (designer‑friendly):

```
/border-packs/
  └─ <pack-slug>/
       manifest.json          # see schema below
       preview.png            # small preview (600px)
       frame.png              # transparent PNG, same aspect ratio
       README.txt             # (optional) design notes/credits
```

\`\`\*\* schema\*\*

```json
{
  "name": "Floral Pink 1",
  "slug": "floral-pink-1",
  "category": "floral",
  "aspect_ratio": "3:4",          
  "safe_zone": { "x": 60, "y": 80, "width": 1080, "height": 1440 },
  "author": "Contributor Name",
  "license": "CC-BY-4.0",
  "version": "1.0.0"
}
```

**Admin UX**

* Drag‑and‑drop ZIP upload → server validates manifest, stores files, generates preview, creates `borders` row.
* Toggle active/inactive; categorize; reorder display.

**Runtime UX**

* Border Picker grid → category filter, search, instant preview overlay.

**Validation**

* Aspect ratio of `frame.png` must match manifest.
* Enforce max dimensions (e.g., 2400×3200) to limit memory use.

---

## 8) Image Compositing Pipeline

1. **Client Preview:** live overlay (CSS/Canvas) for user confirmation.
2. **Server Compose (final):** queue job `ComposeFinalImage`:

   * Load original photo
   * Crop/scale to `aspect_ratio`
   * Overlay `frame.png` (alpha) into final canvas
   * Write to `processed_path` (JPEG quality 90), store width/height
3. Return signed URL for preview (watermarked until paid; see below)

**Watermarking (pre‑payment)**

* Apply semi‑transparent diagonal text (e.g., `PAY TO UNLOCK`) or subtle blur.
* After payment, regenerate without watermark or strip watermark layer.

---

## 9) Download & Print (Pay‑to‑Unlock)

* **Gatekeeping rule:** `photo_sessions.status === 'paid'` to enable download + print buttons.
* **Download:** generate short‑lived signed URL (1–5 minutes). Filename includes session code + border slug.
* **Print (basic):** open a print‑optimized HTML with the unlocked image (CSS `@media print`). Operator confirms OS print dialog.
* **Print (advanced, optional):** local "Print Bridge" (Electron or Node) to auto‑print on a specific printer via WebSocket.

---

## 10) Kiosk Mode

* Single full‑screen route: `/kiosk`
* Idle timeout → reset to start screen + clear in‑memory state
* Large buttons, stepper UI, no admin links
* Optional kiosk PIN to exit kiosk mode

---

## 11) API & Routes (draft)

### Web (Inertia) Routes

* `GET /` → Landing / Start Booth
* `GET /kiosk` → Photobooth UI (capture → pick border → checkout)
* `GET /admin` → Dashboard (auth: admin)
* `GET /admin/borders` → CRUD borders
* `GET /admin/payments` → List
* `GET /admin/sessions` → List
* `GET /admin/settings` → Price/driver keys

### JSON API (prefix `/api/v1`)

* `POST /sessions` → start session `{ kiosk_label }` → `{ code }`
* `POST /sessions/{code}/photos` → upload captured photo (base64 or blob)
* `POST /sessions/{code}/border` → select border `{ border_id }`
* `POST /sessions/{code}/compose` → enqueue composition → `{ preview_url }`
* `POST /sessions/{code}/checkout` → create QR payment → `{ qr_image_url|qr_string, expires_at }`
* `GET /sessions/{code}/status` → `{ session_status, payment_status }`
* `POST /sessions/{code}/regenerate` → new QR if expired
* `GET /sessions/{code}/download` → signed URL (requires paid)

### Webhooks

* `POST /webhooks/midtrans`
* `POST /webhooks/xendit`

**All webhooks** must:

* Verify signature/headers
* Be idempotent (by `provider_txn_id`)
* Update `payments` + fire `PaymentUpdated` event

---

## 12) Frontend Components (Vue + Inertia)

* `KioskLayout.vue` — fullscreen container
* `CameraView.vue` — device select, preview, capture, countdown
* `BorderPicker.vue` — grid, category tabs, search
* `EditorCanvas.vue` — crop/zoom within safe zone
* `CheckoutModal.vue` — shows QR image/canvas, countdown, status
* `SuccessView.vue` — download/print buttons
* `Admin/Borders/*.vue` — list, upload ZIP, validate
* `Admin/Settings.vue` — price + gateway settings
* `store/session.ts` (Pinia) — current session state

---

## 13) Settings & Environment

`.env` keys (example):

```
APP_ENV=local
APP_URL=http://localhost

FILESYSTEM_DISK=public
IMAGE_DRIVER=imagick

PAYMENT_DRIVER=midtrans   # xendit|midtrans|mock
PRICE_PER_PHOTO=15000

MIDTRANS_SERVER_KEY=...
MIDTRANS_CLIENT_KEY=...
MIDTRANS_IS_SANDBOX=true

XENDIT_SECRET_KEY=...
XENDIT_PUBLIC_KEY=...
```

**Settings UI** persists to `settings` table (non‑secret). Secrets read from `.env` only.

---

## 14) Security & Privacy

* CSRF on all web routes; webhook routes exempt but verified via HMAC/signature
* CORS: allow same origin; kiosk runs from same host
* Restrict file MIME types; scan image headers
* Rate‑limit public endpoints
* Auto‑purge originals after N days (configurable)
* No analytics PII; respect camera permissions; HTTPS only in prod

---

## 15) Accessibility & i18n

* Large tap targets in kiosk
* Color‑contrast compliant
* Language toggle: EN/ID; copy strings to `lang/*`


