# AI Agent Guide — Photobooth Station (FULL)

## Active Direction (April 13, 2026)
- Main focus is application feature development first.
- Scramble/OpenAPI work is paused temporarily and will be resumed later.
- Do not spend sprint time on additional Scramble enhancements until app priority tasks are done.

## Progress Log (April 13, 2026)
- Seeder baseline stabilized for repeat runs (`db:seed` can be executed multiple times safely).
- Added default `PrinterSeeder` and wired it into `DatabaseSeeder`.
- Existing seeders now preserve primary IDs and avoid duplicate data in unique tables.
- Added `DemoWorkflowSeeder` and wired it into `DatabaseSeeder` to populate visual app data.
- Demo data now covers session lifecycle (uploaded/editing/ready/queued/failed/printed), thumbnails, rendered output, print orders, queue jobs, and print logs.
- Added repeat-run guard test for demo seeding (`DemoWorkflowSeederTest`) so AI agents can safely continue from seeded visual state.
- Template UI iteration started:
  - quick filters (category, paper size, slot count),
  - interactive slot highlight on template detail canvas,
  - `Gunakan Template` action that stores preferred template and redirects to sessions flow.
  - template detail enhancement:
    - indicator `Template aktif dari library`,
    - slot photo placement preview (`Letak Foto Slot`),
    - custom PNG overlay upload with show/hide toggle and opacity control.
  - session editor enhancement:
    - drag-and-drop framing on canvas slot preview for direct custom photo placement,
    - clearer letak controls naming (`Letak Foto X/Y`) while preserving slider + nudge controls.
  - template layout editor enhancement:
    - API endpoint to update slot layout (`/api/editor/templates/{template}/slots`),
    - template detail now supports direct slot drag on canvas,
    - slot specification panel now has editable x/y/width/height/rotation/border radius with save/reset draft flow.
  - layout precision controls:
    - snap-to-grid toggle with grid size selector,
    - lock aspect ratio toggle for slot resize operations.
  - template management:
    - add/remove slot controls on template detail,
    - create template flow on Templates index (form + API).
  - template management v2:
    - rename template inline + update status (active/archived),
    - duplicate template with slot cloning,
    - delete template with confirmation name + optional reason (audit log recorded),
    - list filtering by status + status badge on cards,
    - `updated_by` tracking for template activity (migration + API payload).
  - template overlay persistence:
    - upload overlay PNG stored in `asset_files` + `template_assets`,
    - overlay URL included in template API so overlay stays after refresh,
    - overlay upload replaces previous overlay file.
- Default seeded accounts:
  - Admin: `admin@photobooth.local`
  - Print Agent: `agent@photobooth.local`
- Default seeded core resources:
  - Station: `STATION-01`
  - Device: `PB-DEVICE-01`
  - Printer: `PRINTER-01`
  - Template: `TPL-2SLOT` with slot 1 and slot 2
- Next app-facing step:
  - continue UI iteration on dashboard/session/queue/printer pages now that visual demo data is available by default.
- Dashboard UI iteration:
  - Added `Recent Sessions` and `Recent Print Orders` panels on dashboard for quick visual overview.
- Sessions UI iteration:
  - Added clickable status summary chips + total count.
  - Added "menampilkan X dari Y sesi" counter.
- Print Queue UI iteration:
  - Added status summary chips (clickable) with total count.
  - Added "menampilkan X dari Y job" counter + highlight failed rows.
- Printers UI iteration:
  - Added status summary chips + search/filter + refresh timer.
  - Added "menampilkan X dari Y printer" counter.
- Print Orders UI iteration:
  - Added status summary chips + search/filter + refresh timer.
  - Added "menampilkan X dari Y order" counter + currency formatting.
- Print Logs UI iteration:
  - Added level summary chips + refresh timer + result counter.
  - Highlight error logs for faster scanning.
- Print Order Detail UI iteration:
  - Added refresh timer + last synced.
  - Added currency formatting on totals.
- Printer Detail UI iteration:
  - Added refresh timer + last synced.
  - Added recent jobs counter + failed job highlight.
- Session Detail UI iteration:
  - Added last synced info.
  - Added photo count badge in library header.
- Session editor fix:
  - Allow reusing the same photo across multiple slots (no forced swap).
- Session editor enhancement:
  - Added "Duplicate Next" to copy a photo into the next empty slot.
