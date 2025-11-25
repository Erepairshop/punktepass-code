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
        if (!in_array($lang, ['DE', 'RO'])) {
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
    public static function generate_monthly_receipt($store_id, $year, $month)
    {
        global $wpdb;

        ppv_log("📅 [PPV_EXPENSE_RECEIPT] generate_monthly_receipt() called: store={$store_id}, year={$year}, month={$month}");

        // 1️⃣ Store adatok lekérése
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name, address, plz, city, country, tax_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d LIMIT 1
        ", $store_id), ARRAY_A);

        if (!$store) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Store nem található: {$store_id}");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Store megtalálva: " . $store['company_name']);

        // 2️⃣ Beváltások lekérése a hónapra
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

        if (!$items || count($items) === 0) {
            ppv_log("⚠️ [PPV_EXPENSE_RECEIPT] Nincsenek beváltások: year={$year}, month={$month}");
            return false;
        }

        $count = count($items);
        ppv_log("✅ [PPV_EXPENSE_RECEIPT] {$count} beváltás találva");

        // 3️⃣ Nyelvválasztás a store country alapján
        $lang = strtoupper($store['country'] ?? 'DE');
        if (!in_array($lang, ['DE', 'RO'])) {
            $lang = 'DE';
        }

        ppv_log("🌍 [PPV_EXPENSE_RECEIPT] Jezik: {$lang}");

        // 4️⃣ HTML generálás (az existing generate_html_for_monthly() függvénnyel)
        $html = self::generate_html_for_monthly($store, $items, $year, $month, $lang);

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
        $filename = sprintf("monthly-receipt-%d-%04d%02d.html", $store_id, $year, $month);
        $filepath = $dir . $filename;

        // 7️⃣ HTML mentése fájlként
        $bytes = file_put_contents($filepath, $html);

        if ($bytes === false) {
            ppv_log("❌ [PPV_EXPENSE_RECEIPT] Fájl írás sikertelen: {$filepath}");
            return false;
        }

        ppv_log("✅ [PPV_EXPENSE_RECEIPT] Havi bizonylat mentve: {$filename} ({$bytes} bytes)");

        // ✅ Relatív útvonal visszaadása
        return 'ppv_receipts/' . $filename;
    }

    /**
     * 🎨 HTML generálás EGYSZERI bizonylathoz
     */
    private static function generate_html_for_redeem($redeem, $lang) {
        $customer_name = trim(($redeem['first_name'] ?? '') . ' ' . ($redeem['last_name'] ?? ''));
        if (!$customer_name) {
            $customer_name = $redeem['user_email'] ?? 'Unbekannt';
        }

        // ✅ HELYES DÁTUM FORMÁZÁS
        $receipt_num = date('Y-m-', strtotime($redeem['redeemed_at'])) . sprintf('%04d', $redeem['id']);

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
        } else {
            return self::html_receipt_de($redeem, $customer_name, $receipt_num, $amount);
        }
    }

    /**
     * 🎨 HTML - Német verzió (DE)
     */
    private static function html_receipt_de($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Bolt');
        $address = htmlspecialchars($redeem['address'] ?? '');
        $plz = htmlspecialchars($redeem['plz'] ?? '');
        $city = htmlspecialchars($redeem['city'] ?? '');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '');
        $tax_id_html = $tax_id ? "<p><strong>Steuernummer:</strong> {$tax_id}</p>" : '';
        
        $customer = htmlspecialchars($customer_name);
        $email = htmlspecialchars($redeem['user_email'] ?? '');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Belohnung');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('d.m.Y H:i', strtotime($redeem['redeemed_at']));

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ausgabebeleg</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; padding: 20px; background: white; }
        .receipt { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ddd; }
        .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .header p { font-size: 12px; color: #666; }
        .company-info { margin-bottom: 20px; }
        .company-info p { margin: 3px 0; font-size: 13px; }
        .receipt-num { background: #f0f0f0; padding: 10px; margin: 15px 0; font-size: 12px; }
        .section { margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 3px solid #007bff; }
        .section-title { font-weight: bold; font-size: 12px; margin-bottom: 8px; color: #333; }
        .line { display: flex; justify-content: space-between; font-size: 12px; margin: 4px 0; }
        .amount-section { background: white; border: 2px solid #333; padding: 15px; margin: 20px 0; text-align: center; }
        .amount-section .label { font-size: 12px; color: #666; }
        .amount-section .value { font-size: 24px; font-weight: bold; margin: 5px 0; }
        .booking { background: #fff3cd; padding: 12px; margin: 15px 0; font-size: 11px; }
        .disclaimer { background: #e8f4f8; padding: 10px; margin: 15px 0; font-size: 11px; }
        .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>KIADÁSI BIZONYLAT</h1>
            <p>AUSGABEBELEG – Loyalty Program Auszahlung</p>
        </div>

        <div class="company-info">
            <p><strong>Betrieb:</strong> {$company}</p>
            <p><strong>Adresse:</strong> {$address}, {$plz} {$city}</p>
            {$tax_id_html}
        </div>

        <div class="receipt-num">
            <div class="line"><span><strong>Datum:</strong></span><span>{$date}</span></div>
            <div class="line"><span><strong>Belegnummer:</strong></span><span>{$receipt_num}</span></div>
        </div>

        <div class="section">
            <div class="section-title">📋 BESCHREIBUNG:</div>
            <p style="margin: 5px 0; font-size: 12px;">Kundenrabatt - Punkteeinlösung</p>
            <p style="margin: 5px 0; font-size: 11px; color: #666;">PunktePass Loyalty Programm</p>
        </div>

        <div class="section">
            <div class="section-title">👤 KUNDE:</div>
            <div class="line"><span>Name:</span><span>{$customer}</span></div>
            <div class="line"><span>E-Mail:</span><span>{$email}</span></div>
            <div class="line"><span>Punkte:</span><span>{$points} Punkte</span></div>
            <div class="line"><span>Belohnung:</span><span>{$reward}</span></div>
        </div>

        <div class="amount-section">
            <div class="label">BETRAG (Kundenbindung)</div>
            <div class="value">{$amount} EUR</div>
        </div>

        <div class="booking">
            <strong>📊 BUCHUNGSVORSCHLAG:</strong><br>
            Soll: 4930 (Marketing / Kundenbindung)<br>
            Haben: 1000 (Kasse)
        </div>

        <div class="disclaimer">
            <strong>⚠️ WICHTIG:</strong><br>
            KEINE UMSATZSTEUER<br>
            Interne Vergütung – Nicht steuerbar
        </div>

        <div class="footer">
            <p>Dieses Dokument wurde automatisch von PunktePass generiert.</p>
            <p>Für Rückfragen: info@punktepass.de</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Román verzió (RO)
     */
    private static function html_receipt_ro($redeem, $customer_name, $receipt_num, $amount) {
        $company = htmlspecialchars($redeem['company_name'] ?? 'Societate');
        $address = htmlspecialchars($redeem['address'] ?? '');
        $plz = htmlspecialchars($redeem['plz'] ?? '');
        $city = htmlspecialchars($redeem['city'] ?? '');
        $tax_id = htmlspecialchars($redeem['tax_id'] ?? '');
        $tax_id_html = $tax_id ? "<p><strong>Cod fiscal:</strong> {$tax_id}</p>" : '';
        
        $customer = htmlspecialchars($customer_name);
        $email = htmlspecialchars($redeem['user_email'] ?? '');
        $reward = htmlspecialchars($redeem['reward_title'] ?? 'Recompensă');
        $points = intval($redeem['points_spent'] ?? 0);
        $date = date('d.m.Y H:i', strtotime($redeem['redeemed_at']));

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Dispoziție de Cheltuieli</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; padding: 20px; background: white; }
        .receipt { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ddd; }
        .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .company-info { margin-bottom: 20px; }
        .company-info p { margin: 3px 0; font-size: 13px; }
        .receipt-num { background: #f0f0f0; padding: 10px; margin: 15px 0; font-size: 12px; }
        .section { margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 3px solid #007bff; }
        .section-title { font-weight: bold; font-size: 12px; margin-bottom: 8px; color: #333; }
        .line { display: flex; justify-content: space-between; font-size: 12px; margin: 4px 0; }
        .amount-section { background: white; border: 2px solid #333; padding: 15px; margin: 20px 0; text-align: center; }
        .amount-section .value { font-size: 24px; font-weight: bold; margin: 5px 0; }
        .booking { background: #fff3cd; padding: 12px; margin: 15px 0; font-size: 11px; }
        .disclaimer { background: #e8f4f8; padding: 10px; margin: 15px 0; font-size: 11px; }
        .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>DISPOZIȚIE DE CHELTUIELI</h1>
            <p>Bon de consum intern – Program de fidelizare</p>
        </div>

        <div class="company-info">
            <p><strong>Societate:</strong> {$company}</p>
            <p><strong>Adresă:</strong> {$address}, {$plz} {$city}</p>
            {$tax_id_html}
        </div>

        <div class="receipt-num">
            <div class="line"><span><strong>Data:</strong></span><span>{$date}</span></div>
            <div class="line"><span><strong>Nr. Dispoziție:</strong></span><span>{$receipt_num}</span></div>
        </div>

        <div class="section">
            <div class="section-title">📋 DESCRIERE:</div>
            <p style="margin: 5px 0; font-size: 12px;">Rambursare puncte program fidelizare</p>
            <p style="margin: 5px 0; font-size: 11px; color: #666;">Program PunktePass</p>
        </div>

        <div class="section">
            <div class="section-title">👤 CLIENT BENEFICIAR:</div>
            <div class="line"><span>Nume:</span><span>{$customer}</span></div>
            <div class="line"><span>E-Mail:</span><span>{$email}</span></div>
            <div class="line"><span>Puncte folosite:</span><span>{$points} Puncte</span></div>
            <div class="line"><span>Recompensă:</span><span>{$reward}</span></div>
        </div>

        <div class="amount-section">
            <div class="label">SUMA TOTALĂ</div>
            <div class="value">{$amount} RON</div>
        </div>

        <div class="booking">
            <strong>📊 ÎNREGISTRARE CONTABILĂ SUGERATĂ:</strong><br>
            Debit: 623 (Cheltuieli marketing)<br>
            Credit: 5311 (Casa în lei)
        </div>

        <div class="disclaimer">
            <strong>⚠️ IMPORTANT:</strong><br>
            FĂRĂ TVA<br>
            Operațiune internă, neimpozabilă
        </div>

        <div class="footer">
            <p>Acest document a fost generat automat de PunktePass.</p>
            <p>Pentru întrebări: info@punktepass.de</p>
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
            $total_amount += self::calculate_item_amount($r);
            $total_points += intval($r->points_spent ?? 0);
        }

        if ($lang === 'RO') {
            return self::html_monthly_receipt_ro($store, $redeems, $year, $month, $total_amount, $total_points);
        } else {
            return self::html_monthly_receipt_de($store, $redeems, $year, $month, $total_amount, $total_points);
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
     * 🎨 HTML - Havi bizonylat NÉMET verzió
     */
    private static function html_monthly_receipt_de($store, $redeems, $year, $month, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Bolt');
        $address = htmlspecialchars($store['address'] ?? '');
        $plz = htmlspecialchars($store['plz'] ?? '');
        $city = htmlspecialchars($store['city'] ?? '');
        $month_str = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $count = count($redeems);

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')));
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Unbekannt');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Belohnung');
            $points = intval($r->points_spent ?? 0);
            $amount = self::calculate_item_amount($r);
            $date = date('d.m.Y', strtotime($r->redeemed_at));

            $rows .= "<tr style=\"border-bottom: 1px solid #ddd;\">
                <td style=\"padding: 8px; font-size: 11px;\">{$date}</td>
                <td style=\"padding: 8px; font-size: 11px;\">{$customer}</td>
                <td style=\"padding: 8px; font-size: 11px;\">{$reward}</td>
                <td style=\"padding: 8px; font-size: 11px; text-align: right;\">{$points}</td>
                <td style=\"padding: 8px; font-size: 11px; text-align: right;\">{$amount} EUR</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Monatlicher Ausgabebeleg</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; padding: 20px; background: white; }
        .receipt { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ddd; }
        .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .company-info { margin-bottom: 20px; }
        .company-info p { margin: 3px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #007bff; color: white; padding: 10px; text-align: left; font-size: 12px; font-weight: bold; }
        .total-row { background: #f9f9f9; font-weight: bold; }
        .booking { background: #fff3cd; padding: 12px; margin: 15px 0; font-size: 11px; }
        .disclaimer { background: #e8f4f8; padding: 10px; margin: 15px 0; font-size: 11px; }
        .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>MONATLICHER AUSGABEBELEG</h1>
            <p>Sammelabrechnung – PunktePass Loyalty Program</p>
        </div>

        <div class="company-info">
            <p><strong>Betrieb:</strong> {$company}</p>
            <p><strong>Adresse:</strong> {$address}, {$plz} {$city}</p>
            <p><strong>Zeitraum:</strong> {$month_str}</p>
        </div>

        <table>
            <thead><tr><th>Datum</th><th>Kunde</th><th>Belohnung</th><th style="text-align: right;">Punkte</th><th style="text-align: right;">Betrag</th></tr></thead>
            <tbody>
                {$rows}
                <tr class="total-row">
                    <td colspan="3" style="padding: 10px; text-align: right;">GESAMT ({$count} Einlösungen):</td>
                    <td style="padding: 10px; text-align: right;">{$total_points}</td>
                    <td style="padding: 10px; text-align: right;">{$total_amount} EUR</td>
                </tr>
            </tbody>
        </table>

        <div class="booking">
            <strong>📊 BUCHUNGSVORSCHLAG:</strong><br>
            Soll: 4930 (Marketing / Kundenbindung)<br>
            Haben: 1000 (Kasse)
        </div>

        <div class="disclaimer">
            <strong>⚠️ WICHTIG:</strong><br>
            KEINE UMSATZSTEUER<br>
            Interne Vergütung – Nicht steuerbar
        </div>

        <div class="footer">
            <p>Dieses Dokument wurde automatisch von PunktePass generiert.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 🎨 HTML - Havi bizonylat ROMÁN verzió
     */
    private static function html_monthly_receipt_ro($store, $redeems, $year, $month, $total_amount, $total_points) {
        $company = htmlspecialchars($store['company_name'] ?? 'Societate');
        $address = htmlspecialchars($store['address'] ?? '');
        $plz = htmlspecialchars($store['plz'] ?? '');
        $city = htmlspecialchars($store['city'] ?? '');
        $month_str = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $count = count($redeems);

        $rows = '';
        foreach ($redeems as $r) {
            $customer = htmlspecialchars(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')));
            if (!$customer) {
                $customer = htmlspecialchars($r->user_email ?? 'Necunoscut');
            }
            $reward = htmlspecialchars($r->reward_title ?? 'Recompensă');
            $points = intval($r->points_spent ?? 0);
            $amount = self::calculate_item_amount($r);
            $date = date('d.m.Y', strtotime($r->redeemed_at));

            $rows .= "<tr style=\"border-bottom: 1px solid #ddd;\">
                <td style=\"padding: 8px; font-size: 11px;\">{$date}</td>
                <td style=\"padding: 8px; font-size: 11px;\">{$customer}</td>
                <td style=\"padding: 8px; font-size: 11px;\">{$reward}</td>
                <td style=\"padding: 8px; font-size: 11px; text-align: right;\">{$points}</td>
                <td style=\"padding: 8px; font-size: 11px; text-align: right;\">{$amount} RON</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Dispoziție Lunară de Cheltuieli</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; padding: 20px; background: white; }
        .receipt { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #ddd; }
        .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .company-info { margin-bottom: 20px; }
        .company-info p { margin: 3px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #007bff; color: white; padding: 10px; text-align: left; font-size: 12px; font-weight: bold; }
        .total-row { background: #f9f9f9; font-weight: bold; }
        .booking { background: #fff3cd; padding: 12px; margin: 15px 0; font-size: 11px; }
        .disclaimer { background: #e8f4f8; padding: 10px; margin: 15px 0; font-size: 11px; }
        .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>DISPOZIȚIE LUNARĂ DE CHELTUIELI</h1>
            <p>Situație rezumativă – Program PunktePass</p>
        </div>

        <div class="company-info">
            <p><strong>Societate:</strong> {$company}</p>
            <p><strong>Adresă:</strong> {$address}, {$plz} {$city}</p>
            <p><strong>Perioada:</strong> {$month_str}</p>
        </div>

        <table>
            <thead><tr><th>Data</th><th>Client</th><th>Recompensă</th><th style="text-align: right;">Puncte</th><th style="text-align: right;">Suma</th></tr></thead>
            <tbody>
                {$rows}
                <tr class="total-row">
                    <td colspan="3" style="padding: 10px; text-align: right;">TOTAL ({$count} Rambursări):</td>
                    <td style="padding: 10px; text-align: right;">{$total_points}</td>
                    <td style="padding: 10px; text-align: right;">{$total_amount} RON</td>
                </tr>
            </tbody>
        </table>

        <div class="booking">
            <strong>📊 ÎNREGISTRARE CONTABILĂ SUGERATĂ:</strong><br>
            Debit: 623 (Cheltuieli marketing)<br>
            Credit: 5311 (Casa în lei)
        </div>

        <div class="disclaimer">
            <strong>⚠️ IMPORTANT:</strong><br>
            FĂRĂ TVA<br>
            Operațiune internă, neimpozabilă
        </div>

        <div class="footer">
            <p>Acest document a fost generat automat de PunktePass.</p>
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
            $options->set('defaultFont', 'Arial');
            $options->set('isPhpEnabled', false);

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
}