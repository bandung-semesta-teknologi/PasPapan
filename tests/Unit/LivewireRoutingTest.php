<?php

use Symfony\Component\Process\Process;

test('livewire endpoints stay consistent when deployment caches routes', function () {
    $cachePath = sys_get_temp_dir().'/paspapan-livewire-routes-'.bin2hex(random_bytes(8)).'.php';
    $environment = ['APP_ROUTES_CACHE' => $cachePath];
    $inspect = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $routes = Illuminate\Support\Facades\Route::getRoutes();
    $updates = [];

    foreach ($routes as $route) {
        if (str_ends_with($route->getName() ?? '', 'livewire.update')) {
            $updates[] = '/'.$route->uri();
        }
    }

    echo json_encode([
        'updateUri' => Livewire\Livewire::getUpdateUri(),
        'updateRoutes' => $updates,
        'scriptUri' => '/'.app(Livewire\Mechanisms\FrontendAssets\FrontendAssets::class)->javaScriptRoute->uri(),
    ], JSON_THROW_ON_ERROR);
    PHP;

    $inspectRoutes = function () use ($inspect, $environment): array {
        $process = new Process([PHP_BINARY, '-r', $inspect], base_path(), $environment);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    };

    try {
        $uncached = $inspectRoutes();

        $cache = new Process([PHP_BINARY, 'artisan', 'route:cache'], base_path(), $environment);
        $cache->mustRun();

        $cached = $inspectRoutes();

        expect($cached)->toBe($uncached)
            ->and($cached['updateRoutes'])->toBe([$cached['updateUri']]);
    } finally {
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }
});
