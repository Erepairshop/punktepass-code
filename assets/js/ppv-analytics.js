/**
 * PunktePass – Analytics Dashboard (v2.0)
 * ✅ Fully translated
 * ✅ Supports: German, Hungarian, Romanian
 * ✅ getLabels() function
 */

class PPV_Analytics {
  constructor() {
    this.data = null;
    this.stores = null;
    this.summary = null;
    this.range = 30;
    this.container = null;
    this.DEBUG = false;
  }

  /** ============================================================
   * 🌍 DEFAULT STRINGS (FALLBACK)
   * ============================================================ */
  getDefaultStrings() {
    return {
      de: {
        analytics_title: 'Punkt Analytik',
        analytics_range_label: 'Zeitraum:',
        analytics_range_7: '7 Tage',
        analytics_range_30: '30 Tage',
        analytics_range_90: '90 Tage',
        analytics_range_365: '1 Jahr',
        analytics_this_week: 'Diese Woche',
        analytics_this_month: 'Dieser Monat',
        analytics_this_year: 'Dieses Jahr',
        analytics_current_streak: 'Aktuelle Serie',
        analytics_points_unit: 'Punkte',
        analytics_days_unit: 'Tage',
        analytics_trend_title: 'Punkttrend',
        analytics_stores_title: 'Aufschlüsselung nach Geschäft',
        analytics_best_day_title: 'Bester Tag',
        analytics_visits: 'Besuche',
        analytics_avg_points: 'Ø Punkte',
        analytics_percentage: '% Anteil',
        analytics_no_data: 'Keine Daten',
        analytics_error_title: 'Fehler beim Laden der Analytik:',
      },
      hu: {
        analytics_title: 'Pont Analitika',
        analytics_range_label: 'Időtartam:',
        analytics_range_7: '7 nap',
        analytics_range_30: '30 nap',
        analytics_range_90: '90 nap',
        analytics_range_365: '1 év',
        analytics_this_week: 'Ez a hét',
        analytics_this_month: 'Ez a hónap',
        analytics_this_year: 'Ez az év',
        analytics_current_streak: 'Jelenlegi csík',
        analytics_points_unit: 'pont',
        analytics_days_unit: 'nap',
        analytics_trend_title: 'Pont trend',
        analytics_stores_title: 'Üzletek szerinti bontás',
        analytics_best_day_title: 'Legjobb nap',
        analytics_visits: 'látogatás',
        analytics_avg_points: 'átlag pont',
        analytics_percentage: '% részesedés',
        analytics_no_data: 'Nincs adat',
        analytics_error_title: 'Hiba az analitika betöltésekor:',
      },
      ro: {
        analytics_title: 'Analitică puncte',
        analytics_range_label: 'Perioada:',
        analytics_range_7: '7 zile',
        analytics_range_30: '30 zile',
        analytics_range_90: '90 zile',
        analytics_range_365: '1 an',
        analytics_this_week: 'Această săptămână',
        analytics_this_month: 'Această lună',
        analytics_this_year: 'Acest an',
        analytics_current_streak: 'Șirul actual',
        analytics_points_unit: 'puncte',
        analytics_days_unit: 'zile',
        analytics_trend_title: 'Tendință puncte',
        analytics_stores_title: 'Defalcare pe magazin',
        analytics_best_day_title: 'Ziua cea mai bună',
        analytics_visits: 'vizite',
        analytics_avg_points: 'puncte medii',
        analytics_percentage: '% cotă',
        analytics_no_data: 'Fără date',
        analytics_error_title: 'Eroare la încărcarea analizei:',
      }
    };
  }

  /** ============================================================
   * 🌍 GET LABELS (Server + Fallback)
   * ============================================================ */
  getLabels(lang = 'de') {
    const serverLabels = window.ppv_lang || {};
    const defaults = this.getDefaultStrings()[lang] || this.getDefaultStrings().de;
    const merged = Object.assign({}, defaults, serverLabels);
    
    console.log(`🌍 [Analytics] Labels for ${lang}: ${Object.keys(merged).length} strings`);
    return merged;
  }

