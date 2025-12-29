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

namespace {

use obray\core\http\Body;
use obray\core\http\Response;
use obray\core\http\ServerRequest;
use obray\core\http\StatusCode;

// Body stream behaviour
$body = new Body('foo');
assert_true((string)$body === 'foo', 'Body::__toString should return contents.');
assert_true($body->isReadable(), 'Body should be readable.');
$body->rewind();
assert_true($body->getContents() === 'foo', 'Body->getContents should read all content.');
$body->rewind();
assert_true($body->read(3) === 'foo', 'Body->read should read expected bytes.');

// Response header immutability
$response = new Response(StatusCode::OK, ['X-Test' => 'one']);
assert_true($response->getHeaderLine('X-Test') === 'one', 'Initial header mismatch.');
$responseWithHeader = $response->withHeader('X-Test', 'two');
assert_true($response->getHeaderLine('X-Test') === 'one', 'withHeader must not mutate original.');
assert_true($responseWithHeader->getHeaderLine('X-Test') === 'two', 'withHeader should replace value.');
$responseAppended = $responseWithHeader->withAddedHeader('X-Test', 'three');
assert_true($responseAppended->getHeaderLine('X-Test') === 'two, three', 'withAddedHeader should append values.');

// ServerRequest attribute & header access
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com',
    'REQUEST_URI' => '/foo?bar=baz',
    'HTTP_AUTHORIZATION' => 'Bearer token123'
];
$_GET = ['bar' => 'baz'];
$_POST = [];
$_COOKIE = ['session' => 'abc'];
$request = new ServerRequest('/foo?bar=baz');
assert_true($request->getHeaderLine('authorization') === 'Bearer token123', 'Authorization header mismatch.');
assert_true($request->getAttribute('user', null) === null, 'Unexpected default attribute.');
$requestWithAttribute = $request->withAttribute('user', 42);
assert_true($request->getAttribute('user', null) === null, 'withAttribute should be immutable.');
assert_true($requestWithAttribute->getAttribute('user') === 42, 'Attribute value mismatch.');
assert_true($request->withCookieParams(['a' => 'b'])->getCookieParams()['a'] === 'b', 'withCookieParams failed.');

$exceptionThrown = false;
try {
    $request->withParsedBody('invalid');
} catch (\InvalidArgumentException $e) {
    $exceptionThrown = true;
}
assert_true($exceptionThrown, 'withParsedBody must reject invalid data.');

echo "PSR-7 compliance smoke test passed\n";

}
