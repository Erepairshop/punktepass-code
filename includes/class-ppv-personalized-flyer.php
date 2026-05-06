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
    const QR_X      = 555;
    const QR_Y      = 885;   // ~5mm lower than initial calibration
    const QR_SIZE   = 460;
    const NAME_Y    = 850;   // caption baseline above the QR (ignored if shop name empty)

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/personalized-flyer', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_render'],
            // Public: slug-based lookup. The flyer is a marketing asset, no
            // sensitive data — making it auth-free lets advertisers share the
            // download URL directly (and the admin button still works without
            // depending on global REST auth).
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

        // Draw business name above the QR — best-effort; fonts may not be
        // perfect across hosts but readable. Skip silently if anything fails.
        if (!empty($adv->business_name)) {
            $shop_name = mb_substr((string)$adv->business_name, 0, 32);
            $color_dark = imagecolorallocate($img, 17, 24, 39);
            $font_path = PPV_PLUGIN_DIR . 'assets/fonts/Inter-Bold.ttf';
            if (file_exists($font_path) && function_exists('imagettftext')) {
                @imagettftext(
                    $img, 26, 0,
                    self::QR_X, self::NAME_Y,
                    $color_dark, $font_path, $shop_name
                );
            } else {
                imagestring($img, 5, self::QR_X, self::NAME_Y - 24, $shop_name, $color_dark);
            }
        }

        imagepng($img, $cache_path, 6);
        imagedestroy($img);
        imagedestroy($qr_img);
        return $cache_path;
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
