<?php
declare(strict_types=1);

namespace {
    if (!defined('__BASE_DIR__')) {
        define('__BASE_DIR__', dirname(__DIR__) . '/');
    }
    require __DIR__ . '/bootstrap.php';

    function assert_factory(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

// Fixture classes in their own namespace to avoid collisions
namespace factory\fixtures {

    class Engine {}

    class Car {
        public function __construct(public Engine $engine) {}
    }

    class ServiceA {
        public function __construct(public ServiceB $b) {}
    }

    class ServiceB {
        public function __construct(public ServiceA $a) {}
    }

    class NodeA {
        public function __construct(public NodeB $b) {}
    }

    class NodeB {
        public function __construct(public NodeC $c) {}
    }

    class NodeC {
        public function __construct(public NodeA $a) {}
    }
}

namespace {

    use obray\core\Factory;
    use obray\core\exceptions\CircularDependencyException;

    $factory = new Factory();

    // --- Normal instantiation with a dependency still works ---

    $car = $factory->make(\factory\fixtures\Car::class);
    assert_factory($car instanceof \factory\fixtures\Car, 'Factory should instantiate Car.');
    assert_factory($car->engine instanceof \factory\fixtures\Engine, 'Factory should inject Engine dependency.');

    // --- Direct cycle (A -> B -> A) throws CircularDependencyException ---

    $caught = false;
    try {
        $factory->make(\factory\fixtures\ServiceA::class);
    } catch (CircularDependencyException $e) {
        $caught = true;
        assert_factory(
            strpos($e->getMessage(), 'factory\fixtures\ServiceA') !== false,
            'Exception message should name the class that closed the cycle.'
        );
    }
    assert_factory($caught, 'Direct circular dependency should throw CircularDependencyException.');

    // --- Indirect cycle (A -> B -> C -> A) throws CircularDependencyException ---

    $caught = false;
    try {
        $factory->make(\factory\fixtures\NodeA::class);
    } catch (CircularDependencyException $e) {
        $caught = true;
        assert_factory(
            strpos($e->getMessage(), 'factory\fixtures\NodeA') !== false,
            'Exception message should name the class that closed the indirect cycle.'
        );
    }
    assert_factory($caught, 'Indirect circular dependency should throw CircularDependencyException.');

    // --- Resolution chain is per-call, not global (same class in two independent makes) ---

    $car1 = $factory->make(\factory\fixtures\Car::class);
    $car2 = $factory->make(\factory\fixtures\Car::class);
    assert_factory($car1 instanceof \factory\fixtures\Car, 'Second independent make should succeed.');
    assert_factory($car1 !== $car2, 'Each make call should return a new instance.');

    echo "Factory tests passed\n";
}
