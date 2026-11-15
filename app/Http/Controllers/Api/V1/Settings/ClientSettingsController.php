<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settings\UpdateSettingsRequest;
use App\Support\OrganizationContext;
use App\Support\Settings\UpdateClientSettings;
use Illuminate\Http\JsonResponse;

/**
 * The client's settings: what the admins may change and what is only shown.
 */
class ClientSettingsController extends Controller
{
    public function __construct(private OrganizationContext $organization) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->organization->allSettings()]);
    }

    public function update(UpdateSettingsRequest $request, UpdateClientSettings $updateSettings): JsonResponse
    {
        $updateSettings->handle($request->validated());

        return response()->json(['data' => $this->organization->allSettings()]);
    }
}
