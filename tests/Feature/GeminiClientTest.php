<?php

use App\Exceptions\GeminiException;
use App\Services\AI\GeminiClient;
use Illuminate\Support\Facades\Http;

function fakeGeminiGenerateResponse(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
}

test('generate sanitizes markdown code fences and decodes the JSON payload', function () {
    fakeGeminiGenerateResponse("```json\n{\"foo\": \"bar\"}\n```");

    $result = app(GeminiClient::class)->generate('qualquer prompt');

    expect($result)->toBe(['foo' => 'bar']);
});

test('generate decodes a plain JSON payload without markdown fences', function () {
    fakeGeminiGenerateResponse('{"foo": "bar"}');

    $result = app(GeminiClient::class)->generate('qualquer prompt');

    expect($result)->toBe(['foo' => 'bar']);
});

test('generate throws a clean GeminiException when the HTTP call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('server error', 500),
    ]);

    expect(fn () => app(GeminiClient::class)->generate('qualquer prompt'))
        ->toThrow(GeminiException::class);
});

test('generate throws a clean GeminiException when the AI response is not valid json', function () {
    fakeGeminiGenerateResponse('isso não é um JSON válido.');

    expect(fn () => app(GeminiClient::class)->generate('qualquer prompt'))
        ->toThrow(GeminiException::class);
});

test('generate throws a clean GeminiException when no API key is configured', function () {
    config(['services.gemini.key' => '']);
    putenv('GEMINI_API_KEY=');
    $_ENV['GEMINI_API_KEY'] = '';
    $_SERVER['GEMINI_API_KEY'] = '';

    Http::fake();

    expect(fn () => app(GeminiClient::class)->generate('qualquer prompt'))
        ->toThrow(GeminiException::class);

    Http::assertNothingSent();

    putenv('GEMINI_API_KEY=test-gemini-api-key');
    $_ENV['GEMINI_API_KEY'] = 'test-gemini-api-key';
    $_SERVER['GEMINI_API_KEY'] = 'test-gemini-api-key';
});

test('generate sends the image as inline data alongside the prompt', function () {
    fakeGeminiGenerateResponse('{"foo": "bar"}');

    app(GeminiClient::class)->generate('descreva a imagem', [
        'mimeType' => 'image/jpeg',
        'data' => base64_encode('fake-image-bytes'),
    ]);

    Http::assertSent(function ($request) {
        $parts = $request->data()['contents'][0]['parts'];

        return count($parts) === 2
            && $parts[1]['inlineData']['mimeType'] === 'image/jpeg';
    });
});
