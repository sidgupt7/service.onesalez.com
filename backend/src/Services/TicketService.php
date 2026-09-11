<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Models\Actor;
use App\Repositories\TicketRepository;
use App\Utils\Input;
use App\Utils\Validator;

final class TicketService
{
    private const PRIORITIES = ['NORMAL', 'HIGH', 'URGENT'];

    public function __construct(private readonly TicketRepository $repository, private readonly Validator $validator)
    {
    }

    public function list(array $query, Actor $actor): array
    {
        return $this->repository->paginate(Input::sanitize($query), $actor->type === 'CLIENT_CONTACT' ? $actor->clientId : null);
    }

    public function get(int $id, Actor $actor): array
    {
        $ticket = $this->repository->find($id) ?? throw new NotFoundException('Ticket not found.');
        $this->assertScope($ticket, $actor);
        if ($actor->type === 'CLIENT_CONTACT') {
            $ticket['messages'] = $this->repository->messages($id, false);
        }
        return $ticket;
    }

    public function create(array $input, Actor $actor): array
    {
        if ($actor->type !== 'CLIENT_CONTACT') {
            throw new AuthorizationException('Tickets must be raised by a client contact.');
        }
        $data = Input::sanitize($input);
        $this->validator->validate($data, [
            'location_id' => ['required', 'integer'],
            'subject' => ['required', ['max' => 250]],
            'issue_description' => ['required'],
            'priority' => [['in' => self::PRIORITIES]],
        ]);
        if (
            !$this->repository->contactCanUseLocation(
                $actor->clientId ?? 0,
                $actor->id,
                (int) $data['location_id'],
            )
        ) {
            throw new AuthorizationException('The selected location is outside this contact’s access scope.');
        }
        $id = $this->repository->create([
            'service_request_number' => $this->requestNumber(),
            'client_id' => $actor->clientId,
            'location_id' => (int) $data['location_id'],
            'reported_by_contact_id' => $actor->id,
            'subject' => $data['subject'],
            'issue_description' => $data['issue_description'],
            'priority' => $data['priority'] ?? 'NORMAL',
        ], $actor->identifier());
        $this->repository->history($id, null, 'OPEN', $actor->type, $actor->id, $actor->identifier());
        return $this->get($id, $actor);
    }

    public function transition(int $id, string $action, array $input, Actor $actor): array
    {
        if ($actor->type !== 'EMPLOYEE') {
            throw new AuthorizationException();
        }
        $note = trim(strip_tags((string) ($input['note'] ?? '')));
        $success = match ($action) {
            'accept' => $this->repository->accept($id, $actor->id, $actor->identifier()),
            'release' => $note !== '' && $this->repository->release($id, $actor->id, $note, $actor->identifier()),
            'complete' => $note !== '' && $this->repository->complete($id, $actor->id, $note, $actor->identifier()),
            'decline' => $actor->can('tickets.decline') && $note !== ''
                && $this->repository->decline($id, $actor->id, $note, $actor->identifier()),
            default => throw new BadRequestException('Unsupported ticket action.'),
        };
        if (!$success) {
            throw new BadRequestException('The ticket state does not permit this action.');
        }
        return $this->get($id, $actor);
    }

    public function priority(int $id, string $priority, Actor $actor): array
    {
        $this->validator->validate(['priority' => $priority], ['priority' => ['required', ['in' => self::PRIORITIES]]]);
        if (!$this->repository->updatePriority($id, $priority, $actor->identifier())) {
            throw new NotFoundException('Ticket not found.');
        }
        return $this->get($id, $actor);
    }

    public function updateDescription(int $id, array $input, Actor $actor): array
    {
        if ($actor->type !== 'CLIENT_CONTACT') {
            throw new AuthorizationException('Only a client contact may change the problem description.');
        }
        $data = Input::sanitize($input);
        $this->validator->validate($data, ['issue_description' => ['required']]);
        if (
            !$this->repository->updateDescription(
                $id,
                $actor->clientId ?? 0,
                $actor->id,
                (string) $data['issue_description'],
                $actor->identifier(),
            )
        ) {
            throw new BadRequestException('The problem description can only be changed while the ticket is open or accepted.');
        }
        return $this->get($id, $actor);
    }

    public function addMessage(int $id, array $input, Actor $actor): array
    {
        $this->get($id, $actor);
        $data = Input::sanitize($input);
        $this->validator->validate($data, ['message' => ['required'], 'is_internal' => []]);
        $internal = $actor->type === 'EMPLOYEE' && filter_var($data['is_internal'] ?? false, FILTER_VALIDATE_BOOL);
        $messageId = $this->repository->addMessage([
            'ticket_id' => $id,
            'author_type' => $actor->type,
            'contact_id' => $actor->type === 'CLIENT_CONTACT' ? $actor->id : null,
            'employee_id' => $actor->type === 'EMPLOYEE' ? $actor->id : null,
            'message' => $data['message'],
            'is_internal' => $internal,
            'created_by' => $actor->identifier(),
        ]);
        return ['message_id' => $messageId];
    }

    public function delete(int $id, Actor $actor): void
    {
        if (!$this->repository->softDelete($id, $actor->identifier())) {
            throw new BadRequestException('Only completed or declined tickets may be removed.');
        }
    }

    private function assertScope(array $ticket, Actor $actor): void
    {
        if ($actor->type === 'CLIENT_CONTACT' && (int) $ticket['client_id'] !== $actor->clientId) {
            throw new AuthorizationException();
        }
    }

    private function requestNumber(): string
    {
        return 'SR-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }
}
