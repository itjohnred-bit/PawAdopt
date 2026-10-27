-- ============================================================================
-- PawAdopt — SECURITY HARDENING MIGRATION
-- Run AFTER sql/pawadopt.sql (and the other sql/*.sql files).
-- Safe to re-run: every statement is guarded.
--
-- Purpose: enforce data-ownership and duplicate-prevention rules on the
-- DATABASE side (server-side enforcement), not only in PHP/frontend code.
-- ============================================================================

-- ── 1. adoption_applications: prevent duplicate applications ────────────────
-- Generated column flags "active" applications (anything not yet decided /
-- cancelled / withdrawn). One active application per adopter per pet.
ALTER TABLE adoption_applications
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1)
    GENERATED ALWAYS AS (
      CASE WHEN status IN ('PENDING','Submitted','Under Review','Approved')
           THEN 1 ELSE 0 END
    ) STORED;

-- Ignore duplicate-key errors from historical rows (older MySQL versions
-- have no IF NOT EXISTS for indexes; re-running prints a harmless warning).
ALTER TABLE adoption_applications
  ADD UNIQUE KEY uq_active_application (adopter_id, pet_id, is_active);

-- ── 2. appointments: prevent double-booking the same slot ───────────────────
ALTER TABLE appointments
  ADD UNIQUE KEY uq_appointment_slot (application_id, appointment_date, appointment_time);

-- ── 3. favorites: idempotent favorite toggle (already unique; guarded re-run)
-- unique_favorite (adopter_id, pet_id) exists in pawadopt.sql — no change.

-- ── 4. users: credential uniqueness (already unique; documented for audit) ──
-- users.username and users.email are UNIQUE in pawadopt.sql — no change.

-- ── 5. user_preferences: one row per user (already unique in user_prefs.sql)
