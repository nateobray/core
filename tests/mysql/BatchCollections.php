<?php
// Included by IntegrationTest.php only after disposable-server identity checks.
require dirname(__DIR__) . '/fixtures/BatchModels.php';
use tests\fixtures\BatchParent;
use tests\fixtures\BatchItem;
use tests\fixtures\BatchDocument;
use obray\core\PerformanceProfile;

$db->run('CREATE TABLE batch_parents (id INT PRIMARY KEY, tenant INT, name VARCHAR(64))');
foreach(['batch_items','batch_documents'] as $table)$db->run("CREATE TABLE $table (id INT PRIMARY KEY,parent_id INT,visible INT,name VARCHAR(64),INDEX(parent_id))");
for($p=1;$p<=52;$p++){
    $db->run('INSERT INTO batch_parents VALUES (:id,:tenant,:name)',[':id'=>$p,':tenant'=>$p===52?2:1,':name'=>'parent '.$p]);
    if($p===51)continue;
    foreach(['batch_items'=>20,'batch_documents'=>10] as $table=>$number){
        $values=[];
        for($i=1;$i<=$number;$i++)$values[]='('.($p*100+$i).','.$p.',1,\'child '.$i.'\')';
        $db->run("INSERT INTO $table VALUES ".implode(',',$values));
    }
}
$db->run("INSERT INTO batch_items VALUES (99999,1,0,'hidden')");
$batch=fn()=>$querier->select(BatchParent::class)->where(['tenant'=>1])->orderBy(['batch_parents.id ASC'])
    ->withMany('cust_items',BatchItem::class,'parent_id',['visible'=>1])
    ->withMany('cust_docs',BatchDocument::class,'parent_id');
$joined=fn()=>$querier->select(BatchParent::class)->where(['batch_parents.tenant'=>1])
    ->leftJoin('cust_items',BatchItem::class,null,'parent_id','id',['cust_items.visible'=>1])
    ->leftJoin('cust_docs',BatchDocument::class,null,'parent_id','id')
    ->orderBy(['batch_parents.id ASC','cust_items.id ASC','cust_docs.id ASC']);
$expected=$joined()->run();$actual=$batch()->run();
check_mysql(json_encode($expected)===json_encode($actual),'Batched and joined collections must produce identical graphs including absent children.');
check_mysql(count($actual)===51 && $actual[50]->cust_items===[] && $actual[50]->cust_docs===[],'Empty collections remain arrays, scoped-out parents stay absent.');
$original=$actual[0]->cust_items[0]->name;
$actual[0]->cust_items[0]->name='changed';
check_mysql($actual[0]->cust_docs[0]->name===$original && $batch()->firstOrNull()->cust_items[0]->name===$original,'Objects and wrappers must not leak across aliases or executions.');
$page=$batch()->limit(2,1)->run();
check_mysql(array_map(fn($p)=>$p->id,$page)===[2,3] && count($page[0]->cust_items)===20 && count($page[1]->cust_docs)===10,'Parent pagination must retain complete child collections and order.');
PerformanceProfile::start();$count=$batch()->count();$profile=PerformanceProfile::stop();
check_mysql($count===51 && $profile['statements_executed']===1.0,'Count executes only the parent query.');
PerformanceProfile::start();
$chunked=$querier->select(BatchParent::class)->where(['id'=>[1,2,3,4,5]])->orderBy(['id DESC'])
    ->withMany('cust_items',BatchItem::class,'parent_id',['visible'=>1],['batch_items.id DESC'],2)->run();
$profile=PerformanceProfile::stop();
check_mysql($profile['statements_executed']===4.0 && array_map(fn($p)=>$p->id,$chunked)===[5,4,3,2,1],'Batch chunking bounds child queries without changing parent order.');
check_mysql($chunked[0]->cust_items[0]->id===520,'Child ordering is independent of parent ordering.');
$scoped=$querier->select(BatchParent::class)->where(['id'=>1])
    ->withMany('cust_items',BatchItem::class,'parent_id',['parent_id'=>52])->run();
check_mysql($scoped[0]->cust_items===[],'Caller filters cannot replace the parent restriction.');
$or=$querier->select(BatchParent::class)->where(['id'=>1])->withMany('cust_items',BatchItem::class,'parent_id',['visible'=>new obray\data\sql\RawSQL('visible = 1 OR visible = 0')])->run();
check_mysql(count($or[0]->cust_items)===21 && count(array_filter($or[0]->cust_items,fn($i)=>$i->parent_id!==1))===0,'OR filter must remain within returned parent scope.');
// Exercise the same connection inside an existing transaction; the loader must not commit it.
$db->beginTransaction();
$db->run("INSERT INTO batch_items VALUES (99998,1,1,'uncommitted')");
check_mysql(count($batch()->firstOrNull()->cust_items)===21 && $db->inTransaction(),'Children must use the caller transaction without committing it.');
$db->rollback();
check_mysql(count($batch()->firstOrNull()->cust_items)===20,'Rolled-back children must not survive in a cache.');
foreach(['joined'=>$joined,'batched'=>$batch] as $label=>$factory){
    $times=[];$profiles=[];
    for($i=0;$i<5;$i++){
        PerformanceProfile::start();$result=$factory()->run();$profile=PerformanceProfile::stop();
        $times[]=$profile['total_ms'];$profiles[]=$profile;
        check_mysql(json_encode($result)===json_encode($expected),'Benchmark iterations must retain exact graph equality.');
    }
    sort($times);
    echo json_encode(['collection_benchmark'=>$label,'median_total_ms'=>round($times[2],3),'queries'=>$profile['statements_executed'],'rows'=>$profile['rows_fetched'],'parents'=>count($result)]),PHP_EOL;
}
