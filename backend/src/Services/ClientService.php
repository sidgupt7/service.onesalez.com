<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Actor;
use App\Repositories\ClientRepository;
use App\Repositories\TicketRepository;
use App\Utils\Input;
use App\Utils\Validator;

final class ClientService
{
    private const FIELDS = ['client_code', 'legal_name', 'display_name', 'gstin', 'pan', 'primary_email', 'primary_phone', 'website_url', 'notes', 'is_active'];
    private const LOCATION_FIELDS = ['location_code', 'location_name', 'location_type', 'address_line_1', 'address_line_2', 'landmark', 'city', 'district', 'state_name', 'postal_code', 'gstin', 'email', 'phone'];
    private const ADMIN_FIELDS = ['full_name', 'designation', 'email', 'mobile_number'];
    private const CONTACT_FIELDS = ['full_name', 'designation', 'email', 'mobile_number', 'alternate_number', 'has_all_locations', 'is_primary_contact', 'role_code'];

    public function __construct(private readonly ClientRepository $repository, private readonly Validator $validator, private readonly TicketRepository $tickets)
    {
    }

    public function list(array $query, Actor $actor): array
    {
        if ($actor->type === 'CLIENT_CONTACT') {
            return ['items' => [$this->get($actor->clientId ?? 0, $actor)], 'total' => 1, 'page' => 1, 'limit' => 1];
        }
        return $this->repository->paginate(Input::sanitize($query));
    }

    public function get(int $id, Actor $actor): array
    {
        $this->assertClientScope($id, $actor);
        $client = $this->repository->find($id) ?? throw new NotFoundException('Client not found.');
        if ($actor->type === 'CLIENT_CONTACT') {
            $client['contacts'] = array_values(array_filter($client['contacts'], static fn (array $contact): bool => (int) $contact['contact_id'] === $actor->id));
            $contact = $client['contacts'][0] ?? null;
            $client['locations'] = array_values(array_filter($client['locations'], static fn (array $location): bool =>
                $contact !== null && (bool) $location['is_active'] && ((bool) $contact['has_all_locations'] || in_array((int) $location['location_id'], $contact['location_ids'], true))));
            unset($client['notes']);
        }
        return $client;
    }

    public function create(array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $data = Input::sanitize($input);
        $data = $this->uppercase($data, ['primary_email', 'website_url']);
        if (isset($data['primary_email'])) {
            $data['primary_email'] = strtolower((string) $data['primary_email']);
        }
        $this->validator->validate($data, [
            'client_code' => ['required', ['max' => 30]],
            'legal_name' => ['required', ['max' => 200]],
            'primary_email' => ['email'],
        ]);
        $id = $this->repository->create(array_intersect_key($data, array_flip(self::FIELDS)), $actor->identifier());
        return $this->get($id, $actor);
    }