- Session editor polish:
  - Added Render Status summary (edit job version, active render, slot count).
- Voucher/Skip (Enterprise):
  - Added session voucher table with apply/revoke tracking.
  - Session detail now supports voucher apply + revoke UI.
- Seeder:
  - SessionVoucherSeeder adds demo voucher data for demo sessions.
  - VoucherLibrarySeeder adds master voucher code examples across types (`promo`, `skip`, `free`, `override`) with realistic validity/discount usage.
  - SessionVoucherSeeder now demonstrates voucher usage mapping to multiple demo sessions for each voucher type.
- Voucher UI polish:
  - Added voucher summary (total/applied/revoked) and status filter chips in Session Detail.
- Voucher Management UI:
  - Added dedicated `Vouchers` menu in sidebar.
  - Added page `/vouchers` for master voucher library management (pre-payment flow).
  - Added editor API `/api/editor/voucher-library` (list/create/update/deactivate).
  - `Valid From/Until` now uses date-only format `DD-MM-YYYY`.
  - Added discount settings on master voucher (`discount_type`, `discount_value`, `max_discount_amount`, `min_purchase_amount`).
  - Added API endpoints `/api/editor/vouchers` (list/create) and `/api/editor/vouchers/{voucher}/revoke`.
  - Added tests for vouchers page and management API workflow.
  - Added `Payment Quote Simulator` on Voucher page (operator can test subtotal + voucher before Android flow).
  - Added editor quote API `/api/editor/voucher-library/quote`.
  - UI voucher management polish:
    - nominal quote dan diskon tampil dalam format Rupiah,
    - tombol `Simulate` per voucher card untuk auto-fill kode ke Quote Simulator,
    - tombol `Reset Quote` + validasi subtotal agar simulasi operator lebih cepat dan minim input salah.
    - quick action `Copy Code` di voucher card.
    - indicator kesehatan voucher di list (`Valid`, `Expired`, `Not Started`, `Usage Full`, `Inactive`) untuk membantu operator sebelum dipakai di Android.
- Device pre-payment voucher check:
  - Added endpoint `/api/device/sessions/{session}/payment-check`.
  - Android can check whether payment is required or can be skipped (`voucher_type`: `skip/free/override`).
  - Added endpoint `/api/device/sessions/{session}/confirm-payment` to mark session payment as paid.
  - Added payment gate: upload/complete session blocked until paid, unless active skip voucher exists.
  - Added endpoint `/api/device/payment-quote` to calculate subtotal, discount, and total due before session/payment.
  - Voucher verify (`/api/device/vouchers/verify`) now supports optional `subtotal_amount` and can return quote context.
  - Session creation with voucher now only auto-paid for bypass types (`skip/free/override`); promo voucher remains pending payment.
  - Added end-to-end device flow regression test for promo voucher:
    - `verify voucher -> payment quote -> create session (pending) -> block upload before paid -> confirm payment -> upload unlocked`.
  - Added edge-case voucher regression tests on device flow:
    - expired voucher rejected,
    - not-started voucher rejected,
    - usage-full voucher rejected,
    - min purchase not met returns `discount_reason=min_purchase_not_met`,
    - `free` voucher quote unlocks photo (`payment_required=false`).
  - Added device-side session event audit trail:
    - `voucher_applied` recorded when session created with voucher code,
    - `payment_gate_blocked` recorded when upload is blocked due to unpaid session,
    - `payment_confirmed` recorded when device confirms payment.
  - Android contract payload stabilization:
    - response keys are aligned across verify/quote/create/check/confirm endpoints,
    - added `contract_version` and normalized `voucher_code` / `voucher_type` top-level keys,
    - retained backward-compatible `voucher.code` / `voucher.type` fields.
  - Added master voucher table (`vouchers`) for pre-payment flow.
  - Added endpoint `/api/device/vouchers/verify` for Android voucher input before session creation.
  - `POST /api/device/sessions` now accepts `voucher_code`; valid voucher auto sets session to paid and unlocks direct photo capture.
- QA alignment:
  - Print order status chips aligned with backend (`created`, `queued`, `printing`, `failed`, `printed`).
  - Inertia component assertions added to key UI route tests.

## Scramble Pause Note
- Completed before pause:
  - Scramble package installation and base config.
  - Basic Scramble docs test and auth gate setup.
  - Initial endpoint documentation updates for auth/device flow.
