# 🎯 429 Error Fix - Deployment Package

## 🔍 Problem Found!

The **429 Too Many Requests** error is caused by rate limiting in the **PPV_QR class**.

### Root Cause
```php
const RATE_LIMIT_SCANS = 1;
const RATE_LIMIT_WINDOW = 86400;  // 24 hours
```

**This means:**
- Users can only scan **ONCE per 24 hours** at each store
- If they try to scan again within 24h → **429 error**
- This is **intentional business logic**, NOT a bug

### Why Debug Logs Weren't Showing

The actual scan handler is:
- ✅ **`PPV_QR::rest_process_scan()`** in `class-ppv-qr.php`
- ✅ Route: **`punktepass/v1/pos/scan`**

NOT:
- ❌ `PPV_POS_REST::handle_scan()` in `ppv-pos-scan.php`
- ❌ Route: `ppv/v1/pos/scan`

That's why the debug logs from `ppv-pos-scan.php` never appeared!

---

## 📦 Files to Deploy

### 1. **class-ppv-qr.php** (MOST IMPORTANT)
- **Source:** `deployment/class-ppv-qr.php`
- **Destination:** `/home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/class-ppv-qr.php`
- **Why:** This is the ACTUAL scan handler with debug logging

### 2. ~~ppv-pos-scan.php~~ (OPTIONAL - NOT USED)
- This file is NOT being used for scanner page scans
- Only deploy if you use the `ppv/v1` namespace elsewhere

---

## 🚀 Quick Deployment

### Step 1: Access Your Server
Use File Manager, FTP, or SSH to access:
```
/home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/
```

### Step 2: Backup Current File
Download and save as backup:
```
class-ppv-qr.php → class-ppv-qr.php.backup
```

### Step 3: Upload New File
Upload `class-ppv-qr.php` from the deployment folder

### Step 4: Clear All Caches
- WordPress cache (if using caching plugins)
- Cloudflare cache (if applicable)
- Browser cache (Ctrl+Shift+R)
- PHP opcache: `php -r "opcache_reset();"` (if shell access)

---

## 📋 What the Debug Logs Will Show

After deployment, when you scan a QR code, you'll see:

```
[2025-11-20 00:15:00 UTC] 🚀 [SCAN START] PPV_QR::rest_process_scan() called
[2025-11-20 00:15:00 UTC] 📋 [SCAN DATA] QR: PPU3MzqSH4dq... | Store Key: 5hwJGIroEq...
[2025-11-20 00:15:00 UTC] 🔐 [STORE VALIDATION] Validating store key...
[2025-11-20 00:15:00 UTC] ✅ [STORE VALIDATION] Store valid - ID: 9 | Name: Test Store
[2025-11-20 00:15:00 UTC] 🔓 [QR DECODE] Decoding user from QR code...
[2025-11-20 00:15:00 UTC] ✅ [QR DECODE] User decoded - ID: 3
[2025-11-20 00:15:00 UTC] 🔍 [RATE_LIMIT_CHECK] User: 3 | Store: 9 | Window: 86400s | Max: 1
[2025-11-20 00:15:00 UTC] 📊 [RATE_LIMIT_CHECK] Recent scans in last 24h: 1 (limit: 1)
[2025-11-20 00:15:00 UTC] 🚫 [RATE_LIMIT] User 3 BLOCKED - Already scanned 1 time(s) in last 24h at store 9
[2025-11-20 00:15:00 UTC] ⚠️ [SCAN RESULT] Returning 429 rate limit response
```

This will tell you:
- ✅ Which user is scanning
- ✅ Which store they're scanning at
- ✅ How many times they've already scanned in the last 24 hours
- ✅ Whether they're being blocked (429) or allowed

---

## 🔧 If You Want to Change the Rate Limit

Edit `class-ppv-qr.php` line 14-15:

### Allow Multiple Scans Per Day
```php
const RATE_LIMIT_SCANS = 5;        // Allow 5 scans
const RATE_LIMIT_WINDOW = 86400;   // Per 24 hours
```

### Allow 1 Scan Per Hour
```php
const RATE_LIMIT_SCANS = 1;        // Allow 1 scan
const RATE_LIMIT_WINDOW = 3600;    // Per 1 hour (3600 seconds)
```

### Disable Rate Limiting Entirely
```php
const RATE_LIMIT_SCANS = 999999;   // Effectively unlimited
const RATE_LIMIT_WINDOW = 60;      // Per 1 minute
```

---

## ✅ After Deployment Checklist

- [ ] Upload `class-ppv-qr.php` to production
- [ ] Clear all caches (WordPress, Cloudflare, browser, opcache)
- [ ] Scan a QR code on the scanner page
- [ ] Check error log immediately
- [ ] Look for emoji debug markers: 🚀 📋 🔐 🔓 🔍 📊 🚫 ✅
- [ ] Verify you see the rate limit check logs
- [ ] Confirm whether 429 is from rate limiting or something else

---

## 🆘 Troubleshooting

### Debug Logs Still Don't Appear
1. **Check file was uploaded correctly**
   ```bash
   grep "SCAN START" /path/to/class-ppv-qr.php
   # Should return: error_log("🚀 [SCAN START] PPV_QR::rest_process_scan() called");
   ```

2. **Check file modification time**
   - Should show today's date/time

3. **Clear PHP opcache**
   ```bash
   php -r "opcache_reset();"
   # or restart PHP-FPM
   service php-fpm restart
   ```

4. **Check error_log is enabled**
   ```bash
   php -i | grep error_log
   php -i | grep "log_errors"
   ```

### Still Getting 429 Error
If you see the debug logs and they show rate limiting is the cause:
- **Option 1:** Change `RATE_LIMIT_SCANS` constant (see above)
- **Option 2:** Clear old scans from database for testing:
  ```sql
  DELETE FROM wp_ppv_points
  WHERE user_id = 3 AND store_id = 9
  AND created >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
  ```

### Toast Notifications Still Not Working
The scan response now includes `store_name` in the success response, which should fix toast notifications showing store names.

---

## 📊 Summary

| Issue | Status | Solution |
|-------|--------|----------|
| 429 error | ✅ Found | Rate limiting: 1 scan per 24h |
| Debug logs missing | ✅ Fixed | Added to correct file (class-ppv-qr.php) |
| Toast notifications | ✅ Fixed | Added store_name to response |
| Wrong handler debugged | ✅ Fixed | Found actual handler (PPV_QR) |

**Next Step:** Deploy `class-ppv-qr.php` and check the debug logs! 🚀
