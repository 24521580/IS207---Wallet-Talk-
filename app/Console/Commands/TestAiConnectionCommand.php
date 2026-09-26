<?php

namespace App\Console\Commands;

use App\Services\Ai\LiveAiClient;
use Illuminate\Console\Command;

class TestAiConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test {provider? : Optional provider to test (gemini, openai, groq)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely test connection to the configured AI provider without exposing keys';

    /**
     * Execute the console command.
     */
    public function handle(LiveAiClient $client): int
    {
        $providerArg = $this->argument('provider');
        $provider = $providerArg ? strtolower((string) $providerArg) : $client->resolveProvider();

        $this->info("Checking AI configuration for provider: [{$provider}]...");

        $status = $client->testConnection($provider);

        $this->table(
            ['Key', 'Value'],
            collect($status)->map(function ($val, $key) {
                return [$key, is_bool($val) ? ($val ? 'true' : 'false') : (string) $val];
            })->values()->all()
        );

        if ($status['connection'] === 'ok') {
            $this->info("✓ AI Connection test succeeded! Model [{$status['model']}] is ready.");
            return self::SUCCESS;
        }

        $this->error("✗ AI Connection test failed! Reason: " . ($status['message'] ?? $status['error_type']));
        return self::FAILURE;
    }
}
