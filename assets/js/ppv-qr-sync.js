/**
 * PunktePass QR Scanner - Sync Module
 * Contains: OfflineSyncManager, ScanProcessor
 * Depends on: ppv-qr-core.js, ppv-qr-ui.js
 */
(function() {
  'use strict';

  if (window.PPV_QR_SYNC_LOADED) return;
  window.PPV_QR_SYNC_LOADED = true;

  const {
    log: ppvLog,
    warn: ppvWarn,
    L,
    getStoreKey,
    getScannerId,
    getScannerName,
    getGpsCoordinates,
    canProcessScan
  } = window.PPV_QR;

  // ============================================================
  // OFFLINE SYNC MANAGER
  // ============================================================
  class OfflineSyncManager {
    static STORAGE_KEY = 'ppv_offline_sync';
    static DUPLICATE_WINDOW = 2 * 60 * 1000;

    static save(qrCode) {
      try {
        let items = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
        const twoMinutesAgo = Date.now() - this.DUPLICATE_WINDOW;
        const isDuplicate = items.some(item => item.qr === qrCode && new Date(item.time).getTime() > twoMinutesAgo);

        if (isDuplicate) {
          window.ppvToast('⚠️ ' + (L.pos_duplicate || 'Bereits gescannt'), 'warning');
          return false;
        }

        items.push({
          id: `${getStoreKey()}-${qrCode}-${Date.now()}`,
          qr: qrCode,
          time: new Date().toISOString(),
          store_key: getStoreKey(),
          synced: false
        });

        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
        return true;
      } catch (e) {
        return false;
      }
    }

    static async sync() {
      try {
        let items = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
        const unsynced = items.filter(i => !i.synced);
        if (!unsynced.length) return;

        const res = await fetch('/wp-json/punktepass/v1/pos/sync_offline', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ scans: unsynced })
        });

        const result = await res.json();
        if (result.success) {
          const synced = unsynced.map(i => i.id);
          localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items.filter(i => !synced.includes(i.id))));
          window.ppvToast(`✅ ${result.synced} ${L.pos_sync || 'synchronisiert'}`, 'success');
        }
      } catch (e) {
        ppvWarn('[QR] Sync error:', e);
      }
    }
  }

  // ============================================================
  // SCAN PROCESSOR
  // ============================================================
  class ScanProcessor {
    constructor(uiManager) {
      this.ui = uiManager;
    }

    async process(qrCode) {
      if (!qrCode || !getStoreKey()) return;
      if (!canProcessScan()) return;

      this.ui.showMessage('⏳ ' + (L.pos_checking || 'Wird geprüft...'), 'info');

      const gps = getGpsCoordinates();

      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/scan', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({
            qr: qrCode,
            store_key: getStoreKey(),
            points: 1,
            latitude: gps.latitude,
            longitude: gps.longitude,
            scanner_id: getScannerId(),
            scanner_name: getScannerName()
          })
        });

        const data = await res.json();

        if (data.success) {
          this.ui.showMessage('✅ ' + data.message, 'success');
          document.dispatchEvent(new CustomEvent('ppv:scan-success', { detail: { points: data.points || 1 } }));

          const now = new Date();
          const scanId = data.scan_id || `local-${data.user_id}-${now.getTime()}`;

          this.ui.addScanItem({
            scan_id: scanId,
            user_id: data.user_id,
            customer_name: data.customer_name || null,
            email: data.email || null,
            avatar: data.avatar || null,
            message: data.message,
            points: data.points || 1,
            date_short: now.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit'}).replace(/\./g, '.'),
            time_short: now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}),
            success: true,
            _realtime: true
          });
        } else {
          this.ui.showMessage('⚠️ ' + (data.message || ''), 'warning');

          const now = new Date();
          const oderId = data.user_id || 0;
          const scanId = data.scan_id || `local-err-${oderId}-${now.getTime()}`;

          this.ui.addScanItem({
            scan_id: scanId,
            user_id: oderId,
            customer_name: data.customer_name || null,
            email: data.email || null,
            avatar: data.avatar || null,
            message: data.message || '⚠️ Fehler',
            points: 0,
            date_short: now.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit'}).replace(/\./g, '.'),
            time_short: now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}),
            success: false,
            _realtime: true
          });

          if (!/bereits|gescannt|duplikat/i.test(data.message || '')) {
            OfflineSyncManager.save(qrCode);
          }
        }
      } catch (e) {
        this.ui.showMessage('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
        OfflineSyncManager.save(qrCode);
      }
    }

    async loadLogs() {
      if (!getStoreKey()) return;
      ppvLog('[QR] 📡 loadLogs() called at', new Date().toLocaleTimeString());
      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/logs', {
          headers: { 'PPV-POS-Token': getStoreKey() }
        });
        ppvLog('[QR] 📡 loadLogs() response:', res.status);
        const logs = await res.json();

        this.ui.clearLogTable();
        (logs || []).forEach(l => this.ui.addScanItem(l));
      } catch (e) {
        ppvWarn('[QR] Failed to load logs:', e);
      }
    }
  }

  // Export to global namespace
  window.PPV_QR.OfflineSyncManager = OfflineSyncManager;
  window.PPV_QR.ScanProcessor = ScanProcessor;

  ppvLog('[QR-Sync] Module loaded');

})();
