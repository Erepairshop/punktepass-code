<?php
/**
 * PunktePass - Widget Demo/Test Page
 * Route: /formular/widget-demo?code=PP-XXXXXX
 * Shows the widget on a simulated website to test appearance
 */

if (!defined('ABSPATH')) exit;

class PPV_Widget_Demo {

    public static function render() {
        $code = sanitize_text_field($_GET['code'] ?? 'PP-DEMO01');
        $mode = sanitize_text_field($_GET['mode'] ?? 'float');
        $lang = sanitize_text_field($_GET['lang'] ?? 'en');
        $pos  = sanitize_text_field($_GET['position'] ?? 'bottom-right');
        $color = sanitize_text_field($_GET['color'] ?? '#667eea');

        // Validate color
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#667eea';

        ob_start();
        ?>
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Widget Demo - PunktePass</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<meta name="robots" content="noindex,nofollow">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,-apple-system,sans-serif;background:#f8fafc;color:#0f172a;min-height:100vh}
.demo-toolbar{position:fixed;top:0;left:0;right:0;z-index:99999;background:#1e293b;color:#fff;padding:12px 24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
.demo-toolbar a{color:#94a3b8;text-decoration:none;font-size:13px}
.demo-toolbar a:hover{color:#fff}
.demo-toolbar .demo-title{font-weight:700;font-size:15px;color:#22c55e;display:flex;align-items:center;gap:6px}
.demo-toolbar select,.demo-toolbar input[type="color"]{padding:4px 8px;border:1px solid #475569;border-radius:6px;background:#334155;color:#fff;font-size:12px;font-family:inherit}
.demo-toolbar input[type="color"]{width:32px;height:28px;padding:2px;cursor:pointer}
.demo-toolbar label{font-weight:600;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px}
.demo-ctrl{display:flex;align-items:center;gap:6px}
.demo-reload{background:#22c55e;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;font-family:inherit}
.demo-reload:hover{background:#16a34a}

/* Simulated website content */
.demo-site{margin-top:60px;padding:40px}
.demo-hero{max-width:900px;margin:0 auto 40px;text-align:center}
.demo-hero h1{font-size:36px;font-weight:800;color:#1e293b;margin-bottom:12px}
.demo-hero p{font-size:18px;color:#64748b;line-height:1.6}
.demo-grid{max-width:900px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
.demo-card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
.demo-card h3{font-size:16px;margin-bottom:8px;color:#374151}
.demo-card p{font-size:14px;color:#6b7280;line-height:1.5}
.demo-card-icon{font-size:32px;margin-bottom:12px}
.demo-inline-area{max-width:900px;margin:30px auto;display:flex;justify-content:center}
.demo-footer{max-width:900px;margin:60px auto 0;padding:24px;text-align:center;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:13px}
@media(max-width:768px){.demo-grid{grid-template-columns:1fr}.demo-site{padding:20px}.demo-hero h1{font-size:24px}.demo-toolbar{padding:10px 12px}}
</style></head><body>

<div class="demo-toolbar">
    <a href="/formular/admin/partners">&larr;</a>
    <span class="demo-title"><i class="ri-eye-line"></i> Widget Demo</span>
    <div style="flex:1"></div>
    <div class="demo-ctrl"><label>Modus</label><select id="d-mode"><option value="catalog"<?php echo $mode==='catalog'?' selected':''; ?>>Katalog</option><option value="float"<?php echo $mode==='float'?' selected':''; ?>>Float</option><option value="inline"<?php echo $mode==='inline'?' selected':''; ?>>Inline</option></select></div>
    <div class="demo-ctrl"><label>Sprache</label><select id="d-lang"><option value="de"<?php echo $lang==='de'?' selected':''; ?>>DE</option><option value="en"<?php echo $lang==='en'?' selected':''; ?>>EN</option></select></div>
    <div class="demo-ctrl"><label>Position</label><select id="d-pos"><option value="bottom-right"<?php echo $pos==='bottom-right'?' selected':''; ?>>Rechts</option><option value="bottom-left"<?php echo $pos==='bottom-left'?' selected':''; ?>>Links</option></select></div>
    <div class="demo-ctrl"><label>Farbe</label><input type="color" id="d-color" value="<?php echo esc_attr($color); ?>"></div>
    <div class="demo-ctrl"><label>Code</label><span style="font-family:monospace;color:#fbbf24;font-weight:700"><?php echo esc_html($code); ?></span></div>
    <button class="demo-reload" onclick="reloadDemo()"><i class="ri-refresh-line"></i> Aktualisieren</button>
</div>

<div class="demo-site">
    <div class="demo-hero">
        <h1>Beispiel Webshop</h1>
        <p>Dies ist eine simulierte Partner-Website, um das PunktePass Widget zu testen. So sieht es auf einer echten Seite aus.</p>
    </div>

    <div class="demo-grid">
        <div class="demo-card"><div class="demo-card-icon">&#128268;</div><h3>Smartphone Reparatur</h3><p>Display, Akku, Kamera - wir reparieren alle Marken schnell und professionell.</p></div>
        <div class="demo-card"><div class="demo-card-icon">&#128187;</div><h3>Laptop Service</h3><p>Mainboard Reparatur, SSD Upgrade, Virus-Entfernung und mehr.</p></div>
        <div class="demo-card"><div class="demo-card-icon">&#127918;</div><h3>Konsolen Reparatur</h3><p>PlayStation, Xbox, Nintendo Switch - HDMI Port, Laufwerk, Drift Fix.</p></div>
    </div>

    <div class="demo-inline-area" id="punktepass-widget"></div>

    <div class="demo-footer">
        &copy; 2026 Beispiel Webshop GmbH &middot; Alle Rechte vorbehalten
    </div>
</div>

<script>
function reloadDemo() {
    var mode = document.getElementById('d-mode').value;
    var lang = document.getElementById('d-lang').value;
    var pos = document.getElementById('d-pos').value;
    var color = encodeURIComponent(document.getElementById('d-color').value);
    var code = <?php echo json_encode($code); ?>;
    window.location.href = '/formular/widget-demo?code=' + code + '&mode=' + mode + '&lang=' + lang + '&position=' + pos + '&color=' + color;
}
</script>

<!-- Actual widget embed -->
<script
    src="/formular/widget.js"
    data-partner="<?php echo esc_attr($code); ?>"
    data-mode="<?php echo esc_attr($mode); ?>"
    data-lang="<?php echo esc_attr($lang); ?>"
    data-position="<?php echo esc_attr($pos); ?>"
    data-color="<?php echo esc_attr($color); ?>"
    <?php if ($mode === 'inline') echo 'data-target="#punktepass-widget"'; ?>
></script>

</body></html>
        <?php
        echo ob_get_clean();
        exit;
    }
}
