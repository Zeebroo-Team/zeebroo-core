<?php

namespace Modules\HRManagement\Services;

use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Settings\Services\SettingsService;

/**
 * Business-level HR settings edited from the desktop Settings → HR tab:
 * the weekly working schedule (per-day on/off + hours) and the break periods inside it
 * (built-in lunch break plus user-defined ones such as tea time), each switchable on/off.
 */
class HrWorkSettingsService
{
    public const SETTING_WORK_SCHEDULE = 'hr.work_schedule';

    public const SETTING_BREAKS = 'hr.breaks';

    /** @var list<string> */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const LUNCH_KEY = 'lunch';

    public const MAX_BREAKS = 20;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /** @return array{work_schedule: array<string, array<string, mixed>>, breaks: list<array<string, mixed>>} */
    public function get(Business $business): array
    {
        $stored = $this->settings->get($business, self::SETTING_WORK_SCHEDULE, null);
        $stored = is_array($stored) ? $stored : [];
        $breaks = $this->settings->get($business, self::SETTING_BREAKS, null);

        return [
            'work_schedule' => $this->mergeWithDefaults($stored),
            'breaks'        => is_array($breaks) ? $this->withLunchFirst(array_values($breaks)) : $this->defaultBreaks($stored),
        ];
    }

    /**
     * @param  array{work_schedule?: mixed, breaks?: mixed}  $data  already shape-validated by the controller
     * @return array{work_schedule: array<string, array<string, mixed>>, breaks: list<array<string, mixed>>}
     */
    public function save(Business $business, array $data): array
    {
        $current  = $this->get($business);
        $schedule = array_key_exists('work_schedule', $data)
            ? $this->normalizeSchedule((array) $data['work_schedule'])
            : $current['work_schedule'];
        $breaks = array_key_exists('breaks', $data)
            ? $this->normalizeBreaks((array) $data['breaks'])
            : $current['breaks'];

        $this->assertBreaksFitSchedule($schedule, $breaks);

        $this->settings->set($business, self::SETTING_WORK_SCHEDULE, $schedule);
        $this->settings->set($business, self::SETTING_BREAKS, $breaks);

        return $this->get($business);
    }

    /** @return array<string, array{enabled: bool, start: ?string, end: ?string}> */
    public function defaultSchedule(): array
    {
        $out = [];
        foreach (self::DAYS as $day) {
            $out[$day] = [
                'enabled' => ! in_array($day, ['sat', 'sun'], true),
                'start'   => '09:00',
                'end'     => '17:00',
            ];
        }

        return $out;
    }

    /**
     * Lunch defaults to 12:30–13:30 on weekdays. Older saves kept lunch per day
     * (break_start/break_end on each day) — reuse Monday's times if present.
     *
     * @param  array<string, mixed>  $storedSchedule
     * @return list<array<string, mixed>>
     */
    private function defaultBreaks(array $storedSchedule): array
    {
        $mon = is_array($storedSchedule['mon'] ?? null) ? $storedSchedule['mon'] : [];

        return [[
            'key'     => self::LUNCH_KEY,
            'name'    => 'Lunch break',
            'enabled' => true,
            'start'   => $this->timeOrNull($mon['break_start'] ?? null) ?? '12:30',
            'end'     => $this->timeOrNull($mon['break_end'] ?? null) ?? '13:30',
            'days'    => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]];
    }

