<?php
require __DIR__ . '/bootstrap.php';

class SerializationModel extends obray\data\DBO
{
    const TABLE = 'serialization_models';
    public obray\data\types\PrimaryKey $col_id;
    public obray\data\types\Varchar64 $col_code;
    protected obray\data\types\Varchar64 $col_internal;
    public obray\data\types\Password $col_password;
}

$parent = new SerializationModel(id: 1, code: '00123', internal: 'hidden', password: 'never expose');
$child = new SerializationModel(id: 2, code: '000456', internal: 'child', password: 'also hidden');
$parent->cust_children = [$child];
$parent->cust_hidden = 'excluded relation';
$parent->unrelated = 'not serialized';
$parent->setEncodeNot(['col_internal', 'cust_hidden']);
$child->setEncodeNot(['col_internal']);
$expected = ['id' => 1, 'code' => '00123', 'children' => [['id' => 2, 'code' => '000456']]];
foreach ([$parent, $parent->toArray()] as $value) {
    $actual = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    if ($actual !== $expected) throw new RuntimeException('Serialization changed fields, identifiers, relations, or password exclusion.');
}
// Protected typed columns remain serializable unless explicitly excluded.
$child->setEncodeNot([]);
if (($child->toArray()['internal'] ?? null) !== 'child') throw new RuntimeException('Protected column disappeared.');
echo "Model serialization tests passed.\n";
