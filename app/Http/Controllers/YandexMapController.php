<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class YandexMapController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user?->hasAnyRole(['manager', 'leader']), 403);

        $canAccessMap = $user->hasRole('manager')
            || ($user->hasRole('leader') && $user->can_use_map);

        abort_unless($canAccessMap, 403);

        return view('map.yandex-embed', [
            'embedUrl' => config('services.yandex_maps.embed_url'),
        ]);
    }
}
