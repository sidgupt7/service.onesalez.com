<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

interface Middleware
{
    public function process(Request $request, callable $next): Response;
}
