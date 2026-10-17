<?php
// No application bootstrap or database connection: actual BM models, synthetic rows.
$bm = $argv[1] ?? '';
if (!is_file($bm . '/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php tests/performance/bm-hydration.php /path/to/bm\n");
    exit(1);
}
require $bm . '/vendor/autoload.php';
$core = getenv('PERF_CORE_ROOT') ?: dirname(__DIR__, 2);
spl_autoload_register(function ($class) use ($core) {
    foreach (['obray\\core\\'=>'src/', 'obray\\data\\'=>'src/data/'] as $prefix=>$dir) {
        if (strpos($class,$prefix)===0) {
            $file=$core.'/'.$dir.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
            if (is_file($file)) require_once $file;
            return;
        }
    }
},true,true);
use models\orders\PurchaseOrderReceipt as Receipt;
use models\orders\PurchaseOrderReceiptItem as ReceiptItem;
use models\orders\PurchaseOrderItem as POItem;
class ModelBenchDB extends obray\data\DBConn {
    public function __construct(public array $rows) {}
    public function run($sql,$bind=[],$fetchStyle=PDO::FETCH_OBJ) { return [$this->rows]; }
}
function fields($class,$alias) {
    $row=[];
    foreach (obray\data\Table::getColumns($class) as $col) {
        $value=constant($col->propertyClass.'::DEFAULT');
        if(str_contains($col->propertyClass,'Date')) $value='2026-01-01 00:00:00';
        $row[$alias.'_'.$col->propertyName]=$value;
    }
    return $row;
}
$base=fields(Receipt::class,Receipt::TABLE)+fields(ReceiptItem::class,'cust_purchase_order_receipt_items')+fields(POItem::class,'purchase_order_items');
$rows=[];
for($p=1;$p<=100;$p++)for($c=1;$c<=10;$c++)for($repeat=0;$repeat<5;$repeat++) {
    $r=$base;
    $r['PurchaseOrderReceipts_purchase_order_receipt_id']=$p;
    $r['cust_purchase_order_receipt_items_purchase_order_receipt_item_id']=$p*100+$c;
    $r['cust_purchase_order_receipt_items_purchase_order_receipt_id']=$p;
    $r['purchase_order_items_purchase_order_item_id']=$p*100+$c;
    $rows[]=$r;
}
$db=new ModelBenchDB($rows);
$query=(new obray\data\Querier($db))->select(Receipt::class)
    ->leftJoin('cust_purchase_order_receipt_items',ReceiptItem::class,null,'purchase_order_receipt_id','purchase_order_receipt_id')
    ->leftJoin('purchase_order_items',POItem::class,[ReceiptItem::class,'cust_purchase_order_receipt_items'],'purchase_order_item_id','purchase_order_item_id');
$query->run();$times=[];
for($i=0;$i<5;$i++){$s=hrtime(true);$result=$query->run();$times[]=(hrtime(true)-$s)/1e6;}
sort($times);
if(count($result)!==100 || count($result[0]->cust_purchase_order_receipt_items)!==10)throw new RuntimeException('Wrong shape');
echo json_encode(['core'=>$core,'rows'=>count($rows),'median_ms'=>$times[2],'response_hash'=>hash('sha256',json_encode($result))]),"\n";
