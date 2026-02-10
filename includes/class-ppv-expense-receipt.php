<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Kiadási Bizonylat Generálás (Expense Receipt)
 * Version: 2.2 FIXED - Működő havi bizonylat generálás
 * ✅ Egyszeri + Havi bizonylatok
 * ✅ DE (Deutsch) + RO (Română) verzió
 * ✅ Auto nyelvválasztás (store country alapján)
 * ✅ HTML mentés → böngésző PDF-ként letölti
 * ✅ NINCS ÁFA - Interne Vergütung / Operațiune internă
 * ✅ Működő generate_monthly_receipt() - nincs include error!
 */

class PPV_Expense_Receipt {

    const RECEIPTS_DIR = 'ppv_receipts';
    const RECEIPT_PREFIX = 'expense-receipt';
    const MONTHLY_PREFIX = 'monthly-receipt';

    /**
     * ✅ EGYSZERI BIZONYLAT - Egyetlen beváltáshoz
     * Approve után automatikusan hívódik
     */
    public static function generate_for_redeem($redeem_id) {
        global $wpdb;

        ppv_log("📄 [PPV_EXPENSE_RECEIPT] Bizonylat generálása: redeem_id={$redeem_id}");

        // 1️⃣ Beváltás adatainak lekérése
        $redeem = $wpdb->get_row($wpdb->prepare("
            SELECT
                r.id,
                r.user_id,
                r.store_id,
                r.reward_id,
                r.points_spent,
                r.actual_amount,
                r.redeemed_at,
                rw.title as reward_title,
                rw.action_value,
                rw.free_product_value,
                u.email as user_email,
                u.first_name,
                u.last_name,
                s.company_name,
                s.address,
                s.plz,
                s.city,
                s.country,
                s.tax_id
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE r.id = %d
        ", $redeem_id), ARRAY_A);

        if (!$redeem) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Redeem nem található: {$redeem_id}");
            return false;
        }

        // 2️⃣ Nyelvválasztás
        $lang = strtoupper($redeem['country'] ?? 'DE');
        if (!in_array($lang, ['DE', 'RO', 'HU'])) {
            $lang = 'DE';
        }

        ppv_log("🌍 [PPV_EXPENSE_RECEIPT] Língua: {$lang}");

        // 3️⃣ HTML generálás
        $html = self::generate_html_for_redeem($redeem, $lang);

        // 4️⃣ Fájl mentés
        $filename = self::RECEIPT_PREFIX . '-' . $redeem_id . '.pdf';
        $path = self::save_receipt_file($html, $filename);

        if ($path) {
            // 5️⃣ Adatbázis frissítés
            $update_result = $wpdb->update(
                $wpdb->prefix . 'ppv_rewards_redeemed',
                ['receipt_pdf_path' => $path],
                ['id' => $redeem_id],
                ['%s'],
                ['%d']
            );

            if ($update_result === false) {
                ppv_log("❌ [PPV_EXPENSE_RECEIPT] DB update hiba: " . $wpdb->last_error);
                return false;
            }

            ppv_log("✅ [PPV_EXPENSE_RECEIPT] Bizonylat sikeres: {$redeem_id} → {$path}");
            return $path;
        }

        ppv_log("❌ [PPV_EXPENSE_RECEIPT] Fájl mentés sikertelen: {$redeem_id}");
        return false;
    }

    /**
     * ✅ HAVI BIZONYLAT GENERÁLÁS - JAVÍTOTT VERZIÓ
     * Működik include error nélkül!
     */
    public static function generate_monthly_receipt($store_id, $year, $month, $group_by_filiale = false)
    {
        global $wpdb;

        ppv_log("📅 [PPV_EXPENSE_RECEIPT] generate_monthly_receipt() called: store={$store_id}, year={$year}, month={$month}, group={$group_by_filiale}");

        // 1️⃣ Store adatok lekérése
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name, name, address, plz, city, country, tax_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d LIMIT 1
        ", $store_id), ARRAY_A);

        if (!$store) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Store nem található: {$store_id}");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Store megtalálva: " . $store['company_name']);

        // 2️⃣ Beváltások lekérése a hónapra
        if ($group_by_filiale) {
            // Get all stores: parent + children
            $store_ids = $wpdb->get_col($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}ppv_stores
                WHERE id = %d OR parent_store_id = %d
            ", $store_id, $store_id));

            if (empty($store_ids)) {
                $store_ids = [$store_id];
            }

            $store_ids_str = implode(',', array_map('intval', $store_ids));

            $items = $wpdb->get_results($wpdb->prepare("
                SELECT
                    r.id,
                    r.user_id,
                    r.store_id,
                    r.reward_id,
                    r.points_spent,
                    r.actual_amount,
                    r.redeemed_at,
                    u.email AS user_email,
                    u.first_name,
                    u.last_name,
                    rw.title AS reward_title,
                    rw.action_value,
                    rw.free_product_value,
                    s.name AS filiale_name,
                    s.company_name AS filiale_company,
                    s.city AS filiale_city
                FROM {$wpdb->prefix}ppv_rewards_redeemed r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
                LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
                WHERE r.store_id IN ({$store_ids_str})
                AND r.status = 'approved'
                AND YEAR(r.redeemed_at) = %d
                AND MONTH(r.redeemed_at) = %d
                ORDER BY s.name ASC, r.redeemed_at ASC
            ", $year, $month));
        } else {
            $items = $wpdb->get_results($wpdb->prepare("
                SELECT
                    r.id,
                    r.user_id,
                    r.store_id,
                    r.reward_id,
                    r.points_spent,
                    r.actual_amount,
                    r.redeemed_at,
                    u.email AS user_email,
                    u.first_name,
                    u.last_name,
                    rw.title AS reward_title,
                    rw.action_value,
                    rw.free_product_value
                FROM {$wpdb->prefix}ppv_rewards_redeemed r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
                WHERE r.store_id = %d
                AND r.status = 'approved'
                AND YEAR(r.redeemed_at) = %d
                AND MONTH(r.redeemed_at) = %d
                ORDER BY r.redeemed_at ASC
            ", $store_id, $year, $month));
        }

        if (!$items || count($items) === 0) {
            ppv_log("⚠️ [PPV_EXPENSE_RECEIPT] Nincsenek beváltások: year={$year}, month={$month}");
            return false;
        }

        $count = count($items);
        ppv_log("✅ [PPV_EXPENSE_RECEIPT] {$count} beváltás találva");

        // 3️⃣ Nyelvválasztás a store country alapján
        $lang = strtoupper($store['country'] ?? 'DE');
        if (!in_array($lang, ['DE', 'RO', 'HU'])) {
            $lang = 'DE';
        }

        ppv_log("🌍 [PPV_EXPENSE_RECEIPT] Jezik: {$lang}");

        // 4️⃣ HTML generálás (az existing generate_html_for_monthly() függvénnyel)
        $html = self::generate_html_for_monthly($store, $items, $year, $month, $lang, $group_by_filiale);

        if (!$html) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] HTML generálás sikertelen");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] HTML generálva (" . strlen($html) . " bytes)");

        // 5️⃣ Könyvtár létrehozása
        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/ppv_receipts/';

        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                ppv_log("❌ [PPV_EXPENSE_RECEIPT] Mappa létrehozás sikertelen: {$dir}");
                return false;
            }
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Könyvtár OK: {$dir}");

        // 6️⃣ Fájlnév és útvonal
        $suffix = $group_by_filiale ? '-all' : '';
        $filename = sprintf("monthly-receipt-%d-%04d%02d%s.html", $store_id, $year, $month, $suffix);
        $filepath = $dir . $filename;

        // 7️⃣ HTML mentése fájlként UTF-8 BOM-mal (román ékezetek miatt)
        $utf8_bom = "\xEF\xBB\xBF";
        $bytes = file_put_contents($filepath, $utf8_bom . $html);

        if ($bytes === false) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Fájl írás sikertelen: {$filepath}");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Havi bizonylat mentve UTF-8: {$filename} ({$bytes} bytes)");

        // ✅ Relatív útvonal visszaadása
        return 'ppv_receipts/' . $filename;
    }

