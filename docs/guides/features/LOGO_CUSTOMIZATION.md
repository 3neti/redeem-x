# Logo Customization Guide

This guide explains how to change the application logo in Redeem-X.

## Current Implementation

The logo system uses a **configurable theme architecture** with automatic dark mode detection.

**Current Logo Design: Tubular Pulley**
- Simpler, more modern design introduced January 2026
- Portrait orientation with transparent background
- Available in three color variants: slate, silver, orange

**Key Files:**
- **Logo Assets**: `/public/images/logo-*.png` - Active logo files (currently tubular pulley)
- **Design-Specific Assets**: `/public/images/{design_name}_logo-*.png` - Archived designs
- **Logo Component**: `/resources/js/components/AppLogoIcon.vue` - Renders logo with theme switching
- **Logo Wrapper**: `/resources/js/components/AppLogo.vue` - Wraps logo with app name
- **Configuration**: `/config/branding.php` - Theme definitions and paths
- **Widget Usage**: `/resources/js/components/RedeemWidget.vue` and `/resources/js/components/PayWidget.vue` - Use logo in public pages

### Available Themes

The application includes three pre-configured themes:

#### 1. Gray Theme (Default) - Professional
- **Light Mode**: Slate `#475569` (contrast 9.2:1 on white)
- **Dark Mode**: Silver `#C0C0C0` (contrast 11.8:1 on dark)
- **Files**: `/public/images/logo-slate.png`, `/public/images/logo-silver.png`
- **Use Case**: Default professional branding

#### 2. Orange Theme - Special Occasions
- **Color**: `#F97316` (Tailwind orange-500)
- **Both Modes**: Same orange color works in light and dark
- **Files**: `/public/images/logo-orange.png`
- **Contrast**: Light 7.5:1, Dark 5.2:1 (WCAG AAA compliant)
- **Use Case**: Marketing campaigns, special events, holidays

#### 3. Custom Theme - User-Defined
- **Configuration**: Via environment variables
- **Files**: User-provided paths
- **Use Case**: Client-specific branding, A/B testing, seasonal variations

## Logo Display Locations

The logo appears in:
1. **Sidebar** (via `AppLogo` component) - Shows logo + app name
2. **Public Pages** (`/disburse`, `/pay`) - Shows logo only (via `AppLogoIcon`)
3. **Header** (mobile/desktop) - Shows logo + app name
4. **Public Layout** - Shows logo + app name

## Logo Design History

### Tubular Pulley (Current - January 2026)
**Design:** Simpler, cleaner pulley design with vertical orientation
- **Files:** `tubular_pulley_logo-slate.png`, `tubular_pulley_logo-silver.png`, `tubular_pulley_logo-orange.png`
- **Dimensions:** 1024x2544px (portrait)
- **Original Color:** `#231f20` (near-black)
- **File Size:** ~150KB per variant

### Treble Pulley (Archived - 2025)
**Design:** Original complex pulley mechanism design
- **Files:** `treble_pulley_logo-slate.png`, `treble_pulley_logo-silver.png`, `treble_pulley_logo-orange.png`
- **Dimensions:** 1024x1536px (2:3 portrait)
- **File Size:** ~219KB per variant
- **Status:** Preserved for rollback if needed

**To restore treble pulley:**
```bash
cp public/images/treble_pulley_logo-slate.png public/images/logo-slate.png
cp public/images/treble_pulley_logo-silver.png public/images/logo-silver.png
cp public/images/treble_pulley_logo-orange.png public/images/logo-orange.png
php artisan config:clear
```

## Design-Based Naming Convention

**File Naming Pattern:** `{design_name}_logo-{color}.png`

Examples:
- `tubular_pulley_logo-slate.png`
- `treble_pulley_logo-orange.png`
- `holiday_special_logo-silver.png`

**Benefits:**
- Clear design lineage for future logo changes
- Easy to switch between design families
- Preserves historical designs for rollback
- No configuration changes needed when switching

**Active vs. Archived:**
- **Active:** Generic names (`logo-slate.png`, `logo-silver.png`, `logo-orange.png`) - Used by application
- **Archived:** Design-specific names (`{design}_logo-*.png`) - Preserved for switching/rollback

**Switching Between Designs:**
```bash
# Switch to any archived design
cp public/images/{design_name}_logo-slate.png public/images/logo-slate.png
cp public/images/{design_name}_logo-silver.png public/images/logo-silver.png
cp public/images/{design_name}_logo-orange.png public/images/logo-orange.png
php artisan config:clear
```

## Logo Theme Configuration

### Quick Theme Switch

To switch between themes, update your `.env` file:

```bash
# Gray theme (default - professional)
LOGO_THEME=gray

# Orange theme (special occasions)
LOGO_THEME=orange

# Custom theme (your own logos)
LOGO_THEME=custom
LOGO_CUSTOM_LIGHT=/images/logo-holiday.png
LOGO_CUSTOM_DARK=/images/logo-holiday-dark.png
```

