<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use obray\core\encoders\JSONEncoder;

$data = ['sku' => '000456', 'zip' => '00123', 'phone' => '+15551234567',
    'external_id' => '999999999999999999999999', 'quantity' => 2, 'active' => true, 'empty' => null];
$controller = (object)['data' => $data];
$decoded = json_decode((new JSONEncoder())->encode($controller, microtime(true)), true);
if ($decoded['data'] !== $data) throw new RuntimeException('Default JSON encoding must preserve strings and native types.');
$legacy = json_decode((new JSONEncoder(true))->encode($controller, microtime(true)), true);
if ($legacy['data']['sku'] !== 456) throw new RuntimeException('Explicit numeric conversion must remain available.');
echo "JSON encoder tests passed\n";
