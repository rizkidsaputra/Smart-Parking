<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThingSpeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_sends_only_parking_totals_and_throttles_repeated_writes(): void
    {
        config(['parking.thingspeak_write_key' => 'fake-test-key']);
        Http::preventStrayRequests();
        Http::fake(['https://api.thingspeak.com/update.json' => Http::response(['entry_id' => 1])]);

        $this->artisan('parking:thingspeak')->assertSuccessful();
        $this->artisan('parking:thingspeak')->assertFailed();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->hasHeader('THINGSPEAKAPIKEY', 'fake-test-key')
            && $request->data() === ['field1' => 0, 'field2' => 500, 'field3' => 0, 'field4' => 0]);
    }

    public function test_sync_rejects_an_unsuccessful_write_even_with_http_200(): void
    {
        config(['parking.thingspeak_write_key' => 'fake-test-key']);
        Http::preventStrayRequests();
        Http::fake(['https://api.thingspeak.com/update.json' => Http::response('0')]);

        $this->artisan('parking:thingspeak')->assertFailed();
    }
}
