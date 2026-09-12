<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AnalyticsService;

final class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $service)
    {
    }

    public function dashboard(Request $request): Response
    {
        return Response::success($this->service->dashboard(
            isset($request->query['from']) ? (string) $request->query['from'] : null,
            isset($request->query['to']) ? (string) $request->query['to'] : null,
            $this->actor($request),
        ));
    }

    public function serviceLedger(Request $request): Response
    {
        return Response::success($this->service->serviceLedger($request->query, $this->actor($request)));
    }
}