- Resume later with this order:
  - Complete remaining endpoint documentation coverage.
  - Add CI enforcement for `php artisan scramble:analyze --no-interaction`.
  - Revisit docs access policy for production role-based restriction.

## Overview
Full system photobooth:
- Device → Session → Upload → Edit → Render → Print → Queue → Agent → Logs

## System Topology (Revised)
- Android Device (Photobooth Client):
  - Menampilkan + memilih template.
  - Foto/capture.
  - Render foto ke template (preview/composited di device).
  - Auth device + upload foto + complete session.
  - Auto print API ke Photobooth Station (local).
  - Next: payment method.
- Photobooth Station (Local Network):
  - Terkoneksi lokal dengan Android Device untuk menerima file foto print.
  - Mengatur skip/voucher pada session Android.
  - Menambahkan + editor template.
  - Menjadi gateway print lokal saat internet bermasalah.
- Photobooth Cloud (Web Client Portal):
  - Client login.
  - Lihat session foto yang pernah dilakukan.
  - Akses foto original + render template + thumbnail.
  - Edit foto untuk dikirim ke Photobooth Admin (order print only).
- Photobooth Admin (Control Plane):
  - Memantau keseluruhan sistem, session, print queue, printer status, dan log.

## MiniPC Development Note
- Current station foundation is ready for MiniPC proof-of-concept, but not final for production-grade MiniPC deployment.
- Keep Android support, but evolve the generic device model so the station can distinguish hardware role/capability instead of assuming every capture client is Android.
- Recommended future `device_type` values:
  - `android`: Android capture client for template selection, voucher/payment check, photo capture, upload, and session completion.
  - `minipc_kiosk`: MiniPC booth app, ideally Electron-based, running fullscreen/kiosk mode for template selection, countdown, capture, preview, upload, render, and optional auto-print.
  - `print_agent`: background MiniPC/Windows service that detects printers, claims print jobs, sends files to printer, reports heartbeat/status, and handles retry/failure.
- Defer `camera_station` unless DSLR/hot-folder capture is split from the booth UI. If needed later, use it for a dedicated camera controller that watches a local folder or camera tether and uploads photos into an active session.
- Recommended implementation path:
  - Add `device_type`, `capabilities_json`, `config_json`, `app_version`, `os_name`, `os_version`, `last_sync_at`, and richer heartbeat data to device records.
  - Add MiniPC config + heartbeat endpoints so station can return default template, capture mode, storage path, printer binding, auto-print settings, and feature flags.
  - Add pairing/install flow: operator installs MiniPC app, enters Station URL + pairing/device code, station binds the app to a device record.
  - Split token abilities/scopes by role: capture device, kiosk device, print agent.
  - Add local offline queue and sync events for MiniPC so capture/print can recover after network interruption.
  - Add ops visibility for MiniPC app version, health, disk space, camera status, printer status, last heartbeat, last sync, and recent errors.
- Recommended app packaging:
  - Start with Electron for Windows MiniPC because it supports installer-style distribution, fullscreen kiosk mode, local file access, printer/camera integration, and auto-start on boot.
  - Keep Tauri/PWA as later alternatives only if app size or deployment model becomes a stronger concern.

## Forward Ideas (Futuristic but Feasible)
- Smart template fit (auto crop/zoom agar wajah selalu pas di slot).
- Dynamic template layer (nama acara, waktu, lokasi, QR, sponsor branding).
- AI style filter presets sebelum render final.
- Offline payment token (QR lokal, sync ke cloud saat online).
- Auto reprint + retry rules dengan batas percobaan.
- Session highlight reel (slideshow singkat otomatis).
- Voucher smart rules (jam sepi/hari tertentu/event).
- Cloud archive + client portal re-order print (tanpa kontak).

## Dynamic Layer v1 (Implemented)
- Dynamic layer text + QR draft stored in `config_json.dynamic_layers`.
- UI editor untuk text/QR posisi, ukuran, warna, opacity.
- Render final: text layer sudah dirender ke output.
- QR layer dirender sebagai QR nyata (endroid/qr-code).
- Token variables untuk text/QR: `{session_code}`, `{station_code}`, `{station_name}`, `{render_date}`, `{render_time}`.
- Tambahan token: `{device_name}`.
- Layer dapat dinonaktifkan via toggle `enabled`.
- UI quick insert token per layer.
- Drag & drop layer langsung di canvas (preview).
- QR layer preview berada di atas overlay PNG, dengan padding + background color yang editable.
- QR preview di UI memakai data URL hasil endpoint `/api/editor/templates/qr-preview`.

