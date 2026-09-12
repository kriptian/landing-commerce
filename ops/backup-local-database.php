#!/usr/bin/env php
<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

$project = dirname(__DIR__);
$keep = 3;

require $project.'/vendor/autoload.php';

$app = require $project.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = config("database.connections.{$connection}");
$host = (string) ($database['host'] ?? '');
$databaseName = (string) ($database['database'] ?? '');

if (! $app->environment('local')
    || ($database['driver'] ?? null) !== 'mysql'
    || ! in_array($host, ['mysql', '127.0.0.1', 'localhost'], true)
    || $databaseName === '') {
    fwrite(STDERR, "Local backups are only allowed for a configured local MySQL database.\n");
    exit(1);
}

$directory = storage_path('app/local-database-backups');

if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    fwrite(STDERR, "The local backup directory could not be created.\n");
    exit(1);
}

chmod($directory, 0700);
$backupName = preg_replace('/[^A-Za-z0-9_-]/', '_', $databaseName);
$sqlPath = $directory.'/'.$backupName.'_'.date('Ymd_His').'.sql';
$gzipPath = $sqlPath.'.gz';

$dump = new Process([
    '/usr/bin/mysqldump',
    '--single-transaction',
    '--quick',
    '--skip-lock-tables',
    '--no-tablespaces',
    '--host='.$host,
    '--port='.(string) $database['port'],
    '--user='.(string) $database['username'],
    '--result-file='.$sqlPath,
    $databaseName,
], $project, ['MYSQL_PWD' => (string) $database['password']]);
$dump->setTimeout(600);
$dump->run();

if (! $dump->isSuccessful() || ! is_file($sqlPath) || filesize($sqlPath) === 0) {
    @unlink($sqlPath);
    fwrite(STDERR, 'Local database backup failed: '.trim($dump->getErrorOutput()).PHP_EOL);
    exit(1);
}

$gzip = new Process(['/usr/bin/gzip', '-f', $sqlPath], $project);
$gzip->setTimeout(300);
$gzip->run();

if (! $gzip->isSuccessful() || ! is_file($gzipPath)) {
    @unlink($sqlPath);
    @unlink($gzipPath);
    fwrite(STDERR, "Local database backup compression failed.\n");
    exit(1);
}

chmod($gzipPath, 0600);

$backups = glob($directory.'/*.sql.gz') ?: [];
usort($backups, fn (string $left, string $right) => filemtime($right) <=> filemtime($left));

foreach (array_slice($backups, $keep) as $backup) {
    @unlink($backup);
}

echo 'Local database backup created: '.basename($gzipPath).PHP_EOL;
