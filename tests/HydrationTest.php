<?php
require __DIR__ . '/bootstrap.php';

use obray\data\DBO;
use obray\data\DBConn;
use obray\data\Querier;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;
use obray\core\PerformanceProfile;

class HydrationParent extends DBO {
    const TABLE = 'parents';
    public static int $created = 0;
    public PrimaryKey $col_id;
    public Varchar64 $col_name;
    public function __construct(...$params) { self::$created++; parent::__construct(...$params); }
}
class HydrationChild extends HydrationParent { const TABLE = 'children'; }
class HydrationDB extends DBConn {
    public function __construct(public array $rows) {}
    public function run($sql, $bind = [], $fetchStyle = PDO::FETCH_OBJ) { return [$this->rows]; }
}
function hydration_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function hydration_row($parent, $child, $leaf, $tag): array {
    return ['parents_id'=>$parent, 'parents_name'=>'parent',
        'cust_children_id'=>$child, 'cust_children_name'=>$child === null ? null : 'child',
        'cust_leaves_id'=>$leaf, 'cust_leaves_name'=>$leaf === null ? null : 'leaf',
        'cust_tags_id'=>$tag, 'cust_tags_name'=>$tag === null ? null : 'tag'];
}
$db = new HydrationDB([
    hydration_row(1, 10, 100, 10), hydration_row(1, 10, 101, 10),
    hydration_row(1, 10, 100, 21), hydration_row(1, 11, null, 21),
    hydration_row(2, 10, 102, 20), hydration_row(3, null, null, null),
    hydration_row(4, 0, 0, 0),
]);
$query = (new Querier($db))->select(HydrationParent::class)
    ->leftJoin('cust_children', HydrationChild::class, null, 'id', 'id')
    ->leftJoin('cust_leaves', HydrationChild::class, [HydrationChild::class, 'cust_children'], 'id', 'id')
    ->leftJoin('cust_tags', HydrationChild::class, null, 'id', 'id');
PerformanceProfile::start();
$results = $query->run();
$metrics = PerformanceProfile::stop();
hydration_check(count($results) === 4, 'Root rows are deduplicated.');
hydration_check(count($results[0]->cust_children) === 2 && count($results[0]->cust_tags) === 2, 'Independent collections must deduplicate.');
hydration_check(array_map(fn($x) => $x->id, $results[0]->cust_children[0]->cust_leaves) === [100, 101], 'Nested children merge in encounter order.');
hydration_check(!isset($results[0]->cust_children[1]->cust_leaves), 'Absent nested relationships retain their omitted shape.');
hydration_check($results[2]->cust_children === [] && $results[2]->cust_tags === [], 'Null left joins produce empty root collections.');
hydration_check($results[0]->cust_children[0] !== $results[1]->cust_children[0], 'Models must not be shared across parents.');
hydration_check($results[0]->cust_children[0] !== $results[0]->cust_tags[0], 'Equal keys in distinct aliases must not share models.');
hydration_check($results[1]->cust_children[0]->cust_leaves[0]->id === 102, 'Nested children stay scoped to their parent.');
hydration_check($results[3]->cust_children[0]->id === 0, 'Integer zero is a valid model identity.');
hydration_check(HydrationParent::$created === 16 && $metrics['models_hydrated'] === 16.0, 'Construct each distinct model exactly once, skipping null joins.');
hydration_check(isset($metrics['hydration_ms']) && PerformanceProfile::stop() === [], 'Profiling stops and clears state.');
$results[0]->cust_children[0]->name = 'changed';
hydration_check($results[1]->cust_children[0]->name === 'child', 'Mutations cannot cross parent relationships.');
$fresh = $query->run();
hydration_check($fresh[0]->cust_children[0]->name === 'child', 'Identity maps cannot leak across executions.');
hydration_check(array_keys($fresh[0]->cust_children[0]->cust_leaves) === [0, 1], 'JSON collections remain lists.');
$db->rows = [];
hydration_check($query->run() === [], 'Empty results must not retain an earlier graph.');
PerformanceProfile::start();
try { PerformanceProfile::measure('failure', fn() => throw new RuntimeException('secret')); } catch (RuntimeException $e) {}
$failure = PerformanceProfile::stop();
hydration_check(isset($failure['failure_ms']) && !str_contains(json_encode($failure), 'secret'), 'Failed operations record duration without error contents.');
echo "Hydration and profiling tests passed.\n";
