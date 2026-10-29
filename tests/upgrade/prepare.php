<?php

// Creates only a marked, disposable upgrade fixture. No installed forum is used.
$phase = $argv[1] ?? '';
$root = getenv('PRIVATE_PROFILE_UPGRADE_ROOT');
$extension = $phase === 'legacy' ? getenv('PRIVATE_PROFILE_LEGACY_PATH') : dirname(__DIR__, 2);

if (! in_array($phase, ['legacy', 'current'], true) || ! $root || ! $extension || ! is_file($extension.'/composer.json')) {
    throw new RuntimeException('Provide an upgrade root and a checked-out 1.x extension path.');
}
if ($phase === 'legacy' && is_file($root.'/config.php')) {
    throw new RuntimeException('The legacy fixture needs a fresh installation directory.');
}
if ($phase === 'current' && (! is_file($root.'/.private-profile-upgrade') || ! is_file($root.'/legacy-state.json'))) {
    throw new RuntimeException('The legacy installation must be prepared and verified first.');
}

foreach (['', '/public/assets', '/storage/cache', '/storage/formatter', '/storage/locale', '/storage/sessions', '/storage/views'] as $directory) {
    if (! is_dir($root.$directory)) {
        mkdir($root.$directory, 0777, true);
    }
}

$version = $phase === 'legacy' ? '1.1.0' : '2.x-dev';
$composer = [
    'name' => 'private-profile/upgrade-fixture',
    'require' => ['flarum/core' => $phase === 'legacy' ? '1.8.13' : '2.0.2', 'huseyinfiliz/private-profile' => $version],
    'repositories' => [[
        'type' => 'path', 'url' => realpath($extension),
        'options' => ['symlink' => true, 'versions' => ['huseyinfiliz/private-profile' => $version]],
    ]],
    'minimum-stability' => 'dev',
    'prefer-stable' => true,
    // Historical dependencies are required only to exercise the 1.x upgrade path.
    'config' => ['policy' => ['advisories' => ['block' => false]]],
];
file_put_contents($root.'/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

if ($phase === 'legacy') {
    file_put_contents($root.'/.private-profile-upgrade', 'disposable fixture');
    file_put_contents(
        $root.'/site.php',
        <<<'PHP'
<?php

require __DIR__.'/vendor/autoload.php';

return Flarum\Foundation\Site::fromPaths([
    'base' => __DIR__,
    'public' => __DIR__.'/public',
    'storage' => __DIR__.'/storage',
]);
PHP
    );
    file_put_contents(
        $root.'/flarum',
        <<<'PHP'
<?php

$site = require __DIR__.'/site.php';
(new Flarum\Console\Server($site))->listen();
PHP
    );
    file_put_contents($root.'/install.json', json_encode([
        'debug' => true,
        'baseUrl' => 'http://localhost/private-profile-upgrade',
        'databaseConfiguration' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'private_profile_upgrade',
            'username' => getenv('DB_USERNAME') ?: 'flarum',
            'password' => getenv('DB_PASSWORD') ?: '',
            'prefix' => getenv('DB_PREFIX') ?: '',
        ],
        'adminUser' => ['username' => 'admin', 'password' => 'upgrade-test-password', 'email' => 'admin@example.invalid'],
        'settings' => ['forum_title' => 'Private Profile upgrade fixture', 'mail_driver' => 'log'],
        'extensions' => 'huseyinfiliz-private-profile',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($root.'/install.json', 0600);
}

echo "Prepared $phase Composer fixture.\n";
