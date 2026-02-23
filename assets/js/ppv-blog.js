/**
 * PunktePass Blog – JavaScript
 * ============================
 * Reading progress, share buttons, smooth interactions.
 */

(function() {
    'use strict';

    // ── Reading Progress Bar ──────────────────
    var progressBar = document.getElementById('ppvBlogProgressBar');
    var articleBody = document.getElementById('ppvBlogBody');

    if (progressBar && articleBody) {
        var ticking = false;

        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(function() {
                    var rect = articleBody.getBoundingClientRect();
                    var articleTop = rect.top + window.scrollY;
                    var articleHeight = rect.height;
                    var scrolled = window.scrollY - articleTop + window.innerHeight * 0.3;
                    var progress = Math.min(Math.max(scrolled / articleHeight * 100, 0), 100);
                    progressBar.style.width = progress + '%';
                    ticking = false;
                });
                ticking = true;
            }
        });
    }

    // ── Share Buttons ─────────────────────────
    var shareButtons = document.querySelectorAll('.ppv-blog-share-btn');
    var pageUrl = encodeURIComponent(window.location.href);
    var pageTitle = encodeURIComponent(document.title);

    shareButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = this.getAttribute('data-share');
            var url = '';

            switch (type) {
                case 'facebook':
                    url = 'https://www.facebook.com/sharer/sharer.php?u=' + pageUrl;
                    break;
                case 'twitter':
                    url = 'https://twitter.com/intent/tweet?url=' + pageUrl + '&text=' + pageTitle;
                    break;
                case 'linkedin':
                    url = 'https://www.linkedin.com/sharing/share-offsite/?url=' + pageUrl;
                    break;
                case 'whatsapp':
                    url = 'https://wa.me/?text=' + pageTitle + '%20' + pageUrl;
                    break;
                case 'copy':
                    copyToClipboard(window.location.href, this);
                    return;
            }

            if (url) {
                window.open(url, '_blank', 'width=600,height=400,scrollbars=yes');
            }
        });
    });

    function copyToClipboard(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback(btn);
            });
        } else {
            // Fallback
            var input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            showCopyFeedback(btn);
        }
    }

    function showCopyFeedback(btn) {
        var icon = btn.querySelector('i');
        var origClass = icon.className;
        icon.className = 'ri-check-line';
        btn.style.color = '#22c55e';
        btn.style.borderColor = '#22c55e';

        setTimeout(function() {
            icon.className = origClass;
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    }

    // ── Lazy Image Loading (Intersection Observer) ──
    if ('IntersectionObserver' in window) {
        var imgObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    imgObserver.unobserve(img);
                }
            });
        }, { rootMargin: '100px' });

        document.querySelectorAll('.ppv-blog-card-img img[data-src]').forEach(function(img) {
            imgObserver.observe(img);
        });
    }
})();