  /** ============================================================
   * INITIALIZE
   * ============================================================ */
  async init(containerId = 'ppv-analytics-section') {
    this.container = document.getElementById(containerId);
    if (!this.container) {
      console.warn('🚨 [Analytics] Container not found:', containerId);
      return;
    }

    console.log('📊 [Analytics] Initializing...');
    
    try {
      // Fetch data
      await this.fetchData();
      
      // Render UI
      this.render();
      
      // Setup event listeners
      this.setupEventListeners();
      
      console.log('✅ [Analytics] Ready');
    } catch (err) {
      console.error('❌ [Analytics] Error:', err);
      this.renderError(err.message);
    }
  }

  /** ============================================================
   * FETCH DATA FROM API
   * ============================================================ */
  async fetchData() {
    console.log('📡 [Analytics] Fetching data...');

    try {
      // Get language
      const lang = window.ppv_mypoints?.lang || 'de';

      // ✅ NE küldjünk WordPress nonce-t!
      // Same fix as My Points - session-based auth, no WordPress nonce needed
      console.log('📡 [Analytics] NOT sending X-WP-Nonce (using session-based auth)');

      // Fetch trend data
      const trendRes = await fetch(
        `/wp-json/ppv/v1/analytics/trend?range=${this.range}`,
        {
          headers: {
            'X-PPV-Lang': lang,
          },
          credentials: 'include',
        }
      );

      if (!trendRes.ok) throw new Error('Trend fetch failed: ' + trendRes.status);
      this.data = await trendRes.json();

      // Fetch store breakdown
      const storesRes = await fetch(
        `/wp-json/ppv/v1/analytics/stores?range=${this.range}`,
        {
          headers: {
            'X-PPV-Lang': lang,
          },
          credentials: 'include',
        }
      );

      if (!storesRes.ok) throw new Error('Stores fetch failed: ' + storesRes.status);
      this.stores = await storesRes.json();

      // Fetch summary
      const summaryRes = await fetch(
        '/wp-json/ppv/v1/analytics/summary',
        {
          headers: {
            'X-PPV-Lang': lang,
          },
          credentials: 'include',
        }
      );

      if (!summaryRes.ok) throw new Error('Summary fetch failed: ' + summaryRes.status);
      this.summary = await summaryRes.json();

      console.log('✅ [Analytics] Data loaded');
      if (this.DEBUG) {
        console.log('📊 Trend:', this.data);
        console.log('🏪 Stores:', this.stores);
        console.log('📈 Summary:', this.summary);
      }
    } catch (err) {
      console.error('❌ [Analytics] Fetch error:', err);
      throw err;
    }
  }

