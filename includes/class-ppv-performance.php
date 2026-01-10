<?php
/**
 * PunktePass – Performance Optimization
 *
 * Features:
 * - Critical CSS inline (above-the-fold styles)
 * - Lazy loading for images
 * - Defer non-critical CSS
 * - Font-display: swap
 * - CLS fixes (layout shift prevention)
 */

if (!defined('ABSPATH')) exit;

class PPV_Performance {

    public static function hooks() {
        // Critical CSS inline in head (very early)
        add_action('wp_head', [__CLASS__, 'inline_critical_css'], 1);

        // Defer non-critical CSS
        add_filter('style_loader_tag', [__CLASS__, 'defer_non_critical_css'], 10, 4);

        // Add lazy loading to images
        add_filter('the_content', [__CLASS__, 'add_lazy_loading'], 999);
        add_filter('post_thumbnail_html', [__CLASS__, 'add_lazy_loading'], 999);
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'lazy_load_attributes'], 10, 3);

        // Preconnect to external resources
        add_action('wp_head', [__CLASS__, 'add_preconnect'], 1);

        // Font-display swap for Google Fonts
        add_filter('style_loader_tag', [__CLASS__, 'font_display_swap'], 10, 4);

        // Defer non-critical JavaScript
        add_filter('script_loader_tag', [__CLASS__, 'defer_scripts'], 10, 3);

        // Remove render-blocking resources
        add_action('wp_head', [__CLASS__, 'remove_render_blocking'], 1);
    }

    /**
     * Inline Critical CSS
     * These are the essential above-the-fold styles
     */
    public static function inline_critical_css() {
        ?>
        <style id="ppv-critical-css">
        /* Critical CSS - Above the fold styles */

        /* Base reset */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* Body base */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdfa 100%);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Prevent FOUT (Flash of Unstyled Text) */
        body { opacity: 1; }

        /* Header skeleton */
        .ppv-header-bar, #ppv-global-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            min-height: 60px;
        }

        /* Bottom nav skeleton - prevent CLS */
        .ppv-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 72px;
            background: #fff;
            z-index: 9999;
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .ppv-bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            text-decoration: none;
            color: #64748b;
            font-size: 24px;
        }

        .ppv-bottom-nav .nav-item.active {
            color: #0ea5e9;
        }

        /* Main content area - reserve space */
        #ppv-my-points-app,
        #ppv-dashboard-root,
        .ppv-profile-container,
        .ppv-qr-wrapper {
            min-height: calc(100vh - 132px);
            padding: 20px;
            padding-bottom: 90px;
        }

        /* Card skeleton */
        .ppv-section, .glass-card, .ppv-store-card-enhanced {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        /* Loading placeholder animation */
        @keyframes ppv-skeleton {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        .ppv-skeleton {
            animation: ppv-skeleton 1.5s ease-in-out infinite;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
        }

        /* Image placeholder - prevent CLS */
        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        img[loading="lazy"] {
            background: #f0f0f0;
        }

        /* Fixed aspect ratios for common images - prevents CLS */
        .ppv-header-logo-tiny { width: 32px; height: 32px; }
        .ppv-header-avatar { width: 36px; height: 36px; border-radius: 50%; }
        .ppv-rw-store-logo { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; }
        #ppv-avatar-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }

        /* Font loading optimization */
        @font-face {
            font-family: 'Inter';
            font-display: swap;
            src: local('Inter');
        }

        /* RemixIcon placeholder while loading */
        .ri-home-smile-2-line, .ri-donut-chart-line, .ri-coupon-3-line,
        .ri-settings-3-line, .ri-feedback-line, .ri-star-fill,
        .ri-user-3-line, .ri-bar-chart-line, .ri-store-2-fill {
            width: 24px;
            height: 24px;
            display: inline-block;
        }

        /* Compact header - fixed height */
        .ppv-compact-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            min-height: 56px;
            background: rgba(255,255,255,0.95);
        }

        /* Hide content until CSS loads */
        .ppv-deferred-visible {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .ppv-css-loaded .ppv-deferred-visible {
            opacity: 1;
        }
        </style>
        <script>
        // Mark body when full CSS loads
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('ppv-css-loaded');
        });
        </script>
        <?php
    }

    /**
     * Defer non-critical CSS (load async)
     */
    public static function defer_non_critical_css($html, $handle, $href, $media) {
        // Don't defer critical stylesheets
        $critical_handles = ['ppv-critical', 'remixicons'];

        if (in_array($handle, $critical_handles)) {
            return $html;
        }

        // Don't defer admin styles
        if (is_admin()) {
            return $html;
        }

        // For main theme CSS, use preload + onload pattern
        if (in_array($handle, ['ppv-theme-light', 'ppv-handler-light', 'ppv-bottom-nav'])) {
            // Preload the CSS
            $preload = sprintf(
                '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n" .
                '<noscript><link rel="stylesheet" href="%s"></noscript>',
                esc_url($href),
                esc_url($href)
            );
            return $preload;
        }

        return $html;
    }

    /**
     * Add lazy loading to images in content
     */
    public static function add_lazy_loading($content) {
        if (empty($content)) {
            return $content;
        }

        // Add loading="lazy" to img tags that don't have it
        $content = preg_replace(
            '/<img(?![^>]*loading=)([^>]*)>/i',
            '<img loading="lazy"$1>',
            $content
        );

        // Add decoding="async" for better performance
        $content = preg_replace(
            '/<img(?![^>]*decoding=)([^>]*)>/i',
            '<img decoding="async"$1>',
            $content
        );

        return $content;
    }

    /**
     * Add lazy loading attributes to attachment images
     */
    public static function lazy_load_attributes($attr, $attachment, $size) {
        // Don't lazy load above-the-fold images (first few)
        static $image_count = 0;
        $image_count++;

        // First 2 images should load eagerly (above the fold)
        if ($image_count <= 2) {
            $attr['loading'] = 'eager';
            $attr['fetchpriority'] = 'high';
        } else {
            $attr['loading'] = 'lazy';
            $attr['decoding'] = 'async';
        }

        return $attr;
    }

    /**
     * Add preconnect hints for faster external resource loading
     */
    public static function add_preconnect() {
        ?>
        <!-- Preconnect to external resources -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        <link rel="dns-prefetch" href="https://fonts.googleapis.com">
        <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
        <?php
    }

    /**
     * Add font-display: swap to Google Fonts
     */
    public static function font_display_swap($html, $handle, $href, $media) {
        // Add display=swap to Google Fonts URLs
        if (strpos($href, 'fonts.googleapis.com') !== false) {
            if (strpos($href, 'display=') === false) {
                $href = add_query_arg('display', 'swap', $href);
                $html = str_replace($html, sprintf(
                    '<link rel="stylesheet" id="%s-css" href="%s" media="%s">',
                    esc_attr($handle),
                    esc_url($href),
                    esc_attr($media)
                ), $html);
            }
        }

        return $html;
    }

    /**
     * Defer non-critical JavaScript
     * Adds defer attribute to scripts that don't need to run immediately
     */
    public static function defer_scripts($tag, $handle, $src) {
        // Skip admin
        if (is_admin()) {
            return $tag;
        }

        // Critical scripts that should NOT be deferred (need to run immediately)
        $no_defer = [
            'jquery-core',
            'jquery',
            'wp-polyfill',
            'ppv-global-init-lock', // Must run first to prevent duplicate listeners
            'ppv-debug', // Must load early for production-safe logging
            'ppv-critical',
        ];

        if (in_array($handle, $no_defer)) {
            return $tag;
        }

        // Scripts that should be async (independent, don't depend on DOM)
        $async_scripts = [
            'google-platform',
            'facebook-jssdk',
        ];

        // Add async for independent external scripts
        if (in_array($handle, $async_scripts)) {
            if (strpos($tag, 'async') === false) {
                $tag = str_replace(' src=', ' async src=', $tag);
            }
            return $tag;
        }

        // Add defer to all other PPV scripts
        if (strpos($handle, 'ppv-') === 0 || strpos($handle, 'pp-') === 0) {
            if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
                $tag = str_replace(' src=', ' defer src=', $tag);
            }
        }

        return $tag;
    }

    /**
     * Add resource hints (preconnect only - no async CSS to prevent CLS)
     */
    public static function remove_render_blocking() {
        ?>
        <!-- Resource Hints - preconnect only, no async loading to prevent CLS -->
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        <?php
    }
}

// Initialize
PPV_Performance::hooks();
