<?php

use LBHurtado\FormFlowManager\Services\ExpressionEvaluator;
use LBHurtado\FormFlowManager\Services\TemplateRenderer;

beforeEach(function () {
    $this->renderer = new TemplateRenderer();
    $this->evaluator = new ExpressionEvaluator($this->renderer);
});

it('evaluates simple boolean expressions', function () {
    expect($this->evaluator->evaluate('true', []))->toBeTrue();
    expect($this->evaluator->evaluate('false', []))->toBeFalse();
});

it('evaluates comparison operators', function () {
    expect($this->evaluator->evaluate('5 > 3', []))->toBeTrue();
    expect($this->evaluator->evaluate('5 < 3', []))->toBeFalse();
    expect($this->evaluator->evaluate('5 >= 5', []))->toBeTrue();
    expect($this->evaluator->evaluate('5 <= 5', []))->toBeTrue();
    expect($this->evaluator->evaluate('5 == 5', []))->toBeTrue();
    expect($this->evaluator->evaluate('5 != 3', []))->toBeTrue();
});

it('evaluates in operator', function () {
    $context = ['item' => 'selfie'];
    expect($this->evaluator->evaluate("item in ['selfie', 'signature']", $context))->toBeTrue();
    
    $context = ['item' => 'location'];
    expect($this->evaluator->evaluate("item in ['selfie', 'signature']", $context))->toBeFalse();
});

it('evaluates logical AND', function () {
    expect($this->evaluator->evaluate('true && true', []))->toBeTrue();
    expect($this->evaluator->evaluate('true && false', []))->toBeFalse();
    expect($this->evaluator->evaluate('false && false', []))->toBeFalse();
});

it('evaluates logical OR', function () {
    expect($this->evaluator->evaluate('true || false', []))->toBeTrue();
    expect($this->evaluator->evaluate('false || false', []))->toBeFalse();
    expect($this->evaluator->evaluate('true || true', []))->toBeTrue();
});

it('evaluates logical NOT', function () {
    expect($this->evaluator->evaluate('!false', []))->toBeTrue();
    expect($this->evaluator->evaluate('!true', []))->toBeFalse();
});

it('evaluates empty function', function () {
    $context = ['value' => ''];
    expect($this->evaluator->evaluate('empty(value)', $context))->toBeTrue();
    
    $context = ['value' => 'hello'];
    expect($this->evaluator->evaluate('empty(value)', $context))->toBeFalse();
});

it('evaluates complex expressions', function () {
    $context = ['x' => 10, 'y' => 5];
    expect($this->evaluator->evaluate('x > 5 && y < 10', $context))->toBeTrue();
});

it('evaluates expressions with variables', function () {
    $context = ['status' => 'active'];
    expect($this->evaluator->evaluate('status == "active"', $context))->toBeTrue();
});

it('evaluates expressions with null values', function () {
    $context = ['value' => null];
    expect($this->evaluator->evaluate('empty(value)', $context))->toBeTrue();
});

it('normalizes single item to array context', function () {
    $result = $this->evaluator->evaluate('item == "test"', 'test');
    expect($result)->toBeTrue();
});
