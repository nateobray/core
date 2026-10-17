<?php
namespace {
require dirname(__DIR__) . '/bootstrap.php';
function bench($fn, $n=5) {
    $fn(); $times=[];
    for($i=0;$i<$n;$i++){gc_collect_cycles();$start=hrtime(true);$result=$fn();$times[]=(hrtime(true)-$start)/1e6;unset($result);}
    sort($times);return round($times[intdiv(count($times),2)],3);
}
class ParentRow extends obray\data\DBO {
    const TABLE='parents'; public static int $created=0;
    public obray\data\types\PrimaryKey $col_id;
    public obray\data\types\Varchar64 $col_name;
    public function __construct(...$args){ self::$created++; parent::__construct(...$args); }
}
class ChildRow extends obray\data\DBO {
    const TABLE='children'; public static int $created=0;
    public obray\data\types\PrimaryKey $col_id;
    public obray\data\types\ForeignKey $col_parent_id;
    public obray\data\types\Varchar64 $col_name;
    public function __construct(...$args){ self::$created++; parent::__construct(...$args); }
}
class TagRow extends ChildRow { const TABLE='tags'; }
class FixtureDB extends obray\data\DBConn {
    public function __construct(public array $rows){}
    public function run($sql,$bind=[],$fetchStyle=\PDO::FETCH_OBJ){return [$this->rows];}
}
function rows($parents,$children,$tags) {
    $rows=[];
    for($p=1;$p<=$parents;$p++) for($c=1;$c<=$children;$c++) for($t=1;$t<=$tags;$t++) $rows[]=[
        'parents_id'=>$p,'parents_name'=>'parent-'.$p,
        'cust_children_id'=>$p*100+$c,'cust_children_parent_id'=>$p,'cust_children_name'=>'child-'.$c,
        'cust_tags_id'=>$p*100+$t,'cust_tags_parent_id'=>$p,'cust_tags_name'=>'tag-'.$t,
    ];
    return $rows;
}
function hydrate($db){
    return (new obray\data\Querier($db))->select(ParentRow::class)
        ->leftJoin('cust_children',ChildRow::class,null,'parent_id','id')
        ->leftJoin('cust_tags',TagRow::class,null,'parent_id','id')->run();
}
// Flat-join prototype only: cache each row identity before constructing a DBO.
function indexed($rows){
    $parents=[];
    foreach($rows as $r){
        $p=$r['parents_id'];$c=$r['cust_children_id'];$t=$r['cust_tags_id'];
        if(!isset($parents[$p])){
            $parents[$p]=new ParentRow(id:$p,name:$r['parents_name']);
            $parents[$p]->cust_children=[];$parents[$p]->cust_tags=[];
        }
        if(!isset($parents[$p]->cust_children[$c])) $parents[$p]->cust_children[$c]=new ChildRow(id:$c,parent_id:$p,name:$r['cust_children_name']);
        if(!isset($parents[$p]->cust_tags[$t])) $parents[$p]->cust_tags[$t]=new TagRow(id:$t,parent_id:$p,name:$r['cust_tags_name']);
    }
    foreach($parents as $p){$p->cust_children=array_values($p->cust_children);$p->cust_tags=array_values($p->cust_tags);}
    return array_values($parents);
}
$report=['php'=>PHP_VERSION,'opcache_cli'=>ini_get('opcache.enable_cli')];
foreach([[1000,1,1],[1000,5,1],[1000,5,5]] as [$p,$c,$t]){
    $before=memory_get_usage();$db=new FixtureDB(rows($p,$c,$t));$raw=memory_get_usage()-$before;
    ParentRow::$created=ChildRow::$created=0;
    $start=memory_get_usage();
    if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
    $result=hydrate($db);
    $peak=memory_get_peak_usage()-$start;
    $counts=['parent_constructions'=>ParentRow::$created,'child_tag_constructions'=>ChildRow::$created];
    if(json_encode($result)!==json_encode(indexed($db->rows)))throw new \RuntimeException('Prototype shape differs');
    $report['joins'][]=['sql_rows'=>count($db->rows),'unique_objects'=>$p*(1+$c+$t),'constructed'=>$counts,
       'raw_mib'=>round($raw/1048576,2),'hydration_peak_extra_mib'=>function_exists('memory_reset_peak_usage') ? round($peak/1048576,2) : null,
       'orm_ms'=>bench(fn()=>hydrate($db)),'flat_identity_prototype_ms'=>bench(fn()=>indexed($db->rows))];
    unset($result,$db);
}
echo json_encode($report, JSON_PRETTY_PRINT), "\n";
}
