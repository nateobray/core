<?php
namespace obray\users;

use obray\core\exceptions\PermissionDenied;
use obray\core\interfaces\PermissionsInterface;
use obray\sessions\Session;

/**
 * PermissionHandler
 * 
 * This class handles permissions for objects
 * 
 * @package obray\users
 */
class PermissionHandler implements PermissionsInterface
{
    private Session $session;

    /**
     * Builds the permissions handlers
     * 
     * @param Session $session Takes a Session
     * 
     * @return static
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * 
     * This method checks the pmerissions set on the object and allows permissions
     * accordingly
     *
     * @param mixed $obj The object we are going to check permissions on
     * @param bool $direct Specifies if the call is from a remote source
     * 
     * @throws PermissionDenied
     */
    public function checkPermissions(mixed $obj, string $fn = null)
    {
        $perms = [];
        
        // reflect the PERMISSIONS constant
        $reflect = new \ReflectionClass($obj);
        $perms = $reflect->getConstant('PERMISSIONS');
        $userPerms = [];
        if(isSet($this->session->user)) $userPerms = $this->session->user->permissions;
        
        // if object has permissions, check start additional perm checks
        if(!empty($perms)){

            // OBJECT
            // if we're just access the object and it's permissions are "any" then we let it through
            if($fn === null && isSet($perms["object"]) && $perms["object"] === Permission::ANY) return;
            // normalize the permissions to an array of permissions
            if(!is_array($perms["object"])) $perms["object"] = [$perms["object"]];
            // check the intersect of the set object perm and the users permissions
            if($fn === null && isSet($perms["object"]) && !empty($userPerms) && !empty(array_intersect($perms["object"], $userPerms))) return;

            // FUNCTION
            // if we have a function check to see if it's perms are "any" and if so let it through
            if($fn !== null && isSet($perms[$fn]) && $perms[$fn] === Permission::ANY) return;
            // normalize our function perm
            if($fn !== null && isSet($perms[$fn]) && !is_array($perms[$fn])) $perms[$fn] = [$perms[$fn]];
            // check the intersect of the set function perm and the users permissions
            if($fn !== null && isSet($perms[$fn]) && !empty($userPerms) && !empty(array_intersect($perms[$fn], $userPerms))) return;
        }
        // if we don't pass the permissions check then we 403
        throw new PermissionDenied('You cannot access this resource.', 403);
    }

    /**
     * Simply returns if the user has permission
     * 
     * @param string $code the code to check if exists in the users permissions
     * 
     * @return bool
     */
    public function hasPermission(string $code): bool
    {
        if(!empty($this->session->user->permissions) && in_array($code,$this->session->user->permissions)){
            return true;
        }
        return false;
    }
}