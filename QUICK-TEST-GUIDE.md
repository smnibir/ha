# Quick Test Guide - Hostaway Plugin

## 🚀 Quick Start Testing

### 1. Access the Plugin
- Go to WordPress Admin
- Navigate to **Hostaway Sync > Settings**

### 2. Enter Credentials
You need these from your Hostaway account:
- **Account ID** (numeric, e.g., 12345)
- **API Key** (long string from Settings > Hostaway API)

### 3. Test Buttons (in order)

#### ✅ Step 1: Test Connection
1. Click **"Test Hostaway Connection"**
2. **Expected**: Green box with "Connection successful! Found X properties"
3. **If error**: Check credentials, see error message for details

#### ✅ Step 2: Test Maps (Optional)
1. Enter Google Maps API Key
2. Click **"Test Google Maps"**
3. **Expected**: "Google Maps connection successful"

#### ✅ Step 3: Sync Data
1. Click **"Sync Now"**
2. **Expected**: 
   - Button changes to "Syncing..."
   - Success notification appears
   - Stats update on right sidebar
   - Recent logs update

#### ✅ Step 4: Load Amenities
1. Click **"Load Amenities from Hostaway"**
2. **Expected**: 
   - Checkboxes appear below button
   - Shows amenities from your properties
3. Select desired amenities
4. Click **"Save Settings"**

#### ✅ Step 5: Clear Cache (if needed)
1. Click **"Clear Cache"**
2. Confirm the dialog
3. **Expected**: "Cache cleared successfully" notification

## 🎯 What Should Work Now

| Feature | Status | What to Expect |
|---------|--------|----------------|
| Test Connection | ✅ Fixed | Shows success with property count or specific error |
| Test Maps | ✅ Fixed | Validates Google Maps API key |
| Sync Now | ✅ Fixed | Syncs properties, updates stats |
| Load Amenities | ✅ Fixed | Shows amenity checkboxes |
| Clear Cache | ✅ Fixed | Clears transients, forces fresh sync |

## 🐛 Troubleshooting

### Button doesn't work?
1. **Check browser console** (F12 → Console tab)
2. Look for red errors
3. Refresh page and try again

### "Nonce verification failed"?
1. Refresh the page
2. Clear browser cache
3. Try again

### "Failed to get access token"?
1. Double-check Account ID (should be numeric)
2. Verify API Key is correct
3. Ensure no extra spaces in fields
4. Check if API key is still active in Hostaway

### "No amenities found"?
1. Check if you have properties in Hostaway
2. Ensure properties have amenities configured
3. Try syncing properties first

### Connection test fails?
1. Verify credentials in Hostaway dashboard
2. Check WordPress debug log (`/wp-content/debug.log`)
3. Go to **Debug** page for detailed error info

## 📊 Expected Flow

```
1. Enter Credentials
   ↓
2. Test Connection ✅
   ↓
3. Sync Properties ✅
   ↓
4. Load Amenities ✅
   ↓
5. Configure Settings
   ↓
6. Save Settings ✅
```

## 🔍 Debug Checklist

If something doesn't work:

- [ ] WordPress is updated
- [ ] WooCommerce is active
- [ ] Admin user (has manage_options capability)
- [ ] Account ID is correct
- [ ] API Key is correct
- [ ] Browser cache cleared
- [ ] JavaScript enabled
- [ ] No console errors
- [ ] Debug log checked
- [ ] Page refreshed

## 📝 Quick API Setup

### Getting Hostaway Credentials:
1. Login to Hostaway
2. Go to **Settings** → **Hostaway API**
3. Click **"Create"** to generate API Key
4. Copy **Account ID** (visible in settings)
5. Copy **API Key** (shown only once!)
6. Paste both into WordPress plugin settings

### Important:
- ✅ Account ID = numbers only
- ✅ API Key = long alphanumeric string
- ✅ Save API Key securely (not shown again)
- ✅ Token valid for 24 months

## ✅ Success Indicators

When everything works correctly:

1. **Connection Test**: ✅ Green success message
2. **Manual Sync**: ✅ "Sync completed" notification
3. **Stats Widget**: ✅ Shows property counts
4. **Recent Logs**: ✅ Shows sync activities
5. **Amenities**: ✅ Checkboxes appear
6. **Properties Page**: ✅ Shows synced properties

## 🎉 All Fixed!

The plugin is now fully operational with all buttons working and proper error handling throughout.
