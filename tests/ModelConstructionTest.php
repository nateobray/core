<?php
require __DIR__ . '/bootstrap.php';

class ConstructionNull extends obray\data\types\BaseType { const DEFAULT = null; }
class ConstructionFalse extends obray\data\types\BaseType { const DEFAULT = false; }
class ConstructionZero extends obray\data\types\BaseType { const DEFAULT = 0; }
class ConstructionText extends obray\data\types\BaseType { const DEFAULT = '00123'; }
class ConstructionOverride extends ConstructionText { const DEFAULT = 'other'; }
class ConstructionFresh extends obray\data\types\BaseType {
    const DEFAULT = 'runtime';
    public static int $constructed = 0;
    public function __construct(mixed $value = self::DEFAULT) {
        parent::__construct($value === 'runtime' ? ++self::$constructed : $value);
    }
}
class ConstructionModel extends obray\data\DBO {
    const TABLE = 'construction_models';
    public obray\data\types\PrimaryKey $col_id;
    public ConstructionNull $col_nullable;
    public ConstructionFalse $col_flag;
    public ConstructionZero $col_number;
    public ConstructionText $col_code;
    public ConstructionFresh $col_fresh;
}
class ConstructionOther extends obray\data\DBO {
    const TABLE = 'construction_others';
    public obray\data\types\PrimaryKey $col_id;
    public ConstructionOverride $col_code;
}
function construction_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$a = new ConstructionModel(); $b = new ConstructionModel();
construction_check($a->nullable === null && $a->flag === false && $a->number === 0 && $a->code === '00123', 'Defaults changed');
construction_check($a->fresh === 1 && $b->fresh === 2, 'Runtime defaults must be normalized per instance');
construction_check($a->col_code !== $b->col_code && $a->col_nullable !== $b->col_nullable, 'Wrappers must never be shared');
$a->code = 'changed';
construction_check($b->code === '00123', 'Instance values leaked');
$c = new ConstructionModel(id: 0, nullable: 'explicit', flag: null, number: false, code: null, fresh: 99);
construction_check($c->getPrimaryKeyValue() === 0 && $c->nullable === 'explicit' && $c->flag === null && $c->number === false && $c->code === null && $c->fresh === 99, 'Explicit values must override defaults including null');
$d = new ConstructionModel(...[0=>7, 1=>null, 2=>true, 3=>8, 4=>'positional', 5=>100, 'code'=>'named']);
construction_check($d->code === 'positional' && $d->getPrimaryKeyValue() === 7, 'Positional precedence changed');
$other = new ConstructionOther();
construction_check($other->code === 'other' && (new ConstructionModel())->code === '00123', 'Defaults leaked between type classes');
construction_check(!$c->isDirty() && !$d->isDirty(), 'Construction must finish clean');
echo "Model construction tests passed.\n";
