<?php
declare(strict_types=1);

namespace { require __DIR__ . '/bootstrap.php'; }
namespace controllers {
    class ConsoleBoundary {
        public $data;
        public function console(\obray\core\http\requests\CONSOLERequest $request, string $name = ''): void {
            $this->data = ['name' => $name, 'method' => $request->getMethod()];
        }
    }
}
namespace {
    use obray\core\Factory;
    use obray\core\Invoker;
    use obray\core\Router;
    use obray\core\encoders\JSONEncoder;
    use obray\core\interfaces\PermissionsInterface;

    // CLI is decided by SAPI, even if a worker has populated HTTP-shaped server variables.
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['argv'] = [];
    $_REQUEST = ['TENANT' => 'unused'];
    $router = new Router(new Factory(), new Invoker());
    $router->setConsoleEncoder(new JSONEncoder());
    $router->setCheckPermissionsHandler(new class implements PermissionsInterface {
        public function checkPermissions(mixed $obj, ?string $fn = null) { throw new RuntimeException('CLI should not use HTTP permissions.'); }
        public function hasPermission(string $code): bool { return false; }
    });
    $router->route('/consoleBoundary?name=worker', [], true);
    $response = $router->getLastResponse();
    $body = json_decode($response['body'], true);
    if ($response['code'] !== 200 || $body['data'] !== ['name' => 'worker', 'method' => 'CONSOLE']) {
        throw new RuntimeException('Genuine CLI routing and query arguments must still work.');
    }
    echo "Console request tests passed\n";
}
