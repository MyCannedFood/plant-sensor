<?php

namespace App\View\Components;

use App\Models\Alert;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AlertBell extends Component
{
    /**
     * Render the alert bell with the number of unresolved alerts.
     */
    public function render(): View
    {
        return view('components.alert-bell', [
            'count' => Alert::query()->active()->count(),
        ]);
    }
}
