<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AnalyticsRepository;
use App\Utils\Input;

final class AnalyticsService
{
    public function __construct(private readonly AnalyticsRepository $repository)
    {
    }

    public function dashboard(?string $from, ?string $to): array
    {
        $end = $this->date($to, gmdate('Y-m-d'));
        $start = $this->date($from, gmdate('Y-m-d', strtotime('-30 days')));
        return [
            'range' => ['from' => $start, 'to' => $end],
            'summary' => $this->repository->summary($start, $end),
            'team_performance' => $this->repository->teamPerformance($start, $end),
        ];
    }

    public function serviceLedger(array $query): array
    {
        $end = $this->date(isset($query['to']) ? (string) $query['to'] : null, gmdate('Y-m-d'));
        $start = $this->date(
            isset($query['from']) ? (string) $query['from'] : null,
            gmdate('Y-m-01'),
        );
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        $status = strtoupper(trim((string) ($query['status'] ?? '')));
        if (!in_array($status, ['', 'OPEN', 'ACCEPTED', 'COMPLETED', 'DECLINED', 'PENDING'], true)) {
            $status = '';
        }
        return $this->repository->serviceLedger([
            'from' => $start,
            'to' => $end,
            'status' => $status,
            'client_id' => $this->optionalId($query['client_id'] ?? null),
            'location_id' => $this->optionalId($query['location_id'] ?? null),
            'employee_id' => $this->optionalId($query['employee_id'] ?? null),
            'search' => trim(strip_tags((string) ($query['search'] ?? ''))),
            'page' => Input::positiveInt($query['page'] ?? null, 1),
            'limit' => min(500, Input::positiveInt($query['limit'] ?? null, 100)),
        ]);
    }

    private function date(?string $value, string $default): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        return $date === false ? $default : $date->format('Y-m-d');
    }

    private function optionalId(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }
}
