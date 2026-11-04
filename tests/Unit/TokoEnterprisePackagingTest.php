<?php

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

function readEnterprisePackagingSource(TestCase $testCase, string $relativePath): string
{
    $path = base_path($relativePath);

    if (! is_file($path)) {
        $testCase->markTestSkipped('Enterprise packaging source is private and is not present in this checkout.');
    }

    $source = file_get_contents($path);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read enterprise packaging source: {$relativePath}");
    }

    return $source;
}

test('toko pos addon is included in enterprise obfuscator build list', function (): void {
    $buildScript = readEnterprisePackagingSource($this, 'secure_tools/build_enterprise.php');

    expect($buildScript)
        ->toContain('../app/Livewire/Admin/TokoPosAddon')
        ->toContain('../app/Support/TokoLegacyImportPreviewService')
        ->toContain('../app/Support/TokoPosCutoverArchiveService')
        ->toContain('../app/Support/TokoPosCutoverReadinessService')
        ->toContain('../app/Support/TokoPosDeliveryLetterService')
        ->toContain('../app/Support/TokoPosInventoryAdjustmentService')
        ->toContain('../app/Support/TokoPosPurchaseService')
        ->toContain('../app/Support/TokoPosQuotationService')
        ->toContain('../app/Support/TokoPosReportService')
        ->toContain('../app/Support/TokoPosSalesService')
        ->toContain('../app/Http/Controllers/Admin/Toko/DownloadTokoDeliveryLetterPdfController')
        ->toContain('../app/Http/Controllers/Admin/Toko/DownloadTokoInvoicePdfController')
        ->toContain('../app/Http/Controllers/Admin/Toko/DownloadTokoQuotationPdfController')
        ->toContain('../app/Http/Controllers/Admin/Toko/PrintTokoStockAdjustmentReportController');
});

test('enterprise obfuscator does not promote secured artifacts into source files', function (): void {
    $buildScript = readEnterprisePackagingSource($this, 'secure_tools/build_enterprise.php');

    expect($buildScript)
        ->toContain('promoteEnterpriseTargetToSource')
        ->toContain('enterpriseSourceLooksSecured($contents)')
        ->toContain('*.Source.php harus berisi plaintext source');
});

test('enterprise runtime can recover when process obfuscator key is stale but env file is current', function (): void {
    $artifact = base_path('app/Http/Controllers/Admin/OperationalHealthController.php');
    $artifactContents = file_get_contents($artifact);

    if (! is_string($artifactContents) || ! str_contains($artifactContents, 'eval(gzinflate(base64_decode(')) {
        $this->markTestSkipped('Enterprise controller artifact is not currently secured.');
    }

    $envContents = is_file(base_path('.env')) ? file_get_contents(base_path('.env')) : false;
    if (! is_string($envContents) || ! preg_match('/^ENTERPRISE_OBFUSCATOR_KEY=(.+)$/m', $envContents, $matches) || trim($matches[1], " \t\n\r\0\x0B\"'") === '') {
        $this->markTestSkipped('ENTERPRISE_OBFUSCATOR_KEY is not available in .env.');
    }

    $php = (new PhpExecutableFinder)->find(false) ?: PHP_BINARY;
    $process = new Process([
        $php,
        '-r',
        <<<'PHP'
require 'vendor/autoload.php';
require 'app/Http/Controllers/Admin/OperationalHealthController.php';
echo class_exists('App\\Http\\Controllers\\Admin\\OperationalHealthController') ? 'ok' : 'missing';
PHP,
    ], base_path(), [
        'ENTERPRISE_OBFUSCATOR_KEY' => str_repeat('x', 32),
    ]);

    $process->setTimeout(30);
    $process->run();

    expect($process->isSuccessful())
        ->toBeTrue($process->getErrorOutput().$process->getOutput())
        ->and(trim($process->getOutput()))->toBe('ok');
});

test('toko pos addon is included in license generator feature catalog', function (): void {
    $licenseGenerator = readEnterprisePackagingSource($this, 'secure_tools/license_generator.php');

    expect($licenseGenerator)
        ->toContain("'toko_pos'")
        ->toContain('Toko / POS Add-on')
        ->toContain("'category' => 'addon'")
        ->toContain('--addons=a,b')
        ->toContain('--features=none')
        ->toContain("'addons' => \$addons");
});

test('license generator treats db persistence and email delivery as optional integrations', function (): void {
    $licenseGenerator = readEnterprisePackagingSource($this, 'secure_tools/license_generator.php');

    expect($licenseGenerator)
        ->toContain('class_exists(LicensePurchase::class)')
        ->toContain('class_exists(LicenseKeyMail::class)')
        ->toContain('DB save: dilewati karena App\\\\Models\\\\LicensePurchase tidak tersedia.')
        ->toContain('Email: dilewati karena App\\\\Mail\\\\LicenseKeyMail tidak tersedia.');
});

test('toko pos addon is flagged as an enterprise addon module', function (): void {
    $module = config('rbac.modules.toko_pos_addon');

    expect($module)
        ->not->toBeNull()
        ->and($module['enterprise'] ?? false)->toBeTrue()
        ->and($module['addon'] ?? false)->toBeTrue()
        ->and($module['license_feature'] ?? null)->toBe('toko_pos')
        ->and($module['module_type'] ?? null)->toBe('addon');
});
