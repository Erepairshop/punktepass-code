jQuery(document).ready(function($){
  // 🌐 Language detection
  const detectLang = () => document.cookie.match(/ppv_lang=([a-z]{2})/)?.[1] || localStorage.getItem('ppv_lang') || 'de';
  const LANG = detectLang();
  const T = {
    de: { download_starting: 'Download startet...' },
    hu: { download_starting: 'Letöltés indul...' },
    ro: { download_starting: 'Descărcarea începe...' }
  }[LANG] || { download_starting: 'Download startet...' };

  $('.ppv-card button').on('click', function(){
    alert(T.download_starting);
  });
});