  /** ============================================================
   * RENDER UI
   * ============================================================ */
  render() {
    if (!this.container) return;

    // Get language
    const lang = window.ppv_mypoints?.lang || 'de';
    const l = this.getLabels(lang);

    const html = `
      <div class="ppv-analytics-wrapper">
        <!-- Header -->
        <div class="ppv-analytics-header">
          <h3><i class="ri-bar-chart-2-line"></i> ${l.analytics_title}</h3>
          <div class="ppv-analytics-controls">
            <label>${l.analytics_range_label}</label>
            <select id="ppv-analytics-range" class="ppv-analytics-range-select">
              <option value="7">${l.analytics_range_7}</option>
              <option value="30" selected>${l.analytics_range_30}</option>
              <option value="90">${l.analytics_range_90}</option>
              <option value="365">${l.analytics_range_365}</option>
            </select>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="ppv-analytics-summary">
          <div class="ppv-summary-card">
            <div class="card-icon"><i class="ri-calendar-event-fill"></i></div>
            <div class="card-content">
              <div class="label">${l.analytics_this_week}</div>
              <div class="value" id="ppv-week-points">0</div>
              <div class="unit">${l.analytics_points_unit}</div>
            </div>
          </div>

          <div class="ppv-summary-card">
            <div class="card-icon"><i class="ri-calendar-2-fill"></i></div>
            <div class="card-content">
              <div class="label">${l.analytics_this_month}</div>
              <div class="value" id="ppv-month-points">0</div>
              <div class="unit">${l.analytics_points_unit}</div>
            </div>
          </div>

          <div class="ppv-summary-card">
            <div class="card-icon"><i class="ri-calendar-year-fill"></i></div>
            <div class="card-content">
              <div class="label">${l.analytics_this_year}</div>
              <div class="value" id="ppv-year-points">0</div>
              <div class="unit">${l.analytics_points_unit}</div>
            </div>
          </div>

          <div class="ppv-summary-card">
            <div class="card-icon"><i class="ri-fire-fill"></i></div>
            <div class="card-content">
              <div class="label">${l.analytics_current_streak}</div>
              <div class="value" id="ppv-streak">0</div>
              <div class="unit">${l.analytics_days_unit}</div>
            </div>
          </div>
        </div>

        <!-- Trend Chart -->
        <div class="ppv-analytics-section">
          <h4>${l.analytics_trend_title}</h4>
          <div id="ppv-chart-trend" class="ppv-chart-container"></div>
        </div>

        <!-- Store Breakdown Chart -->
        <div class="ppv-analytics-section">
          <h4>${l.analytics_stores_title}</h4>
          <div class="ppv-stores-breakdown">
            <div id="ppv-chart-stores" class="ppv-chart-container ppv-chart-pie"></div>
            <div id="ppv-stores-list" class="ppv-stores-list"></div>
          </div>
        </div>

        <!-- Best Day Info -->
        <div class="ppv-analytics-section">
          <h4>${l.analytics_best_day_title}</h4>
          <div id="ppv-best-day" class="ppv-best-day-card"></div>
        </div>
      </div>
    `;

    this.container.innerHTML = html;

    // Populate data
    this.populateSummary();
    this.renderTrendChart();
    this.renderStoresChart();
    this.renderStoresList();
    this.renderBestDay();
  }

  /** ============================================================
   * POPULATE SUMMARY CARDS
   * ============================================================ */
  populateSummary() {
    if (!this.summary?.summary) return;

    const s = this.summary.summary;
    
    document.getElementById('ppv-week-points').textContent = s.week_points.toLocaleString();
    document.getElementById('ppv-month-points').textContent = s.month_points.toLocaleString();
    document.getElementById('ppv-year-points').textContent = s.year_points.toLocaleString();
    document.getElementById('ppv-streak').textContent = s.current_streak;
  }

  /** ============================================================
   * RENDER TREND CHART (LINE CHART)
   * ============================================================ */
  renderTrendChart() {
    const lang = window.ppv_mypoints?.lang || 'de';
    const l = this.getLabels(lang);

    if (!this.data?.trend || this.data.trend.length === 0) {
      document.getElementById('ppv-chart-trend').innerHTML = `<p>${l.analytics_no_data}</p>`;
      return;
    }

    const container = document.getElementById('ppv-chart-trend');
    const data = this.data.trend;

    // Format data
    const chartData = data.map(d => ({
      date: d.short_date,
      points: d.points,
      count: d.count,
    }));

    // Simple SVG chart (fallback if Recharts not available)
    const html = this.generateTrendChartSVG(chartData);
    container.innerHTML = html;
  }

  /** ============================================================
   * RENDER STORES CHART (PIE CHART)
   * ============================================================ */
  renderStoresChart() {
    const lang = window.ppv_mypoints?.lang || 'de';
    const l = this.getLabels(lang);

    if (!this.stores?.stores || this.stores.stores.length === 0) {
      document.getElementById('ppv-chart-stores').innerHTML = `<p>${l.analytics_no_data}</p>`;
      return;
    }

    const container = document.getElementById('ppv-chart-stores');
    const stores = this.stores.stores.slice(0, 5); // Top 5

    const html = this.generatePieChartSVG(stores);
    container.innerHTML = html;
  }

