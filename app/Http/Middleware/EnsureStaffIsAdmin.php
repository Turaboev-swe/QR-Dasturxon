<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Staff|null $staff */
        $staff = $request->attributes->get('staff');

        if (! $staff?->isAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        return $next($request);
    }
}
