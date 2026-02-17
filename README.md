# Cookie Tracking System

A self-hosted visitor tracking and analytics platform built with Laravel and Filament. Track visitors across your websites with browser fingerprinting, form submission capture, UTM attribution, and more.

## Features

- **Multi-tenant Support**: Track multiple websites with separate tracking pixels
- **Browser Fingerprinting**: Identify visitors using Canvas, WebGL, Audio fingerprints + cookies
- **Form Submission Tracking**: Capture form data including multi-step forms
- **UTM Attribution**: Track marketing campaign performance with first-touch attribution
- **Click Tracking**: Monitor button and link clicks
- **Engagement Metrics**: Track time on page and scroll depth
- **Ad Platform Integration**: Capture Facebook, Google, TikTok click IDs
- **Filament Admin Panel**: Beautiful dashboard with analytics and data management

## Requirements

- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Node.js & NPM (optional, for assets)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/uniwiperdomain-blip/cookie-tracking.git
cd cookie-tracking
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cookie_tracking
DB_USERNAME=your_username
DB_PASSWORD=your_password

APP_URL=https://your-domain.com
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Create Admin User

```bash
php artisan make:filament-user
```

### 6. Start the Server

```bash
php artisan serve
```

Access the admin panel at: `http://localhost:8000/admin`

## Usage

### Creating a Tracking Pixel

1. Log into the admin panel at `/admin`
2. Go to "Tracking Pixels" and click "Create"
3. Enter a name and optionally a domain
4. Copy the embed code from the pixel detail page

### Embedding the Pixel

Add the embed code to your website's `<head>` section:

```html
<script src="https://your-domain.com/api/pixel/YOUR_PIXEL_CODE.js" async></script>
```

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

// Or with just email
PixelTracking.identify('user@example.com');

// Manually track a form
PixelTracking.trackForm('#my-form');

// Get current tracking data
const data = PixelTracking.getTrackingData();
console.log(data.cookieId, data.timeOnPage, data.scrollDepth);
```

### Debug Mode

Enable debug mode to see tracking logs in the browser console:

```javascript
window.PixelTrackingDebug = true;
```

## API Endpoints

All endpoints accept POST requests with JSON body.

| Endpoint | Description |
|----------|-------------|
| `GET /api/pixel/{code}.js` | Serve tracking pixel script |
| `POST /api/track/pageview` | Record page view |
| `POST /api/track/form` | Record form submission |
| `POST /api/track/click` | Record click event |
| `POST /api/track/engagement` | Record engagement metrics |
| `POST /api/track/event` | Record custom event |
| `POST /api/track/identify` | Identify visitor |

## Tinker Commands

Use Laravel Tinker for quick database operations:

```bash
php artisan tinker
```

### Create a Tracking Pixel

```php
use App\Models\Tenant;

$tenant = Tenant::create([
    'name' => 'My Website',
    'domain' => 'https://example.com',
    'is_active' => true,
]);

echo $tenant->embed_code;
```

### Find Visitors by Email

```php
use App\Models\Visitor;

$visitors = Visitor::where('identified_email', 'user@example.com')->get();
```

### Get Recent Form Submissions

```php
use App\Models\FormSubmission;

$submissions = FormSubmission::with('visitor')
    ->whereNotNull('email')
    ->latest()
    ->take(10)
    ->get();

foreach ($submissions as $sub) {
    echo "{$sub->email} - {$sub->form_id} - {$sub->created_at}\n";
}
```

### Get Visitor Journey

```php
use App\Models\Visitor;

$visitor = Visitor::find(1);

// Get all pageviews
$visitor->pageviews()->orderBy('created_at')->get();

// Get form submissions
$visitor->formSubmissions;

// Get clicks
$visitor->clicks;
```

### Get Traffic by UTM Source

```php
use App\Models\Visitor;

Visitor::selectRaw('first_utm_source, COUNT(*) as count')
    ->whereNotNull('first_utm_source')
    ->groupBy('first_utm_source')
    ->orderByDesc('count')
    ->get();
```

### Get Conversion Rate

```php
use App\Models\Visitor;
use App\Models\FormSubmission;

$totalVisitors = Visitor::count();
$conversions = FormSubmission::distinct('visitor_id')->count('visitor_id');
$conversionRate = $totalVisitors > 0 ? ($conversions / $totalVisitors) * 100 : 0;

echo "Conversion Rate: " . round($conversionRate, 2) . "%";
```

### Merge Duplicate Visitors

```php
use App\Models\Visitor;
use App\Services\VisitorService;

$primary = Visitor::find(1);
$duplicate = Visitor::find(2);

$service = app(VisitorService::class);
$service->mergeVisitors($primary, $duplicate);
```

### Get Stats for a Specific Pixel

```php
use App\Models\Tenant;

$tenant = Tenant::where('pixel_code', 'YOUR_PIXEL_CODE')->first();

echo "Visitors: " . $tenant->visitors()->count() . "\n";
echo "Pageviews: " . $tenant->pageviews()->count() . "\n";
echo "Form Submissions: " . $tenant->formSubmissions()->count() . "\n";
echo "Today's Visitors: " . $tenant->visitors()->whereDate('first_seen_at', today())->count() . "\n";
```

### Export Form Submissions to CSV

```php
use App\Models\FormSubmission;

$submissions = FormSubmission::whereNotNull('email')
    ->select('email', 'phone', 'full_name', 'company', 'form_id', 'created_at')
    ->get();

$fp = fopen('exports/submissions.csv', 'w');
fputcsv($fp, ['Email', 'Phone', 'Name', 'Company', 'Form', 'Date']);

foreach ($submissions as $sub) {
    fputcsv($fp, [
        $sub->email,
        $sub->phone,
        $sub->full_name,
        $sub->company,
        $sub->form_id,
        $sub->created_at->format('Y-m-d H:i:s'),
    ]);
}

fclose($fp);
echo "Exported " . count($submissions) . " submissions";
```

## Database Schema

### Tables

- `tenants` - Tracking pixels/sites
- `visitors` - Unique visitors with fingerprints
- `pageviews` - Page visit records
- `form_submissions` - Captured form data
- `clicks` - Click events
- `engagements` - Time on page & scroll depth
- `events` - Custom events

## Configuration

### CORS

By default, the API allows requests from any origin. To restrict, edit `config/cors.php`:

```php
'allowed_origins' => ['https://your-site.com'],
```

### Cookie Settings

The tracking cookie (`pt_tid`) is set with:
- 365-day expiration
- `SameSite=Lax`
- `Secure` flag on HTTPS
- localStorage fallback for restricted browsers

## Security Considerations

- The pixel doesn't track passwords or hidden fields
- Form data is truncated to prevent oversized payloads
- IP addresses are stored for fraud detection (can be disabled)
- Consider GDPR compliance for EU visitors

## Deployment

### Production Checklist

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Configure a proper database (MySQL/PostgreSQL)
3. Set up SSL/HTTPS
4. Configure your web server (Nginx/Apache)
5. Set up queue worker for async processing (optional)
6. Configure backups for the database

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name tracking.yourdomain.com;
    root /var/www/cookie-tracking/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## License

MIT License

## Support

For issues and feature requests, please use the GitHub issue tracker.