  /** ============================================================
   * RENDER STORES LIST
   * ============================================================ */
  renderStoresList() {
    const lang = window.ppv_mypoints?.lang || 'de';
    const l = this.getLabels(lang);

    if (!this.stores?.stores || this.stores.stores.length === 0) {
      document.getElementById('ppv-stores-list').innerHTML = `<p>${l.analytics_no_data}</p>`;
      return;
    }

    const stores = this.stores.stores;
    let html = '';

    stores.forEach((store, index) => {
      const colors = ['#667eea', '#764ba2', '#f59e0b', '#10b981', '#ef4444'];
      const color = colors[index % colors.length];

      html += `
        <div class="ppv-store-item">
          <div class="store-color" style="background-color: ${color};"></div>
          <div class="store-info">
            <div class="store-name">${store.name}</div>
            <div class="store-stats">
              <span>${store.visits} ${l.analytics_visits}</span>
              <span>•</span>
              <span>${store.avg_points} ${l.analytics_avg_points}</span>
            </div>
          </div>
          <div class="store-points">
            <div class="points">${store.points}</div>
            <div class="percentage">${store.percentage}${l.analytics_percentage}</div>
          </div>
        </div>
      `;
    });

    document.getElementById('ppv-stores-list').innerHTML = html;
  }

  /** ============================================================
   * RENDER BEST DAY
   * ============================================================ */
  renderBestDay() {
    const lang = window.ppv_mypoints?.lang || 'de';
    const l = this.getLabels(lang);

    if (!this.summary?.summary?.best_day) {
      document.getElementById('ppv-best-day').innerHTML = `<p>${l.analytics_no_data}</p>`;
      return;
    }

    const best = this.summary.summary.best_day;
    const date = new Date(best.date + 'T00:00:00');
    
    // Format date in current language
    const formatted = date.toLocaleDateString(
      lang === 'hu' ? 'hu-HU' : lang === 'ro' ? 'ro-RO' : 'de-DE',
      {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      }
    );

    const html = `
      <div class="best-day-content">
        <div class="best-day-icon">🏆</div>
        <div class="best-day-info">
          <div class="date">${formatted}</div>
          <div class="points">${best.points} ${l.analytics_points_unit}</div>
        </div>
      </div>
    `;

    document.getElementById('ppv-best-day').innerHTML = html;
  }

  /** ============================================================
   * GENERATE SVG TREND CHART (Fallback)
   * ============================================================ */
  generateTrendChartSVG(data) {
    if (data.length === 0) return '<p>Keine Daten</p>';

    const padding = 40;
    const width = 500;
    const height = 300;
    const graphWidth = width - padding * 2;
    const graphHeight = height - padding * 2;

    const maxPoints = Math.max(...data.map(d => d.points)) || 100;
    const xStep = graphWidth / (data.length - 1 || 1);

    // Generate path
    let pathData = '';
    data.forEach((d, i) => {
      const x = padding + i * xStep;
      const y = height - padding - (d.points / maxPoints) * graphHeight;

      if (i === 0) {
        pathData += `M ${x} ${y}`;
      } else {
        pathData += ` L ${x} ${y}`;
      }
    });

    // Generate points
    let pointsHtml = '';
    data.forEach((d, i) => {
      const x = padding + i * xStep;
      const y = height - padding - (d.points / maxPoints) * graphHeight;

      pointsHtml += `
        <circle cx="${x}" cy="${y}" r="4" fill="#667eea" 
                opacity="0.7" class="ppv-chart-point"
                title="${d.date}: ${d.points}">
          <title>${d.date}: ${d.points}</title>
        </circle>
      `;
    });

    // Generate grid lines
    let gridHtml = '';
    for (let i = 0; i <= 5; i++) {
      const y = height - padding - (graphHeight / 5) * i;
      const value = Math.round((maxPoints / 5) * i);
      gridHtml += `
        <line x1="${padding}" y1="${y}" x2="${width - padding}" y2="${y}" 
              stroke="#e2e8f0" stroke-dasharray="2,2" stroke-width="1"/>
        <text x="${padding - 10}" y="${y + 4}" text-anchor="end" font-size="12" fill="#94a3b8">
          ${value}
        </text>
      `;
    }

    // X axis labels
    let xLabelsHtml = '';
    data.forEach((d, i) => {
      if (i % Math.ceil(data.length / 6) === 0 || i === data.length - 1) {
        const x = padding + i * xStep;
        xLabelsHtml += `
          <text x="${x}" y="${height - padding + 20}" text-anchor="middle" font-size="11" fill="#64748b">
            ${d.date}
          </text>
        `;
      }
    });

    return `
      <svg viewBox="0 0 ${width} ${height}" class="ppv-trend-chart">
        <defs>
          <linearGradient id="grad1" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" style="stop-color:#667eea;stop-opacity:0.3" />
            <stop offset="100%" style="stop-color:#667eea;stop-opacity:0" />
          </linearGradient>
        </defs>
        
        <!-- Grid -->
        ${gridHtml}
        
        <!-- Area fill -->
        <path d="${pathData} L ${width - padding} ${height - padding} L ${padding} ${height - padding} Z"
              fill="url(#grad1)" />
        
        <!-- Line -->
        <path d="${pathData}" stroke="#667eea" stroke-width="2" fill="none" />
        
        <!-- Points -->
        ${pointsHtml}
        
        <!-- X Axis labels -->
        ${xLabelsHtml}
        
        <!-- Axes -->
        <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" 
              stroke="#0f172a" stroke-width="1" />
      </svg>
    `;
  }

