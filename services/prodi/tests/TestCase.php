<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // This check runs BEFORE RefreshDatabase can migrate or clear any tables.
        if (config('database.default') !== 'mysql' ||
            config('database.connections.mysql.database') !== 'crud_mahasiswa_prodi_testing') {
            throw new \RuntimeException('Pengujian hanya boleh memakai database khusus crud_mahasiswa_prodi_testing.');
        }
        Http::preventStrayRequests();

        return $app;
    }
}
