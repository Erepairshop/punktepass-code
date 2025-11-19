# Deployment Instructions for ppv-pos-scan.php

## File to Deploy
- **Source:** `ppv-pos-scan.php` (in this folder)
- **Destination:** `/home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php`

## What This Update Includes
✅ **Comprehensive Debug Logging** - Track the entire scan flow with detailed logs
✅ **Fixed PHP 8.1 Deprecation Warning** - Null check before trim()
✅ **Increased Rate Limit** - From 10 to 100 scans/minute for debugging
✅ **IP Address Tracking** - Logs IP addresses and user agents properly

## Deployment Methods

### Method 1: File Manager (Recommended for Hosting Control Panel)
1. Log into your hosting control panel (Plesk/cPanel)
2. Navigate to: **File Manager** → `/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/`
3. **Backup the current file first:**
   - Download `ppv-pos-scan.php` as `ppv-pos-scan.php.backup`
4. Upload the new `ppv-pos-scan.php` from this folder
5. Overwrite the existing file

### Method 2: FTP/SFTP
1. Connect to your server via FTP/SFTP
2. Navigate to: `/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/`
3. **Backup first:** Download `ppv-pos-scan.php` to your local machine
4. Upload the new `ppv-pos-scan.php` and overwrite

### Method 3: SSH (If you have terminal access)
```bash
# Backup current file
cp /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php \
   /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php.backup

# Upload new file (adjust the source path to where you downloaded this file)
cp /path/to/downloaded/ppv-pos-scan.php \
   /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php
```

## After Deployment - Verify It Works

### Step 1: Check File Timestamp
- In your hosting File Manager, verify that `ppv-pos-scan.php` shows today's date/time

### Step 2: Clear WordPress Cache (if any)
- If using a caching plugin (WP Super Cache, W3 Total Cache, etc.), clear all caches
- If using Cloudflare, purge cache

### Step 3: Test a Scan
1. Go to the scanner page
2. Scan a QR code
3. Check the error log immediately after

### Step 4: Look for Debug Logs
You should now see logs like this in your error log:

```
[19-Nov-2025 23:45:56 UTC] 🚀 [SCAN START] Request received
[19-Nov-2025 23:45:56 UTC] 📋 [SCAN DATA] QR: xxx... | Lang: de
[19-Nov-2025 23:45:56 UTC] 🔍 [RATE LIMIT CHECK] IP: 123.45.67.89 | Recent scans: 1/100
[19-Nov-2025 23:45:56 UTC] ✅ [RATE LIMIT] IP 123.45.67.89 passed - continuing scan
[19-Nov-2025 23:45:56 UTC] 📍 [IP ADDRESS] Raw: '123.45.67.89' | Cleaned: '123.45.67.89'
[19-Nov-2025 23:45:56 UTC] ✅ [LOGGING SCAN] User: 3 | Store: 9 | Points: +1
[19-Nov-2025 23:45:56 UTC] 💾 [LOG_SCAN_ATTEMPT] Store: 9 | User: 3 | IP: '123.45.67.89' | Status: ok | Reason: ✅ +1 Punkte
[19-Nov-2025 23:45:56 UTC] ✅ [LOG_SCAN_ATTEMPT] Logged successfully. Insert ID: 123
```

## Troubleshooting

### If debug logs STILL don't appear:
1. **Check file permissions** - Should be 644 or 664
2. **Verify correct path** - Make sure you uploaded to the right location
3. **Check PHP version** - Should be PHP 7.4+ or PHP 8.x
4. **Restart PHP-FPM** (if you have access):
   ```bash
   service php-fpm restart
   ```
5. **Check for opcache** - If opcache is enabled, it might cache the old file:
   ```bash
   # Clear opcache (if you have shell access)
   php -r "opcache_reset();"
   ```

### If you get a 500 error after deployment:
1. **Restore the backup immediately**
2. Check error log for PHP syntax errors
3. Contact me with the error message

## Expected Outcomes After Deployment

✅ Debug logs appear with emoji markers (🚀, 🔍, 📍, 💾, ✅, ❌)
✅ IP addresses are captured in the database (no more NULL values)
✅ Rate limiting works correctly (100 scans/minute limit)
✅ 429 error root cause becomes visible in logs
✅ PHP 8.1 deprecation warning disappears

## Git Commit Reference
- **Commit:** 3906852
- **Message:** "DEBUG: Add comprehensive logging to track 429 error and IP address handling"
- **Date:** Nov 19, 2025
- **Branch:** claude/close-process-final-message-011FQvWFN7neaZGPg9VgRaeo
