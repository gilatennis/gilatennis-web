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
            'transactionFee' => (int) ($settings?->transaction_fee ?? 0),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request)
    {
        $data = $request->validated();
        $data['contact_wa_number'] = $data['contact_wa_number'] !== null && $data['contact_wa_number'] !== ''
            ? $data['contact_wa_number']
            : null;
        $data['transaction_fee'] = (int) ($data['transaction_fee'] ?? 0);

        Settings::updateOrCreate([], $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('System settings updated.')]);

        return redirect()->route('admin.settings');
    }
}
