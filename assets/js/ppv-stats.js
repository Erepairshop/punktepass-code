/**
 * PunktePass – Complete Stats Dashboard JS (v3.0)
 * ✅ Basic Stats (1-3): Daily, Top5, Peak Hours
 * ✅ Advanced Stats (4-7): Trend, Spending, Conversion, Export
 * ✅ ONE FILE, ALL FUNCTIONALITY
 */

jQuery(document).ready(function($) {
    console.log("📊 [Stats COMPLETE JS v3.0] Loaded");

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

    console.log("✅ [Stats] Config OK, filialen:", config.filialen?.length || 0);

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
        console.log(`📊 [Basic Stats] Loading range: ${range}, filiale: ${currentFilialeId}`);

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
                console.log("✅ [Basic] Response OK");

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
        console.log("🎨 [Basic] Updating");

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

        console.log("✅ [Basic] Updated");
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
        console.log("✅ [Top5] Updated");
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
        console.log("✅ [Peak] Updated");
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

            console.log("✅ [Chart] OK");
        } catch (e) {
            console.error("❌ [Chart] Error:", e.message);
        }
    }

    // ============================================================
    // 4️⃣ LOAD TREND
    // ============================================================
    function loadTrend() {
        console.log(`📈 [Trend] Loading... filiale: ${currentFilialeId}`);

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
                    console.log("✅ [Trend] OK");
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
        console.log(`💰 [Spending] Loading... filiale: ${currentFilialeId}`);

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
                    console.log("✅ [Spending] OK");
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
        console.log(`📊 [Conversion] Loading... filiale: ${currentFilialeId}`);

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
                    console.log("✅ [Conversion] OK");
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
        console.log(`📥 [Export Advanced] Format: ${format}`);

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

                    console.log("✅ [Export Advanced] Downloaded");
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
        console.log(`📥 [Export Basic] Range: ${range}`);

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
        console.log(`🏢 [Filiale] Changed to: ${currentFilialeId}`);

        // Reload all stats with new filiale
        const range = $rangeSelect.val();
        loadBasicStats(range);
        loadTrend();
        loadSpending();
        loadConversion();
    });

    // ============================================================
    // 🚀 INIT
    // ============================================================
    console.log("🚀 [Stats COMPLETE] Initializing...");

    if (!config.ajax_url) {
        console.error("❌ Config invalid!");
        return;
    }

    // Check if user has a store (is a handler/merchant)
    if (!config.store_id || config.store_id === 0) {
        console.log("ℹ️ [Stats] No store ID - stats not available for this user");
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

    console.log("✅ [Stats COMPLETE] Ready!");
});