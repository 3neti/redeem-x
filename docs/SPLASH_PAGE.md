# Splash Page Documentation

## Overview

The splash page system provides a flexible, customizable welcome screen shown before voucher redemption. It supports multiple content formats, dynamic images, countdown timers, and per-campaign customization.

## Configuration

### Environment Variables

```bash
# Enable/disable splash page globally
SPLASH_ENABLED=true

# Default timeout in seconds (0 = no auto-advance)
SPLASH_DEFAULT_TIMEOUT=5

# Button label
SPLASH_BUTTON_LABEL="Continue Now"

# Application metadata
SPLASH_APP_AUTHOR="3neti R&D OPC"
SPLASH_COPYRIGHT_HOLDER="3neti R&D OPC"
SPLASH_COPYRIGHT_YEAR=2025

# Optional: Custom default splash content (HTML/Markdown/SVG)
# SPLASH_DEFAULT_CONTENT="<h1>{app_name}</h1><p>Redeeming {voucher_code}</p>"
```

### Config File: `config/splash.php`

```php
return [
    'enabled' => env('SPLASH_ENABLED', true),
    'default_timeout' => env('SPLASH_DEFAULT_TIMEOUT', 5),
    'default_content' => env('SPLASH_DEFAULT_CONTENT', null),
    'app_author' => env('SPLASH_APP_AUTHOR', '3neti R&D OPC'),
    'copyright_holder' => env('SPLASH_COPYRIGHT_HOLDER', '3neti R&D OPC'),
    'copyright_year' => env('SPLASH_COPYRIGHT_YEAR', date('Y')),
    'button_label' => env('SPLASH_BUTTON_LABEL', 'Continue Now'),
];
```

## Content Types

The splash page auto-detects and renders multiple content formats:

### 1. HTML
```bash
SPLASH_DEFAULT_CONTENT='<div class="text-center"><h1 class="text-3xl font-bold">{app_name}</h1><p>Redeeming voucher {voucher_code}</p></div>'
```

### 2. Markdown
```bash
SPLASH_DEFAULT_CONTENT='# Welcome to {app_name}\n\n## Redeeming Voucher: {voucher_code}\n\nPlease wait...'
```

### 3. SVG
```bash
SPLASH_DEFAULT_CONTENT='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="150" height="150"><circle cx="100" cy="100" r="80" fill="#3b82f6"/><text x="100" y="110" font-size="24" text-anchor="middle" fill="white">REDEEM</text></svg>'
```

### 4. Plain Text
```bash
SPLASH_DEFAULT_CONTENT='Welcome to our voucher redemption system. Processing voucher {voucher_code}...'
```

### 5. External URL (iframe)
```bash
SPLASH_DEFAULT_CONTENT='https://example.com/splash-page'
```

## Template Variables

All content types support variable replacement:

| Variable | Description | Example Output |
|----------|-------------|----------------|
| `{app_name}` | Application name from `APP_NAME` | "Redeem-X" |
| `{app_version}` | Version from `composer.json` | "1.0.0" |
| `{app_author}` | Developer/company name | "3neti R&D OPC" |
| `{copyright_year}` | Copyright year | "2025" |
| `{copyright_holder}` | Copyright holder | "3neti R&D OPC" |
| `{voucher_code}` | Current voucher being redeemed | "ABC123" |

**Example:**
```html
<h1>{app_name}</h1>
<p>Version: {app_version}</p>
<p>Redeeming: {voucher_code}</p>
<p>© {copyright_year} {copyright_holder}</p>
```

## Dynamic Images

### Random Images APIs

#### Random Cats
```bash
SPLASH_DEFAULT_CONTENT='<div class="text-center"><img src="https://cataas.com/cat?width=600&height=400" alt="Cat" class="mx-auto rounded-lg shadow-lg mb-4"/><h2>Redeeming {voucher_code}</h2></div>'
```

#### Random Dogs
```bash
SPLASH_DEFAULT_CONTENT='<div class="text-center"><img src="https://random.dog/woof.jpg" alt="Dog" class="mx-auto rounded-lg shadow-lg mb-4"/><h2>{app_name}</h2></div>'
```

#### Random Nature Photos
```bash
SPLASH_DEFAULT_CONTENT='<div class="text-center"><img src="https://picsum.photos/800/500?random" alt="Nature" class="mx-auto rounded-xl shadow-2xl mb-4"/><h2>Processing voucher {voucher_code}</h2></div>'
```

#### Cat with Text Overlay
```bash
SPLASH_DEFAULT_CONTENT='<img src="https://cataas.com/cat/says/{voucher_code}?fontSize=40&fontColor=white&width=600&height=400" class="mx-auto rounded-lg shadow-lg"/>'
```

### Popular Dynamic Image APIs

