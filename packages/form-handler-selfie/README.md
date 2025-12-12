# Selfie Handler Plugin

A Form Flow Manager plugin for capturing selfie photos using browser camera (MediaDevices API).

## Features

✅ Browser camera capture (front/back camera)  
✅ Base64 image encoding  
✅ Configurable quality & dimensions  
✅ Face guide overlay (optional)  
✅ Auto-registration with Form Flow Manager

## Installation

```bash
composer require lbhurtado/form-handler-selfie
```

## Usage

```javascript
{
    handler: 'selfie',
    config: {
        title: 'Take a Selfie',
        width: 640,
        height: 480,
        quality: 0.85,
        format: 'image/jpeg',
        show_guide: true
    }
}
```

## Requirements

- PHP 8.2+
- Laravel 12+
- HTTPS (required for MediaDevices API)
- Browser with getUserMedia support

## Testing

```bash
cd packages/form-handler-selfie
composer install
vendor/bin/pest
```

## License

Proprietary

## Author

Lester Hurtado <lester@hurtado.ph>
