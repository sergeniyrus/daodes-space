<?php

namespace Tests\Feature;

use App\Contracts\SmsTransport;
use App\Exceptions\EmployeeInvitationRateLimitException;
use App\Exceptions\SmsProviderNotConfiguredException;
use App\Models\EmployeePhoneChallenge;
use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeMembership;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use App\Services\Sms\UnconfiguredSmsTransport;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Mockery;
use Tests\TestCase;

class EmployeeInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep route and login rate limiters active but backed by an in-memory
        // cache so the feature suite does not require an external Redis service.
        config([
            'cache.default' => 'array',
            'cache.stores.array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
        ]);

        $limiter = new RateLimiter(Cache::store('array'));

        $this->app->instance(RateLimiter::class, $limiter);
        RateLimiterFacade::swap($limiter);

        // Invitation and login throttles are tested at the service boundary;
        // the HTTP throttle middleware itself requires a Redis service in this app.
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->createLegacyOrganizationSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_invitation_stores_only_hmac_and_activates_the_employee_once(): void
    {
        $organization = $this->createOrganization('Invitation test');
        $employee = $this->createEmployee($organization, '+436601234567');

        $plainCode = null;
        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->with(
                $employee->phone,
                Mockery::on(function (string $message) use (&$plainCode): bool {
                    if (preg_match('/код подтверждения телефона ([0-9]{6})/u', $message, $matches) !== 1) {
                        return false;
                    }

                    $plainCode = $matches[1];

                    return str_contains($message, 'Действует 10 минут');
                })
            )
            ->andReturnNull();

        $service = new EmployeeInvitationService($transport);
        $service->sendInvitation((int) $employee->id, (int) $organization->id);

        $challenge = EmployeePhoneChallenge::query()->firstOrFail();

        $this->assertNotNull($plainCode);
        $this->assertSame(64, strlen($challenge->code_hash));
        $this->assertNotSame($plainCode, $challenge->code_hash);
        $this->assertArrayNotHasKey('code_hash', $challenge->toArray());
        $this->assertNotNull($challenge->sent_at);
        $this->assertSame(
            600,
            $challenge->expires_at->getTimestamp() - $challenge->created_at->getTimestamp()
        );

        $password = 'Secure-Staff-Pass-2026!';

        $this->assertTrue($service->activate($employee->phone, $plainCode, $password));

        $employee->refresh();

        $this->assertNotNull($employee->phone_verified_at);
        $this->assertTrue(Hash::check($password, $employee->password));
        $this->assertNotNull($challenge->fresh()->consumed_at);
        $this->assertFalse($service->activate($employee->phone, $plainCode, $password));
    }

    public function test_fifth_invalid_code_attempt_revokes_the_challenge(): void
    {
        $organization = $this->createOrganization('Attempts test');
        $employee = $this->createEmployee($organization, '+436601234568');

        $plainCode = null;
        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$plainCode): void {
                preg_match('/код подтверждения телефона ([0-9]{6})/u', $message, $matches);
                $plainCode = $matches[1] ?? null;
            });

        $service = new EmployeeInvitationService($transport);
        $service->sendInvitation((int) $employee->id, (int) $organization->id);

        $wrongCode = $plainCode === '000000' ? '000001' : '000000';

        $this->assertFalse($service->activate($employee->phone, '12345', 'Secure-Staff-Pass-2026!'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertFalse(
                $service->activate($employee->phone, $wrongCode, 'Secure-Staff-Pass-2026!')
            );
        }

        $challenge = EmployeePhoneChallenge::query()->firstOrFail();

        $this->assertSame(5, $challenge->attempts);
        $this->assertNotNull($challenge->revoked_at);
        $this->assertNull($challenge->consumed_at);
        $this->assertFalse(
            $service->activate($employee->phone, $plainCode, 'Secure-Staff-Pass-2026!')
        );
    }

    public function test_expired_invitation_cannot_be_used(): void
    {
        $organization = $this->createOrganization('Expiry test');
        $employee = $this->createEmployee($organization, '+436601234569');

        $plainCode = null;
        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$plainCode): void {
                preg_match('/код подтверждения телефона ([0-9]{6})/u', $message, $matches);
                $plainCode = $matches[1] ?? null;
            });

        $service = new EmployeeInvitationService($transport);
        $service->sendInvitation((int) $employee->id, (int) $organization->id);

        $challenge = EmployeePhoneChallenge::query()->firstOrFail();
        $challenge->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->assertFalse(
            $service->activate($employee->phone, $plainCode, 'Secure-Staff-Pass-2026!')
        );

        $this->assertNotNull($challenge->fresh()->revoked_at);
        $this->assertNull($employee->fresh()->phone_verified_at);
    }

    public function test_invites_from_different_organizations_do_not_revoke_each_other_until_activation(): void
    {
        $firstOrganization = $this->createOrganization('First organization');
        $secondOrganization = $this->createOrganization('Second organization');
        $employee = $this->createEmployee($firstOrganization, '+436601234570');
        $this->addMembership($employee, $secondOrganization, 'courier', 'pending');

        $codes = [];
        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->twice()
            ->andReturnUsing(function (string $phone, string $message) use (&$codes): void {
                preg_match('/код подтверждения телефона ([0-9]{6})/u', $message, $matches);
                $codes[] = $matches[1] ?? null;
            });

        $service = new EmployeeInvitationService($transport);
        $start = Carbon::now();
        Carbon::setTestNow($start);

        $service->sendInvitation((int) $employee->id, (int) $firstOrganization->id);
        Carbon::setTestNow($start->copy()->addSeconds(61));
        $service->sendInvitation((int) $employee->id, (int) $secondOrganization->id);

        $firstChallenge = EmployeePhoneChallenge::query()
            ->where('organization_id', $firstOrganization->id)
            ->firstOrFail();
        $secondChallenge = EmployeePhoneChallenge::query()
            ->where('organization_id', $secondOrganization->id)
            ->firstOrFail();

        $this->assertCount(2, $codes);
        $this->assertNotNull($codes[0]);
        $this->assertNotNull($codes[1]);
        $this->assertNull($firstChallenge->revoked_at);
        $this->assertNull($secondChallenge->revoked_at);

        // Более ранний код остаётся действительным, пока общая учётная запись не активирована.
        $this->assertTrue(
            $service->activate($employee->phone, $codes[0], 'Secure-Staff-Pass-2026!')
        );

        $this->assertNotNull($firstChallenge->fresh()->consumed_at);
        $this->assertNotNull($secondChallenge->fresh()->revoked_at);
        $this->assertSame(1, $secondChallenge->fresh()->attempts);
    }

    public function test_invitation_cooldown_and_hourly_limit_are_enforced_per_phone_account(): void
    {
        $organization = $this->createOrganization('Rate limit test');
        $employee = $this->createEmployee($organization, '+436601234571');

        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')->times(3)->andReturnNull();

        $service = new EmployeeInvitationService($transport);
        $start = Carbon::now();
        Carbon::setTestNow($start);

        $service->sendInvitation((int) $employee->id, (int) $organization->id);

        try {
            $service->sendInvitation((int) $employee->id, (int) $organization->id);
            $this->fail('The 60-second resend cooldown must be enforced.');
        } catch (EmployeeInvitationRateLimitException $exception) {
            $this->assertStringContainsString('минуту', $exception->getMessage());
        }

        Carbon::setTestNow($start->copy()->addSeconds(61));
        $service->sendInvitation((int) $employee->id, (int) $organization->id);

        Carbon::setTestNow($start->copy()->addSeconds(122));
        $service->sendInvitation((int) $employee->id, (int) $organization->id);

        Carbon::setTestNow($start->copy()->addSeconds(183));

        try {
            $service->sendInvitation((int) $employee->id, (int) $organization->id);
            $this->fail('The hourly invitation limit must be enforced.');
        } catch (EmployeeInvitationRateLimitException $exception) {
            $this->assertStringContainsString('лимит', $exception->getMessage());
        }

        $this->assertDatabaseCount('employee_phone_challenges', 3);
    }

    public function test_unconfigured_transport_never_marks_a_challenge_as_sent(): void
    {
        $organization = $this->createOrganization('No provider test');
        $employee = $this->createEmployee($organization, '+436601234572');

        $this->assertInstanceOf(UnconfiguredSmsTransport::class, app(SmsTransport::class));

        try {
            app(EmployeeInvitationService::class)->sendInvitation(
                (int) $employee->id,
                (int) $organization->id
            );

            $this->fail('The unconfigured transport must not simulate SMS delivery.');
        } catch (SmsProviderNotConfiguredException) {
            // Expected: no provider is configured and no SMS is sent.
        }

        $challenge = EmployeePhoneChallenge::query()->firstOrFail();

        $this->assertSame(64, strlen($challenge->code_hash));
        $this->assertNull($challenge->sent_at);
        $this->assertNotNull($challenge->revoked_at);
    }

    public function test_manager_cannot_resend_invitation_for_an_employee_of_another_organization(): void
    {
        $firstOrganization = $this->createOrganization('Manager organization');
        $secondOrganization = $this->createOrganization('Employee organization');
        $employee = $this->createEmployee($secondOrganization, '+436601234573');
        $manager = $this->createManager($firstOrganization, 'Manager cross tenant');

        $this->actingAs($manager, 'web')
            ->post(route('organizations.staff.invite', [$firstOrganization, $employee]))
            ->assertNotFound();

        $this->assertDatabaseCount('employee_phone_challenges', 0);
        $this->assertDatabaseHas('organization_employee_memberships', [
            'employee_id' => $employee->id,
            'organization_id' => $secondOrganization->id,
            'status' => 'pending',
        ]);
    }

    public function test_same_phone_is_reused_for_memberships_in_multiple_organizations(): void
    {
        $firstOrganization = $this->createOrganization('First manager organization');
        $secondOrganization = $this->createOrganization('Second manager organization');
        $firstManager = $this->createManager($firstOrganization, 'Manager first');
        $secondManager = $this->createManager($secondOrganization, 'Manager second');

        $payload = [
            'name' => 'Shared employee',
            'phone' => '+436601234574',
            'role' => 'cook',
        ];

        $this->actingAs($firstManager, 'web')
            ->post(route('organizations.staff.store', $firstOrganization), $payload)
            ->assertRedirect(route('organizations.manage', $firstOrganization));

        $this->actingAs($secondManager, 'web')
            ->post(route('organizations.staff.store', $secondOrganization), $payload)
            ->assertRedirect(route('organizations.manage', $secondOrganization));

        $employee = OrganizationEmployee::query()
            ->where('phone', $payload['phone'])
            ->firstOrFail();

        $this->assertDatabaseCount('organization_employees', 1);
        $this->assertDatabaseCount('organization_employee_memberships', 2);
        $this->assertSame(2, $employee->memberships()->count());
        $this->assertDatabaseCount('employee_phone_challenges', 1);
        $this->assertNotNull(EmployeePhoneChallenge::query()->firstOrFail()->revoked_at);
    }

    public function test_employee_login_and_logout_preserve_the_web_guard_and_rotate_the_session(): void
    {
        $organization = $this->createOrganization('Session test');
        $employee = $this->createEmployee(
            $organization,
            '+436601234575',
            'cook',
            'active',
            true
        );
        $employee->forceFill([
            'password' => 'Secure-Staff-Pass-2026!',
        ])->save();

        $manager = $this->createManager($organization, 'Manager session');

        $this->actingAs($manager, 'web')
            ->withSession(['session_marker' => 'preserved']);

        $beforeLoginSessionId = $this->app['session']->driver()->getId();

        $loginResponse = $this->post(route('employee.login.store'), [
            'phone' => $employee->phone,
            'password' => 'Secure-Staff-Pass-2026!',
        ])->assertRedirect(route('employee.dashboard'));

        $afterLoginSessionId = $this->app['session']->driver()->getId();

        $this->assertNotSame($beforeLoginSessionId, $afterLoginSessionId);
        $this->assertAuthenticatedAs($manager, 'web');
        $this->assertAuthenticatedAs($employee, 'employee');
        $loginResponse->assertSessionHas('session_marker', 'preserved');

        $logoutResponse = $this->post(route('employee.logout'))
            ->assertRedirect(route('employee.login'));

        $afterLogoutSessionId = $this->app['session']->driver()->getId();

        $this->assertNotSame($afterLoginSessionId, $afterLogoutSessionId);
        $this->assertAuthenticatedAs($manager, 'web');
        $this->assertGuest('employee');
        $logoutResponse->assertSessionHas('session_marker', 'preserved');
    }

    private function createOrganization(string $name): Organization
    {
        return Organization::query()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createEmployee(
        Organization $organization,
        string $phone,
        string $role = 'employee',
        string $status = 'pending',
        bool $verified = false
    ): OrganizationEmployee {
        $employee = OrganizationEmployee::query()->create([
            'name' => 'Test employee',
            'phone' => $phone,
            'password' => Str::random(64),
            'phone_verified_at' => $verified ? now() : null,
        ]);

        $this->addMembership($employee, $organization, $role, $status);

        return $employee;
    }

    private function addMembership(
        OrganizationEmployee $employee,
        Organization $organization,
        string $role,
        string $status
    ): OrganizationEmployeeMembership {
        return OrganizationEmployeeMembership::query()->create([
            'employee_id' => $employee->id,
            'organization_id' => $organization->id,
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function createManager(Organization $organization, string $name): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'password' => Hash::make('manager-password'),
            'keyword' => 'test-keyword',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_users')->insert([
            'organization_id' => $organization->id,
            'user_id' => $userId,
            'role' => 'manager',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    /**
     * The legacy organization tables have no migrations in this repository.
     * This minimal test-only schema is intentionally not a production migration.
     */
    private function createLegacyOrganizationSchema(): void
    {
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('organization_users')) {
            Schema::create('organization_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 50)->default('manager');
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['organization_id', 'user_id']);
            });
        }
    }
}
