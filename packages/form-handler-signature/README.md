# Signature Handler Plugin

A Form Flow Manager plugin for capturing signatures using HTML5 canvas drawing.

## Features

✅ Canvas-based signature drawing (mouse/touch support)  
✅ Base64 image encoding  
✅ Configurable canvas dimensions & quality  
✅ Customizable stroke properties (width, color, cap, join)  
✅ High-DPI display support (device pixel ratio scaling)  
✅ Auto-registration with Form Flow Manager

## Installation

```bash
composer require lbhurtado/form-handler-signature
```

## Usage

```javascript
{
    handler: 'signature',
    config: {
        title: 'Sign Here',
        description: 'Please provide your signature',
        width: 600,
        height: 256,
        quality: 0.85,
        format: 'image/png',
        line_width: 2,
        line_color: '#000000',
        line_cap: 'round',
        line_join: 'round'
    }
}
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `width` | int | 600 | Canvas width in pixels |
| `height` | int | 256 | Canvas height in pixels |
| `quality` | float | 0.85 | Image compression quality (0-1) |
| `format` | string | 'image/png' | Output format (png/jpeg/webp) |
| `line_width` | int | 2 | Stroke width in pixels |
| `line_color` | string | '#000000' | Stroke color (hex) |
| `line_cap` | string | 'round' | Line ending style (butt/round/square) |
| `line_join` | string | 'round' | Line join style (bevel/round/miter) |

## Requirements

- PHP 8.2+
- Laravel 12+
- Browser with HTML5 canvas support

## Testing

```bash
cd packages/form-handler-signature
composer install
vendor/bin/pest
```

## License

Proprietary

## Author

Lester Hurtado <lester@hurtado.ph>
