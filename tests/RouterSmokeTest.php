<?php
declare(strict_types=1);

namespace {
    define('OBRAY_FORCE_HTTP_REQUEST', true);
    require __DIR__ . '/bootstrap.php';
}

namespace controllers {

class Dummy
{
    public $data;

    public function __construct()
    {
        $this->data = ['message' => 'ok'];
    }

    public function get(): void
    {
        // no-op; data is already set on construction
    }
}

}

namespace {

use obray\core\Factory;
use obray\core\Invoker;
use obray\core\Router;
use obray\core\encoders\ErrorEncoder;
use obray\core\encoders\JSONEncoder;

$_SERVER = array_replace([
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => '/dummy',
], $_SERVER ?? []);
$_SERVER['argv'] = [];
$_GET = [];
$_POST = [];
$_COOKIE = [];
$_REQUEST = [];

$factory = new Factory(null);
$invoker = new Invoker();
$router = new Router($factory, $invoker, null, false, microtime(true));
$router->addEncoder(JSONEncoder::class, 'data', 'application/json');
$router->addEncoder(ErrorEncoder::class, 'error', 'application/json');

$response = $router->route('/dummy', [], true);
$last = $router->getLastResponse();

if (empty($last) || !array_key_exists('body', $last)) {
    throw new \RuntimeException('Router did not record a response payload.');
}

$payload = json_decode($last['body'], true);
if (!is_array($payload)) {
    throw new \RuntimeException('Router response was not valid JSON.');
}

if (($payload['data']['message'] ?? null) !== 'ok') {
    throw new \RuntimeException('Router response missing expected data payload.');
}

if (($last['code'] ?? null) !== 200) {
    throw new \RuntimeException('Unexpected status code: ' . var_export($last['code'] ?? null, true));
}

$missingRouter = new Router($factory, $invoker, null, false, microtime(true));
$missingRouter->addEncoder(JSONEncoder::class, 'data', 'application/json');
$missingRouter->addEncoder(ErrorEncoder::class, 'error', 'application/json');
$missingRouter->route('/missing', [], true);
$missing = $missingRouter->getLastResponse();

if (($missing['code'] ?? null) !== 404) {
    throw new \RuntimeException('Missing route should return 404.');
}

echo "Router JSON smoke test passed\n";

}
