<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Models\Settings;
use Inertia\Inertia;
use Inertia\Response;

class SystemController extends Controller
{
    public function edit(): Response
    {
        $settings = Settings::first();

        return Inertia::render('Admin/Settings/System', [
            'contactWaNumber' => $settings?->contact_wa_number,
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request)
    {
        Settings::updateOrCreate([], $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('System settings updated.')]);

        return redirect()->route('admin.settings');
    }
}