    /**
     * ✅ IDŐSZAK BIZONYLAT GENERÁLÁS (Zeitraumbericht)
     * Date range report - similar to monthly but with custom date range
     */
    public static function generate_date_range_receipt($store_id, $date_from, $date_to, $group_by_filiale = false)
    {
        global $wpdb;

        ppv_log("📅 [PPV_EXPENSE_RECEIPT] generate_date_range_receipt() called: store={$store_id}, from={$date_from}, to={$date_to}, group={$group_by_filiale}");

        // 1️⃣ Store adatok lekérése
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name, name, address, plz, city, country, tax_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d LIMIT 1
        ", $store_id), ARRAY_A);

        if (!$store) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Store nem található: {$store_id}");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Store megtalálva: " . $store['company_name']);

        // 2️⃣ Beváltások lekérése az időszakra
        if ($group_by_filiale) {
            // Get all stores: parent + children
            $store_ids = $wpdb->get_col($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}ppv_stores
                WHERE id = %d OR parent_store_id = %d
            ", $store_id, $store_id));

            if (empty($store_ids)) {
                $store_ids = [$store_id];
            }

            $store_ids_str = implode(',', array_map('intval', $store_ids));

            $items = $wpdb->get_results($wpdb->prepare("
                SELECT
                    r.id,
                    r.user_id,
                    r.store_id,
                    r.reward_id,
                    r.points_spent,
                    r.actual_amount,
                    r.redeemed_at,
                    u.email AS user_email,
                    u.first_name,
                    u.last_name,
                    rw.title AS reward_title,
                    rw.action_value,
                    rw.free_product_value,
                    s.name AS filiale_name,
                    s.company_name AS filiale_company,
                    s.city AS filiale_city
                FROM {$wpdb->prefix}ppv_rewards_redeemed r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
                LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
                WHERE r.store_id IN ({$store_ids_str})
                AND r.status = 'approved'
                AND DATE(r.redeemed_at) >= %s
                AND DATE(r.redeemed_at) <= %s
                ORDER BY s.name ASC, r.redeemed_at ASC
            ", $date_from, $date_to));
        } else {
            $items = $wpdb->get_results($wpdb->prepare("
                SELECT
                    r.id,
                    r.user_id,
                    r.store_id,
                    r.reward_id,
                    r.points_spent,
                    r.actual_amount,
                    r.redeemed_at,
                    u.email AS user_email,
                    u.first_name,
                    u.last_name,
                    rw.title AS reward_title,
                    rw.action_value,
                    rw.free_product_value
                FROM {$wpdb->prefix}ppv_rewards_redeemed r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
                WHERE r.store_id = %d
                AND r.status = 'approved'
                AND DATE(r.redeemed_at) >= %s
                AND DATE(r.redeemed_at) <= %s
                ORDER BY r.redeemed_at ASC
            ", $store_id, $date_from, $date_to));
        }

        if (!$items || count($items) === 0) {
            ppv_log("⚠️ [PPV_EXPENSE_RECEIPT] Nincsenek beváltások: from={$date_from}, to={$date_to}");
            return false;
        }

        $count = count($items);
        ppv_log("✅ [PPV_EXPENSE_RECEIPT] {$count} beváltás találva");

        // 3️⃣ Nyelvválasztás a store country alapján
        $lang = strtoupper($store['country'] ?? 'DE');
        if (!in_array($lang, ['DE', 'RO', 'HU'])) {
            $lang = 'DE';
        }

        ppv_log("🌍 [PPV_EXPENSE_RECEIPT] Jezik: {$lang}");

        // 4️⃣ HTML generálás (időszakra)
        $html = self::generate_html_for_date_range($store, $items, $date_from, $date_to, $lang, $group_by_filiale);

        if (!$html) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] HTML generálás sikertelen");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] HTML generálva (" . strlen($html) . " bytes)");

        // 5️⃣ Könyvtár létrehozása
        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/ppv_receipts/';

        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                ppv_log("❌ [PPV_EXPENSE_RECEIPT] Mappa létrehozás sikertelen: {$dir}");
                return false;
            }
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Könyvtár OK: {$dir}");

        // 6️⃣ Fájlnév és útvonal
        $suffix = $group_by_filiale ? '-all' : '';
        $filename = sprintf("date-range-receipt-%d-%s-%s%s.html", $store_id, str_replace('-', '', $date_from), str_replace('-', '', $date_to), $suffix);
        $filepath = $dir . $filename;

        // 7️⃣ HTML mentése fájlként UTF-8 BOM-mal (román ékezetek miatt)
        $utf8_bom = "\xEF\xBB\xBF";
        $bytes = file_put_contents($filepath, $utf8_bom . $html);

        if ($bytes === false) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Fájl írás sikertelen: {$filepath}");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Időszak bizonylat mentve UTF-8: {$filename} ({$bytes} bytes)");

