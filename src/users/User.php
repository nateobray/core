<?php
namespace obray\users;

use obray\data\DBO;
use obray\data\types\Boolean;
use obray\data\types\BooleanTrue;
use obray\data\types\DateTimeNullable;
use obray\data\types\Int11UnsignedDefault0;
use obray\data\types\Password;
use obray\data\types\PrimaryKey;
use obray\data\types\TinyInt1UnsignedDefault1;
use obray\data\types\Varchar64;
use obray\data\types\Varchar64Nullable;

Class User extends DBO
{
    const TABLE = 'Users';
    
    public PrimaryKey $col_user_id;
    public Varchar64Nullable $col_user_first_name;
    public Varchar64Nullable $col_user_last_name;
    public Varchar64 $col_user_email;
    public Password $col_user_password;
    public TinyInt1UnsignedDefault1 $col_user_permission_level;
    public BooleanTrue $col_user_is_active;
    public Boolean $col_user_is_system;
    public Int11UnsignedDefault0 $col_user_failed_attempts;
    public DateTimeNullable $col_user_last_login;

    const INDEXES = [
        ['user_first_name'],
        ['user_last_name'],
        ['user_email', 'UNIQUE'],
        ['user_permission_level'],
        ['user_is_active'],
        ['user_is_system'],
        ['user_password'],
        ['user_failed_attempts'],
        ['user_last_login']
    ];
}