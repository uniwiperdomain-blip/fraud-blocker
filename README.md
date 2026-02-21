# Fraud Blocker

Google Ads click fraud detection and IP blocking system built with Laravel 12 and Filament 5.1. Detects fraudulent ad clicks using behavioral analysis, bot detection, and IP intelligence, then automatically blocks offending IPs in your Google Ads campaigns.

## Features

- **Multi-tenant Support**: Track multiple websites with separate tracking pixels
- **Browser Fingerprinting**: Identify visitors using Canvas, WebGL, Audio fingerprints + cookies
- **Click Fraud Detection**: Score visitors against 4 fraud signals with configurable thresholds
- **Auto-blocking**: Automatically block IPs exceeding the fraud score threshold
- **Google Ads Integration**: Push blocked IPs to Google Ads campaign exclusions (OAuth API or manual export)
- **Bot Detection**: Client-side and server-side detection of headless browsers, bots, and automation tools
- **IP Intelligence**: VPN, proxy, and datacenter IP detection via ipinfo.io
- **Form Submission Tracking**: Capture form data including multi-step forms
- **UTM Attribution**: Track marketing campaign performance with first-touch attribution
- **Engagement Metrics**: Track time on page, scroll depth, and clicks
- **Ad Platform Integration**: Capture Facebook, Google, TikTok click IDs
- **Filament Admin Panel**: Dashboard with fraud analytics, detection charts, and management tools

## Fraud Detection Signals

| Signal | Default Points | Description |
|--------|---------------|-------------|
| Rapid Clicks | 30 | Same IP with 3+ ad clicks within 60 seconds |
| Bot Detection | 50 | Headless browsers, missing fingerprints, failed JS challenges |
| Low Engagement | 20 | Zero scroll depth, <2s on page, no clicks after ad click |
| VPN/Datacenter IP | 40 | Traffic from VPNs, proxies, and datacenter IPs (via ipinfo.io) |

Auto-block threshold: **100 points** within a 24-hour window (configurable per tenant).

## Requirements

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL
- Node.js & npm (for frontend assets)

## Installation

```bash
git clone https://github.com/fraud-blocker/fraud-blocker.git
cd fraud-blocker
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan filament:user
npm install && npm run build
php artisan serve
```

Access the admin panel at: `http://localhost:8000/admin`

## Environment Variables

Add these to your `.env` file:

```env
# Fraud Detection
FRAUD_DETECTION_ENABLED=true
IP_INTELLIGENCE_PROVIDER=ipinfo

# IPinfo.io (VPN/Datacenter detection)
IPINFO_TOKEN=your_token_here
IPINFO_ENABLED=true

# Google Ads API (optional - for automatic IP exclusion sync)
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_REDIRECT_URI="${APP_URL}/google-ads/callback"
```

## Usage

### Tracking Pixel

Create a tenant (tracking pixel) in the admin panel, then embed on your landing page:

```html
<script src="https://yourdomain.com/api/pixel/YOUR_PIXEL_CODE.js" defer></script>
```

The pixel automatically captures:
- Page views with UTM parameters and click IDs
- Browser fingerprint (canvas, WebGL, audio)
- Engagement metrics (scroll depth, time on page, clicks)
- Bot detection signals (webdriver, honeypot, JS challenge, mouse movement)

### JavaScript API

The tracking pixel exposes a `PixelTracking` object for manual tracking:

```javascript
// Track a custom event
PixelTracking.trackEvent('purchase', {
    product_id: '123',
    amount: 99.99
});

// Identify a visitor
PixelTracking.identify({
    email: 'user@example.com',
    phone: '+1234567890'
});

// Manually track a form
PixelTracking.trackForm('#my-form');

// Enable debug mode
window.PixelTrackingDebug = true;
```

### Admin Panel

Access the Filament admin at `/admin`:

- **Fraud Detections** — Browse individual fraud signal events with evidence
- **Blocked IPs** — Manage blocked IPs, manual blocking, export for Google Ads
- **Fraud Settings** — Configure thresholds per tracking pixel
- **Google Ads** — Connect via OAuth or manually export blocked IPs (CSV/copy)
- **Pageviews** — Browse all page views with fraud score badges
- **Visitors** — View visitor profiles with fraud history

### Google Ads Integration

**Option A: Automatic (OAuth)**
1. Configure `GOOGLE_ADS_*` env vars
2. Go to Google Ads Settings in the admin panel
3. Click "Connect Google Ads Account" and authorize via OAuth
4. Blocked IPs sync automatically every 15 minutes

**Option B: Manual Export**
1. Go to Blocked IPs in the admin panel
2. Use "Export IPs" to download CSV or copy to clipboard
3. Paste into Google Ads campaign IP exclusions

### Artisan Commands

```bash
# Bulk analyze recent pageviews for fraud
php artisan fraud:analyze --hours=24

# Analyze for a specific tenant
php artisan fraud:analyze --tenant=1

# Manually trigger Google Ads sync
php artisan fraud:sync-google
```

