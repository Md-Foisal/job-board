<?php

use App\Enums\MembershipRole;
use App\Enums\StaffRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as OutsideAccount;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function candidateUser(): User
{
    $user = User::factory()->create();
    CandidateProfile::factory()->for($user)->create();

    return $user;
}

function employerUser(?Company $company = null, MembershipRole $role = MembershipRole::Member): User
{
    $user = User::factory()->create();

    Membership::factory()
        ->for($user)
        ->for($company ?? Company::factory()->create())
        ->create(['role' => $role]);

    return $user;
}

/**
 * Skill names in alphabetical order, for comparing which skills were found
 * without caring about the order they were found in.
 */
function skillNames(iterable $skills): array
{
    return collect($skills)->pluck('name')->sort()->values()->all();
}

function staffUser(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->create(['staff_role' => $role]);
}

/**
 * A staff member who can actually get into the admin panel, which
 * refuses anyone without two-factor authentication.
 */
function staffWithTwoFactor(StaffRole $role = StaffRole::Moderator): User
{
    return User::factory()->withTwoFactor()->create(['staff_role' => $role]);
}

/**
 * The smallest job form that passes validation, for tests that need a
 * posting saved through the employer's own screen.
 */
function jobFormPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Senior Laravel Developer',
        'description' => 'Build and maintain our hiring platform.',
        'employmentType' => 'full-time',
        'workplaceType' => 'remote',
        'locationCountry' => 'Bangladesh',
        'expiresAt' => now()->addMonth()->toDateString(),
    ], $overrides);
}

function fillJobForm($component, array $overrides = [])
{
    foreach (jobFormPayload($overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

/**
 * Makes every write to the table fail the way a database error does,
 * without touching the schema. Dropping or altering a table to force the
 * failure does not work on MySQL: schema changes commit the open
 * transaction, so the test's rows outlive it and break the tests after.
 */
function failWritesTo(string $table): void
{
    $pattern = '/^\s*(insert|update|delete)\b[^;]*?\b'.preg_quote($table, '/').'\b/i';

    DB::connection()->beforeExecuting(function (string $query, array $bindings, Connection $connection) use ($pattern, $table) {
        if (preg_match($pattern, $query) === 1) {
            throw new QueryException($connection->getName(), $query, $bindings, new PDOException("Writes to {$table} fail in this test."));
        }
    });
}

/**
 * Gives both sign-in providers credentials, so their buttons and routes
 * are on. Nothing here ever reaches Google or GitHub: tests fake the
 * round trip with Socialite::fake().
 */
function configureSignInProviders(): void
{
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.github.client_id' => 'github-client-id',
        'services.github.client_secret' => 'github-client-secret',
    ]);
}

/**
 * What Google or GitHub hands back after the person agrees: by default a
 * Google account whose address Google has verified.
 *
 * @param  array<string, mixed>  $attributes
 */
function fakeOutsideAccount(string $provider = 'google', array $attributes = []): void
{
    Socialite::fake($provider, OutsideAccount::fake([
        'id' => $provider.'-123',
        'nickname' => 'nadia-r',
        'name' => 'Nadia Rahman',
        'email' => 'nadia@gmail.com',
        'email_verified' => true,
        ...$attributes,
    ]));
}