        // ✅ Relatív útvonal visszaadása
        return 'ppv_receipts/' . $filename;
    }

    /**
     * 🎨 HTML generálás EGYSZERI bizonylathoz
     */
    private static function generate_html_for_redeem($redeem, $lang) {
        global $wpdb;

        $customer_name = trim(($redeem['first_name'] ?? '') . ' ' . ($redeem['last_name'] ?? ''));
        if (!$customer_name) {
            $customer_name = $redeem['user_email'] ?? 'Unbekannt';
        }

        // ✅ STORE-SPECIFIC RECEIPT NUMBER
        // Count how many redeems this store has had up to this point
        $store_redeem_count = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_rewards_redeemed
            WHERE store_id = %d AND id <= %d
        ", $redeem['store_id'], $redeem['id']));

        $receipt_num = date('Y-m-', strtotime($redeem['redeemed_at'])) . sprintf('%05d', $store_redeem_count);

        // ✅ Amount calculation: actual_amount → action_value → free_product_value → 0
        $amount = 0;
        if (!empty($redeem['actual_amount']) && floatval($redeem['actual_amount']) > 0) {
            $amount = floatval($redeem['actual_amount']);
        } elseif (!empty($redeem['action_value']) && $redeem['action_value'] !== '0') {
            $amount = floatval($redeem['action_value']);
        } elseif (!empty($redeem['free_product_value']) && floatval($redeem['free_product_value']) > 0) {
            $amount = floatval($redeem['free_product_value']);
        }

        if ($lang === 'RO') {
            return self::html_receipt_ro($redeem, $customer_name, $receipt_num, $amount);
        } elseif ($lang === 'HU') {
            return self::html_receipt_hu($redeem, $customer_name, $receipt_num, $amount);
        } else {
            return self::html_receipt_de($redeem, $customer_name, $receipt_num, $amount);
        }
    }

    /**
     * 🎨 HTML - Német verzió (DE) - Professional Design
     */
    private static function html_receipt_de($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Unternehmen', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($redeem['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($redeem['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($redeem['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        $customer = htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($redeem['user_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Prämie', ENT_QUOTES, 'UTF-8');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('d.m.Y', strtotime($redeem['redeemed_at']));
        $time = date('H:i', strtotime($redeem['redeemed_at']));
        $amount_formatted = number_format($amount, 2, ',', '.');

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ausgabebeleg Nr. {$receipt_num}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 700px;
            margin: 0 auto;
            padding: 25px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 22pt;
            font-weight: 700;
            color: #1a5276;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
        }
        .doc-subtitle {
            font-size: 10pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 11pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .doc-date {
            font-size: 10pt;
            color: #7f8c8d;
        }

        /* Info Sections */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .info-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .info-box:last-child {
            padding-right: 0;
            padding-left: 15px;
        }
        .info-label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #95a5a6;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .info-content {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
        }
        .info-content p {
            margin: 3px 0;
            font-size: 10pt;
        }
        .info-content .name {
            font-weight: 600;
            font-size: 11pt;
            color: #2c3e50;
        }

        /* Details Table */
        .details-section {
            margin: 25px 0;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table th {
            background: #1a5276;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .details-table th:last-child {
            text-align: right;
        }
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 10pt;
        }
        .details-table td:last-child {
            text-align: right;
        }
        .details-table .item-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .details-table .item-desc {
            font-size: 9pt;
            color: #7f8c8d;
            margin-top: 3px;
        }

        /* Amount Box */
        .amount-box {
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 20px 25px;
            margin: 25px 0;
            display: table;
            width: 100%;
        }
        .amount-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        .amount-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 28pt;
            font-weight: 700;
        }
        .amount-currency {
            font-size: 14pt;
            opacity: 0.8;
            margin-left: 5px;
        }

        /* Notes Section */
        .notes-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        .note-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .note-box:last-child {
            padding-right: 0;
            padding-left: 10px;
        }
        .note-card {
            padding: 12px 15px;
            font-size: 9pt;
        }
        .note-card.booking {
            background: #fef9e7;
            border-left: 3px solid #f39c12;
        }
        .note-card.legal {
            background: #eaf2f8;
            border-left: 3px solid #3498db;
        }
        .note-title {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .note-card.booking .note-title { color: #d68910; }
        .note-card.legal .note-title { color: #2874a6; }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 8pt;
            color: #95a5a6;
        }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Ausgabebeleg</div>
                <div class="doc-subtitle">Kundenprämie · Treueprogramm</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Nr. {$receipt_num}</div>
                <div class="doc-date">{$date} um {$time} Uhr</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Aussteller</div>
                <div class="info-content">
                    <p class="name">{$company}</p>
                    <p>{$address}</p>
                    <p>{$plz} {$city}</p>
                    {$tax_id}
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Empfänger</div>
                <div class="info-content">
                    <p class="name">{$customer}</p>
                    <p>{$email}</p>
                    <p>Eingelöste Punkte: {$points}</p>
                </div>
            </div>
        </div>

        <div class="details-section">
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Beschreibung</th>
                        <th>Betrag</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="item-name">{$reward}</div>
                            <div class="item-desc">Punkteeinlösung im Rahmen des Treueprogramms</div>
                        </td>
                        <td>{$amount_formatted} EUR</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="amount-box">
            <div class="amount-label">Gesamtbetrag</div>
            <div class="amount-value">{$amount_formatted}<span class="amount-currency">EUR</span></div>
        </div>

        <div class="notes-grid">
            <div class="note-box">
                <div class="note-card booking">
                    <div class="note-title">Buchungshinweis</div>
                    <p>Soll: 4930 (Werbekosten)</p>
                    <p>Haben: 1000 (Kasse)</p>
                </div>
            </div>
            <div class="note-box">
                <div class="note-card legal">
                    <div class="note-title">Steuerhinweis</div>
                    <p>Ohne Umsatzsteuer gem. § 1 UStG</p>
                    <p>Interne Vergütung – nicht steuerbar</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Dieses Dokument wurde automatisch erstellt und ist ohne Unterschrift gültig.</p>
            <p>PunktePass Treueprogramm · www.punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Román verzió (RO) - Professional Design
     */
    private static function html_receipt_ro($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Societate', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($redeem['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($redeem['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($redeem['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        $customer = htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($redeem['user_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Recompensă', ENT_QUOTES, 'UTF-8');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('d.m.Y', strtotime($redeem['redeemed_at']));
        $time = date('H:i', strtotime($redeem['redeemed_at']));
        $amount_formatted = number_format($amount, 2, ',', '.');

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Dispoziție de Plată Nr. {$receipt_num}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 700px;
            margin: 0 auto;
            padding: 25px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 22pt;
            font-weight: 700;
            color: #1a5276;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
        }
        .doc-subtitle {
            font-size: 10pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 11pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .doc-date {
            font-size: 10pt;
            color: #7f8c8d;
        }

        /* Info Sections */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .info-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .info-box:last-child {
            padding-right: 0;
            padding-left: 15px;
        }
        .info-label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #95a5a6;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .info-content {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
        }
        .info-content p {
            margin: 3px 0;
            font-size: 10pt;
        }
        .info-content .name {
            font-weight: 600;
            font-size: 11pt;
            color: #2c3e50;
        }

        /* Details Table */
        .details-section {
            margin: 25px 0;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table th {
            background: #1a5276;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .details-table th:last-child {
            text-align: right;
        }
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 10pt;
        }
        .details-table td:last-child {
            text-align: right;
        }
        .details-table .item-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .details-table .item-desc {
            font-size: 9pt;
            color: #7f8c8d;
            margin-top: 3px;
        }

        /* Amount Box */
        .amount-box {
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 20px 25px;
            margin: 25px 0;
            display: table;
            width: 100%;
        }
        .amount-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        .amount-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 28pt;
            font-weight: 700;
        }
        .amount-currency {
            font-size: 14pt;
            opacity: 0.8;
            margin-left: 5px;
        }

        /* Notes Section */
        .notes-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        .note-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .note-box:last-child {
            padding-right: 0;
            padding-left: 10px;
        }
        .note-card {
            padding: 12px 15px;
            font-size: 9pt;
        }
        .note-card.booking {
            background: #fef9e7;
            border-left: 3px solid #f39c12;
        }
        .note-card.legal {
            background: #eaf2f8;
            border-left: 3px solid #3498db;
        }
        .note-title {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .note-card.booking .note-title { color: #d68910; }
        .note-card.legal .note-title { color: #2874a6; }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 8pt;
            color: #95a5a6;
        }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Dispoziție de Plată</div>
                <div class="doc-subtitle">Recompensă Client · Program Fidelizare</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Nr. {$receipt_num}</div>
                <div class="doc-date">{$date} ora {$time}</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Emitent</div>
                <div class="info-content">
                    <p class="name">{$company}</p>
                    <p>{$address}</p>
                    <p>{$plz} {$city}</p>
                    {$tax_id}
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Beneficiar</div>
                <div class="info-content">
                    <p class="name">{$customer}</p>
                    <p>{$email}</p>
                    <p>Puncte utilizate: {$points}</p>
                </div>
            </div>
        </div>

        <div class="details-section">
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Descriere</th>
                        <th>Valoare</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="item-name">{$reward}</div>
                            <div class="item-desc">Utilizare puncte din programul de fidelizare</div>
                        </td>
                        <td>{$amount_formatted} RON</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="amount-box">
            <div class="amount-label">Suma Totală</div>
            <div class="amount-value">{$amount_formatted}<span class="amount-currency">RON</span></div>
        </div>

        <div class="notes-grid">
            <div class="note-box">
                <div class="note-card booking">
                    <div class="note-title">Înregistrare Contabilă</div>
                    <p>Debit: 623 (Cheltuieli cu publicitatea)</p>
                    <p>Credit: 5311 (Casa în lei)</p>
                </div>
            </div>
            <div class="note-box">
                <div class="note-card legal">
                    <div class="note-title">Mențiuni Fiscale</div>
                    <p>Operațiune fără TVA</p>
                    <p>Consum intern – neimpozabil</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Document generat automat, valabil fără semnătură.</p>
            <p>Program PunktePass · www.punktepass.de</p>
            <p>Pentru întrebări: info@punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Magyar verzió (HU) - Professional Design
     */
    private static function html_receipt_hu($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Vállalkozás', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($redeem['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($redeem['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($redeem['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        $customer = htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($redeem['user_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Jutalom', ENT_QUOTES, 'UTF-8');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('Y.m.d', strtotime($redeem['redeemed_at']));
        $time = date('H:i', strtotime($redeem['redeemed_at']));
        $amount_formatted = number_format($amount, 2, ',', '.');

        return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Kiadási bizonylat {$receipt_num}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt { max-width: 700px; margin: 0 auto; padding: 25px; }
        .header { display: table; width: 100%; border-bottom: 3px solid #1a5276; padding-bottom: 20px; margin-bottom: 25px; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; width: 40%; }
        .doc-title { font-size: 22pt; font-weight: 700; color: #1a5276; letter-spacing: -0.5px; margin-bottom: 5px; }
        .doc-subtitle { font-size: 10pt; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; }
        .doc-number { font-size: 11pt; color: #1a5276; font-weight: 600; margin-bottom: 5px; }
        .doc-date { font-size: 10pt; color: #7f8c8d; }
        .info-grid { display: table; width: 100%; margin-bottom: 25px; }
        .info-box { display: table-cell; width: 50%; vertical-align: top; padding-right: 15px; }
        .info-box:last-child { padding-right: 0; padding-left: 15px; }
        .info-label { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #95a5a6; margin-bottom: 8px; font-weight: 600; }
        .info-content { background: #f8f9fa; border-left: 3px solid #1a5276; padding: 12px 15px; }
        .info-content p { margin: 3px 0; font-size: 10pt; }
        .info-content .name { font-weight: 600; font-size: 11pt; color: #2c3e50; }
        .details-section { margin: 25px 0; }
        .details-table { width: 100%; border-collapse: collapse; }
        .details-table th { background: #1a5276; color: #fff; padding: 10px 12px; text-align: left; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .details-table th:last-child { text-align: right; }
        .details-table td { padding: 12px; border-bottom: 1px solid #ecf0f1; font-size: 10pt; }
        .details-table td:last-child { text-align: right; }
        .details-table .item-name { font-weight: 600; color: #2c3e50; }
        .details-table .item-desc { font-size: 9pt; color: #7f8c8d; margin-top: 3px; }
        .amount-box { background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%); color: #fff; padding: 20px 25px; margin: 25px 0; display: table; width: 100%; }
        .amount-label { display: table-cell; vertical-align: middle; font-size: 10pt; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }
        .amount-value { display: table-cell; vertical-align: middle; text-align: right; font-size: 28pt; font-weight: 700; }
        .amount-currency { font-size: 14pt; opacity: 0.8; margin-left: 5px; }
        .notes-grid { display: table; width: 100%; margin: 20px 0; }
        .note-box { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .note-box:last-child { padding-right: 0; padding-left: 10px; }
        .note-card { padding: 12px 15px; font-size: 9pt; }
        .note-card.booking { background: #fef9e7; border-left: 3px solid #f39c12; }
        .note-card.legal { background: #eaf2f8; border-left: 3px solid #3498db; }
        .note-title { font-weight: 600; margin-bottom: 8px; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .note-card.booking .note-title { color: #d68910; }
        .note-card.legal .note-title { color: #2874a6; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ecf0f1; text-align: center; font-size: 8pt; color: #95a5a6; }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Kiadási bizonylat</div>
                <div class="doc-subtitle">Vásárlói jutalom · Hűségprogram</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Sz. {$receipt_num}</div>
                <div class="doc-date">{$date} {$time}</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Kiállító</div>
                <div class="info-content">
                    <p class="name">{$company}</p>
                    <p>{$address}</p>
                    <p>{$plz} {$city}</p>
                    {$tax_id}
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Kedvezményezett</div>
                <div class="info-content">
                    <p class="name">{$customer}</p>
                    <p>{$email}</p>
                    <p>Felhasznált pontok: {$points}</p>
                </div>
            </div>
        </div>

        <div class="details-section">
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Leírás</th>
                        <th>Összeg</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="item-name">{$reward}</div>
                            <div class="item-desc">Pontbeváltás a hűségprogram keretében</div>
                        </td>
                        <td>{$amount_formatted} EUR</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="amount-box">
            <div class="amount-label">Végösszeg</div>
            <div class="amount-value">{$amount_formatted}<span class="amount-currency">EUR</span></div>
        </div>

        <div class="notes-grid">
            <div class="note-box">
                <div class="note-card booking">
                    <div class="note-title">Könyvelési útmutató</div>
                    <p>Tartozik: 4930 (Reklámköltségek)</p>
                    <p>Követel: 1000 (Pénztár)</p>
                </div>
            </div>
            <div class="note-box">
                <div class="note-card legal">
                    <div class="note-title">Adózási megjegyzés</div>
                    <p>ÁFA nélkül a § 1 UStG alapján</p>
                    <p>Belső juttatás – nem adóköteles</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Ez a dokumentum automatikusan készült, aláírás nélkül is érvényes.</p>
            <p>PunktePass Hűségprogram · www.punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML generálás HAVI bizonylathoz
     */
    private static function generate_html_for_monthly($store, $redeems, $year, $month, $lang, $group_by_filiale = false) {
        $total_amount = 0;
        $total_points = 0;

        foreach ($redeems as $r) {
            $total_amount += self::calculate_item_amount($r);
            $total_points += intval($r->points_spent ?? 0);
        }

        // Group items by filiale if requested
        $grouped_redeems = null;
        if ($group_by_filiale) {
            $grouped_redeems = [];
            foreach ($redeems as $r) {
                $filiale_key = $r->store_id ?? 0;
                $filiale_name = $r->filiale_name ?: ($r->filiale_company ?: 'Unbekannt');
                if ($r->filiale_city) {
                    $filiale_name .= ' – ' . $r->filiale_city;
                }
                if (!isset($grouped_redeems[$filiale_key])) {
                    $grouped_redeems[$filiale_key] = [
                        'name' => $filiale_name,
                        'items' => [],
                        'total_amount' => 0,
                        'total_points' => 0
                    ];
                }
                $grouped_redeems[$filiale_key]['items'][] = $r;
                $grouped_redeems[$filiale_key]['total_amount'] += self::calculate_item_amount($r);
                $grouped_redeems[$filiale_key]['total_points'] += intval($r->points_spent ?? 0);
            }
        }

        if ($lang === 'RO') {
            return self::html_monthly_receipt_ro($store, $redeems, $year, $month, $total_amount, $total_points, $grouped_redeems);
        } elseif ($lang === 'HU') {
            return self::html_monthly_receipt_hu($store, $redeems, $year, $month, $total_amount, $total_points, $grouped_redeems);
        } else {
            return self::html_monthly_receipt_de($store, $redeems, $year, $month, $total_amount, $total_points, $grouped_redeems);
        }
    }

    /**
     * 💰 Amount calculation helper: actual_amount → action_value → free_product_value → 0
     */
    private static function calculate_item_amount($item) {
        if (!empty($item->actual_amount) && floatval($item->actual_amount) > 0) {
            return floatval($item->actual_amount);
        }
        if (!empty($item->action_value) && $item->action_value !== '0') {
            return floatval($item->action_value);
        }
        if (!empty($item->free_product_value) && floatval($item->free_product_value) > 0) {
            return floatval($item->free_product_value);
        }
        return 0;
    }

    /**
     * 🎨 HTML - Havi bizonylat NÉMET verzió - Professional Design
     */
    private static function html_monthly_receipt_de($store, $redeems, $year, $month, $total_amount, $total_points, $grouped_redeems = null) {
        $company = htmlspecialchars($store['company_name'] ?? 'Unternehmen', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($store['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($store['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($store['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        // German month names
        $german_months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $month_str = $german_months[$month - 1] . ' ' . $year;
        $count = count($redeems);
        $total_formatted = number_format($total_amount, 2, ',', '.');
        $receipt_num = sprintf('%d-%02d-M', $year, $month);

        $rows = '';

        // Grouped by filiale
        if ($grouped_redeems && count($grouped_redeems) > 1) {
            foreach ($grouped_redeems as $filiale_id => $filiale_data) {
                $filiale_name = htmlspecialchars($filiale_data['name'], ENT_QUOTES, 'UTF-8');
                $filiale_total = number_format($filiale_data['total_amount'], 2, ',', '.');
                $filiale_points = $filiale_data['total_points'];

                $rows .= "<tr class=\"filiale-header\">
                    <td colspan=\"5\" style=\"background: #e8f4f8; font-weight: 600; padding: 8px; border-top: 2px solid #1a5276;\">
                        📍 {$filiale_name}
                    </td>
                </tr>";

                foreach ($filiale_data['items'] as $r) {
                    $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
                    if (!$customer) {
                        $customer = htmlspecialchars($r->user_email ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
                    }
                    $reward = htmlspecialchars($r->reward_title ?? 'Prämie', ENT_QUOTES, 'UTF-8');
                    $points = intval($r->points_spent ?? 0);
                    $amount = self::calculate_item_amount($r);
                    $amount_fmt = number_format($amount, 2, ',', '.');
                    $date = date('d.m.Y', strtotime($r->redeemed_at));

                    $rows .= "<tr>
                        <td>{$date}</td>
                        <td>{$customer}</td>
                        <td>{$reward}</td>
                        <td class=\"num\">{$points}</td>
                        <td class=\"num\">{$amount_fmt}</td>
                    </tr>";
                }

                $rows .= "<tr class=\"filiale-subtotal\">
                    <td colspan=\"3\" style=\"text-align: right; font-weight: 500; background: #f5f5f5;\">Zwischensumme {$filiale_name}:</td>
                    <td class=\"num\" style=\"font-weight: 500; background: #f5f5f5;\">{$filiale_points}</td>
                    <td class=\"num\" style=\"font-weight: 500; background: #f5f5f5;\">{$filiale_total} €</td>
                </tr>";
            }
        } else {
            // Normal single-store layout
            foreach ($redeems as $r) {
                $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
                if (!$customer) {
                    $customer = htmlspecialchars($r->user_email ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
                }
                $reward = htmlspecialchars($r->reward_title ?? 'Prämie', ENT_QUOTES, 'UTF-8');
                $points = intval($r->points_spent ?? 0);
                $amount = self::calculate_item_amount($r);
                $amount_fmt = number_format($amount, 2, ',', '.');
                $date = date('d.m.Y', strtotime($r->redeemed_at));

                $rows .= "<tr>
                    <td>{$date}</td>
                    <td>{$customer}</td>
                    <td>{$reward}</td>
                    <td class=\"num\">{$points}</td>
                    <td class=\"num\">{$amount_fmt}</td>
                </tr>";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Monatsabrechnung {$month_str}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .doc-period {
            font-size: 11pt;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Company Info */
        .company-box {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .company-box p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .company-box .name {
            font-weight: 600;
            font-size: 10pt;
            color: #2c3e50;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .data-table thead th {
            background: #1a5276;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-table thead th.num {
            text-align: right;
        }
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .data-table tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        /* Summary Row */
        .summary-row {
            display: table;
            width: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .summary-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 18pt;
            font-weight: 700;
        }
        .summary-currency {
            font-size: 10pt;
            opacity: 0.8;
            margin-left: 3px;
        }
        .summary-details {
            font-size: 8pt;
            opacity: 0.8;
            margin-top: 3px;
        }

        /* Notes */
        .notes-row {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        .note-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }
        .note-cell:last-child {
            padding-right: 0;
            padding-left: 8px;
        }
        .note-box {
            padding: 10px 12px;
            font-size: 8pt;
        }
        .note-box.booking {
            background: #fef9e7;
            border-left: 3px solid #f39c12;
        }
        .note-box.legal {
            background: #eaf2f8;
            border-left: 3px solid #3498db;
        }
        .note-title {
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .note-box.booking .note-title { color: #d68910; }
        .note-box.legal .note-title { color: #2874a6; }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 7pt;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Monatsabrechnung</div>
                <div class="doc-subtitle">Sammelbeleg Kundenprämien</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Nr. {$receipt_num}</div>
                <div class="doc-period">{$month_str}</div>
            </div>
        </div>

        <div class="company-box">
            <p class="name">{$company}</p>
            <p>{$address}, {$plz} {$city}</p>
            {$tax_id}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kunde</th>
                    <th>Prämie</th>
                    <th class="num">Punkte</th>
                    <th class="num">Betrag (EUR)</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>

        <div class="summary-row">
            <div class="summary-label">
                Gesamtbetrag
                <div class="summary-details">{$count} Einlösungen · {$total_points} Punkte</div>
            </div>
            <div class="summary-value">{$total_formatted}<span class="summary-currency">EUR</span></div>
        </div>

        <div class="notes-row">
            <div class="note-cell">
                <div class="note-box booking">
                    <div class="note-title">Buchungshinweis</div>
                    <p>Soll: 4930 (Werbekosten)</p>
                    <p>Haben: 1000 (Kasse)</p>
                </div>
            </div>
            <div class="note-cell">
                <div class="note-box legal">
                    <div class="note-title">Steuerhinweis</div>
                    <p>Ohne Umsatzsteuer gem. § 1 UStG</p>
                    <p>Interne Vergütung – nicht steuerbar</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Automatisch erstellt · PunktePass Treueprogramm · www.punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Havi bizonylat ROMÁN verzió - Professional Design
     */
    private static function html_monthly_receipt_ro($store, $redeems, $year, $month, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Societate', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($store['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($store['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($store['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        // Romanian month names
        $romanian_months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
        $month_str = $romanian_months[$month - 1] . ' ' . $year;
        $count = count($redeems);
        $total_formatted = number_format($total_amount, 2, ',', '.');
        $receipt_num = sprintf('%d-%02d-L', $year, $month);

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Necunoscut', ENT_QUOTES, 'UTF-8');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Recompensă', ENT_QUOTES, 'UTF-8');
            $points = intval($r->points_spent ?? 0);
            $amount = self::calculate_item_amount($r);
            $amount_fmt = number_format($amount, 2, ',', '.');
            $date = date('d.m.Y', strtotime($r->redeemed_at));

            $rows .= "<tr>
                <td>{$date}</td>
                <td>{$customer}</td>
                <td>{$reward}</td>
                <td class=\"num\">{$points}</td>
                <td class=\"num\">{$amount_fmt}</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Situație Lunară {$month_str}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .doc-period {
            font-size: 11pt;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Company Info */
        .company-box {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .company-box p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .company-box .name {
            font-weight: 600;
            font-size: 10pt;
            color: #2c3e50;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .data-table thead th {
            background: #1a5276;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-table thead th.num {
            text-align: right;
        }
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .data-table tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        /* Summary Row */
        .summary-row {
            display: table;
            width: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .summary-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 18pt;
            font-weight: 700;
        }
        .summary-currency {
            font-size: 10pt;
            opacity: 0.8;
            margin-left: 3px;
        }
        .summary-details {
            font-size: 8pt;
            opacity: 0.8;
            margin-top: 3px;
        }

        /* Notes */
        .notes-row {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        .note-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }
        .note-cell:last-child {
            padding-right: 0;
            padding-left: 8px;
        }
        .note-box {
            padding: 10px 12px;
            font-size: 8pt;
        }
        .note-box.booking {
            background: #fef9e7;
            border-left: 3px solid #f39c12;
        }
        .note-box.legal {
            background: #eaf2f8;
            border-left: 3px solid #3498db;
        }
        .note-title {
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .note-box.booking .note-title { color: #d68910; }
        .note-box.legal .note-title { color: #2874a6; }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 7pt;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Situație Lunară</div>
                <div class="doc-subtitle">Centralizator Recompense Clienți</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Nr. {$receipt_num}</div>
                <div class="doc-period">{$month_str}</div>
            </div>
        </div>

        <div class="company-box">
            <p class="name">{$company}</p>
            <p>{$address}, {$plz} {$city}</p>
            {$tax_id}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Client</th>
                    <th>Recompensă</th>
                    <th class="num">Puncte</th>
                    <th class="num">Valoare (RON)</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>

        <div class="summary-row">
            <div class="summary-label">
                Suma Totală
                <div class="summary-details">{$count} utilizări · {$total_points} puncte</div>
            </div>
            <div class="summary-value">{$total_formatted}<span class="summary-currency">RON</span></div>
        </div>

        <div class="notes-row">
            <div class="note-cell">
                <div class="note-box booking">
                    <div class="note-title">Înregistrare Contabilă</div>
                    <p>Debit: 623 (Cheltuieli cu publicitatea)</p>
                    <p>Credit: 5311 (Casa în lei)</p>
                </div>
            </div>
            <div class="note-cell">
                <div class="note-box legal">
                    <div class="note-title">Mențiuni Fiscale</div>
                    <p>Operațiune fără TVA</p>
                    <p>Consum intern – neimpozabil</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Document generat automat · Program PunktePass · www.punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 💾 FÁJL MENTÉS - DOMPDF-el valódi PDF generálás
     */
    private static function save_receipt_file($html, $filename) {
        $upload_dir = wp_upload_dir();
        $receipts_dir = $upload_dir['basedir'] . '/' . self::RECEIPTS_DIR;

        // Mappa létrehozása
        if (!is_dir($receipts_dir)) {
            if (!wp_mkdir_p($receipts_dir)) {
                ppv_log("❌ [PPV_EXPENSE_RECEIPT] Mappa létrehozás sikertelen: {$receipts_dir}");
                return false;
            }
        }

        $filepath = $receipts_dir . '/' . $filename;

        // ✅ DOMPDF betöltése és PDF generálás
        try {
            $dompdf_autoload = PPV_PLUGIN_DIR . 'libs/dompdf/vendor/autoload.php';
            if (!file_exists($dompdf_autoload)) {
                ppv_log("❌ [PPV_EXPENSE_RECEIPT] DOMPDF autoload nem található: {$dompdf_autoload}");
                return false;
            }

            require_once $dompdf_autoload;

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isPhpEnabled', false);
            $options->set('isFontSubsettingEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdf_content = $dompdf->output();
            $bytes = file_put_contents($filepath, $pdf_content);

            if ($bytes === false) {
                ppv_log("❌ [PPV_EXPENSE_RECEIPT] PDF írás sikertelen: {$filepath}");
                return false;
            }

            ppv_log("✅ [PPV_EXPENSE_RECEIPT] PDF mentve: {$filename} ({$bytes} bytes)");

        } catch (\Exception $e) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] DOMPDF hiba: " . $e->getMessage());
            return false;
        }

        // Relatív útvonal visszaadása az adatbázisba
        return self::RECEIPTS_DIR . '/' . $filename;
    }

    /**
     * 🔗 PDF URL generálás
     */
    public static function get_receipt_url($receipt_path) {
        if (!$receipt_path) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/' . $receipt_path;
    }

    /**
     * 🎨 HTML generálás IDŐSZAK bizonylathoz (Zeitraumbericht)
     */
    private static function generate_html_for_date_range($store, $redeems, $date_from, $date_to, $lang, $group_by_filiale = false) {
        $total_amount = 0;
        $total_points = 0;

        foreach ($redeems as $r) {
            $total_amount += self::calculate_item_amount($r);
            $total_points += intval($r->points_spent ?? 0);
        }

        // Group items by filiale if requested
        $grouped_redeems = null;
        if ($group_by_filiale) {
            $grouped_redeems = [];
            foreach ($redeems as $r) {
                $filiale_key = $r->store_id ?? 0;
                $filiale_name = $r->filiale_name ?: ($r->filiale_company ?: 'Unbekannt');
                if ($r->filiale_city) {
                    $filiale_name .= ' – ' . $r->filiale_city;
                }
                if (!isset($grouped_redeems[$filiale_key])) {
                    $grouped_redeems[$filiale_key] = [
                        'name' => $filiale_name,
                        'items' => [],
                        'total_amount' => 0,
                        'total_points' => 0
                    ];
                }
                $grouped_redeems[$filiale_key]['items'][] = $r;
                $grouped_redeems[$filiale_key]['total_amount'] += self::calculate_item_amount($r);
                $grouped_redeems[$filiale_key]['total_points'] += intval($r->points_spent ?? 0);
            }
        }

        if ($lang === 'RO') {
            return self::html_date_range_receipt_ro($store, $redeems, $date_from, $date_to, $total_amount, $total_points, $grouped_redeems);
        } elseif ($lang === 'HU') {
            return self::html_date_range_receipt_hu($store, $redeems, $date_from, $date_to, $total_amount, $total_points, $grouped_redeems);
        } else {
            return self::html_date_range_receipt_de($store, $redeems, $date_from, $date_to, $total_amount, $total_points, $grouped_redeems);
        }
    }

    /**
     * 🎨 HTML - Időszak bizonylat NÉMET verzió - Professional Design
     */
    private static function html_date_range_receipt_de($store, $redeems, $date_from, $date_to, $total_amount, $total_points, $grouped_redeems = null) {
        $company = htmlspecialchars($store['company_name'] ?? 'Unternehmen', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($store['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($store['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($store['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        // Date range formatting
        $from_formatted = date('d.m.Y', strtotime($date_from));
        $to_formatted = date('d.m.Y', strtotime($date_to));
        $period_str = $from_formatted . ' - ' . $to_formatted;
        $count = count($redeems);
        $total_formatted = number_format($total_amount, 2, ',', '.');
        $receipt_num = sprintf('ZR-%s-%s', str_replace('-', '', $date_from), str_replace('-', '', $date_to));

        $rows = '';

        // Grouped by filiale
        if ($grouped_redeems && count($grouped_redeems) > 1) {
            foreach ($grouped_redeems as $filiale_id => $filiale_data) {
                $filiale_name = htmlspecialchars($filiale_data['name'], ENT_QUOTES, 'UTF-8');
                $filiale_total = number_format($filiale_data['total_amount'], 2, ',', '.');
                $filiale_points = $filiale_data['total_points'];

                $rows .= "<tr class=\"filiale-header\">
                    <td colspan=\"5\" style=\"background: #e8f4f8; font-weight: 600; padding: 8px; border-top: 2px solid #1a5276;\">
                        📍 {$filiale_name}
                    </td>
                </tr>";

                foreach ($filiale_data['items'] as $r) {
                    $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
                    if (!$customer) {
                        $customer = htmlspecialchars($r->user_email ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
                    }
                    $reward = htmlspecialchars($r->reward_title ?? 'Prämie', ENT_QUOTES, 'UTF-8');
                    $points = intval($r->points_spent ?? 0);
                    $amount = self::calculate_item_amount($r);
                    $amount_fmt = number_format($amount, 2, ',', '.');
                    $date = date('d.m.Y', strtotime($r->redeemed_at));

                    $rows .= "<tr>
                        <td>{$date}</td>
                        <td>{$customer}</td>
                        <td>{$reward}</td>
                        <td class=\"num\">{$points}</td>
                        <td class=\"num\">{$amount_fmt}</td>
                    </tr>";
                }

                $rows .= "<tr class=\"filiale-subtotal\">
                    <td colspan=\"3\" style=\"text-align: right; font-weight: 500; background: #f5f5f5;\">Zwischensumme {$filiale_name}:</td>
                    <td class=\"num\" style=\"font-weight: 500; background: #f5f5f5;\">{$filiale_points}</td>
                    <td class=\"num\" style=\"font-weight: 500; background: #f5f5f5;\">{$filiale_total} €</td>
                </tr>";
            }
        } else {
            // Normal single-store layout
            foreach ($redeems as $r) {
                $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
                if (!$customer) {
                    $customer = htmlspecialchars($r->user_email ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
                }
                $reward = htmlspecialchars($r->reward_title ?? 'Prämie', ENT_QUOTES, 'UTF-8');
                $points = intval($r->points_spent ?? 0);
                $amount = self::calculate_item_amount($r);
                $amount_fmt = number_format($amount, 2, ',', '.');
                $row_date = date('d.m.Y', strtotime($r->redeemed_at));

                $rows .= "<tr>
                    <td>{$row_date}</td>
                    <td>{$customer}</td>
                    <td>{$reward}</td>
                    <td class=\"num\">{$points}</td>
                    <td class=\"num\">{$amount_fmt}</td>
                </tr>";
            }
        }

        $generated_date = date('d.m.Y H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zeitraumbericht {$period_str}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .doc-period {
            font-size: 11pt;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Company Info */
        .company-box {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .company-box p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .company-box .name {
            font-weight: 600;
            font-size: 10pt;
            color: #2c3e50;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .data-table thead th {
            background: #1a5276;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-table thead th.num {
            text-align: right;
        }
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .data-table tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        /* Summary Row */
        .summary-row {
            display: table;
            width: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .summary-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 18pt;
            font-weight: 700;
        }
        .summary-currency {
            font-size: 10pt;
            opacity: 0.8;
            margin-left: 3px;
        }
        .summary-details {
            font-size: 8pt;
            opacity: 0.8;
            margin-top: 3px;
        }

        /* Notes */
        .notes-row {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        .note-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }
        .note-cell:last-child {
            padding-right: 0;
            padding-left: 8px;
        }
        .note-box {
            padding: 10px 12px;
            font-size: 8pt;
        }
        .note-box.booking {
            background: #fef9e7;
            border-left: 3px solid #f39c12;
        }
        .note-box.legal {
            background: #eaf2f8;
            border-left: 3px solid #3498db;
        }
        .note-title {
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .note-box.booking .note-title { color: #d68910; }
        .note-box.legal .note-title { color: #2874a6; }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 7pt;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Zeitraumbericht</div>
                <div class="doc-subtitle">Sammelbeleg Kundenprämien</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Nr. {$receipt_num}</div>
                <div class="doc-period">{$period_str}</div>
            </div>
        </div>

        <div class="company-box">
            <p class="name">{$company}</p>
            <p>{$address}, {$plz} {$city}</p>
            {$tax_id}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kunde</th>
                    <th>Prämie</th>
                    <th class="num">Punkte</th>
                    <th class="num">Wert (€)</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>

        <div class="summary-row">
            <div class="summary-label">
                Gesamtsumme<br>
                <span class="summary-details">{$count} Einlösungen · {$total_points} Punkte</span>
            </div>
            <div class="summary-value">
                {$total_formatted}<span class="summary-currency">€</span>
            </div>
        </div>

        <div class="notes-row">
            <div class="note-cell">
                <div class="note-box booking">
                    <div class="note-title">📋 Buchungshinweis</div>
                    Werbeaufwand oder Kundenbindungskosten
                </div>
            </div>
            <div class="note-cell">
                <div class="note-box legal">
                    <div class="note-title">⚖️ Rechtlicher Hinweis</div>
                    Kundenprämien gemäß § 4 Nr. 5 UStG umsatzsteuerfrei
                </div>
            </div>
        </div>

        <div class="footer">
            Erstellt am: {$generated_date} · PunktePass Loyalty System
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Időszak bizonylat ROMÁN verzió
     */
    private static function html_date_range_receipt_ro($store, $redeems, $date_from, $date_to, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Companie', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($store['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($store['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($store['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        // Date range formatting
        $from_formatted = date('d.m.Y', strtotime($date_from));
        $to_formatted = date('d.m.Y', strtotime($date_to));
        $period_str = $from_formatted . ' - ' . $to_formatted;
        $count = count($redeems);
        $total_formatted = number_format($total_amount, 2, ',', '.');
        $receipt_num = sprintf('RP-%s-%s', str_replace('-', '', $date_from), str_replace('-', '', $date_to));

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Necunoscut', ENT_QUOTES, 'UTF-8');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Premiu', ENT_QUOTES, 'UTF-8');
            $points = intval($r->points_spent ?? 0);
            $amount = self::calculate_item_amount($r);
            $amount_fmt = number_format($amount, 2, ',', '.');
            $row_date = date('d.m.Y', strtotime($r->redeemed_at));

            $rows .= "<tr>
                <td>{$row_date}</td>
                <td>{$customer}</td>
                <td>{$reward}</td>
                <td class=\"num\">{$points}</td>
                <td class=\"num\">{$amount_fmt}</td>
            </tr>";
        }

        $generated_date = date('d.m.Y H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Raport Perioadă {$period_str}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .doc-period {
            font-size: 11pt;
            font-weight: 600;
            color: #2c3e50;
        }
        .company-box {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .company-box p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .company-box .name {
            font-weight: 600;
            font-size: 10pt;
            color: #2c3e50;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .data-table thead th {
            background: #1a5276;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-table thead th.num {
            text-align: right;
        }
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .data-table tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .summary-row {
            display: table;
            width: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .summary-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 18pt;
            font-weight: 700;
        }
        .summary-currency {
            font-size: 10pt;
            opacity: 0.8;
            margin-left: 3px;
        }
        .summary-details {
            font-size: 8pt;
            opacity: 0.8;
            margin-top: 3px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 7pt;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Raport Perioadă</div>
                <div class="doc-subtitle">Document Colectiv Premii Clienți</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Nr. {$receipt_num}</div>
                <div class="doc-period">{$period_str}</div>
            </div>
        </div>

        <div class="company-box">
            <p class="name">{$company}</p>
            <p>{$address}, {$plz} {$city}</p>
            {$tax_id}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Client</th>
                    <th>Premiu</th>
                    <th class="num">Puncte</th>
                    <th class="num">Valoare (€)</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>

        <div class="summary-row">
            <div class="summary-label">
                Total<br>
                <span class="summary-details">{$count} răscumpărări · {$total_points} puncte</span>
            </div>
            <div class="summary-value">
                {$total_formatted}<span class="summary-currency">€</span>
            </div>
        </div>

        <div class="footer">
            Generat: {$generated_date} · PunktePass Loyalty System
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Időszak bizonylat MAGYAR verzió
     */
    private static function html_date_range_receipt_hu($store, $redeems, $date_from, $date_to, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Vállalkozás', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($store['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($store['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($store['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        // Date range formatting
        $from_formatted = date('Y.m.d', strtotime($date_from));
        $to_formatted = date('Y.m.d', strtotime($date_to));
        $period_str = $from_formatted . ' - ' . $to_formatted;
        $count = count($redeems);
        $total_formatted = number_format($total_amount, 0, ',', ' ');
        $receipt_num = sprintf('IJ-%s-%s', str_replace('-', '', $date_from), str_replace('-', '', $date_to));

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Ismeretlen', ENT_QUOTES, 'UTF-8');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Jutalom', ENT_QUOTES, 'UTF-8');
            $points = intval($r->points_spent ?? 0);
            $amount = self::calculate_item_amount($r);
            $amount_fmt = number_format($amount, 0, ',', ' ');
            $row_date = date('Y.m.d', strtotime($r->redeemed_at));

            $rows .= "<tr>
                <td>{$row_date}</td>
                <td>{$customer}</td>
                <td>{$reward}</td>
                <td class=\"num\">{$points}</td>
                <td class=\"num\">{$amount_fmt}</td>
            </tr>";
        }

        $generated_date = date('Y.m.d H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Időszaki jelentés {$period_str}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .doc-period {
            font-size: 11pt;
            font-weight: 600;
            color: #2c3e50;
        }
        .company-box {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .company-box p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .company-box .name {
            font-weight: 600;
            font-size: 10pt;
            color: #2c3e50;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .data-table thead th {
            background: #1a5276;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-table thead th.num {
            text-align: right;
        }
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .data-table tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .summary-row {
            display: table;
            width: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .summary-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 18pt;
            font-weight: 700;
        }
        .summary-currency {
            font-size: 10pt;
            opacity: 0.8;
            margin-left: 3px;
        }
        .summary-details {
            font-size: 8pt;
            opacity: 0.8;
            margin-top: 3px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 7pt;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Időszaki jelentés</div>
                <div class="doc-subtitle">Összesített jutalom bizonylat</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Sz. {$receipt_num}</div>
                <div class="doc-period">{$period_str}</div>
            </div>
        </div>

        <div class="company-box">
            <p class="name">{$company}</p>
            <p>{$address}, {$plz} {$city}</p>
            {$tax_id}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Dátum</th>
                    <th>Ügyfél</th>
                    <th>Jutalom</th>
                    <th class="num">Pont</th>
                    <th class="num">Érték (Ft)</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>

        <div class="summary-row">
            <div class="summary-label">
                Összesen<br>
                <span class="summary-details">{$count} beváltás · {$total_points} pont</span>
            </div>
            <div class="summary-value">
                {$total_formatted}<span class="summary-currency">Ft</span>
            </div>
        </div>

        <div class="footer">
            Készült: {$generated_date} · PunktePass Loyalty System
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Havi bizonylat MAGYAR verzió
     */
    private static function html_monthly_receipt_hu($store, $redeems, $year, $month, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Vállalkozás', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $plz = htmlspecialchars($store['plz'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($store['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $tax_id = htmlspecialchars($store['tax_id'] ?? '', ENT_QUOTES, 'UTF-8');

        // Hungarian month names
        $hungarian_months = ['Január', 'Február', 'Március', 'Április', 'Május', 'Június', 'Július', 'Augusztus', 'Szeptember', 'Október', 'November', 'December'];
        $month_str = $year . '. ' . $hungarian_months[$month - 1];
        $count = count($redeems);
        $total_formatted = number_format($total_amount, 0, ',', ' ');
        $receipt_num = sprintf('%d-%02d-H', $year, $month);

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')), ENT_QUOTES, 'UTF-8');
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Ismeretlen', ENT_QUOTES, 'UTF-8');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Jutalom', ENT_QUOTES, 'UTF-8');
            $points = intval($r->points_spent ?? 0);
            $amount = self::calculate_item_amount($r);
            $amount_fmt = number_format($amount, 0, ',', ' ');
            $date = date('Y.m.d', strtotime($r->redeemed_at));

            $rows .= "<tr>
                <td>{$date}</td>
                <td>{$customer}</td>
                <td>{$reward}</td>
                <td class=\"num\">{$points}</td>
                <td class=\"num\">{$amount_fmt}</td>
            </tr>";
        }

        $generated_date = date('Y.m.d H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Havi elszámolás {$month_str}</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10pt;
            color: #1a5276;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .doc-period {
            font-size: 11pt;
            font-weight: 600;
            color: #2c3e50;
        }
        .company-box {
            background: #f8f9fa;
            border-left: 3px solid #1a5276;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .company-box p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .company-box .name {
            font-weight: 600;
            font-size: 10pt;
            color: #2c3e50;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .data-table thead th {
            background: #1a5276;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-table thead th.num {
            text-align: right;
        }
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        .data-table tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .summary-row {
            display: table;
            width: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%);
            color: #fff;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .summary-label {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-value {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 18pt;
            font-weight: 700;
        }
        .summary-currency {
            font-size: 10pt;
            opacity: 0.8;
            margin-left: 3px;
        }
        .summary-details {
            font-size: 8pt;
            opacity: 0.8;
            margin-top: 3px;
        }
        .notes-row {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        .note-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }
        .note-cell:last-child {
            padding-right: 0;
            padding-left: 8px;
        }
        .note-box {
            padding: 10px 12px;
            font-size: 8pt;
        }
        .note-box.booking {
            background: #fef9e7;
            border-left: 3px solid #f39c12;
        }
        .note-box.legal {
            background: #eaf2f8;
            border-left: 3px solid #3498db;
        }
        .note-title {
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .note-box.booking .note-title { color: #d68910; }
        .note-box.legal .note-title { color: #2874a6; }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
            font-size: 7pt;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-left">
                <div class="doc-title">Havi elszámolás</div>
                <div class="doc-subtitle">Összesített jutalom bizonylat</div>
            </div>
            <div class="header-right">
                <div class="doc-number">Sz. {$receipt_num}</div>
                <div class="doc-period">{$month_str}</div>
            </div>
        </div>

        <div class="company-box">
            <p class="name">{$company}</p>
            <p>{$address}, {$plz} {$city}</p>
            {$tax_id}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Dátum</th>
                    <th>Ügyfél</th>
                    <th>Jutalom</th>
                    <th class="num">Pont</th>
                    <th class="num">Érték (Ft)</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>

        <div class="summary-row">
            <div class="summary-label">
                Összesen<br>
                <span class="summary-details">{$count} beváltás · {$total_points} pont</span>
            </div>
            <div class="summary-value">
                {$total_formatted}<span class="summary-currency">Ft</span>
            </div>
        </div>

        <div class="notes-row">
            <div class="note-cell">
                <div class="note-box booking">
                    <div class="note-title">📋 Könyvelési megjegyzés</div>
                    Reklámköltség vagy ügyfélmegtartási költség
                </div>
            </div>
            <div class="note-cell">
                <div class="note-box legal">
                    <div class="note-title">⚖️ Jogi megjegyzés</div>
                    Ügyféljutalmak a hatályos jogszabályok szerint
                </div>
            </div>
        </div>

        <div class="footer">
            Készült: {$generated_date} · PunktePass Loyalty System
        </div>
    </div>
</body>
</html>
HTML;
    }
}