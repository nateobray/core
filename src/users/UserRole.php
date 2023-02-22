<?php
namespace obray\users;

use obray\data\DBO;
use obray\data\types\Int11Unsigned;
use obray\data\types\PrimaryKey;

Class UserRole extends DBO
{
    const TABLE = 'UserRoles';
    
    protected PrimaryKey $col_user_role_id;
    protected Int11Unsigned $col_role_id;
    protected Int11Unsigned $col_user_id;

    const INDEXES = [
        [['role_id', 'user_id'], 'UNIQUE']
    ];

    const FOREIGN_KEYS = [
        ['role_id', 'Roles', 'role_id'],
        ['user_id', 'Users', 'user_id']
    ];
}