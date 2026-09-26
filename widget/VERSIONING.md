# Widget Versioning Architecture

## Overview

This document explains the three-layer versioning architecture designed to avoid browser caching issues while minimizing the code that users need to embed on their sites.

## Architecture Layers

```
User's Website
    ↓
embed.js (minimal, rarely changes)
    ↓
bootstrap.js (versioned, loads actual widget)
    ↓
widget.min.js / search.min.js (versioned bundles)
```

## Layer 1: Embed Script (`embed.js`)

**Location:** `dist/embed.js`  
**What users embed:** ✅ This is what gets embedded on user sites  
**Versioned:** ❌ No (but rarely changes)

### Purpose
- Minimal script that users embed once and never need to update
- Fetches the current version from `version.json`
- Loads the versioned `bootstrap.js` script

### Key Features
- Tiny file size (under 5KB)
- Simple version fetching logic
- Prevents multiple initializations
- Stores agent configuration in `window.chatvia`

### Example Usage
```html
<script 
  src="https://your-cdn.com/embed.js" 
  id="chatbot-embed-script"
  agent-id="YOUR_AGENT_ID"
  type="widget"
  language="auto"
></script>
```

## Layer 2: Bootstrap Script (`bootstrap.js`)

**Location:** `dist/{VERSION}/bootstrap.js`  
**Versioned:** ✅ Yes  
**Cache-busting:** Automatic via version path

### Purpose
- Loads the appropriate versioned bundle (widget or search)
- Handles widget initialization logic
- Fetches version info and loads the correct bundle

### Key Features
- Fully versioned in its own directory
- No caching issues - new versions get new URLs
- Contains the core initialization logic
- Determines which bundle to load (widget vs search)

## Layer 3: Widget Bundles

**Location:** `dist/{VERSION}/widget.min.js` and `dist/{VERSION}/search.min.js`  
**Versioned:** ✅ Yes  
**Cache-busting:** Automatic via version path

### Purpose
- The actual chat widget or search widget implementation
- All UI, logic, and functionality

## Version Flow

1. **User embeds `loader.js`** on their website
   ```html
   <script src="https://cdn.example.com/loader.js" agent-id="123"></script>
   ```

2. **`loader.js` executes:**
   - Fetches `/version.json?t=[timestamp]` (cache-busted with timestamp)
   - Reads current version (e.g., `"1.0.5"`)
   - Dynamically creates script tag: `<script src="/1.0.5/embed.js">`

3. **`embed.js` loads and executes:**
   - Reads widget configuration from `window.chatvia`
   - Fetches `/version.json` again to get bundle version
   - Loads the appropriate bundle: `/1.0.5/widget.min.js` or `/1.0.5/search.min.js`

4. **Widget/Search bundle loads:**
   - Initializes the actual widget
   - Renders UI and connects to backend

## Build Process

The rollup configuration creates four separate outputs:

```javascript
// 1. Main widget bundle (versioned)
dist/{VERSION}/widget.min.js

// 2. Search widget bundle (versioned)
dist/{VERSION}/search.min.js

// 3. Bootstrap script (versioned)
dist/{VERSION}/bootstrap.js

// 4. Embed script (unversioned)
dist/embed.js

// 5. Version manifest (unversioned, always fresh)
dist/version.json
```

### Build Commands
```bash
# Development build
npm run build

# Production build (with minification)
npm run build:prod
```

## Cache Strategy

### What Gets Cached
- `embed.js` - Cached by browsers (but rarely changes)
- Everything in `dist/{VERSION}/` - Aggressively cached (immutable)

### What Never Gets Cached
- `version.json` - Cache-busted with timestamp parameter

### Why This Works
1. **`embed.js` rarely changes** - It only contains version-fetching logic
2. **Version changes = new URLs** - `dist/1.0.5/` vs `dist/1.0.6/`
3. **Old versions stay available** - No breaking changes for cached pages
4. **`version.json` is always fresh** - Timestamp query parameter prevents caching

## Deployment Strategy

### Step 1: Build New Version
```bash
# Update version in .env
VITE_APP_VERSION=1.0.6

# Build production assets
npm run build:prod
```

### Step 2: Deploy Files
Upload to CDN:
```
dist/
  ├── embed.js (overwrite - rarely changes)
  ├── version.json (overwrite - always new)
  └── 1.0.6/ (new directory)
      ├── bootstrap.js
      ├── widget.min.js
      └── search.min.js
```

### Step 3: Verification
1. Check `version.json` returns new version
2. Test widget loads with new version URL
3. Verify old version still works for cached pages

## Advantages

✅ **No user action required** - Users never need to update their embed code  
✅ **Zero downtime** - Old and new versions coexist  
✅ **Instant updates** - New version loads immediately after deployment  
✅ **Minimal embed code** - Users embed tiny `embed.js` file  
✅ **Browser cache friendly** - Versioned assets cached aggressively  
✅ **Easy rollback** - Just update `version.json` to previous version  

## Troubleshooting

### Widget not updating after deployment?
1. Check `version.json` contains correct version
2. Verify new version files exist at `/VERSION/` path
3. Clear CDN cache for `version.json`
4. Check browser console for loading errors

### Users seeing old version?
- `version.json` might be cached by CDN
- Add cache control headers: `Cache-Control: no-cache`

### Embed script cached?
- This is okay! It rarely changes
- If needed to update, users need to clear cache or hard refresh

## No Migration Needed!

The embed script remains at the same URL, so existing integrations continue working without any changes.

## Environment Variables

The version is set via environment variable:

```env
VITE_APP_VERSION=1.0.6
VITE_SCRIPT_URL=https://cdn.example.com
```

## File Structure

```
src/
  ├── embed.js        # Minimal version loader (Layer 1)
  ├── bootstrap.js    # Versioned bundle loader (Layer 2)
  ├── main.js         # Chat widget entry (Layer 3)
  └── search.js       # Search widget entry (Layer 3)

dist/
  ├── embed.js            # Built embed script (unversioned)
  ├── version.json        # Version manifest
  └── {VERSION}/
      ├── bootstrap.js    # Versioned bootstrap script
      ├── widget.min.js   # Chat widget bundle
      └── search.min.js   # Search widget bundle
```

