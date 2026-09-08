<?php

return [
    'enabled' => env('DEPLOYMENT_ENABLED', false),
    'allow_testing' => false,
    'allowed_email' => env('DEPLOYMENT_ALLOWED_EMAIL', 'cristian.ospinagarcia@gmail.com'),
    'allowed_store_slug' => env('DEPLOYMENT_ALLOWED_STORE', 'la-aguacatera'),
    'branch' => 'main',
    'repository_https' => env('DEPLOYMENT_REPOSITORY_HTTPS', 'https://github.com/kriptian/landing-commerce.git'),
    'repository_ssh' => env('DEPLOYMENT_REPOSITORY_SSH', 'git@github.com:kriptian/landing-commerce.git'),
    'git_identity_file' => env('DEPLOYMENT_GIT_IDENTITY_FILE', '/home/sail/.ssh/github'),
    'ssh_identity_file' => env('DEPLOYMENT_SSH_IDENTITY_FILE', '/home/sail/.ssh/hostinger'),
    'known_hosts_file' => env('DEPLOYMENT_KNOWN_HOSTS_FILE', '/home/sail/.ssh/known_hosts'),
    'remote_host' => env('DEPLOYMENT_REMOTE_HOST', '212.1.209.174'),
    'remote_port' => (int) env('DEPLOYMENT_REMOTE_PORT', 65002),
    'remote_user' => env('DEPLOYMENT_REMOTE_USER', 'u747542941'),
    'remote_script' => env('DEPLOYMENT_REMOTE_SCRIPT', '/home/u747542941/deploy/landing-commerce.sh'),
    'timeout' => (int) env('DEPLOYMENT_TIMEOUT', 4800),
    'output_limit' => (int) env('DEPLOYMENT_OUTPUT_LIMIT', 100000),
];
