<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Actor
{
    public function __construct(
        public int $id,
        public string $type,
        public string $email,
        public ?int $clientId,
        public array $roles,
        public array $permissions,
        public ?string $displayName = null,
    ) {
    }

    public function can(string $permission): bool
    {
        return in_array('SYSTEM_ADMIN', $this->roles, true)
            || in_array($permission, $this->permissions, true);
    }

    public function identifier(): string
    {
        return $this->type . ':' . $this->id;
    }
}
