#!/usr/bin/env php
<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

$project = '/home/u747542941/domains/ondigitalsolution.com/public_html';
$keep = 7;

require $project.'/vendor/autoload.php';

$app = require $project.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('production')) {
    fwrite(STDERR, "Database backups are only allowed in production.\n");
    exit(1);
}

$connection = config('database.default');
$database = config("database.connections.{$connection}");

if (! is_array($database) || ! in_array($database['driver'] ?? null, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "The production database must use MySQL or MariaDB.\n");
    exit(1);
}

$directory = $project.'/storage/app/deployment-backups';

if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    fwrite(STDERR, "The backup directory could not be created.\n");
    exit(1);
}

chmod($directory, 0700);

$name = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $database['database']);
$sqlPath = $directory.'/'.$name.'_'.date('Ymd_His').'.sql';
$gzipPath = $sqlPath.'.gz';
$host = (string) ($database['host'] ?? '127.0.0.1');

if ($host === 'localhost') {
    $host = '127.0.0.1';
}

$dump = new Process([
    '/usr/bin/mysqldump',
    '--single-transaction',
    '--quick',
    '--skip-lock-tables',
    '--no-tablespaces',
    '--host='.$host,
    '--port='.(string) ($database['port'] ?? 3306),
    '--user='.(string) ($database['username'] ?? ''),
    '--result-file='.$sqlPath,
    (string) $database['database'],
], $project, ['MYSQL_PWD' => (string) ($database['password'] ?? '')]);
$dump->setTimeout(900);
$dump->run();

if (! $dump->isSuccessful() || ! is_file($sqlPath) || filesize($sqlPath) === 0) {
    @unlink($sqlPath);
    fwrite(STDERR, 'Database backup failed: '.trim($dump->getErrorOutput()).PHP_EOL);
    exit(1);
}

$gzip = new Process(['/usr/bin/gzip', '-f', $sqlPath], $project);
$gzip->setTimeout(900);
$gzip->run();

if (! $gzip->isSuccessful() || ! is_file($gzipPath)) {
    @unlink($sqlPath);
    @unlink($gzipPath);
    fwrite(STDERR, 'Database backup compression failed: '.trim($gzip->getErrorOutput()).PHP_EOL);
    exit(1);
}

$backups = glob($directory.'/*.sql.gz') ?: [];
usort($backups, fn (string $left, string $right) => filemtime($right) <=> filemtime($left));

foreach (array_slice($backups, $keep) as $backup) {
    @unlink($backup);
}

echo 'Database backup created: '.basename($gzipPath).PHP_EOL;
