# Quick Deployment Checklist

## Pre-Deployment
- [ ] Access hosting control panel (Plesk/cPanel) or FTP
- [ ] Navigate to WordPress plugin directory
- [ ] **BACKUP CURRENT FILE FIRST** (Download as `.backup`)

## Deployment
- [ ] Upload `ppv-pos-scan.php` from this folder
- [ ] Overwrite existing file at:
      `/home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/api/ppv-pos-scan.php`
- [ ] Verify file timestamp shows today's date

## Post-Deployment
- [ ] Clear WordPress cache (if using caching plugins)
- [ ] Clear Cloudflare cache (if applicable)
- [ ] Clear opcache: `php -r "opcache_reset();"` (if shell access)
- [ ] Hard refresh browser (Ctrl+Shift+R)

## Testing
- [ ] Go to scanner page
- [ ] Scan a QR code
- [ ] Check error log **immediately** after scan

## Verify Success
- [ ] Look for emoji markers in error log: 🚀 🔍 📍 💾 ✅
- [ ] Confirm IP address is captured (not NULL)
- [ ] Check if 429 error still occurs
- [ ] Review debug output to identify root cause

## Expected Result
You should see logs like:
```
🚀 [SCAN START] Request received
📋 [SCAN DATA] QR: xxx... | Lang: de
🔍 [RATE LIMIT CHECK] IP: 123.45.67.89 | Recent scans: 1/100
✅ [RATE LIMIT] IP 123.45.67.89 passed - continuing scan
📍 [IP ADDRESS] Raw: '123.45.67.89' | Cleaned: '123.45.67.89'
✅ [LOGGING SCAN] User: 3 | Store: 9 | Points: +1
💾 [LOG_SCAN_ATTEMPT] Store: 9 | User: 3 | IP: '123.45.67.89' | Status: ok
✅ [LOG_SCAN_ATTEMPT] Logged successfully. Insert ID: 123
```

## If Something Goes Wrong
1. **500 Error** → Restore backup immediately, check error log for PHP errors
2. **No debug logs** → Check file was uploaded correctly, clear all caches
3. **Still 429 error** → Debug logs will show the cause now
4. **IP shows NULL** → Debug logs will show why IP detection fails

## Files in This Folder
- `ppv-pos-scan.php` - **The file to upload**
- `DEPLOYMENT_INSTRUCTIONS.md` - Detailed deployment guide
- `EXPECTED_DEBUG_OUTPUT.md` - What logs you should see
- `QUICK_CHECKLIST.md` - This file

---

**Ready to deploy!** 🚀
