<?php
declare(strict_types=1);

namespace {
    define('OBRAY_FORCE_HTTP_REQUEST', true);
    require __DIR__ . '/bootstrap.php';

    function assert_true($condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

namespace controllers {

use obray\core\exceptions\HTTPException;

class Error
{
    public function get(): void
    {
        $ex = new HTTPException('Bad Request', 400);
        $ex->add('Missing data', 'detail');
        throw $ex;
    }
}

}

namespace {

use obray\core\Factory;
use obray\core\Invoker;
use obray\core\Router;
use obray\core\encoders\JSONEncoder;

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => '/error'
];
$_SERVER['argv'] = [];
$_GET = [];
$_POST = [];
$_COOKIE = [];

$factory = new Factory(null);
$invoker = new Invoker();
$router = new Router($factory, $invoker, null, false, microtime(true));
$router->addEncoder(JSONEncoder::class, 'data', 'application/json');

$response = $router->route('/error', [], true);
$last = $router->getLastResponse();

assert_true(isset($last['code']), 'Router did not record status code.');
assert_true((int)$last['code'] === 400, 'Router should preserve exception status code.');
assert_true(isset($last['body']), 'Router did not encode error body.');

$decoded = json_decode($last['body'], true);
assert_true(is_array($decoded), 'Error payload not JSON.');
assert_true(($decoded['errors']['detail'] ?? null) === 'Missing data', 'HTTPException::add data missing.');

echo "Error handling test passed\n";

}
