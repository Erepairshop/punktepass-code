-- ============================================================
-- PunktePass Database Indexes
-- Futtatás: phpMyAdmin-ban vagy mysql CLI-vel
-- Dátum: 2025-11-29
-- ============================================================

-- 🔒 FONTOS: Futtasd production adatbázison CSAK AKKOR ha kevés a forgalom!
-- Az index létrehozás lassú nagy táblákon.

-- ============================================================
-- ppv_points - LEGFONTOSABB (user pontok lekérdezése)
-- ============================================================

-- User pontjainak összesítése (nagyon gyakori query)
CREATE INDEX IF NOT EXISTS idx_points_user_id ON wp_ppv_points(user_id);

-- Store szerinti szűrés
CREATE INDEX IF NOT EXISTS idx_points_store_id ON wp_ppv_points(store_id);

-- Dátum szerinti szűrés (napi duplikáció check)
CREATE INDEX IF NOT EXISTS idx_points_created ON wp_ppv_points(created);

-- Kombinált index (user + store együtt - legtöbb query így kérdez)
CREATE INDEX IF NOT EXISTS idx_points_user_store ON wp_ppv_points(user_id, store_id);

-- Kombinált index dátummal (napi scan check)
CREATE INDEX IF NOT EXISTS idx_points_user_store_date ON wp_ppv_points(user_id, store_id, created);

-- ============================================================
-- ppv_users - User lookup
-- ============================================================

-- Email alapú keresés (login)
CREATE INDEX IF NOT EXISTS idx_users_email ON wp_ppv_users(email);

-- Status szűrés (active check)
CREATE INDEX IF NOT EXISTS idx_users_status ON wp_ppv_users(status);

-- Store lookup (vendor_store_id)
CREATE INDEX IF NOT EXISTS idx_users_vendor_store ON wp_ppv_users(vendor_store_id);

-- ============================================================
-- ppv_stores - Store keresés
-- ============================================================

-- Status szűrés
CREATE INDEX IF NOT EXISTS idx_stores_status ON wp_ppv_stores(status);

-- User (owner) lookup
CREATE INDEX IF NOT EXISTS idx_stores_user_id ON wp_ppv_stores(user_id);

-- POS token keresés
CREATE INDEX IF NOT EXISTS idx_stores_pos_token ON wp_ppv_stores(pos_token);

-- ============================================================
-- ppv_rewards - Reward keresés
-- ============================================================

-- Store rewards listázása
CREATE INDEX IF NOT EXISTS idx_rewards_store_id ON wp_ppv_rewards(store_id);

-- Required points szerinti rendezés
CREATE INDEX IF NOT EXISTS idx_rewards_required_points ON wp_ppv_rewards(required_points);

-- ============================================================
-- ppv_reward_requests - Beváltás history
-- ============================================================

-- User history
CREATE INDEX IF NOT EXISTS idx_requests_user_id ON wp_ppv_reward_requests(user_id);

-- Store history
CREATE INDEX IF NOT EXISTS idx_requests_store_id ON wp_ppv_reward_requests(store_id);

-- Status szűrés
CREATE INDEX IF NOT EXISTS idx_requests_status ON wp_ppv_reward_requests(status);

-- Duplikáció check (user + reward + store + időszak)
CREATE INDEX IF NOT EXISTS idx_requests_user_reward_store ON wp_ppv_reward_requests(user_id, reward_id, store_id);

-- Dátum szerinti szűrés
CREATE INDEX IF NOT EXISTS idx_requests_created ON wp_ppv_reward_requests(created_at);

-- ============================================================
-- ppv_tokens - Token authentication
-- ============================================================

-- Token lookup (gyors auth)
CREATE INDEX IF NOT EXISTS idx_tokens_token ON wp_ppv_tokens(token);

-- User tokens
CREATE INDEX IF NOT EXISTS idx_tokens_user_id ON wp_ppv_tokens(user_id);

-- Expiry check
CREATE INDEX IF NOT EXISTS idx_tokens_expires ON wp_ppv_tokens(expires_at);

-- ============================================================
-- ppv_campaigns - Kampány keresés
-- ============================================================

-- Store campaigns
CREATE INDEX IF NOT EXISTS idx_campaigns_store_id ON wp_ppv_campaigns(store_id);

-- Active campaigns
CREATE INDEX IF NOT EXISTS idx_campaigns_status ON wp_ppv_campaigns(status);

-- Date range filter
CREATE INDEX IF NOT EXISTS idx_campaigns_dates ON wp_ppv_campaigns(start_date, end_date);

-- ============================================================
-- ppv_qr_scans - Scan log (audit)
-- ============================================================

-- Store scans
CREATE INDEX IF NOT EXISTS idx_scans_store_id ON wp_ppv_qr_scans(store_id);

-- User scans
CREATE INDEX IF NOT EXISTS idx_scans_user_id ON wp_ppv_qr_scans(user_id);

-- Időszak szerinti query
CREATE INDEX IF NOT EXISTS idx_scans_created ON wp_ppv_qr_scans(created_at);

-- ============================================================
-- ELLENŐRZÉS - Meglévő indexek listázása
-- ============================================================

-- Futtasd ezt külön, hogy lásd milyen indexek vannak:
-- SHOW INDEX FROM wp_ppv_points;
-- SHOW INDEX FROM wp_ppv_users;
-- SHOW INDEX FROM wp_ppv_stores;

-- ============================================================
-- MEGJEGYZÉS
-- ============================================================
-- Ha a táblák prefixe nem "wp_", akkor cseréld ki!
-- Pl. "wp_ppv_points" -> "youprefix_ppv_points"
