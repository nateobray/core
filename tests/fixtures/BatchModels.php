<?php
namespace tests\fixtures;
use obray\data\DBO;
use obray\data\types\PrimaryKey;
use obray\data\types\Int11;
use obray\data\types\Varchar64;
class BatchParent extends DBO {
    const TABLE = 'batch_parents';
    public PrimaryKey $col_id;
    public Int11 $col_tenant;
    public Varchar64 $col_name;
}
class BatchItem extends DBO {
    const TABLE = 'batch_items';
    public PrimaryKey $col_id;
    public Int11 $col_parent_id;
    public Int11 $col_visible;
    public Varchar64 $col_name;
}
class BatchDocument extends BatchItem { const TABLE = 'batch_documents'; }
