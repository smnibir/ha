# Hostaway Plugin - Manual Sync & Test Connection Fix

## ✅ **Issues Fixed:**

### 1. **AJAX Parameter Mismatch**
- **Problem**: AJAX handlers were expecting `api_key` + `api_secret` but form was sending `account_id` + `api_key`
- **Fix**: Updated AJAX handlers to use correct parameter names

### 2. **API Endpoints Updated**
- **Problem**: Some endpoints were using incorrect paths
- **Fix**: Updated to use correct Hostaway API endpoints:
  - Rates: `/listings/{id}/calendar` (not `/calendar/rates`)
  - Availability: `/listings/{id}/calendar` (not `/calendar/availability`)

### 3. **Better Error Handling**
- **Problem**: No debugging information when API calls failed
- **Fix**: Added console logging and error logging for debugging

### 4. **Improved Response Handling**
- **Problem**: JavaScript wasn't handling API responses correctly
- **Fix**: Updated JavaScript to properly display success/error messages

## **What Was Fixed:**

### **AJAX Handlers (`includes/Plugin.php`)**
```php
// OLD (broken)
$api_key = sanitize_text_field($_POST['api_key'] ?? '');
$api_secret = sanitize_text_field($_POST['api_secret'] ?? '');

// NEW (fixed)
$account_id = sanitize_text_field($_POST['account_id'] ?? '');
$api_key = sanitize_text_field($_POST['api_key'] ?? '');
```

### **JavaScript (`includes/Admin/Settings.php`)**
```javascript
// OLD (broken)
if (response.success) {
    result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
}

// NEW (fixed)
if (response.success) {
    result.html('<span style="color: green;">✓ ' + response.message + '</span>');
}
```

### **API Endpoints (`includes/API/HostawayClient.php`)**
```php
// OLD (incorrect)
return $this->makeRequest('GET', "/listings/{$property_id}/calendar/rates", $params);

// NEW (correct)
return $this->makeRequest('GET', "/listings/{$property_id}/calendar", $params);
```

## **How to Test:**

### **1. Upload Updated Files**
Upload the updated plugin files to your WordPress site.

### **2. Configure Credentials**
1. Go to **WordPress Admin → Hostaway → Settings**
2. Enter your **Account ID** (from Hostaway dashboard)
3. Enter your **API Key** (from Hostaway dashboard)

### **3. Test Connection**
1. Click **"Test Connection"** button
2. Check browser console (F12) for debugging info
3. Should show success message if credentials are correct

### **4. Manual Sync**
1. Click **"Sync Now"** button
2. Check browser console for debugging info
3. Should show sync progress and results

### **5. Debug API (Optional)**
Run the test script from command line:
```bash
cd /path/to/your/plugin
php test-api.php
```

## **Troubleshooting:**

### **If Test Connection Still Fails:**

1. **Check Browser Console** (F12 → Console tab)
   - Look for JavaScript errors
   - Check AJAX response details

2. **Check WordPress Error Logs**
   - Look in `/wp-content/debug.log`
   - Search for "Hostaway API" errors

3. **Verify Credentials**
   - Double-check Account ID and API Key
   - Make sure they're from Hostaway dashboard
   - Test with cURL or Postman

4. **Check WordPress Debug**
   - Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

### **If Manual Sync Still Fails:**

1. **Check Sync Logs**
   - Look in WordPress error logs
   - Check for specific error messages

2. **Test Individual Components**
   - Test API connection first
   - Then try manual sync

3. **Check Database**
   - Verify tables were created
   - Check if any data was synced

## **Expected Behavior:**

### **Test Connection:**
- ✅ Shows "Testing..." while processing
- ✅ Shows green checkmark with success message
- ✅ Shows red X with error message if failed

### **Manual Sync:**
- ✅ Shows "Syncing..." while processing
- ✅ Shows green checkmark with success message
- ✅ Shows red X with error message if failed
- ✅ Creates/updates properties in database

## **Next Steps:**

1. **Test the fixes** with your Hostaway credentials
2. **Check browser console** for any remaining errors
3. **Verify properties are syncing** to the database
4. **Test frontend shortcodes** to display properties

The manual sync and test connection should now work properly! 🎉
