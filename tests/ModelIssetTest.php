<?php
require __DIR__ . '/bootstrap.php';

class IssetModel extends obray\data\DBO
{
    const TABLE = 'isset_models';
    public obray\data\types\PrimaryKey $col_id;
    protected obray\data\types\Varchar64 $col_name;
    public function clearName(): void { unset($this->col_name); }
}
class IssetOtherModel extends obray\data\DBO
{
    const TABLE = 'isset_other_models';
    public obray\data\types\PrimaryKey $col_id;
}
function isset_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$a = new IssetModel(id: 0, name: null);
$b = new IssetOtherModel(id: 1);
isset_check(isset($a->id), 'Zero primary key is set');
isset_check(isset($a->name), 'Initialized wrapper remains set even if its value is null');
isset_check(!isset($a->cust_missing), 'Missing relationship is not set');
$a->cust_children = [];
isset_check(isset($a->cust_children), 'Empty relationship is set');
$a->cust_children = null;
isset_check(!isset($a->cust_children), 'Null relationship is not set');
$a->clearName();
isset_check(!isset($a->name), 'Uninitialized column is not set');
$a->name = 'restored';
isset_check(isset($a->name) && $a->name === 'restored', 'Reinitialized protected column is set');
$b->name = 'dynamic';
isset_check(isset($b->name), 'Metadata must be scoped to the concrete class');
isset_check(!isset($b->absent) && !isset($a->absent), 'Repeated negative checks remain false');
isset_check(!property_exists($a, 'absent'), 'Existence checks must not create properties');
echo "Model isset tests passed.\n";
