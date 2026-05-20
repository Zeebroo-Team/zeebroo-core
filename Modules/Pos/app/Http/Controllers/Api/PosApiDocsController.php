<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
class PosApiDocsController extends Controller
{
    public function index(): View
    {
        $specUrl = url('/api/v1/pos/docs/openapi.yaml');
        $spec = $this->openapiSpecArray();

        return view('pos::api.docs', [
            'specUrl' => $specUrl,
            'specJson' => $spec,
            'apiBaseUrl' => url('/api/v1/pos'),
            'appName' => config('app.name', 'Zeebroo'),
        ]);
    }

    public function openapi(): Response
    {
        return response($this->openapiContents(), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function openapiJson(): JsonResponse
    {
        return response()->json($this->openapiSpecArray(), 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function openapiSpecArray(): array
    {
        $jsonPath = module_path('Pos', 'docs/openapi.json');
        $contents = file_get_contents($jsonPath);
        if ($contents === false) {
            return [];
        }

        $spec = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $serverUrl = rtrim(url('/api/v1/pos'), '/');
        if (isset($spec['servers'][0]) && is_array($spec['servers'][0])) {
            $spec['servers'][0]['url'] = $serverUrl;
        }

        return $spec;
    }

    private function openapiContents(): string
    {
        $contents = file_get_contents(module_path('Pos', 'docs/openapi.yaml'));
        $serverUrl = rtrim(url('/api/v1/pos'), '/');

        return (string) preg_replace(
            '/- url: \/api\/v1\/pos/m',
            '- url: '.$serverUrl,
            $contents,
            1,
        );
    }

    public function readme(): Response
    {
        $path = module_path('Pos', 'docs/API.md');

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
        ]);
    }
}
