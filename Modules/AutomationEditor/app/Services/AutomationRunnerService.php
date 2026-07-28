<?php

namespace Modules\AutomationEditor\Services;

use Illuminate\Support\Facades\Log;
use Modules\AIBot\Services\GeminiGenerateContentClient;
use Modules\AutomationEditor\Mail\AutomationMail;
use Modules\AutomationEditor\Models\AutomationFlow;
use Modules\AutomationEditor\Models\AutomationNotification;
use Modules\AutomationEditor\Models\AutomationRun;
use Modules\AutomationEditor\Services\WhatsappSenderService;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Product\Models\Product;

class AutomationRunnerService
{
    public function __construct(
        private readonly BusinessMailerService $mailer,
    ) {}

    /**
     * Find all active flows for the business matching the trigger and run them.
     */
    public function dispatch(string $triggerKey, Business $business, array $payload): void
    {
        $flows = AutomationFlow::query()
            ->where('business_id', $business->id)
            ->where('trigger_type', $triggerKey)
            ->where('is_active', true)
            ->get();

        foreach ($flows as $flow) {
            try {
                $this->run($flow, $business, $payload);
            } catch (\Throwable $e) {
                Log::error('AutomationRunner: flow run failed', [
                    'flow_id' => $flow->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function run(AutomationFlow $flow, Business $business, array $payload): void
    {
        $run = AutomationRun::create([
            'flow_id'         => $flow->id,
            'status'          => 'running',
            'trigger_payload' => $payload,
            'result'          => null,
            'error_message'   => null,
        ]);

        try {
            $nodes = $this->extractNodes($flow);

            if (empty($nodes)) {
                throw new \RuntimeException('Flow has no nodes.');
            }

            $result = $this->executeFromTrigger($nodes, $payload, $business);

            $run->update(['status' => 'success', 'result' => $result]);
            $flow->increment('run_count');
            $flow->update(['last_run_at' => now()]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Extract Drawflow nodes from flow_data.
     * Stored as: flow_data.drawflow.drawflow.Home.data
     */
    private function extractNodes(AutomationFlow $flow): array
    {
        $fd = $flow->flow_data ?? [];
        return $fd['drawflow']['drawflow']['Home']['data'] ?? [];
    }

    private function executeFromTrigger(array $nodes, array $payload, Business $business): array
    {
        $triggerNode = null;
        foreach ($nodes as $node) {
            if (($node['data']['type'] ?? '') === 'trigger') {
                $triggerNode = $node;
                break;
            }
        }

        if (!$triggerNode) {
            throw new \RuntimeException('No trigger node found in flow.');
        }

        $log = [];
        $this->traverseNext($triggerNode, $nodes, $payload, $business, $log);
        return $log;
    }

    private function traverseNext(array $currentNode, array $nodes, array $payload, Business $business, array &$log, string $port = 'output_1'): void
    {
        $connections = $currentNode['outputs'][$port]['connections'] ?? [];

        foreach ($connections as $conn) {
            $nextId   = (string) $conn['node'];
            $nextNode = $nodes[$nextId] ?? null;
            if ($nextNode) {
                $this->executeNode($nextNode, $nodes, $payload, $business, $log);
            }
        }
    }

    private function executeNode(array $node, array $nodes, array $payload, Business $business, array &$log): void
    {
        $type   = $node['data']['type']   ?? '';
        $config = $node['data']['config'] ?? [];

        if ($type === 'action') {
            $res    = $this->executeAction($config, $payload, $business);
            $log[]  = ['node_id' => $node['id'], 'type' => $type, 'action' => $config['action'] ?? $config['preset'] ?? '', 'result' => $res];
            $this->traverseNext($node, $nodes, $payload, $business, $log);
        } elseif ($type === 'condition') {
            $passes = $this->evaluateCondition($config, $payload);
            $port   = $passes ? 'output_1' : 'output_2';
            $log[]  = ['node_id' => $node['id'], 'type' => $type, 'passed' => $passes];
            $this->traverseNext($node, $nodes, $payload, $business, $log, $port);
        } elseif ($type === 'delay') {
            // MVP: delays are skipped (async scheduling not yet implemented)
            $log[] = ['node_id' => $node['id'], 'type' => 'delay', 'note' => 'skipped (sync run)'];
            $this->traverseNext($node, $nodes, $payload, $business, $log);
        }
    }

    private function executeAction(array $config, array $payload, Business $business): array
    {
        $action = $config['action'] ?? $config['preset'] ?? '';

        return match ($action) {
            'send_email'      => $this->actionSendEmail($config, $payload, $business),
            'send_webhook'    => $this->actionSendWebhook($config, $payload),
            'create_task'     => $this->actionCreateTask($config, $payload, $business),
            'create_lead'     => $this->actionCreateLead($config, $payload, $business),
            'deduct_stock'    => $this->actionDeductStock($config, $payload, $business),
            'ai_send_email'       => $this->actionAiSendEmail($config, $payload, $business),
            'ai_whatsapp_message' => $this->actionAiWhatsappMessage($config, $payload, $business),
            'send_notification'   => $this->actionSendNotification($config, $payload, $business),
            default           => ['success' => false, 'error' => "Action '{$action}' is not yet implemented."],
        };
    }

    private function actionSendEmail(array $config, array $payload, Business $business): array
    {
        $to      = $this->render($config['to']      ?? '', $payload);
        $subject = $this->render($config['subject'] ?? '', $payload);
        $body    = $this->render($config['body']    ?? '', $payload);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid email address: {$to}"];
        }

        $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        return $this->mailer->send($business, new AutomationMail($subject, $bodyHtml), $to);
    }

    private function actionSendWebhook(array $config, array $payload): array
    {
        $url = $this->render($config['url'] ?? '', $payload);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid webhook URL'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'User-Agent: Socibiz-Automation/1.0'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return ['success' => $code >= 200 && $code < 300, 'http_code' => $code, 'error' => $err ?: null];
    }

    private function actionCreateTask(array $config, array $payload, Business $business): array
    {
        $title   = $this->render($config['title']    ?? 'Automation task', $payload);
        $desc    = $this->render($config['desc']     ?? '', $payload);
        $dueDays = max(0, (int) ($config['due_days'] ?? 1));

        $task = Task::create([
            'business_id' => $business->id,
            'title'       => $title ?: 'Automation task',
            'description' => $desc ?: null,
            'due_at'      => now()->addDays($dueDays),
            'status'      => Task::STATUS_PENDING,
        ]);

        return ['success' => true, 'task_id' => $task->id, 'title' => $task->title];
    }

    private function actionCreateLead(array $config, array $payload, Business $business): array
    {
        $name  = $this->render($config['lead_name']  ?? '', $payload);
        $email = $this->render($config['lead_email'] ?? '', $payload);
        $phone = $this->render($config['lead_phone'] ?? '', $payload);

        if (empty($name)) {
            return ['success' => false, 'error' => 'Lead name is empty after rendering template.'];
        }

        // Use first project for this business, or create none (stage_id nullable)
        $project = Project::where('business_id', $business->id)->orderBy('id')->first();

        $lead = Lead::create([
            'business_id' => $business->id,
            'project_id'  => $project?->id,
            'name'        => $name,
            'email'       => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone'       => $phone ?: null,
            'source'      => 'automation',
        ]);

        return ['success' => true, 'lead_id' => $lead->id, 'name' => $lead->name];
    }

    private function actionDeductStock(array $config, array $payload, Business $business): array
    {
        $sku = trim($this->render($config['product_sku'] ?? '', $payload));
        $qty = (float) $this->render((string) ($config['qty'] ?? '1'), $payload);

        if ($sku === '') {
            return ['success' => false, 'error' => 'Product SKU is empty.'];
        }
        if ($qty <= 0) {
            return ['success' => false, 'error' => 'Quantity must be greater than zero.'];
        }

        $product = Product::where('business_id', $business->id)
            ->where('sku', $sku)
            ->first();

        if (!$product) {
            return ['success' => false, 'error' => "Product with SKU '{$sku}' not found."];
        }

        $before = (float) $product->stock_quantity;
        $after  = max(0.0, round($before - $qty, 3));

        Product::where('id', $product->id)->update(['stock_quantity' => $after]);

        return [
            'success'     => true,
            'product_id'  => $product->id,
            'sku'         => $sku,
            'qty_deducted' => $qty,
            'qty_before'  => $before,
            'qty_after'   => $after,
        ];
    }

    private function actionAiSendEmail(array $config, array $payload, Business $business): array
    {
        $to       = $this->render($config['to']        ?? '', $payload);
        $subject  = $this->render($config['subject']   ?? 'Message from ' . $business->name, $payload);
        $prompt   = $this->render($config['ai_prompt'] ?? '', $payload);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid email address: {$to}"];
        }
        if (empty($prompt)) {
            return ['success' => false, 'error' => 'AI prompt is empty.'];
        }

        $apiKey = (string) config('aibot.gemini.api_key', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'GEMINI_API_KEY is not configured.'];
        }

        $gemini  = app(GeminiGenerateContentClient::class);
        $resp    = $gemini->generate([
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 800],
        ]);

        if (!$resp->successful()) {
            return ['success' => false, 'error' => 'Gemini API error: ' . $resp->status()];
        }

        $generated = $resp->json('candidates.0.content.parts.0.text') ?? '';
        if (empty($generated)) {
            return ['success' => false, 'error' => 'Gemini returned empty content.'];
        }

        $bodyHtml = nl2br(htmlspecialchars($generated, ENT_QUOTES, 'UTF-8'));
        $result   = $this->mailer->send($business, new AutomationMail($subject, $bodyHtml), $to);

        return array_merge($result, ['ai_generated' => true, 'prompt_length' => strlen($prompt)]);
    }

    private function actionAiWhatsappMessage(array $config, array $payload, Business $business): array
    {
        $to     = trim($this->render($config['whatsapp_to']     ?? '', $payload));
        $prompt = trim($this->render($config['whatsapp_prompt'] ?? '', $payload));

        if ($to === '') {
            return ['success' => false, 'error' => 'Recipient phone number is empty.'];
        }
        if ($prompt === '') {
            return ['success' => false, 'error' => 'AI prompt is empty.'];
        }

        $phoneNumberId = (string) scope_setting($business, 'whatsapp_phone_number_id', '');
        $accessToken   = (string) scope_setting($business, 'whatsapp_access_token', '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['success' => false, 'error' => 'WhatsApp credentials not configured (whatsapp_phone_number_id / whatsapp_access_token).'];
        }

        $apiKey = (string) config('aibot.gemini.api_key', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'GEMINI_API_KEY is not configured.'];
        }

        $gemini = app(GeminiGenerateContentClient::class);
        $resp   = $gemini->generate([
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 500],
        ]);

        if (!$resp->successful()) {
            return ['success' => false, 'error' => 'Gemini API error: ' . $resp->status()];
        }

        $generated = $resp->json('candidates.0.content.parts.0.text') ?? '';
        if (empty($generated)) {
            return ['success' => false, 'error' => 'Gemini returned empty content.'];
        }

        // Strip HTML tags — WhatsApp plain-text only
        $message = strip_tags(html_entity_decode($generated, ENT_QUOTES, 'UTF-8'));

        $result = app(WhatsappSenderService::class)->send($to, $message, $phoneNumberId, $accessToken);

        return array_merge($result, ['ai_generated' => true, 'prompt_length' => strlen($prompt)]);
    }

    private function actionSendNotification(array $config, array $payload, Business $business): array
    {
        $title   = $this->render($config['notif_title']   ?? 'Automation Alert', $payload);
        $message = $this->render($config['message']       ?? '', $payload);

        if (empty($message)) {
            return ['success' => false, 'error' => 'Notification message is empty.'];
        }

        $notif = AutomationNotification::create([
            'business_id' => $business->id,
            'flow_id'     => null,
            'title'       => $title ?: null,
            'message'     => $message,
            'payload'     => $payload,
        ]);

        return ['success' => true, 'notification_id' => $notif->id, 'title' => $notif->title];
    }

    private function evaluateCondition(array $config, array $payload): bool
    {
        $field  = $config['field'] ?? '';
        $op     = $config['op']    ?? 'eq';
        $value  = $config['value'] ?? '';
        $actual = $this->dotGet($payload, $field);

        return match ($op) {
            'eq'       => (string) $actual === (string) $value,
            'neq'      => (string) $actual !== (string) $value,
            'gt'       => is_numeric($actual) && is_numeric($value) && (float) $actual >  (float) $value,
            'lt'       => is_numeric($actual) && is_numeric($value) && (float) $actual <  (float) $value,
            'contains' => str_contains((string) $actual, (string) $value),
            default    => false,
        };
    }

    /**
     * Replace {{dot.path}} placeholders with values from the payload.
     */
    private function render(string $template, array $payload): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($m) use ($payload) {
            return (string) ($this->dotGet($payload, trim($m[1])) ?? $m[0]);
        }, $template);
    }

    private function dotGet(array $data, string $path): mixed
    {
        $cursor = $data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return null;
            }
            $cursor = $cursor[$key];
        }
        return $cursor;
    }
}
