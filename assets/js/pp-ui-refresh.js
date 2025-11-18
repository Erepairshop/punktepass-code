(function($){
  $(document).on('click', 'button.pp-delete', function(){
    if($(this).attr('type')!=='button'){ $(this).attr('type','button'); }
  });
  $(document).on('click', '.pp-delete', function(e){
    // Ha már van saját handlered, ez nem zavar be: csak reloadol siker esetén.
    var $btn = $(this);
    // Ha a te handlered már elküldi az AJAX-ot, figyeljük a globális ajaxComplete-et is:
    $(document).one('ajaxSuccess', function(_e, xhr, settings, data){
      try{
        if(data && (data.success || data.status==='success')){
          // kemény frissítés cache ellen
          window.location.reload(true);
        }
      }catch(_){}
    });
    // Biztonsági fallback: ha 1 mp-ig nincs ajaxSuccess, akkor is frissítünk
    setTimeout(function(){
      try{ window.location.reload(true); }catch(_){}
    }, 1200);
  });
})(jQuery);