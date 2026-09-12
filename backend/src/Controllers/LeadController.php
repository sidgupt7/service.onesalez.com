<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\LeadService;

final class LeadController extends Controller
{
    public function __construct(private readonly LeadService $service)
    {
    }

    public function index(Request $request): Response
    {
        $result = $this->service->list($request->query);
        if (($request->query['paginated'] ?? '') === '1') {
            return Response::success($result);
        }
        return Response::success($result['items'], 200, array_diff_key($result, ['items' => true]));
    }

    public function show(Request $request, array $parameters): Response
    {
        return Response::success($this->service->get($this->id($parameters)));
    }

    public function convert(Request $request, array $parameters): Response
    {
        return Response::success($this->service->convert($this->id($parameters), $request->body, $this->actor($request)), 201);
    }

    public function store(Request $request): Response
    {
        return Response::success($this->service->create($request->body, $this->actor($request)), 201);
    }

    public function update(Request $request, array $parameters): Response
    {
        return Response::success($this->service->update($this->id($parameters), $request->body, $this->actor($request)));
    }

    public function destroy(Request $request, array $parameters): Response
    {
        $this->service->delete($this->id($parameters), $this->actor($request));
        return Response::success(['message' => 'Lead removed.']);
    }

    public function bulkStatus(Request $request): Response
    {
        return Response::success(['updated' => $this->service->bulkStatus($request->body, $this->actor($request))]);
    }
}
