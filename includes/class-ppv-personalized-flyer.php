<?php
/**
 * Per-advertiser personalized flyer.
 *
 * Takes the base PunktePass flyer PNG (assets/flyers/punktepass-flyer-<lang>.png)
 * and overlays:
 *   1. A QR code that points to https://punktepass.de/business/<slug>
 *      (so QR scanners land directly on the advertiser's page)
 *   2. The advertiser business name as a small caption above the QR
 *
 * The output is cached in wp-content/uploads/punktepass_flyers/.
 *
 * REST: GET /wp-json/ppv/v1/personalized-flyer?lang=de
 *   Auth: advertiser session ($_SESSION['ppv_advertiser_id']).
 *   Streams a PNG with Content-Disposition: inline.
 */

if (!defined('ABSPATH')) exit;

class PPV_Personalized_Flyer {

    /** Approximate QR rectangle (origin + size) on the base flyer.
     *  Calibrated for the 1054×1492 PunktePass flyer. The base QR sits
     *  bottom-right inside the yellow-bordered box. */
    const QR_X      = 569;   // ~2mm inset (was 555)
    const QR_Y      = 878;   // shifted ~3mm up (was 899)
    const QR_SIZE   = 432;   // ~4mm smaller (2mm each side, was 460)
    const NAME_Y    = 843;   // caption baseline above the QR (ignored if shop name empty)

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/personalized-flyer', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_render'],
            'permission_callback' => '__return_true',
            'args' => [
                'lang' => ['type' => 'string', 'default' => 'de'],
                'slug' => ['type' => 'string', 'required' => false],
            ],
        ]);
        register_rest_route('ppv/v1', '/social-image', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_social_image'],
            'permission_callback' => '__return_true',
            'args' => [
                'lang' => ['type' => 'string', 'default' => 'de'],
                'slug' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public static function rest_render($req) {
        global $wpdb;
        $lang = strtolower(sanitize_text_field($req->get_param('lang')));
        if (!in_array($lang, ['de', 'ro'], true)) $lang = 'de';

        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
        $slug = sanitize_text_field((string) $req->get_param('slug'));
        $adv = null;
        if ($slug) {
            $adv = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, business_name FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s LIMIT 1",
                $slug
            ));
        } elseif (!empty($_SESSION['ppv_advertiser_id'])) {
            $adv_id = (int) $_SESSION['ppv_advertiser_id'];
            $adv = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, business_name FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d LIMIT 1",
                $adv_id
            ));
        }
        if (!$adv || empty($adv->slug)) {
            return new WP_REST_Response(['success' => false, 'msg' => 'advertiser slug missing'], 400);
        }

        $path = self::generate($adv, $lang);
        if (!$path || !file_exists($path)) {
            return new WP_REST_Response(['success' => false, 'msg' => 'render failed'], 500);
        }

        $filename = 'punktepass-flyer-' . sanitize_file_name($adv->slug) . '-' . $lang . '.png';
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }

    /** Returns absolute filesystem path of the cached personalized flyer. */
    public static function generate($adv, $lang) {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'punktepass_flyers/';
        if (!file_exists($dir)) wp_mkdir_p($dir);

        $cache_path = $dir . 'flyer-' . sanitize_file_name($adv->slug) . '-' . $lang . '.png';
        if (file_exists($cache_path) && (time() - filemtime($cache_path) < 86400)) {
            return $cache_path;
        }

        $base = PPV_PLUGIN_DIR . 'assets/flyers/punktepass-flyer-' . $lang . '.png';
        if (!file_exists($base)) $base = PPV_PLUGIN_DIR . 'assets/flyers/punktepass-flyer-de.png';
        if (!file_exists($base)) return false;

        $img = @imagecreatefrompng($base);
        if (!$img) return false;

        $target_url = home_url('/business/' . $adv->slug);
        $qr_png = self::fetch_qr_png($target_url, self::QR_SIZE);
        if (!$qr_png) { imagedestroy($img); return false; }
        $qr_img = @imagecreatefromstring($qr_png);
        if (!$qr_img) { imagedestroy($img); return false; }

        // White background panel behind the QR so it scans cleanly even if the
        // base art has subtle texture under it.
        $pad = 14;
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle(
            $img,
            self::QR_X - $pad, self::QR_Y - $pad,
            self::QR_X + self::QR_SIZE + $pad, self::QR_Y + self::QR_SIZE + $pad,
            $white
        );

        imagecopyresampled(
            $img, $qr_img,
            self::QR_X, self::QR_Y, 0, 0,
            self::QR_SIZE, self::QR_SIZE,
            imagesx($qr_img), imagesy($qr_img)
        );

        // Draw URL caption BELOW the QR so customers can also type it manually
        // if QR scanning fails. Format: punktepass.de/business/<slug>
        $url_caption = 'punktepass.de/business/' . $adv->slug;
        $color_dark  = imagecolorallocate($img, 17, 24, 39);
        $font_path   = PPV_PLUGIN_DIR . 'assets/fonts/Inter-Bold.ttf';
        $caption_y   = self::QR_Y + self::QR_SIZE + 28;
        if (file_exists($font_path) && function_exists('imagettftext')) {
            // Auto-size font so the URL fits within the QR width (best effort).
            $size = 18;
            $bbox = @imagettfbbox($size, 0, $font_path, $url_caption);
            if ($bbox) {
                $w = abs($bbox[2] - $bbox[0]);
                while ($w > self::QR_SIZE + 20 && $size > 10) {
                    $size--;
                    $bbox = imagettfbbox($size, 0, $font_path, $url_caption);
                    $w = abs($bbox[2] - $bbox[0]);
                }
                $tx = self::QR_X + (self::QR_SIZE - $w) / 2;
                @imagettftext($img, $size, 0, (int)$tx, $caption_y, $color_dark, $font_path, $url_caption);
            }
        } else {
            // Fallback to bitmap font (less elegant but always works)
            $w = imagefontwidth(4) * strlen($url_caption);
            $tx = self::QR_X + (self::QR_SIZE - $w) / 2;
            imagestring($img, 4, (int)$tx, $caption_y - 14, $url_caption, $color_dark);
        }

        imagepng($img, $cache_path, 6);
        imagedestroy($img);
        imagedestroy($qr_img);
        return $cache_path;
    }

    /** REST: 1080×1080 social image (Instagram/Facebook/WhatsApp Stories) */
    public static function rest_social_image($req) {
        global $wpdb;
        $lang = strtolower(sanitize_text_field($req->get_param('lang')));
        if (!in_array($lang, ['de','hu','ro','en'], true)) $lang = 'de';
        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
        $slug = sanitize_text_field((string) $req->get_param('slug'));
        $adv = null;
        if ($slug) {
            $adv = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, business_name, logo_url FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s LIMIT 1", $slug));
        } elseif (!empty($_SESSION['ppv_advertiser_id'])) {
            $adv = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, business_name, logo_url FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d LIMIT 1",
                (int)$_SESSION['ppv_advertiser_id']));
        }
        if (!$adv || empty($adv->slug)) {
            return new WP_REST_Response(['success'=>false,'msg'=>'advertiser not found'], 404);
        }

        $tagline = [
            'de' => 'Folge uns für Aktionen + Coupons',
            'hu' => 'Kövess minket akciókért és kuponokért',
            'ro' => 'Urmărește-ne pentru oferte și cupoane',
            'en' => 'Follow us for offers and coupons',
        ][$lang];

        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'punktepass_flyers/';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $cache_path = $dir . 'social-' . sanitize_file_name($adv->slug) . '-' . $lang . '.png';
        if (file_exists($cache_path) && (time() - filemtime($cache_path) < 86400)) {
            self::stream_png($cache_path, 'punktepass-social-' . $adv->slug . '-' . $lang . '.png');
            return null;
        }

        // Canvas 1080×1080
        $W = 1080; $H = 1080;
        $img = imagecreatetruecolor($W, $H);
        // gradient background (purple -> indigo)
        $top = imagecolorallocate($img, 99, 102, 241);    // #6366f1
        $bot = imagecolorallocate($img, 139, 92, 246);    // #8b5cf6
        for ($y = 0; $y < $H; $y++) {
            $t = $y / max(1, $H - 1);
            $r = (int) (((1-$t) * 99) + ($t * 139));
            $g = (int) (((1-$t) * 102) + ($t * 92));
            $b = (int) (((1-$t) * 241) + ($t * 246));
            $c = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $W, $y, $c);
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark  = imagecolorallocate($img, 30, 41, 59);
        $muted = imagecolorallocate($img, 71, 85, 105);

        // White rounded card in the center
        $cardX = 80; $cardY = 80; $cardW = $W - 160; $cardH = $H - 160;
        imagefilledrectangle($img, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $white);

        // QR (640x640 in the bottom)
        $qr_size = 600;
        $qr_x = ($W - $qr_size) / 2;
        $qr_y = $H - $qr_size - 140;
        $qr_url = home_url('/business/' . $adv->slug);
        $qr_png = self::fetch_qr_png($qr_url, $qr_size);
        if ($qr_png) {
            $qr_img = @imagecreatefromstring($qr_png);
            if ($qr_img) {
                imagecopyresampled($img, $qr_img, (int)$qr_x, (int)$qr_y, 0, 0,
                                   $qr_size, $qr_size, imagesx($qr_img), imagesy($qr_img));
                imagedestroy($qr_img);
            }
        }

        // Text: business name + tagline
        $font_path = PPV_PLUGIN_DIR . 'assets/fonts/Inter-Bold.ttf';
        if (file_exists($font_path) && function_exists('imagettftext')) {
            $name = mb_substr((string)$adv->business_name, 0, 32);
            $size_n = 64;
            $bbox = imagettfbbox($size_n, 0, $font_path, $name);
            $w = abs($bbox[2] - $bbox[0]);
            while ($w > $cardW - 80 && $size_n > 32) {
                $size_n -= 4;
                $bbox = imagettfbbox($size_n, 0, $font_path, $name);
                $w = abs($bbox[2] - $bbox[0]);
            }
            $tx = (int) (($W - $w) / 2);
            imagettftext($img, $size_n, 0, $tx, 220, $dark, $font_path, $name);

            $size_t = 30;
            $bbox = imagettfbbox($size_t, 0, $font_path, $tagline);
            $w = abs($bbox[2] - $bbox[0]);
            $tx = (int) (($W - $w) / 2);
            imagettftext($img, $size_t, 0, $tx, 290, $muted, $font_path, $tagline);

            // URL caption below QR
            $url_caption = 'punktepass.de/business/' . $adv->slug;
            $size_u = 28;
            $bbox = imagettfbbox($size_u, 0, $font_path, $url_caption);
            $w = abs($bbox[2] - $bbox[0]);
            while ($w > $cardW - 60 && $size_u > 16) {
                $size_u -= 2;
                $bbox = imagettfbbox($size_u, 0, $font_path, $url_caption);
                $w = abs($bbox[2] - $bbox[0]);
            }
            $tx = (int) (($W - $w) / 2);
            imagettftext($img, $size_u, 0, $tx, $H - 90, $dark, $font_path, $url_caption);
        }

        imagepng($img, $cache_path, 6);
        imagedestroy($img);
        self::stream_png($cache_path, 'punktepass-social-' . $adv->slug . '-' . $lang . '.png');
        return null;
    }

    private static function stream_png($path, $filename) {
        if (!file_exists($path)) return;
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }

    private static function fetch_qr_png($url, $size) {
        $api = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size .
               '&margin=2&color=000000&bgcolor=FFFFFF&data=' . rawurlencode($url);
        $resp = wp_remote_get($api, ['timeout' => 15]);
        if (is_wp_error($resp)) return false;
        $body = wp_remote_retrieve_body($resp);
        return !empty($body) ? $body : false;
    }
}
PPV_Personalized_Flyer::hooks();