  /** ============================================================
   * GENERATE SVG PIE CHART (Fallback)
   * ============================================================ */
  generatePieChartSVG(data) {
    if (data.length === 0) return '<p>No data</p>';

    const size = 300;
    const radius = 100;
    const cx = size / 2;
    const cy = size / 2;

    const colors = ['#667eea', '#764ba2', '#f59e0b', '#10b981', '#ef4444'];
    const total = data.reduce((sum, d) => sum + d.points, 0);

    let currentAngle = 0;
    let slicesHtml = '';

    data.forEach((store, index) => {
      const slicePercent = store.points / total;
      const sliceAngle = slicePercent * 360;
      const startAngle = currentAngle;
      const endAngle = currentAngle + sliceAngle;

      const color = colors[index % colors.length];

      // SVG arc path
      const x1 = cx + radius * Math.cos((startAngle * Math.PI) / 180);
      const y1 = cy + radius * Math.sin((startAngle * Math.PI) / 180);
      const x2 = cx + radius * Math.cos((endAngle * Math.PI) / 180);
      const y2 = cy + radius * Math.sin((endAngle * Math.PI) / 180);

      const largeArc = sliceAngle > 180 ? 1 : 0;

      const pathData = `
        M ${cx} ${cy}
        L ${x1} ${y1}
        A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2}
        Z
      `;

      slicesHtml += `
        <path d="${pathData}" fill="${color}" opacity="0.8" class="ppv-pie-slice"
              title="${store.name}: ${store.points} (${store.percentage}%)">
          <title>${store.name}: ${store.points} (${store.percentage}%)</title>
        </path>
      `;

      currentAngle = endAngle;
    });

    return `
      <svg viewBox="0 0 ${size} ${size}" class="ppv-pie-chart">
        ${slicesHtml}
      </svg>
    `;
  }

  /** ============================================================
   * SETUP EVENT LISTENERS
   * ============================================================ */
  setupEventListeners() {
    const rangeSelect = document.getElementById('ppv-analytics-range');
    if (rangeSelect) {
      rangeSelect.addEventListener('change', (e) => {
        this.range = parseInt(e.target.value);
        this.refresh();
      });
    }
  }

  /** ============================================================
   * REFRESH DATA
   * ============================================================ */
  async refresh() {
    console.log('🔄 [Analytics] Refreshing...');
    try {
      await this.fetchData();
      this.render();
    } catch (err) {
      console.error('❌ [Analytics] Refresh error:', err);
      this.renderError(err.message);
    }
  }

  /** ============================================================
   * ERROR HANDLING
   * ============================================================ */
  renderError(message) {
    if (!this.container) return;
    
    const lang = window.ppv_mypoints?.lang || 'de';
    const l = this.getLabels(lang);

    this.container.innerHTML = `
      <div class="ppv-error" style="padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px;">
        <strong>❌ ${l.analytics_error_title}</strong><br/>
        ${message}
      </div>
    `;
  }
}

// Global instance
window.ppv_analytics = new PPV_Analytics();

// Auto-init on page load
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('ppv-analytics-section')) {
    window.ppv_analytics.init();
  }
});

console.log('✅ [Analytics] Script loaded - TRANSLATED');