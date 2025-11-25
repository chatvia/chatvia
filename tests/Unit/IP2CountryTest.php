<?php

namespace Tests\Unit;

use App\Services\IP2Country;
use PHPUnit\Framework\TestCase;

class IP2CountryTest extends TestCase
{
    public function test_returns_empty_string_for_localhost_ip(): void
    {
        $result = IP2Country::getCountryByIP('127.0.0.1');

        $this->assertSame('', $result);
    }

    public function test_returns_empty_string_for_private_ip_addresses(): void
    {
        $privateIPs = [
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '169.254.1.1',
        ];

        foreach ($privateIPs as $ip) {
            $result = IP2Country::getCountryByIP($ip);
            $this->assertSame('', $result, "Failed for IP: {$ip}");
        }
    }

    public function test_returns_empty_string_for_invalid_ip_addresses(): void
    {
        $invalidIPs = [
            'not-an-ip',
            '999.999.999.999',
            '',
            'localhost',
        ];

        foreach ($invalidIPs as $ip) {
            $result = IP2Country::getCountryByIP($ip);
            $this->assertSame('', $result, "Failed for invalid IP: {$ip}");
        }
    }
}
