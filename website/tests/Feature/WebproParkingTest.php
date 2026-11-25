<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebproParkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_compare_areas_and_only_admin_can_manage_slots(): void
    {
        $this->get('/area-parkir')->assertOk()->assertSee('TULT');
        $this->get('/kelola-area')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/kelola-area')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/kelola-area', ['name' => 'Area Demo', 'location' => 'Gedung A', 'capacity' => 1])->assertRedirect();
        $area = DB::table('parking_areas')->value('id');
        $this->get("/kelola-area?area=$area")->assertOk()->assertSee('Tambah slot');
        $this->from("/kelola-area?area=$area")->post("/kelola-area/$area/slot", ['label' => 'A-01', 'occupied' => '0'])->assertSessionHasNoErrors();
        $slot = DB::table('parking_slots')->value('id');
        $this->post("/kelola-area/$area/slot", ['label' => 'A-02', 'occupied' => '0'])->assertSessionHasErrors('label');
        $this->post("/kelola-area/$area/slot/$slot", ['label' => 'A-01', 'occupied' => '1'])->assertSessionHasNoErrors();
        $this->get('/area-parkir')->assertOk()->assertSee('Area Demo')->assertSee('A-01');
        $this->assertDatabaseHas('parking_slots', ['id' => $slot, 'occupied' => 1]);
        $this->delete("/kelola-area/$area")->assertRedirect('/kelola-area');
        $this->assertDatabaseCount('parking_slots', 0);
    }

    public function test_fines_are_private_and_only_admin_can_change_them(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $fine = ['user_id' => $student->id, 'reason' => 'Pelanggaran uji', 'amount' => 10000, 'issued_on' => '2026-11-08'];
        $this->actingAs($student)->post('/denda', $fine)->assertForbidden();
        $this->actingAs($admin)->post('/denda', $fine)->assertRedirect('/denda');
        $id = DB::table('parking_fines')->value('id');
        $this->get("/denda?edit=$id")->assertOk()->assertSee('Edit denda');
        $this->post("/denda/$id", array_replace($fine, ['amount' => 15000]))->assertRedirect('/denda');
        $this->actingAs($student)->get('/denda')->assertOk()->assertSee('Rp15.000');
        $this->actingAs($other)->get("/denda?edit=$id")->assertOk()->assertDontSee('Pelanggaran uji');
        $this->delete("/denda/$id")->assertForbidden();
        $this->actingAs($admin)->delete("/denda/$id")->assertRedirect('/denda');
        $this->assertDatabaseCount('parking_fines', 0);
    }
}
