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

    class OptionalScalarConfig {
        public function __construct(
            public string $modelsPath = '',
            public array $featureSet = []
        ) {}
    }

    class RequiredScalarConfig {
        public function __construct(public string $modelsPath) {}
    }

    class Engine {}

    class Car {
        public function __construct(public Engine $engine) {}
    }

    class MixedDefaults {
        public function __construct(public string $label = 'default', public ?Engine $engine = null) {}
    }

    class UntypedDefault {
        public function __construct(public $label = 'default', public ?Engine $engine = null) {}
    }

    class OptionalInterface {
        public function __construct(public ?\Psr\Log\LoggerInterface $logger = null) {}
    }

    class VariadicEngines {
        public array $engines;
        public function __construct(Engine ...$engines) { $this->engines = $engines; }
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
    use obray\core\exceptions\DependencyNotFound;

    $factory = new Factory();

    // --- Builtin scalar defaults are left alone instead of routed through DI ---

    $optionalScalarConfig = $factory->make(\factory\fixtures\OptionalScalarConfig::class);
    assert_factory($optionalScalarConfig instanceof \factory\fixtures\OptionalScalarConfig, 'Factory should instantiate OptionalScalarConfig.');
    assert_factory($optionalScalarConfig->modelsPath === '', 'Factory should preserve default string constructor values.');
    assert_factory($optionalScalarConfig->featureSet === [], 'Factory should preserve default array constructor values.');

    // --- Required builtin scalars still fail with a clear dependency error ---

    $caught = false;
    try {
        $factory->make(\factory\fixtures\RequiredScalarConfig::class);
    } catch (DependencyNotFound $e) {
        $caught = true;
        assert_factory(
            strpos($e->getMessage(), 'builtin dependency string') !== false,
            'Exception message should explain that a required builtin dependency could not be resolved.'
        );
    }
    assert_factory($caught, 'Required builtin scalar dependencies should throw DependencyNotFound.');

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

    foreach ([\factory\fixtures\MixedDefaults::class, \factory\fixtures\UntypedDefault::class] as $class) {
        $mixed = $factory->make($class);
        assert_factory($mixed->label === 'default', 'Skipping defaults must preserve argument positions.');
        assert_factory($mixed->engine instanceof \factory\fixtures\Engine, 'Object dependency must receive its own argument.');
    }
    assert_factory($factory->make(\factory\fixtures\OptionalInterface::class)->logger === null, 'Unregistered optional interfaces must preserve defaults.');
    assert_factory($factory->make(\factory\fixtures\VariadicEngines::class)->engines === [], 'Unconfigured variadic dependencies must remain empty.');
    $engine = new \factory\fixtures\Engine();
    $container = new class($engine) implements \Psr\Container\ContainerInterface {
        public function __construct(private object $engine) {}
        public function has($id): bool { return $id === \factory\fixtures\Engine::class; }
        public function get($id) { return $this->engine; }
    };
    $mixed = (new Factory($container))->make(\factory\fixtures\MixedDefaults::class);
    assert_factory($mixed->engine === $engine && $mixed->label === 'default', 'Container injection must preserve argument positions and registered instances.');

    echo "Factory tests passed\n";
}
