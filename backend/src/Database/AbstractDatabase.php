<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

abstract class AbstractDatabase
{
    abstract public function connection(): PDO;

    abstract public function transaction(callable $operation): mixed;
}
