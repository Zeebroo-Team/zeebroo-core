<?php

namespace Modules\Mail\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Mail\Models\AdminAutomatedEmail;
use Modules\Mail\Services\AutomatedEmailService;
use Throwable;

class AdminAutomatedEmailController extends Controller
{
    public function __construct(private readonly AutomatedEmailService $automated) {}

    public function index(): View
    {
        return view('mail::admin.automated.index', [
            'definitions' => $this->automated->definitions(),
            'emails' => $this->automated->all(),
            'stats' => $this->automated->stats(),
        ]);
    }

    public function edit(string $key): View
    {
        $definition = $this->definitionOr404($key);

        return view('mail::admin.automated.edit', [
            'key' => $key,
            'definition' => $definition,
            'email' => $this->automated->get($key),
            'placeholders' => $this->automated->tagsFor($key),
            'sample' => $this->automated->sampleVars($key),
            'logs' => $this->automated->recentLogs($key),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->definitionOr404($key);

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500000'],
            ...$this->settingsRules($key),
        ]);

        $this->automated->update($key, [
            'is_enabled' => $request->boolean('is_enabled'),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'settings' => $this->castSettings($key, $data['settings'] ?? []),
        ], $request->user());

        return redirect()->route('admin.automated-emails.edit', $key)->with('status', __('Automated email saved.'));
    }

    public function toggle(Request $request, string $key): RedirectResponse
    {
        $definition = $this->definitionOr404($key);
        $email = $this->automated->toggle($key, $request->user());

        return back()->with('status', __(':name is now :state.', [
            'name' => $definition['name'],
            'state' => $email->is_enabled ? __('on') : __('off'),
        ]));
    }

    public function reset(Request $request, string $key): RedirectResponse
    {
        $this->definitionOr404($key);
        $this->automated->resetTemplate($key, $request->user());

        return redirect()->route('admin.automated-emails.edit', $key)->with('status', __('Template restored to the default design.'));
    }

    public function sendTest(Request $request, string $key): JsonResponse
    {
        $this->definitionOr404($key);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500000'],
        ]);

        try {
            $this->automated->sendTest($key, $data['subject'], $data['body'], $request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => __('Could not send the test email: :error', ['error' => $e->getMessage()])], 422);
        }

        return response()->json(['success' => true, 'message' => __('Test email sent to :email.', ['email' => $request->user()->email])]);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function definitionOr404(string $key): array
    {
        return $this->automated->definition($key) ?? abort(404);
    }

    private function settingsRules(string $key): array
    {
        return match ($key) {
            AdminAutomatedEmail::PASSWORD_RESET => [
                'settings.otp_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            ],
            AdminAutomatedEmail::INACTIVITY => [
                'settings.days' => ['required', 'integer', 'min:1', 'max:365'],
                'settings.send_hour' => ['required', 'integer', 'min:0', 'max:23'],
            ],
            AdminAutomatedEmail::REPORT => [
                'settings.frequency' => ['required', Rule::in(array_keys(AutomatedEmailService::FREQUENCIES))],
                'settings.day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
                'settings.send_hour' => ['required', 'integer', 'min:0', 'max:23'],
                'settings.skip_empty' => ['nullable', 'boolean'],
            ],
            AdminAutomatedEmail::NEW_RELEASE => [
                'settings.channels' => ['required', 'array', 'min:1'],
                'settings.channels.*' => [Rule::in(AutomatedEmailService::RELEASE_CHANNELS)],
            ],
            default => [],
        };
    }

    private function castSettings(string $key, array $settings): array
    {
        return match ($key) {
            AdminAutomatedEmail::PASSWORD_RESET => ['otp_minutes' => (int) $settings['otp_minutes']],
            AdminAutomatedEmail::INACTIVITY => ['days' => (int) $settings['days'], 'send_hour' => (int) $settings['send_hour']],
            AdminAutomatedEmail::REPORT => [
                'frequency' => $settings['frequency'],
                'day_of_week' => (int) $settings['day_of_week'],
                'send_hour' => (int) $settings['send_hour'],
                'skip_empty' => (bool) ($settings['skip_empty'] ?? false),
            ],
            AdminAutomatedEmail::NEW_RELEASE => ['channels' => array_values(array_unique($settings['channels']))],
            default => [],
        };
    }
}
