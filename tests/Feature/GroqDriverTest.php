<?php

use App\Exceptions\GroqException;
use App\Services\AI\Drivers\GroqDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function fakeGroqChatResponse(string $content): void
{
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ], 200),
    ]);
}

test('generateStructured sanitizes markdown fences and decodes the JSON payload', function () {
    fakeGroqChatResponse("```json\n{\"foo\": \"bar\"}\n```");

    $result = app(GroqDriver::class)->generateStructured('qualquer prompt');

    expect($result)->toBe(['foo' => 'bar']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-groq-api-key')
        && $request['model'] === 'openai/gpt-oss-20b');
});

test('generateStructured sends the system instruction as a dedicated message', function () {
    fakeGroqChatResponse('{"foo": "bar"}');

    app(GroqDriver::class)->generateStructured('pergunta do usuário', 'instrução de sistema');

    Http::assertSent(fn ($request) => $request['messages'][0] === ['role' => 'system', 'content' => 'instrução de sistema']
        && $request['messages'][1] === ['role' => 'user', 'content' => 'pergunta do usuário']);
});

test('analyzeImage sends the image as an OpenAI-compatible image_url content part', function () {
    fakeGroqChatResponse('{"foo": "bar"}');
    $image = UploadedFile::fake()->create('ficha.jpg', 200, 'image/jpeg');

    app(GroqDriver::class)->analyzeImage($image, 'descreva a imagem');

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'];

        return $content[0] === ['type' => 'text', 'text' => 'descreva a imagem']
            && str_starts_with($content[1]['image_url']['url'], 'data:image/jpeg;base64,');
    });
});

test('throws a clean GroqException when the HTTP call fails', function () {
    Http::fake([
        'api.groq.com/*' => Http::response('server error', 500),
    ]);

    expect(fn () => app(GroqDriver::class)->generateStructured('qualquer prompt'))
        ->toThrow(GroqException::class);
});

test('throws a clean GroqException when the AI response is not valid json', function () {
    fakeGroqChatResponse('isso não é um JSON válido.');

    expect(fn () => app(GroqDriver::class)->generateStructured('qualquer prompt'))
        ->toThrow(GroqException::class);
});

test('throws a clean GroqException when no API key is configured', function () {
    config(['services.groq.key' => '']);

    Http::fake();

    expect(fn () => app(GroqDriver::class)->generateStructured('qualquer prompt'))
        ->toThrow(GroqException::class);

    Http::assertNothingSent();
});
