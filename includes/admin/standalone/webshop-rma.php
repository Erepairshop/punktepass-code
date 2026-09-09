<?php
/** Read-only overview of eRepairShop return requests. */

if (!defined('ABSPATH')) exit;

final class PPV_Standalone_Webshop_RMA {
    private const SHOP_URL = 'https://shop.erepairshop.de/?ers-rma-snapshot=1';

    public static function render(): void {
        $result = self::fetch();
        $rmas = is_array($result['rmas'] ?? null) ? $result['rmas'] : [];
        $filter = sanitize_key($_GET['status'] ?? 'open');
        $allowed = ['open', 'pending', 'approved', 'received', 'completed', 'rejected', 'all'];
        if (!in_array($filter, $allowed, true)) $filter = 'open';
        $shown = array_values(array_filter($rmas, static function (array $rma) use ($filter): bool {
            $status = (string) ($rma['status'] ?? '');
            if ($filter === 'all') return true;
            if ($filter === 'open') return in_array($status, ['pending', 'approved', 'received'], true);
            return $status === $filter;
        }));
        $counts = array_count_values(array_map(static fn(array $rma): string => (string) ($rma['status'] ?? ''), $rmas));

        PPV_Standalone_Admin::get_admin_header('webshop-rma');
        self::styles();
        ?>
        <div class="rma-head">
            <div><h1 class="page-title"><i class="ri-arrow-go-back-line"></i> Webshop RMA-k</h1><p>eRepairShop visszaküldési kérelmek. Jóváhagyás a shop adminban történik.</p></div>
            <a class="rma-refresh" href="/admin/webshop-rma"><i class="ri-refresh-line"></i> Frissítés</a>
        </div>
        <?php if (!empty($result['error'])): ?>
            <div class="rma-error"><?php echo esc_html($result['error']); ?></div>
        <?php else: ?>
            <div class="rma-stats">
                <div><strong><?php echo (int) (($counts['pending'] ?? 0) + ($counts['approved'] ?? 0) + ($counts['received'] ?? 0)); ?></strong><span>Nyitott</span></div>
                <div><strong><?php echo (int) ($counts['pending'] ?? 0); ?></strong><span>Új elbírálásra</span></div>
                <div><strong><?php echo (int) ($counts['approved'] ?? 0); ?></strong><span>Jóváhagyva</span></div>
                <div><strong><?php echo count($rmas); ?></strong><span>Összes RMA</span></div>
            </div>
            <nav class="rma-filters">
                <?php foreach (['open' => 'Nyitott', 'pending' => 'Új', 'approved' => 'Jóváhagyva', 'received' => 'Beérkezett', 'completed' => 'Lezárt', 'rejected' => 'Elutasított', 'all' => 'Mind'] as $key => $label): ?>
                    <a class="<?php echo $filter === $key ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('status', $key, '/admin/webshop-rma')); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="rma-list">
                <?php if (!$shown): ?><div class="rma-empty">Nincs ebben a nézetben RMA-kérelem.</div><?php endif; ?>
                <?php foreach ($shown as $rma):
                    $status = sanitize_key($rma['status'] ?? 'pending');
                    $resolutionKey = sanitize_key($rma['resolutionKey'] ?? '');
                    $resolutionLabel = (string) ($rma['resolution'] ?? 'Nicht angegeben');
                    if ($resolutionKey === '') {
                        if (stripos($resolutionLabel, 'erstatt') !== false) $resolutionKey = 'refund';
                        elseif (stripos($resolutionLabel, 'ersatz') !== false || stripos($resolutionLabel, 'umtausch') !== false) $resolutionKey = 'replacement';
                    }
                    $wishLabel = $resolutionKey === 'refund' ? 'Geld zurück' : ($resolutionKey === 'replacement' ? 'Umtausch / Ersatz' : $resolutionLabel);
                ?>
                    <article class="rma-card rma-card--<?php echo esc_attr($status); ?>">
                        <header><div><strong><?php echo esc_html($rma['number'] ?? 'RMA'); ?></strong><span class="rma-status"><?php echo esc_html($rma['statusLabel'] ?? $status); ?></span></div><time><?php echo esc_html(self::date($rma['requestedAt'] ?? '')); ?></time></header>
                        <div class="rma-wish rma-wish--<?php echo esc_attr($resolutionKey ?: 'other'); ?>"><small>Kundenwunsch</small><strong><?php echo esc_html($wishLabel); ?></strong></div>
                        <div class="rma-grid">
                            <div><small>Rendelés és ügyfél</small><b>#<?php echo esc_html($rma['orderNumber'] ?? ''); ?></b><span><?php echo esc_html($rma['customerName'] ?? ''); ?></span><a href="mailto:<?php echo esc_attr($rma['customerEmail'] ?? ''); ?>"><?php echo esc_html($rma['customerEmail'] ?? ''); ?></a></div>
                            <div><small>Termék</small><b><?php echo esc_html($rma['itemName'] ?? ''); ?></b><span>SKU: <?php echo esc_html($rma['itemSku'] ?? ''); ?></span><span>Mennyiség: <?php echo (int) ($rma['quantity'] ?? 0); ?></span></div>
                            <div><small>Ok és kérés</small><b><?php echo esc_html($rma['reason'] ?? ''); ?></b><span><?php echo esc_html($rma['resolution'] ?? ''); ?></span></div>
                        </div>
                        <?php if (!empty($rma['details'])): ?><p class="rma-details"><?php echo nl2br(esc_html($rma['details'])); ?></p><?php endif; ?>
                        <footer><span>Állapot frissítve: <?php echo esc_html(self::date($rma['changedAt'] ?? '') ?: 'még nem'); ?></span><a target="_blank" rel="noopener" href="<?php echo esc_url($rma['adminUrl'] ?? ''); ?>">Megnyitás a shop adminban</a></footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php PPV_Standalone_Admin::get_admin_footer();
    }

