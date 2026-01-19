# Logo Customization Guide

This guide explains how to change the application logo in Redeem-X.

## Current Implementation

The logo is displayed using a PNG image (`/public/images/logo.png`) rendered through the `AppLogoIcon` component.

**Key Files:**
- **Logo Image**: `/public/images/logo.png` - The actual logo file
- **Logo Component**: `/resources/js/components/AppLogoIcon.vue` - Renders the logo image
- **Logo Wrapper**: `/resources/js/components/AppLogo.vue` - Wraps logo with app name
- **Widget Usage**: `/resources/js/components/RedeemWidget.vue` and `/resources/js/components/PayWidget.vue` - Use logo in public pages

## Logo Display Locations

The logo appears in:
1. **Sidebar** (via `AppLogo` component) - Shows logo + app name
2. **Public Pages** (`/disburse`, `/pay`) - Shows logo only (via `AppLogoIcon`)
3. **Header** (mobile/desktop) - Shows logo + app name
4. **Public Layout** - Shows logo + app name

## How to Change the Logo

### Step 1: Prepare Your Logo Image

**Recommended specifications:**
- **Format**: PNG with transparent background
- **Aspect Ratio**: Portrait orientation (e.g., 2:3 or similar)
- **Size**: At least 1024px width for retina displays
- **Content**: Black or dark-colored design works best

**Why PNG?**
- Supports transparency (no background box)
- Better for complex designs than SVG
- Simpler to implement and maintain

### Step 2: Replace the Logo File

```bash
# Copy your new logo to the public images directory
cp /path/to/your/logo.png /path/to/redeem-x/public/images/logo.png
```

**Note**: The filename must be `logo.png` or you'll need to update the component (see Advanced Customization).

### Step 3: Clear Browser Cache

After replacing the logo:
1. Hard refresh your browser: `Cmd+Shift+R` (Mac) or `Ctrl+Shift+R` (Windows/Linux)
2. Or clear browser cache manually
3. If using Vite dev server, restart it: `npm run dev`

### Step 4: Test All Locations

Verify the logo appears correctly in:
- [ ] Sidebar navigation
- [ ] `/disburse` page (redeem widget)
- [ ] `/pay` page (payment widget)
- [ ] Mobile header
- [ ] Desktop header

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
