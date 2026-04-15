# Photobooth Station ROADMAP (FULL)

## Active Focus (April 13, 2026)
- Temporarily prioritize core application development.
- Scramble documentation track is parked and will continue after app milestones are stable.

## Progress Update (April 13, 2026)
- Completed:
  - default seeder refactor for idempotent runs,
  - default printer seeder integration,
  - stable default admin + print-agent + station/device/template/printer bootstrap data,
  - `DemoWorkflowSeeder` integration for visual transaction data (sessions/photos/render/order/queue/log),
  - idempotency test coverage for demo workflow seeding,
  - template UI v1 improvements (quick filter, slot highlight, use-template action),
  - template detail UI v2 (active-library indicator, slot photo placement preview, custom PNG overlay control),
  - session editor UI v2 (direct drag photo placement inside slot preview + clearer X/Y placement controls),
  - template layout editor v1 (slot drag on template detail canvas + slot layout save API + numeric slot controls),
  - layout precision controls (snap-to-grid + lock aspect ratio),
  - template management v1 (create template + add/remove slot),
  - template management v2 (rename + archive toggle + duplicate + delete with audit log + status filter/badges).
  - template overlay persistence (upload PNG + store in assets + keep after refresh).
  - dashboard UI iteration (recent sessions + recent print orders panels).
  - sessions UI iteration (status summary chips + result counter).
  - print queue UI iteration (status chips + result counter + failed highlight).
  - printers UI iteration (status chips + search/filter + refresh + result counter).
  - print orders UI iteration (status chips + search/filter + refresh + result counter).
  - print logs UI iteration (level chips + refresh + error highlight).
  - print order detail UI iteration (refresh + currency formatting).
  - printer detail UI iteration (refresh + failed highlight + job counter).
  - session detail UI iteration (last synced + photo count badge).
  - QA alignment (print order status chips + inertia component assertions).
  - session editor fix (allow duplicate photo usage across slots).
  - session editor enhancement (duplicate photo to next empty slot).
  - session editor polish (render status summary).
  - enterprise voucher/skip (session_vouchers + apply/revoke UI).
  - seed demo vouchers for sessions.
  - expanded voucher seed data:
    - master voucher codes for multiple types (`promo`, `skip`, `free`, `override`),
    - session voucher usage examples across several demo sessions.
  - voucher UI polish (summary + filter chips).
  - voucher management UI (sidebar menu + dedicated page `/vouchers`).
  - master voucher library management (create/list/update/deactivate) for before-payment Android flow.
  - editor voucher library API (`/api/editor/voucher-library` + update endpoint).
  - voucher validity switched to date-only input/output (`DD-MM-YYYY`).
  - master voucher discount configuration added (percent/fixed + nominal constraints).
  - voucher page now includes payment quote simulator UI for operator.
  - voucher UI polish:
    - quote values now formatted as Rupiah,
    - quick `Simulate` action from voucher list card,
    - `Reset Quote` action + subtotal guard validation.
    - quick `Copy Code` action from voucher list card.
    - voucher health indicator in list: `Valid` / `Expired` / `Not Started` / `Usage Full` / `Inactive`.
  - editor quote endpoint added: `/api/editor/voucher-library/quote`.
  - voucher management API endpoints (list/create/revoke) for station operator workflow.
  - vouchers regression tests (editor page access + management API flow).
  - device pre-payment check endpoint for voucher skip flow (`/api/device/sessions/{session}/payment-check`).
  - device confirm-payment endpoint (`/api/device/sessions/{session}/confirm-payment`).
  - device payment quote endpoint (`/api/device/payment-quote`) with discount calculation.
  - added E2E promo voucher regression test for Android before-payment chain:
    - verify voucher -> quote -> create pending session -> payment gate blocks upload -> confirm payment -> upload allowed.
  - added session audit events for device payment chain:
    - `voucher_applied`, `payment_gate_blocked`, `payment_confirmed`.
  - added voucher edge-case regression coverage:
    - expired/not-started/usage-full rejected in verify endpoint,
    - min purchase rule reflected in quote reason,
    - `free` voucher quote unlock behaviour validated.
  - Android before-payment API contract stabilized:
    - consistent response keys across verify/quote/create/check/confirm,
    - added `contract_version` marker and normalized voucher keys for mobile integration.
  - added Android contract checklist in project guide for mobile handoff (required request/response keys per endpoint).
  - added copy-paste JSON payload/response samples for Android integration in AI agent guide.
  - hard gate payment before capture: upload/complete blocked unless `payment_status=paid` or skip voucher active.
  - master voucher storage (`vouchers`) for pre-payment validation.
  - Android voucher verify endpoint (`/api/device/vouchers/verify`) before create session.
  - create session supports `voucher_code`; bypass vouchers auto-unlock capture, promo vouchers stay pending payment.
