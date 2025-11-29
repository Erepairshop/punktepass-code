/**
 * PunktePass QR Scanner - UI Module
 * Contains: UIManager class
 * Depends on: ppv-qr-core.js
 */
(function() {
  'use strict';

  if (window.PPV_QR_UI_LOADED) return;
  window.PPV_QR_UI_LOADED = true;

  const { log: ppvLog, L } = window.PPV_QR;

  // ============================================================
  // UI MANAGER
  // ============================================================
  class UIManager {
    constructor() {
      this.resultBox = null;
      this.logList = null;
      this.campaignList = null;
      this.displayedScanIds = new Set();
    }

    init() {
      this.resultBox = document.getElementById('ppv-pos-result');
      this.logList = document.getElementById('ppv-pos-log');
      this.campaignList = document.getElementById('ppv-campaign-list');
      this.displayedScanIds.clear();
    }

    showMessage(text, type = 'info') {
      window.ppvToast(text, type);
    }

    clearLogTable() {
      if (!this.logList) return;
      this.logList.innerHTML = '';
      this.displayedScanIds.clear();
    }

    addScanItem(log) {
      if (!this.logList) return;

      const scanId = log.scan_id || `${log.user_id}-${log.date_short}-${log.time_short}`;
      if (this.displayedScanIds.has(scanId)) {
        ppvLog('[UI] Skipping duplicate scan:', scanId);
        return;
      }
      this.displayedScanIds.add(scanId);

      const item = document.createElement('div');
      item.className = `ppv-scan-item ${log.success ? 'success' : 'error'}`;
      item.dataset.scanId = scanId;

      const displayName = log.customer_name || log.email || `Kunde #${log.user_id}`;

      // Check for bonuses
      const vipMatch = (log.message || '').match(/\(VIP:?\s*\+(\d+)\)/i);
      const vipBonus = vipMatch ? vipMatch[1] : null;
      const isVip = !!vipBonus;

      const bdayMatch = (log.message || '').match(/🎂\s*\+(\d+)/);
      const bdayBonus = bdayMatch ? bdayMatch[1] : (log.birthday_bonus > 0 ? log.birthday_bonus : null);
      const isBirthday = !!bdayBonus;

      const comebackMatch = (log.message || '').match(/👋\s*\+(\d+)/);
      const comebackBonus = comebackMatch ? comebackMatch[1] : (log.comeback_bonus > 0 ? log.comeback_bonus : null);
      const isComeback = !!comebackBonus;

      // Subtitle logic
      const dateTime = `${log.date_short || ''} ${log.time_short || ''}`.trim();
      let subtitle = dateTime;
      let subtitle2 = '';

      if (!log.success && log.message) {
        const errorMsg = log.message.replace(/^[⚠️❌✗\s]+/, '').trim();
        subtitle = errorMsg;
        subtitle2 = dateTime;
      } else if (log.customer_name && log.email) {
        subtitle = log.email;
        subtitle2 = dateTime;
      }

      // Avatar
      const avatarHtml = log.avatar
        ? `<img src="${log.avatar}" class="ppv-scan-avatar" alt="">`
        : `<div class="ppv-scan-avatar-placeholder">${log.success ? '✓' : '✗'}</div>`;

      // Points display
      let pointsHtml;
      if (!log.success) {
        pointsHtml = `<div class="ppv-scan-points error-badge">✗</div>`;
      } else {
        let badges = '';
        if (isVip) badges += `<span class="ppv-vip-badge">VIP +${vipBonus}</span>`;
        if (isBirthday) badges += `<span class="ppv-bday-badge">🎂 +${bdayBonus}</span>`;
        if (isComeback) badges += `<span class="ppv-comeback-badge">👋 +${comebackBonus}</span>`;
        const hasBonus = isVip || isBirthday || isComeback;
        pointsHtml = `<div class="ppv-scan-points ${hasBonus ? 'bonus' : ''}">+${log.points}${badges}</div>`;
      }

      const subtitle2Html = subtitle2 ? `<div class="ppv-scan-detail ppv-scan-time">${subtitle2}</div>` : '';
      const scannerName = log.scanner_name || null;
      const scannerHtml = scannerName
        ? `<div class="ppv-scan-detail ppv-scan-scanner">👤 ${scannerName}</div>`
        : '';

      item.innerHTML = `
        ${avatarHtml}
        <div class="ppv-scan-info">
          <div class="ppv-scan-name">${displayName}</div>
          <div class="ppv-scan-detail">${subtitle}</div>
          ${subtitle2Html}
          ${scannerHtml}
        </div>
        ${pointsHtml}
      `;

      if (log._realtime) {
        this.logList.prepend(item);
      } else {
        this.logList.appendChild(item);
      }
    }

    flashCampaignList() {
      if (!this.campaignList) return;
      this.campaignList.scrollTo({ top: 0, behavior: 'smooth' });
      this.campaignList.style.transition = 'background 0.5s';
      this.campaignList.style.background = 'rgba(0,255,120,0.25)';
      setTimeout(() => this.campaignList.style.background = 'transparent', 600);
    }
  }

  // Export to global namespace
  window.PPV_QR.UIManager = UIManager;

  ppvLog('[QR-UI] Module loaded');

})();
