<?php

use LBHurtado\FormHandlerSignature\SignatureHandler;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use Illuminate\Http\Request;

test('implements FormHandlerInterface', function () {
    $handler = new SignatureHandler();
    expect($handler)->toBeInstanceOf(FormHandlerInterface::class);
});

test('returns correct handler name', function () {
    $handler = new SignatureHandler();
    expect($handler->getName())->toBe('signature');
});

test('validates required image field', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => []
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validates image format as string', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 12345, // Invalid: not a string
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('accepts valid base64 image', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'width' => 600,
            'height' => 256,
            'format' => 'image/png',
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('image')
        ->and($result)->toHaveKey('timestamp');
});

test('validates width constraints', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/png;base64,test',
            'width' => 50, // Too small (min: 100)
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validates height constraints', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/png;base64,test',
            'height' => 1500, // Too large (max: 1000)
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('accepts valid image formats', function ($format) {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => "data:{$format};base64,test",
            'format' => $format,
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result['format'])->toBe($format);
})->with(['image/png', 'image/jpeg', 'image/webp']);

test('rejects invalid image formats', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/gif;base64,test',
            'format' => 'image/gif',
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('creates SignatureData with timestamp', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/png;base64,test',
            'width' => 600,
            'height' => 256,
            'format' => 'image/png',
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result['timestamp'])->toBeString()
        ->and($result['width'])->toBe(600)
        ->and($result['height'])->toBe(256)
        ->and($result['format'])->toBe('image/png');
});

test('uses config defaults when values not provided', function () {
    $handler = new SignatureHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/png;base64,test',
            // No width, height, format provided
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'signature', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result['width'])->toBe(600) // Default from config
        ->and($result['height'])->toBe(256)
        ->and($result['format'])->toBe('image/png');
});

test('config schema validation includes drawing properties', function () {
    $handler = new SignatureHandler();
    $schema = $handler->getConfigSchema();
    
    expect($schema)->toHaveKey('width')
        ->and($schema)->toHaveKey('height')
        ->and($schema)->toHaveKey('quality')
        ->and($schema)->toHaveKey('format')
        ->and($schema)->toHaveKey('line_width')
        ->and($schema)->toHaveKey('line_color')
        ->and($schema)->toHaveKey('line_cap')
        ->and($schema)->toHaveKey('line_join');
});

test('handler auto-registers with form-flow-manager', function () {
    $handlers = config('form-flow.handlers', []);
    
    expect($handlers)->toHaveKey('signature')
        ->and($handlers['signature'])->toBe(SignatureHandler::class);
});

test('validates line_width in config schema', function () {
    $handler = new SignatureHandler();
    $schema = $handler->getConfigSchema();
    
    expect($schema['line_width'])->toContain('min:1')
        ->and($schema['line_width'])->toContain('max:10');
});

test('validates line_cap options in config schema', function () {
    $handler = new SignatureHandler();
    $schema = $handler->getConfigSchema();
    
    expect($schema['line_cap'])->toContain('butt')
        ->and($schema['line_cap'])->toContain('round')
        ->and($schema['line_cap'])->toContain('square');
});