### Scheduled Jobs

Run the scheduler with `php artisan schedule:work`:

- **Sync Google Ads exclusions** — every 15 minutes
- **Cleanup expired blocks** — hourly
- **Prune old fraud logs** — daily (configurable retention)

## API Endpoints

All tracking endpoints accept POST requests with JSON body.

| Endpoint | Description |
|----------|-------------|
| `GET /api/pixel/{code}.js` | Serve tracking pixel script |
| `POST /api/track/pageview` | Record page view + real-time fraud check |
| `POST /api/track/form` | Record form submission |
| `POST /api/track/click` | Record click event |
| `POST /api/track/engagement` | Record engagement metrics |
| `POST /api/track/event` | Record custom event |
| `POST /api/track/identify` | Identify visitor |

## Architecture

```
app/
├── Console/Commands/          # fraud:analyze, fraud:sync-google
├── Filament/
│   ├── Pages/                 # FraudSettings, GoogleAdsSettings
│   ├── Resources/             # FraudLog, BlockedIp, Pageview, Visitor, etc.
│   └── Widgets/               # FraudStatsOverview, FraudDetectionsChart
├── Http/Controllers/
│   ├── Api/TrackingController # Pixel serving + tracking + fraud checks
│   └── GoogleAdsController    # OAuth redirect/callback/disconnect
├── Jobs/                      # AnalyzeFraud, SyncGoogleAds, CleanupExpiredBlocks
├── Models/                    # Tenant, Visitor, Pageview, FraudLog, BlockedIp, etc.
└── Services/
    ├── FraudDetectionService  # Core scoring engine (4 signals, auto-block)
    ├── IpIntelligenceService  # ipinfo.io with 24hr cache
    └── GoogleAdsService       # Google Ads REST API v18
```

## Database Schema

### Tables

- `tenants` — Tracking pixels/sites
- `visitors` — Unique visitors with fingerprints
- `pageviews` — Page visit records with fraud scores and bot signals
- `fraud_logs` — Individual fraud signal events with evidence
- `blocked_ips` — Blocked IP list with Google Ads sync status
- `fraud_settings` — Per-tenant configurable thresholds
- `google_ads_accounts` — OAuth credentials for Google Ads
- `form_submissions` — Captured form data
- `clicks` — Click events
- `engagements` — Time on page & scroll depth
- `events` — Custom events

## Configuration

### CORS

By default, the API allows requests from any origin. To restrict, edit `config/cors.php`:

```php
'allowed_origins' => ['https://your-site.com'],
```

### Fraud Detection Defaults

Edit `config/fraud.php` to change default thresholds, bot detection patterns, IP intelligence provider, and log retention settings.

### Cookie Settings

The tracking cookie (`pt_tid`) is set with:
- 365-day expiration
- `SameSite=Lax`
- `Secure` flag on HTTPS
- localStorage fallback for restricted browsers

## Deployment

### Docker / Dokploy

Build and run with Docker:

```bash
docker compose up -d
```

Or deploy via **Dokploy** — set these environment variables in your Dokploy service:

| Variable | Required | Example |
|----------|----------|---------|
| `APP_URL` | Yes | `https://fraud.yourdomain.com` |
| `APP_KEY` | No | Auto-generated if not set |
| `DB_LINK` | Yes | `mysql://user:pass@host:3306/dbname` |
| `IPINFO_TOKEN` | Recommended | Your ipinfo.io token |
| `FRAUD_DETECTION_ENABLED` | No | `true` (default) |
| `GOOGLE_ADS_CLIENT_ID` | No | For auto IP exclusion sync |
| `GOOGLE_ADS_CLIENT_SECRET` | No | For auto IP exclusion sync |
| `GOOGLE_ADS_DEVELOPER_TOKEN` | No | For auto IP exclusion sync |

**`DB_LINK`** accepts a full database URL. The driver is auto-detected from the scheme:

- `mysql://user:pass@host:3306/db` → MySQL
- `pgsql://user:pass@host:5432/db` → PostgreSQL
- `mariadb://user:pass@host:3306/db` → MariaDB

Alternatively, set individual `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` variables instead of `DB_LINK`.

The container handles migrations, config caching, and process management (Nginx, PHP-FPM, queue worker, scheduler) automatically.

### Production Checklist

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Configure a proper database (MySQL/PostgreSQL)
3. Set up SSL/HTTPS
4. Configure your web server (Nginx/Apache)
5. Set up queue worker: `php artisan queue:work`
6. Set up scheduler: cron entry for `php artisan schedule:run`
7. Configure `IPINFO_TOKEN` for VPN/datacenter detection
8. Configure Google Ads API credentials (optional)
9. Configure backups for the database

## Security Considerations

- The pixel doesn't track passwords or hidden fields
- Form data is truncated to prevent oversized payloads
- Blocked IPs receive fake 200 responses to avoid tipping off bots
- IP addresses are stored for fraud detection
- Consider GDPR compliance for EU visitors

## License

Proprietary.