    public function onboard(array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $data = Input::sanitize($input);
        $client = is_array($data['client'] ?? null) ? $data['client'] : [];
        $location = is_array($data['location'] ?? null) ? $data['location'] : [];
        $administrator = is_array($data['administrator'] ?? null) ? $data['administrator'] : [];
        $client = $this->uppercase($client, ['primary_email', 'website_url']);
        $location = $this->uppercase($location, ['email']);
        $administrator = $this->uppercase($administrator, ['email', 'password']);
        if (isset($administrator['email'])) {
            $administrator['email'] = strtolower((string) $administrator['email']);
        }

        $this->validateOnboarding($client, $location, $administrator);
        $password = (string) ($administrator['password'] ?? '');
        $conflict = $this->repository->onboardingConflict(
            (string) $client['client_code'],
            isset($client['gstin']) ? (string) $client['gstin'] : null,
            (string) $administrator['email'],
        );
        if ($conflict !== null) {
            throw new ValidationException([$conflict => ['This value is already in use.']]);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        unset($administrator['password']);
        $client['primary_email'] ??= $administrator['email'];
        $client['display_name'] = $client['display_name'] ?? $client['legal_name'];

        $id = $this->repository->onboard(
            array_intersect_key($client, array_flip(self::FIELDS)),
            array_intersect_key($location, array_flip(self::LOCATION_FIELDS)),
            array_intersect_key($administrator, array_flip(self::ADMIN_FIELDS)),
            $passwordHash,
            $actor->identifier(),
        );
        return $this->get($id, $actor);
    }

    public function update(int $id, array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $this->get($id, $actor);
        $data = array_intersect_key($this->uppercase(Input::sanitize($input), ['primary_email', 'website_url']), array_flip(self::FIELDS));
        if (isset($data['primary_email'])) {
            $data['primary_email'] = strtolower((string) $data['primary_email']);
        }
        $this->validator->validate($data, ['primary_email' => ['email']]);
        $this->repository->update($id, $data, $actor->identifier());
        return $this->get($id, $actor);
    }

    public function delete(int $id, Actor $actor): void
    {
        $this->assertEmployee($actor);
        if (!$this->repository->softDelete($id, $actor->identifier())) {
            throw new NotFoundException('Client not found.');
        }
    }

    public function setActive(int $id, bool $active, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $this->get($id, $actor);
        $this->repository->setClientActive($id, $active, $actor->identifier());
        return $this->get($id, $actor);
    }

    public function createLocation(int $clientId, array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $this->get($clientId, $actor);
        $data = array_intersect_key($this->uppercase(Input::sanitize($input), ['email']), array_flip(self::LOCATION_FIELDS));
        $this->validateLocation($data);
        $this->repository->createLocation($clientId, $data, $actor->identifier());
        return $this->get($clientId, $actor);
    }

    public function updateLocation(int $clientId, int $locationId, array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $client = $this->get($clientId, $actor);
        $this->assertNestedExists($client['locations'], 'location_id', $locationId, 'Client site not found.');
        $data = array_intersect_key($this->uppercase(Input::sanitize($input), ['email']), array_flip(self::LOCATION_FIELDS));
        $this->validateLocation($data);
        $this->repository->updateLocation($clientId, $locationId, $data, $actor->identifier());
        return $this->get($clientId, $actor);
    }

    public function setLocationActive(int $clientId, int $locationId, bool $active, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $client = $this->get($clientId, $actor);
        $this->assertNestedExists($client['locations'], 'location_id', $locationId, 'Client site not found.');
        $this->repository->setLocationActive($clientId, $locationId, $active, $actor->identifier());
        return $this->get($clientId, $actor);
    }

    public function createContact(int $clientId, array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $this->get($clientId, $actor);
        [$data, $locationIds, $portalEnabled, $passwordHash] = $this->contactData(
            $clientId,
            $input,
            $actor,
            true,
        );
        if ($this->repository->contactEmailInUse((string) $data['email'])) {
            throw new ValidationException(['email' => ['This client contact email is already in use.']]);
        }
        $this->repository->createContact(
            $clientId,
            $data,
            $locationIds,
            $portalEnabled ? $passwordHash : null,
            $actor->identifier(),
        );
        return $this->get($clientId, $actor);
    }

    public function updateContact(int $clientId, int $contactId, array $input, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $client = $this->get($clientId, $actor);
        $contact = $this->nestedItem($client['contacts'], 'contact_id', $contactId, 'Client contact not found.');
        [$data, $locationIds, $portalEnabled, $passwordHash] = $this->contactData(
            $clientId,
            $input,
            $actor,
            ($contact['account_status'] ?? null) === null,
        );
        if ($this->repository->contactEmailInUse((string) $data['email'], $contactId)) {
            throw new ValidationException(['email' => ['This client contact email is already in use.']]);
        }
        $this->repository->updateContact(
            $clientId,
            $contactId,
            $data,
            $locationIds,
            $portalEnabled,
            $passwordHash,
            $actor->identifier(),
        );
        return $this->get($clientId, $actor);
    }

    public function setContactActive(int $clientId, int $contactId, bool $active, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $client = $this->get($clientId, $actor);
        $this->assertNestedExists($client['contacts'], 'contact_id', $contactId, 'Client contact not found.');
        $this->repository->setContactActive($clientId, $contactId, $active, $actor->identifier());
        return $this->get($clientId, $actor);
    }

    public function history(int $id, array $query, Actor $actor): array
    {
        $this->get($id, $actor);
        return $this->tickets->paginate(array_merge($query, ['client_id' => $id]), $actor);
    }

    private function assertClientScope(int $id, Actor $actor): void
    {
        if ($actor->type === 'CLIENT_CONTACT' && $actor->clientId !== $id) {
            throw new AuthorizationException();
        }
    }

    public function removeLocation(int $clientId, int $locationId, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $client = $this->get($clientId, $actor);
        $this->assertNestedExists($client['locations'], 'location_id', $locationId, 'Client site not found.');
        $this->repository->removeLocation($clientId, $locationId, $actor->identifier());
        return $this->get($clientId, $actor);
    }

    public function removeContact(int $clientId, int $contactId, Actor $actor): array
    {
        $this->assertEmployee($actor);
        $client = $this->get($clientId, $actor);
        $this->assertNestedExists($client['contacts'], 'contact_id', $contactId, 'Client contact not found.');
        $this->repository->removeContact($clientId, $contactId, $actor->identifier());
        return $this->get($clientId, $actor);
    }

    private function validateOnboarding(array $client, array $location, array $administrator): void
    {
        $this->validator->validate($client, [
            'client_code' => ['required', ['max' => 30]],
            'legal_name' => ['required', ['max' => 200]],
            'gstin' => [['max' => 15]],
            'primary_email' => ['email'],
        ]);
        $this->validator->validate($location, [
            'location_code' => ['required', ['max' => 30]],
            'location_name' => ['required', ['max' => 200]],
            'location_type' => ['required', ['in' => ['HEAD_OFFICE', 'BRANCH', 'WAREHOUSE', 'OTHER']]],
            'address_line_1' => ['required', ['max' => 250]],
            'city' => ['required', ['max' => 100]],
            'state_name' => ['required', ['max' => 100]],
            'postal_code' => ['required', ['min' => 6], ['max' => 10]],
        ]);
        $this->validator->validate($administrator, [
            'full_name' => ['required', ['max' => 200]],
            'email' => ['required', 'email'],
            'mobile_number' => ['required', ['max' => 20]],
            'password' => ['required', ['min' => 12]],
        ]);
    }

    private function validateLocation(array $location): void
    {
        $this->validator->validate($location, [
            'location_code' => ['required', ['max' => 30]],
            'location_name' => ['required', ['max' => 200]],
            'location_type' => ['required', ['in' => ['HEAD_OFFICE', 'BRANCH', 'WAREHOUSE', 'OTHER']]],
            'address_line_1' => ['required', ['max' => 250]],
            'city' => ['required', ['max' => 100]],
            'state_name' => ['required', ['max' => 100]],
            'postal_code' => ['required', ['min' => 6], ['max' => 10]],
            'email' => ['email'],
        ]);
    }

    private function contactData(int $clientId, array $input, Actor $actor, bool $requiresInitialPassword): array
    {
        $sanitized = Input::sanitize($input);
        $locationIds = is_array($sanitized['location_ids'] ?? null) ? $sanitized['location_ids'] : [];
        $portalEnabled = filter_var($sanitized['portal_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $password = (string) ($sanitized['password'] ?? '');
        $data = array_intersect_key($this->uppercase($sanitized, ['email']), array_flip(self::CONTACT_FIELDS));
        if (isset($data['email'])) {
            $data['email'] = strtolower((string) $data['email']);
        }
        $data['has_all_locations'] = filter_var($data['has_all_locations'] ?? false, FILTER_VALIDATE_BOOL);
        $data['is_primary_contact'] = filter_var($data['is_primary_contact'] ?? false, FILTER_VALIDATE_BOOL);
        $this->validator->validate($data, [
            'full_name' => ['required', ['max' => 200]],
            'email' => ['required', 'email'],
            'mobile_number' => ['required', ['max' => 20]],
            'role_code' => ['required', ['in' => ['SYSTEM_OPERATOR', 'END_USER', 'CLIENT_ADMIN', 'OWNER']]],
        ]);
        if ($data['role_code'] === 'CLIENT_ADMIN' && !in_array('SYSTEM_ADMIN', $actor->roles, true)) {
            throw new AuthorizationException('Only a system administrator may assign the client administrator role.');
        }
        if (!$data['has_all_locations'] && !$this->repository->locationsBelongToClient($clientId, $locationIds)) {
            throw new ValidationException(['location_ids' => ['One or more selected sites do not belong to this client.']]);
        }
        if ($portalEnabled && $requiresInitialPassword && $password === '') {
            throw new ValidationException(['password' => ['A temporary password is required to enable portal login.']]);
        }
        if ($password !== '' && mb_strlen($password) < 12) {
            throw new ValidationException(['password' => ['Password must contain at least 12 characters.']]);
        }
        return [
            $data,
            $data['has_all_locations'] ? [] : array_map('intval', $locationIds),
            $portalEnabled,
            $password === '' ? null : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        ];
    }

    private function uppercase(array $data, array $except = []): array
    {
        foreach ($data as $field => $value) {
            if (is_string($value) && !in_array($field, $except, true)) {
                $data[$field] = mb_strtoupper($value);
            }
        }
        return $data;
    }

    private function assertEmployee(Actor $actor): void
    {
        if ($actor->type !== 'EMPLOYEE') {
            throw new AuthorizationException('Client maintenance is available to employees only.');
        }
    }

    private function assertNestedExists(array $items, string $key, int $id, string $message): void
    {
        foreach ($items as $item) {
            if ((int) $item[$key] === $id) {
                return;
            }
        }
        throw new NotFoundException($message);
    }

    private function nestedItem(array $items, string $key, int $id, string $message): array
    {
        foreach ($items as $item) {
            if ((int) $item[$key] === $id) {
                return $item;
            }
        }
        throw new NotFoundException($message);
    }
}
