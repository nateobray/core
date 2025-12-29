<?php
namespace obray\users;

use obray\core\interfaces\PermissionsInterface;

/**
 * Null object implementation used when no permission handler is supplied.
 */
class NullPermissionHandler implements PermissionsInterface
{
    public function checkPermissions(mixed $obj, ?string $fn = null)
    {
        // intentionally allow everything
    }

    public function hasPermission(string $code): bool
    {
        return true;
    }
}
