<?php

namespace Modules\AppConnection\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\AppConnection\Models\AppRelease;

class AppReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $app = $request->query('app');
        $app = is_string($app) && array_key_exists($app, AppRelease::APPS) ? $app : AppRelease::APP_MAIN;

        $releases = AppRelease::where('app', $app)->orderByDesc('release_date')->orderByDesc('id')->get();
        $counts   = AppRelease::selectRaw('app, count(*) as total')->groupBy('app')->pluck('total', 'app');

        return view('appconnection::admin.releases.index', [
            'releases' => $releases,
            'app'      => $app,
            'apps'     => AppRelease::APPS,
            'counts'   => $counts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRelease($request);

        if (!empty($data['is_latest'])) {
            AppRelease::where('app', $data['app'])->where('channel', $data['channel'])->update(['is_latest' => false]);
        }

        $release = AppRelease::create($data);

        return redirect()->route('admin.releases.index', ['app' => $release->app])
            ->with('success', AppRelease::APPS[$release->app] . ' v' . $release->version . ' published.');
    }

    public function update(Request $request, AppRelease $release): RedirectResponse
    {
        $data = $this->validateRelease($request, $release);

        if (!empty($data['is_latest'])) {
            AppRelease::where('app', $data['app'])->where('channel', $data['channel'])
                ->where('id', '!=', $release->id)->update(['is_latest' => false]);
        }

        $release->update($data);

        return redirect()->route('admin.releases.index', ['app' => $release->app])
            ->with('success', AppRelease::APPS[$release->app] . ' v' . $release->version . ' updated.');
    }

    private function validateRelease(Request $request, ?AppRelease $release = null): array
    {
        $data = $request->validate([
            'app'          => ['required', Rule::in(array_keys(AppRelease::APPS))],
            'version'      => [
                'required', 'string', 'max:32',
                Rule::unique('app_releases', 'version')->where('app', $request->input('app'))->ignore($release?->id),
            ],
            'release_date' => ['required', 'date'],
            'channel'      => ['required', 'in:stable,beta,alpha,rc'],
            'is_latest'    => ['boolean'],
            'notes'        => ['nullable', 'string'],
            'windows_url'  => ['nullable', 'url', 'max:512'],
            'macos_url'    => ['nullable', 'url', 'max:512'],
            'linux_url'    => ['nullable', 'url', 'max:512'],
        ]);

        // Unchecked checkbox isn't submitted — treat as false so edits can un-mark latest
        $data['is_latest'] = !empty($data['is_latest']);

        // Parse notes textarea → array
        $data['notes'] = collect(explode("\n", $data['notes'] ?? ''))
            ->map(fn($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        return $data;
    }

    public function setLatest(AppRelease $release): RedirectResponse
    {
        AppRelease::where('app', $release->app)->where('channel', $release->channel)->update(['is_latest' => false]);
        $release->update(['is_latest' => true]);

        return redirect()->route('admin.releases.index', ['app' => $release->app])
            ->with('success', AppRelease::APPS[$release->app] . ' v' . $release->version . ' is now the latest on ' . $release->channel . '.');
    }

    public function destroy(AppRelease $release): RedirectResponse
    {
        $app     = $release->app;
        $version = $release->version;
        $release->delete();

        return redirect()->route('admin.releases.index', ['app' => $app])
            ->with('success', AppRelease::APPS[$app] . ' v' . $version . ' deleted.');
    }
}
