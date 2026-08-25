<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAuthController extends Controller
{
    /**
     * Return the identified staff member's own profile. Identity comes
     * entirely from `staff.auth` (Telegram id lookup) — there is nothing
     * to log in or out of.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        return response()->json([
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'role' => $staff->role,
                'restaurant_id' => $staff->restaurant_id,
            ],
        ]);
    }
}