| API | URL | Features |
|-----|-----|----------|
| **Cataas** | `https://cataas.com/cat` | Random cats, text overlay support |
| **Random Dog** | `https://random.dog/woof.jpg` | Random dog photos |
| **Random Fox** | `https://randomfox.ca/floof/` | Random fox photos |
| **Picsum Photos** | `https://picsum.photos/800/600?random` | Random nature/stock photos |
| **Unsplash Source** | `https://source.unsplash.com/random/800x600` | High-quality random photos |

## Per-Campaign Customization

Each voucher campaign can override the default splash page via the `splash` and `splash_timeout` fields:

### In Voucher Generation Form

```php
RiderInstructionData::from([
    'splash' => '<h1>Special Campaign</h1><img src="https://partner.com/logo.png"/>',
    'splash_timeout' => 10, // seconds
    'message' => 'Thank you for redeeming!',
    'url' => 'https://partner.com',
    'redirect_timeout' => 5,
])
```

### Priority Order

1. **Campaign-specific splash** (if set in voucher `rider.splash` field)
2. **Custom default content** (if `SPLASH_DEFAULT_CONTENT` is set)
3. **Built-in default** (auto-generated with app metadata)

## Timeout Behavior

| Timeout Value | Behavior |
|--------------|----------|
| `0` | No auto-advance, button only (no countdown shown) |
| `1-60` | Auto-advances after X seconds with countdown timer |
| `null/empty` | Uses `SPLASH_DEFAULT_TIMEOUT` (default: 5) |

**Examples:**
```bash
# No countdown, wait for button click
SPLASH_DEFAULT_TIMEOUT=0

# 10 second countdown
SPLASH_DEFAULT_TIMEOUT=10

# Per-campaign override (in voucher generation)
splash_timeout: 15
```

## Default Splash Content

When no custom content is provided, a beautiful default screen is auto-generated:

- 🎟️ Ticket icon
- App name (from `APP_NAME`)
- Version badge (from `composer.json`)
- "Redeeming voucher {CODE}" card
- Developer credit
- Copyright notice
- Fully responsive with dark mode support

## Security

All content is sanitized using **DOMPurify** to prevent XSS attacks while preserving:
- HTML tags
- SVG markup
- Image tags
- Iframe embeds

Malicious scripts are automatically stripped.

## Future Enhancement Ideas

### 1. Advertisement System

**Rotating Sponsor Banners:**
```php
// Database-driven ad rotation
$ad = Ad::active()->forZone('splash')->random()->first();

RiderInstructionData::from([
    'splash' => $ad->html_content,
    'splash_timeout' => $ad->display_duration,
])
```

**Ad Server Integration:**
```html
<img src="https://ads.yourcompany.com/serve?zone=splash&campaign={campaign_id}" />
```

**Analytics Tracking:**
- Splash view duration
- Button click-through rate
- A/B testing different designs
- Geographic/demographic targeting

### 2. Campaign-Specific Branding

**Partner Logo & Message:**
```html
<div class="text-center">
  <img src="https://partner.com/logo.png" class="mx-auto mb-4" width="200"/>
  <h2>Exclusive Offer from Partner Inc.</h2>
  <p>Redeeming voucher: {voucher_code}</p>
  <p class="text-sm text-gray-500">Powered by {app_name}</p>
</div>
```

**Video Splash:**
```html
<div class="text-center">
  <video autoplay muted loop class="mx-auto rounded-lg shadow-lg" width="600">
    <source src="https://cdn.example.com/promo.mp4" type="video/mp4">
  </video>
  <h2 class="mt-4">Processing your voucher...</h2>
</div>
```

### 3. Time/Context-Based Content

**Time-of-Day Personalization:**
```php
$hour = now()->hour;
$greeting = match(true) {
    $hour < 12 => 'Good morning',
    $hour < 18 => 'Good afternoon',
    default => 'Good evening',
};

$splash = "<h1>{$greeting}!</h1><p>Redeeming voucher {voucher_code}</p>";
```

**Location-Based Content:**
```php
// Show location-specific offers
$splash = Ad::forRegion($user->region)->random()->content;
```

**Event-Based:**
```php
// Special splash for holidays/events
if (now()->isChristmas()) {
    $splash = '<img src="/christmas-splash.jpg"/><h1>Holiday Special!</h1>';
}
```

### 4. Interactive Elements

**Countdown to Event:**
```html
<div class="text-center">
  <h1>Flash Sale Ending Soon!</h1>
  <div id="countdown" class="text-4xl font-bold text-red-600">5:00:00</div>
  <p>Redeeming voucher: {voucher_code}</p>
</div>
<script>/* countdown timer logic */</script>
```

**Social Media Embeds:**
```html
<div class="text-center">
  <blockquote class="twitter-tweet">
    <a href="https://twitter.com/company/status/123">Latest announcement</a>
  </blockquote>
  <script async src="https://platform.twitter.com/widgets.js"></script>
</div>
```

### 5. Monetization Strategies

**Sponsored Vouchers:**
- Brand-specific splash pages for sponsored campaigns
- Advertiser logos with "This voucher is brought to you by..."
- Affiliate tracking links embedded in splash

