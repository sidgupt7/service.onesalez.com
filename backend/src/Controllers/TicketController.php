<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\TicketService;

final class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service)
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

    public function transition(Request $request, array $parameters): Response
    {
        return Response::success($this->service->transition(
            $this->id($parameters),
            (string) ($parameters['action'] ?? ''),
            $request->body,
            $this->actor($request),
        ));
    }

    public function priority(Request $request, array $parameters): Response
    {
        return Response::success($this->service->priority(
            $this->id($parameters),
            (string) ($request->body['priority'] ?? ''),
            $this->actor($request),
        ));
    }

    public function addMessage(Request $request, array $parameters): Response
    {
        return Response::success($this->service->addMessage($this->id($parameters), $request->body, $this->actor($request)), 201);
    }

    public function updateDescription(Request $request, array $parameters): Response
    {
        return Response::success($this->service->updateDescription(
            $this->id($parameters),
            $request->body,
            $this->actor($request),
        ));
    }

    public function destroy(Request $request, array $parameters): Response
    {
        $this->service->delete($this->id($parameters), $this->actor($request));
        return Response::success(['message' => 'Ticket removed.']);
    }
}
