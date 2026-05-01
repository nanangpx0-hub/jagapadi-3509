# Qwen AI API Setup Guide

## Overview
This guide explains how to configure Qwen AI API credentials for the JAGAPADI application.

## Problem Fixed
Previously, the Qwen Editor Token Manager returned a hardcoded `"dummy_token"`, causing 401 errors when calling the Qwen AI API.

## Solution
The system now implements proper OAuth2 token management with:
- Automatic token caching in `storage/cache/qwen_token.json`
- Token refresh before expiry (5-minute buffer)
- Support for multiple authentication flows
- Proper error handling and status reporting

## Configuration

### 1. Add Environment Variables

Add the following variables to your `.env` file:

```env
# Qwen AI API Configuration
# Get these from your Qwen AI dashboard: https://qwen.aliyun.com/

# Option 1: API Key + API Secret (Recommended)
QWEN_API_KEY=your_api_key_here
QWEN_API_SECRET=your_api_secret_here

# Option 2: OAuth2 Client Credentials (Alternative)
QWEN_CLIENT_ID=your_client_id_here
QWEN_CLIENT_SECRET=your_client_secret_here

# Optional: Refresh Token (if you have one)
QWEN_REFRESH_TOKEN=your_refresh_token_here

# Optional: Custom Token URL (defaults to Qwen's official endpoint)
# QWEN_TOKEN_URL=https://api.qwen.com/v1/oauth/token
```

### 2. Supported Authentication Methods

The system supports two authentication flows:

#### Method 1: API Key + API Secret (Default)
```
QWEN_API_KEY=sk-xxxxxxxxxxxxxxxx
QWEN_API_SECRET=xxxxxxxxxxxxxxxx
```

#### Method 2: OAuth2 Client Credentials
```
QWEN_CLIENT_ID=your_client_id
QWEN_CLIENT_SECRET=your_client_secret
```

### 3. Verify Configuration

After adding credentials, verify the setup:

```bash
# Check token status
curl -X GET http://your-domain/api/qwen/status \
  -H "X-API-Key: your_external_api_key"

# Expected response:
# {
#   "success": true,
#   "message": "Token status retrieved",
#   "data": {
#     "configured": true,
#     "cached": false,
#     "valid": false,
#     "expires_at": null,
#     "token_preview": null
#   }
# }
```

### 4. Test Token Generation

```bash
# Request a new token
curl -X POST http://your-domain/api/qwen/token \
  -H "X-API-Key: your_external_api_key"

# Expected response (when configured):
# {
#   "success": true,
#   "message": "Success",
#   "data": {
#     "access_token": "eyJhbGciOiJIUzI1NiIs..."
#   },
#   "timestamp": "2025-01-15 10:30:00"
# }
```

## API Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/qwen/token` | Get access token | external_auth + rate_limit |
| GET | `/api/qwen/status` | Check token status | external_auth + rate_limit |

## Token Cache

Tokens are cached in `storage/cache/qwen_token.json` with the following structure:

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "expires_at": 1705312800,
  "cached_at": 1705309200
}
```

The cache automatically refreshes 5 minutes before expiry.

## Troubleshooting

### Error: "Qwen API credentials not configured"
**Cause**: Environment variables not set  
**Solution**: Add `QWEN_API_KEY` and `QWEN_API_SECRET` to `.env`

### Error: "Token refresh failed with HTTP 401"
**Cause**: Invalid API credentials  
**Solution**: Verify your API key and secret are correct

### Error: "Network error while refreshing token"
**Cause**: Cannot connect to Qwen API  
**Solution**: Check internet connection and firewall settings

### Error: "Invalid JSON response from token endpoint"
**Cause**: Qwen API returned non-JSON response  
**Solution**: Check if the token URL is correct

## Security Notes

1. **Never commit `.env` file** - It's already in `.gitignore`
2. **Rotate credentials regularly** - Change API keys every 90 days
3. **Use environment variables** - Never hardcode credentials in PHP files
4. **Monitor logs** - Check `storage/logs/` for token refresh errors
5. **Clear cache when rotating** - Delete `storage/cache/qwen_token.json` after credential changes

## Getting Qwen API Credentials

1. Visit [Qwen AI Platform](https://qwen.aliyun.com/)
2. Create an account or log in
3. Navigate to API Keys section
4. Generate a new API key pair
5. Copy the API Key and API Secret to your `.env` file

## Files Modified

- `config/api_config.php` - Added environment variable loading
- `app/services/QwenEditorTokenManager.php` - Real OAuth2 implementation
- `app/controllers/Api/QwenController.php` - Added error handling and status endpoint
- `public/js/qwen-editor-token.js` - Better error handling
- `app/core/Router.php` - Added status route

## Support

For issues with Qwen API itself, contact:
- Qwen AI Documentation: https://qwen.aliyun.com/docs
- Qwen AI Support: https://qwen.aliyun.com/support
