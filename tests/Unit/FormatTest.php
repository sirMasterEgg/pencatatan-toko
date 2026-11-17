<?php

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_rupiah(): void
    {
        $this->assertSame('Rp248.000', Format::rupiah(248000));
        $this->assertSame('Rp0', Format::rupiah(0));
        $this->assertSame('Rp0', Format::rupiah(null));
    }

    public function test_quantity(): void
    {
        $this->assertSame('2', Format::quantity(2));
        $this->assertSame('0,5', Format::quantity('0.500'));
        $this->assertSame('1,25', Format::quantity(1.25));
        $this->assertSame('1.000', Format::quantity(1000));
    }
}
