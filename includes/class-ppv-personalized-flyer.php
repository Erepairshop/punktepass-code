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

    /** Per-language flyer calibration (file basename + QR rectangle + URL Y).
     *  Each flyer has a large white rectangular zone at the bottom designed
     *  to host the QR code + URL caption. Coordinates calibrated against
     *  flyer_neu_<lang>.png in assets/flyers/. */
    /** Per-language layout. The flyer has a 2-column bottom area; the right
     *  column has a dedicated "DEIN LINK" text slot and a dashed "QR-CODE"
     *  slot. We render INTO those slots specifically (not full-width). */
    const FLYER_CONFIG = [
        'de' => ['file' => 'flyer_neu_de.png', 'qr_x' => 610, 'qr_y' => 1025, 'qr_size' => 250,
                 'url_x' => 285, 'url_y' => 941, 'url_w' => 900,
                 'w' => 1024, 'h' => 1536],
        'hu' => ['file' => 'flyer_neu_hu.png', 'qr_x' => 625, 'qr_y' => 1045, 'qr_size' => 250,
                 'url_x' => 295, 'url_y' => 956, 'url_w' => 900, 'pad_extra' => 30,
                 'w' => 1054, 'h' => 1492],
        'ro' => ['file' => 'flyer_neu_ro.png', 'qr_x' => 625, 'qr_y' => 1033, 'qr_size' => 250,
                 'url_x' => 305, 'url_y' => 938, 'url_w' => 900, 'pad_extra' => 30,
                 'w' => 1054, 'h' => 1492],
        'en' => ['file' => 'flyer_neu_en.png', 'qr_x' => 617, 'qr_y' => 1005, 'qr_size' => 250,
                 'url_x' => 302, 'url_y' => 903, 'url_w' => 900, 'pad_extra' => 30,
                 'w' => 1054, 'h' => 1492],
    ];

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
        register_rest_route('ppv/v1', '/flyers-bundle-json', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_flyers_bundle_json'],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => ['type' => 'string', 'required' => false],
                'embed' => ['type' => 'string', 'default' => '1'],
            ],
        ]);
    }

    /** REST: bundle all flyer + social images as a single JSON download.
     *  GET /wp-json/ppv/v1/flyers-bundle-json?slug=foo[&embed=0]
     *    embed=1 (default): base64-encoded image data inside JSON
     *    embed=0: only metadata + URLs (small file, fetch images separately)
     */
    public static function rest_flyers_bundle_json($req) {
        global $wpdb;
        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
        $slug = sanitize_text_field((string) $req->get_param('slug'));
        $embed = ($req->get_param('embed') !== '0');
        $adv = null;
        if ($slug) {
            $adv = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, business_name, logo_url FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s LIMIT 1",
                $slug
            ));
        } elseif (!empty($_SESSION['ppv_advertiser_id'])) {
            $adv = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, business_name, logo_url FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d LIMIT 1",
                (int) $_SESSION['ppv_advertiser_id']
            ));
        }
        if (!$adv || empty($adv->slug)) {
            return new WP_REST_Response(['success' => false, 'msg' => 'advertiser slug missing'], 400);
        }

        $bundle = [
            'success'       => true,
            'generated_at'  => gmdate('c'),
            'advertiser'    => [
                'slug'          => $adv->slug,
                'business_name' => $adv->business_name,
                'qr_target_url' => home_url('/business/' . $adv->slug),
            ],
            'images'        => [],
        ];

        $langs = ['de', 'hu', 'ro', 'en'];
        foreach ($langs as $lang) {
            // Personalized flyer
            $flyer_path = self::generate($adv, $lang);
            if ($flyer_path && file_exists($flyer_path)) {
                $item = [
                    'type'     => 'flyer',
                    'lang'     => $lang,
                    'filename' => 'punktepass-flyer-' . $adv->slug . '-' . $lang . '.png',
                    'mime'     => 'image/png',
                    'bytes'    => filesize($flyer_path),
                    'url'      => home_url('/wp-json/ppv/v1/personalized-flyer?lang=' . $lang . '&slug=' . rawurlencode($adv->slug)),
                ];
                if ($embed) {
                    $item['data_uri'] = 'data:image/png;base64,' . base64_encode((string) file_get_contents($flyer_path));
                }
                $bundle['images'][] = $item;
            }
            // Social image — generate via cache (rest_social_image streams; we use same cache file).
            $upload = wp_upload_dir();
            $social_cache = trailingslashit($upload['basedir']) . 'punktepass_flyers/social-' . sanitize_file_name($adv->slug) . '-' . $lang . '.png';
            if (!file_exists($social_cache) || (time() - filemtime($social_cache) > 86400)) {
                // Warm cache by calling internal route. Easier: invoke the rest endpoint via a faux request handler
                // For now, fall back to URL-only if cache missing and we don't want to regenerate inline.
                // (Skipping inline generation keeps the JSON build fast; client can fetch via URL.)
            }
            $social_item = [
                'type'     => 'social',
                'lang'     => $lang,
                'filename' => 'punktepass-social-' . $adv->slug . '-' . $lang . '.png',
                'mime'     => 'image/png',
                'url'      => home_url('/wp-json/ppv/v1/social-image?lang=' . $lang . '&slug=' . rawurlencode($adv->slug)),
            ];
            if (file_exists($social_cache)) {
                $social_item['bytes'] = filesize($social_cache);
                if ($embed) {
                    $social_item['data_uri'] = 'data:image/png;base64,' . base64_encode((string) file_get_contents($social_cache));
                }
            }
            $bundle['images'][] = $social_item;
        }

        $json = wp_json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = 'punktepass-flyers-' . sanitize_file_name($adv->slug) . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');
        echo $json;
        exit;
    }

    public static function rest_render($req) {
        global $wpdb;
        $lang = strtolower(sanitize_text_field($req->get_param('lang')));
        if (!array_key_exists($lang, self::FLYER_CONFIG)) $lang = 'de';

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
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }

    /** Returns absolute filesystem path of the cached personalized flyer. */
    public static function generate($adv, $lang) {
        if (!array_key_exists($lang, self::FLYER_CONFIG)) $lang = 'de';
        $cfg = self::FLYER_CONFIG[$lang];

        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'punktepass_flyers/';
        if (!file_exists($dir)) wp_mkdir_p($dir);

        $cache_path = $dir . 'flyer-' . sanitize_file_name($adv->slug) . '-' . $lang . '.png';
        if (file_exists($cache_path) && (time() - filemtime($cache_path) < 86400)) {
            return $cache_path;
        }

        $base = PPV_PLUGIN_DIR . 'assets/flyers/' . $cfg['file'];
        if (!file_exists($base)) $base = PPV_PLUGIN_DIR . 'assets/flyers/flyer_neu_de.png';
        if (!file_exists($base)) return false;

        $img = @imagecreatefrompng($base);
        if (!$img) return false;

        $target_url = home_url('/business/' . $adv->slug);
        $qr_size = (int) $cfg['qr_size'];
        $qr_x    = (int) $cfg['qr_x'];
        $qr_y    = (int) $cfg['qr_y'];

        $qr_png = self::fetch_qr_png($target_url, $qr_size);
        if (!$qr_png) { imagedestroy($img); return false; }
        $qr_img = @imagecreatefromstring($qr_png);
        if (!$qr_img) { imagedestroy($img); return false; }

        imagecopyresampled(
            $img, $qr_img,
            $qr_x, $qr_y, 0, 0,
            $qr_size, $qr_size,
            imagesx($qr_img), imagesy($qr_img)
        );
        imagedestroy($qr_img);

        // The DEIN LINK slot already has placeholder text baked into the
        // flyer image ("punktepass.de/dein-shop"). White out that strip first,
        // then draw the real URL on top — single line, auto-shrunk to fit.
        $url_caption = 'punktepass.de/business/' . $adv->slug;
        $color_dark  = imagecolorallocate($img, 17, 24, 39);
        $font_path   = PPV_PLUGIN_DIR . 'assets/fonts/Inter-Bold.ttf';
        if (!file_exists($font_path)) {
            $fallback = PPV_PLUGIN_DIR . 'libs/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
            if (file_exists($fallback)) { $font_path = $fallback; }
        }
        $url_x       = (int) $cfg['url_x'];
        $url_y       = (int) $cfg['url_y'];
        $url_w       = (int) $cfg['url_w'];

        if (file_exists($font_path) && function_exists('imagettftext')) {
            // URL size: 15pt baseline fits prefix "punktepass.de/business/" + 9 slug chars
            // = 32 total chars. Scale proportionally so longer slugs still fit.
            $base_size  = 15;
            $base_chars = 32;
            $len = max(1, strlen($url_caption));
            $size = (int) max(6, min($base_size, floor($base_size * $base_chars / $len)));
            $bbox = imagettfbbox($size, 0, $font_path, $url_caption);
            $w = abs($bbox[2] - $bbox[0]);
            $tx = $url_x + ($url_w - $w) / 2;

            // Visible gray cover strip behind the text — generous padding so
            // it actually shows up around 2x-larger glyphs. Use a slightly
            // darker gray for clear contrast with the white-ish flyer bg.
            $bg  = imagecolorallocate($img, 246, 246, 246);
            $pad_x = 8;
            $extra_pad = isset($cfg['pad_extra']) ? (int)$cfg['pad_extra'] : 0;
            $pad_top = (int) ($size * 1.0) + 8 + (int)($extra_pad / 2);
            $pad_bot = (int) ($size * 0.25) + 7 + (int)($extra_pad / 2);
            imagefilledrectangle(
                $img,
                (int)($tx - $pad_x), $url_y - $pad_top,
                (int)($tx + $w + $pad_x), $url_y + $pad_bot,
                $bg
            );

            @imagettftext($img, $size, 0, (int)$tx, $url_y, $color_dark, $font_path, $url_caption);
        } else {
            $w = imagefontwidth(5) * strlen($url_caption);
            $tx = $url_x + ($url_w - $w) / 2;
            imagestring($img, 5, (int)$tx, $url_y - 14, $url_caption, $color_dark);
        }

        imagepng($img, $cache_path, 6);
        imagedestroy($img);
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
            'de' => ['Folge uns!', 'Verpasse keine Aktionen,', 'Coupons & Events!'],
            'hu' => ['Kövess minket!', 'Ne hagyd ki akcióinkat,', 'kuponjainkat és eseményeinket!'],
            'ro' => ['Urmărește-ne!', 'Nu rata ofertele,', 'cupoanele și evenimentele!'],
            'en' => ['Follow us!', "Don't miss our offers,", 'coupons and events!'],
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

        // Decorative sparkles on the gradient bg (before card)
        $sparkle = imagecolorallocatealpha($img, 255, 255, 255, 60);
        $sparkle2 = imagecolorallocatealpha($img, 255, 255, 255, 90);
        $stars = [
            [120, 90, 14], [970, 70, 18], [1010, 200, 10], [60, 320, 12],
            [100, 950, 16], [990, 880, 14], [1010, 1010, 11], [50, 1000, 13],
            [900, 540, 9], [70, 540, 9],
        ];
        foreach ($stars as $s) {
            self::draw_star($img, $s[0], $s[1], $s[2], $s[2] * 0.4, $s[2] === 18 || $s[2] === 16 ? $sparkle : $sparkle2);
        }

        // White card center
        $cardX = 60; $cardY = 60; $cardW = $W - 120; $cardH = $H - 120;
        imagefilledrectangle($img, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $white);

        // Brand accent strip on top of card
        $accent = imagecolorallocate($img, 99, 102, 241);
        imagefilledrectangle($img, $cardX, $cardY, $cardX + $cardW, $cardY + 8, $accent);
        // small badge top-right
        $badge_bg = imagecolorallocate($img, 245, 158, 11);
        imagefilledellipse($img, $cardX + $cardW - 60, $cardY + 60, 70, 70, $badge_bg);
        self::draw_star($img, $cardX + $cardW - 60, $cardY + 60, 22, 9, $white);

        // Logo (round, top center) — fetch advertiser logo if available
        $logo_drawn_h = 0;
        if (!empty($adv->logo_url)) {
            $logo_data = @file_get_contents($adv->logo_url);
            if ($logo_data) {
                $logo_img = @imagecreatefromstring($logo_data);
                if ($logo_img) {
                    $logo_size = 200;
                    $lx = (int)(($W - $logo_size) / 2);
                    $ly = 110;
                    imagecopyresampled($img, $logo_img, $lx, $ly, 0, 0,
                                       $logo_size, $logo_size,
                                       imagesx($logo_img), imagesy($logo_img));
                    imagedestroy($logo_img);
                    $logo_drawn_h = $logo_size + 30;
                }
            }
        }

        // Font fallback: prefer Inter, fallback to bundled DejaVu
        $font_path = PPV_PLUGIN_DIR . 'assets/fonts/Inter-Bold.ttf';
        if (!file_exists($font_path)) {
            $fallback = PPV_PLUGIN_DIR . 'libs/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
            if (file_exists($fallback)) $font_path = $fallback;
        }

        $brand = imagecolorallocate($img, 99, 102, 241);

        if (file_exists($font_path) && function_exists('imagettftext')) {
            // Business name — top (under logo if present, else high)
            $name = mb_substr((string)$adv->business_name, 0, 32);
            $name_y = 110 + $logo_drawn_h + 60;
            $size_n = 56;
            $bbox = imagettfbbox($size_n, 0, $font_path, $name);
            $w = abs($bbox[2] - $bbox[0]);
            while ($w > $cardW - 80 && $size_n > 28) {
                $size_n -= 2;
                $bbox = imagettfbbox($size_n, 0, $font_path, $name);
                $w = abs($bbox[2] - $bbox[0]);
            }
            $tx = (int) (($W - $w) / 2);
            imagettftext($img, $size_n, 0, $tx, $name_y, $dark, $font_path, $name);

            // Tagline — 3 lines, brand color line 1 (bigger), muted lines 2+3
            $tag_y = $name_y + 70;
            $size_l1 = 42;
            $bbox = imagettfbbox($size_l1, 0, $font_path, $tagline[0]);
            $w = abs($bbox[2] - $bbox[0]);
            $tx = (int) (($W - $w) / 2);
            imagettftext($img, $size_l1, 0, $tx, $tag_y, $brand, $font_path, $tagline[0]);

            $size_l = 26;
            $tag_y += 50;
            foreach ([$tagline[1], $tagline[2]] as $line) {
                $bbox = imagettfbbox($size_l, 0, $font_path, $line);
                $w = abs($bbox[2] - $bbox[0]);
                while ($w > $cardW - 80 && $size_l > 16) {
                    $size_l -= 1;
                    $bbox = imagettfbbox($size_l, 0, $font_path, $line);
                    $w = abs($bbox[2] - $bbox[0]);
                }
                $tx = (int) (($W - $w) / 2);
                imagettftext($img, $size_l, 0, $tx, $tag_y, $muted, $font_path, $line);
                $tag_y += 36;
            }
        }

        // QR — placed in lower section
        $qr_size = 380;
        $qr_x = (int)(($W - $qr_size) / 2);
        $qr_y = $H - $qr_size - 130;
        $qr_url = home_url('/business/' . $adv->slug);
        $qr_png = self::fetch_qr_png($qr_url, $qr_size);
        if ($qr_png) {
            $qr_img = @imagecreatefromstring($qr_png);
            if ($qr_img) {
                imagecopyresampled($img, $qr_img, $qr_x, $qr_y, 0, 0,
                                   $qr_size, $qr_size, imagesx($qr_img), imagesy($qr_img));
                imagedestroy($qr_img);
            }
        }

        // URL caption below QR
        if (file_exists($font_path) && function_exists('imagettftext')) {
            $url_caption = 'punktepass.de/business/' . $adv->slug;
            $size_u = 28;
            $bbox = imagettfbbox($size_u, 0, $font_path, $url_caption);
            $w = abs($bbox[2] - $bbox[0]);
            while ($w > $cardW - 60 && $size_u > 14) {
                $size_u -= 1;
                $bbox = imagettfbbox($size_u, 0, $font_path, $url_caption);
                $w = abs($bbox[2] - $bbox[0]);
            }
            $tx = (int) (($W - $w) / 2);
            imagettftext($img, $size_u, 0, $tx, $H - 80, $dark, $font_path, $url_caption);
        }

        imagepng($img, $cache_path, 6);
        imagedestroy($img);
        self::stream_png($cache_path, 'punktepass-social-' . $adv->slug . '-' . $lang . '.png');
        return null;
    }

    /** 5-pointed filled star centered at (cx,cy). $r1 outer, $r2 inner radius. */
    private static function draw_star($img, $cx, $cy, $r1, $r2, $color) {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $r = ($i % 2 === 0) ? $r1 : $r2;
            $a = -M_PI / 2 + $i * (M_PI / 5);
            $pts[] = $cx + $r * cos($a);
            $pts[] = $cy + $r * sin($a);
        }
        if (PHP_VERSION_ID >= 80100) {
            imagefilledpolygon($img, $pts, $color);
        } else {
            imagefilledpolygon($img, $pts, count($pts) / 2, $color);
        }
    }

    private static function stream_png($path, $filename) {
        if (!file_exists($path)) return;
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
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
