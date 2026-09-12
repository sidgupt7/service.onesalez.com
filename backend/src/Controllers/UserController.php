<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserService;

final class UserController extends Controller
{
    public function __construct(private readonly UserService $service)
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

    public function store(Request $request): Response
    {
        return Response::success($this->service->register($request->body, $this->actor($request)), 201);
    }

    public function show(Request $request, array $parameters): Response
    {
        return Response::success($this->service->get($this->id($parameters), $this->actor($request)));
    }

    public function update(Request $request, array $parameters): Response
    {
        return Response::success($this->service->updateEmployee(
            $this->id($parameters),
            $request->body,
            $this->actor($request),
        ));
    }

    public function suspend(Request $request, array $parameters): Response
    {
        $this->service->suspend(
            $this->id($parameters),
            (string) ($request->body['reason'] ?? ''),
            $this->actor($request),
        );
        return Response::success(['message' => 'Employee suspended.']);
    }

    public function reactivate(Request $request, array $parameters): Response
    {
        $this->service->reactivate($this->id($parameters), $this->actor($request));
        return Response::success(['message' => 'Employee reactivated.']);
    }

    public function setPassword(Request $request, array $parameters): Response
    {
        $this->service->setPassword(
            $this->id($parameters),
            (string) ($request->body['password'] ?? ''),
            $this->actor($request),
        );
        return Response::success(['message' => 'Employee password changed.']);
    }

    public function destroy(Request $request, array $parameters): Response
    {
        $this->service->deleteEmployee($this->id($parameters), $this->actor($request));
        return Response::success(['message' => 'Employee deleted.']);
    }

    public function createTeam(Request $request): Response
    {
        return Response::success($this->service->createTeam($request->body, $this->actor($request)), 201);
    }

    public function teams(Request $request): Response
    {
        return Response::success($this->service->teams($this->actor($request)));
    }

    public function updateTeam(Request $request, array $parameters): Response
    {
        return Response::success($this->service->updateTeam($this->id($parameters, 'team_id'), $request->body, $this->actor($request)));
    }

    public function removeTeam(Request $request, array $parameters): Response
    {
        $this->service->removeTeam($this->id($parameters, 'team_id'), $this->actor($request));
        return Response::success(['message' => 'Team removed.']);
    }

    public function removeTeamMember(Request $request, array $parameters): Response
    {
        $this->service->removeTeamMember($this->id($parameters, 'team_id'), $this->id($parameters, 'employee_id'), $this->actor($request));
        return Response::success(['message' => 'Team member removed.']);
    }

    public function addTeamMember(Request $request, array $parameters): Response
    {
        return Response::success($this->service->addTeamMember(
            $this->id($parameters, 'team_id'),
            $request->body,
            $this->actor($request),
        ), 201);
    }
}