**Premium Campaigns:**
- Basic campaigns: Default splash
- Premium campaigns: Custom branded splash with videos
- Enterprise campaigns: Full interactive splash with analytics

**Ad Impressions:**
- Track splash views as ad impressions
- Sell splash inventory to advertisers
- Programmatic ad insertion via ad networks

### 6. Analytics & Optimization

**Track Metrics:**
```php
SplashView::create([
    'voucher_id' => $voucher->id,
    'campaign_id' => $voucher->campaign_id,
    'viewed_at' => now(),
    'user_agent' => request()->userAgent(),
    'duration' => $splashDuration, // Time spent on splash
    'skipped' => $buttonClicked, // Did user click "Continue" early?
]);
```

**A/B Testing:**
```php
// Randomly assign splash variants
$variant = rand(0, 1) ? 'splash_a' : 'splash_b';
$campaign->splash = Campaign::getSplashVariant($variant);
```

## Technical Architecture

### Components

- **Backend:** `packages/form-flow-manager/src/Handlers/SplashHandler.php`
- **Frontend:** `resources/js/pages/form-flow/core/Splash.vue`
- **Config:** `config/splash.php`
- **Driver:** `config/form-flow-drivers/voucher-redemption.yaml`

### Data Flow

1. User redeems voucher via `/disburse/{code}`
2. `DriverService` transforms voucher to form flow
3. Splash step extracted from `rider.splash` or default content generated
4. `SplashHandler` renders Inertia page with content + timeout
5. `Splash.vue` displays content with countdown timer
6. Auto-advances after timeout or manual button click
7. Proceeds to wallet information step

### Disabling Splash

**Globally:**
```bash
SPLASH_ENABLED=false
```

**Per-Campaign:**
Set both `splash` and `splash_timeout` to `null` or empty in voucher generation.

**Driver Condition:**
```yaml
splash:
  handler: "splash"
  condition: "{{ splash_enabled | default('true') }}"
```

## Best Practices

### Content Guidelines

✅ **DO:**
- Use responsive images (`max-width: 100%`)
- Add Tailwind classes for styling
- Keep text concise and readable
- Test with different screen sizes
- Use HTTPS for external resources

❌ **DON'T:**
- Embed untrusted external scripts
- Use excessive timeout (>30 seconds)
- Display sensitive information
- Forget to test variable replacement

### Performance

- Optimize images (compress, use WebP)
- Use CDN for static assets
- Set appropriate cache headers
- Lazy-load non-critical content
- Monitor splash load times

### Accessibility

- Add `alt` text to images
- Use semantic HTML headings
- Ensure sufficient color contrast
- Support keyboard navigation
- Test with screen readers

## Examples

### Simple Text Splash
```bash
SPLASH_DEFAULT_CONTENT='Welcome to {app_name}! Processing voucher {voucher_code}...'
```

### Brand Splash with Logo
```bash
SPLASH_DEFAULT_CONTENT='<div class="text-center"><img src="https://yourcompany.com/logo.png" class="mx-auto mb-4" width="200"/><h1 class="text-3xl font-bold">{app_name}</h1><p class="text-gray-600">Redeeming voucher: <span class="font-mono font-bold">{voucher_code}</span></p></div>'
```

### Video Splash
```bash
SPLASH_DEFAULT_CONTENT='<div class="text-center"><video autoplay muted loop class="mx-auto rounded-lg shadow-xl mb-4" width="600"><source src="https://cdn.example.com/splash-video.mp4" type="video/mp4"></video><h2 class="text-2xl font-bold">Processing your voucher...</h2></div>'
```

### Partner Advertisement
```bash
SPLASH_DEFAULT_CONTENT='<div class="bg-gradient-to-r from-purple-500 to-pink-500 text-white p-8 rounded-lg text-center"><h1 class="text-4xl font-bold mb-2">Special Offer!</h1><p class="text-xl">Get 20% off at PartnerStore.com</p><p class="text-sm mt-4">Code: {voucher_code}</p></div>'
```

## Troubleshooting

**Splash not showing:**
- Check `SPLASH_ENABLED=true`
- Verify `config:clear` was run after `.env` changes
- Confirm voucher has no empty splash field blocking default

**Timeout not working:**
- Empty string timeout triggers default (5s)
- Set `timeout: 0` for no auto-advance
- Check browser console for JavaScript errors

**Images not loading:**
- Use HTTPS URLs
- Check CORS policy on image host
- Verify URL is publicly accessible
- Test URL in browser directly

**Variables not replacing:**
- Ensure syntax is `{variable_name}` (curly braces)
- Check spelling matches exactly (case-sensitive)
- Verify variable is in `replaceVariables()` method

## Support

For issues or questions:
- Review test files: `tests/Feature/DefaultSplashScreenTest.php`
- Check handler: `packages/form-flow-manager/src/Handlers/SplashHandler.php`
- See component: `resources/js/pages/form-flow/core/Splash.vue`
