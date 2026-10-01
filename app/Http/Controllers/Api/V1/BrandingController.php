<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;

/**
 * Public institution branding for the SPA's login screen.
 */
class BrandingController extends Controller
{
    public function __invoke(OrganizationContext $organization): JsonResponse
    {
        return response()->json([
            'data' => [
                'code' => $organization->code(),
                'name' => $organization->name(),
                'type' => $organization->type()->value,
                'terminology' => $organization->type()->terminology(),
                'logo_url' => $organization->setting('branding.logo_url'),
                'theme' => $organization->setting('branding.theme'),
            ],
        ]);
    }
}
