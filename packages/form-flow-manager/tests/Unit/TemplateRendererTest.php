<?php

use LBHurtado\FormFlowManager\Services\TemplateRenderer;

beforeEach(function () {
    $this->renderer = new TemplateRenderer();
});

it('renders simple variables', function () {
    $result = $this->renderer->render('{{ name }}', ['name' => 'John']);
    expect($result)->toBe('John');
});

it('renders dot notation', function () {
    $context = [
        'source' => (object) ['code' => 'ABC123'],
    ];
    $result = $this->renderer->render('{{ source.code }}', $context);
    expect($result)->toBe('ABC123');
});

it('renders nested dot notation', function () {
    $context = [
        'source' => (object) [
            'owner' => (object) ['name' => 'Alice'],
        ],
    ];
    $result = $this->renderer->render('{{ source.owner.name }}', $context);
    expect($result)->toBe('Alice');
});

it('handles null coalescing operator', function () {
    $context = ['name' => null];
    $result = $this->renderer->render('{{ name ?? "Default" }}', $context);
    expect($result)->toBe('Default');
});

it('handles null coalescing with existing value', function () {
    $context = ['name' => 'Alice'];
    $result = $this->renderer->render('{{ name ?? "Default" }}', $context);
    expect($result)->toBe('Alice');
});

it('handles concatenation with tilde', function () {
    $context = ['code' => 'ABC'];
    $result = $this->renderer->render('{{ "voucher_" ~ code }}', $context);
    expect($result)->toBe('voucher_ABC');
});

it('handles function calls', function () {
    $context = [
        'strtoupper' => fn($str) => strtoupper($str),
    ];
    $result = $this->renderer->render('{{ strtoupper("hello") }}', $context);
    expect($result)->toBe('HELLO');
});

it('handles multiple placeholders', function () {
    $context = ['first' => 'John', 'last' => 'Doe'];
    $result = $this->renderer->render('{{ first }} {{ last }}', $context);
    expect($result)->toBe('John Doe');
});

it('handles templates without placeholders', function () {
    $result = $this->renderer->render('No placeholders here', []);
    expect($result)->toBe('No placeholders here');
});

it('handles empty values', function () {
    $context = ['value' => ''];
    $result = $this->renderer->render('{{ value }}', $context);
    expect($result)->toBe('');
});

it('handles missing keys gracefully', function () {
    $result = $this->renderer->render('{{ missing }}', []);
    expect($result)->toBe('');
});

it('handles boolean values', function () {
    $context = ['flag' => true];
    $result = $this->renderer->render('{{ flag }}', $context);
    expect($result)->toBe('true');
});

it('handles array values as JSON', function () {
    $context = ['items' => ['a', 'b', 'c']];
    $result = $this->renderer->render('{{ items }}', $context);
    expect($result)->toBe('["a","b","c"]');
});

it('handles function calls with multiple arguments', function () {
    $context = [
        'concat' => fn($a, $b) => $a . $b,
    ];
    $result = $this->renderer->render('{{ concat("Hello", "World") }}', $context);
    expect($result)->toBe('HelloWorld');
});

it('handles nested function calls', function () {
    $context = [
        'route' => fn(...$args) => '/api/' . implode('/', $args),
    ];
    $result = $this->renderer->render('{{ route("redeem", "ABC123") }}', $context);
    expect($result)->toBe('/api/redeem/ABC123');
});
