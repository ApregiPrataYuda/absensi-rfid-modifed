<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramBotService $telegramBotService): JsonResponse
    {
        $settings = $telegramBotService->getSettings(true);
        $expectedSecret = trim((string) ($settings['telegram_webhook_secret'] ?? ''));

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $secret)) {
            abort(404);
        }

        $headerSecret = trim((string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''));
        if ($headerSecret === '' || ! hash_equals($expectedSecret, $headerSecret)) {
            abort(403);
        }

        $rawPayload = (string) $request->getContent();
        $payload = $this->decodeWebhookPayload($rawPayload, (array) $request->all());

        try {
            $result = $telegramBotService->handleWebhookUpdate($payload);

            if (($result['handled'] ?? false) === true && filled($result['reply'] ?? null) && filled($result['chat_id'] ?? null)) {
                $telegramBotService->sendMessageToChatId(
                    (string) $result['chat_id'],
                    (string) $result['reply'],
                    (string) ($settings['telegram_bot_token'] ?? '')
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram webhook processing failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function decodeWebhookPayload(string $rawPayload, array $fallback): array
    {
        if (trim($rawPayload) === '') {
            return $fallback;
        }

        try {
            $decoded = json_decode($rawPayload, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
