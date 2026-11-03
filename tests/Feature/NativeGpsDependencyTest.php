<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class NativeGpsDependencyTest extends TestCase
{
    public function test_missing_asn1_dependency_fails_closed_with_a_safe_service_error(): void
    {
        // Isolate the autoloader change so other cryptographic tests keep the real dependency.
        $code = <<<'PHP'
$loader = require 'vendor/autoload.php';
$loader->addClassMap(['phpseclib3\File\ASN1' => false]);
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Log::swap(new Psr\Log\NullLogger());
try {
    (new App\Services\Presensi\AndroidGpsAttestation())->verifyChain([], str_repeat('a', 64), str_repeat('b', 64));
    exit(1);
} catch (Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException $error) {
    echo json_encode(['status' => $error->getStatusCode(), 'message' => $error->getMessage()]);
}
PHP;
        $process = new Process([PHP_BINARY, '-r', $code], base_path());
        $process->mustRun();
        $response = json_decode($process->getOutput(), true);

        $this->assertSame(503, $response['status']);
        $this->assertStringContainsString('Hubungi admin', $response['message']);
        $this->assertStringNotContainsString('phpseclib', $response['message']);
        $this->assertStringNotContainsString('ASN1', $response['message']);
    }
}
