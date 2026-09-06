<?php
declare(strict_types=1);

namespace {
    // Exercise the HTTP branch without requiring a listening socket in the test suite.
    define('obray\\core\\http\\PHP_SAPI', 'fpm-fcgi');
    require __DIR__ . '/bootstrap.php';
}

namespace controllers {
    class GuardedBoundary {
        public static int $calls = 0;
        public $data = ['ok' => true];
        public function get(): void { self::$calls++; }
        public function console(): void { self::$calls++; }
    }
    class BoundaryError {
        public $data;
        public function get(): void { throw new \Error('Controller failed'); }
    }
    class BoundaryTypeError {
        public $data;
        public function get(): void { strlen([]); }
    }
    class BoundaryReturnError {
        public $data;
        public function get(): string { return []; }
    }
    class BoundaryNestedError {
        public $data;
        public function get(): void { $this->helper([]); }
        private function helper(bool $enabled): void {}
    }
    class BoundaryEncodingError {
        public $data;
        public function get(): void { $this->data = "\xB1"; }
    }
    class BoundaryMissingEncoder {
        public function get(): void {}
    }
    class BoundaryRequestObject {
        public $data;
        public function get(?\obray\core\http\requests\GETRequest $request = null): void {
            $this->data = $request->getMethod();
        }
    }
    class BoundaryInput {
        public $data;
        public function get(bool $enabled, ?string $label = 'default'): void {
            $this->data = [$enabled, $label];
        }
    }
}

namespace {
    use obray\core\Factory;
    use obray\core\Invoker;
    use obray\core\Router;
    use obray\core\encoders\ErrorEncoder;
    use obray\core\encoders\JSONEncoder;
    use obray\core\exceptions\PermissionDenied;
    use obray\core\interfaces\PermissionsInterface;

    class DenyBoundary implements PermissionsInterface {
        public int $checks = 0;
        public function checkPermissions(mixed $obj, ?string $fn = null) {
            $this->checks++;
            throw new PermissionDenied('Denied', 403);
        }
        public function hasPermission(string $code): bool { return false; }
    }
    function boundary_assert(bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    }
    function boundary_router(): Router {
        $router = new Router(new Factory(), new Invoker());
        $router->addEncoder(JSONEncoder::class, 'data', 'application/json');
        $router->addEncoder(ErrorEncoder::class, 'error', 'application/json');
        return $router;
    }

    $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/'];
    $_GET = $_POST = $_COOKIE = $_REQUEST = [];
    foreach (['none', 'query', 'body', 'cookie', 'argv'] as $source) {
        $_REQUEST = $source === 'none' ? [] : ['TENANT' => 'test', 'PATH' => '/guardedBoundary/get'];
        $_GET = $source === 'query' ? $_REQUEST : [];
        $_POST = $source === 'body' ? $_REQUEST : [];
        $_COOKIE = $source === 'cookie' ? $_REQUEST : [];
        $_SERVER['argv'] = $source === 'argv' ? ['test.php', '/guardedBoundary/get'] : [];
        $router = boundary_router();
        $deny = new DenyBoundary();
        $router->setCheckPermissionsHandler($deny);
        $router->route('/guardedBoundary/get?TENANT=test', [], true);
        boundary_assert($deny->checks === 1, "$source input must not bypass permissions.");
        boundary_assert($router->getLastResponse()['code'] === 403, 'PermissionDenied must produce HTTP 403.');
        boundary_assert(\controllers\GuardedBoundary::$calls === 0, 'Denied controller must not execute.');
    }

    $_GET = $_POST = $_COOKIE = $_REQUEST = [];
    foreach (['CONSOLE', 'BOGUS', '../CONSOLE'] as $method) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $router = boundary_router();
        $router->route('/guardedBoundary/get', [], true);
        boundary_assert($router->getLastResponse()['code'] === 405, 'Unsupported HTTP methods must produce 405.');
        boundary_assert(\controllers\GuardedBoundary::$calls === 0, 'Unsupported methods must not execute a controller.');
    }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    foreach (['/boundaryError', '/boundaryTypeError', '/boundaryReturnError', '/boundaryNestedError', '/boundaryEncodingError'] as $path) {
        $router = boundary_router();
        $router->route($path, [], true);
        boundary_assert($router->getLastResponse()['code'] === 500, 'Controller bugs must produce HTTP 500.');
    }
    $router = boundary_router();
    foreach ([true, false] as $enabled) {
        $router->route('/boundaryInput', ['enabled' => $enabled, 'label' => null], true);
        $body = json_decode($router->getLastResponse()['body'], true);
        boundary_assert(($body['data'] ?? null) === [$enabled, null], 'Boolean and explicit null inputs must retain their values.');
    }
    $router->route('/boundaryInput', ['enabled' => []], true);
    boundary_assert($router->getLastResponse()['code'] === 406, 'Invalid argument types must retain a client error.');
    $router->route('/boundaryInput', [], true);
    boundary_assert($router->getLastResponse()['code'] === 400, 'Missing required input must be a client error.');
    $router->route('/boundaryRequestObject', ['request' => 'spoofed'], true);
    boundary_assert(json_decode($router->getLastResponse()['body'], true)['data'] === 'GET', 'Request objects must come from the server, including nullable defaulted parameters.');
    $router->route('/boundaryMissingEncoder', [], true);
    boundary_assert($router->getLastResponse()['code'] === 500, 'Router reuse must not inherit the preceding encoder.');
    echo "Request boundary tests passed\n";
}
