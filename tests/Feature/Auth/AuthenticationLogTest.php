<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Torann\GeoIP\Location;

// Answers every lookup with $place, recording which IPs were asked about.
function fakeGeoIp(array $place): object
{
    $fake = new class($place) {
        public array $asked = [];

        public function __construct(private array $place) {}

        public function getLocation($ip)
        {
            $this->asked[] = $ip;

            return new Location($this->place + ['ip' => $ip]);
        }
    };
    app()->instance('geoip', $fake);

    return $fake;
}

test('a sign-in is logged with the visitor IP from behind a trusted proxy, and where it is', function () {
    config(['services.ip_lookup.enabled' => true, 'trustedproxy.proxies' => '*']);
    Http::fake();
    $geo = fakeGeoIp(['city' => 'Cebu City', 'state_name' => 'Central Visayas', 'country' => 'Philippines', 'default' => false]);
    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '112.198.10.20')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    $log = DB::table('authentication_logs')->where('user_id', $user->id)->first();
    expect($log->ip_address)->toBe('112.198.10.20')
        ->and($geo->asked)->toBe(['112.198.10.20']);

    // The server's own address is never asked of an outside service.
    Http::assertNothingSent();

    $this->actingAs($user)
        ->get('/profile?option=authentication-logs&count=10')
        ->assertJsonPath('data.0.ip', '112.198.10.20')
        ->assertJsonPath('data.0.location', 'Cebu City, Central Visayas, Philippines');
});

test('a sign-in from a private address records no place', function () {
    config(['services.ip_lookup.enabled' => true]);
    $geo = fakeGeoIp(['city' => 'New Haven', 'country' => 'United States', 'default' => true]);
    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.15'])
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($geo->asked)->toBe([]);

    $this->actingAs($user)
        ->get('/profile?option=authentication-logs&count=10')
        ->assertJsonPath('data.0.ip', '192.168.1.15')
        ->assertJsonPath('data.0.location', null);
});

test('a lookup that misses records no place rather than the GeoIP default', function () {
    config(['services.ip_lookup.enabled' => true]);
    fakeGeoIp(['city' => 'New Haven', 'state_name' => 'CT', 'country' => 'United States', 'default' => true]);
    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '112.198.10.20'])
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->actingAs($user)
        ->get('/profile?option=authentication-logs&count=10')
        ->assertJsonPath('data.0.location', null);
});
