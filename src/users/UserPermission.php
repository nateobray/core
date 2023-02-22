<?php
namespace obray\users;

use obray\data\DBO;
use obray\data\types\Int11Unsigned;
use obray\data\types\PrimaryKey;

/**
 * UserPermission
 * 
 * A user permission is an assignment of a permission to a user
 * 
 * @package obray\users
 */
Class UserPermission extends DBO
{
	const TABLE = 'UserPermissions';

	protected PrimaryKey $col_user_permission_id;
	protected Int11Unsigned $col_permission_id;
	protected Int11Unsigned $col_user_id;

	const INDEXES = [
		[['permission_id', 'user_id'], 'UNIQUE']
	];

	const FOREIGN_KEYS = [
		['permission_id', 'Permissions', 'permission_id'],
		['user_id', 'Users', 'user_id']
	];
}
?>