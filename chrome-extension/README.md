# LinkDigest Chrome Extension

Save links to your WordPress LinkDigest plugin directly from any webpage!

## Features

- 🔗 Automatically captures page title and URL
- 📁 Select categories from your WordPress site
- 🏷️ Add tags
- ✍️ Add optional descriptions
- 🔒 Secure API key authentication
- 🚀 One-click saving

## Installation

### 1. Prepare WordPress Plugin

1. Go to your WordPress admin panel
2. Navigate to **LinkDigest → Settings**
3. Click **Generate API Key**
4. Copy the **API Endpoint** and **API Key**

### 2. Install Chrome Extension

1. Open Chrome and go to `chrome://extensions/`
2. Enable **Developer mode** (toggle in top right)
3. Click **Load unpacked**
4. Select the `chrome-extension` folder from your LinkDigest plugin directory
5. The LinkDigest extension should now appear in your extensions list

### 3. Configure Extension

1. Click the LinkDigest extension icon in Chrome toolbar
2. Click **Settings** (or **Open Settings**)
3. Paste your **API Endpoint** (e.g., `https://yoursite.com/wp-json/linkdigest/v1`)
4. Paste your **API Key**
5. Click **Save Settings**
6. The extension will test the connection and confirm if successful

## Usage

1. Navigate to any webpage you want to save
2. Click the LinkDigest extension icon
3. The title and URL are automatically filled
4. (Optional) Add a description
5. (Optional) Select categories
6. (Optional) Add tags (comma-separated)
7. Click **Save Link**
8. Done! The link is now in your WordPress LinkDigest

## Icons

⚠️ **Note**: This extension requires icon files. You need to create three PNG icon files:

- `icon16.png` (16x16 pixels)
- `icon48.png` (48x48 pixels)
- `icon128.png` (128x128 pixels)

Place these files in the `chrome-extension` folder. You can create simple icons with your logo or the LinkDigest branding.

## Troubleshooting

### "Failed to connect to WordPress"

- Check that your API Endpoint URL is correct
- Ensure your API Key is copied correctly (no extra spaces)
- Verify that your WordPress site is accessible
- Check that the LinkDigest plugin is activated in WordPress

### "Please configure your API settings first"

- Click the extension icon
- Click **Open Settings**
- Enter your API credentials

### Categories not loading

- Make sure you have created at least one category in WordPress (LinkDigest → Categories)
- Check your API credentials are correct

### CORS errors in console

- The WordPress plugin automatically handles CORS for Chrome extensions
- Ensure you're using the latest version of the LinkDigest WordPress plugin

## Security

- Your API key is stored securely in Chrome's sync storage
- The API key is only sent to your WordPress site
- Keep your API key private - don't share it with others
- You can regenerate your API key anytime in WordPress settings

## Development

The extension consists of:

- `manifest.json` - Extension configuration
- `popup.html` - Main popup interface
- `popup.js` - Popup logic and API calls
- `popup.css` - Popup styling
- `settings.html` - Settings page interface
- `settings.js` - Settings page logic
- `README.md` - This file

## Support

For issues or questions, please refer to the LinkDigest WordPress plugin documentation or create an issue in the plugin repository.
