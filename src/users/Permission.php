<?php
namespace obray\users;

use obray\data\DBO;
use obray\data\types\PrimaryKey;
use obray\data\types\Text;
use obray\data\types\Varchar24;

Class Permission extends DBO
{
	const TABLE = 'Permissions';

	const ANY = 1;
	
	protected PrimaryKey $col_permission_id;
	protected Varchar24 $col_permission_code;
	protected Text $col_permission_description;

	const INDEXES = [
		['permission_code', 'UNIQUE']
	];
}