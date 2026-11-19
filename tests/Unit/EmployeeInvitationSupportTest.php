<?php

namespace Tests\Unit;

use App\Exceptions\SmsProviderNotConfiguredException;
use App\Services\Sms\UnconfiguredSmsTransport;
use App\Support\EmployeePhone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmployeeInvitationSupportTest extends TestCase
{
    public function test_phone_is_normalized_to_e164(): void
    {
        $this->assertSame(
            '+79991234567',
            EmployeePhone::normalize(' +7 (999) 123-45-67 ')
        );
    }

    public function test_local_phone_without_country_code_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmployeePhone::normalize('89991234567');
    }

    public function test_unconfigured_sms_transport_fails_explicitly_without_sending(): void
    {
        $this->expectException(SmsProviderNotConfiguredException::class);

        (new UnconfiguredSmsTransport())->send(
            '+79991234567',
            'test'
        );
    }
}
