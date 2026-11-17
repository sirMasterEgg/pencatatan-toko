<?php

namespace Tests\Unit;

use App\Support\Parse;
use PHPUnit\Framework\TestCase;

class ParseTest extends TestCase
{
    public function test_quantity(): void
    {
        $this->assertSame('0.5', Parse::quantity('0,5'));
        $this->assertSame('0.5', Parse::quantity('0.5'));
        $this->assertSame('1.25', Parse::quantity(' 1,25 '));
        $this->assertSame('2', Parse::quantity('2'));
        $this->assertSame('2', Parse::quantity(2));
        $this->assertNull(Parse::quantity(''));
        $this->assertNull(Parse::quantity(null));
        $this->assertSame('abc', Parse::quantity('abc'));
        $this->assertSame('1,2,3', Parse::quantity('1,2,3'));
    }
}
