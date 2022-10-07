<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray\core\interfaces;

/**
 * Describes the interface of an obray users
 */
interface UsersInterface
{
        public function checkPermissions(mixed $obj, bool $direct);
}