After changing the theme:
```bash
php artisan config:clear
php artisan config:cache  # Optional for production
```

### How Dark Mode Switching Works

1. **Automatic Detection**: `AppLogoIcon.vue` uses `MutationObserver` to watch for `.dark` class on `<html>`
2. **Reactive Switching**: Logo changes instantly when user toggles appearance
3. **No Flash**: Pre-configured paths prevent loading delays
4. **Fallback**: Defaults to `/images/logo.png` if config missing

### Theme Configuration File

All themes are defined in `/config/branding.php`:

```php
'logo' => [
    'theme' => env('LOGO_THEME', 'gray'),
    
    'themes' => [
        'gray' => [
            'light' => '/images/logo-slate.png',
            'dark' => '/images/logo-silver.png',
        ],
        'orange' => [
            'light' => '/images/logo-orange.png',
            'dark' => '/images/logo-orange.png',
        ],
        'custom' => [
            'light' => env('LOGO_CUSTOM_LIGHT'),
            'dark' => env('LOGO_CUSTOM_DARK'),
        ],
    ],
    
    'fallback' => '/images/logo.png',
],
```

## How to Change the Logo

### Method 1: Switch to Existing Theme (Easiest)

No file changes needed - just update `.env`:

```bash
# Switch to orange for a promotion
LOGO_THEME=orange
php artisan config:clear
```

### Method 2: Add Custom Theme

**Step 1: Prepare Your Logo Images**

**Recommended specifications:**
- **Format**: PNG with transparent background (RGBA, not RGB)
- **Aspect Ratio**: Portrait orientation (e.g., 2:3 or similar)
- **Size**: At least 1024px width for retina displays
- **Colors**: 
  - Light mode: Dark colors for contrast on white background
  - Dark mode: Light colors for contrast on dark background

**Why PNG?**
- Supports transparency (no background box)
- Better for complex designs than SVG
- Simpler to implement and maintain

**Step 2: Recolor Your Logo (if needed)**

Use ImageMagick to create theme-specific variants:

```bash
# Create slate version for light mode (dark gray)
magick original-logo.png -fuzz 15% -fill "#475569" -opaque "#ORIGINAL_COLOR" logo-custom-light.png

# Create silver version for dark mode (light gray)
magick original-logo.png -fuzz 15% -fill "#C0C0C0" -opaque "#ORIGINAL_COLOR" logo-custom-dark.png
```

**Step 3: Copy Logo Files**

```bash
# Copy your logo files to public images directory
cp logo-custom-light.png public/images/
cp logo-custom-dark.png public/images/
```

**Step 4: Configure Custom Theme**

Update `.env`:

```bash
LOGO_THEME=custom
LOGO_CUSTOM_LIGHT=/images/logo-custom-light.png
LOGO_CUSTOM_DARK=/images/logo-custom-dark.png
```

**Step 5: Clear Configuration Cache

```bash
php artisan config:clear
```

**Step 6: Clear Browser Cache & Test**

1. Hard refresh your browser: `Cmd+Shift+R` (Mac) or `Ctrl+Shift+R` (Windows/Linux)
2. Toggle between light and dark modes to verify both logos
3. Test all display locations

Verify the logo appears correctly in:
- [ ] Sidebar navigation (light mode)
- [ ] Sidebar navigation (dark mode)
- [ ] `/disburse` page (light mode)
- [ ] `/disburse` page (dark mode)
- [ ] `/pay` page (light mode)
- [ ] `/pay` page (dark mode)
- [ ] Mobile header (both modes)
- [ ] Desktop header (both modes)

## Logo Sizing

Current logo size is **80px height** (`h-20` in Tailwind CSS) with auto width to maintain aspect ratio.

**To adjust size**, edit these files:

```vue
<!-- /resources/js/components/AppLogo.vue -->
<AppLogoIcon class="h-20 w-auto" />  <!-- Change h-20 to h-24, h-16, etc. -->

<!-- /resources/js/components/RedeemWidget.vue (line ~134) -->
<AppLogoIcon class="h-20 w-auto" />

<!-- /resources/js/components/PayWidget.vue (line ~121) -->
<AppLogoIcon class="h-20 w-auto" />
```

**Tailwind height classes:**
- `h-12` = 48px
- `h-16` = 64px
- `h-20` = 80px (current)
- `h-24` = 96px
- `h-28` = 112px
- `h-32` = 128px

## Advanced Customization

### Using a Different File Path

If you want to use a different filename or path:

```vue
<!-- Edit: /resources/js/components/AppLogoIcon.vue -->
<template>
    <img
        src="/images/my-custom-logo.png"  <!-- Change this -->
        alt="Logo"
        :class="className"
        v-bind="$attrs"
    />
</template>
```

### Using SVG Instead

