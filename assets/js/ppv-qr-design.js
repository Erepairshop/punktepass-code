jQuery(document).ready(function($){
  $('#qr-color').on('input', function(){
    let color = $(this).val();
    $('#qr-preview img').css('filter', 'drop-shadow(0 0 0 ' + color + ')');
  });
});
