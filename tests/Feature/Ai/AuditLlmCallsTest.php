<?php

use App\Ai\Agents\HeadMatcher;
use App\Ai\Agents\StatementParser;
use App\Ai\Middleware\AuditLlmCalls;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->middleware = new AuditLlmCalls;
    $this->provider = Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
});

describe('AuditLlmCalls middleware', function () {
    it('logs successful LLM call metadata to activity log', function () {
        $agent = HeadMatcher::make();

        $prompt = new AgentPrompt(
            agent: $agent,
            prompt: 'Match these transactions to account heads',
            attachments: [],
            provider: $this->provider,
            model: 'mistralai/mistral-large-latest',
        );

        $usage = new Usage(promptTokens: 500, completionTokens: 200);
        $meta = new Meta(provider: 'openrouter', model: 'mistralai/mistral-large-latest');
        $response = new AgentResponse('inv-123', 'Some response content', $usage, $meta);

        $this->middleware->handle($prompt, function () use ($response) {
            return $response;
        });

        $log = Activity::where('log_name', 'llm-calls')->latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->description)->toBe('llm_call')
            ->and($log->properties['agent'])->toBe('HeadMatcher')
            ->and($log->properties['model'])->toBe('mistralai/mistral-large-latest')
            ->and($log->properties['provider'])->toBe('openrouter')
            ->and($log->properties['prompt_tokens'])->toBe(500)
            ->and($log->properties['completion_tokens'])->toBe(200)
            ->and($log->properties['status'])->toBe('success')
            ->and($log->properties)->toHaveKey('duration_ms');
    });

    it('never logs prompt content', function () {
        $agent = StatementParser::make();

        $sensitivePrompt = 'Account number 50100123456789 PAN ABCDE1234F';
        $prompt = new AgentPrompt(
            agent: $agent,
            prompt: $sensitivePrompt,
            attachments: [],
            provider: $this->provider,
            model: 'test-model',
        );

        $usage = new Usage(promptTokens: 100, completionTokens: 50);
        $meta = new Meta(provider: 'openrouter', model: 'test-model');
        $response = new AgentResponse('inv-456', 'Sensitive response data', $usage, $meta);

        $this->middleware->handle($prompt, function () use ($response) {
            return $response;
        });

        $log = Activity::where('log_name', 'llm-calls')->latest()->first();
        $allProperties = json_encode($log->properties);

        expect($allProperties)->not->toContain('50100123456789')
            ->and($allProperties)->not->toContain('ABCDE1234F')
            ->and($allProperties)->not->toContain($sensitivePrompt)
            ->and($allProperties)->not->toContain('Sensitive response data');
    });

    it('logs failures with error status', function () {
        $agent = HeadMatcher::make();

        $prompt = new AgentPrompt(
            agent: $agent,
            prompt: 'Test prompt',
            attachments: [],
            provider: $this->provider,
            model: 'test-model',
        );

        try {
            $this->middleware->handle($prompt, function () {
                throw new RuntimeException('API connection failed');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $log = Activity::where('log_name', 'llm-calls')->latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->properties['status'])->toBe('error')
            ->and($log->properties['error'])->toBe('API connection failed')
            ->and($log->properties)->toHaveKey('duration_ms');
    });

    it('re-throws exceptions after logging', function () {
        $agent = HeadMatcher::make();

        $prompt = new AgentPrompt(
            agent: $agent,
            prompt: 'Test prompt',
            attachments: [],
            provider: $this->provider,
            model: 'test-model',
        );

        expect(fn () => $this->middleware->handle($prompt, function () {
            throw new RuntimeException('API error');
        }))->toThrow(RuntimeException::class, 'API error');
    });
});
