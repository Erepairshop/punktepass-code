<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Kiadási Bizonylat Generálás (Expense Receipt)
 * Version: 3.1 - Free Product Support
 * ✅ Modern design (Inter font, gradient header, card layout)
 * ✅ Egyszeri + Havi bizonylatok
 * ✅ DE (Deutsch) + RO (Română) verzió - HELYES NYELVEK
 * ✅ Lokalizált hónapnevek (német/román)
 * ✅ Auto nyelvválasztás (store country alapján)
 * ✅ HTML mentés → böngésző PDF-ként letölti
 * ✅ NINCS ÁFA - Interne Vergütung / Operațiune internă
 * ✅ Free Product támogatás (action_type, free_product, free_product_value)
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

        error_log("📄 [PPV_EXPENSE_RECEIPT] Bizonylat generálása: redeem_id={$redeem_id}");

        // 1️⃣ Beváltás adatainak lekérése (+ free_product támogatás!)
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
                rw.action_type,
                rw.action_value,
                rw.free_product,
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
            error_log("❌ [PPV_EXPENSE_RECEIPT] Redeem nem található: {$redeem_id}");
            return false;
        }

        // 2️⃣ Nyelvválasztás
        $lang = strtoupper($redeem['country'] ?? 'DE');
        if (!in_array($lang, ['DE', 'RO'])) {
            $lang = 'DE';
        }

        error_log("🌍 [PPV_EXPENSE_RECEIPT] Língua: {$lang}");

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
                error_log("❌ [PPV_EXPENSE_RECEIPT] DB update hiba: " . $wpdb->last_error);
                return false;
            }

            error_log("✅ [PPV_EXPENSE_RECEIPT] Bizonylat sikeres: {$redeem_id} → {$path}");
            return $path;
        }

        error_log("❌ [PPV_EXPENSE_RECEIPT] Fájl mentés sikertelen: {$redeem_id}");
        return false;
    }

    /**
     * ✅ HAVI BIZONYLAT GENERÁLÁS - JAVÍTOTT VERZIÓ
     * Működik include error nélkül!
     */
    public static function generate_monthly_receipt($store_id, $year, $month)
    {
        global $wpdb;

        error_log("📅 [PPV_EXPENSE_RECEIPT] generate_monthly_receipt() called: store={$store_id}, year={$year}, month={$month}");

        // 1️⃣ Store adatok lekérése
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name, address, plz, city, country, tax_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d LIMIT 1
        ", $store_id), ARRAY_A);

        if (!$store) {
            error_log("❌ [PPV_EXPENSE_RECEIPT] Store nem található: {$store_id}");
            return false;
        }

        error_log("✅ [PPV_EXPENSE_RECEIPT] Store megtalálva: " . $store['company_name']);

        // 2️⃣ Beváltások lekérése a hónapra (+ free_product támogatás!)
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
                rw.action_type,
                rw.free_product,
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

        if (!$items || count($items) === 0) {
            error_log("⚠️ [PPV_EXPENSE_RECEIPT] Nincsenek beváltások: year={$year}, month={$month}");
            return false;
        }

        $count = count($items);
        error_log("✅ [PPV_EXPENSE_RECEIPT] {$count} beváltás találva");

        // 3️⃣ Nyelvválasztás a store country alapján
        $lang = strtoupper($store['country'] ?? 'DE');
        if (!in_array($lang, ['DE', 'RO'])) {
            $lang = 'DE';
        }

        error_log("🌍 [PPV_EXPENSE_RECEIPT] Jezik: {$lang}");

        // 4️⃣ HTML generálás (az existing generate_html_for_monthly() függvénnyel)
        $html = self::generate_html_for_monthly($store, $items, $year, $month, $lang);

        if (!$html) {
            error_log("❌ [PPV_EXPENSE_RECEIPT] HTML generálás sikertelen");
            return false;
        }

        error_log("✅ [PPV_EXPENSE_RECEIPT] HTML generálva (" . strlen($html) . " bytes)");

        // 5️⃣ Könyvtár létrehozása
        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/ppv_receipts/';

        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                error_log("❌ [PPV_EXPENSE_RECEIPT] Mappa létrehozás sikertelen: {$dir}");
                return false;
            }
        }

        error_log("✅ [PPV_EXPENSE_RECEIPT] Könyvtár OK: {$dir}");

        // 6️⃣ Fájlnév és útvonal
        $filename = sprintf("monthly-receipt-%d-%04d%02d.html", $store_id, $year, $month);
        $filepath = $dir . $filename;

        // 7️⃣ HTML mentése fájlként
        $bytes = file_put_contents($filepath, $html);

        if ($bytes === false) {
            error_log("❌ [PPV_EXPENSE_RECEIPT] Fájl írás sikertelen: {$filepath}");
            return false;
        }

        error_log("✅ [PPV_EXPENSE_RECEIPT] Havi bizonylat mentve: {$filename} ({$bytes} bytes)");

        // ✅ Relatív útvonal visszaadása
        return 'ppv_receipts/' . $filename;
    }

    /**
     * 🎨 HTML generálás EGYSZERI bizonylathoz
     */
    private static function generate_html_for_redeem($redeem, $lang) {
        $customer_name = trim(($redeem['first_name'] ?? '') . ' ' . ($redeem['last_name'] ?? ''));
        if (!$customer_name) {
            $customer_name = $redeem['user_email'] ?? ($lang === 'RO' ? 'Necunoscut' : 'Unbekannt');
        }

        // ✅ HELYES DÁTUM FORMÁZÁS
        $receipt_num = date('Y-m-', strtotime($redeem['redeemed_at'])) . sprintf('%04d', $redeem['id']);

        // ✅ FREE PRODUCT TÁMOGATÁS - használja a free_product_value-t ha van!
        $action_type = $redeem['action_type'] ?? 'discount';
        if ($action_type === 'free_product' && floatval($redeem['free_product_value'] ?? 0) > 0) {
            $amount = floatval($redeem['free_product_value']);
        } else {
            $amount = floatval($redeem['actual_amount'] ?? $redeem['points_spent'] ?? 0);
        }

        if ($lang === 'RO') {
            return self::html_receipt_ro($redeem, $customer_name, $receipt_num, $amount);
        } else {
            return self::html_receipt_de($redeem, $customer_name, $receipt_num, $amount);
        }
    }

    /**
     * 🎨 HTML - Német verzió (DE) - MODERN DESIGN
     */
    private static function html_receipt_de($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Unternehmen');
        $address = htmlspecialchars($redeem['address'] ?? '');
        $plz = htmlspecialchars($redeem['plz'] ?? '');
        $city = htmlspecialchars($redeem['city'] ?? '');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '');
        $tax_id_html = $tax_id ? "<div class=\"info-row\"><span class=\"label\">Steuernummer:</span><span class=\"value\">{$tax_id}</span></div>" : '';

        $customer = htmlspecialchars($customer_name);
        $email = htmlspecialchars($redeem['user_email'] ?? '');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Belohnung');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('d.m.Y H:i', strtotime($redeem['redeemed_at']));
        $amount_formatted = number_format($amount, 2, ',', '.');

        // ✅ FREE PRODUCT TÁMOGATÁS
        $action_type = $redeem['action_type'] ?? 'discount';
        $free_product = htmlspecialchars($redeem['free_product'] ?? '');
        $is_free_product = ($action_type === 'free_product' && !empty($free_product));

        // Free product row HTML (csak ha van)
        $free_product_row = $is_free_product
            ? "<div class=\"info-row\"><span class=\"label\">Gratis Produkt:</span><span class=\"value\" style=\"color:#667eea;font-weight:600;\">🎁 {$free_product}</span></div>"
            : '';

        // Beschreibung típus alapján
        $description_text = $is_free_product ? 'Gratis Produkt – Punkteeinlösung' : 'Kundenrabatt – Punkteeinlösung';

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ausgabebeleg - {$receipt_num}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .receipt {
            max-width: 650px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content { padding: 30px; }

        .company-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .company-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #666; }
        .info-row .value { font-weight: 500; color: #333; }

        .meta-bar {
            display: flex;
            justify-content: space-between;
            background: #f0f4f8;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .meta-item {
            text-align: center;
        }

        .meta-item .meta-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-item .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-card {
            background: #fafbfc;
            border-radius: 10px;
            padding: 16px;
            border-left: 4px solid #667eea;
        }

        .amount-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            color: white;
            margin: 24px 0;
        }

        .amount-box .amount-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .amount-box .amount-value {
            font-size: 36px;
            font-weight: 700;
            margin-top: 8px;
        }

        .notice-box {
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .notice-box.warning {
            background: #fff8e6;
            border: 1px solid #ffd666;
        }

        .notice-box.info {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
        }

        .notice-box strong {
            display: block;
            margin-bottom: 8px;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }

        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>AUSGABEBELEG</h1>
            <p>Kundenbindungsprogramm – Punkteeinlösung</p>
        </div>

        <div class="content">
            <div class="company-card">
                <h3>{$company}</h3>
                <div class="info-row">
                    <span class="label">Adresse:</span>
                    <span class="value">{$address}, {$plz} {$city}</span>
                </div>
                {$tax_id_html}
            </div>

            <div class="meta-bar">
                <div class="meta-item">
                    <div class="meta-label">Datum</div>
                    <div class="meta-value">{$date}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Belegnummer</div>
                    <div class="meta-value">{$receipt_num}</div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Beschreibung</div>
                <div class="section-card">
                    <div class="info-row">
                        <span class="label">Verwendungszweck:</span>
                        <span class="value">{$description_text}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Programm:</span>
                        <span class="value">PunktePass Loyalty</span>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Kundeninformationen</div>
                <div class="section-card">
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span class="value">{$customer}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">E-Mail:</span>
                        <span class="value">{$email}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Eingelöste Punkte:</span>
                        <span class="value">{$points} Punkte</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Belohnung:</span>
                        <span class="value">{$reward}</span>
                    </div>
                    {$free_product_row}
                </div>
            </div>

            <div class="amount-box">
                <div class="amount-label">Auszahlungsbetrag</div>
                <div class="amount-value">{$amount_formatted} €</div>
            </div>

            <div class="notice-box warning">
                <strong>Buchungsvorschlag:</strong>
                Soll: 4930 (Marketing / Kundenbindung) → Haben: 1000 (Kasse)
            </div>

            <div class="notice-box info">
                <strong>Hinweis zur Umsatzsteuer:</strong>
                Keine Umsatzsteuer – Interne Vergütung, nicht steuerbar gemäß § 1 UStG
            </div>
        </div>

        <div class="footer">
            <p>Automatisch generiert von PunktePass | info@punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Román verzió (RO) - MODERN DESIGN
     */
    private static function html_receipt_ro($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Societate');
        $address = htmlspecialchars($redeem['address'] ?? '');
        $plz = htmlspecialchars($redeem['plz'] ?? '');
        $city = htmlspecialchars($redeem['city'] ?? '');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '');
        $tax_id_html = $tax_id ? "<div class=\"info-row\"><span class=\"label\">CUI / CIF:</span><span class=\"value\">{$tax_id}</span></div>" : '';

        $customer = htmlspecialchars($customer_name);
        $email = htmlspecialchars($redeem['user_email'] ?? '');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Recompensă');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('d.m.Y H:i', strtotime($redeem['redeemed_at']));
        $amount_formatted = number_format($amount, 2, ',', '.');

        // ✅ FREE PRODUCT TÁMOGATÁS
        $action_type = $redeem['action_type'] ?? 'discount';
        $free_product = htmlspecialchars($redeem['free_product'] ?? '');
        $is_free_product = ($action_type === 'free_product' && !empty($free_product));

        // Free product row HTML (csak ha van)
        $free_product_row = $is_free_product
            ? "<div class=\"info-row\"><span class=\"label\">Produs gratuit:</span><span class=\"value\" style=\"color:#667eea;font-weight:600;\">🎁 {$free_product}</span></div>"
            : '';

        // Descriere típus alapján
        $description_text = $is_free_product ? 'Produs gratuit – Valorificare puncte' : 'Rambursare puncte fidelitate';

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispoziție de Plată - {$receipt_num}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .receipt {
            max-width: 650px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content { padding: 30px; }

        .company-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .company-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #666; }
        .info-row .value { font-weight: 500; color: #333; }

        .meta-bar {
            display: flex;
            justify-content: space-between;
            background: #f0f4f8;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .meta-item {
            text-align: center;
        }

        .meta-item .meta-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-item .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-card {
            background: #fafbfc;
            border-radius: 10px;
            padding: 16px;
            border-left: 4px solid #667eea;
        }

        .amount-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            color: white;
            margin: 24px 0;
        }

        .amount-box .amount-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .amount-box .amount-value {
            font-size: 36px;
            font-weight: 700;
            margin-top: 8px;
        }

        .notice-box {
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .notice-box.warning {
            background: #fff8e6;
            border: 1px solid #ffd666;
        }

        .notice-box.info {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
        }

        .notice-box strong {
            display: block;
            margin-bottom: 8px;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }

        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>DISPOZIȚIE DE PLATĂ</h1>
            <p>Program de fidelizare – Valorificare puncte</p>
        </div>

        <div class="content">
            <div class="company-card">
                <h3>{$company}</h3>
                <div class="info-row">
                    <span class="label">Adresă:</span>
                    <span class="value">{$address}, {$plz} {$city}</span>
                </div>
                {$tax_id_html}
            </div>

            <div class="meta-bar">
                <div class="meta-item">
                    <div class="meta-label">Data</div>
                    <div class="meta-value">{$date}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Nr. Dispoziție</div>
                    <div class="meta-value">{$receipt_num}</div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Descriere</div>
                <div class="section-card">
                    <div class="info-row">
                        <span class="label">Scop:</span>
                        <span class="value">{$description_text}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Program:</span>
                        <span class="value">PunktePass Loyalty</span>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Informații Client</div>
                <div class="section-card">
                    <div class="info-row">
                        <span class="label">Nume:</span>
                        <span class="value">{$customer}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">E-Mail:</span>
                        <span class="value">{$email}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Puncte valorificate:</span>
                        <span class="value">{$points} puncte</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Recompensă:</span>
                        <span class="value">{$reward}</span>
                    </div>
                    {$free_product_row}
                </div>
            </div>

            <div class="amount-box">
                <div class="amount-label">Suma de Plată</div>
                <div class="amount-value">{$amount_formatted} RON</div>
            </div>

            <div class="notice-box warning">
                <strong>Sugestie înregistrare contabilă:</strong>
                Debit: 623 (Cheltuieli marketing) → Credit: 5311 (Casa în lei)
            </div>

            <div class="notice-box info">
                <strong>Informații TVA:</strong>
                Fără TVA – Operațiune internă, neimpozabilă conform Codului Fiscal
            </div>
        </div>

        <div class="footer">
            <p>Document generat automat de PunktePass | info@punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML generálás HAVI bizonylathoz
     */
    private static function generate_html_for_monthly($store, $redeems, $year, $month, $lang) {
        $total_amount = 0;
        $total_points = 0;

        foreach ($redeems as $r) {
            // ✅ FREE PRODUCT TÁMOGATÁS - használja a free_product_value-t ha van!
            $action_type = $r->action_type ?? 'discount';
            if ($action_type === 'free_product' && floatval($r->free_product_value ?? 0) > 0) {
                $total_amount += floatval($r->free_product_value);
            } else {
                $total_amount += floatval($r->actual_amount ?? $r->points_spent ?? 0);
            }
            $total_points += intval($r->points_spent ?? 0);
        }

        if ($lang === 'RO') {
            return self::html_monthly_receipt_ro($store, $redeems, $year, $month, $total_amount, $total_points);
        } else {
            return self::html_monthly_receipt_de($store, $redeems, $year, $month, $total_amount, $total_points);
        }
    }

    /**
     * 🎨 HTML - Havi bizonylat NÉMET verzió - MODERN DESIGN
     */
    private static function html_monthly_receipt_de($store, $redeems, $year, $month, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Unternehmen');
        $address = htmlspecialchars($store['address'] ?? '');
        $plz = htmlspecialchars($store['plz'] ?? '');
        $city = htmlspecialchars($store['city'] ?? '');

        // German month names
        $months_de = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $month_str = $months_de[$month] . ' ' . $year;

        $count = count($redeems);
        $total_formatted = number_format($total_amount, 2, ',', '.');

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')));
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Unbekannt');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Belohnung');
            $points = intval($r->points_spent ?? 0);

            // ✅ FREE PRODUCT TÁMOGATÁS
            $action_type = $r->action_type ?? 'discount';
            $free_product = $r->free_product ?? '';
            if ($action_type === 'free_product' && floatval($r->free_product_value ?? 0) > 0) {
                $amount = number_format(floatval($r->free_product_value), 2, ',', '.');
                // Free product neve hozzáadva a reward-hoz
                if (!empty($free_product)) {
                    $reward .= ' <span style="color:#667eea;">(🎁 ' . htmlspecialchars($free_product) . ')</span>';
                }
            } else {
                $amount = number_format(floatval($r->actual_amount ?? $r->points_spent ?? 0), 2, ',', '.');
            }

            $date = date('d.m.Y', strtotime($r->redeemed_at));

            $rows .= "<tr>
                <td>{$date}</td>
                <td>{$customer}</td>
                <td>{$reward}</td>
                <td class=\"text-right\">{$points}</td>
                <td class=\"text-right\">{$amount} €</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monatlicher Ausgabebeleg - {$month_str}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .receipt {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }

        .header h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .header p { font-size: 14px; opacity: 0.9; }

        .content { padding: 30px; }

        .info-bar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .info-item { }
        .info-item .label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-item .value { font-size: 14px; font-weight: 600; color: #333; margin-top: 4px; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
            color: #333;
        }

        tr:hover { background: #f9fafb; }

        .text-right { text-align: right; }

        .total-row {
            background: #f0f4f8 !important;
        }

        .total-row td {
            font-weight: 700;
            font-size: 14px;
            border-bottom: none;
            padding: 16px 12px;
        }

        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            color: white;
            text-align: center;
            margin: 24px 0;
        }

        .summary-box .label { font-size: 12px; opacity: 0.9; text-transform: uppercase; }
        .summary-box .value { font-size: 36px; font-weight: 700; margin-top: 8px; }

        .notice-box {
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .notice-box.warning { background: #fff8e6; border: 1px solid #ffd666; }
        .notice-box.info { background: #e6f7ff; border: 1px solid #91d5ff; }
        .notice-box strong { display: block; margin-bottom: 8px; }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }

        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>MONATLICHER AUSGABEBELEG</h1>
            <p>Sammelabrechnung – PunktePass Kundenbindungsprogramm</p>
        </div>

        <div class="content">
            <div class="info-bar">
                <div class="info-item">
                    <div class="label">Unternehmen</div>
                    <div class="value">{$company}</div>
                </div>
                <div class="info-item">
                    <div class="label">Adresse</div>
                    <div class="value">{$address}, {$plz} {$city}</div>
                </div>
                <div class="info-item">
                    <div class="label">Abrechnungszeitraum</div>
                    <div class="value">{$month_str}</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Kunde</th>
                        <th>Belohnung</th>
                        <th class="text-right">Punkte</th>
                        <th class="text-right">Betrag</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Gesamt ({$count} Einlösungen):</td>
                        <td class="text-right">{$total_points}</td>
                        <td class="text-right">{$total_formatted} €</td>
                    </tr>
                </tbody>
            </table>

            <div class="summary-box">
                <div class="label">Gesamtbetrag</div>
                <div class="value">{$total_formatted} €</div>
            </div>

            <div class="notice-box warning">
                <strong>Buchungsvorschlag:</strong>
                Soll: 4930 (Marketing / Kundenbindung) → Haben: 1000 (Kasse)
            </div>

            <div class="notice-box info">
                <strong>Hinweis zur Umsatzsteuer:</strong>
                Keine Umsatzsteuer – Interne Vergütung, nicht steuerbar gemäß § 1 UStG
            </div>
        </div>

        <div class="footer">
            <p>Automatisch generiert von PunktePass | info@punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Havi bizonylat ROMÁN verzió - MODERN DESIGN
     */
    private static function html_monthly_receipt_ro($store, $redeems, $year, $month, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Societate');
        $address = htmlspecialchars($store['address'] ?? '');
        $plz = htmlspecialchars($store['plz'] ?? '');
        $city = htmlspecialchars($store['city'] ?? '');

        // Romanian month names
        $months_ro = ['', 'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
        $month_str = $months_ro[$month] . ' ' . $year;

        $count = count($redeems);
        $total_formatted = number_format($total_amount, 2, ',', '.');

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')));
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Necunoscut');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Recompensă');
            $points = intval($r->points_spent ?? 0);

            // ✅ FREE PRODUCT TÁMOGATÁS
            $action_type = $r->action_type ?? 'discount';
            $free_product = $r->free_product ?? '';
            if ($action_type === 'free_product' && floatval($r->free_product_value ?? 0) > 0) {
                $amount = number_format(floatval($r->free_product_value), 2, ',', '.');
                // Free product neve hozzáadva a reward-hoz
                if (!empty($free_product)) {
                    $reward .= ' <span style="color:#667eea;">(🎁 ' . htmlspecialchars($free_product) . ')</span>';
                }
            } else {
                $amount = number_format(floatval($r->actual_amount ?? $r->points_spent ?? 0), 2, ',', '.');
            }

            $date = date('d.m.Y', strtotime($r->redeemed_at));

            $rows .= "<tr>
                <td>{$date}</td>
                <td>{$customer}</td>
                <td>{$reward}</td>
                <td class=\"text-right\">{$points}</td>
                <td class=\"text-right\">{$amount} RON</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispoziție Lunară de Plată - {$month_str}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .receipt {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }

        .header h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .header p { font-size: 14px; opacity: 0.9; }

        .content { padding: 30px; }

        .info-bar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .info-item { }
        .info-item .label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-item .value { font-size: 14px; font-weight: 600; color: #333; margin-top: 4px; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
            color: #333;
        }

        tr:hover { background: #f9fafb; }

        .text-right { text-align: right; }

        .total-row {
            background: #f0f4f8 !important;
        }

        .total-row td {
            font-weight: 700;
            font-size: 14px;
            border-bottom: none;
            padding: 16px 12px;
        }

        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            color: white;
            text-align: center;
            margin: 24px 0;
        }

        .summary-box .label { font-size: 12px; opacity: 0.9; text-transform: uppercase; }
        .summary-box .value { font-size: 36px; font-weight: 700; margin-top: 8px; }

        .notice-box {
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .notice-box.warning { background: #fff8e6; border: 1px solid #ffd666; }
        .notice-box.info { background: #e6f7ff; border: 1px solid #91d5ff; }
        .notice-box strong { display: block; margin-bottom: 8px; }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }

        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>DISPOZIȚIE LUNARĂ DE PLATĂ</h1>
            <p>Situație centralizatoare – Program de fidelizare PunktePass</p>
        </div>

        <div class="content">
            <div class="info-bar">
                <div class="info-item">
                    <div class="label">Societate</div>
                    <div class="value">{$company}</div>
                </div>
                <div class="info-item">
                    <div class="label">Adresă</div>
                    <div class="value">{$address}, {$plz} {$city}</div>
                </div>
                <div class="info-item">
                    <div class="label">Perioada de raportare</div>
                    <div class="value">{$month_str}</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Client</th>
                        <th>Recompensă</th>
                        <th class="text-right">Puncte</th>
                        <th class="text-right">Suma</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Total ({$count} valorificări):</td>
                        <td class="text-right">{$total_points}</td>
                        <td class="text-right">{$total_formatted} RON</td>
                    </tr>
                </tbody>
            </table>

            <div class="summary-box">
                <div class="label">Sumă Totală</div>
                <div class="value">{$total_formatted} RON</div>
            </div>

            <div class="notice-box warning">
                <strong>Sugestie înregistrare contabilă:</strong>
                Debit: 623 (Cheltuieli marketing) → Credit: 5311 (Casa în lei)
            </div>

            <div class="notice-box info">
                <strong>Informații TVA:</strong>
                Fără TVA – Operațiune internă, neimpozabilă conform Codului Fiscal
            </div>
        </div>

        <div class="footer">
            <p>Document generat automat de PunktePass | info@punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 💾 FÁJL MENTÉS - EGYSZERŰSÍTVE
     * HTML-t PDF-ként menti el
     */
    private static function save_receipt_file($html, $filename) {
        $upload_dir = wp_upload_dir();
        $receipts_dir = $upload_dir['basedir'] . '/' . self::RECEIPTS_DIR;

        // Mappa létrehozása
        if (!is_dir($receipts_dir)) {
            if (!wp_mkdir_p($receipts_dir)) {
                error_log("❌ [PPV_EXPENSE_RECEIPT] Mappa létrehozás sikertelen: {$receipts_dir}");
                return false;
            }
        }

        $filepath = $receipts_dir . '/' . $filename;

        // ✅ HTML-t egyszerűen fájlként mentjük
        $bytes = file_put_contents($filepath, $html);

        if ($bytes === false) {
            error_log("❌ [PPV_EXPENSE_RECEIPT] Fájl írás sikertelen: {$filepath}");
            return false;
        }

        error_log("✅ [PPV_EXPENSE_RECEIPT] Fájl mentve: {$filename} ({$bytes} bytes)");

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
}