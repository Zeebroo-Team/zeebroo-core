<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Auth\Services\LogViewerService;

class AdminLogController extends Controller
{
    private const PER_PAGE = 30;

    private const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    public function __construct(private readonly LogViewerService $logs) {}

    public function index(Request $request): View
    {
        $file = $this->logs->resolve($request->query('file'));
        $level = in_array($request->query('level'), self::LEVELS, true) ? $request->query('level') : null;
        $search = trim((string) $request->query('q', ''));

        $entries = $file ? $this->logs->entries($file, $level, $search ?: null) : [];
        $page = max(1, (int) $request->query('page', 1));

        $paginator = new LengthAwarePaginator(
            array_slice($entries, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($entries),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('auth::admin.logs.index', [
            'files' => $this->logs->files(),
            'file' => $file,
            'level' => $level,
            'search' => $search,
            'levels' => self::LEVELS,
            'entries' => $paginator,
        ]);
    }
}
