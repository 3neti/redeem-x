<?php

use LBHurtado\FormFlowManager\Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Unit', 'Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
