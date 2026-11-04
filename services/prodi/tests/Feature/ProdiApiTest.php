<?php

namespace Tests\Feature;

use App\Models\Prodi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProdiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_and_json_contract(): void
    {
        $id = $this->postJson('/api/prodi', ['nama_prodi' => 'Sistem Informasi'])
            ->assertCreated()->assertJsonPath('data.nama_prodi', 'Sistem Informasi')->json('data.id');
        $this->getJson('/api/prodi/'.$id)->assertOk()->assertJsonPath('data.id', $id);
        $this->putJson('/api/prodi/'.$id, ['nama_prodi' => 'Informatika'])->assertOk()->assertJsonPath('data.nama_prodi', 'Informatika');
        Http::fake(['*/api/prodi/'.$id.'/usage' => Http::response(['data' => ['id_prodi' => $id, 'has_mahasiswa' => false]])]);
        $this->deleteJson('/api/prodi/'.$id)->assertOk();
        $this->assertDatabaseMissing('prodi', ['id' => $id]);
        $this->assertFalse(Schema::hasTable('mahasiswa'));
    }

    public function test_validation_and_missing_records_always_return_json(): void
    {
        $this->postJson('/api/prodi', [])->assertUnprocessable()->assertJsonValidationErrors('nama_prodi');
        $this->postJson('/api/prodi', ['nama_prodi' => str_repeat('a', 101)])->assertUnprocessable();
        $this->get('/api/prodi/99999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
        Http::assertNothingSent();
    }

    public function test_pagination_and_all_options_are_available(): void
    {
        foreach (range(1, 12) as $number) Prodi::create(['nama_prodi' => sprintf('Prodi %02d', $number)]);
        $this->getJson('/api/prodi')->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 12);
        $this->getJson('/api/prodi?page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2);
        $this->getJson('/api/prodi/options')->assertJsonCount(12, 'data')->assertJsonPath('data.0.nama_prodi', 'Prodi 01');
    }

    public function test_used_prodi_cannot_be_deleted(): void
    {
        $prodi = Prodi::create(['nama_prodi' => 'SI']);
        Http::fake(['*' => Http::response(['data' => ['id_prodi' => $prodi->id, 'has_mahasiswa' => true]])]);
        $this->deleteJson('/api/prodi/'.$prodi->id)->assertConflict();
        $this->assertDatabaseHas('prodi', ['id' => $prodi->id]);
        Http::assertSent(fn ($request) => $request->url() === config('services.mahasiswa.url').'/api/prodi/'.$prodi->id.'/usage');
    }

    public function test_unavailable_service_prevents_deletion(): void
    {
        $prodi = Prodi::create(['nama_prodi' => 'SI']);
        Http::fake(['*' => Http::failedConnection()]);
        $this->deleteJson('/api/prodi/'.$prodi->id)->assertStatus(503);
        $this->assertDatabaseHas('prodi', ['id' => $prodi->id]);
    }

    public function test_upstream_error_or_malformed_response_prevents_deletion(): void
    {
        $prodi = Prodi::create(['nama_prodi' => 'SI']);
        foreach ([
            Http::response(['message' => 'error'], 500),
            Http::response(['data' => ['has_mahasiswa' => 'false']]),
            Http::response(['data' => ['id_prodi' => 999, 'has_mahasiswa' => false]]),
        ] as $response) {
            Http::fake(['*' => $response]);
            $this->deleteJson('/api/prodi/'.$prodi->id)->assertStatus(503);
            $this->assertDatabaseHas('prodi', ['id' => $prodi->id]);
        }
    }

    public function test_cors_allows_frontend_preflight_and_rejects_other_origin(): void
    {
        $this->options('/api/prodi', [], [
            'Origin' => 'http://127.0.0.1:8080',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:8080');
        $this->get('/api/prodi', ['Origin' => 'https://example.com'])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_scramble_generates_documentation_and_request_schema(): void
    {
        $this->app['env'] = 'local';
        $this->get('/docs/api')->assertOk();
        $spec = $this->getJson('/docs/api.json')->assertOk();
        $spec->assertJsonStructure(['openapi', 'paths' => ['/prodi', '/prodi/options', '/prodi/{prodi}']]);
        $spec->assertJsonPath('components.schemas.ProdiRequest.properties.nama_prodi.type', 'string');
        $spec->assertJsonPath('components.schemas.ProdiRequest.required.0', 'nama_prodi');
        $this->assertArrayHasKey('201', $spec->json('paths./prodi.post.responses'));
        $this->assertArrayHasKey('503', $spec->json('paths./prodi/{prodi}.delete.responses'));
    }

    public function test_seed_is_repeatable(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('prodi', 2);
    }
}
