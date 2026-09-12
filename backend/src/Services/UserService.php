<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Actor;
use App\Repositories\EmployeeRepository;
use App\Utils\Input;
use App\Utils\Validator;

final class UserService
{
    private const EMPLOYEE_ROLES = ['SERVICE_EMPLOYEE', 'SERVICE_ADMIN', 'SYSTEM_ADMIN'];

    public function __construct(private readonly EmployeeRepository $repository, private readonly Validator $validator)
    {
    }

    public function list(array $query): array
    {
        return $this->repository->paginate(Input::sanitize($query));
    }

    public function register(array $input, Actor $actor): array
    {
        return $actor->type === 'CLIENT_CONTACT'
            ? $this->registerContact($input, $actor)
            : $this->registerEmployee($input, $actor);
    }

    public function suspend(int $id, string $reason, Actor $actor): void
    {
        $this->assertSystemAdministrator($actor);
        $this->assertCanDisable($id, $actor);
        $reason = trim(strip_tags($reason));
        if (
            !$this->repository->suspend(
                $id,
                $reason === '' ? 'SUSPENDED BY SYSTEM ADMINISTRATOR' : $reason,
                $actor->identifier(),
            )
        ) {
            throw new NotFoundException('Employee not found.');
        }
    }

    public function get(int $id, Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        $employee = $this->repository->find($id);
        if ($employee === null) {
            throw new NotFoundException('Employee not found.');
        }
        return $employee;
    }

    public function updateEmployee(int $id, array $input, Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        $existing = $this->get($id, $actor);
        $data = Input::sanitize($input);
        $this->validator->validate($data, [
            'employee_code' => ['required', ['max' => 30]],
            'full_name' => ['required', ['max' => 200]],
            'official_email' => ['required', 'email'],
            'roles' => ['required'],
        ]);
        $roles = $this->roles($data['roles']);
        if ($id === $actor->id && !in_array('SYSTEM_ADMIN', $roles, true)) {
            throw new AuthorizationException('You cannot remove your own system administrator role.');
        }
        if (
            str_contains((string) ($existing['roles'] ?? ''), 'SYSTEM_ADMIN')
            && !in_array('SYSTEM_ADMIN', $roles, true)
            && $this->repository->activeSystemAdministratorCount() <= 1
        ) {
            throw new AuthorizationException('At least one active system administrator must remain.');
        }
        $fields = $this->employeeFields($data);
        $fields['official_email'] = strtolower((string) $fields['official_email']);
        if ($this->repository->emailInUse($fields['official_email'], $id)) {
            throw new ValidationException(['official_email' => ['This employee email is already in use.']]);
        }
        $this->repository->updateEmployee($id, $fields, $roles, $actor->identifier());
        return $this->get($id, $actor);
    }

    public function reactivate(int $id, Actor $actor): void
    {
        $this->assertSystemAdministrator($actor);
        if (!$this->repository->reactivate($id, $actor->identifier())) {
            throw new NotFoundException('Employee not found.');
        }
    }

    public function setPassword(int $id, string $password, Actor $actor): void
    {
        $this->assertSystemAdministrator($actor);
        $this->validator->validate(['password' => $password], ['password' => ['required', ['min' => 12]]]);
        if (
            !$this->repository->setPassword(
                $id,
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                $actor->identifier(),
            )
        ) {
            throw new NotFoundException('Employee account not found.');
        }
    }

    public function deleteEmployee(int $id, Actor $actor): void
    {
        $this->assertSystemAdministrator($actor);
        $this->assertCanDisable($id, $actor);
        if (!$this->repository->softDelete($id, $actor->identifier())) {
            throw new NotFoundException('Employee not found.');
        }
    }

    public function createTeam(array $input, Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        $data = Input::sanitize($input);
        $this->validator->validate($data, ['team_name' => ['required', ['max' => 150]], 'description' => [['max' => 500]]]);
        $id = $this->repository->createTeam($data['team_name'], $data['description'] ?? null, $actor->identifier());
        return ['team_id' => $id, 'team_name' => $data['team_name']];
    }

