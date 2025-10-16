# Hostaway API Credentials - Updated for OAuth 2.0

## ✅ **Problem Solved!**

You were absolutely right to be confused! The plugin was using generic "API Key" and "API Secret" terminology, but Hostaway actually uses **OAuth 2.0 Client Credentials Grant** with:

- **Account ID** (Client ID)
- **API Key** (Client Secret)

## **What I Fixed:**

### 1. **Updated API Client (`HostawayClient.php`)**
- ✅ Changed from `api_key` + `api_secret` to `account_id` + `api_key`
- ✅ Implemented proper OAuth 2.0 flow:
  1. Use Account ID + API Key to get access token
  2. Use access token for all API requests
  3. Cache tokens with automatic refresh
- ✅ Added proper error handling for token requests

### 2. **Updated Admin Settings (`Settings.php`)**
- ✅ Changed form fields from "API Key" + "API Secret" to "Account ID" + "API Key"
- ✅ Updated field descriptions to clarify:
  - Account ID = Client ID
  - API Key = Client Secret
- ✅ Updated JavaScript AJAX calls
- ✅ Updated validation notices

### 3. **Updated Default Options (`Activator.php`)**
- ✅ Changed default option names to match new structure

## **How Hostaway OAuth 2.0 Works:**

```bash
# Step 1: Get Access Token
POST https://api.hostaway.com/v1/accessTokens
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=YOUR_ACCOUNT_ID&client_secret=YOUR_API_KEY&scope=general

# Response:
{
  "token_type": "Bearer",
  "expires_in": 15897600,
  "access_token": "your_access_token"
}

# Step 2: Use Access Token for API Calls
GET https://api.hostaway.com/v1/listings
Authorization: Bearer your_access_token
```

## **What You Need to Do:**

1. **Get your credentials from Hostaway:**
   - Account ID (Client ID)
   - API Key (Client Secret)

2. **Enter them in WordPress Admin:**
   - Go to **Hostaway → Settings**
   - Enter your **Account ID** in the first field
   - Enter your **API Key** in the second field
   - Click **"Test Connection"** to verify

3. **The plugin will automatically:**
   - Get access tokens using OAuth 2.0
   - Cache tokens for efficiency
   - Refresh tokens when needed
   - Use tokens for all API calls

## **Field Mapping:**

| Hostaway Provides | Plugin Field | OAuth 2.0 Term |
|-------------------|--------------|----------------|
| Account ID        | Account ID   | client_id      |
| API Key           | API Key      | client_secret  |

## **Benefits of This Fix:**

- ✅ **Correct OAuth 2.0 implementation**
- ✅ **Automatic token management**
- ✅ **Better security** (tokens expire)
- ✅ **Matches Hostaway's actual API**
- ✅ **Clear field labels** (no more confusion!)

The plugin now correctly implements Hostaway's OAuth 2.0 authentication flow! 🎉
