<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_international_numbers_are_kept(): void
    {
        $this->assertSame('923001234567', PhoneNormalizer::normalize('+92 300 1234567', '92'));
        $this->assertSame('14155550123', PhoneNormalizer::normalize('+1 (415) 555-0123', '92'));
        $this->assertSame('923001234567', PhoneNormalizer::normalize('0092-300-1234567', '92'));
    }

    public function test_local_numbers_get_the_default_country_code(): void
    {
        $this->assertSame('923001234567', PhoneNormalizer::normalize('0300 1234567', '92'));
        $this->assertSame('923001234567', PhoneNormalizer::normalize('3001234567', '92'));
    }

    public function test_invalid_numbers_return_null(): void
    {
        $this->assertNull(PhoneNormalizer::normalize('', '92'));
        $this->assertNull(PhoneNormalizer::normalize('abc', '92'));
        $this->assertNull(PhoneNormalizer::normalize('123', '92'));
    }

    public function test_from_order_uses_first_valid_phone(): void
    {
        $order = ['phone' => null, 'shipping_address' => ['phone' => '0300 1234567']];
        $this->assertSame('923001234567', PhoneNormalizer::fromOrder($order, '92'));
    }
}
