(function(){
  function onReady(fn){ if(document.readyState!='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  onReady(function(){
    var buttons = document.querySelectorAll('.button-link-delete');
    for(var i=0;i<buttons.length;i++){
      buttons[i].addEventListener('click', function(ev){
        if(!confirm('Löschen?')){
          ev.preventDefault();
          ev.stopPropagation();
          return false;
        }
        // else: allow form submit
      });
    }
  });
})();
