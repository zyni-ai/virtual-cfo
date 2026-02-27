<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

class AuditLlmCalls
{
    /**
     * Handle the incoming agent prompt and log metadata.
     */
    public function handle(AgentPrompt $prompt, Closure $next): AgentResponse
    {
        $startTime = hrtime(true);

        try {
            /** @var AgentResponse $response */
            $response = $next($prompt);

            $this->logCall($prompt, $startTime, 'success', $response);

            return $response;
        } catch (\Throwable $e) {
            $this->logCall($prompt, $startTime, 'error', error: $e->getMessage());

            throw $e;
        }
    }

    /**
     * Log LLM call metadata (never prompt/response content).
     */
    protected function logCall(
        AgentPrompt $prompt,
        int $startTime,
        string $status,
        ?AgentResponse $response = null,
        ?string $error = null,
    ): void {
        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        $properties = [
            'agent' => class_basename($prompt->agent),
            'model' => $prompt->model,
            'provider' => $response?->meta->provider ?? 'unknown',
            'prompt_tokens' => $response?->usage->promptTokens ?? 0,
            'completion_tokens' => $response?->usage->completionTokens ?? 0,
            'duration_ms' => $durationMs,
            'status' => $status,
        ];

        if ($error !== null) {
            $properties['error'] = $error;
        }

        activity('llm-calls')
            ->withProperties($properties)
            ->log('llm_call');
    }
}
