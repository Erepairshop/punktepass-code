jQuery(document).ready(function ($) {

    /**
     * ====== Bild Vorschau für Logo / Cover ======
     */
    $('input[type="file"][name="store_logo"], input[type="file"][name="store_cover"]').on('change', function (e) {
        let file = e.target.files[0];
        if (!file) return;

        let reader = new FileReader();
        reader.onload = function (event) {
            let previewImg = $('<img>').attr('src', event.target.result).css({
                'max-height': '80px',
                'margin-top': '10px',
                'display': 'block'
            });

            $(e.target).next('img').remove(); // előző preview törlése
            $(e.target).after(previewImg);
        };
        reader.readAsDataURL(file);
    });

    /**
     * ====== Galerie Vorschau ======
     */
    $('input[type="file"][name="store_gallery[]"]').on('change', function (e) {
        let files = e.target.files;
        let container = $('<div class="ppv-gallery-preview"></div>');

        for (let i = 0; i < files.length; i++) {
            let reader = new FileReader();
            reader.onload = function (event) {
                let img = $('<img>').attr('src', event.target.result).css({
                    'max-height': '60px',
                    'margin': '5px',
                    'border': '1px solid #ccc'
                });
                container.append(img);
            };
            reader.readAsDataURL(files[i]);
        }

        $(this).next('.ppv-gallery-preview').remove(); // régi preview törlése
        $(this).after(container);
    });

    /**
     * ====== Accordion für Öffnungszeiten (opcionális) ======
     */
    if ($('.ppv-profile-form').length) {
        let ohSection = $('.ppv-profile-form h3:contains("Öffnungszeiten")');
        let fields = ohSection.nextUntil('h3');

        // alapból összecsukva
        fields.wrapAll('<div class="ppv-opening-hours"></div>');
        $('.ppv-opening-hours').hide();

        ohSection.css({ 'cursor': 'pointer', 'color': '#2d89ef' }).append(' ▼');
        ohSection.on('click', function () {
            $('.ppv-opening-hours').slideToggle();
        });
    }

    /**
     * ====== Dashboard Karten Hover Effekt ======
     */
    $('.ppv-card').on('mouseenter', function () {
        $(this).css('box-shadow', '0 4px 12px rgba(0,0,0,0.15)');
    }).on('mouseleave', function () {
        $(this).css('box-shadow', '0 2px 6px rgba(0,0,0,0.1)');
    });

});
