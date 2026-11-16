<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('6281200001234', Phone::normalize('0812-0000-1234'));
        $this->assertSame('6281200001234', Phone::normalize('+62 812 0000 1234'));
        $this->assertSame('6281200001234', Phone::normalize('81200001234'));
        $this->assertNull(Phone::normalize(''));
        $this->assertNull(Phone::normalize(null));
    }
}
