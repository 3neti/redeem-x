<?php

use LBHurtado\FormHandlerSelfie\SelfieHandler;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use Illuminate\Http\Request;

test('implements FormHandlerInterface', function () {
    $handler = new SelfieHandler();
    expect($handler)->toBeInstanceOf(FormHandlerInterface::class);
});

test('returns correct handler name', function () {
    $handler = new SelfieHandler();
    expect($handler->getName())->toBe('selfie');
});

test('validates required image field', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => []
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validates image format as string', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 12345, // Invalid: not a string
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('accepts valid base64 image', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'width' => 640,
            'height' => 480,
            'format' => 'image/jpeg',
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('image')
        ->and($result)->toHaveKey('timestamp');
});

test('validates width constraints', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/jpeg;base64,test',
            'width' => 100, // Too small (min: 320)
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validates height constraints', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/jpeg;base64,test',
            'height' => 2000, // Too large (max: 1080)
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('accepts valid image formats', function ($format) {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => "data:{$format};base64,test",
            'format' => $format,
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result['format'])->toBe($format);
})->with(['image/jpeg', 'image/png', 'image/webp']);

test('rejects invalid image formats', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/gif;base64,test',
            'format' => 'image/gif',
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('creates SelfieData with timestamp', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/jpeg;base64,test',
            'width' => 640,
            'height' => 480,
            'format' => 'image/jpeg',
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result['timestamp'])->toBeString()
        ->and($result['width'])->toBe(640)
        ->and($result['height'])->toBe(480)
        ->and($result['format'])->toBe('image/jpeg');
});

test('uses config defaults when values not provided', function () {
    $handler = new SelfieHandler();
    $request = Request::create('/test', 'POST', [
        'data' => [
            'image' => 'data:image/jpeg;base64,test',
            // No width, height, format provided
        ]
    ]);
    $step = FormFlowStepData::from(['handler' => 'selfie', 'config' => []]);
    
    $result = $handler->handle($request, $step);
    
    expect($result['width'])->toBe(640) // Default from config
        ->and($result['height'])->toBe(480)
        ->and($result['format'])->toBe('image/jpeg');
});

test('config schema validation', function () {
    $handler = new SelfieHandler();
    $schema = $handler->getConfigSchema();
    
    expect($schema)->toHaveKey('width')
        ->and($schema)->toHaveKey('height')
        ->and($schema)->toHaveKey('quality')
        ->and($schema)->toHaveKey('format')
        ->and($schema)->toHaveKey('facing_mode')
        ->and($schema)->toHaveKey('show_guide');
});

test('handler auto-registers with form-flow-manager', function () {
    $handlers = config('form-flow.handlers', []);
    
    expect($handlers)->toHaveKey('selfie')
        ->and($handlers['selfie'])->toBe(SelfieHandler::class);
});
