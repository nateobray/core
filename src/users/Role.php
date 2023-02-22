<?php
namespace obray\users;

use obray\data\DBO;
use obray\data\types\PrimaryKey;
use obray\data\types\Text;
use obray\data\types\Varchar24;

/**
 * Role
 * 
 * A role is a role that a user can have
 */

Class Role extends DBO
{
	// The table name for this DBO
	const TABLE = 'Roles';

	protected PrimaryKey $col_role_id;
	protected Varchar24 $col_role_code;
	protected Text $col_role_description;

	const INDEXES = [
		['role_code', 'UNIQUE']
	];
}