- Unblocked:
  - visual-first app development can continue with populated dashboard, sessions, queue, printer, and print order pages.
- Next:
  - iterate UX and workflow actions directly on seeded visual data.

## Parked Track: Scramble API Docs
- Status: paused intentionally.
- Resume checkpoints:
  - finish docs coverage for remaining APIs (editor + print-agent),
  - enforce Scramble analyze in CI,
  - finalize production docs access policy.

## Current
Backend complete (~85%)

## Phase 1
- Login
- Dashboard
- Printers
- Queue

## Phase 2
- Sessions
- Templates

## Phase 3
- Edit Job
- Render

## Phase 4
- Print Orders
- Logs

## Phase 5
- Smart routing
- Auto retry

## Phase 6
- Monitoring & alert

## Phase 7
- Optimization

## Phase 8
- Testing

## System Topology (Revised)
- Android Device (Photobooth Client):
  - template select + capture + device render preview.
  - auth + upload + complete session.
  - auto print API ke station (local).
  - next: payment method.
- Photobooth Station (Local Network):
  - menerima file foto print dari Android Device.
  - skip/voucher control pada session.
  - template editor + management.
- Photobooth Cloud (Web Client Portal):
  - client login + session history.
  - akses original + render + thumbnail.
  - edit foto untuk print-only order ke Admin.
- Photobooth Admin:
  - monitoring keseluruhan sistem.

## Forward Ideas (Futuristic but Feasible)
- Smart template fit (auto crop/zoom).
- Dynamic template layer (event, lokasi, QR, sponsor).
- AI style filter presets.
- Offline payment token + sync.
- Auto reprint + retry rules.
- Session highlight reel.
- Voucher smart rules.
- Cloud archive + re-order print.

## Dynamic Layer v1 (Done)
- Text layer rendering on final output.
- QR layer rendered via endroid/qr-code.
- Variable tokens + enable toggle.
- UI quick insert token.
- Layer drag & drop di canvas preview.
- QR preview di atas overlay + padding/background color editable.
- QR preview real via API endpoint.
- Token tambahan: device_name.

## Smart Template Fit v1 (Done)
- Auto fit saat assign foto + toggle + apply all.
- Zoom indicator di Framing Preview.

## Smart Template Fit v2 (Heuristic)
- Bias crop sedikit ke atas tanpa dependency OpenCV.
- Bias slider untuk kontrol operator.
- Preferensi Smart Fit disimpan lokal.

## Smart Template Fit v3 (Face-aware)
- Face-fit endpoint + apply saat assign foto.
- Disabled in testing environment to avoid opencv segfault.

## Goal
Production-ready scalable system

## Roadmap v2 (Weekly Execution Plan)

### Week 1 — Payment Real Integration
- Scope:
  - Integrasi payment gateway real (QRIS/e-wallet) untuk pre-payment flow Android.
  - Webhook callback untuk update status pembayaran otomatis.
  - Reconciliation dasar antara request payment vs status session.
- Definition of Done:
  - Session `pending -> paid` bisa otomatis via webhook.
  - Audit event payment tercatat konsisten.
  - Minimal happy-path + failed callback test pass.

#### Week 1 — Daily Plan
- Day 1 (Contract + Data Model):
  - Finalisasi endpoint contract payment-init + webhook callback.
  - Tentukan field idempotency, reference external, signature metadata.
  - Checklist migration tambahan jika diperlukan (tanpa ubah schema lama secara destruktif).
