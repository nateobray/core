<?php
declare(strict_types=1);

namespace {
    define('OBRAY_FORCE_HTTP_REQUEST', true);
    require __DIR__ . '/bootstrap.php';
}

namespace controllers {

use obray\core\http\requests\GETRequest;

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

class Index
{
    public $html = '<html>shell</html>';

    public function get(GETRequest $request): void
    {
        // no-op; html shell is already set
    }
}

}

namespace {

use obray\core\Factory;
use obray\core\Invoker;
use obray\core\Router;
use obray\core\encoders\ErrorEncoder;
use obray\core\encoders\HTMLEncoder;
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

$fallbackRouter = new Router($factory, $invoker, null, false, microtime(true));
$fallbackRouter->addEncoder(JSONEncoder::class, 'data', 'application/json');
$fallbackRouter->addEncoder(ErrorEncoder::class, 'error', 'application/json');
$fallbackRouter->addEncoder(HTMLEncoder::class, 'html', 'text/html');
$fallbackRouter->setNotFoundFallbackController(\controllers\Index::class, static function ($request): bool {
    return $request->getMethod() === 'GET'
        && strpos((string)$request->getUri()->getPath(), '/v1/') !== 0;
});
$fallbackRouter->route('/company/manufacturing/uptime/', [], true);
$fallback = $fallbackRouter->getLastResponse();

if (($fallback['code'] ?? null) !== 200) {
    throw new \RuntimeException('Fallback route should return 200.');
}

if (($fallback['contentType'] ?? null) !== 'text/html') {
    throw new \RuntimeException('Fallback route should use the HTML encoder.');
}

$headRouter = new Router($factory, $invoker, null, false, microtime(true));
$headRouter->addEncoder(JSONEncoder::class, 'data', 'application/json');
$headRouter->addEncoder(ErrorEncoder::class, 'error', 'application/json');
$headRouter->addEncoder(HTMLEncoder::class, 'html', 'text/html');
$headRouter->setNotFoundFallbackController(\controllers\Index::class, static function ($request): bool {
    return in_array($request->getMethod(), ['GET', 'HEAD'], true)
        && strpos((string)$request->getUri()->getPath(), '/v1/') !== 0;
});
$_SERVER['REQUEST_METHOD'] = 'HEAD';
$headRouter->route('/company/settings/', [], true);
$head = $headRouter->getLastResponse();
$_SERVER['REQUEST_METHOD'] = 'GET';

if (($head['code'] ?? null) !== 200) {
    throw new \RuntimeException('HEAD fallback route should return 200.');
}

if (($head['contentType'] ?? null) !== 'text/html') {
    throw new \RuntimeException('HEAD fallback route should use the HTML encoder.');
}

if (($head['body'] ?? null) !== '') {
    throw new \RuntimeException('HEAD fallback route should not include a response body.');
}

echo "Router JSON smoke test passed\n";

}
