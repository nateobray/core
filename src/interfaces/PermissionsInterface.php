<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray\core\interfaces;

/**
 * Describes the permissions interface
 */
interface PermissionsInterface
{
        public function checkPermissions(mixed $obj, string $fn = null);
        public function hasPermission(string $code): bool;
}