    public function addTeamMember(int $teamId, array $input, Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        $this->repository->team($teamId);
        $this->validator->validate($input, ['employee_id' => ['required', 'integer']]);
        $this->get((int) $input['employee_id'], $actor);
        $id = $this->repository->addTeamMember(
            $teamId,
            (int) $input['employee_id'],
            filter_var($input['is_team_lead'] ?? false, FILTER_VALIDATE_BOOL),
            $actor->identifier(),
        );
        return ['team_member_id' => $id];
    }

    public function teams(Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        return $this->repository->teams();
    }

    public function updateTeam(int $id, array $input, Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        $this->repository->team($id);
        $data = Input::sanitize($input);
        $this->validator->validate($data, ['team_name' => ['required', ['max' => 150]], 'description' => [['max' => 500]]]);
        $this->repository->updateTeam($id, (string) $data['team_name'], (string) ($data['description'] ?? ''), $actor->identifier());
        return $this->repository->team($id);
    }

    public function removeTeam(int $id, Actor $actor): void
    {
        $this->assertSystemAdministrator($actor);
        $this->repository->team($id);
        $this->repository->removeTeam($id, $actor->identifier());
    }

    public function removeTeamMember(int $id, int $employeeId, Actor $actor): void
    {
        $this->assertSystemAdministrator($actor);
        $this->repository->team($id);
        $this->repository->removeTeamMember($id, $employeeId, $actor->identifier());
    }

    private function registerEmployee(array $input, Actor $actor): array
    {
        $this->assertSystemAdministrator($actor);
        $data = Input::sanitize($input);
        $this->validator->validate($data, [
            'employee_code' => ['required', ['max' => 30]],
            'full_name' => ['required', ['max' => 200]],
            'official_email' => ['required', 'email'],
            'password' => ['required', ['min' => 12]],
            'roles' => ['required'],
        ]);
        $roles = $this->roles($data['roles']);
        $fields = $this->employeeFields($data);
        $fields['official_email'] = strtolower((string) $fields['official_email']);
        if ($this->repository->emailInUse($fields['official_email'])) {
            throw new ValidationException(['official_email' => ['This employee email is already in use.']]);
        }
        $id = $this->repository->createEmployee($fields, $roles, $data['password'], $actor);
        return ['employee_id' => $id];
    }

    private function registerContact(array $input, Actor $actor): array
    {
        // Client contacts are provisioned through the employee-assisted client workflow.
        throw new AuthorizationException('Use client contact management to create portal accounts.');
    }

    private function roles(mixed $roles): array
    {
        $valid = array_values(array_unique(array_intersect((array) $roles, self::EMPLOYEE_ROLES)));
        if ($valid === []) {
            throw new ValidationException(['roles' => ['At least one valid employee role is required.']]);
        }
        return $valid;
    }

    private function employeeFields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip([
            'employee_code', 'full_name', 'official_email', 'mobile_number', 'designation', 'department', 'joining_date',
        ]));
        foreach (['mobile_number', 'designation', 'department', 'joining_date'] as $optional) {
            if (($fields[$optional] ?? null) === '') {
                $fields[$optional] = null;
            }
        }
        return $fields;
    }

    private function assertSystemAdministrator(Actor $actor): void
    {
        if ($actor->type !== 'EMPLOYEE' || !in_array('SYSTEM_ADMIN', $actor->roles, true)) {
            throw new AuthorizationException('Only a system administrator may manage employee accounts.');
        }
    }

    private function assertCanDisable(int $id, Actor $actor): void
    {
        if ($id === $actor->id) {
            throw new AuthorizationException('You cannot suspend or delete your own account.');
        }
        if (
            $this->repository->hasRole($id, 'SYSTEM_ADMIN')
            && $this->repository->activeSystemAdministratorCount() <= 1
        ) {
            throw new AuthorizationException('At least one active system administrator must remain.');
        }
    }
}