    private static function fetch(): array {
        if (!defined('ERS_PPV_BRIDGE_SECRET') || strlen((string) ERS_PPV_BRIDGE_SECRET) < 32) return ['error' => 'Az RMA kapcsolat nincs beállítva.'];
        $body = '{}';
        $timestamp = (string) time();
        $response = wp_remote_post(self::SHOP_URL, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-ERS-Timestamp' => $timestamp,
                'X-ERS-Signature' => hash_hmac('sha256', $timestamp . "\n" . $body, (string) ERS_PPV_BRIDGE_SECRET),
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) return ['error' => 'A shop RMA adatai most nem érhetők el.'];
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        if ((int) wp_remote_retrieve_response_code($response) !== 200 || empty($payload['ok'])) return ['error' => 'A shop RMA adatai most nem érhetők el.'];
        return ['rmas' => $payload['rmas'] ?? []];
    }

    private static function date($value): string {
        $time = $value ? strtotime((string) $value) : false;
        return $time ? date_i18n('Y. m. d. H:i', $time) : '';
    }

    private static function styles(): void { ?>
        <style>
        .rma-head,.rma-card header,.rma-card footer{display:flex;justify-content:space-between;align-items:center;gap:12px}.rma-head p,.rma-card time,.rma-card footer>span{color:#91a0b8;font-size:13px}.rma-refresh,.rma-card footer a{border-radius:10px;background:#00d4ea;color:#06161c;padding:10px 14px;text-decoration:none;font-weight:800}.rma-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:20px 0}.rma-stats>div,.rma-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:17px}.rma-stats strong{display:block;color:#00e6ff;font-size:27px}.rma-stats span,.rma-grid small{color:#91a0b8;font-size:12px}.rma-filters{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.rma-filters a{padding:9px 12px;color:#b7c3d6;text-decoration:none;border:1px solid rgba(255,255,255,.13);border-radius:9px;font-weight:700}.rma-filters a.active{background:#00d4ea;color:#06161c}.rma-list{display:grid;gap:12px}.rma-card{border-left:4px solid #f0aa25}.rma-status{margin-left:9px;padding:4px 8px;border-radius:20px;background:rgba(0,212,234,.13);color:#83efff;font-size:11px}.rma-wish{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;padding:12px 14px;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:rgba(0,212,234,.09)}.rma-wish small{color:#91a0b8;text-transform:uppercase;letter-spacing:.08em;font-weight:800}.rma-wish strong{font-size:17px;color:#fff}.rma-wish--refund{border-color:rgba(44,200,116,.5);background:rgba(44,200,116,.12)}.rma-wish--replacement{border-color:rgba(0,212,234,.5)}.rma-grid{display:grid;grid-template-columns:1.2fr 1.5fr 1fr;gap:15px;margin-top:15px}.rma-grid div{display:flex;flex-direction:column;gap:4px;overflow-wrap:anywhere}.rma-grid span,.rma-grid a{color:#b6c2d4;font-size:13px}.rma-details,.rma-empty,.rma-error{margin-top:14px;padding:14px;border-radius:10px;background:rgba(0,0,0,.16);color:#d8e0eb}.rma-card footer{margin-top:14px}@media(max-width:800px){.rma-stats{grid-template-columns:repeat(2,1fr)}.rma-grid{grid-template-columns:1fr}.rma-card header,.rma-card footer{align-items:flex-start;flex-direction:column}.rma-wish{align-items:flex-start;flex-direction:column}}
        </style>
    <?php }
}
