<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/BatchModels.php';
use tests\fixtures\BatchParent;
use tests\fixtures\BatchItem;
use obray\data\Querier;
class BatchFakeDB extends obray\data\DBConn {
    public array $queries = [];
    public bool $fail = false;
    public array $parents = [['batch_parents_id'=>0,'batch_parents_tenant'=>1,'batch_parents_name'=>'zero']];
    public function __construct() {}
    public function run($sql,$bind=[],$fetchStyle=PDO::FETCH_OBJ) {
        $this->queries[] = [$sql,$bind];
        if(str_contains($sql,'count(*)'))return [[['count'=>count($this->parents)]]];
        if(str_contains($sql,'FROM `batch_parents`'))return [$this->parents];
        if($this->fail)throw new RuntimeException('child failure');
        return [[['batch_items_id'=>1,'batch_items_parent_id'=>0,'batch_items_visible'=>1,'batch_items_name'=>'child']]];
    }
}
function batch_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function batch_fail($callback,$type){try{$callback();}catch(Throwable $e){batch_check($e instanceof $type,'Unexpected error type');return;}throw new RuntimeException('Expected failure');}
$db=new BatchFakeDB();$q=new Querier($db);
$stmt=$q->select(BatchParent::class)->withMany('cust_items',BatchItem::class,'parent_id');
$rows=$stmt->run();batch_check($rows[0]->cust_items[0]->parent_id===0,'Zero parent key must be loaded');
batch_check(count($db->queries)===2,'One parent plus one child query');
$rows[0]->cust_items[0]->name='mutated';
$fresh=$stmt->run();batch_check($fresh[0]->cust_items[0]->name==='child','Results must not leak across executions');
$before=count($db->queries);batch_check($stmt->count()===1 && count($db->queries)===$before+1,'Count must not load children');
$single=$stmt->firstOrNull();batch_check($single instanceof BatchParent && count($single->cust_items)===1,'firstOrNull must attach collections');
$db->parents=[];$before=count($db->queries);batch_check($stmt->run()===[] && count($db->queries)===$before+1,'Empty parents must skip child query');
batch_check($stmt->firstOrNull()===null,'Empty firstOrNull must remain null');
foreach([['items',BatchItem::class,'parent_id',[],[],500],['cust_items',BatchItem::class,'missing',[],[],500],['cust_items',BatchItem::class,'parent_id',[],[],0]] as $args)batch_fail(fn()=>$q->select(BatchParent::class)->withMany(...$args),InvalidArgumentException::class);
batch_fail(fn()=>$q->select(BatchParent::class)->withMany('cust_items',BatchItem::class,'parent_id')->withMany('cust_items',BatchItem::class,'parent_id'),InvalidArgumentException::class);
batch_fail(fn()=>$q->select(BatchParent::class)->withMany('cust_items',BatchItem::class,'parent_id')->leftJoin('cust_items',BatchItem::class,null,'parent_id','id')->run(),InvalidArgumentException::class);
batch_fail(fn()=>$stmt->run('SELECT 1'),LogicException::class);
$db->parents=[['batch_parents_id'=>1,'batch_parents_tenant'=>1,'batch_parents_name'=>'one']];$db->fail=true;
batch_fail(fn()=>$stmt->run(),RuntimeException::class);
echo "Batch collection tests passed.\n";
