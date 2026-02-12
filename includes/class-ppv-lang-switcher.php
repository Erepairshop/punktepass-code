<?php
if (!defined('ABSPATH')) exit;

class PPV_Lang_Switcher {

    /**
     * Render language switcher (formular-style design)
     * Used in main system: login, signup, profile, dashboard header
     * Uses position:fixed dropdown to work inside overflow:hidden headers
     */
    public static function render() {
        $langs = ['de', 'en', 'hu', 'ro'];
        $current = PPV_Lang::$active ?? 'de';

        ob_start(); ?>
        <div class="ppv-lang-wrap" id="ppv-lang-wrap">
            <button type="button" class="ppv-lang-toggle" id="ppv-lang-toggle-btn">
                <i class="ri-global-line"></i> <?php echo strtoupper($current); ?>
            </button>
            <div class="ppv-lang-opts" id="ppv-lang-opts">
                <?php foreach ($langs as $lc): ?>
                    <button type="button" class="ppv-lang-opt <?php echo $current === $lc ? 'active' : ''; ?>" data-lang="<?php echo $lc; ?>"><?php echo strtoupper($lc); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
        .ppv-lang-wrap{position:relative;display:inline-block}
        .ppv-lang-toggle{display:flex;align-items:center;gap:4px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:5px 10px;font-size:12px;font-weight:700;color:inherit;cursor:pointer;font-family:inherit;transition:all .2s;letter-spacing:.5px}
        .ppv-lang-toggle:hover{background:rgba(255,255,255,.18)}
        .ppv-lang-toggle i{font-size:14px}
        .ppv-lang-opts{display:none;position:fixed;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);overflow:hidden;min-width:52px;z-index:99999}
        .ppv-lang-opts.ppv-lang-open{display:block}
        .ppv-lang-opt{display:block;padding:6px 12px;font-size:11px;font-weight:700;color:#6b7280;cursor:pointer;border:none;background:none;width:100%;text-align:center;font-family:inherit;letter-spacing:.5px;transition:all .15s}
        .ppv-lang-opt:hover{background:#f0f2ff;color:#4338ca}
        .ppv-lang-opt.active{color:#667eea}
        </style>

        <script>
        (function(){
            var toggle=document.getElementById('ppv-lang-toggle-btn');
            var opts=document.getElementById('ppv-lang-opts');
            if(!toggle||!opts)return;

            toggle.addEventListener('click',function(e){
                e.stopPropagation();
                var isOpen=opts.classList.contains('ppv-lang-open');
                if(isOpen){
                    opts.classList.remove('ppv-lang-open');
                    return;
                }
                var r=toggle.getBoundingClientRect();
                opts.style.top=(r.bottom+4)+'px';
                opts.style.right=(window.innerWidth-r.right)+'px';
                opts.style.left='auto';
                opts.classList.add('ppv-lang-open');
            });

            document.querySelectorAll('.ppv-lang-opt').forEach(function(btn){
                btn.addEventListener('click',function(){
                    var lang=this.getAttribute('data-lang');
                    var maxAge=60*60*24*365;
                    document.cookie='ppv_lang='+lang+';path=/;max-age='+maxAge+';SameSite=Lax';
                    document.cookie='ppv_lang_manual=1;path=/;max-age='+maxAge+';SameSite=Lax';
                    try{localStorage.setItem('ppv_lang',lang)}catch(e){}
                    var url=new URL(window.location.href);
                    url.searchParams.set('lang',lang);
                    if(window.Turbo){window.Turbo.visit(url.toString(),{action:'replace'})}
                    else{window.location.href=url.toString()}
                });
            });

            document.addEventListener('click',function(e){
                if(!toggle.contains(e.target)&&!opts.contains(e.target)){
                    opts.classList.remove('ppv-lang-open');
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