## Smart Template Fit v1 (Implemented)
- Default assign foto menggunakan smart fit (zoom disesuaikan dari rasio foto vs slot).
- Toggle `Smart Fit` untuk aktif/nonaktif.
- Tombol `Smart Fit All` untuk menerapkan ke semua slot terisi.
- Indikator zoom pada Framing Preview.

## Smart Template Fit v2 (Heuristic)
- Tanpa OpenCV: offset_y sedikit negatif agar framing cenderung ke area atas.
- Bias slider untuk mengatur intensitas offset.
- Preferensi Smart Fit (enabled + bias) disimpan di localStorage.

## Smart Template Fit v3 (Face-aware, OpenCV)
- Endpoint face-fit: `/api/editor/sessions/{session}/photos/{photo}/face-fit`.
- Frontend mencoba face-fit saat assign foto + auto fill.
- Face-fit dinonaktifkan otomatis di environment testing (hindari segfault opencv).

## Architecture
- Device API
- Editor API
- Print Agent API

## Android Contract Checklist (Before Payment Flow)
- Contract version saat ini: `2026-04-15`.
- Endpoint: `POST /api/device/vouchers/verify`
  - Wajib kirim: `voucher_code`
  - Opsional: `subtotal_amount`
  - Wajib baca response: `contract_version`, `valid`, `payment_required`, `unlock_photo`, `voucher_code`, `voucher_type`, `quote`
- Endpoint: `POST /api/device/payment-quote`
  - Wajib kirim: `subtotal_amount`
  - Opsional: `voucher_code`
  - Wajib baca response: `contract_version`, `quote.subtotal_amount`, `quote.discount_amount`, `quote.total_due`, `quote.payment_required`, `quote.unlock_photo`, `quote.discount_reason`
- Endpoint: `POST /api/device/sessions`
  - Opsional kirim: `voucher_code`
  - Wajib baca response: `contract_version`, `session_id`, `session_code`, `payment_status`, `payment_required`, `unlock_photo`, `voucher_applied`, `voucher_code`, `voucher_type`
- Endpoint: `GET /api/device/sessions/{session}/payment-check`
  - Wajib baca response: `contract_version`, `payment_status`, `payment_required`, `payment_unlocked`, `skip_reason`, `voucher_code`, `voucher_type`
- Endpoint: `POST /api/device/sessions/{session}/confirm-payment`
  - Wajib kirim: `payment_ref`, `payment_method`, `amount`, `currency`
  - Wajib baca response: `contract_version`, `payment_status`, `payment_required`, `unlock_photo`, `paid_at`
- Guard rule:
  - upload/complete session wajib ditolak bila `payment_required=true`.
  - `skip/free/override` voucher membuka capture tanpa payment (`unlock_photo=true`).

