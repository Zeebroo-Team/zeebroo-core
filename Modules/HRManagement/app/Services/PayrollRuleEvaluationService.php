<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Modules\HRManagement\Models\PayrollRule;

final class PayrollRuleEvaluationService
{
    /**
     * @param  array<string, float|int|string|null>  $context
     * @return array{amount: float, quantity: float, rate: float, meta: array<string, mixed>, errors: list<string>}
     */
    public function evaluate(PayrollRule $rule, array $context): array
    {
        $cfg = is_array($rule->config_json) ? $rule->config_json : [];
        $errors = [];
        $quantity = 1.0;
        $rate = 0.0;
        $amount = 0.0;
        $meta = [];

        if (! $rule->is_active) {
            return [
                'amount' => 0.0,
                'quantity' => $quantity,
                'rate' => $rate,
                'meta' => ['skipped' => true],
                'errors' => [],
            ];
        }

        $mode = (string) $rule->calculation_mode;
        if ($mode === PayrollRule::MODE_FIXED) {
            $amount = round((float) ($cfg['amount'] ?? 0), 2);
            $rate = $amount;
        } elseif ($mode === PayrollRule::MODE_PERCENTAGE) {
            $percent = (float) ($cfg['percent'] ?? 0);
            $baseField = (string) ($cfg['base_field'] ?? 'basic_salary');
            $baseValue = (float) ($context[$baseField] ?? 0);
            $amount = round(($baseValue * $percent) / 100, 2);
            $rate = $percent;
            $meta['base_field'] = $baseField;
            $meta['base_value'] = round($baseValue, 2);
        } elseif ($mode === PayrollRule::MODE_SLAB) {
            $inputField = (string) ($cfg['input_field'] ?? 'taxable_earnings');
            $slabs = isset($cfg['slabs']) && is_array($cfg['slabs']) ? $cfg['slabs'] : [];
            $input = (float) ($context[$inputField] ?? 0);
            [$amount, $slabMeta] = $this->evaluateSlabs($input, $slabs);
            $rate = 0;
            $meta = [
                'input_field' => $inputField,
                'input_value' => round($input, 2),
                'slab_breakdown' => $slabMeta,
            ];
        } elseif ($mode === PayrollRule::MODE_FORMULA) {
            $flowV1 = isset($cfg['flow_v1']) && is_array($cfg['flow_v1']) ? $cfg['flow_v1'] : null;
            $flowRoot = is_array($flowV1) ? trim((string) ($flowV1['root'] ?? '')) : '';
            $flowNodes = is_array($flowV1) && isset($flowV1['nodes']) && is_array($flowV1['nodes']) ? $flowV1['nodes'] : [];
            $hasFlowGraph = $flowRoot !== '' && $flowNodes !== [];

            if ($hasFlowGraph) {
                $flowResult = $this->evaluateFormulaFlowGraph($flowV1, $context);
                $amount = round($flowResult['value'], 2);
                $errors = array_merge($errors, $flowResult['errors']);
                $meta['flow_v1'] = true;
                $formulaText = trim((string) ($cfg['formula'] ?? ''));
                if ($formulaText !== '') {
                    $meta['formula'] = $formulaText;
                }
            } else {
                $formula = trim((string) ($cfg['formula'] ?? ''));
                if ($formula === '') {
                    $errors[] = 'Missing formula expression.';
                } else {
                    $formulaResult = $this->evaluateFormula($formula, $context);
                    $amount = round($formulaResult['value'], 2);
                    $errors = array_merge($errors, $formulaResult['errors']);
                    $meta['formula'] = $formula;
                }
            }
        } else {
            $errors[] = 'Unsupported calculation mode: '.$mode;
        }

        if ($amount < 0) {
            $amount = round($amount, 2);
        }

        return [
            'amount' => $amount,
            'quantity' => $quantity,
            'rate' => $rate,
            'meta' => $meta,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, mixed>  $slabs
     * @return array{0: float, 1: array<int, array<string, float>>}
     */
    private function evaluateSlabs(float $income, array $slabs): array
    {
        $remaining = max(0, $income);
        $total = 0.0;
        $breakdown = [];

        usort($slabs, static function ($a, $b): int {
            $aFrom = (float) (is_array($a) ? ($a['from'] ?? 0) : 0);
            $bFrom = (float) (is_array($b) ? ($b['from'] ?? 0) : 0);

            return $aFrom <=> $bFrom;
        });

        foreach ($slabs as $slab) {
            if (! is_array($slab)) {
                continue;
            }
            $from = max(0, (float) ($slab['from'] ?? 0));
            $to = isset($slab['to']) && $slab['to'] !== null ? (float) $slab['to'] : null;
            $percent = max(0, (float) ($slab['percent'] ?? 0));
            $fixed = max(0, (float) ($slab['fixed'] ?? 0));

            if ($income <= $from || $remaining <= 0) {
                continue;
            }

            $sliceUpper = $to === null ? $income : min($income, $to);
            $sliceBase = max(0.0, $sliceUpper - $from);
            $sliceAmount = round(($sliceBase * $percent) / 100 + $fixed, 2);
            if ($sliceAmount <= 0 && $sliceBase <= 0) {
                continue;
            }

            $total += $sliceAmount;
            $remaining = max(0, $remaining - $sliceBase);
            $breakdown[] = [
                'from' => $from,
                'to' => $to ?? -1,
                'taxable' => round($sliceBase, 2),
                'percent' => $percent,
                'fixed' => $fixed,
                'amount' => $sliceAmount,
            ];
        }

        return [round($total, 2), $breakdown];
    }

    /**
     * Visual formula graph (flow_v1): context, constant, binary, compare, conditional.
     *
     * @param  array<string, mixed>  $flow
     * @param  array<string, float|int|string|null>  $context
     * @return array{value: float, errors: list<string>}
     */
    private function evaluateFormulaFlowGraph(array $flow, array $context): array
    {
        $nodes = $flow['nodes'] ?? [];
        if (! is_array($nodes)) {
            return ['value' => 0.0, 'errors' => ['Invalid formula flow definition.']];
        }

        $root = trim((string) ($flow['root'] ?? ''));
        if ($root === '' || ! isset($nodes[$root]) || ! is_array($nodes[$root])) {
            return ['value' => 0.0, 'errors' => ['Formula flow root is invalid.']];
        }

        $memo = [];
        $path = [];

        return $this->evaluateFlowGraphNode($root, $nodes, $context, $memo, $path, 0);
    }

    /**
     * @param  array<string, mixed>  $nodes
     * @param  array<string, true>  $path
     * @return array{value: float, errors: list<string>}
     */
    private function evaluateFlowGraphNode(string $id, array $nodes, array $context, array &$memo, array &$path, int $depth): array
    {
        if ($depth > 128) {
            return ['value' => 0.0, 'errors' => ['Formula flow graph is too deep.']];
        }

        if (isset($memo[$id])) {
            return ['value' => $memo[$id], 'errors' => []];
        }

        if (isset($path[$id])) {
            return ['value' => 0.0, 'errors' => ['Cycle detected in formula flow graph.']];
        }

        $path[$id] = true;
        $node = $nodes[$id] ?? null;
        if (! is_array($node)) {
            unset($path[$id]);

            return ['value' => 0.0, 'errors' => ['Missing flow node: '.$id]];
        }

        $type = (string) ($node['type'] ?? '');
        $errors = [];
        $value = 0.0;

        if ($type === 'context') {
            $field = (string) ($node['field'] ?? '');
            $value = $field !== '' ? (float) ($context[$field] ?? 0) : 0.0;
        } elseif ($type === 'constant') {
            $value = (float) ($node['value'] ?? 0);
        } elseif ($type === 'binary') {
            $op = (string) ($node['op'] ?? '+');
            if (! in_array($op, ['+', '-', '*', '/'], true)) {
                $errors[] = 'Unsupported binary op in flow node '.$id.'.';
            } else {
                $leftId = trim((string) ($node['left'] ?? ''));
                $rightId = trim((string) ($node['right'] ?? ''));
                if ($leftId === '' || $rightId === '') {
                    $errors[] = 'Binary node '.$id.' needs left and right inputs.';
                } else {
                    $l = $this->evaluateFlowGraphNode($leftId, $nodes, $context, $memo, $path, $depth + 1);
                    $errors = array_merge($errors, $l['errors']);
                    $r = $this->evaluateFlowGraphNode($rightId, $nodes, $context, $memo, $path, $depth + 1);
                    $errors = array_merge($errors, $r['errors']);
                    if ($errors !== []) {
                        $value = 0.0;
                    } elseif ($op === '/') {
                        $rv = $r['value'];
                        $value = abs($rv) < 0.0000001 ? 0.0 : $l['value'] / $rv;
                    } else {
                        $value = match ($op) {
                            '+' => $l['value'] + $r['value'],
                            '-' => $l['value'] - $r['value'],
                            '*' => $l['value'] * $r['value'],
                            default => 0.0,
                        };
                    }
                }
            }
        } elseif ($type === 'compare') {
            $cop = strtolower((string) ($node['op'] ?? ''));
            if (! in_array($cop, ['gt', 'gte', 'lt', 'lte', 'eq'], true)) {
                $errors[] = 'Unsupported compare op in flow node '.$id.'.';
            } else {
                $leftId = trim((string) ($node['left'] ?? ''));
                $rightId = trim((string) ($node['right'] ?? ''));
                if ($leftId === '' || $rightId === '') {
                    $errors[] = 'Compare node '.$id.' needs left and right inputs.';
                } else {
                    $l = $this->evaluateFlowGraphNode($leftId, $nodes, $context, $memo, $path, $depth + 1);
                    $errors = array_merge($errors, $l['errors']);
                    $r = $this->evaluateFlowGraphNode($rightId, $nodes, $context, $memo, $path, $depth + 1);
                    $errors = array_merge($errors, $r['errors']);
                    if ($errors === []) {
                        $lv = $l['value'];
                        $rv = $r['value'];
                        $ok = match ($cop) {
                            'gt' => $lv > $rv,
                            'gte' => $lv >= $rv,
                            'lt' => $lv < $rv,
                            'lte' => $lv <= $rv,
                            'eq' => abs($lv - $rv) < 0.0000001,
                            default => false,
                        };
                        $value = $ok ? 1.0 : 0.0;
                    }
                }
            }
        } elseif ($type === 'cond') {
            $testId = trim((string) ($node['test'] ?? ''));
            $thenId = trim((string) ($node['then'] ?? ''));
            $elseId = trim((string) ($node['else'] ?? ''));
            if ($testId === '' || $thenId === '' || $elseId === '') {
                $errors[] = 'Condition node '.$id.' needs test, then, and else branches.';
            } else {
                $t = $this->evaluateFlowGraphNode($testId, $nodes, $context, $memo, $path, $depth + 1);
                $errors = array_merge($errors, $t['errors']);
                if ($errors !== []) {
                    $value = 0.0;
                } elseif (abs((float) $t['value']) >= 0.0000001) {
                    $b = $this->evaluateFlowGraphNode($thenId, $nodes, $context, $memo, $path, $depth + 1);
                    $errors = array_merge($errors, $b['errors']);
                    $value = $b['value'];
                } else {
                    $b = $this->evaluateFlowGraphNode($elseId, $nodes, $context, $memo, $path, $depth + 1);
                    $errors = array_merge($errors, $b['errors']);
                    $value = $b['value'];
                }
            }
        } elseif ($type === 'bill') {
            // Reads pre-computed bill metrics injected into context by PayrollComputationService.
            // scope: 'employee' | 'business'
            // metric: 'overdue_total' | 'overdue_count' | 'available_total' | 'available_count'
            $scope  = (string) ($node['scope']  ?? 'employee');
            $metric = (string) ($node['metric'] ?? 'overdue_total');
            if (! in_array($scope,  ['employee', 'business'], true)) {
                $scope = 'employee';
            }
            if (! in_array($metric, ['overdue_total', 'overdue_count', 'available_total', 'available_count'], true)) {
                $metric = 'overdue_total';
            }
            $ctxKey = 'bill_' . $scope . '_' . $metric;
            $value  = (float) ($context[$ctxKey] ?? 0);
        } else {
            $errors[] = 'Unsupported flow node type '.$type.' at '.$id.'.';
        }

        unset($path[$id]);
        if ($errors === []) {
            $memo[$id] = $value;
        }

        return ['value' => $value, 'errors' => $errors];
    }

    /**
     * @param  array<string, float|int|string|null>  $context
     * @return array{value: float, errors: list<string>}
     */
    private function evaluateFormula(string $formula, array $context): array
    {
        $errors = [];
        $allowed = [];
        foreach ($context as $key => $val) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key) !== 1) {
                continue;
            }
            $allowed[(string) $key] = (float) $val;
        }

