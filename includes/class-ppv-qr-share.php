<?php
if (!defined('ABSPATH')) exit;

class PPV_QR_Share {

    public static function render_share($store) {
        if (!$store) {
            echo "<p>⚠️ Kein Store gefunden.</p>";
            return;
        }

       // ✅ Helyes mezők a Datenbank alapján
$store_name = isset($store->name) && $store->name !== '' ? $store->name : ($store->company_name ?? '');
$store_key  = isset($store->store_key) && $store->store_key !== '' ? $store->store_key : sanitize_title($store_name);

// 🔹 Store URL
$store_url = esc_url(home_url('/store/' . $store_key));


        // 🔹 QR generálása
        // 🔹 QR generálása a PunktePass rendszer saját generátorával
if (class_exists('PPV_QR_Generator')) {
    $qr_data = PPV_QR_Generator::generate_qr($store);
    $qr_img = is_array($qr_data) ? ($qr_data['img'] ?? '') : $qr_data;
    ppv_log('🌀 Teilen QR generated internally for store_id=' . $store->id);
} else {
    $qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . rawurlencode($store_url);
    ppv_log('⚠️ Fallback external QR API used for store_id=' . $store->id);
}


        // 🔹 Dinamikus share szöveg
        $share_text = urlencode("Sammle Punkte in meinem Geschäft bei PunktePass und erhalte Belohnungen! 👉 {$store_url}");
        ?>

        <div class="ppv-share-container futuristic">
            <h3>🔗 Teile deinen Store-Link</h3>
            <p class="ppv-share-subtext">
                Erhöhe deine Sichtbarkeit durch einfaches Teilen deines QR-Codes oder Store-Links.
            </p>

            <div class="ppv-share-section">
                <div class="ppv-share-qr">
                    <img src="<?php echo esc_url($qr_img . '?v=' . time()); ?>" 
                         alt="Store QR" 
                         class="ppv-share-qr-img"
                         title="Scanne diesen QR, um dein Store-Profil zu öffnen.">
                    <a href="<?php echo esc_url($qr_img); ?>" 
                       download="store-<?php echo esc_attr($store->id); ?>-qr.png" 
                       class="ppv-btn neon mt-2">📥 QR herunterladen</a>
                </div>

                <div class="ppv-share-links">
                    <label for="ppv-store-link">Store-Link:</label>
                    <div class="ppv-copy-wrapper">
                        <input type="text" 
                               id="ppv-store-link" 
                               readonly 
                               value="<?php echo esc_attr($store_url); ?>">
                        <button type="button" 
                                id="ppv-copy-store-link" 
                                class="ppv-btn neon">Link kopieren</button>
                    </div>
                    <div id="ppv-copy-msg" 
                         class="ppv-copy-msg" 
                         style="display:none;">✅ Link kopiert!</div>

                    <div class="ppv-social-share">
                        <p>Teile auf:</p>
                        <div class="ppv-social-buttons">
                            <!-- Facebook -->
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($store_url); ?>" 
                               target="_blank" 
                               class="ppv-social fb">Facebook</a>

                            <!-- WhatsApp -->
                            <a href="https://wa.me/?text=<?php echo $share_text; ?>" 
                               target="_blank" 
                               class="ppv-social wa">WhatsApp</a>

                            <!-- SMS -->
                            <a href="sms:?body=<?php echo $share_text; ?>" 
                               class="ppv-social sms">SMS</a>

                            <!-- E-Mail -->
                            <a href="mailto:?subject=<?php echo rawurlencode('Schau dir ' . $store_name . ' an!'); ?>&body=<?php echo rawurlencode('Hier ist der Link: ' . $store_url); ?>" 
                               class="ppv-social mail">E-Mail</a>

                            <!-- Instagram (copy fallback) -->
                            <button type="button" 
                                    id="ppv-instagram-share" 
                                    class="ppv-social ig">Instagram</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btnCopy = document.getElementById('ppv-copy-store-link');
            const input = document.getElementById('ppv-store-link');
            const msg = document.getElementById('ppv-copy-msg');
            const instaBtn = document.getElementById('ppv-instagram-share');

            // 🔹 Link kopieren
            if(btnCopy && input){
                btnCopy.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(input.value);
                        msg.style.display = 'block';
                        setTimeout(() => msg.style.display = 'none', 2000);
                    } catch(e) {
                        alert('Kopieren fehlgeschlagen.');
                    }
                });
            }

            // 🔹 Instagram fallback (copy + Hinweis)
            if(instaBtn && input){
                instaBtn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(input.value);
                        const toast = document.createElement('div');
                        toast.className = 'ppv-toast success';
                        toast.innerText = '📋 Link kopiert — füge ihn in deiner Instagram-Bio ein!';
                        document.body.appendChild(toast);
                        setTimeout(() => toast.classList.add('show'), 50);
                        setTimeout(() => {
                            toast.classList.remove('show');
                            setTimeout(() => toast.remove(), 400);
                        }, 3000);
                    } catch(e) {
                        alert('Kopieren fehlgeschlagen.');
                    }
                });
            }
        });
        </script>

        <style>
        .ppv-social-buttons { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .ppv-social { padding:6px 12px; border-radius:8px; text-decoration:none; color:#fff; font-weight:500; }
        .ppv-social.fb { background:#1877F2; }
        .ppv-social.wa { background:#25D366; }
        .ppv-social.sms { background:#00bcd4; }
        .ppv-social.mail { background:#FF9800; }
        .ppv-social.ig { background:#C13584; border:none; cursor:pointer; }
        .ppv-toast { position:fixed; bottom:30px; left:50%; transform:translateX(-50%);
                     background:rgba(0,0,0,0.8); color:#0ff; padding:10px 20px; border-radius:10px;
                     opacity:0; transition:0.3s; z-index:9999; }
        .ppv-toast.show { opacity:1; }
        </style>

        <?php
    }
}
