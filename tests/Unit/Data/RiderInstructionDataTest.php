<?php

namespace Tests\Unit\Data;

use LBHurtado\Voucher\Data\RiderInstructionData;
use Tests\TestCase;

class RiderInstructionDataTest extends TestCase
{
    /** @test */
    public function it_can_create_rider_with_splash_only()
    {
        $data = RiderInstructionData::from([
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => '# Welcome!',
            'splash_timeout' => 10,
        ]);

        $this->assertNull($data->message);
        $this->assertNull($data->url);
        $this->assertNull($data->redirect_timeout);
        $this->assertEquals('# Welcome!', $data->splash);
        $this->assertEquals(10, $data->splash_timeout);
    }

    /** @test */
    public function it_serializes_splash_fields_correctly()
    {
        $data = RiderInstructionData::from([
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => '# Welcome!',
            'splash_timeout' => 10,
        ]);

        $array = $data->toArray();

        $this->assertArrayHasKey('splash', $array);
        $this->assertArrayHasKey('splash_timeout', $array);
        $this->assertEquals('# Welcome!', $array['splash']);
        $this->assertEquals(10, $array['splash_timeout']);
    }

    /** @test */
    public function it_creates_rider_with_all_fields()
    {
        $data = RiderInstructionData::from([
            'message' => 'Test message',
            'url' => 'https://example.com',
            'redirect_timeout' => 5,
            'splash' => '# Welcome!',
            'splash_timeout' => 10,
        ]);

        $this->assertEquals('Test message', $data->message);
        $this->assertEquals('https://example.com', $data->url);
        $this->assertEquals(5, $data->redirect_timeout);
        $this->assertEquals('# Welcome!', $data->splash);
        $this->assertEquals(10, $data->splash_timeout);
    }

    /** @test */
    public function it_handles_all_null_fields()
    {
        $data = RiderInstructionData::from([
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
        ]);

        $array = $data->toArray();

        // All fields should be present, even if null
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('redirect_timeout', $array);
        $this->assertArrayHasKey('splash', $array);
        $this->assertArrayHasKey('splash_timeout', $array);
    }

    /** @test */
    public function it_json_encodes_correctly()
    {
        $data = RiderInstructionData::from([
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => '# Welcome!',
            'splash_timeout' => 10,
        ]);

        $json = json_encode($data->toArray());
        $decoded = json_decode($json, true);

        $this->assertEquals('# Welcome!', $decoded['splash']);
        $this->assertEquals(10, $decoded['splash_timeout']);
    }
}