If you prefer SVG (for simpler designs):

1. Convert your logo to SVG with a single path
2. Update `AppLogoIcon.vue`:

```vue
<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 WIDTH HEIGHT"
        :class="className"
        v-bind="$attrs"
    >
        <path fill="#000000" d="YOUR_SVG_PATH_DATA" />
    </svg>
</template>
```

**Pros:**
- Scalable without quality loss
- Smaller file size
- Can use `currentColor` for theme-aware coloring

**Cons:**
- Requires SVG optimization
- Complex designs may not convert well
- Harder to maintain

### Adding Theme-Aware Logo

To show different logos for light/dark mode:

```vue
<!-- /resources/js/components/AppLogoIcon.vue -->
<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';

const { isDark } = useAppearance();
</script>

<template>
    <img
        :src="isDark ? '/images/logo-dark.png' : '/images/logo-light.png'"
        alt="Logo"
        :class="className"
        v-bind="$attrs"
    />
</template>
```

## Troubleshooting

### Logo Not Updating After Replacement

**Cause**: Browser cache holding old image

**Solution**:
1. Hard refresh: `Cmd+Shift+R` (Mac) or `Ctrl+Shift+R` (Windows/Linux)
2. Open DevTools → Network tab → Check "Disable cache"
3. Restart Vite dev server if in development

### Logo Appears Too Small/Large

**Cause**: Aspect ratio doesn't match container size

**Solution**:
1. Adjust the `h-20` class (see Logo Sizing section)
2. Ensure PNG has sufficient resolution (1024px+ width)
3. Check if padding/margins are affecting size

### Logo Has Unwanted Background

**Cause**: PNG doesn't have transparency

**Solution**:
1. Re-export PNG with transparent background
2. Use image editing tool to remove background
3. Save as PNG (not JPG which doesn't support transparency)

### Logo Appears Blurry on Retina Displays

**Cause**: Image resolution too low

**Solution**:
- Use at least 2x the display size (minimum 1024px width recommended)
- For 80px height display, use ~160px+ height image

## Examples

### Example: Changing to Your Company Logo

```bash
# 1. Create feature branch
git checkout -b feature/update-company-logo

# 2. Replace logo file
cp ~/Downloads/company-logo.png public/images/logo.png

# 3. Test in browser
npm run dev
# Visit http://redeem-x.test/disburse

# 4. Commit changes
git add public/images/logo.png
git commit -m "Update logo to company branding"

# 5. Merge to main
git checkout main
git merge feature/update-company-logo

# 6. Push to remote
git push origin main

# 7. Clean up branch
git branch -d feature/update-company-logo
```

### Example: Adjusting Logo Size

```vue
<!-- Make logo 50% larger (80px → 120px) -->

<!-- /resources/js/components/AppLogo.vue -->
<AppLogoIcon class="h-30 w-auto" />

<!-- /resources/js/components/RedeemWidget.vue -->
<AppLogoIcon class="h-30 w-auto" />

<!-- /resources/js/components/PayWidget.vue -->
<AppLogoIcon class="h-30 w-auto" />
```

## Migration Notes

### From Previous SVG Implementation

If upgrading from the old SVG-based logo:

**Old approach:**
- Inline SVG paths in `AppLogoIcon.vue`
- Used `currentColor` for theming
- Viewbox: `0 0 836 1254`

**New approach:**
- PNG image in `/public/images/`
- Fixed black color (or use theme-aware variant)
- Better display at various sizes

**Why changed:**
- Simpler to customize (just swap PNG file)
- Better rendering of complex designs
- Easier for non-developers to update

## Related Files

```
/public/images/logo.png                          # Logo image file
/resources/js/components/AppLogoIcon.vue         # Logo component
/resources/js/components/AppLogo.vue             # Logo + app name wrapper
/resources/js/components/AppSidebar.vue          # Sidebar usage
/resources/js/components/AppHeader.vue           # Header usage
/resources/js/components/RedeemWidget.vue        # Public page usage
/resources/js/components/PayWidget.vue           # Payment page usage
/resources/js/layouts/PublicLayout.vue           # Public layout usage
/docs/LOGO_CUSTOMIZATION.md                      # This documentation
```

## Best Practices

1. **Always use version control** when changing the logo
2. **Test on multiple devices** (desktop, mobile, tablet)
3. **Check both light and dark modes** if app supports theming
4. **Maintain consistent sizing** across all display locations
5. **Keep original logo file** as backup before replacing
6. **Document custom modifications** for future reference
7. **Use high-resolution images** (2x display size minimum)
8. **Optimize file size** (use PNG compression tools like TinyPNG)

## Support

For questions or issues:
1. Check this documentation first
2. Review git history: `git log --oneline -- public/images/logo.png`
3. Search for component usage: `grep -r "AppLogoIcon" resources/js/`
4. Refer to Tailwind CSS docs for sizing utilities