        $expr = preg_replace_callback(
            '/[A-Za-z_][A-Za-z0-9_]*/',
            static function (array $m) use ($allowed): string {
                $key = $m[0];
                if (array_key_exists($key, $allowed)) {
                    return (string) $allowed[$key];
                }

                return '0';
            },
            $formula
        );

        if ($expr === null || preg_match('/[^0-9+\-*\/().\s]/', $expr) === 1) {
            return ['value' => 0.0, 'errors' => ['Formula contains unsupported tokens.']];
        }

        try {
            $value = $this->evaluateArithmeticExpression($expr);
        } catch (\Throwable $e) {
            $errors[] = 'Formula evaluation failed.';
            $value = 0.0;
        }

        return ['value' => (float) $value, 'errors' => $errors];
    }

    private function evaluateArithmeticExpression(string $expr): float
    {
        $tokens = $this->tokenize($expr);
        $output = [];
        $ops = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $output[] = (float) $token;

                continue;
            }
            if ($token === '(') {
                $ops[] = $token;

                continue;
            }
            if ($token === ')') {
                while (! empty($ops) && end($ops) !== '(') {
                    $this->reduceStack($output, (string) array_pop($ops));
                }
                array_pop($ops);

                continue;
            }
            while (! empty($ops) && end($ops) !== '(' && $precedence[(string) end($ops)] >= $precedence[$token]) {
                $this->reduceStack($output, (string) array_pop($ops));
            }
            $ops[] = $token;
        }

        while (! empty($ops)) {
            $this->reduceStack($output, (string) array_pop($ops));
        }

        return (float) ($output[0] ?? 0.0);
    }

    /** @return list<string> */
    private function tokenize(string $expr): array
    {
        $expr = preg_replace('/\s+/', '', $expr) ?? '';
        preg_match_all('/\d+(?:\.\d+)?|[()+\-*\/]/', $expr, $m);

        return $m[0] ?? [];
    }

    /** @param  array<int, float>  $stack */
    private function reduceStack(array &$stack, string $op): void
    {
        $b = array_pop($stack) ?? 0.0;
        $a = array_pop($stack) ?? 0.0;
        $res = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => abs($b) < 0.0000001 ? 0.0 : $a / $b,
            default => 0.0,
        };
        $stack[] = $res;
    }
}