## Android Payload/Response Samples (Copy-Paste)
- Verify voucher:
```json
POST /api/device/vouchers/verify
{
  "voucher_code": "PROMO-E2E-20K",
  "subtotal_amount": 100000
}
```
```json
200 OK
{
  "contract_version": "2026-04-15",
  "valid": true,
  "unlock_photo": false,
  "payment_required": true,
  "message": "Voucher valid. Lanjutkan ke payment dengan harga diskon.",
  "voucher_code": "PROMO-E2E-20K",
  "voucher_type": "promo",
  "voucher": {
    "code": "PROMO-E2E-20K",
    "type": "promo",
    "voucher_code": "PROMO-E2E-20K",
    "voucher_type": "promo"
  },
  "quote": {
    "subtotal_amount": 100000,
    "discount_amount": 20000,
    "total_due": 80000,
    "payment_required": true,
    "unlock_photo": false,
    "discount_reason": null
  }
}
```
- Payment quote:
```json
POST /api/device/payment-quote
{
  "subtotal_amount": 100000,
  "voucher_code": "PROMO-E2E-20K"
}
```
```json
200 OK
{
  "contract_version": "2026-04-15",
  "message": "Payment quote generated.",
  "voucher_code": "PROMO-E2E-20K",
  "voucher_type": "promo",
  "voucher": {
    "code": "PROMO-E2E-20K",
    "type": "promo",
    "voucher_code": "PROMO-E2E-20K",
    "voucher_type": "promo"
  },
  "quote": {
    "subtotal_amount": 100000,
    "discount_amount": 20000,
    "total_due": 80000,
    "payment_required": true,
    "unlock_photo": false,
    "discount_reason": null
  }
}
```
- Create session (promo):
```json
POST /api/device/sessions
{
  "voucher_code": "PROMO-E2E-20K"
}
```
```json
201 Created
{
  "contract_version": "2026-04-15",
  "message": "Session created",
  "session_id": "uuid",
  "session_code": "SES-ABCDEFGH",
  "station_id": "uuid",
  "device_id": "uuid",
  "status": "created",
  "payment_status": "pending",
  "payment_required": true,
  "unlock_photo": false,
  "voucher_applied": true,
  "voucher_code": "PROMO-E2E-20K",
  "voucher_type": "promo",
  "voucher": {
    "code": "PROMO-E2E-20K",
    "type": "promo",
    "voucher_code": "PROMO-E2E-20K",
    "voucher_type": "promo"
  }
}
```
- Payment check:
```json
GET /api/device/sessions/{session_id}/payment-check
```
```json
200 OK
{
  "contract_version": "2026-04-15",
  "session_id": "uuid",
  "session_code": "SES-ABCDEFGH",
  "payment_status": "pending",
  "payment_required": true,
  "payment_unlocked": false,
  "skip_reason": null,
  "voucher_code": "PROMO-E2E-20K",
  "voucher_type": "promo",
  "voucher": {
    "id": "uuid",
    "voucher_code": "PROMO-E2E-20K",
    "voucher_type": "promo",
    "status": "applied"
  }
}
```
- Confirm payment:
```json
POST /api/device/sessions/{session_id}/confirm-payment
{
  "payment_ref": "PAY-E2E-001",
  "payment_method": "qris",
  "amount": 80000,
  "currency": "IDR"
}
```
```json
200 OK
{
  "contract_version": "2026-04-15",
  "message": "Payment confirmed",
  "session_id": "uuid",
  "session_code": "SES-ABCDEFGH",
  "payment_status": "paid",
  "payment_required": false,
  "unlock_photo": true,
  "payment_ref": "PAY-E2E-001",
  "payment_method": "qris",
  "paid_at": "2026-04-15T00:00:00.000000Z"
}
```

## Core Rules
- DO NOT break existing flow
- DO NOT rename models/tables randomly
- ALWAYS use transactions for critical flows
- ALWAYS enforce ownership (printer binding)

## Status (STRICT)
PhotoSession:
created, uploaded, editing, ready_print, queued_print, failed_print, printed

PrintOrder:
queued, printing, failed, printed

PrintQueueJob:
pending, processing, failed, completed

Printer:
ready, printing, offline, error, paused

## Security
- Sanctum auth
- Role based:
  - admin
  - editor
  - print-agent
- Print-agent MUST use bound printer_id

## Backend Patterns
- Controllers thin
- Use FormRequest
- Use DB::transaction
- Prevent duplicate actions (idempotency)

## Scheduler
- Auto fail stuck jobs
- Auto mark printer offline

## Frontend Rules
- Use Inertia + Vue
- Pages in resources/js/Pages
- Components reusable
- No direct DB logic in frontend

## Definition of Done
- Route works
- No duplicate data
- Status correct
- Auth safe

## Next Program (Roadmap v2 Summary)
- Week 1: Payment gateway real + webhook reconciliation.
- Week 2: Print reliability (retry, idempotency, fallback printer).
- Week 3: Offline-first station queue + sync recovery.
- Week 4: Android production contract & error-code finalization.
- Week 5: Cloud sync + client history + re-order print.
- Week 6: Security hardening (credential rotation, throttling, audit).
- Week 7: Scramble docs resume + CI governance gates.
- Week 8: Pilot UAT lapangan + final stabilization.

### Execution Note — Current Active Sprint
- Active sprint target: Week 1 (Payment Real Integration).
- Work order priority:
  1. Contract & idempotency fields,
  2. Payment init service,
  3. Webhook verification handler,
  4. Reconciliation scheduler,
  5. Ops UI status visibility,
  6. Failure-path regression tests,
  7. Android contract freeze handover.
