<?php

namespace Tests\Feature;

use App\Filament\Clusters\Settings\Pages\MufiseSettings as SettingsPage;
use App\Models\Cart;
use App\Models\MufiseAccount;
use App\Models\MufiseOperation;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mufise\MufiseConnectionService;
use App\Services\Mufise\MufiseException;
use App\Services\Mufise\MufiseSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MufiseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $projectId = '11111111-1111-4111-8111-111111111111';

    private string $dayId = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        foreach (['super_admin', 'admin', 'staff'] as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'customer']);
        }
        Setting::set('mufise_enabled', '1');
        Setting::set('mufise_url', 'https://mufise.test');
        Setting::set('mufise_connection_id', 'connection-1');
        Setting::set('mufise_api_key_encrypted', Crypt::encryptString('server-secret'));
    }

    private function account(User $user): MufiseAccount
    {
        $account = app(MufiseConnectionService::class)->account($user->id);
        $account->update(['access_token' => 'borrower-secret', 'expires_at' => now()->addDays(90), 'mufise_user_id' => 'mufise-user']);

        return $account;
    }

    private function bundle(bool $collect = true): array
    {
        return [
            'project' => ['id' => $this->projectId, 'title' => 'Film Senja', 'status' => 'active'],
            'crew' => [['id' => (string) Str::uuid(), 'name' => 'Ayu', 'role' => 'Director', 'version' => str_repeat('a', 64), 'phone' => '0812345', 'email' => '']],
            'locations' => [['id' => (string) Str::uuid(), 'name' => 'Studio', 'address' => 'Jl. Kampus', 'version' => str_repeat('b', 64)]],
            'permissions' => ['can_edit' => true, 'can_view_call_sheet' => true],
            'call_sheets' => [['day_id' => $this->dayId, 'day_number' => 1, 'exists' => true, 'locked' => $collect, 'date' => '2026-11-01']],
            'schedule_revision' => 7, 'checked_at' => now()->toIso8601String(),
            'readiness' => ['can_submit' => true, 'can_collect' => $collect, 'missing' => $collect ? [] : ['day_1_locked_call_sheet_required']],
        ];
    }

    private function rental(User $user, bool $required = true): Rental
    {
        $account = $this->account($user);

        return Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'MUFISE-'.Str::uuid(), 'user_id' => $user->id,
            'usage_type' => $required ? 'outside_class' : 'in_class', 'status' => Rental::STATUS_CONFIRMED,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
            'mufise_required' => $required, 'mufise_account_id' => $required ? $account->id : null,
            'mufise_project_id' => $required ? $this->projectId : null, 'mufise_project_title' => 'Film Senja',
        ]));
    }

    private function cart(User $user): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'camera']);
        $brand = DB::table('brands')->insertGetId(['name' => 'Test', 'slug' => 'test']);
        $product = DB::table('products')->insertGetId(['category_id' => $category, 'brand_id' => $brand, 'name' => 'Camera', 'slug' => 'camera', 'daily_rate' => 100000]);
        $unit = DB::table('product_units')->insertGetId(['product_id' => $product, 'serial_number' => 'CAM-001', 'status' => 'available']);
        Cart::create(['user_id' => $user->id, 'product_unit_id' => $unit, 'start_date' => now()->addWeek()->setTime(9, 0), 'end_date' => now()->addWeek()->addDay()->setTime(9, 0), 'daily_rate' => 100000, 'days' => 1, 'subtotal' => 100000]);
    }

    public function test_pairing_uses_session_identity_and_never_returns_secrets(): void
    {
        $user = User::factory()->create();
        Http::fake(['*/link-requests' => Http::response(['request_id' => 'request-1', 'request_secret' => 'pair-secret', 'verification_path' => '/connect/warehouse?request=request-1', 'expires_in' => 600])]);
        $this->actingAs($user, 'customer')->postJson(route('customer.mufise.connect'), ['external_user_id' => 'another-user'])
            ->assertOk()->assertJsonPath('verification_url', 'https://mufise.test/connect/warehouse?request=request-1')->assertDontSee('pair-secret');
        Http::assertSent(fn ($request) => $request['external_user_id'] === (string) $user->id && $request->hasHeader('X-API-Key', 'server-secret') && ! $request->hasHeader('Origin'));
        $account = MufiseAccount::first();
        $this->assertStringNotContainsString('pair-secret', $account->getRawOriginal('pairing'));
        $this->assertArrayNotHasKey('pairing', $account->toArray());
    }

    public function test_polling_waits_five_seconds_and_exchange_is_only_once(): void
    {
        $user = User::factory()->create();
        $account = app(MufiseConnectionService::class)->account($user->id);
        $account->update(['pairing' => ['request_id' => 'r', 'request_secret' => 'secret', 'expires_at' => now()->addMinutes(10)->toIso8601String()], 'next_poll_at' => now()->addSeconds(5)]);
        Http::fake(['*/token' => Http::response(['access_token' => 'new-token', 'user_id' => 'mufise-user', 'external_user_id' => (string) $user->id, 'expires_in' => 777])]);
        $this->actingAs($user, 'customer')->postJson(route('customer.mufise.poll'))->assertOk()->assertJsonPath('pending', true);
        Http::assertNothingSent();
        $this->travel(5)->seconds();
        $this->postJson(route('customer.mufise.poll'))->assertOk()->assertExactJson(['connected' => true])->assertDontSee('new-token');
        $this->postJson(route('customer.mufise.poll'))->assertOk();
        Http::assertSentCount(1);
        $this->assertNull($account->fresh()->pairing);
        $this->assertStringNotContainsString('new-token', $account->fresh()->getRawOriginal('access_token'));
    }

    public function test_pending_and_expired_pairing_do_not_destroy_previous_token(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $account->update(['pairing' => ['request_id' => 'r', 'request_secret' => 'secret', 'expires_at' => now()->addMinute()->toIso8601String()]]);
        Http::fake(['*/token' => Http::response(['error' => ['code' => 'authorization_pending']], 428)]);
        $this->actingAs($user, 'customer')->postJson(route('customer.mufise.poll'))->assertOk()->assertJsonPath('pending', true);
        $this->travel(61)->seconds();
        $this->postJson(route('customer.mufise.poll'))->assertStatus(410);
        $this->assertSame('borrower-secret', $account->fresh()->access_token);
        $this->assertNull($account->fresh()->pairing);
    }

    public function test_user_cannot_poll_or_open_another_borrowers_transaction(): void
    {
        $owner = User::factory()->create();
        $rental = $this->rental($owner);
        $this->actingAs(User::factory()->create(), 'customer')->get(route('customer.mufise.rental', $rental->id))->assertForbidden();
        $this->get(route('customer.mufise.rental.sheet', ['rental' => $rental->id, 'day' => $this->dayId]))->assertForbidden();
        $this->postJson(route('customer.mufise.poll'))->assertOk()->assertExactJson(['connected' => false]);
        Http::assertNothingSent();
    }

    public function test_admin_transaction_access_requires_policy_permission(): void
    {
        $rental = $this->rental(User::factory()->create());
        $this->actingAs(User::factory()->create())->get(route('admin.mufise', $rental->id))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_authorized_admin_can_view_borrowers_project_with_borrowers_token(): void
    {
        $borrower = User::factory()->create();
        $rental = $this->rental($borrower);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        Http::fake(['*/projects/'.$this->projectId => Http::response($this->bundle()), '*/crew-positions' => Http::response(['positions' => []])]);
        $this->actingAs($admin)->get(route('admin.mufise', $rental->id))->assertOk()->assertSee('Film Senja');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer borrower-secret'));
    }

    public function test_pending_pairing_resumes_after_page_reload_without_exposing_secret(): void
    {
        $user = User::factory()->create();
        $account = app(MufiseConnectionService::class)->account($user->id);
        $account->update(['pairing' => ['request_id' => 'pending-request', 'request_secret' => 'hidden-secret', 'expires_at' => now()->addMinutes(9)->toIso8601String()]]);
        $this->actingAs($user, 'customer')->get(route('customer.mufise.index'))
            ->assertOk()->assertSee('pending-request')->assertDontSee('hidden-secret');
        Http::assertNothingSent();
    }

    public function test_connection_page_explains_organization_next_action(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        Http::fake(['*/me' => Http::response(['organization' => ['name' => 'FTV', 'join_code' => 'CODE'], 'next_action' => 'join_organization'])]);
        $this->actingAs($user, 'customer')->get(route('customer.mufise.index'))->assertOk()->assertSee('Bergabung atau terima undangan')->assertSee('Gunakan proyek lain')->assertDontSee('borrower-secret')->assertDontSee('server-secret');
        Http::assertSentCount(1);
    }

    public function test_project_forms_and_document_proxy_render_without_credentials(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        $rental = $this->rental($user);
        Http::fake(['*/projects/'.$this->projectId => Http::response($this->bundle()), '*/crew-positions' => Http::response(['positions' => [['id' => 'director', 'name' => 'Director']]]), '*/call-sheets/*' => Http::response('<h1>Day 1</h1>')]);
        $this->actingAs($user, 'customer')->get(route('customer.mufise.rental', $rental->id))->assertOk()->assertSee('Film Senja')->assertSee('Tambah kru')->assertDontSee('borrower-secret');
        $this->get(route('customer.mufise.rental.sheet', ['rental' => $rental->id, 'day' => $this->dayId]))->assertOk()->assertSee('Day 1')->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox");
    }

    public function test_day_two_lock_does_not_satisfy_pickup_even_if_readiness_is_inconsistent(): void
    {
        $rental = $this->rental(User::factory()->create());
        $bundle = $this->bundle();
        $bundle['call_sheets'][0]['locked'] = false;
        $bundle['call_sheets'][] = ['day_number' => 2, 'exists' => true, 'locked' => true];
        Http::fake(['*/projects/*' => Http::response($bundle)]);
        try {
            $rental->validatePickup();
            $this->fail('Pickup should be blocked');
        } catch (MufiseException) {
            $this->assertSame('confirmed', $rental->fresh()->status);
        }
    }

    public function test_pickup_stores_revision_day_and_encrypted_document_snapshot(): void
    {
        $rental = $this->rental(User::factory()->create());
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'call-sheets')) {
                return isset($request['format']) ? Http::response('<h1>Pickup evidence</h1>') : Http::response(['locked' => true, 'day_number' => 1, 'day_id' => $this->dayId, 'schedule_revision' => 7, 'data' => ['title' => 'Senja']]);
            }

            return Http::response($this->bundle());
        });
        Rental::withoutEvents(fn () => $rental->validatePickup());
        $rental->refresh();
        $this->assertSame('active', $rental->status);
        $this->assertSame(7, $rental->mufise_pickup_snapshot['schedule_revision']);
        $this->assertSame($this->dayId, $rental->mufise_pickup_snapshot['day_id']);
        $this->assertSame('<h1>Pickup evidence</h1>', $rental->mufise_call_sheet_html);
        $this->assertStringNotContainsString('Pickup evidence', $rental->getRawOriginal('mufise_call_sheet_html'));
        $this->assertArrayNotHasKey('mufise_pickup_snapshot', $rental->toArray());
    }

    public function test_outage_revocation_and_disabled_connection_fail_closed_for_linked_pickup(): void
    {
        $rental = $this->rental(User::factory()->create());
        foreach ([401, 403, 404, 503] as $status) {
            Http::fake(['*' => Http::response(['error' => ['code' => 'unavailable']], $status)]);
            try {
                $rental->validatePickup();
                $this->fail('Pickup should fail closed');
            } catch (MufiseException) {
                $this->assertSame('confirmed', $rental->fresh()->status);
                $this->assertNull($rental->fresh()->mufise_pickup_snapshot);
            }
        }
        Setting::set('mufise_enabled', '0');
        $this->expectException(MufiseException::class);
        $rental->validatePickup();
    }

    public function test_in_class_and_historical_pickup_do_not_call_mufise(): void
    {
        $rental = $this->rental(User::factory()->create(), false);
        Rental::withoutEvents(fn () => $rental->validatePickup());
        $this->assertSame('active', $rental->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_mutation_reuses_persisted_operation_and_rejects_changed_body(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        Http::fake(['*' => Http::response(['location' => ['id' => 'location-1']])]);
        $url = route('customer.mufise.project.mutate', $this->projectId);
        $body = ['operation_id' => (string) Str::uuid(), 'kind' => 'locations', 'name' => 'Studio', 'address' => 'Jl. Kampus', 'parking' => '', 'hospital' => ''];
        $this->actingAs($user, 'customer')->from($url)->post($url, $body)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $body)->assertRedirect()->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $body['operation_id']) && $request->hasHeader('Authorization', 'Bearer borrower-secret') && $request['parking'] === '');
        $this->assertNotNull(MufiseOperation::first()->completed_at);
        $body['address'] = 'Another address';
        $this->post($url, $body)->assertStatus(409);
    }

    public function test_existing_person_can_be_assigned_without_exposing_directory(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        $personId = (string) Str::uuid();
        Http::fake(['*' => Http::response(['crew' => ['id' => 'crew-1']])]);
        $this->actingAs($user, 'customer')->post(route('customer.mufise.project.mutate', $this->projectId), [
            'operation_id' => (string) Str::uuid(), 'kind' => 'crew', 'person_id' => $personId,
        ])->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => $request['person_id'] === $personId && count($request->data()) === 1);
    }

    public function test_unknown_mutation_result_retries_with_same_key_and_body(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        Http::fake(['*' => Http::sequence()->pushStatus(503)->push(['crew' => ['id' => 'crew-1']])]);
        $url = route('customer.mufise.project.mutate', $this->projectId);
        $body = ['operation_id' => (string) Str::uuid(), 'kind' => 'crew', 'full_name' => 'Ayu', 'phone' => '0812345', 'role' => 'Director', 'department' => 'Production', 'email' => '', 'confirm_distinct' => '1'];
        $this->actingAs($user, 'customer')->from($url)->post($url, $body)->assertSessionHasErrors('mutation');
        $this->post($url, $body)->assertSessionHasNoErrors();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['confirm_distinct'] === true && $request['email'] === '');
        $this->assertSame(1, MufiseOperation::count());
    }

    public function test_checkout_requires_project_only_for_outside_class_and_creates_snapshot(): void
    {
        $user = User::factory()->create(['is_verified' => true]);
        $this->account($user);
        $this->cart($user);
        Http::fake(['*/projects/'.$this->projectId => Http::response($this->bundle(false)), '*/me' => Http::response(['next_action' => 'ready']), '*/projects' => Http::response(['projects' => [['id' => $this->projectId, 'title' => 'Film Senja']]])]);
        $this->actingAs($user, 'customer')->get(route('checkout.index'))->assertOk()->assertSee('Proyek Mufise')->assertDontSee('name="production_name"', false);
        $body = ['usage_type' => 'outside_class', 'outside_purpose' => 'daily_assignment', 'agree_terms' => '1'];
        $this->post(route('checkout.process'), $body)->assertSessionHasErrors('mufise_project_id');
        $body['mufise_project_id'] = $this->projectId;
        $response = $this->post(route('checkout.process'), $body);
        $this->assertNull(session('error'), (string) session('error'));
        $response->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $this->assertTrue($rental->mufise_required);
        $this->assertSame($this->projectId, $rental->mufise_project_id);
        $this->assertNull($rental->shooting_itinerary_id);
        $this->assertTrue($rental->mufise_submission_snapshot['readiness']['can_submit']);
    }

    public function test_checkout_rejects_incomplete_project_and_allows_in_class_without_connection(): void
    {
        $user = User::factory()->create(['is_verified' => true]);
        $this->account($user);
        $this->cart($user);
        $bundle = $this->bundle();
        $bundle['readiness'] = ['can_submit' => false, 'missing' => ['producer_or_director_required']];
        Http::fake(['*' => Http::response($bundle)]);
        $this->actingAs($user, 'customer')->post(route('checkout.process'), ['usage_type' => 'outside_class', 'outside_purpose' => 'daily_assignment', 'agree_terms' => 1, 'mufise_project_id' => $this->projectId])->assertSessionHasErrors('mufise_project_id');
        $this->assertSame(0, Rental::count());
        Http::fake();
        $this->post(route('checkout.process'), ['usage_type' => 'in_class', 'agree_terms' => 1])->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertFalse(Rental::firstOrFail()->mufise_required);
        Http::assertNothingSent();
    }

    public function test_configuration_changes_cannot_send_old_token_to_new_connection(): void
    {
        $account = $this->account(User::factory()->create());
        Setting::set('mufise_url', 'https://other.test');
        $this->expectException(MufiseException::class);
        app(MufiseConnectionService::class)->token($account);
    }

    public function test_admin_settings_save_encrypted_key_and_never_hydrate_saved_secret(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->assertSet('data.api_key', '')
            ->set('data.api_key', 'rotated-secret')->call('save')->assertHasNoErrors()->assertSet('data.api_key', '');
        $this->assertSame('rotated-secret', MufiseSettings::key());
        $this->assertStringNotContainsString('rotated-secret', Setting::get('mufise_api_key_encrypted'));
        $this->actingAs(User::factory()->create());
        $this->assertFalse(SettingsPage::canAccess());
    }

    public function test_admin_editor_cannot_replace_borrower_or_bypass_pickup(): void
    {
        $rental = $this->rental(User::factory()->create());
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\RentalEditor::class, ['record' => $rental])
            ->set('customer_id', $admin->id)->call('save')->assertHasErrors(['customer_id']);
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\RentalEditor::class, ['record' => $rental])
            ->set('status', Rental::STATUS_ACTIVE)->call('save')->assertHasErrors(['status']);
        $this->assertSame('confirmed', $rental->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_expired_token_requires_reconnection_and_guest_cannot_start_pairing(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $account->update(['expires_at' => now()->subSecond()]);
        $this->post(route('customer.mufise.connect'))->assertRedirect(route('customer.login'));
        try {
            app(MufiseConnectionService::class)->token($account);
            $this->fail('Expired token must not be sent');
        } catch (MufiseException $e) {
            $this->assertSame('reconnect_required', $e->errorCode);
        }
        Http::assertNothingSent();
    }

    public function test_schedule_revision_change_blocks_pickup_without_saving_snapshot(): void
    {
        $rental = $this->rental(User::factory()->create());
        Http::fake(['*/projects/'.$this->projectId => Http::response($this->bundle()), '*/call-sheets/*' => Http::response(['locked' => true, 'day_number' => 1, 'day_id' => $this->dayId, 'schedule_revision' => 8])]);
        try {
            $rental->validatePickup();
            $this->fail('Changed revision must block pickup');
        } catch (MufiseException $e) {
            $this->assertSame('revision_conflict', $e->errorCode);
        }
        $this->assertNull($rental->fresh()->mufise_pickup_snapshot);
        $this->assertSame('confirmed', $rental->fresh()->status);
    }

    public function test_disconnect_rejects_ambiguous_unauthorized_response_and_preserves_token(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        Http::fake(['*/disconnect' => Http::response(['error' => ['code' => 'invalid_credentials']], 401)]);
        $this->actingAs($user, 'customer')->postJson(route('customer.mufise.disconnect'))->assertStatus(401);
        $this->assertSame('borrower-secret', $account->fresh()->access_token);
    }

    public function test_disconnect_clears_token_only_after_remote_revocation(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        Http::fake(['*/disconnect' => Http::sequence()->pushStatus(503)->push(['ok' => true])]);
        $this->actingAs($user, 'customer')->postJson(route('customer.mufise.disconnect'))->assertStatus(503);
        $this->assertNotNull($account->fresh()->access_token);
        $this->postJson(route('customer.mufise.disconnect'))->assertOk();
        $this->assertNull($account->fresh()->access_token);
    }
}
