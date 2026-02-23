<?php
/**
 * PunktePass Blog System
 *
 * SEO-optimized blog rendered in PunktePass design.
 * Uses WordPress native posts with custom standalone rendering.
 *
 * Routes:
 *   /blog/                     → Blog listing (paginated)
 *   /blog/{slug}/              → Single post
 *   /blog/kategorie/{slug}/    → Category archive
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Blog {

    const POSTS_PER_PAGE = 9;

    /** Register hooks */
    public static function hooks() {
        add_action('template_redirect', [__CLASS__, 'handle_request'], 5);
        add_action('init', [__CLASS__, 'register_rewrite_rules']);

        // Sitemap route
        add_action('template_redirect', [__CLASS__, 'handle_sitemap'], 1);

        // Auto-ping search engines on publish/update
        add_action('publish_post', [__CLASS__, 'on_post_publish'], 10, 2);
        add_action('edit_post', [__CLASS__, 'on_post_update'], 10, 2);

        // Serve IndexNow key verification file (/{key}.txt)
        add_action('init', [__CLASS__, 'serve_indexnow_key'], 1);
    }

    /** Register rewrite rules for blog */
    public static function register_rewrite_rules() {
        // Blog listing (paginated)
        add_rewrite_rule('^blog/?$', 'index.php?ppv_blog=1', 'top');
        add_rewrite_rule('^blog/seite/([0-9]+)/?$', 'index.php?ppv_blog=1&ppv_blog_page=$matches[1]', 'top');

        // Category archive
        add_rewrite_rule('^blog/kategorie/([^/]+)/?$', 'index.php?ppv_blog=1&ppv_blog_cat=$matches[1]', 'top');
        add_rewrite_rule('^blog/kategorie/([^/]+)/seite/([0-9]+)/?$', 'index.php?ppv_blog=1&ppv_blog_cat=$matches[1]&ppv_blog_page=$matches[2]', 'top');

        // Single post
        add_rewrite_rule('^blog/([^/]+)/?$', 'index.php?ppv_blog=1&ppv_blog_slug=$matches[1]', 'top');

        // Blog sitemap
        add_rewrite_rule('^blog-sitemap\.xml$', 'index.php?ppv_blog_sitemap=1', 'top');

        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'ppv_blog';
            $vars[] = 'ppv_blog_slug';
            $vars[] = 'ppv_blog_cat';
            $vars[] = 'ppv_blog_page';
            $vars[] = 'ppv_blog_sitemap';
            return $vars;
        });
    }

    /** Handle blog requests */
    public static function handle_request() {
        if (!get_query_var('ppv_blog')) return;

        $slug = get_query_var('ppv_blog_slug');
        $cat  = get_query_var('ppv_blog_cat');
        $page = max(1, intval(get_query_var('ppv_blog_page') ?: 1));

        // Exclude reserved slugs
        $reserved = ['kategorie', 'seite'];
        if ($slug && in_array($slug, $reserved)) {
            $slug = '';
        }

        if ($slug) {
            self::render_single($slug);
        } elseif ($cat) {
            self::render_listing($page, $cat);
        } else {
            self::render_listing($page);
        }
        exit;
    }

    // =========================================
    // LISTING PAGE
    // =========================================

    /** Render blog listing page */
    private static function render_listing($page = 1, $category_slug = '') {
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => self::POSTS_PER_PAGE,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $page_title = 'Blog';
        $page_description = 'Tipps, News und Wissenswertes rund um Kundenbindung, Bonusprogramme und digitale Lösungen für lokale Geschäfte.';
        $canonical = home_url('/blog/');

        if ($category_slug) {
            $cat = get_category_by_slug($category_slug);
            if (!$cat) {
                status_header(404);
                self::render_404();
                return;
            }
            $args['cat'] = $cat->term_id;
            $page_title = $cat->name . ' - Blog';
            $page_description = $cat->description ?: "Artikel zum Thema {$cat->name} – PunktePass Blog.";
            $canonical = home_url("/blog/kategorie/{$category_slug}/");
        }

        $query = new WP_Query($args);
        $total_pages = $query->max_num_pages;

        if ($page > 1) {
            $canonical .= "seite/{$page}/";
        }

        // Get categories for filter
        $categories = get_categories(['hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);

        // Build SEO
        $seo = self::build_listing_seo($page_title, $page_description, $canonical, $page);

        // Render
        self::render_page_shell($page_title, $seo, function() use ($query, $categories, $category_slug, $page, $total_pages) {
            self::render_listing_content($query, $categories, $category_slug, $page, $total_pages);
        });
    }

    /** Build listing page content */
    private static function render_listing_content($query, $categories, $active_cat, $page, $total_pages) {
        ?>
        <!-- Hero Section -->
        <section class="ppv-blog-hero">
            <div class="ppv-blog-hero-inner">
                <h1 class="ppv-blog-hero-title">PunktePass Blog</h1>
                <p class="ppv-blog-hero-subtitle">Tipps, News und Wissenswertes rund um Kundenbindung, Bonusprogramme und digitale Lösungen für lokale Geschäfte.</p>
            </div>
        </section>

        <!-- Category Filter -->
        <?php if (!empty($categories)): ?>
        <nav class="ppv-blog-categories" aria-label="Blog Kategorien">
            <a href="<?php echo home_url('/blog/'); ?>" class="ppv-blog-cat-chip <?php echo !$active_cat ? 'active' : ''; ?>">Alle</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo home_url('/blog/kategorie/' . $cat->slug . '/'); ?>"
                   class="ppv-blog-cat-chip <?php echo $active_cat === $cat->slug ? 'active' : ''; ?>">
                    <?php echo esc_html($cat->name); ?>
                    <span class="ppv-blog-cat-count"><?php echo $cat->count; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- Posts Grid -->
        <?php if ($query->have_posts()): ?>
        <div class="ppv-blog-grid">
            <?php
            $index = 0;
            while ($query->have_posts()): $query->the_post();
                self::render_post_card($index === 0 && $page === 1 && !$active_cat);
                $index++;
            endwhile;
            wp_reset_postdata();
            ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <?php self::render_pagination($page, $total_pages, $active_cat); ?>
        <?php endif; ?>

        <?php else: ?>
        <div class="ppv-blog-empty">
            <i class="ri-article-line"></i>
            <p>Noch keine Beiträge vorhanden.</p>
        </div>
        <?php endif; ?>
        <?php
    }

    /** Render a single post card */
    private static function render_post_card($is_featured = false) {
        $post_id = get_the_ID();
        $thumbnail = get_the_post_thumbnail_url($post_id, $is_featured ? 'large' : 'medium_large');
        $cats = get_the_category($post_id);
        $read_time = self::estimate_read_time(get_the_content());
        $card_class = 'ppv-blog-card' . ($is_featured ? ' ppv-blog-card--featured' : '');
        ?>
        <article class="<?php echo $card_class; ?>">
            <a href="<?php echo home_url('/blog/' . get_post_field('post_name') . '/'); ?>" class="ppv-blog-card-link">
                <?php if ($thumbnail): ?>
                <div class="ppv-blog-card-img">
                    <img src="<?php echo esc_url($thumbnail); ?>"
                         alt="<?php echo esc_attr(get_the_title()); ?>"
                         loading="lazy"
                         width="600" height="340">
                </div>
                <?php else: ?>
                <div class="ppv-blog-card-img ppv-blog-card-img--placeholder">
                    <i class="ri-article-line"></i>
                </div>
                <?php endif; ?>

                <div class="ppv-blog-card-body">
                    <div class="ppv-blog-card-meta">
                        <?php if (!empty($cats)): ?>
                            <span class="ppv-blog-card-cat"><?php echo esc_html($cats[0]->name); ?></span>
                        <?php endif; ?>
                        <span class="ppv-blog-card-date">
                            <i class="ri-calendar-line"></i>
                            <?php echo get_the_date('d. M Y'); ?>
                        </span>
                        <span class="ppv-blog-card-read">
                            <i class="ri-time-line"></i>
                            <?php echo $read_time; ?> Min.
                        </span>
                    </div>
                    <h2 class="ppv-blog-card-title"><?php echo esc_html(get_the_title()); ?></h2>
                    <p class="ppv-blog-card-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), $is_featured ? 30 : 18)); ?></p>
                    <span class="ppv-blog-card-cta">Weiterlesen <i class="ri-arrow-right-line"></i></span>
                </div>
            </a>
        </article>
        <?php
    }

    /** Render pagination */
    private static function render_pagination($current, $total, $cat_slug = '') {
        $base = $cat_slug ? "/blog/kategorie/{$cat_slug}/" : '/blog/';
        ?>
        <nav class="ppv-blog-pagination" aria-label="Blog Seiten">
            <?php if ($current > 1): ?>
                <a href="<?php echo home_url($base . ($current - 1 > 1 ? "seite/" . ($current - 1) . "/" : '')); ?>" class="ppv-blog-page-btn" aria-label="Vorherige Seite">
                    <i class="ri-arrow-left-s-line"></i> Zurück
                </a>
            <?php endif; ?>

            <div class="ppv-blog-page-numbers">
                <?php for ($i = 1; $i <= $total; $i++): ?>
                    <?php if ($i === $current): ?>
                        <span class="ppv-blog-page-num active"><?php echo $i; ?></span>
                    <?php elseif (abs($i - $current) <= 2 || $i === 1 || $i === $total): ?>
                        <a href="<?php echo home_url($base . ($i > 1 ? "seite/{$i}/" : '')); ?>" class="ppv-blog-page-num"><?php echo $i; ?></a>
                    <?php elseif (abs($i - $current) === 3): ?>
                        <span class="ppv-blog-page-dots">&hellip;</span>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <?php if ($current < $total): ?>
                <a href="<?php echo home_url($base . "seite/" . ($current + 1) . "/"); ?>" class="ppv-blog-page-btn" aria-label="Nächste Seite">
                    Weiter <i class="ri-arrow-right-s-line"></i>
                </a>
            <?php endif; ?>
        </nav>
        <?php
    }

    // =========================================
    // SINGLE POST
    // =========================================

    /** Render single post */
    private static function render_single($slug) {
        $post = get_page_by_path($slug, OBJECT, 'post');

        if (!$post || $post->post_status !== 'publish') {
            status_header(404);
            self::render_404();
            return;
        }

        // Set up post data
        setup_postdata($GLOBALS['post'] = $post);
        $post_id = $post->ID;

        $title       = get_the_title($post_id);
        $content     = apply_filters('the_content', $post->post_content);
        $excerpt     = get_the_excerpt($post_id);
        $thumbnail   = get_the_post_thumbnail_url($post_id, 'full');
        $cats        = get_the_category($post_id);
        $tags        = get_the_tags($post_id);
        $author_name = get_the_author_meta('display_name', $post->post_author);
        $date        = get_the_date('d. F Y', $post_id);
        $date_iso    = get_the_date('c', $post_id);
        $modified_iso = get_the_modified_date('c', $post_id);
        $read_time   = self::estimate_read_time($post->post_content);
        $canonical   = home_url('/blog/' . $slug . '/');

        // Related posts
        $related = self::get_related_posts($post_id, $cats, 3);

        // SEO
        $seo = self::build_single_seo($title, $excerpt, $canonical, $thumbnail, $author_name, $date_iso, $modified_iso);

        // Render
        self::render_page_shell($title . ' - PunktePass Blog', $seo, function() use ($post_id, $title, $content, $thumbnail, $cats, $tags, $author_name, $date, $read_time, $related, $canonical) {
            self::render_single_content($post_id, $title, $content, $thumbnail, $cats, $tags, $author_name, $date, $read_time, $related, $canonical);
        });

        wp_reset_postdata();
    }

    /** Build single post content */
    private static function render_single_content($post_id, $title, $content, $thumbnail, $cats, $tags, $author_name, $date, $read_time, $related, $canonical) {
        ?>
        <!-- Reading Progress -->
        <div class="ppv-blog-progress" id="ppvBlogProgress">
            <div class="ppv-blog-progress-bar" id="ppvBlogProgressBar"></div>
        </div>

        <!-- Breadcrumb -->
        <nav class="ppv-blog-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo home_url('/'); ?>">Home</a>
            <i class="ri-arrow-right-s-line"></i>
            <a href="<?php echo home_url('/blog/'); ?>">Blog</a>
            <?php if (!empty($cats)): ?>
                <i class="ri-arrow-right-s-line"></i>
                <a href="<?php echo home_url('/blog/kategorie/' . $cats[0]->slug . '/'); ?>"><?php echo esc_html($cats[0]->name); ?></a>
            <?php endif; ?>
            <i class="ri-arrow-right-s-line"></i>
            <span><?php echo esc_html(wp_trim_words($title, 6)); ?></span>
        </nav>

        <article class="ppv-blog-article">
            <!-- Article Header -->
            <header class="ppv-blog-article-header">
                <div class="ppv-blog-article-meta">
                    <?php if (!empty($cats)): ?>
                        <a href="<?php echo home_url('/blog/kategorie/' . $cats[0]->slug . '/'); ?>" class="ppv-blog-article-cat"><?php echo esc_html($cats[0]->name); ?></a>
                    <?php endif; ?>
                    <span class="ppv-blog-article-date"><i class="ri-calendar-line"></i> <?php echo esc_html($date); ?></span>
                    <span class="ppv-blog-article-read"><i class="ri-time-line"></i> <?php echo $read_time; ?> Min. Lesezeit</span>
                </div>
                <h1 class="ppv-blog-article-title"><?php echo esc_html($title); ?></h1>
                <div class="ppv-blog-article-author">
                    <div class="ppv-blog-article-author-avatar">
                        <i class="ri-user-line"></i>
                    </div>
                    <span>Von <strong><?php echo esc_html($author_name); ?></strong></span>
                </div>
            </header>

            <!-- Featured Image -->
            <?php if ($thumbnail): ?>
            <div class="ppv-blog-article-hero">
                <img src="<?php echo esc_url($thumbnail); ?>"
                     alt="<?php echo esc_attr($title); ?>"
                     width="800" height="450">
            </div>
            <?php endif; ?>

            <!-- Article Body -->
            <div class="ppv-blog-article-body" id="ppvBlogBody">
                <?php echo $content; ?>
            </div>

            <!-- Tags -->
            <?php if (!empty($tags)): ?>
            <div class="ppv-blog-tags">
                <i class="ri-price-tag-3-line"></i>
                <?php foreach ($tags as $tag): ?>
                    <span class="ppv-blog-tag"><?php echo esc_html($tag->name); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Share -->
            <div class="ppv-blog-share">
                <span class="ppv-blog-share-label">Teilen:</span>
                <button class="ppv-blog-share-btn" data-share="facebook" title="Auf Facebook teilen" aria-label="Auf Facebook teilen">
                    <i class="ri-facebook-fill"></i>
                </button>
                <button class="ppv-blog-share-btn" data-share="twitter" title="Auf X teilen" aria-label="Auf X teilen">
                    <i class="ri-twitter-x-fill"></i>
                </button>
                <button class="ppv-blog-share-btn" data-share="linkedin" title="Auf LinkedIn teilen" aria-label="Auf LinkedIn teilen">
                    <i class="ri-linkedin-fill"></i>
                </button>
                <button class="ppv-blog-share-btn" data-share="whatsapp" title="Per WhatsApp teilen" aria-label="Per WhatsApp teilen">
                    <i class="ri-whatsapp-fill"></i>
                </button>
                <button class="ppv-blog-share-btn" data-share="copy" title="Link kopieren" aria-label="Link kopieren">
                    <i class="ri-link"></i>
                </button>
            </div>
        </article>

        <!-- CTA Banner -->
        <section class="ppv-blog-cta">
            <div class="ppv-blog-cta-inner">
                <h2>Bereit für Ihr eigenes Bonusprogramm?</h2>
                <p>Starten Sie jetzt kostenlos mit PunktePass und binden Sie Ihre Kunden dauerhaft.</p>
                <a href="<?php echo home_url('/signup'); ?>" class="ppv-blog-cta-btn">
                    Kostenlos starten <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </section>

        <!-- Related Posts -->
        <?php if (!empty($related)): ?>
        <section class="ppv-blog-related">
            <h2 class="ppv-blog-related-title">Weitere Artikel</h2>
            <div class="ppv-blog-related-grid">
                <?php
                foreach ($related as $rel_post) {
                    $GLOBALS['post'] = $rel_post;
                    setup_postdata($rel_post);
                    self::render_post_card(false);
                }
                wp_reset_postdata();
                ?>
            </div>
        </section>
        <?php endif; ?>
        <?php
    }

    /** Get related posts by category */
    private static function get_related_posts($post_id, $cats, $count = 3) {
        if (empty($cats)) return [];

        $cat_ids = array_map(function($c) { return $c->term_id; }, $cats);

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'post__not_in'   => [$post_id],
            'category__in'   => $cat_ids,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        return get_posts($args);
    }

    // =========================================
    // 404 PAGE
    // =========================================

    private static function render_404() {
        self::render_page_shell('Seite nicht gefunden', '', function() {
            ?>
            <div class="ppv-blog-empty ppv-blog-404">
                <i class="ri-error-warning-line"></i>
                <h1>Seite nicht gefunden</h1>
                <p>Der gesuchte Beitrag existiert leider nicht.</p>
                <a href="<?php echo home_url('/blog/'); ?>" class="ppv-blog-cta-btn">
                    <i class="ri-arrow-left-line"></i> Zum Blog
                </a>
            </div>
            <?php
        });
    }

    // =========================================
    // PAGE SHELL (Standalone HTML)
    // =========================================

    /** Render full standalone HTML page */
    private static function render_page_shell($title, $seo_head, $content_callback) {
        $plugin_url = PPV_PLUGIN_URL;
        $v = PPV_Core::asset_version();
        $site_url = home_url();
        $lang = class_exists('PPV_Lang') ? PPV_Lang::current() : 'de';

        // Disable WP caching for standalone
        ppv_disable_wp_optimization();

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?></title>

    <?php echo $seo_head; ?>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">

    <!-- PunktePass Blog CSS -->
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-blog.css?v=<?php echo $v; ?>">

    <!-- Favicons -->
    <?php echo PPV_SEO::get_favicon_links(); ?>

    <!-- RSS Feed Discovery -->
    <link rel="alternate" type="application/rss+xml" title="PunktePass Blog RSS" href="<?php echo $site_url; ?>/feed/" />
    <link rel="alternate" type="application/atom+xml" title="PunktePass Blog Atom" href="<?php echo $site_url; ?>/feed/atom/" />

    <!-- Blog Sitemap -->
    <link rel="sitemap" type="application/xml" title="Blog Sitemap" href="<?php echo $site_url; ?>/blog-sitemap.xml" />

    <link rel="manifest" href="<?php echo $site_url; ?>/manifest.json">
    <meta name="theme-color" content="#0f172a">
</head>
<body class="ppv-blog-page">

    <!-- Navigation -->
    <header class="ppv-blog-nav">
        <div class="ppv-blog-nav-inner">
            <a href="<?php echo $site_url; ?>" class="ppv-blog-nav-logo" aria-label="PunktePass Home">
                <span class="ppv-blog-nav-logo-icon">P</span>
                <span class="ppv-blog-nav-logo-text">PunktePass</span>
            </a>
            <nav class="ppv-blog-nav-links">
                <a href="<?php echo home_url('/blog/'); ?>">Blog</a>
                <a href="<?php echo home_url('/login'); ?>" class="ppv-blog-nav-cta">Anmelden</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="ppv-blog-main">
        <?php $content_callback(); ?>
    </main>

    <!-- Footer -->
    <footer class="ppv-blog-footer">
        <div class="ppv-blog-footer-inner">
            <div class="ppv-blog-footer-brand">
                <span class="ppv-blog-nav-logo-icon">P</span>
                <span class="ppv-blog-footer-name">PunktePass</span>
                <p class="ppv-blog-footer-tagline">Digitales Bonusprogramm für lokale Geschäfte</p>
            </div>
            <div class="ppv-blog-footer-links">
                <a href="<?php echo home_url('/impressum'); ?>">Impressum</a>
                <a href="<?php echo home_url('/datenschutz'); ?>">Datenschutz</a>
                <a href="<?php echo home_url('/agb'); ?>">AGB</a>
                <a href="<?php echo home_url('/kontakt'); ?>">Kontakt</a>
            </div>
            <p class="ppv-blog-footer-copy">&copy; <?php echo date('Y'); ?> PunktePass. Alle Rechte vorbehalten.</p>
        </div>
    </footer>

    <!-- Blog JS -->
    <script src="<?php echo $plugin_url; ?>assets/js/ppv-blog.js?v=<?php echo $v; ?>" defer></script>
</body>
</html>
<?php
    }

    // =========================================
    // SEO HELPERS
    // =========================================

    /** Build listing page SEO tags */
    private static function build_listing_seo($title, $description, $canonical, $page) {
        $og_image = PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png';

        if ($page > 1) {
            $title .= " - Seite {$page}";
        }

        $html = PPV_SEO::get_performance_hints();

        $html .= "\n    <!-- SEO Meta Tags -->\n";
        $html .= '    <meta name="description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '    <meta name="keywords" content="Bonusprogramm, Kundenbindung, Treuepunkte, lokale Geschäfte, Einzelhandel, QR-Code, PunktePass Blog">' . "\n";
        $html .= '    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">' . "\n";
        $html .= '    <link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        $html .= '    <meta name="author" content="PunktePass">' . "\n";

        // Open Graph
        $html .= "\n    <!-- Open Graph -->\n";
        $html .= '    <meta property="og:type" content="website">' . "\n";
        $html .= '    <meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
        $html .= '    <meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        $html .= '    <meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '    <meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        $html .= '    <meta property="og:site_name" content="PunktePass">' . "\n";
        $html .= '    <meta property="og:locale" content="de_DE">' . "\n";

        // Twitter
        $html .= "\n    <!-- Twitter Card -->\n";
        $html .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '    <meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        $html .= '    <meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '    <meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";

        // JSON-LD: Blog structured data
        $blog_ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => 'PunktePass Blog',
            'url' => home_url('/blog/'),
            'description' => $description,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'PunktePass',
                'url' => home_url(),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png',
                ],
            ],
            'inLanguage' => 'de-DE',
        ];

        $html .= "\n    <!-- Structured Data -->\n";
        $html .= '    <script type="application/ld+json">' . json_encode($blog_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        // Breadcrumb LD
        $breadcrumb_items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url()],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => home_url('/blog/')],
        ];

        $breadcrumb_ld = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumb_items,
        ];

        $html .= '    <script type="application/ld+json">' . json_encode($breadcrumb_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        return $html;
    }

    /** Build single post SEO tags */
    private static function build_single_seo($title, $description, $canonical, $image, $author, $date_iso, $modified_iso) {
        $og_image = $image ?: PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png';
        $description = $description ?: wp_trim_words(get_the_content(), 25);

        $html = PPV_SEO::get_performance_hints();

        $html .= "\n    <!-- SEO Meta Tags -->\n";
        $html .= '    <meta name="description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">' . "\n";
        $html .= '    <link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        $html .= '    <meta name="author" content="' . esc_attr($author) . '">' . "\n";

        // Article meta
        $html .= '    <meta property="article:published_time" content="' . esc_attr($date_iso) . '">' . "\n";
        $html .= '    <meta property="article:modified_time" content="' . esc_attr($modified_iso) . '">' . "\n";
        $html .= '    <meta property="article:author" content="' . esc_attr($author) . '">' . "\n";

        // Open Graph
        $html .= "\n    <!-- Open Graph -->\n";
        $html .= '    <meta property="og:type" content="article">' . "\n";
        $html .= '    <meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
        $html .= '    <meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        $html .= '    <meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '    <meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        $html .= '    <meta property="og:site_name" content="PunktePass">' . "\n";
        $html .= '    <meta property="og:locale" content="de_DE">' . "\n";

        // Twitter Card
        $html .= "\n    <!-- Twitter Card -->\n";
        $html .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '    <meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        $html .= '    <meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '    <meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";

        // JSON-LD: Article
        $article_ld = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $description,
            'url' => $canonical,
            'datePublished' => $date_iso,
            'dateModified' => $modified_iso,
            'author' => [
                '@type' => 'Person',
                'name' => $author,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'PunktePass',
                'url' => home_url(),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png',
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
            'inLanguage' => 'de-DE',
        ];

        if ($image) {
            $article_ld['image'] = [
                '@type' => 'ImageObject',
                'url' => $image,
            ];
        }

        $html .= "\n    <!-- Structured Data -->\n";
        $html .= '    <script type="application/ld+json">' . json_encode($article_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        // Breadcrumb LD
        $breadcrumb_ld = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url()],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => home_url('/blog/')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonical],
            ],
        ];

        $html .= '    <script type="application/ld+json">' . json_encode($breadcrumb_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        return $html;
    }

    // =========================================
    // UTILITIES
    // =========================================

    /** Estimate reading time in minutes */
    private static function estimate_read_time($content) {
        $word_count = str_word_count(strip_tags($content));
        return max(1, ceil($word_count / 200));
    }

    // =========================================
    // BLOG SITEMAP (XML)
    // =========================================

    /** Handle /blog-sitemap.xml request */
    public static function handle_sitemap() {
        if (!get_query_var('ppv_blog_sitemap')) return;

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: public, max-age=3600');

        $site_url = home_url();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"' . "\n";
        echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        // Blog main page
        echo '  <url>' . "\n";
        echo '    <loc>' . esc_url($site_url . '/blog/') . '</loc>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>0.9</priority>' . "\n";
        echo '  </url>' . "\n";

        // Category pages
        $categories = get_categories(['hide_empty' => true]);
        foreach ($categories as $cat) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url($site_url . '/blog/kategorie/' . $cat->slug . '/') . '</loc>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.7</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        // All published posts
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        foreach ($posts as $p) {
            $post_url = $site_url . '/blog/' . $p->post_name . '/';
            $modified = get_the_modified_date('c', $p->ID);
            $thumbnail = get_the_post_thumbnail_url($p->ID, 'large');

            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url($post_url) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html($modified) . '</lastmod>' . "\n";
            echo '    <changefreq>monthly</changefreq>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";

            // Image sitemap tag (helps Google Images)
            if ($thumbnail) {
                echo '    <image:image>' . "\n";
                echo '      <image:loc>' . esc_url($thumbnail) . '</image:loc>' . "\n";
                echo '      <image:title>' . esc_html($p->post_title) . '</image:title>' . "\n";
                echo '    </image:image>' . "\n";
            }

            // News sitemap for posts < 2 days old
            $post_age_days = (time() - strtotime($p->post_date_gmt)) / 86400;
            if ($post_age_days <= 2) {
                echo '    <news:news>' . "\n";
                echo '      <news:publication>' . "\n";
                echo '        <news:name>PunktePass</news:name>' . "\n";
                echo '        <news:language>de</news:language>' . "\n";
                echo '      </news:publication>' . "\n";
                echo '      <news:publication_date>' . get_the_date('c', $p->ID) . '</news:publication_date>' . "\n";
                echo '      <news:title>' . esc_html($p->post_title) . '</news:title>' . "\n";
                echo '    </news:news>' . "\n";
            }

            echo '  </url>' . "\n";
        }

        echo '</urlset>';
        exit;
    }

    // =========================================
    // AUTO-PING ON PUBLISH (Google, Bing, IndexNow)
    // =========================================

    /**
     * When a post is published, notify search engines immediately.
     * - Ping Google & Bing sitemaps
     * - Submit to IndexNow (Bing, Yandex, etc.)
     */
    public static function on_post_publish($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_type !== 'post') return;
        if ($post->post_status !== 'publish') return;

        // Avoid double-ping (use transient as lock)
        $lock_key = 'ppv_blog_ping_' . $post_id;
        if (get_transient($lock_key)) return;
        set_transient($lock_key, 1, 300); // 5 min lock

        $post_url = home_url('/blog/' . $post->post_name . '/');
        $sitemap_url = home_url('/blog-sitemap.xml');

        // Run pings asynchronously
        self::ping_google($sitemap_url);
        self::ping_bing($sitemap_url);
        self::ping_indexnow($post_url);

        ppv_log("[PPV_Blog] Pinged search engines for: {$post_url}");
    }

    /**
     * On post update, re-ping if it was already published
     */
    public static function on_post_update($post_id, $post) {
        if (!is_object($post)) {
            $post = get_post($post_id);
        }
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') return;

        // Only re-ping if content actually changed (compare modified vs published date)
        $modified = strtotime($post->post_modified_gmt);
        $published = strtotime($post->post_date_gmt);
        if (($modified - $published) < 10) return; // Skip initial publish (handled by on_post_publish)

        $lock_key = 'ppv_blog_update_ping_' . $post_id;
        if (get_transient($lock_key)) return;
        set_transient($lock_key, 1, 600); // 10 min lock

        $post_url = home_url('/blog/' . $post->post_name . '/');
        self::ping_indexnow($post_url);

        ppv_log("[PPV_Blog] IndexNow re-ping for updated: {$post_url}");
    }

    /**
     * Ping Google Sitemap
     * Google officially deprecated the ping endpoint in 2023,
     * but we also ping the sitemap to ensure discovery.
     */
    private static function ping_google($sitemap_url) {
        $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);
        wp_remote_get($ping_url, [
            'timeout'   => 5,
            'blocking'  => false,
            'sslverify' => true,
        ]);
    }

    /**
     * Ping Bing Sitemap
     */
    private static function ping_bing($sitemap_url) {
        $ping_url = 'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url);
        wp_remote_get($ping_url, [
            'timeout'   => 5,
            'blocking'  => false,
            'sslverify' => true,
        ]);
    }

    /**
     * IndexNow - Instant URL submission to Bing, Yandex, Seznam, Naver
     * Uses Bing as the endpoint. Key is auto-generated and stored.
     *
     * @see https://www.indexnow.org/documentation
     */
    private static function ping_indexnow($url) {
        $key = self::get_indexnow_key();
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        $payload = [
            'host'    => $host,
            'key'     => $key,
            'urlList' => [$url],
        ];

        wp_remote_post('https://api.indexnow.org/indexnow', [
            'timeout'   => 5,
            'blocking'  => false,
            'sslverify' => true,
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => json_encode($payload),
        ]);
    }

    /**
     * Get or create the IndexNow API key.
     * Key must also be served at /{key}.txt on the domain.
     */
    private static function get_indexnow_key() {
        $key = get_option('ppv_indexnow_key');
        if (!$key) {
            $key = wp_generate_password(32, false, false);
            update_option('ppv_indexnow_key', $key, true);
        }
        return $key;
    }

    /**
     * Serve the IndexNow key verification file.
     * Called from init hook to handle /{key}.txt requests.
     */
    public static function serve_indexnow_key() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH);
        $key = get_option('ppv_indexnow_key');

        if ($key && $path === '/' . $key . '.txt') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo $key;
            exit;
        }
    }
}
