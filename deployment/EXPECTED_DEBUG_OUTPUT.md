# Expected Debug Output After Deployment

## What You Should See in Error Logs After Scanning

When you scan a QR code after deploying the updated file, you should see logs in this order:

```
[19-Nov-2025 23:45:56 UTC] 🚀 [SCAN START] Request received
[19-Nov-2025 23:45:56 UTC] 📋 [SCAN DATA] QR: ppv://user/123456... | Lang: de
[19-Nov-2025 23:45:56 UTC] 🔍 [RATE LIMIT CHECK] IP: 123.45.67.89 | Recent scans: 1/100
[19-Nov-2025 23:45:56 UTC] ✅ [RATE LIMIT] IP 123.45.67.89 passed - continuing scan
[19-Nov-2025 23:45:56 UTC] 📍 [IP ADDRESS] Raw: '123.45.67.89' | Cleaned: '123.45.67.89'
[19-Nov-2025 23:45:56 UTC] ✅ [LOGGING SCAN] User: 3 | Store: 9 | Points: +1
[19-Nov-2025 23:45:56 UTC] 💾 [LOG_SCAN_ATTEMPT] Store: 9 | User: 3 | IP: '123.45.67.89' | Status: ok | Reason: ✅ +1 Punkte
[19-Nov-2025 23:45:56 UTC] ✅ [LOG_SCAN_ATTEMPT] Logged successfully. Insert ID: 456
```

## Debug Log Markers Explained

| Emoji | Marker | What It Tracks |
|-------|--------|----------------|
| 🚀 | `[SCAN START]` | Scan request received by API endpoint |
| 📋 | `[SCAN DATA]` | QR code data and language parameter |
| 🔍 | `[RATE LIMIT CHECK]` | IP address and current scan count |
| ✅ | `[RATE LIMIT]` | Rate limit passed (or ❌ if blocked) |
| 🚫 | `[RATE LIMIT]` | **Rate limit exceeded** - This is likely causing your 429 error! |
| 📍 | `[IP ADDRESS]` | Raw and cleaned IP address detection |
| ✅ | `[LOGGING SCAN]` | Scan details before database insert |
| 💾 | `[LOG_SCAN_ATTEMPT]` | Database insert attempt |
| ✅ | `[LOG_SCAN_ATTEMPT]` | Database insert successful (or ❌ if failed) |

## If You See 429 Error

Look for this in the logs:
```
[19-Nov-2025 23:45:56 UTC] 🔍 [RATE LIMIT CHECK] IP: 123.45.67.89 | Recent scans: 101/100
[19-Nov-2025 23:45:56 UTC] 🚫 [RATE LIMIT] IP 123.45.67.89 blocked - 101 scans in last minute
```

This tells us:
- **IP address being tracked** - Check if it's the correct IP (should be your scanner device IP)
- **Number of recent scans** - If it's over 100 in 1 minute, rate limiting is triggering
- **Why 429 is happening** - Backend rate limiting is the cause

## If IP Address Shows NULL in Database

Look for this pattern:
```
[19-Nov-2025 23:45:56 UTC] 📍 [IP ADDRESS] Raw: '' | Cleaned: ''
```

This means:
- `$_SERVER['HTTP_X_FORWARDED_FOR']` is empty
- `$_SERVER['REMOTE_ADDR']` is also empty
- Likely a proxy/CDN issue (Cloudflare, etc.)
- Need to check server configuration

## If Debug Logs Don't Appear AT ALL

### Before Blaming the Deployment:
1. **Check error_log location:**
   ```bash
   # Find where PHP errors are being logged
   php -i | grep error_log
   ```

2. **Check error_log is enabled:**
   ```bash
   # Check PHP configuration
   php -i | grep "log_errors"
   # Should show: log_errors => On => On
   ```

3. **Check file was actually updated:**
   ```bash
   # Check file modification time
   ls -lh /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php
   # Should show today's date/time

   # Check for debug marker in file
   grep "SCAN START" /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php
   # Should return: error_log("🚀 [SCAN START] Request received");
   ```

4. **Clear all caches:**
   - PHP opcache: `php -r "opcache_reset();"`
   - WordPress object cache: `wp cache flush` (if WP-CLI available)
   - Cloudflare cache: Purge via dashboard
   - Browser cache: Hard refresh (Ctrl+Shift+R)

5. **Restart PHP-FPM:**
   ```bash
   service php-fpm restart
   # or
   systemctl restart php8.1-fpm
   ```

## Current Issue Summary

**Problem:** 429 Too Many Requests error when scanning QR codes
**Cause:** Unknown - needs debug logging to identify
**Possible Causes:**
1. ✅ Backend rate limiting (10 → 100 scans/minute increased for testing)
2. ❓ IP address detection failing (showing NULL in database)
3. ❓ Cloudflare rate limiting
4. ❓ Server WAF/security rules
5. ❓ WordPress security plugin

**Solution:** Deploy updated file → Scan again → Check logs → Identify root cause

## Next Steps After Deployment

1. ✅ Deploy `ppv-pos-scan.php` to production
2. ✅ Scan a QR code
3. ✅ Check error log for debug markers (🚀, 🔍, 📍, etc.)
4. ✅ Share the debug output
5. ✅ Identify root cause of 429 error
6. ✅ Fix the issue
7. ✅ Test toast notifications work correctly
