<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class ApprovalPendingRegistrationResponse implements RegistrationResponse
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->route('approval.pending');
    }
}
