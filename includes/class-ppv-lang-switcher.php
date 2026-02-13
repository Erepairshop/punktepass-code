<?php
if (!defined('ABSPATH')) exit;

class PPV_Lang_Switcher {

    /**
     * Render language switcher (formular-style design)
     * Uses native <select> so it works inside overflow:hidden headers
     */
    public static function render() {
        $langs = ['de', 'en', 'hu', 'ro'];
        $current = PPV_Lang::$active ?? 'de';

        ob_start(); ?>
        <div class="ppv-lang-wrap">
            <i class="ri-global-line ppv-lang-icon"></i>
            <select class="ppv-lang-select" id="ppv-lang-select">
                <?php foreach ($langs as $lc): ?>
                    <option value="<?php echo $lc; ?>" <?php echo $current === $lc ? 'selected' : ''; ?>><?php echo strtoupper($lc); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <style>
        .ppv-lang-wrap{position:relative;display:inline-flex;align-items:center}
        .ppv-lang-icon{position:absolute;left:8px;font-size:14px;color:inherit;pointer-events:none;z-index:1}
        .ppv-lang-select{-webkit-appearance:none;appearance:none;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:5px 24px 5px 26px;font-size:12px;font-weight:700;color:inherit;cursor:pointer;font-family:inherit;letter-spacing:.5px;transition:all .2s;outline:none}
        .ppv-lang-select:hover{background:rgba(255,255,255,.18)}
        .ppv-lang-select:focus{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.35)}
        .ppv-lang-select option{background:#1a1a2e;color:#e0e0e0}
        </style>

        <script>
        (function(){
            var sel=document.getElementById('ppv-lang-select');
            if(!sel||sel.dataset.init)return;
            sel.dataset.init='1';
            sel.addEventListener('change',function(){
                var lang=this.value;
                var maxAge=60*60*24*365;
                document.cookie='ppv_lang='+lang+';path=/;max-age='+maxAge+';SameSite=Lax';
                document.cookie='ppv_lang_manual=1;path=/;max-age='+maxAge+';SameSite=Lax';
                try{localStorage.setItem('ppv_lang',lang)}catch(e){}
                var url=new URL(window.location.href);
                url.searchParams.set('lang',lang);
                if(window.Turbo){window.Turbo.visit(url.toString(),{action:'replace'})}
                else{window.location.href=url.toString()}
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