    /** @param  array<string, mixed>  $stored */
    private function mergeWithDefaults(array $stored): array
    {
        $out = $this->defaultSchedule();
        foreach (self::DAYS as $day) {
            if (is_array($stored[$day] ?? null)) {
                $out[$day] = array_merge($out[$day], array_intersect_key($stored[$day], $out[$day]));
                $out[$day]['enabled'] = (bool) $out[$day]['enabled'];
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $breaks
     * @return list<array<string, mixed>>
     */
    private function withLunchFirst(array $breaks): array
    {
        $lunch = array_values(array_filter($breaks, fn ($b) => ($b['key'] ?? null) === self::LUNCH_KEY));
        $rest  = array_values(array_filter($breaks, fn ($b) => ($b['key'] ?? null) !== self::LUNCH_KEY));

        return array_merge($lunch !== [] ? [$lunch[0]] : $this->defaultBreaks([]), $rest);
    }

    /** @param  array<string, mixed>  $input */
    private function normalizeSchedule(array $input): array
    {
        $out    = [];
        $errors = [];

        foreach (self::DAYS as $day) {
            $row = is_array($input[$day] ?? null) ? $input[$day] : [];

            $enabled = (bool) ($row['enabled'] ?? false);
            $start   = $this->timeOrNull($row['start'] ?? null);
            $end     = $this->timeOrNull($row['end'] ?? null);

            if ($enabled) {
                if ($start === null || $end === null) {
                    $errors["work_schedule.$day"] = 'Start and end time are required for working days.';
                } elseif ($end <= $start) {
                    $errors["work_schedule.$day"] = 'End time must be after start time.';
                }
            }

            $out[$day] = ['enabled' => $enabled, 'start' => $start, 'end' => $end];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** @param  list<mixed>  $input */
    private function normalizeBreaks(array $input): array
    {
        $out     = [];
        $seen    = [];
        $errors  = [];
        $hasLunch = false;

        foreach (array_values($input) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $isLunch = ($row['key'] ?? null) === self::LUNCH_KEY && ! $hasLunch;
            $name    = $isLunch ? 'Lunch break' : mb_substr(trim((string) ($row['name'] ?? '')), 0, 60);
            $start   = $this->timeOrNull($row['start'] ?? null);
            $end     = $this->timeOrNull($row['end'] ?? null);
            $enabled = (bool) ($row['enabled'] ?? false);
            $days    = array_values(array_intersect(self::DAYS, (array) ($row['days'] ?? [])));

            if ($name === '') {
                $errors["breaks.$i.name"] = 'Break name is required.';
                continue;
            }
            $lower = mb_strtolower($name);
            if (isset($seen[$lower])) {
                $errors["breaks.$i.name"] = "Duplicate break name \"$name\".";
                continue;
            }
            $seen[$lower] = true;

            if ($enabled) {
                if ($start === null || $end === null) {
                    $errors["breaks.$i"] = "$name: start and end time are required.";
                } elseif ($end <= $start) {
                    $errors["breaks.$i"] = "$name: end time must be after start time.";
                } elseif ($days === []) {
                    $errors["breaks.$i"] = "$name: pick at least one day.";
                }
            }

            $hasLunch = $hasLunch || $isLunch;
            $out[] = [
                'key'     => $isLunch ? self::LUNCH_KEY : $this->uniqueKey($name, $out),
                'name'    => $name,
                'enabled' => $enabled,
                'start'   => $start,
                'end'     => $end,
                'days'    => $days,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->withLunchFirst($out);
    }

    /**
     * Every enabled break must sit inside the working hours of each working day it applies to,
     * and enabled breaks may not overlap each other on the same day.
     *
     * @param  array<string, array<string, mixed>>  $schedule
     * @param  list<array<string, mixed>>  $breaks
     */
    private function assertBreaksFitSchedule(array $schedule, array $breaks): void
    {
        $dayNames = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
        $errors   = [];

        foreach (self::DAYS as $day) {
            $d = $schedule[$day];
            if (! $d['enabled']) {
                continue;
            }

            $active = array_values(array_filter($breaks, fn ($b) => $b['enabled'] && in_array($day, $b['days'], true)));
            usort($active, fn ($a, $b) => strcmp($a['start'], $b['start']));

            foreach ($active as $i => $b) {
                if ($b['start'] < $d['start'] || $b['end'] > $d['end']) {
                    $errors["breaks.$day"] = "{$b['name']} ({$b['start']}–{$b['end']}) is outside {$dayNames[$day]} working hours ({$d['start']}–{$d['end']}).";
                    break;
                }
                if ($i > 0 && $b['start'] < $active[$i - 1]['end']) {
                    $errors["breaks.$day"] = "{$active[$i - 1]['name']} and {$b['name']} overlap on {$dayNames[$day]}.";
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function timeOrNull(mixed $v): ?string
    {
        if (! is_string($v) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $v)) {
            return null;
        }

        return $v;
    }

    /** @param  list<array<string, mixed>>  $existing */
    private function uniqueKey(string $name, array $existing): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_')) ?: 'break';
        if ($base === self::LUNCH_KEY) {
            $base = 'lunch_custom';
        }
        $taken = array_column($existing, 'key');
        $key   = $base;
        for ($n = 2; in_array($key, $taken, true); $n++) {
            $key = "{$base}_{$n}";
        }

        return $key;
    }
}
