<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'values' => Settings::all(),
            'sections' => collect(Settings::DEFINITIONS)->groupBy(fn ($d) => $d[0], preserveKeys: true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(Settings::rules());

        $values = [];
        foreach (Settings::DEFINITIONS as $key => [, $type]) {
            $values[$key] = match (true) {
                $type === 'bool' => $request->boolean($key),          // an unchecked box is absent from the form
                $type === 'int' => (int) $data[$key],
                default => (string) ($data[$key] ?? ''),
            };
            // A secret is never sent back to the browser; an empty field means "keep the stored key".
            if ($type === 'secret' && $values[$key] === '' && ! $request->boolean($key.'_clear')) {
                unset($values[$key]);
            }
        }

        $changed = Settings::save($values);
        if ($changed) {
            // Field names only: values may be secrets or personal data.
            AuditLog::record('update', 'settings', 'site', 'fields: '.implode(', ', $changed));
        }

        return redirect()->route('admin.settings.edit')->with('status', $changed ? 'Settings saved.' : 'No change.');
    }
}
