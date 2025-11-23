<?php
if (!defined('ABSPATH')) exit;

class PPV_QR_Generator {
    

    /** 🔹 QR generálás időbélyeges fájlnévvel + automatikus régi törléssel */
    public static function generate_qr($store) {
        ppv_log('🧩 PPV_QR_Generator::generate_qr() called for store_id=' . ($store->id ?? 'undefined'));

        $upload_dir = wp_upload_dir();
        $qr_dir = trailingslashit($upload_dir['basedir']) . 'punktepass_qr/';
        if (!file_exists($qr_dir)) wp_mkdir_p($qr_dir);

        // 🧹 Régi QR-ek törlése (csak az adott store-hoz tartozó fájlokat)
        foreach (glob($qr_dir . 'store_' . intval($store->id) . '_qr_*.png') as $old_file) {
            @unlink($old_file);
        }

        // 🕒 Egyedi fájlnév időbélyeggel
        $timestamp = date('Ymd_His');
        $file_path = $qr_dir . 'store_' . intval($store->id) . '_qr_' . $timestamp . '.png';

        // 🔗 QR adat
        $qr_data = PPV_QR::generate_secure_qr_url($store->id);



        // 🧩 Ha nincs design_color → marad fekete-fehér QR
        $design_color = !empty($store->design_color) ? $store->design_color : '';
        $design_logo  = !empty($store->design_logo) ? $store->design_logo : '';

        // ✅ QR generálás (fekete-fehér, átlátszó háttér)
        $qr_img_url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=0&color=000000&bgcolor=transparent&data=' . rawurlencode($qr_data);
        $response   = wp_remote_get($qr_img_url, ['timeout' => 15]);

        if (is_wp_error($response) || empty($response['body'])) {
            return self::fallback_qr($store, $qr_data);
        }

        file_put_contents($file_path, $response['body']);

        // 🎨 Csak akkor adunk háttérszínt, ha a design_color meg van adva
        if (!empty($design_color)) {
            self::add_colored_background($file_path, $design_color);
        }

        // 📸 Logó fehér háttérrel (ha van)
        if (!empty($design_logo) && filter_var($design_logo, FILTER_VALIDATE_URL)) {
            self::add_logo_with_white_box($file_path, $design_logo);
        }

        // ✅ Végső URL visszaadása
        return [
            'img' => trailingslashit($upload_dir['baseurl']) . 'punktepass_qr/store_' . intval($store->id) . '_qr_' . $timestamp . '.png'
        ];
    }

    /** 🔹 Alap fallback QR – ha az API hívás sikertelen */
    private static function fallback_qr($store, $data) {
        $upload_dir = wp_upload_dir();
        $qr_dir = trailingslashit($upload_dir['basedir']) . 'punktepass_qr/';
        if (!file_exists($qr_dir)) wp_mkdir_p($qr_dir);

        // ⏰ Egyedi név fallback esetén is
        $timestamp = date('Ymd_His');
        $file_path = $qr_dir . 'store_' . intval($store->id) . '_qr_' . $timestamp . '.png';

        $img = imagecreatetruecolor(400, 400);
        $bg  = imagecolorallocate($img, 255, 255, 255);
        $fg  = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $bg);
        imagestring($img, 5, 120, 190, 'PunktePass', $fg);
        imagepng($img, $file_path);
        imagedestroy($img);

        return [
            'img' => trailingslashit($upload_dir['baseurl']) . 'punktepass_qr/store_' . intval($store->id) . '_qr_' . $timestamp . '.png'
        ];
    }

    /** 🔹 Háttér színezése (csak ha szín meg van adva) */
    private static function add_colored_background($file_path, $hex_color) {
        [$r, $g, $b] = self::hex_to_rgb($hex_color);
        $qr = @imagecreatefrompng($file_path);
        if (!$qr) return;

        $w = imagesx($qr);
        $h = imagesy($qr);
        $bg = imagecreatetruecolor($w + 60, $h + 60);

        imagealphablending($bg, true);
        imagesavealpha($bg, true);

        $bg_color = imagecolorallocate($bg, $r, $g, $b);
        imagefill($bg, 0, 0, $bg_color);
        imagecopy($bg, $qr, 30, 30, 0, 0, $w, $h);

        imagepng($bg, $file_path);
        imagedestroy($qr);
        imagedestroy($bg);
    }

    /** 🔹 Logó lekerekített fehér háttérrel */
    private static function add_logo_with_white_box($qr_path, $logo_url) {
        $upload_dir = wp_upload_dir();
        $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo_url);
        if (!file_exists($logo_path)) return;

        $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'png': $logo = @imagecreatefrompng($logo_path); break;
            case 'jpg':
            case 'jpeg': $logo = @imagecreatefromjpeg($logo_path); break;
            case 'webp': $logo = @imagecreatefromwebp($logo_path); break;
            default: return;
        }

        $qr = @imagecreatefrompng($qr_path);
        if (!$qr || !$logo) return;

        imagealphablending($qr, true);
        imagesavealpha($qr, true);

        $qr_w = imagesx($qr);
        $qr_h = imagesy($qr);
        $logo_w = imagesx($logo);
        $logo_h = imagesy($logo);

        $new_logo_w = intval($qr_w / 5);
        $scale = $logo_w / $new_logo_w;
        $new_logo_h = intval($logo_h / $scale);
        $dst_x = intval(($qr_w - $new_logo_w) / 2);
        $dst_y = intval(($qr_h - $new_logo_h) / 2);

        // 🔸 Fehér lekerekített háttér
        $padding = 10;
        $box_w = $new_logo_w + $padding * 2;
        $box_h = $new_logo_h + $padding * 2;

        $white_bg = imagecreatetruecolor($box_w, $box_h);
        imagesavealpha($white_bg, true);
        $transparent = imagecolorallocatealpha($white_bg, 0, 0, 0, 127);
        imagefill($white_bg, 0, 0, $transparent);

        $white = imagecolorallocatealpha($white_bg, 255, 255, 255, 30);
        $radius = 12;

        imagefilledrectangle($white_bg, $radius, 0, $box_w - $radius, $box_h, $white);
        imagefilledrectangle($white_bg, 0, $radius, $box_w, $box_h - $radius, $white);

        $corner = imagecreatetruecolor($radius * 2, $radius * 2);
        imagesavealpha($corner, true);
        imagefill($corner, 0, 0, $transparent);
        imagefilledellipse($corner, $radius, $radius, $radius * 2, $radius * 2, $white);

        imagecopymerge($white_bg, $corner, 0, 0, 0, 0, $radius, $radius, 100);
        imagecopymerge($white_bg, $corner, $box_w - $radius, 0, $radius, 0, $radius, $radius, 100);
        imagecopymerge($white_bg, $corner, 0, $box_h - $radius, 0, $radius, $radius, $radius, 100);
        imagecopymerge($white_bg, $corner, $box_w - $radius, $box_h - $radius, $radius, $radius, $radius, $radius, 100);
        imagedestroy($corner);

        imagecopy($qr, $white_bg, $dst_x - $padding, $dst_y - $padding, 0, 0, $box_w, $box_h);
        imagedestroy($white_bg);

        // 🔹 Logó középre helyezése
        imagecopyresampled($qr, $logo, $dst_x, $dst_y, 0, 0, $new_logo_w, $new_logo_h, $logo_w, $logo_h);

        imagepng($qr, $qr_path);
        imagedestroy($qr);
        imagedestroy($logo);
    }

    /** 🔹 HEX → RGB konverzió */
    private static function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3)
            $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }
}
