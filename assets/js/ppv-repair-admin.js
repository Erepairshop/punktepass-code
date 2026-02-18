/**
 * PunktePass - Repair Admin Dashboard JS
 * Handles search, filters, status updates, settings, logo upload
 */
(function() {
    'use strict';

    if (typeof ppvRepairAdmin === 'undefined') return;

    var searchInput = document.getElementById('ra-search-input');
    var filterStatus = document.getElementById('ra-filter-status');
    var repairsList = document.getElementById('ra-repairs-list');
    var settingsForm = document.getElementById('ra-settings-form');
    var loginForm = document.getElementById('ra-login-form');
    var searchTimeout = null;

    // ========================================
    // Login Form
    // ========================================
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var email = document.getElementById('ra-login-email').value;
            var pass = document.getElementById('ra-login-pass').value;
            var errorDiv = document.getElementById('ra-login-error');

            var fd = new FormData();
            fd.append('action', 'ppv_login');
            fd.append('email', email);
            fd.append('password', pass);
            fd.append('nonce', ppvRepairAdmin.nonce);

            fetch(ppvRepairAdmin.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        errorDiv.textContent = data.data?.message || 'Login fehlgeschlagen';
                        errorDiv.style.display = 'block';
                    }
                })
                .catch(function() {
                    errorDiv.textContent = 'Verbindungsfehler';
                    errorDiv.style.display = 'block';
                });
        });
        return; // Don't initialize admin features if on login page
    }

    // ========================================
    // Search & Filter
    // ========================================
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() { loadRepairs(); }, 400);
        });
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', function() { loadRepairs(); });
    }

    function loadRepairs(page) {
        page = page || 1;

        var fd = new FormData();
        fd.append('action', 'ppv_repair_search');
        fd.append('nonce', ppvRepairAdmin.nonce);
        fd.append('search', searchInput ? searchInput.value : '');
        fd.append('filter_status', filterStatus ? filterStatus.value : '');
        fd.append('page', page);

        fetch(ppvRepairAdmin.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && repairsList) {
                    if (data.data.repairs.length === 0) {
                        repairsList.innerHTML = '<div class="ra-empty"><i class="ri-search-line"></i><p>Keine Ergebnisse gefunden</p></div>';
                    } else {
                        repairsList.innerHTML = data.data.repairs.map(renderCard).join('');
                        bindStatusSelects();
                    }

                    // Update load more button
                    var loadMoreBtn = document.getElementById('ra-load-more');
                    if (loadMoreBtn) {
                        loadMoreBtn.dataset.page = page;
                        loadMoreBtn.style.display = data.data.pages > page ? 'block' : 'none';
                    }
                }
            });
    }

    function renderCard(r) {
        var statusLabels = {
            'new': ['Neu', 'ra-status-new'],
            'in_progress': ['In Bearbeitung', 'ra-status-progress'],
            'waiting_parts': ['Wartet auf Teile', 'ra-status-waiting'],
            'done': ['Fertig', 'ra-status-done'],
            'delivered': ['Abgeholt', 'ra-status-delivered'],
            'cancelled': ['Storniert', 'ra-status-cancelled']
        };

        var status = statusLabels[r.status] || ['Unbekannt', ''];
        var device = ((r.device_brand || '') + ' ' + (r.device_model || '')).trim();
        var date = formatDate(r.created_at);
        var problem = r.problem_description || '';
        if (problem.length > 150) problem = problem.substring(0, 150) + '...';

        var options = Object.keys(statusLabels).map(function(key) {
            return '<option value="' + key + '"' + (r.status === key ? ' selected' : '') + '>' + statusLabels[key][0] + '</option>';
        }).join('');

        return '<div class="ra-repair-card" data-id="' + r.id + '">' +
            '<div class="ra-repair-header">' +
                '<div class="ra-repair-id">#' + r.id + '</div>' +
                '<span class="ra-status ' + status[1] + '">' + status[0] + '</span>' +
            '</div>' +
            '<div class="ra-repair-body">' +
                '<div class="ra-repair-customer">' +
                    '<strong>' + escHtml(r.customer_name) + '</strong>' +
                    '<span class="ra-repair-meta">' + escHtml(r.customer_email) +
                    (r.customer_phone ? ' &middot; ' + escHtml(r.customer_phone) : '') + '</span>' +
                '</div>' +
                (device ? '<div class="ra-repair-device"><i class="ri-smartphone-line"></i> ' + escHtml(device) + '</div>' : '') +
                '<div class="ra-repair-problem">' + escHtml(problem) + '</div>' +
                '<div class="ra-repair-date"><i class="ri-time-line"></i> ' + date + '</div>' +
            '</div>' +
            '<div class="ra-repair-actions">' +
                '<select class="ra-status-select" data-repair-id="' + r.id + '">' + options + '</select>' +
                '<button type="button" class="ra-btn ra-btn-sm ra-angebot-from-repair" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;margin-left:8px;font-size:11px" ' +
                    'data-name="' + escAttr(r.customer_name || '') + '" ' +
                    'data-email="' + escAttr(r.customer_email || '') + '" ' +
                    'data-phone="' + escAttr(r.customer_phone || '') + '" ' +
                    'data-device="' + escAttr(device) + '" ' +
                    'data-problem="' + escAttr(r.problem_description || '') + '">' +
                    '<i class="ri-draft-line"></i> Angebot' +
                '</button>' +
            '</div>' +
        '</div>';
    }

    // ========================================
    // Status Update
    // ========================================
    function bindStatusSelects() {
        document.querySelectorAll('.ra-status-select').forEach(function(sel) {
            sel.removeEventListener('change', onStatusChange);
            sel.addEventListener('change', onStatusChange);
        });
    }

    function onStatusChange() {
        var select = this;
        var repairId = select.dataset.repairId;
        var newStatus = select.value;

        var fd = new FormData();
        fd.append('action', 'ppv_repair_update_status');
        fd.append('nonce', ppvRepairAdmin.nonce);
        fd.append('repair_id', repairId);
        fd.append('status', newStatus);

        fetch(ppvRepairAdmin.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    // Update status badge
                    var card = select.closest('.ra-repair-card');
                    if (card) {
                        var badge = card.querySelector('.ra-status');
                        var statusLabels = {
                            'new': ['Neu', 'ra-status-new'],
                            'in_progress': ['In Bearbeitung', 'ra-status-progress'],
                            'waiting_parts': ['Wartet auf Teile', 'ra-status-waiting'],
                            'done': ['Fertig', 'ra-status-done'],
                            'delivered': ['Abgeholt', 'ra-status-delivered'],
                            'cancelled': ['Storniert', 'ra-status-cancelled']
                        };
                        var s = statusLabels[newStatus];
                        if (badge && s) {
                            badge.className = 'ra-status ' + s[1];
                            badge.textContent = s[0];
                        }
                    }
                    showToast('Status aktualisiert', 'success');
                } else {
                    showToast(data.data?.message || 'Fehler', 'error');
                }
            });
    }

    // Init status selects on page load
    bindStatusSelects();

    // ========================================
    // Settings Form
    // ========================================
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var fd = new FormData(settingsForm);
            fd.append('action', 'ppv_repair_save_settings');
            fd.append('nonce', ppvRepairAdmin.nonce);

            fetch(ppvRepairAdmin.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast('Einstellungen gespeichert', 'success');
                    } else {
                        showToast(data.data?.message || 'Fehler beim Speichern', 'error');
                    }
                });
        });
    }

    // ========================================
    // Logo Upload
    // ========================================
    var logoFile = document.getElementById('ra-logo-file');
    if (logoFile) {
        logoFile.addEventListener('change', function() {
            if (!this.files[0]) return;

            var fd = new FormData();
            fd.append('action', 'ppv_repair_upload_logo');
            fd.append('nonce', ppvRepairAdmin.nonce);
            fd.append('logo', this.files[0]);

            fetch(ppvRepairAdmin.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var preview = document.getElementById('ra-logo-preview');
                        if (preview.tagName === 'IMG') {
                            preview.src = data.data.url;
                        } else {
                            preview.outerHTML = '<img src="' + data.data.url + '" class="ra-logo-preview" id="ra-logo-preview">';
                        }
                        showToast('Logo hochgeladen', 'success');
                    } else {
                        showToast(data.data?.message || 'Upload fehlgeschlagen', 'error');
                    }
                });
        });
    }

    // ========================================
    // Load More
    // ========================================
    var loadMoreBtn = document.getElementById('ra-load-more');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            var nextPage = parseInt(this.dataset.page || 1) + 1;
            loadRepairs(nextPage);
        });
    }

    // ========================================
    // Helpers
    // ========================================
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function formatDate(str) {
        if (!str) return '';
        var d = new Date(str);
        return d.toLocaleDateString('de-DE') + ' ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    function showToast(msg, type) {
        if (window.ppvToast) {
            window.ppvToast(msg, type);
        }
    }
})();
