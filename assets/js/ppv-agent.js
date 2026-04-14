/**
 * PunktePass Agent Dashboard — Frontend Logic
 */
(function() {
    const API = window.PPV_AGENT.api;
    const NONCE = window.PPV_AGENT.nonce;
    let map = null;
    let markers = [];
    let prospects = [];
    let selectedStatus = 'visited';

    // ============================================================
    //  API Helper
    // ============================================================
    async function apiCall(endpoint, method = 'GET', body = null) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': NONCE
            }
        };
        if (body) opts.body = JSON.stringify(body);
        const url = method === 'GET' && body ? `${API}/${endpoint}?${new URLSearchParams(body)}` : `${API}/${endpoint}`;
        const res = await fetch(method === 'GET' && body ? url : `${API}/${endpoint}`, opts);
        return res.json();
    }

    // ============================================================
    //  Navigation
    // ============================================================
    const panels = { stats: 'panel-stats', list: 'panel-list', map: 'panel-map' };
    const btns = { stats: 'btn-stats', list: 'btn-list', map: 'btn-map' };

    function showPanel(name) {
        Object.keys(panels).forEach(k => {
            document.getElementById(panels[k]).style.display = k === name ? '' : 'none';
            document.getElementById(btns[k]).classList.toggle('active', k === name);
        });
        if (name === 'map') initMap();
        if (name === 'stats') loadStats();
    }

    Object.keys(btns).forEach(k => {
        document.getElementById(btns[k]).addEventListener('click', () => showPanel(k));
    });

    // ============================================================
    //  Prospects List
    // ============================================================
    async function loadProspects() {
        const status = document.getElementById('filter-status').value;
        const period = document.getElementById('filter-period').value;
        const params = new URLSearchParams();
        if (status) params.set('status', status);
        if (period) params.set('period', period);

        const res = await fetch(`${API}/prospects?${params}`, {
            headers: { 'X-WP-Nonce': NONCE }
        });
        const json = await res.json();
        prospects = json.data || [];
        renderList();
        if (map) renderMapMarkers();
    }

    function renderList() {
        const el = document.getElementById('prospect-list');
        if (!prospects.length) {
            el.innerHTML = '<div class="empty-state"><i class="ri-map-pin-line"></i><p>Încă nu sunt vizite</p></div>';
            return;
        }
        el.innerHTML = prospects.map(p => `
            <div class="prospect-card" data-id="${p.id}" onclick="window._openDetail(${p.id})">
                <div class="prospect-top">
                    <span class="status-badge status-${p.status}">${statusLabel(p.status)}</span>
                    ${p.result && p.result !== 'pending' ? `<span class="result-badge result-${p.result}">${resultLabel(p.result)}</span>` : ''}
                </div>
                <h3>${esc(p.business_name)}</h3>
                <p class="prospect-addr"><i class="ri-map-pin-2-line"></i> ${esc(p.address || '')}${p.city ? ', ' + esc(p.city) : ''}</p>
                ${p.contact_name ? `<p class="prospect-contact"><i class="ri-user-line"></i> ${esc(p.contact_name)}</p>` : ''}
                <p class="prospect-date"><i class="ri-time-line"></i> ${formatDate(p.visited_at)}</p>
                ${p.next_followup ? `<p class="prospect-followup"><i class="ri-calendar-check-line"></i> Follow-up: ${formatDate(p.next_followup)}</p>` : ''}
            </div>
        `).join('');
    }

    document.getElementById('filter-status').addEventListener('change', loadProspects);
    document.getElementById('filter-period').addEventListener('change', loadProspects);

    // ============================================================
    //  Check-in Flow
    // ============================================================
    const checkinModal = document.getElementById('checkin-modal');
    const checkinForm = document.getElementById('checkin-form');

    document.getElementById('btn-checkin').addEventListener('click', () => {
        checkinModal.style.display = 'flex';
        checkinForm.reset();
        selectedStatus = 'visited';
        document.querySelectorAll('.status-btn').forEach(b => b.classList.toggle('active', b.dataset.status === 'visited'));
        document.getElementById('checkin-gps-status').style.display = '';
        document.getElementById('ci-address').value = '';
        document.getElementById('ci-city').value = '';
        getGPS();
    });

    document.getElementById('checkin-close').addEventListener('click', () => {
        checkinModal.style.display = 'none';
    });

    // Status buttons
    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            selectedStatus = btn.dataset.status;
            document.querySelectorAll('.status-btn').forEach(b => b.classList.toggle('active', b === btn));
        });
    });

    function getGPS() {
        if (!navigator.geolocation) {
            document.getElementById('checkin-gps-status').innerHTML = '<i class="ri-error-warning-fill"></i> GPS indisponibil';
            return;
        }
        navigator.geolocation.getCurrentPosition(
            pos => {
                const { latitude, longitude } = pos.coords;
                document.getElementById('ci-lat').value = latitude;
                document.getElementById('ci-lng').value = longitude;
                document.getElementById('checkin-gps-status').innerHTML = `<i class="ri-checkbox-circle-fill" style="color:#22c55e"></i> Poziție: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
                reverseGeocode(latitude, longitude);
            },
            err => {
                document.getElementById('checkin-gps-status').innerHTML = `<i class="ri-error-warning-fill"></i> Eroare GPS: ${err.message}`;
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    }

    async function reverseGeocode(lat, lng) {
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1&accept-language=ro`);
            const data = await res.json();
            if (data.address) {
                const a = data.address;
                const street = [a.road || a.pedestrian || '', a.house_number || ''].filter(Boolean).join(' ');
                const city = a.city || a.town || a.village || a.municipality || '';
                document.getElementById('ci-address').value = street;
                document.getElementById('ci-city').value = city;
            }
        } catch(e) {
            console.log('Geocode error:', e);
        }
    }

    checkinForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('ci-submit');
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Se salvează...';

        const data = {
            lat: parseFloat(document.getElementById('ci-lat').value),
            lng: parseFloat(document.getElementById('ci-lng').value),
            business_name: document.getElementById('ci-business').value,
            address: document.getElementById('ci-address').value,
            city: document.getElementById('ci-city').value,
            contact_name: document.getElementById('ci-contact').value,
            contact_phone: document.getElementById('ci-phone').value,
            notes: document.getElementById('ci-notes').value,
            status: selectedStatus
        };

        const res = await apiCall('checkin', 'POST', data);

        btn.disabled = false;
        btn.innerHTML = '<i class="ri-save-fill"></i> Speichern';

        if (res.success) {
            checkinModal.style.display = 'none';
            loadProspects();
            showToast('Check-in salvat!');
        } else {
            showToast(res.message || 'Fehler', true);
        }
    });

    // ============================================================
    //  Prospect Detail
    // ============================================================
    window._openDetail = async function(id) {
        const p = prospects.find(x => x.id == id);
        if (!p) return;

        document.getElementById('detail-title').textContent = p.business_name;
        document.getElementById('detail-body').innerHTML = `
            <div class="detail-section">
                <p><i class="ri-map-pin-2-fill"></i> ${esc(p.address || '')}${p.city ? ', ' + esc(p.city) : ''}</p>
                ${p.contact_name ? `<p><i class="ri-user-fill"></i> ${esc(p.contact_name)}</p>` : ''}
                ${p.contact_phone ? `<p><i class="ri-phone-fill"></i> <a href="tel:${esc(p.contact_phone)}">${esc(p.contact_phone)}</a></p>` : ''}
                ${p.contact_email ? `<p><i class="ri-mail-fill"></i> ${esc(p.contact_email)}</p>` : ''}
                ${p.notes ? `<p><i class="ri-sticky-note-fill"></i> ${esc(p.notes)}</p>` : ''}
                <p><i class="ri-time-fill"></i> Vizitat: ${formatDate(p.visited_at)}</p>
            </div>

            <div class="detail-section">
                <label>Stare</label>
                <div class="status-buttons">
                    ${['visited','contacted','interested','customer','not_interested'].map(s =>
                        `<button class="status-btn ${p.status === s ? 'active' : ''}" onclick="window._updateProspect(${id},'status','${s}')">${statusLabel(s)}</button>`
                    ).join('')}
                </div>
            </div>

            <div class="detail-section">
                <label>Rezultat</label>
                <div class="status-buttons">
                    ${['pending','trial','converted','lost'].map(r =>
                        `<button class="status-btn result-${r} ${(p.result||'pending') === r ? 'active' : ''}" onclick="window._updateProspect(${id},'result','${r}')">${resultLabel(r)}</button>`
                    ).join('')}
                </div>
            </div>

            <div class="detail-section">
                <label><input type="checkbox" id="det-device" ${p.needs_device == 1 ? 'checked' : ''} onchange="window._updateProspect(${id},'needs_device',this.checked?1:0)"> Necesită dispozitiv</label>
                <label><input type="checkbox" id="det-delivered" ${p.device_delivered == 1 ? 'checked' : ''} onchange="window._updateProspect(${id},'device_delivered',this.checked?1:0)"> Dispozitiv livrat</label>
            </div>

            ${p.trial_start ? `<div class="detail-section trial-info">
                <p><i class="ri-calendar-fill"></i> Perioadă de test: ${formatDate(p.trial_start)} - ${formatDate(p.trial_end)}</p>
            </div>` : ''}

            <div class="detail-section">
                <label>Data follow-up</label>
                <input type="date" id="det-followup" value="${p.next_followup ? p.next_followup.split(' ')[0] : ''}" onchange="window._updateProspect(${id},'next_followup',this.value)">
            </div>

            <div class="detail-section">
                <label>Notițe</label>
                <textarea id="det-notes" rows="3" onblur="window._updateProspect(${id},'notes',this.value)">${esc(p.notes || '')}</textarea>
            </div>
        `;
        document.getElementById('detail-modal').style.display = 'flex';
    };

    window._updateProspect = async function(id, field, value) {
        const res = await apiCall(`prospect/${id}`, 'POST', { [field]: value });
        if (res.success) {
            showToast('Salvat');
            loadProspects();
        }
    };

    document.getElementById('detail-close').addEventListener('click', () => {
        document.getElementById('detail-modal').style.display = 'none';
    });

    // ============================================================
    //  Map
    // ============================================================
    function initMap() {
        if (map) { renderMapMarkers(); return; }
        map = L.map('agent-map').setView([47.5, 24.0], 7); // Romania center
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        setTimeout(() => map.invalidateSize(), 200);
        renderMapMarkers();
    }

    function renderMapMarkers() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];
        const bounds = [];
        prospects.forEach(p => {
            if (!p.lat || !p.lng) return;
            const color = statusColor(p.status);
            const icon = L.divIcon({
                className: 'agent-marker',
                html: `<div style="background:${color};width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3)"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });
            const m = L.marker([parseFloat(p.lat), parseFloat(p.lng)], { icon })
                .addTo(map)
                .bindPopup(`<b>${esc(p.business_name)}</b><br>${statusLabel(p.status)}<br><small>${esc(p.address || '')}</small>`);
            m.on('click', () => window._openDetail(p.id));
            markers.push(m);
            bounds.push([parseFloat(p.lat), parseFloat(p.lng)]);
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
    }

    // ============================================================
    //  Stats
    // ============================================================
    async function loadStats() {
        const res = await fetch(`${API}/stats`, { headers: { 'X-WP-Nonce': NONCE } });
        const json = await res.json();
        if (!json.success) return;
        const s = json.stats;
        document.getElementById('stat-today').textContent = s.today;
        document.getElementById('stat-week').textContent = s.week;
        document.getElementById('stat-month').textContent = s.month;
        document.getElementById('stat-followups').textContent = s.followups_due;

        const pipe = document.getElementById('stats-pipeline');
        const statuses = s.by_status || {};
        const results = s.by_result || {};
        pipe.innerHTML = `
            <h3>Flux vânzări</h3>
            <div class="pipeline-row">${Object.entries(statuses).map(([k,v]) => `<div class="pipe-item"><span class="pipe-dot" style="background:${statusColor(k)}"></span>${statusLabel(k)}: <b>${v}</b></div>`).join('')}</div>
            ${Object.keys(results).length ? `<h3>Rezultate</h3><div class="pipeline-row">${Object.entries(results).map(([k,v]) => `<div class="pipe-item"><span class="pipe-dot" style="background:${resultColor(k)}"></span>${resultLabel(k)}: <b>${v}</b></div>`).join('')}</div>` : ''}
        `;
    }

    // ============================================================
    //  Helpers
    // ============================================================
    function statusLabel(s) {
        return { visited: 'Vizitat', contacted: 'Contactat', interested: 'Interesat', customer: 'Client', not_interested: 'Neinteresat' }[s] || s;
    }
    function resultLabel(r) {
        return { pending: 'Deschis', trial: 'Test 30 zile', converted: 'Client!', lost: 'Pierdut' }[r] || r;
    }
    function statusColor(s) {
        return { visited: '#64748b', contacted: '#3b82f6', interested: '#f59e0b', customer: '#22c55e', not_interested: '#ef4444' }[s] || '#999';
    }
    function resultColor(r) {
        return { pending: '#94a3b8', trial: '#f59e0b', converted: '#22c55e', lost: '#ef4444' }[r] || '#999';
    }
    function formatDate(d) {
        if (!d) return '-';
        const dt = new Date(d);
        return dt.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function showToast(msg, err = false) {
        const t = document.createElement('div');
        t.className = 'agent-toast' + (err ? ' error' : '');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.classList.add('show'), 10);
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2500);
    }

    // Close modals on backdrop click
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
    });

    // ============================================================
    //  Init
    // ============================================================
    loadProspects();
})();
