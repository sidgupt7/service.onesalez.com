<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\BadRequestException;
use App\Http\Request;
use App\Http\Response;
use App\Services\ClientService;

final class ClientController extends Controller
{
    public function removeLocation(Request $request, array $parameters): Response
    {
        return Response::success($this->service->removeLocation($this->id($parameters), $this->id($parameters, 'location_id'), $this->actor($request)));
    }

    public function removeContact(Request $request, array $parameters): Response
    {
        return Response::success($this->service->removeContact($this->id($parameters), $this->id($parameters, 'contact_id'), $this->actor($request)));
    }
    public function __construct(private readonly ClientService $service)
    {
    }

    public function index(Request $request): Response
    {
        $result = $this->service->list($request->query, $this->actor($request));
        if (($request->query['paginated'] ?? '') === '1') {
            return Response::success($result);
        }
        return Response::success($result['items'], 200, array_diff_key($result, ['items' => true]));
    }

    public function show(Request $request, array $parameters): Response
    {
        return Response::success($this->service->get($this->id($parameters), $this->actor($request)));
    }

    public function store(Request $request): Response
    {
        return Response::success($this->service->create($request->body, $this->actor($request)), 201);
    }

    public function onboard(Request $request): Response
    {
        return Response::success($this->service->onboard($request->body, $this->actor($request)), 201);
    }

    public function update(Request $request, array $parameters): Response
    {
        return Response::success($this->service->update($this->id($parameters), $request->body, $this->actor($request)));
    }

    public function destroy(Request $request, array $parameters): Response
    {
        $this->service->delete($this->id($parameters), $this->actor($request));
        return Response::success(['message' => 'Client removed.']);
    }

    public function serviceHistory(Request $request, array $parameters): Response
    {
        $result = $this->service->history($this->id($parameters), $request->query, $this->actor($request));
        return Response::success($result['items'], 200, array_diff_key($result, ['items' => true]));
    }

    public function status(Request $request, array $parameters): Response
    {
        return Response::success($this->service->setActive(
            $this->id($parameters),
            $this->activeFromAction($parameters),
            $this->actor($request),
        ));
    }

    public function createLocation(Request $request, array $parameters): Response
    {
        return Response::success($this->service->createLocation(
            $this->id($parameters),
            $request->body,
            $this->actor($request),
        ), 201);
    }

    public function updateLocation(Request $request, array $parameters): Response
    {
        return Response::success($this->service->updateLocation(
            $this->id($parameters),
            $this->id($parameters, 'location_id'),
            $request->body,
            $this->actor($request),
        ));
    }

    public function locationStatus(Request $request, array $parameters): Response
    {
        return Response::success($this->service->setLocationActive(
            $this->id($parameters),
            $this->id($parameters, 'location_id'),
            $this->activeFromAction($parameters),
            $this->actor($request),
        ));
    }

    public function createContact(Request $request, array $parameters): Response
    {
        return Response::success($this->service->createContact(
            $this->id($parameters),
            $request->body,
            $this->actor($request),
        ), 201);
    }

    public function updateContact(Request $request, array $parameters): Response
    {
        return Response::success($this->service->updateContact(
            $this->id($parameters),
            $this->id($parameters, 'contact_id'),
            $request->body,
            $this->actor($request),
        ));
    }

    public function contactStatus(Request $request, array $parameters): Response
    {
        return Response::success($this->service->setContactActive(
            $this->id($parameters),
            $this->id($parameters, 'contact_id'),
            $this->activeFromAction($parameters),
            $this->actor($request),
        ));
    }

    private function activeFromAction(array $parameters): bool
    {
        return match ($parameters['action'] ?? '') {
            'suspend' => false,
            'reactivate' => true,
            default => throw new BadRequestException('Unsupported status action.'),
        };
    }
}
