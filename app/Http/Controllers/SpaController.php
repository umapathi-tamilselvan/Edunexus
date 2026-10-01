<?php

namespace App\Http\Controllers;

use App\Support\OrganizationContext;
use Illuminate\Contracts\View\View;

/**
 * Serves the Vue SPA shell. All data comes from the API.
 */
class SpaController extends Controller
{
    public function __invoke(OrganizationContext $organization): View
    {
        return view('app', [
            'title' => $organization->name(),
            'config' => [
                'name' => $organization->name(),
                'code' => $organization->code(),
                'type' => $organization->type()->value,
            ],
        ]);
    }
}
