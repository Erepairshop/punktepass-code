/**
 * PunktePass QR Scanner - Camera Module
 * Contains: CameraScanner class, SettingsManager
 * Depends on: ppv-qr-core.js
 */
(function() {
  'use strict';

  if (window.PPV_QR_CAMERA_LOADED) return;
  window.PPV_QR_CAMERA_LOADED = true;

  const {
    log: ppvLog,
    warn: ppvWarn,
    L,
    STATE,
    getStoreKey,
    getScannerId,
    getScannerName,
    getGpsCoordinates,
    checkGpsGeofence,
    playSound
  } = window.PPV_QR;

  // ============================================================
  // CAMERA SCANNER
  // ============================================================
  class CameraScanner {
    constructor(scanProcessor) {
      this.scanProcessor = scanProcessor;
      this.scanner = null;
      this.scanning = false;
      this.state = 'stopped';
      this.lastRead = '';
      this.countdown = 0;
      this.countdownInterval = null;
      this.miniContainer = null;
      this.readerDiv = null;
      this.statusDiv = null;
      this.toggleBtn = null;
      this.torchBtn = null;
      this.torchOn = false;
      this.videoTrack = null;
      this.refocusInterval = null;
    }

    init() {
      this.createMiniScanner();
      this.checkAutoStart();
    }

    checkAutoStart() {
      try {
        const wasRunning = localStorage.getItem('ppv_scanner_running') === 'true';
        if (wasRunning) {
          ppvLog('[Scanner] Auto-starting (was running before navigation)');
          setTimeout(() => this.startScannerManual(), 500);
        }
      } catch (e) {}
    }

    saveScannerState(running) {
      try {
        localStorage.setItem('ppv_scanner_running', running ? 'true' : 'false');
      } catch (e) {}
    }

    createMiniScanner() {
      const existing = document.getElementById('ppv-mini-scanner');
      if (existing) existing.remove();

      this.miniContainer = document.createElement('div');
      this.miniContainer.id = 'ppv-mini-scanner';
      this.miniContainer.className = 'ppv-mini-scanner-active';
      this.miniContainer.innerHTML = `
        <div id="ppv-mini-drag-handle" class="ppv-mini-drag-handle"><span class="ppv-drag-icon">⋮⋮</span></div>
        <div id="ppv-mini-reader" style="display:none;"></div>
        <div id="ppv-mini-status" style="display:none;"><span class="ppv-mini-icon">📷</span><span class="ppv-mini-text">${L.scanner_active || 'Scanner aktiv'}</span></div>
        <div class="ppv-mini-controls">
          <button id="ppv-mini-toggle" class="ppv-mini-toggle"><span class="ppv-toggle-icon">📷</span><span class="ppv-toggle-text">Start</span></button>
        </div>
        <div class="ppv-mini-toolbar" style="display:none;">
          <button id="ppv-mini-fullscreen" class="ppv-mini-btn" title="Kiosk mód"><span>⛶</span></button>
          <button id="ppv-mini-torch" class="ppv-mini-btn" style="display:none;" title="Blitz"><span class="ppv-torch-icon">🔦</span></button>
          <button id="ppv-mini-refocus" class="ppv-mini-btn" style="display:none;" title="Fokus"><span class="ppv-refocus-icon">🎯</span></button>
        </div>
      `;
      document.body.appendChild(this.miniContainer);
      this.isFullscreen = false;

      this.readerDiv = document.getElementById('ppv-mini-reader');
      this.statusDiv = document.getElementById('ppv-mini-status');
      this.toggleBtn = document.getElementById('ppv-mini-toggle');
      this.torchBtn = document.getElementById('ppv-mini-torch');
      this.refocusBtn = document.getElementById('ppv-mini-refocus');
      this.fullscreenBtn = document.getElementById('ppv-mini-fullscreen');
      this.toolbar = this.miniContainer.querySelector('.ppv-mini-toolbar');

      this.loadPosition();
      this.makeDraggable();
      this.setupToggle();
      this.setupTorch();
      this.setupRefocus();
      this.setupFullscreen();
    }

    // ============================================================
    // TORCH CONTROL
    // ============================================================
    setupTorch() {
      if (!this.torchBtn) return;
      this.torchBtn.addEventListener('click', async () => {
        await this.toggleTorch();
      });
    }

    async toggleTorch() {
      if (!this.videoTrack) return;
      try {
        const capabilities = this.videoTrack.getCapabilities();
        if (!capabilities.torch) {
          ppvLog('[Camera] Torch not supported');
          return;
        }
        this.torchOn = !this.torchOn;
        await this.videoTrack.applyConstraints({ advanced: [{ torch: this.torchOn }] });
        this.torchBtn.querySelector('.ppv-torch-icon').textContent = this.torchOn ? '💡' : '🔦';
        ppvLog('[Camera] Torch:', this.torchOn ? 'ON' : 'OFF');
      } catch (e) {
        ppvWarn('[Camera] Torch error:', e);
      }
    }

    // ============================================================
    // MANUAL REFOCUS
    // ============================================================
    setupRefocus() {
      if (!this.refocusBtn) return;
      this.refocusBtn.addEventListener('click', async () => {
        await this.triggerRefocus();
      });
    }

    async triggerRefocus() {
      if (!this.videoTrack) return;
      try {
        const capabilities = this.videoTrack.getCapabilities();
        if (capabilities.focusMode && capabilities.focusMode.includes('manual')) {
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'manual' }] });
          await new Promise(r => setTimeout(r, 100));
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
          ppvLog('[Camera] Refocus triggered');
        } else if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });
          await new Promise(r => setTimeout(r, 200));
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
          ppvLog('[Camera] Refocus triggered (single-shot method)');
        }
      } catch (e) {
        ppvWarn('[Camera] Refocus error:', e);
      }
    }

    startPeriodicRefocus() {
      if (this.refocusInterval) return;
      this.refocusInterval = setInterval(() => {
        if (this.scanning && this.state === 'scanning') {
          this.triggerRefocus();
        }
      }, 8000);
      ppvLog('[Camera] Periodic refocus started (8s interval)');
    }

    stopPeriodicRefocus() {
      if (this.refocusInterval) {
        clearInterval(this.refocusInterval);
        this.refocusInterval = null;
        ppvLog('[Camera] Periodic refocus stopped');
      }
    }

    // ============================================================
    // FULLSCREEN / KIOSK MODE
    // ============================================================
    setupFullscreen() {
      if (!this.fullscreenBtn) return;
      this.fullscreenBtn.addEventListener('click', () => {
        this.toggleFullscreen();
      });
    }

    toggleFullscreen() {
      this.isFullscreen = !this.isFullscreen;

      if (this.isFullscreen) {
        const rect = this.miniContainer.getBoundingClientRect();
        this.savedPosition = { x: rect.left, y: rect.top };
        this.miniContainer.classList.add('ppv-fullscreen-mode');
        this.fullscreenBtn.querySelector('span').textContent = '⛶';
        this.fullscreenBtn.title = 'Mini mód';
        this.miniContainer.style.left = '';
        this.miniContainer.style.top = '';
        this.miniContainer.style.bottom = '';
        this.miniContainer.style.right = '';
        ppvLog('[Scanner] Entered fullscreen/kiosk mode');
      } else {
        this.miniContainer.classList.remove('ppv-fullscreen-mode');
        this.fullscreenBtn.querySelector('span').textContent = '⛶';
        this.fullscreenBtn.title = 'Kiosk mód';
        if (this.savedPosition) {
          this.miniContainer.style.bottom = 'auto';
          this.miniContainer.style.right = 'auto';
          this.miniContainer.style.left = this.savedPosition.x + 'px';
          this.miniContainer.style.top = this.savedPosition.y + 'px';
        }
        ppvLog('[Scanner] Exited fullscreen mode');
      }
    }

    loadPosition() {
      try {
        const saved = localStorage.getItem('ppv_scanner_position');
        if (saved) {
          const pos = JSON.parse(saved);
          this.miniContainer.style.bottom = 'auto';
          this.miniContainer.style.right = 'auto';
          this.miniContainer.style.left = pos.x + 'px';
          this.miniContainer.style.top = pos.y + 'px';
        }
      } catch (e) {}
    }

    savePosition(x, y) {
      try { localStorage.setItem('ppv_scanner_position', JSON.stringify({ x, y })); } catch (e) {}
    }

    makeDraggable() {
      const handle = document.getElementById('ppv-mini-drag-handle');
      if (!handle) return;

      let isDragging = false, currentX = 0, currentY = 0, offsetX = 0, offsetY = 0;

      const dragStart = e => {
        const rect = this.miniContainer.getBoundingClientRect();
        currentX = rect.left; currentY = rect.top;
        offsetX = (e.touches ? e.touches[0].clientX : e.clientX) - currentX;
        offsetY = (e.touches ? e.touches[0].clientY : e.clientY) - currentY;
        if (e.target === handle || e.target.classList.contains('ppv-drag-icon')) {
          isDragging = true;
          this.miniContainer.style.transition = 'none';
        }
      };

      const drag = e => {
        if (!isDragging) return;
        e.preventDefault();
        currentX = (e.touches ? e.touches[0].clientX : e.clientX) - offsetX;
        currentY = (e.touches ? e.touches[0].clientY : e.clientY) - offsetY;
        const rect = this.miniContainer.getBoundingClientRect();
        currentX = Math.max(0, Math.min(currentX, window.innerWidth - rect.width));
        currentY = Math.max(0, Math.min(currentY, window.innerHeight - rect.height));
        this.miniContainer.style.bottom = 'auto';
        this.miniContainer.style.right = 'auto';
        this.miniContainer.style.left = currentX + 'px';
        this.miniContainer.style.top = currentY + 'px';
      };

      const dragEnd = () => {
        if (isDragging) {
          isDragging = false;
          this.miniContainer.style.transition = '';
          this.savePosition(currentX, currentY);
        }
      };

      handle.addEventListener('mousedown', dragStart);
      document.addEventListener('mousemove', drag);
      document.addEventListener('mouseup', dragEnd);
      handle.addEventListener('touchstart', dragStart, { passive: false });
      document.addEventListener('touchmove', drag, { passive: false });
      document.addEventListener('touchend', dragEnd);
    }

    setupToggle() {
      if (!this.toggleBtn) return;
      this.toggleBtn.addEventListener('click', async () => {
        if (this.scanning) await this.stopScanner();
        else await this.startScannerManual();
      });
    }

    async stopScanner() {
      try {
        if (this.scanner) {
          if (typeof this.scanner.stop === 'function') await this.scanner.stop();
          if (typeof this.scanner.destroy === 'function') this.scanner.destroy();
          this.scanner = null;
        }
        if (this.iosStream) { this.iosStream.getTracks().forEach(t => t.stop()); this.iosStream = null; }
      } catch (e) { ppvWarn('[Camera] Stop error:', e); }

      this.scanning = false;
      this.state = 'stopped';
      this.videoTrack = null;
      this.torchOn = false;
      this.readerDiv.style.display = 'none';
      this.statusDiv.style.display = 'none';
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '📷';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Start';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #00e676, #00c853)';

      if (this.toolbar) this.toolbar.style.display = 'none';
      if (this.torchBtn) this.torchBtn.style.display = 'none';
      if (this.refocusBtn) this.refocusBtn.style.display = 'none';

      if (this.isFullscreen) {
        this.toggleFullscreen();
      }

      if (this.countdownInterval) { clearInterval(this.countdownInterval); this.countdownInterval = null; }
      this.stopPeriodicRefocus();
      this.saveScannerState(false);
    }

    async startScannerManual() {
      const deviceCheck = await this.checkDeviceAllowed();
      if (!deviceCheck.allowed) {
        this.showDeviceBlockedMessage(deviceCheck.message);
        return;
      }

      this.readerDiv.style.display = 'block';
      this.statusDiv.style.display = 'none';
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '🛑';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = '';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #ff5252, #f44336)';

      if (this.toolbar) this.toolbar.style.display = 'flex';
      this.saveScannerState(true);
      await this.loadLibrary();
    }

    async checkDeviceAllowed() {
      try {
        const fpResult = await this.getDeviceFingerprintFull();
        if (!fpResult || !fpResult.visitorId) {
          ppvWarn('[Scanner] No fingerprint available - blocking scanner');
          return {
            allowed: false,
            message: L.device_register_first || 'Bitte registrieren Sie zuerst ein Gerät im Tab "Geräte", bevor Sie den Scanner verwenden können.'
          };
        }

        // Send fingerprint with components for similarity matching (auto-update feature)
        const response = await fetch('/wp-json/punktepass/v1/user-devices/check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            fingerprint: fpResult.visitorId,
            components: fpResult.components // For auto-update similarity matching
          })
        });

        const data = await response.json();
        ppvLog('[Scanner] Device check result:', data);

        // ═══════════════════════════════════════════════════════════════
        // FINGERPRINT CHANGE NOTIFICATION (Fázis 1 - 2025-12)
        // Show toast when fingerprint was auto-updated
        // ═══════════════════════════════════════════════════════════════
        if (data.auto_updated && data.similarity_score) {
          ppvLog('[Scanner] 🔄 Fingerprint auto-updated with similarity:', data.similarity_score + '%');
          const toastMsg = (L.fingerprint_auto_updated || 'Geräte-Fingerprint automatisch aktualisiert ({score}% Übereinstimmung)')
            .replace('{score}', data.similarity_score);
          if (window.ppvToast) {
            window.ppvToast(toastMsg, 'info');
          }
        }

        if (!data.can_use_scanner) {
          let message;
          if (data.device_count === 0) {
            message = L.device_register_first || 'Bitte registrieren Sie zuerst ein Gerät im Tab "Geräte", bevor Sie den Scanner verwenden können.';
          } else {
            message = L.device_not_allowed || 'Dieses Gerät ist nicht für den Scanner registriert. Bitte registrieren Sie es im Tab "Geräte".';
          }
          return { allowed: false, message: message };
        }

        if (data.gps) {
          const gpsCheck = await checkGpsGeofence(data.gps);
          ppvLog('[Scanner] GPS geofence check result:', gpsCheck);
          if (!gpsCheck.allowed) {
            return {
              allowed: false,
              message: gpsCheck.message,
              reason: gpsCheck.reason
            };
          }
        }

        return { allowed: true };
      } catch (e) {
        ppvWarn('[Scanner] Device check error:', e);
        return {
          allowed: false,
          message: L.device_register_first || 'Bitte registrieren Sie zuerst ein Gerät im Tab "Geräte", bevor Sie den Scanner verwenden können.'
        };
      }
    }

    /**
     * Get device fingerprint with components for similarity matching
     * Returns { visitorId, components } or null
     */
    async getDeviceFingerprintFull() {
      try {
        if (window.FingerprintJS) {
          const fp = await FingerprintJS.load();
          const result = await fp.get();
          // Extract key components for similarity comparison
          const components = {};
          if (result.components) {
            // Store stable components (less likely to change)
            const stableKeys = ['platform', 'timezone', 'languages', 'colorDepth', 'deviceMemory',
                               'hardwareConcurrency', 'screenResolution', 'vendor', 'vendorFlavors',
                               'cookiesEnabled', 'colorGamut', 'audio', 'canvas', 'webGlBasics'];
            for (const key of stableKeys) {
              if (result.components[key]) {
                components[key] = result.components[key].value;
              }
            }
          }
          return { visitorId: result.visitorId, components };
        }

        // Fallback: generate simple fingerprint without components
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('fingerprint', 0, 0);
        const data = canvas.toDataURL() + navigator.userAgent + screen.width + screen.height + navigator.language + (new Date()).getTimezoneOffset();
        let hash1 = 0, hash2 = 0;
        for (let i = 0; i < data.length; i++) {
          hash1 = ((hash1 << 5) - hash1) + data.charCodeAt(i);
          hash1 = hash1 & hash1;
          hash2 = ((hash2 << 7) - hash2) + data.charCodeAt(i);
          hash2 = hash2 & hash2;
        }
        return {
          visitorId: 'fp_' + Math.abs(hash1).toString(16).padStart(8, '0') + Math.abs(hash2).toString(16).padStart(8, '0'),
          components: null
        };
      } catch (e) {
        ppvWarn('[Scanner] Fingerprint error:', e);
        return null;
      }
    }

    // Legacy method for backwards compatibility
    async getDeviceFingerprint() {
      const result = await this.getDeviceFingerprintFull();
      return result ? result.visitorId : null;
    }

    showDeviceBlockedMessage(message) {
      const overlay = document.createElement('div');
      overlay.id = 'ppv-device-blocked-overlay';
      overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 99999;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        box-sizing: border-box;
      `;

      overlay.innerHTML = `
        <div style="background: #1a1a2e; padding: 30px; border-radius: 20px; max-width: 400px; text-align: center; border: 2px solid #f44336;">
          <div style="font-size: 64px; margin-bottom: 20px;">🚫</div>
          <h3 style="color: #f44336; margin: 0 0 15px 0; font-size: 20px;">
            ${L.device_blocked_title || 'Gerät nicht registriert'}
          </h3>
          <p style="color: #ccc; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
            ${message}
          </p>
          <p style="color: #ff9800; font-size: 13px; margin: 0 0 20px 0;">
            <i class="ri-information-line"></i>
            ${L.device_blocked_hint || 'Gehen Sie zum Tab "Készülékek" um dieses Gerät zu registrieren.'}
          </p>
          <button id="ppv-close-device-blocked" style="
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
          ">${L.close || 'Schließen'}</button>
        </div>
      `;

      document.body.appendChild(overlay);

      document.getElementById('ppv-close-device-blocked').addEventListener('click', () => {
        overlay.remove();
      });

      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          overlay.remove();
        }
      });

      window.ppvToast(L.device_not_registered || '🚫 Gerät nicht registriert', 'error');
    }

    async loadLibrary() {
      if (window.QrScanner) {
        await this.startQrScanner();
        return;
      }

      // Load QR Scanner from local vendor (no CDN dependency)
      const pluginUrl = window.PPV_STORE_DATA?.plugin_url || '/wp-content/plugins/punktepass/';
      const script = document.createElement('script');
      script.src = pluginUrl + 'assets/js/vendor/qr-scanner.umd.min.js';
      script.onload = () => this.startQrScanner();
      script.onerror = () => {
        ppvWarn('[Camera] qr-scanner failed to load, falling back to jsQR');
        this.loadFallbackLibrary();
      };
      document.head.appendChild(script);
    }

    loadFallbackLibrary() {
      if (window.jsQR) { this.startJsQRScanner(); return; }
      // jsQR fallback still uses CDN (less critical, only used if local qr-scanner fails)
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
      script.onload = () => this.startJsQRScanner();
      script.onerror = () => this.updateStatus('error', '❌ Scanner nicht verfügbar');
      document.head.appendChild(script);
    }

    async startQrScanner() {
      if (!this.readerDiv || !window.QrScanner) return;

      try {
        let videoEl = this.readerDiv.querySelector('video');
        if (!videoEl) {
          videoEl = document.createElement('video');
          videoEl.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:8px;';
          this.readerDiv.appendChild(videoEl);
        }

        const options = {
          preferredCamera: 'environment',
          maxScansPerSecond: 30, // Aggressive scanning for faster detection
          highlightScanRegion: true,
          highlightCodeOutline: true,
          returnDetailedScanResult: true,
          // Scan center 60% of video for faster processing
          calculateScanRegion: (video) => {
            const size = Math.min(video.videoWidth, video.videoHeight) * 0.6;
            return {
              x: (video.videoWidth - size) / 2,
              y: (video.videoHeight - size) / 2,
              width: size,
              height: size
            };
          }
        };

        this.scanner = new QrScanner(
          videoEl,
          result => this.onScanSuccess(result.data || result),
          options
        );

        // Note: WORKER_PATH no longer needed in qr-scanner 1.4.2+ (worker is bundled inline)

        await this.scanner.start();
        ppvLog('[Camera] QrScanner started successfully');

        try {
          const stream = videoEl.srcObject;
          if (stream) {
            this.videoTrack = stream.getVideoTracks()[0];
            if (this.videoTrack) {
              const capabilities = this.videoTrack.getCapabilities();
              ppvLog('[Camera] Capabilities:', capabilities);

              const advancedConstraints = [];
              if (capabilities.focusMode?.includes('continuous')) {
                advancedConstraints.push({ focusMode: 'continuous' });
              }
              if (capabilities.exposureMode?.includes('continuous')) {
                advancedConstraints.push({ exposureMode: 'continuous' });
              }
              // Set closer focus distance if supported (helps with close-up scanning)
              if (capabilities.focusDistance) {
                const minFocus = capabilities.focusDistance.min || 0;
                const maxFocus = capabilities.focusDistance.max || 1;
                // Set focus distance to closer range (20-30% of range for close-up)
                const closeFocus = minFocus + (maxFocus - minFocus) * 0.25;
                advancedConstraints.push({ focusDistance: closeFocus });
                ppvLog('[Camera] Focus distance set to:', closeFocus, '(range:', minFocus, '-', maxFocus, ')');
              }
              if (advancedConstraints.length > 0) {
                await this.videoTrack.applyConstraints({ advanced: advancedConstraints });
              }

              if (capabilities.torch && this.torchBtn) {
                this.torchBtn.style.display = 'inline-flex';
              }
              if (this.refocusBtn) {
                this.refocusBtn.style.display = 'inline-flex';
              }
              this.startPeriodicRefocus();
            }
          }
        } catch (trackErr) {
          ppvWarn('[Camera] Track setup error:', trackErr);
        }

        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || 'Scanning...');

      } catch (e) {
        // Ignore "interrupted" errors (happens on quick start/stop)
        if (e?.name === 'AbortError') {
          ppvLog('[Camera] Play interrupted, ignoring...');
          return;
        }

        ppvWarn('[Camera] QrScanner error:', e);
        console.error('[Camera] Detailed error:', e);

        const errMsg = e?.message || String(e);
        if (/permission|denied|not allowed/i.test(errMsg)) {
          this.updateStatus('error', '❌ Kamera-Zugriff verweigert');
          window.ppvToast('Bitte erlaube den Kamerazugriff', 'error');
        } else if (/not found|no camera/i.test(errMsg)) {
          this.updateStatus('error', '❌ Keine Kamera gefunden');
        } else if (/in use|busy/i.test(errMsg)) {
          this.updateStatus('error', '❌ Kamera wird verwendet');
        } else if (/interrupted/i.test(errMsg)) {
          // Play was interrupted - ignore silently
          ppvLog('[Camera] Play interrupted, ignoring...');
          return;
        } else {
          this.updateStatus('error', '❌ Kamera nicht verfügbar');
          window.ppvToast('Kamera-Fehler: ' + errMsg.substring(0, 50), 'error');
        }
      }
    }

    async startJsQRScanner() {
      if (!this.readerDiv || !window.jsQR) return;
      if (this.iosStream) { this.iosStream.getTracks().forEach(t => t.stop()); this.iosStream = null; }

      try {
        const video = document.createElement('video');
        video.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:8px;';
        video.setAttribute('playsinline', 'true');
        video.setAttribute('autoplay', 'true');
        video.setAttribute('muted', 'true');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        this.readerDiv.innerHTML = '';
        this.readerDiv.appendChild(video);

        const stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { exact: 'environment' },
            width: { ideal: 640 },  // Lower resolution for faster processing
            height: { ideal: 480 }
          }
        });

        video.srcObject = stream;
        try {
          await video.play();
        } catch (playErr) {
          // Ignore "interrupted" errors (happens on quick start/stop)
          if (playErr.name !== 'AbortError') {
            throw playErr;
          }
          ppvLog('[jsQR] Play interrupted, continuing...');
        }

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;

        this.iosStream = stream;
        this.iosVideo = video;
        this.iosCanvas = canvas;
        this.iosCanvasCtx = ctx;

        this.videoTrack = stream.getVideoTracks()[0];
        if (this.videoTrack) {
          const capabilities = this.videoTrack.getCapabilities();
          if (capabilities.torch && this.torchBtn) {
            this.torchBtn.style.display = 'inline-flex';
          }
          if (this.refocusBtn) {
            this.refocusBtn.style.display = 'inline-flex';
          }
        }

        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || 'Scanning...');
        this.jsQRScanLoop();

      } catch (e) {
        ppvWarn('[jsQR Camera] Start error:', e);

        if (e.name === 'OverconstrainedError') {
          ppvLog('[jsQR] Retrying without exact facingMode...');
          try {
            const stream = await navigator.mediaDevices.getUserMedia({
              video: { facingMode: 'environment' }
            });
            this.updateStatus('error', '❌ Kamera nicht kompatibel');
          } catch (e2) {
            this.updateStatus('error', '❌ Kamera nicht verfügbar');
          }
        } else {
          this.updateStatus('error', '❌ Kamera nicht verfügbar');
        }
        console.error('[jsQR Camera] Detailed error:', e.name, e.message);
      }
    }

    jsQRScanLoop() {
      if (!this.scanning || !this.iosVideo || !this.iosCanvas) return;

      const video = this.iosVideo, canvas = this.iosCanvas, ctx = this.iosCanvasCtx;

      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, {
          inversionAttempts: 'dontInvert' // Faster - skip inverted QR codes
        });
        if (code && code.data) this.onScanSuccess(code.data);
      }

      if (this.scanning) setTimeout(() => this.jsQRScanLoop(), 25); // ~40fps scan loop
    }

    onScanSuccess(qrCode) {
      if (this.state === 'paused' || this.state !== 'scanning') return;
      if (qrCode === this.lastRead) return;
      this.lastRead = qrCode;
      this.state = 'processing';
      this.updateStatus('processing', '⏳ ' + (L.scanner_points_adding || 'Wird verarbeitet...'));
      this.inlineProcessScan(qrCode);
    }

    async inlineProcessScan(qrCode) {
      const gps = getGpsCoordinates();
      const fingerprint = await this.getDeviceFingerprint();

      fetch('/wp-json/punktepass/v1/pos/scan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
        body: JSON.stringify({
          qr: qrCode,
          store_key: getStoreKey(),
          points: 1,
          latitude: gps.latitude,
          longitude: gps.longitude,
          scanner_id: getScannerId(),
          scanner_name: getScannerName(),
          device_fingerprint: fingerprint
        })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            playSound('success');
            this.updateStatus('success', '✅ ' + (data.message || L.scanner_success_msg || 'Erfolgreich!'));
            window.ppvToast(data.message || L.scanner_point_added || '✅ Punkt hinzugefügt!', 'success');

            // 📊 Show customer insights to Händler
            if (data.customer_insights && data.customer_insights.display) {
              this.showCustomerInsights(data.customer_name, data.customer_insights);
            }

            const now = new Date();
            const scanId = data.scan_id || `local-${data.user_id}-${now.getTime()}`;

            if (STATE.uiManager) {
              STATE.uiManager.addScanItem({
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
                _realtime: true,
                customer_insights: data.customer_insights || null
              });
            }

            this.startPauseCountdown();
          } else {
            playSound('error');
            this.updateStatus('warning', '⚠️ ' + (data.message || L.error_generic || 'Fehler'));
            window.ppvToast(data.message || '⚠️ Fehler', 'warning');

            if (STATE.uiManager) {
              const now = new Date();
              const oderId = data.user_id || 0;
              const scanId = data.scan_id || `local-err-${oderId}-${now.getTime()}`;

              STATE.uiManager.addScanItem({
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
            }

            setTimeout(() => this.restartAfterError(), 3000);
          }
        })
        .catch(() => {
          playSound('error');
          this.updateStatus('error', '❌ ' + (L.pos_network_error || 'Netzwerkfehler'));
          window.ppvToast('❌ ' + (L.pos_network_error || 'Netzwerkfehler'), 'error');
          setTimeout(() => this.restartAfterError(), 3000);
        });
    }

    startPauseCountdown() {
      if (this.countdownInterval) clearInterval(this.countdownInterval);
      this.state = 'paused';
      this.countdown = 3;
      this.lastRead = '';
      this.updateStatus('paused', `⏸️ Pause: ${this.countdown}s`);

      this.countdownInterval = setInterval(() => {
        this.countdown--;
        if (this.countdown <= 0) {
          clearInterval(this.countdownInterval);
          this.countdownInterval = null;
          this.autoRestartScanner();
        } else {
          this.updateStatus('paused', `⏸️ Pause: ${this.countdown}s`);
        }
      }, 1000);
    }

    restartAfterError() {
      this.lastRead = '';
      this.state = 'scanning';
      this.updateStatus('scanning', L.scanner_active || 'Scanning...');
    }

    async autoRestartScanner() {
      if (this.state === 'stopped' || !this.scanning) return;
      this.state = 'scanning';
      this.updateStatus('scanning', L.scanner_active || 'Scanning...');
      setTimeout(() => this.triggerRefocus(), 200);
    }

    updateStatus(state, text) {
      if (!this.statusDiv) return;
      const iconMap = { scanning: '📷', processing: '⏳', success: '✅', warning: '⚠️', error: '❌', paused: '⏸️' };
      const iconEl = this.statusDiv.querySelector('.ppv-mini-icon');
      const textEl = this.statusDiv.querySelector('.ppv-mini-text');
      if (iconEl) iconEl.textContent = iconMap[state] || '📷';
      if (textEl) textEl.textContent = text.replace(/^[📷⏳✅⚠️❌⏸️]\s*/, '');
    }

    /**
     * 📊 Show customer insights panel to Händler after successful scan
     * Compact display optimized for small screens (XCover 4S)
     */
    showCustomerInsights(customerName, insights) {
      if (!insights || !insights.display) return;

      // Remove existing insights panel if any
      const existing = document.getElementById('ppv-customer-insights');
      if (existing) existing.remove();

      const d = insights.display;
      const name = customerName || '';

      // Build compact info lines
      let html = `
        <div id="ppv-customer-insights" class="ppv-insights-panel">
          <div class="ppv-insights-header">
            <span class="ppv-insights-name">${this.escapeHtml(name)}</span>
            <button class="ppv-insights-close" onclick="this.parentElement.parentElement.remove()">×</button>
          </div>
          <div class="ppv-insights-body">
            <div class="ppv-insights-line1">${this.escapeHtml(d.line1 || '')}</div>
      `;

      if (d.line2) {
        html += `<div class="ppv-insights-line2">${this.escapeHtml(d.line2)}</div>`;
      }

      if (d.line3) {
        html += `<div class="ppv-insights-line3">${this.escapeHtml(d.line3)}</div>`;
      }

      if (d.birthday) {
        html += `<div class="ppv-insights-birthday">${this.escapeHtml(d.birthday)}</div>`;
      }

      html += `
          </div>
        </div>
      `;

      // Insert after mini scanner or at top of page
      const mini = document.getElementById('ppv-mini-scanner');
      if (mini) {
        mini.insertAdjacentHTML('afterend', html);
      } else {
        document.body.insertAdjacentHTML('afterbegin', html);
      }

      // Auto-hide after 15 seconds
      setTimeout(() => {
        const panel = document.getElementById('ppv-customer-insights');
        if (panel) {
          panel.classList.add('ppv-insights-hiding');
          setTimeout(() => panel.remove(), 500);
        }
      }, 15000);
    }

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    cleanup() {
      this.stopScanner();
      this.stopPeriodicRefocus();
      const mini = document.getElementById('ppv-mini-scanner');
      if (mini) mini.remove();
    }
  }

  // ============================================================
  // SETTINGS MANAGER
  // ============================================================
  class SettingsManager {
    static initLanguage() {
      const langSel = document.getElementById('ppv-lang-select');
      if (!langSel) return;

      const cur = (document.cookie.match(/ppv_lang=([^;]+)/) || [])[1] || 'de';
      langSel.value = cur;

      langSel.addEventListener('change', async e => {
        const newLang = e.target.value;
        document.cookie = `ppv_lang=${newLang};path=/;max-age=${60 * 60 * 24 * 365}`;
        localStorage.setItem('ppv_lang', newLang);

        try {
          const res = await fetch('/wp-json/punktepass/v1/strings', { headers: { 'X-Lang': newLang } });
          window.ppv_lang = await res.json();
          window.ppvToast(`✅ ${L.lang_changed || 'Sprache'}: ${newLang.toUpperCase()}`, 'success');
        } catch (e) {
          window.ppvToast('❌ ' + (L.lang_change_failed || 'Sprachänderung fehlgeschlagen'), 'error');
          langSel.value = cur;
        }
      });
    }

    static initTheme() {
      const themeBtn = document.getElementById('ppv-theme-toggle');
      if (!themeBtn) return;

      const apply = v => {
        document.body.classList.remove('ppv-light', 'ppv-dark');
        document.body.classList.add(`ppv-${v}`);
      };

      let cur = localStorage.getItem('ppv_theme') || 'dark';
      apply(cur);

      themeBtn.addEventListener('click', () => {
        cur = cur === 'dark' ? 'light' : 'dark';
        localStorage.setItem('ppv_theme', cur);
        apply(cur);
      });
    }
  }

  // Export to global namespace
  window.PPV_QR.CameraScanner = CameraScanner;
  window.PPV_QR.SettingsManager = SettingsManager;

  ppvLog('[QR-Camera] Module loaded');

})();
