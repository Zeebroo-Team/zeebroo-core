<?php

namespace Modules\AppConnection\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AppConnection\Models\AppRelease;

class AppReleaseController extends Controller
{
    public function index(): View
    {
        $releases = AppRelease::orderByDesc('release_date')->orderByDesc('id')->get();

        return view('appconnection::admin.releases.index', compact('releases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version'      => ['required', 'string', 'max:32'],
            'release_date' => ['required', 'date'],
            'channel'      => ['required', 'in:stable,beta,alpha,rc'],
            'is_latest'    => ['boolean'],
            'notes'        => ['nullable', 'string'],
            'windows_url'  => ['nullable', 'url', 'max:512'],
            'macos_url'    => ['nullable', 'url', 'max:512'],
            'linux_url'    => ['nullable', 'url', 'max:512'],
        ]);

        // Parse notes textarea → array
        $data['notes'] = collect(explode("\n", $data['notes'] ?? ''))
            ->map(fn($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        if (!empty($data['is_latest'])) {
            AppRelease::where('channel', $data['channel'])->update(['is_latest' => false]);
        }

        AppRelease::updateOrCreate(['version' => $data['version']], $data);

        return redirect()->route('admin.releases.index')
            ->with('success', 'Release ' . $data['version'] . ' saved.');
    }

    public function setLatest(AppRelease $release): RedirectResponse
    {
        AppRelease::where('channel', $release->channel)->update(['is_latest' => false]);
        $release->update(['is_latest' => true]);

        return redirect()->route('admin.releases.index')
            ->with('success', 'v' . $release->version . ' is now the latest on ' . $release->channel . '.');
    }

    public function destroy(AppRelease $release): RedirectResponse
    {
        $version = $release->version;
        $release->delete();

        return redirect()->route('admin.releases.index')
            ->with('success', 'Release v' . $version . ' deleted.');
    }
}
