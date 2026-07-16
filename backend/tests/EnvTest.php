<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    protected function setUp(): void
    {
        Env::reset();
    }

    public function testReturnsDefaultWhenEnvFileNotFound(): void
    {
        $result = Env::get('NONEXISTENT_KEY', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testLoadDoesNotFailWhenFileMissing(): void
    {
        Env::load('/tmp/nonexistent/.env');
        $this->assertTrue(Env::isLoaded());
    }

    public function testReturnsEnvVarFromEnvironment(): void
    {
        putenv('TEST_JAGAPADI_KEY=test_value_123');
        $result = Env::get('TEST_JAGAPADI_KEY');
        $this->assertEquals('test_value_123', $result);
        putenv('TEST_JAGAPADI_KEY');
    }
}
