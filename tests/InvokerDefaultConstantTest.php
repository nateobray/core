<?php
declare(strict_types=1);

namespace {
    define('OBRAY_FORCE_HTTP_REQUEST', true);
    require __DIR__ . '/bootstrap.php';
}

namespace tests\fixtures {

class InvokerDefaultConstantBase
{
    protected const DEFAULT_LIMIT = 5;
}

class InvokerDefaultConstantController extends InvokerDefaultConstantBase
{
    private const STATUS_KEEP = 'keep';

    public array $captured = [];

    public function get(string $status = self::STATUS_KEEP, int $limit = parent::DEFAULT_LIMIT): void
    {
        $this->captured = [
            'status' => $status,
            'limit' => $limit,
        ];
    }
}

}

namespace {

use obray\core\Invoker;
use obray\core\http\requests\GETRequest;
use tests\fixtures\InvokerDefaultConstantController;

$_SERVER = array_replace([
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => '/dummy',
], $_SERVER ?? []);
$_GET = [];
$_POST = [];
$_COOKIE = [];
$_REQUEST = [];

$invoker = new Invoker();
$controller = new InvokerDefaultConstantController();
$request = new GETRequest('/dummy', []);

$invoker->invoke($request, $controller, 'get', []);

if (($controller->captured['status'] ?? null) !== 'keep') {
    throw new \RuntimeException('Invoker did not resolve self:: default constant values.');
}

if (($controller->captured['limit'] ?? null) !== 5) {
    throw new \RuntimeException('Invoker did not resolve parent:: default constant values.');
}

echo "Invoker default constant test passed\n";

}
