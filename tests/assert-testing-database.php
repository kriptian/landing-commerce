<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();

if (! $app->environment('testing') || $database !== 'testing') {
    fwrite(STDERR, "Refusing to run tests outside the testing database. Environment: {$app->environment()}, database: {$database}.\n");
    exit(1);
}

echo "Test database guard passed.\n";
