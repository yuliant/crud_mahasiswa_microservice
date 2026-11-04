<?php

namespace Tests\Feature;

use App\Models\Mahasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MahasiswaApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace(['id_prodi' => 7, 'nama' => 'Andi Saputra', 'nim' => '0012026001'], $overrides);
    }

    private function availableProdi(): void
    {
        Http::fake(['*/api/prodi/7' => Http::response(['data' => ['id' => 7, 'nama_prodi' => 'Sistem Informasi']])]);
    }

    public function test_crud_preserves_nim_and_uses_prodi_api(): void
    {
        $this->availableProdi();
        $id = $this->postJson('/api/mahasiswa', $this->payload())->assertCreated()
            ->assertJsonPath('data.nim', '0012026001')->assertJsonPath('data.id_prodi', 7)->json('data.id');
        $this->getJson('/api/mahasiswa/'.$id)->assertOk();
        $this->putJson('/api/mahasiswa/'.$id, $this->payload(['nama' => 'Andi Baru']))->assertOk()->assertJsonPath('data.nama', 'Andi Baru');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === config('services.prodi.url').'/api/prodi/7');
        $this->deleteJson('/api/mahasiswa/'.$id)->assertOk();
        $this->assertDatabaseMissing('mahasiswa', ['id' => $id]);
        $this->assertFalse(Schema::hasTable('prodi'));
    }

    public function test_local_validation_runs_before_network_calls(): void
    {
        $this->postJson('/api/mahasiswa', [])->assertUnprocessable()->assertJsonValidationErrors(['id_prodi', 'nama', 'nim']);
        $this->postJson('/api/mahasiswa', $this->payload(['nim' => 123]))->assertUnprocessable()->assertJsonValidationErrors('nim');
        $this->postJson('/api/mahasiswa', $this->payload(['id_prodi' => 0, 'nama' => str_repeat('a', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['id_prodi', 'nama']);
        Http::assertNothingSent();
    }

    public function test_duplicate_nim_is_rejected_but_own_nim_can_be_kept(): void
    {
        $record = Mahasiswa::create($this->payload());
        $other = Mahasiswa::create($this->payload(['nim' => '002']));
        $this->availableProdi();
        $this->postJson('/api/mahasiswa', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('nim');
        $this->putJson('/api/mahasiswa/'.$other->id, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('nim');
        $this->putJson('/api/mahasiswa/'.$record->id, $this->payload())->assertOk();
    }

    public function test_unknown_prodi_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Not found'], 404)]);
        $this->postJson('/api/mahasiswa', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('id_prodi');
        $this->assertDatabaseCount('mahasiswa', 0);
    }

    public function test_service_outage_prevents_create_and_update_without_data_loss(): void
    {
        $record = Mahasiswa::create($this->payload());
        Http::fake(['*' => Http::failedConnection()]);
        $this->postJson('/api/mahasiswa', $this->payload(['nim' => '003']))->assertStatus(503);
        $this->putJson('/api/mahasiswa/'.$record->id, $this->payload(['nama' => 'Tidak tersimpan']))->assertStatus(503);
        $this->assertDatabaseCount('mahasiswa', 1);
        $this->assertDatabaseHas('mahasiswa', ['id' => $record->id, 'nama' => 'Andi Saputra']);
    }

    public function test_upstream_error_or_malformed_response_is_not_an_unknown_prodi(): void
    {
        foreach ([Http::response([], 500), Http::response([]), Http::response(['data' => ['id' => 999]])] as $response) {
            Http::fake(['*' => $response]);
            $this->postJson('/api/mahasiswa', $this->payload())->assertStatus(503);
        }
        $this->assertDatabaseCount('mahasiswa', 0);
    }

    public function test_read_usage_and_delete_work_without_prodi_service(): void
    {
        $record = Mahasiswa::create($this->payload());
        $this->getJson('/api/mahasiswa')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/prodi/7/usage')->assertOk()->assertJsonPath('data.has_mahasiswa', true)->assertJsonPath('data.id_prodi', 7);
        $this->getJson('/api/prodi/8/usage')->assertOk()->assertJsonPath('data.has_mahasiswa', false);
        $this->deleteJson('/api/mahasiswa/'.$record->id)->assertOk();
        Http::assertNothingSent();
    }

    public function test_pagination_and_missing_record_json(): void
    {
        foreach (range(1, 12) as $number) Mahasiswa::create($this->payload(['nim' => 'NIM'.$number]));
        $this->getJson('/api/mahasiswa')->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 12);
        $this->getJson('/api/mahasiswa?page=2')->assertJsonCount(2, 'data');
        $this->get('/api/mahasiswa/99999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_cors_preflight_supports_updates(): void
    {
        $this->options('/api/mahasiswa/1', [], [
            'Origin' => 'http://localhost:8080',
            'Access-Control-Request-Method' => 'PUT',
            'Access-Control-Request-Headers' => 'content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8080');
    }

    public function test_scramble_generates_documentation_and_request_schema(): void
    {
        $this->app['env'] = 'local';
        $this->get('/docs/api')->assertOk();
        $spec = $this->getJson('/docs/api.json')->assertOk();
        $spec->assertJsonStructure(['openapi', 'paths' => ['/mahasiswa', '/mahasiswa/{mahasiswa}', '/prodi/{id}/usage']]);
        $spec->assertJsonPath('components.schemas.MahasiswaRequest.properties.nim.type', 'string');
        $spec->assertJsonPath('components.schemas.MahasiswaRequest.properties.id_prodi.type', 'integer');
        $this->assertArrayHasKey('201', $spec->json('paths./mahasiswa.post.responses'));
        $this->assertArrayHasKey('503', $spec->json('paths./mahasiswa.post.responses'));
        $this->assertArrayHasKey('503', $spec->json('paths./mahasiswa/{mahasiswa}.put.responses'));
    }
}