- Day 2 (Payment Init Service):
  - Implement service untuk create payment intent ke gateway.
  - Simpan relasi `session <-> payment reference`.
  - Return payload siap dipakai Android (QR/content + expiry + status awal).
- Day 3 (Webhook Handler):
  - Implement endpoint webhook + signature verification.
  - Mapping status gateway ke status internal (`pending`, `paid`, `failed`, `expired`).
  - Record audit events (`payment_webhook_received`, `payment_confirmed`).
- Day 4 (Reconciliation Job):
  - Scheduler job untuk cek transaksi pending/unknown.
  - Retry fetch status ke gateway dengan backoff.
  - Pastikan update status idempotent.
- Day 5 (UI Ops + Monitoring):
  - Tambah indikator status payment real-time di session detail.
  - Tampilkan reference + waktu update terakhir.
  - Filter list berdasarkan status payment.
- Day 6 (Test & Failure Drill):
  - Feature test: init success/fail, webhook valid/invalid signature, retry reconcile.
  - Simulasi duplicate webhook dan out-of-order callback.
  - Verifikasi tidak ada double state transition.
- Day 7 (UAT Mini + Handover):
  - Runbook operasional untuk tim station/admin.
  - Checklist rollback/fallback jika gateway gangguan.
  - Freeze contract untuk tim Android (no breaking change).

### Week 2 — Print Reliability Hardening
- Scope:
  - Retry policy print job berbasis rule (attempt + cooldown).
  - Proteksi anti-double print dengan idempotency key.
  - Fallback printer rule jika printer utama error/offline.
- Definition of Done:
  - Print job gagal bisa retry otomatis sesuai policy.
  - Tidak ada double print untuk request yang sama.
  - Test retry/fallback/idempotency pass.

### Week 3 — Offline-First Station
- Scope:
  - Local queue untuk action kritikal saat internet putus.
  - Sync worker untuk push data saat koneksi kembali.
  - Conflict handling sederhana (latest valid event wins).
- Definition of Done:
  - Station tetap operasional saat offline.
  - Data tersinkron kembali tanpa duplikasi saat online.
  - Uji skenario disconnect/reconnect pass.

### Week 4 — Android Production Readiness
- Scope:
  - Error-code matrix final (`401/403/422/...`) per endpoint device.
  - Retry/timeout guidance untuk mobile client.
  - Contract validation test untuk payload response wajib.
- Definition of Done:
  - Matrix error dipakai tim Android sebagai acuan final.
  - Contract version + key wajib terkunci di regression test.

### Week 5 — Cloud Portal Sync & Re-order
- Scope:
  - Sinkron session/render/print summary ke Cloud.
  - Endpoint riwayat sesi untuk client portal.
  - Jalur re-order print dari cloud ke station/admin.
- Definition of Done:
  - Session history tampil konsisten di cloud.
  - Re-order request masuk ke workflow print station.

### Week 6 — Security & Audit Upgrade
- Scope:
  - Hardening credential device (rotation + revocation).
  - Rate limiting endpoint kritikal.
  - Audit log diperluas untuk aksi sensitif.
- Definition of Done:
  - Endpoint sensitif terproteksi throttle.
  - Device credential bisa di-rotate tanpa downtime besar.
  - Audit trail memadai untuk investigasi operasional.

### Week 7 — API Docs & CI Governance
- Scope:
  - Resume Scramble track (coverage endpoint prioritas).
  - Publish docs internal untuk tim Android/ops.
  - CI gate: analyze docs + test voucher/payment critical path.
- Definition of Done:
  - Dokumen API bisa dipakai lintas tim.
  - CI gagal jika contract/test kritikal regress.

### Week 8 — Pilot UAT Lapangan
- Scope:
  - Simulasi event ramai (multi-device, multi-session, multi-print).
  - Chaos test jaringan lokal + printer failure.
  - Final bugfix batch dari hasil UAT.
- Definition of Done:
  - Pilot checklist lolos.
  - Bug high/critical terselesaikan.
  - Go/No-Go production decision siap.
