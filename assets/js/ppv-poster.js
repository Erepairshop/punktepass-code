jQuery(document).ready(function ($) {

    // élő előnézet – sablonváltás
    $('input[name="poster_template"]').on('change', function () {
        $('#ppv-poster-preview')
            .removeClass('hell dunkel neon')
            .addClass($(this).val());
    });

    // élő előnézet – saját szöveg
    $('#poster_text').on('input', function () {
        const txt = $(this).val().trim();
        const custom = $('.ppv-custom');
        if (custom.length) custom.text(txt);
        else if (txt) $('<p class="ppv-custom">'+txt+'</p>').insertAfter('.ppv-slogan');
        if (!txt) $('.ppv-custom').remove();
    });

    // mentés AJAX
    $('#poster_save').on('click', function () {
        $.post(PPV_POSTER.ajaxurl, {
            action: 'ppv_save_poster',
            nonce: PPV_POSTER.nonce,
            template: $('input[name="poster_template"]:checked').val(),
            text: $('#poster_text').val()
        }, function (res) {
            if (res.success) {
                $('#poster_saved').fadeIn(200).delay(1200).fadeOut(400);
            }
        });
    });

    // PNG letöltés
    $('#ppv-poster-download').on('click', function () {
        const poster = document.getElementById('ppv-poster-preview');
        html2canvas(poster, {scale:2,backgroundColor:null}).then(canvas=>{
            const link=document.createElement('a');
            link.download=$('.ppv-store-name').text().replace(/\s+/g,'_')+'_Poster.png';
            link.href=canvas.toDataURL('image/png');
            link.click();
        });
    });

    // Nyomtatás
    $('#ppv-poster-print').on('click', function () {
        const poster=document.getElementById('ppv-poster-preview');
        html2canvas(poster,{scale:2,backgroundColor:'#fff'}).then(canvas=>{
            const win=window.open('');
            win.document.write('<img src="'+canvas.toDataURL()+'" style="width:100%">');
            win.print();
        });
    });
});
