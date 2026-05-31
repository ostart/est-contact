<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class YandexMapController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['manager', 'leader']), 403);

        return view('map.yandex-embed', [
            'embedUrl' => config('services.yandex_maps.embed_url'),
        ]);
    }
}
