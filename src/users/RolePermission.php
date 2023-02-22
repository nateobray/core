<?php
namespace obray\users;

use obray\data\DBO;
use obray\data\types\Int11Unsigned;
use obray\data\types\PrimaryKey;

/**
 * RolePermission
 * 
 * A role permission is a permission that a role can have
 * 
 * @package obray\users
 */
Class RolePermission extends DBO
{
	const TABLE = 'RolePermissions';

	protected PrimaryKey $col_role_permission_id;
	protected Int11Unsigned $col_role_id;
	protected Int11Unsigned $col_permission_id;

	const INDEXES = [
		[['role_id', 'permission_id'], 'UNIQUE']
	];

	const FOREIGN_KEYS = [
		['role_id', 'Roles', 'role_id'],
		['permission_id', 'Permissions', 'permission_id']
	];
}