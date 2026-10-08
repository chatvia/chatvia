<?php

namespace Tests\Unit\Support\Http;

use PHPUnit\Framework\TestCase;
use Support\Http\SafeUrl;

class SafeUrlPublicIpTest extends TestCase
{
    public function test_classifies_private_ip_ranges_as_non_public(): void
    {
        foreach ([
            '10.0.0.1',
            '172.16.0.1',
            '192.168.1.1',
            '127.0.0.1',
            '169.254.1.1',
            '::1',
            'fc00::1',
            'fe80::1',
        ] as $ip) {
            $this->assertFalse(
                SafeUrl::isPublicIp($ip),
                "Expected {$ip} to be classified as private/reserved.",
            );
        }
    }

    public function test_classifies_public_ips_correctly(): void
    {
        foreach ([
            '1.1.1.1',
            '8.8.8.8',
            '142.250.74.46',
            '2606:4700:4700::1111',
        ] as $ip) {
            $this->assertTrue(
                SafeUrl::isPublicIp($ip),
                "Expected {$ip} to be classified as public.",
            );
        }
    }
}
