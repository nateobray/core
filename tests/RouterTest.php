<?php
declare(strict_types=1);

namespace {
    define('OBRAY_FORCE_HTTP_REQUEST', true);
    require __DIR__ . '/bootstrap.php';
}

// --- Fixture controllers ---

namespace controllers\section {
    class Index {
        public $data = ['section' => 'index'];
        public function get(): void {}
    }
}

namespace controllers {

    use obray\core\http\requests\GETRequest;
    use obray\core\http\requests\POSTRequest;

    class RouterDummy {
        public $data;
        public function __construct() { $this->data = ['message' => 'ok']; }
        public function get(): void {}
    }

    class RouterPost {
        public $data;
        public function post(POSTRequest $request): void {
            $this->data = ['method' => 'post'];
        }
    }

    // get() exists but requires a POSTRequest — triggers 405 on GET
    class RouterWrongMethod {
        public $data;
        public function get(POSTRequest $request): void {
            $this->data = ['ok' => true];
        }
    }
}

// --- Tests ---

namespace {

    use obray\core\Factory;
    use obray\core\Invoker;
    use obray\core\Router;
    use obray\core\encoders\ErrorEncoder;
    use obray\core\encoders\HTMLEncoder;
    use obray\core\encoders\JSONEncoder;

    function assert_router(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    function makeRouter(): Router
    {
        $router = new Router(new Factory(null), new Invoker(), null, false, microtime(true));
        $router->addEncoder(JSONEncoder::class, 'data', 'application/json');
        $router->addEncoder(ErrorEncoder::class, 'error', 'application/json');
        $router->addEncoder(HTMLEncoder::class, 'html', 'text/html');
        return $router;
    }

    $_SERVER = array_replace([
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST'      => 'localhost',
        'REQUEST_URI'    => '/',
    ], $_SERVER ?? []);
    $_SERVER['argv'] = [];
    $_GET = $_POST = $_COOKIE = $_REQUEST = [];

    // -------------------------------------------------------------------------
    // 1. Index-path fallback: /section has no controllers\Section class,
    //    but controllers\Section\Index exists and should be found.
    // -------------------------------------------------------------------------

    $r = makeRouter();
    $r->route('/section', [], true);
    $last = $r->getLastResponse();

    assert_router(($last['code'] ?? null) === 200, 'Index-path fallback should return 200.');
    $payload = json_decode($last['body'], true);
    assert_router(($payload['data']['section'] ?? null) === 'index', 'Index-path fallback should return Section\Index data.');

    // -------------------------------------------------------------------------
    // 2. SPA fallback condition returning false → still 404.
    // -------------------------------------------------------------------------

    $r = makeRouter();
    $r->setNotFoundFallbackController(\controllers\section\Index::class, fn() => false);
    $r->route('/does/not/exist', [], true);
    $last = $r->getLastResponse();

    assert_router(($last['code'] ?? null) === 404, 'Fallback condition=false should still return 404.');

    // -------------------------------------------------------------------------
    // 3. Method routing via path: /routerdummy/get routes to
    //    controllers\RouterDummy and calls get() through the recursive search.
    // -------------------------------------------------------------------------

    $r = makeRouter();
    $r->route('/routerdummy/get', [], true);
    $last = $r->getLastResponse();

    assert_router(($last['code'] ?? null) === 200, 'Method-via-path routing should return 200.');
    $payload = json_decode($last['body'], true);
    assert_router(($payload['data']['message'] ?? null) === 'ok', 'Method-via-path routing should return RouterDummy data.');

    // -------------------------------------------------------------------------
    // 4. POST routing: POST request to a controller with post() returns 200.
    // -------------------------------------------------------------------------

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $r = makeRouter();
    $r->route('/routerpost', [], true);
    $last = $r->getLastResponse();

    assert_router(($last['code'] ?? null) === 200, 'POST routing should return 200.');
    $payload = json_decode($last['body'], true);
    assert_router(($payload['data']['method'] ?? null) === 'post', 'POST routing should invoke post() method.');

    $_SERVER['REQUEST_METHOD'] = 'GET';

    // -------------------------------------------------------------------------
    // 5. 405 Method Not Allowed: GET to a controller whose get() requires a
    //    POSTRequest type hint — Invoker catches the TypeError and returns 405.
    // -------------------------------------------------------------------------

    $r = makeRouter();
    $r->route('/routerwrongmethod', [], true);
    $last = $r->getLastResponse();

    assert_router(($last['code'] ?? null) === 405, 'Wrong request type hint should return 405 Method Not Allowed.');

    echo "Router tests passed\n";
}
