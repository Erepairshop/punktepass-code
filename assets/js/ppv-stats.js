/**
 * PunktePass – Complete Stats Dashboard JS (v3.0)
 * ✅ Basic Stats (1-3): Daily, Top5, Peak Hours
 * ✅ Advanced Stats (4-7): Trend, Spending, Conversion, Export
 * ✅ ONE FILE, ALL FUNCTIONALITY
 */

jQuery(document).ready(function($) {

    // ============================================================
    // 🔧 CONFIG + TRANSLATIONS
    // ============================================================
    if (!window.ppvStats) {
        console.error("❌ [Stats] Config missing!");
        return;
    }

    const config = window.ppvStats;
    const nonce = config.nonce;
    const T = window.ppvStats?.translations || {};

    // Canvas for chart
    const ctx = document.getElementById('ppv-stats-canvas');
    let chart = null;
    let currentData = null;

    // jQuery cache
    const $loading = $('#ppv-stats-loading');
    const $error = $('#ppv-stats-error');
    const $content = $('.ppv-stats-content');
    const $rangeSelect = $('#ppv-stats-range');
    const $filialeSelect = $('#ppv-stats-filiale');
    const $exportBtn = $('#ppv-export-csv');
    const $exportAdvBtn = $('#ppv-export-advanced');
    const $exportFormatSelect = $('#ppv-export-format');

    // Current filiale selection
    let currentFilialeId = 'all';


    // ============================================================
    // 🛡️ HELPERS
    // ============================================================
    function formatNumber(num) {
        return parseInt(num || 0).toLocaleString('de-DE');
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;', '<': '&lt;', '>': '&gt;',
            '"': '&quot;', "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function getTrendArrow(trend) {
        if (trend > 0) return '📈 +';
        if (trend < 0) return '📉 ';
        return '➡️ ';
    }

    function showError(msg) {
        console.error("❌ " + msg);
        $loading.hide();
        $content.hide();
        $error.show().find('p').text(`❌ ${msg}`);
    }

    // ============================================================
    // 📊 LOAD BASIC STATS (1-3)
    // ============================================================
    function loadBasicStats(range = 'week') {

        $loading.show();
        $error.hide();
        $content.hide();

        $.ajax({
            url: config.ajax_url,
            method: 'GET',
            data: { range: range, filiale_id: currentFilialeId },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {

                if (!res || !res.success) {
                    showError(res.error || T['error_loading_data'] || 'Server error');
                    return;
                }

                currentData = res;
                updateBasicStats(res);
                updateChart(res.chart || []);

                $loading.hide();
                $content.show();
            },
            error: function(xhr) {
                let msg = T['network_error'] || 'Network error';
                if (xhr.status === 403) msg = T['not_authenticated'] || 'Not authorized';
                if (xhr.status === 404) msg = T['api_not_found'] || 'API not found';
                if (xhr.status === 500) msg = T['server_error'] || 'Server error';
                showError(msg + ` (${xhr.status})`);
            }
        });
    }

    // ============================================================
    // 🎨 UPDATE BASIC STATS
    // ============================================================
    function updateBasicStats(data) {

        // Main stats cards
        $('#ppv-stat-daily').text(formatNumber(data.daily || 0));
        $('#ppv-stat-weekly').text(formatNumber(data.weekly || 0));
        $('#ppv-stat-monthly').text(formatNumber(data.monthly || 0));
        $('#ppv-stat-all-time').text(formatNumber(data.all_time || 0));
        $('#ppv-stat-unique').text(formatNumber(data.unique || 0));

        // Rewards stats
        if (data.rewards) {
            $('#ppv-rewards-total').text(formatNumber(data.rewards.total || 0));
            $('#ppv-rewards-approved').text(formatNumber(data.rewards.approved || 0));
            $('#ppv-rewards-pending').text(formatNumber(data.rewards.pending || 0));
            $('#ppv-rewards-spent').text(formatNumber(data.rewards.points_spent || 0));
        }

        // Top 5 users
        updateTop5Users(data.top5_users || []);

        // Peak hours
        updatePeakHours(data.peak_hours || []);

    }

    // ============================================================
    // 🏆 TOP 5 USERS
    // ============================================================
    function updateTop5Users(users) {
        const $container = $('#ppv-top5-users');
        
        if (!Array.isArray(users) || users.length === 0) {
            $container.html(`<p class="ppv-loading-small">${T['no_data'] || 'No data'}</p>`);
            return;
        }

        const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
        let html = '';

        users.forEach((user, idx) => {
            const medal = medals[idx] || '⭐';
            const name = escapeHtml(user.name || `User #${user.user_id}`);
            const email = user.email && user.email !== 'N/A' ? user.email : T['no_email'] || 'No email';
            const emailShort = email.length > 25 ? email.substring(0, 22) + '...' : email;
            const purchases = user.purchases || 0;
            const points = formatNumber(user.total_points || 0);
            const purchaseLabel = purchases === 1 ? T['purchase'] || 'Purchase' : T['purchases'] || 'Purchases';

            html += `
                <div class="ppv-top5-card">
                    <div class="ppv-top5-rank">${medal}</div>
                    <div class="ppv-top5-content">
                        <h4 class="ppv-top5-name">${name}</h4>
                        <p class="ppv-top5-email" title="${escapeHtml(email)}">📧 ${escapeHtml(emailShort)}</p>
                        <span class="ppv-top5-purchases">💳 ${purchases} ${purchaseLabel}</span>
                    </div>
                    <div class="ppv-top5-points">${points}<br><small>pts</small></div>
                </div>
            `;
        });

        $container.html(html);
    }

    // ============================================================
    // ⏰ PEAK HOURS
    // ============================================================
    function updatePeakHours(hours) {
        const $container = $('#ppv-peak-hours');
        
        if (!Array.isArray(hours) || hours.length === 0) {
            $container.html(`<p class="ppv-loading-small">${T['no_data'] || 'No data'}</p>`);
            return;
        }

        const maxCount = Math.max(...hours.map(h => h.count || 0), 1);
        let html = '';

        hours.forEach(hour => {
            const time = hour.time || '00:00';
            const count = hour.count || 0;
            const percent = (count / maxCount) * 100;

            html += `
                <div class="ppv-peak-item">
                    <span class="ppv-time">⏰ ${time}</span>
                    <div class="ppv-bar" style="width: ${percent}%"></div>
                    <span class="ppv-count">${count}</span>
                </div>
            `;
        });

        $container.html(html);
    }

    // ============================================================
    // 📈 CHART.JS
    // ============================================================
    function updateChart(chartData) {
        if (!ctx) {
            console.warn("⚠️ [Chart] Canvas not found");
            return;
        }

        if (!Array.isArray(chartData) || chartData.length === 0) {
            console.warn("⚠️ [Chart] No data");
            return;
        }

        const labels = chartData.map(c => {
            try {
                const d = new Date(c.date + 'T00:00:00');
                return d.toLocaleDateString('de-DE', { month: 'short', day: 'numeric' });
            } catch (e) {
                return c.date;
            }
        });

        const data = chartData.map(c => parseInt(c.count) || 0);

        if (chart) {
            try { chart.destroy(); } catch (e) {}
        }

        try {
            const gradientBg = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
            gradientBg.addColorStop(0, 'rgba(0, 224, 255, 0.3)');
            gradientBg.addColorStop(1, 'rgba(0, 224, 255, 0.01)');

            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: T['daily_points'] || 'Daily Points',
                        data: data,
                        borderColor: '#00e0ff',
                        backgroundColor: gradientBg,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#00e0ff',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    animation: { duration: 500 },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: 'rgba(255, 255, 255, 0.7)' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: 'rgba(255, 255, 255, 0.7)' }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: { color: 'rgba(255, 255, 255, 0.9)' }
                        }
                    }
                }
            });

        } catch (e) {
            console.error("❌ [Chart] Error:", e.message);
        }
    }

    // ============================================================
    // 4️⃣ LOAD TREND
    // ============================================================
    function loadTrend() {

        $.ajax({
            url: config.trend_url,
            method: 'GET',
            data: { filiale_id: currentFilialeId },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {
                if (res.success) {
                    displayTrend(res);
                }
            },
            error: function() {
                $('#ppv-trend').html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading'}</p>`);
            }
        });
    }

    function displayTrend(data) {
        const week = data.week || {};
        const month = data.month || {};
        const daily = data.daily_breakdown || [];

        const weekArrow = getTrendArrow(week.trend || 0);
        const monthArrow = getTrendArrow(month.trend || 0);

        let html = `
            <div class="ppv-trend-grid">
                <div class="ppv-trend-card">
                    <h4>${T['this_week'] || 'This Week'}</h4>
                    <div class="ppv-trend-main">${formatNumber(week.current || 0)}</div>
                    <div class="ppv-trend-compare">${T['vs_last_week'] || 'vs. last week'}: <strong>${formatNumber(week.previous || 0)}</strong></div>
                    <div class="ppv-trend-percent ${week.trend_up ? 'up' : 'down'}">
                        ${weekArrow} ${Math.abs(week.trend || 0).toFixed(1)}%
                    </div>
                </div>

                <div class="ppv-trend-card">
                    <h4>${T['this_month'] || 'This Month'}</h4>
                    <div class="ppv-trend-main">${formatNumber(month.current || 0)}</div>
                    <div class="ppv-trend-compare">${T['vs_last_month'] || 'vs. last month'}: <strong>${formatNumber(month.previous || 0)}</strong></div>
                    <div class="ppv-trend-percent ${month.trend_up ? 'up' : 'down'}">
                        ${monthArrow} ${Math.abs(month.trend || 0).toFixed(1)}%
                    </div>
                </div>
            </div>

            <div class="ppv-daily-breakdown">
                <h4>${T['daily_trend'] || 'Daily Trend'}</h4>
                <div class="ppv-daily-bars">
        `;

        const maxDaily = Math.max(...daily.map(d => d.count || 0), 1);
        daily.forEach(day => {
            const pct = (day.count / maxDaily) * 100;
            html += `
                <div class="ppv-daily-bar">
                    <div class="ppv-bar-fill" style="height: ${pct}%"></div>
                    <span class="ppv-day-label">${day.day}</span>
                    <span class="ppv-day-count">${day.count}</span>
                </div>
            `;
        });

        html += '</div></div>';
        $('#ppv-trend').html(html);
    }

    // ============================================================
    // 5️⃣ LOAD SPENDING
    // ============================================================
    function loadSpending() {

        $.ajax({
            url: config.spending_url,
            method: 'GET',
            data: { filiale_id: currentFilialeId },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {
                if (res.success) {
                    displaySpending(res);
                }
            },
            error: function() {
                $('#ppv-spending').html(`<p class="ppv-error-small">${T['error_loading'] || 'Error'}</p>`);
            }
        });
    }

    function displaySpending(data) {
        const spend = data.spending || {};
        const status = data.by_status || {};
        const topRewards = data.top_rewards || [];

        let html = `
            <div class="ppv-spending-grid">
                <div class="ppv-spend-card">
                    <span class="ppv-label">${T['today'] || 'Today'}</span>
                    <div class="ppv-spend-value">${formatNumber(spend.daily || 0)} <small>pts</small></div>
                </div>
                <div class="ppv-spend-card">
                    <span class="ppv-label">${T['weekly'] || 'This Week'}</span>
                    <div class="ppv-spend-value">${formatNumber(spend.weekly || 0)} <small>pts</small></div>
                </div>
                <div class="ppv-spend-card">
                    <span class="ppv-label">${T['monthly'] || 'This Month'}</span>
                    <div class="ppv-spend-value">${formatNumber(spend.monthly || 0)} <small>pts</small></div>
                </div>
                <div class="ppv-spend-card">
                    <span class="ppv-label">${T['average_per_reward'] || 'Avg per Reward'}</span>
                    <div class="ppv-spend-value">${formatNumber(data.average_reward_value || 0)} <small>pts</small></div>
                </div>
            </div>

            <div class="ppv-spend-status">
                <h4>${T['by_status'] || 'By Status'}</h4>
                <div class="ppv-status-grid">
                    <div class="ppv-status-item approved">
                        <span class="ppv-status-label">✅ ${T['confirmed'] || 'Confirmed'}</span>
                        <span class="ppv-status-value">${formatNumber(status.approved || 0)}</span>
                    </div>
                    <div class="ppv-status-item pending">
                        <span class="ppv-status-label">⏳ ${T['outstanding'] || 'Outstanding'}</span>
                        <span class="ppv-status-value">${formatNumber(status.pending || 0)}</span>
                    </div>
                    <div class="ppv-status-item rejected">
                        <span class="ppv-status-label">❌ ${T['rejected'] || 'Rejected'}</span>
                        <span class="ppv-status-value">${formatNumber(status.rejected || 0)}</span>
                    </div>
                </div>
            </div>
        `;

        if (topRewards.length > 0) {
            html += `<div class="ppv-top-rewards"><h4>${T['top_rewards'] || 'Top Rewards'}</h4><div class="ppv-top-rewards-list">`;
            topRewards.forEach((r, i) => {
                html += `
                    <div class="ppv-reward-item">
                        <span class="ppv-reward-rank">${i + 1}</span>
                        <span class="ppv-reward-id">Reward #${r.reward_id}</span>
                        <span class="ppv-reward-stats">${r.redeemed_count}x</span>
                        <span class="ppv-reward-total">${formatNumber(r.total_spent)} pts</span>
                    </div>
                `;
            });
            html += '</div></div>';
        }

        $('#ppv-spending').html(html);
    }

    // ============================================================
    // 6️⃣ LOAD CONVERSION
    // ============================================================
    function loadConversion() {

        $.ajax({
            url: config.conversion_url,
            method: 'GET',
            data: { filiale_id: currentFilialeId },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {
                if (res.success) {
                    displayConversion(res);
                }
            },
            error: function() {
                $('#ppv-conversion').html(`<p class="ppv-error-small">${T['error_loading'] || 'Error'}</p>`);
            }
        });
    }

    function displayConversion(data) {
        const total = data.total_users || 0;
        const redeemed = data.redeemed_users || 0;
        const rate = data.conversion_rate || 0;

        let html = `
            <div class="ppv-conversion-metrics">
                <div class="ppv-metric-card">
                    <div class="ppv-metric-main">${total}</div>
                    <div class="ppv-metric-label">${T['total_users'] || 'Total Users'}</div>
                </div>

                <div class="ppv-metric-card highlighted">
                    <div class="ppv-metric-main">${rate.toFixed(1)}%</div>
                    <div class="ppv-metric-label">${T['conversion_rate'] || 'Conversion Rate'}</div>
                    <div class="ppv-metric-sub">${redeemed} ${(T['redeemed_users'] || 'Users').replace('%d', redeemed)}</div>
                </div>

                <div class="ppv-metric-card">
                    <div class="ppv-metric-main">${data.repeat_customers || 0}</div>
                    <div class="ppv-metric-label">${T['repeat_customers'] || 'Repeat Customers'}</div>
                    <div class="ppv-metric-sub">${data.repeat_rate ? data.repeat_rate.toFixed(1) : 0}%</div>
                </div>

                <div class="ppv-metric-card">
                    <div class="ppv-metric-main">${formatNumber(data.average_points_per_user || 0)}</div>
                    <div class="ppv-metric-label">${T['avg_points_per_user'] || 'Avg Points/User'}</div>
                </div>

                <div class="ppv-metric-card">
                    <div class="ppv-metric-main">${(data.average_redemptions_per_user || 0).toFixed(1)}</div>
                    <div class="ppv-metric-label">${T['avg_redemptions_per_user'] || 'Avg Redemptions'}</div>
                </div>
            </div>

            <div class="ppv-conversion-chart">
                <h4>${T['user_segmentation'] || 'User Segmentation'}</h4>
                <div class="ppv-segment">
                    <div class="ppv-segment-bar">
                        <div class="ppv-segment-part redeemed" style="width: ${total > 0 ? (redeemed/total)*100 : 0}%"></div>
                        <div class="ppv-segment-part unredeemed" style="width: ${total > 0 ? ((total-redeemed)/total)*100 : 100}%"></div>
                    </div>
                    <div class="ppv-segment-legend">
                        <span class="ppv-legend-item redeemed">📊 ${T['redeemed'] || 'Redeemed'} (${redeemed})</span>
                        <span class="ppv-legend-item unredeemed">⭕ ${T['not_redeemed'] || 'Not Redeemed'} (${total - redeemed})</span>
                    </div>
                </div>
            </div>
        `;

        $('#ppv-conversion').html(html);
    }

    // ============================================================
    // 7️⃣ EXPORT ADVANCED
    // ============================================================
    $exportAdvBtn.on('click', function() {
        const format = $exportFormatSelect.val() || 'detailed';

        const $btn = $(this);
        const txt = $btn.text();
        $btn.prop('disabled', true).text(T['exporting'] || '⏳ Exporting...');

        $.ajax({
            url: config.export_adv_url,
            method: 'GET',
            data: { format: format },
            headers: { 'X-WP-Nonce': nonce },
            cache: false,
            success: function(res) {
                if (res.success) {
                    const blob = new Blob([res.csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);

                    link.href = url;
                    link.download = res.filename || 'stats.csv';
                    link.style.display = 'none';

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                }
            },
            error: function() {
                alert(T['export_failed'] || 'Export failed');
            },
            complete: function() {
                $btn.prop('disabled', false).text(txt);
            }
        });
    });

    // ============================================================
    // 💾 BASIC CSV EXPORT
    // ============================================================
    $exportBtn.on('click', function() {
        const range = $rangeSelect.val();

        const $btn = $(this);
        const txt = $btn.html();
        $btn.prop('disabled', true).html(`<i class="ri-loader-4-line ri-spin"></i> ${T['exporting'] || 'Exporting...'}`);

        $.ajax({
            url: config.export_url,
            method: 'GET',
            data: { range: range },
            headers: { 'X-WP-Nonce': nonce },
            cache: false,
            success: function(res) {
                if (res.success) {
                    const blob = new Blob([res.csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);

                    link.href = url;
                    link.download = res.filename || 'stats.csv';
                    link.style.display = 'none';

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            },
            error: function() {
                alert(T['export_failed'] || 'Export failed');
            },
            complete: function() {
                $btn.prop('disabled', false).html(txt);
            }
        });
    });

    // ============================================================
    // 🔄 FILTER CHANGE
    // ============================================================
    $rangeSelect.on('change', function() {
        const range = $(this).val();
        loadBasicStats(range);
    });

    // ============================================================
    // 🏢 FILIALE CHANGE
    // ============================================================
    $filialeSelect.on('change', function() {
        currentFilialeId = $(this).val();

        // Reload all stats with new filiale
        const range = $rangeSelect.val();
        loadBasicStats(range);
        loadTrend();
        loadSpending();
        loadConversion();
        loadScannerStats();
    });

    // ============================================================
    // 📑 TAB SWITCHING
    // ============================================================
    let scannerStatsLoaded = false;

    $('.ppv-stats-tab').on('click', function() {
        const tab = $(this).data('tab');

        // Update tab buttons
        $('.ppv-stats-tab').removeClass('active');
        $(this).addClass('active');

        // Update tab content
        $('.ppv-stats-tab-content').removeClass('active');
        $(`#ppv-tab-${tab}`).addClass('active');

        // Load scanner stats on first view
        if (tab === 'scanners' && !scannerStatsLoaded) {
            loadScannerStats();
            scannerStatsLoaded = true;
        }

        // Load suspicious scans on first view
        if (tab === 'suspicious' && !suspiciousStatsLoaded) {
            loadSuspiciousScans();
            suspiciousStatsLoaded = true;
        }

        // Load device activity on first view
        if (tab === 'device-activity' && !deviceActivityLoaded) {
            loadDeviceActivity();
            deviceActivityLoaded = true;
        }
    });

    // ============================================================
    // ⚠️ SUSPICIOUS SCANS
    // ============================================================
    let suspiciousStatsLoaded = false;

    // Status filter change
    $('#ppv-suspicious-status').on('change', function() {
        loadSuspiciousScans();
    });

    function loadSuspiciousScans() {
        if (!config.suspicious_url) {
            return;
        }

        const status = $('#ppv-suspicious-status').val() || 'new';

        const $loading = $('#ppv-suspicious-loading');
        const $list = $('#ppv-suspicious-list');

        $loading.show();

        $.ajax({
            url: config.suspicious_url,
            method: 'GET',
            data: { status: status },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {
                $loading.hide();

                if (res.success) {
                    displaySuspiciousScans(res);
                    updateSuspiciousBadge(res.counts);
                } else {
                    $list.html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading data'}</p>`);
                }
            },
            error: function() {
                $loading.hide();
                $list.html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading data'}</p>`);
            }
        });
    }

    function displaySuspiciousScans(data) {
        const scans = data.scans || [];
        const counts = data.counts || {};
        const $list = $('#ppv-suspicious-list');

        if (scans.length === 0) {
            $list.html(`<p class="ppv-no-data">${T['no_suspicious_scans'] || 'Keine verdächtigen Scans vorhanden.'}</p>`);
            return;
        }

        let html = '<div class="ppv-suspicious-table">';

        // Table header
        html += `
            <div class="ppv-suspicious-row ppv-suspicious-header">
                <div class="ppv-suspicious-cell">${T['user'] || 'Benutzer'}</div>
                <div class="ppv-suspicious-cell">${T['distance'] || 'Entfernung'}</div>
                <div class="ppv-suspicious-cell">${T['status'] || 'Status'}</div>
                <div class="ppv-suspicious-cell">${T['date'] || 'Datum'}</div>
                <div class="ppv-suspicious-cell">${T['actions'] || 'Aktionen'}</div>
            </div>
        `;

        // Scan rows
        scans.forEach(scan => {
            const statusClass = scan.status === 'new' ? 'ppv-status-warning' :
                               scan.status === 'reviewed' ? 'ppv-status-info' :
                               scan.status === 'dismissed' ? 'ppv-status-success' : '';

            const statusLabel = {
                'new': T['status_new'] || 'Neu',
                'reviewed': T['status_reviewed'] || 'Überprüft',
                'dismissed': T['status_dismissed'] || 'Abgewiesen',
                'blocked': T['status_blocked'] || 'Gesperrt'
            }[scan.status] || scan.status;

            const dateFormatted = new Date(scan.created_at).toLocaleString('de-DE', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });

            html += `
                <div class="ppv-suspicious-row ${scan.status === 'new' ? 'ppv-row-highlight' : ''}">
                    <div class="ppv-suspicious-cell ppv-user-info">
                        <strong>${escapeHtml(scan.user_name)}</strong>
                        ${scan.user_email ? `<br><small>${escapeHtml(scan.user_email)}</small>` : ''}
                    </div>
                    <div class="ppv-suspicious-cell ppv-distance">
                        <span class="ppv-distance-value">${scan.distance_km} km</span>
                    </div>
                    <div class="ppv-suspicious-cell">
                        <span class="ppv-status-badge ${statusClass}">${statusLabel}</span>
                    </div>
                    <div class="ppv-suspicious-cell ppv-date">
                        ${dateFormatted}
                    </div>
                    <div class="ppv-suspicious-cell ppv-actions">
                        <a href="${scan.maps_link}" target="_blank" class="ppv-btn-small" title="${T['view_on_map'] || 'Auf Karte anzeigen'}">
                            <i class="ri-map-pin-line"></i>
                        </a>
                        <button class="ppv-btn-small ppv-btn-review-request" data-scan-id="${scan.id}" data-user-name="${escapeHtml(scan.user_name)}" title="${T['request_review'] || 'Admin Überprüfung anfordern'}">
                            <i class="ri-mail-send-line"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        $list.html(html);
    }

    function updateSuspiciousBadge(counts) {
        const newCount = counts?.new || 0;
        const $badge = $('#ppv-suspicious-badge');

        if (newCount > 0) {
            $badge.text(newCount).show();
        } else {
            $badge.hide();
        }
    }

    // ============================================================
    // 📧 REQUEST ADMIN REVIEW
    // ============================================================
    $(document).on('click', '.ppv-btn-review-request', function() {
        const $btn = $(this);
        const scanId = $btn.data('scan-id');
        const userName = $btn.data('user-name');

        if ($btn.hasClass('ppv-btn-disabled')) return;

        const confirmMsg = T['confirm_review_request'] || `Admin Überprüfung für "${userName}" anfordern?`;
        if (!confirm(confirmMsg)) return;

        $btn.addClass('ppv-btn-disabled').find('i').removeClass('ri-mail-send-line').addClass('ri-loader-4-line ri-spin');

        $.ajax({
            url: config.review_request_url || (config.ajax_url.replace('/stats', '/stats/request-review')),
            method: 'POST',
            data: JSON.stringify({ scan_id: scanId }),
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': nonce },
            success: function(res) {
                if (res.success) {
                    alert(T['review_requested'] || '✅ Admin Überprüfung wurde angefordert!');
                    $btn.find('i').removeClass('ri-loader-4-line ri-spin').addClass('ri-check-line');
                    $btn.addClass('ppv-btn-success');
                } else {
                    alert(T['review_request_failed'] || '❌ Fehler: ' + (res.error || 'Unbekannter Fehler'));
                    $btn.removeClass('ppv-btn-disabled').find('i').removeClass('ri-loader-4-line ri-spin').addClass('ri-mail-send-line');
                }
            },
            error: function() {
                alert(T['review_request_failed'] || '❌ Netzwerkfehler');
                $btn.removeClass('ppv-btn-disabled').find('i').removeClass('ri-loader-4-line ri-spin').addClass('ri-mail-send-line');
            }
        });
    });

    // ============================================================
    // 👤 LOAD SCANNER STATS
    // ============================================================
    function loadScannerStats() {

        const $loading = $('#ppv-scanner-stats-loading');
        const $list = $('#ppv-scanner-list');

        $loading.show();

        $.ajax({
            url: config.scanner_url,
            method: 'GET',
            data: { filiale_id: currentFilialeId },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {
                $loading.hide();

                if (res.success) {
                    displayScannerStats(res);
                } else {
                    $list.html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading data'}</p>`);
                }
            },
            error: function() {
                $loading.hide();
                $list.html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading data'}</p>`);
            }
        });
    }

    function displayScannerStats(data) {
        const scanners = data.scanners || [];
        const summary = data.summary || {};
        const untracked = data.untracked || {};

        // Update summary cards
        $('#ppv-scanner-count').text(formatNumber(summary.scanner_count || 0));
        $('#ppv-tracked-scans').text(formatNumber(summary.total_tracked || 0));
        $('#ppv-untracked-scans').text(formatNumber(summary.total_untracked || 0));

        // Build scanner list
        const $list = $('#ppv-scanner-list');

        if (scanners.length === 0) {
            $list.html(`<p class="ppv-no-data">${T['no_scanner_data'] || 'Noch keine Scanner-Daten vorhanden.'}</p>`);
            return;
        }

        let html = '<div class="ppv-scanner-table">';

        // Helper: format scan + points (e.g. "2 (4P)")
        const formatScanPoints = (scans, points) => {
            if (scans === 0 && points === 0) return '0';
            if (points && points !== scans) {
                return `${formatNumber(scans)} <span class="ppv-points-badge">(${formatNumber(points)}P)</span>`;
            }
            return formatNumber(scans);
        };

        // Table header
        html += `
            <div class="ppv-scanner-row ppv-scanner-header">
                <div class="ppv-scanner-cell">${T['employee'] || 'Mitarbeiter'}</div>
                <div class="ppv-scanner-cell">${T['today'] || 'Heute'}</div>
                <div class="ppv-scanner-cell">${T['this_week'] || 'Woche'}</div>
                <div class="ppv-scanner-cell">${T['this_month'] || 'Monat'}</div>
                <div class="ppv-scanner-cell">${T['total'] || 'Gesamt'}</div>
            </div>
        `;

        // Scanner rows - show scans (points) format
        scanners.forEach((scanner, index) => {
            const rankClass = index < 3 ? `ppv-rank-${index + 1}` : '';
            const rankIcon = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';

            html += `
                <div class="ppv-scanner-row ${rankClass}">
                    <div class="ppv-scanner-cell ppv-scanner-name">
                        ${rankIcon} ${escapeHtml(scanner.scanner_name)}
                    </div>
                    <div class="ppv-scanner-cell">${formatScanPoints(scanner.today_scans, scanner.today_points)}</div>
                    <div class="ppv-scanner-cell">${formatScanPoints(scanner.week_scans, scanner.week_points)}</div>
                    <div class="ppv-scanner-cell">${formatScanPoints(scanner.month_scans, scanner.month_points)}</div>
                    <div class="ppv-scanner-cell ppv-scanner-total">${formatScanPoints(scanner.total_scans, scanner.total_points)}</div>
                </div>
            `;
        });

        // Untracked row (if any)
        if (untracked.total_scans > 0) {
            html += `
                <div class="ppv-scanner-row ppv-scanner-untracked">
                    <div class="ppv-scanner-cell ppv-scanner-name">
                        ⚠️ ${T['untracked'] || 'Ohne Zuordnung'}
                    </div>
                    <div class="ppv-scanner-cell">${formatNumber(untracked.today_scans)}</div>
                    <div class="ppv-scanner-cell">${formatNumber(untracked.week_scans)}</div>
                    <div class="ppv-scanner-cell">-</div>
                    <div class="ppv-scanner-cell ppv-scanner-total">${formatNumber(untracked.total_scans)}</div>
                </div>
            `;
        }

        html += '</div>';
        $list.html(html);
    }

    // ============================================================
    // 📱 DEVICE ACTIVITY
    // ============================================================
    let deviceActivityLoaded = false;

    // Load device activity when tab is clicked
    $('.ppv-stats-tab[data-tab="device-activity"]').on('click', function() {
        if (!deviceActivityLoaded) {
            loadDeviceActivity();
            deviceActivityLoaded = true;
        }
    });

    function loadDeviceActivity() {
        if (!config.device_activity_url) {
            console.log("📱 [Device Activity] URL not configured");
            return;
        }

        const $loading = $('#ppv-device-loading');
        const $list = $('#ppv-device-activity-list');

        $loading.show();

        $.ajax({
            url: config.device_activity_url,
            method: 'GET',
            data: { filiale_id: currentFilialeId },
            headers: { 'X-WP-Nonce': nonce },
            dataType: 'json',
            cache: false,
            success: function(res) {
                $loading.hide();

                if (res.success) {
                    displayDeviceActivity(res);
                } else {
                    $list.html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading data'}</p>`);
                }
            },
            error: function() {
                $loading.hide();
                $list.html(`<p class="ppv-error-small">${T['error_loading'] || 'Error loading data'}</p>`);
            }
        });
    }

    function displayDeviceActivity(data) {
        const devices = data.devices || [];
        const dates = data.dates || [];
        const summary = data.summary || {};
        const $list = $('#ppv-device-activity-list');

        // Update summary stats
        $('#ppv-device-count').text(formatNumber(summary.total_devices || 0));
        $('#ppv-mobile-scanner-count').text(formatNumber(summary.mobile_scanner_count || 0));
        $('#ppv-suspicious-device-count').text(formatNumber(summary.suspicious_count || 0));

        if (devices.length === 0) {
            $list.html(`<p class="ppv-no-data">${T['no_device_data'] || 'Noch keine Gerätedaten vorhanden.'}</p>`);
            return;
        }

        // Format date headers (show only day name)
        const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        const dateHeaders = dates.map(d => {
            const date = new Date(d);
            return dayNames[date.getDay()];
        });

        let html = '<div class="ppv-device-table">';

        // Table header
        html += `
            <div class="ppv-device-row ppv-device-header">
                <div class="ppv-device-cell ppv-device-name-cell">${T['device'] || 'Gerät'}</div>
                ${dateHeaders.map(d => `<div class="ppv-device-cell ppv-device-day-cell">${d}</div>`).join('')}
                <div class="ppv-device-cell ppv-device-total-cell">${T['total'] || 'Ges.'}</div>
            </div>
        `;

        // Device rows
        devices.forEach(device => {
            const suspiciousClass = device.is_suspicious ? 'ppv-device-suspicious' : '';
            const scannerBadge = device.is_mobile_scanner ? '<span class="ppv-badge-scanner">📱</span>' : '';
            const suspiciousBadge = device.is_suspicious ? '<span class="ppv-badge-warning">⚠️</span>' : '';

            // Generate mini bar chart for daily scans
            const maxScan = Math.max(...device.daily_scans, 1);
            const dailyCells = device.daily_scans.map(count => {
                const height = Math.round((count / maxScan) * 100);
                const barClass = count > 50 ? 'ppv-bar-high' : count > 20 ? 'ppv-bar-medium' : 'ppv-bar-low';
                return `
                    <div class="ppv-device-cell ppv-device-day-cell">
                        <div class="ppv-mini-bar ${barClass}" style="height: ${Math.max(height, 5)}%;" title="${count} Scans"></div>
                        <span class="ppv-day-count">${count || '-'}</span>
                    </div>
                `;
            }).join('');

            html += `
                <div class="ppv-device-row ${suspiciousClass}">
                    <div class="ppv-device-cell ppv-device-name-cell">
                        <span class="ppv-device-name">${escapeHtml(device.name)}</span>
                        ${scannerBadge}${suspiciousBadge}
                        <span class="ppv-device-fingerprint">${device.fingerprint}</span>
                    </div>
                    ${dailyCells}
                    <div class="ppv-device-cell ppv-device-total-cell">
                        <strong>${formatNumber(device.total_scans)}</strong>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        $list.html(html);
    }

    // ============================================================
    // 🚀 INIT
    // ============================================================

    if (!config.ajax_url) {
        console.error("❌ Config invalid!");
        return;
    }

    // Check if user has a store (is a handler/merchant)
    if (!config.store_id || config.store_id === 0) {
        $loading.hide();
        $content.hide();
        $error.show().find('p').html('ℹ️ ' + (T['no_store_access'] || 'Statistics only available for merchants'));
        return;
    }

    // Load all sections
    loadBasicStats('week');
    loadTrend();
    loadSpending();
    loadConversion();

    // Check URL for tab parameter (e.g., ?tab=suspicious)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        const $tabBtn = $(`.ppv-stats-tab[data-tab="${tabParam}"]`);
        if ($tabBtn.length) {
            $tabBtn.trigger('click');
        }
    }

    // Also check for hash (e.g., #suspicious)
    if (window.location.hash) {
        const hashTab = window.location.hash.substring(1);
        const $tabBtn = $(`.ppv-stats-tab[data-tab="${hashTab}"]`);
        if ($tabBtn.length) {
            $tabBtn.trigger('click');
        }
    }

});