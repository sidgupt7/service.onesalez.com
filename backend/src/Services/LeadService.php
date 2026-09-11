<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Models\Actor;
use App\Repositories\LeadRepository;
use App\Utils\Input;
use App\Utils\Validator;

final class LeadService
{
    private const STATUSES = ['NEW', 'CONTACTED', 'QUALIFIED', 'WON', 'LOST'];
    private const FIELDS = ['business_name', 'contact_name', 'email', 'phone', 'source', 'status', 'assigned_employee_id', 'notes'];

    public function __construct(private readonly LeadRepository $repository, private readonly Validator $validator)
    {
    }

    public function list(array $query): array
    {
        return $this->repository->paginate(Input::sanitize($query));
    }

    public function get(int $id): array
    {
        return $this->repository->find($id) ?? throw new NotFoundException('Lead not found.');
    }

    public function create(array $input, Actor $actor): array
    {
        $data = Input::sanitize($input);
        $this->validator->validate($data, [
            'business_name' => ['required', ['max' => 200]],
            'contact_name' => ['required', ['max' => 200]],
            'email' => ['required', 'email'],
            'status' => [['in' => self::STATUSES]],
        ]);
        $id = $this->repository->create(array_intersect_key($data, array_flip(self::FIELDS)), $actor->identifier());
        return $this->get($id);
    }

    public function update(int $id, array $input, Actor $actor): array
    {
        $this->get($id);
        $data = array_intersect_key(Input::sanitize($input), array_flip(self::FIELDS));
        $this->validator->validate($data, ['email' => ['email'], 'status' => [['in' => self::STATUSES]]]);
        $this->repository->update($id, $data, $actor->identifier());
        return $this->get($id);
    }

    public function delete(int $id, Actor $actor): void
    {
        if (!$this->repository->softDelete($id, $actor->identifier())) {
            throw new NotFoundException('Lead not found.');
        }
    }

    public function bulkStatus(array $input, Actor $actor): int
    {
        $this->validator->validate($input, ['ids' => ['required'], 'status' => ['required', ['in' => self::STATUSES]]]);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $input['ids']))));
        if ($ids === []) {
            throw new \App\Exceptions\ValidationException(['ids' => ['At least one valid lead ID is required.']]);
        }
        return $this->repository->bulkStatus($ids, $input['status'], $actor->identifier());
    }
}
