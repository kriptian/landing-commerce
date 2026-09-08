<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

function productAiUser(bool $withPermission = true): User
{
    $user = User::factory()->create();
    $store = Store::create([
        'name' => 'AI Store',
        'slug' => 'ai-store-'.$user->id,
        'user_id' => $user->id,
        'plan' => 'negociante',
    ]);
    $user->update(['store_id' => $store->id]);
    $store->categories()->create(['name' => 'Camisetas']);

    if ($withPermission) {
        $user->givePermissionTo(Permission::findOrCreate('crear productos', 'web'));
    }

    return $user;
}

it('generates a reviewed product draft without creating a product', function () {
    config()->set('ai.enabled', true);
    config()->set('ai.gemini.api_key', 'test-key');
    config()->set('ai.gemini.model', 'gemini-test');
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'name' => 'Camiseta deportiva',
                            'short_description' => 'Prenda ligera para entrenamiento.',
                            'long_description' => 'Una camiseta cómoda para actividad física.',
                            'specifications' => ['Tela ligera', 'Color azul'],
                            'meta_keywords' => ['camiseta', 'deporte'],
                            'category_name' => 'Camisetas',
                        ]),
                    ]],
                ],
            ]],
        ]),
    ]);
    $user = productAiUser();

    $this->actingAs($user)
        ->postJson(route('admin.products.ai-draft'), ['context' => 'Camiseta azul para hacer deporte'])
        ->assertOk()
        ->assertJsonPath('draft.name', 'Camiseta deportiva')
        ->assertJsonPath('draft.specifications', 'Tela ligera, Color azul')
        ->assertJsonPath('draft.category.name', 'Camisetas');

    $this->assertDatabaseCount('products', 0);
    Http::assertSent(function ($request) {
        $payload = $request->data();
        $serialized = json_encode($payload);

        return $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($request->url(), '/models/gemini-test:generateContent')
            && ! str_contains($serialized, 'purchase_price')
            && ! str_contains($serialized, 'stock');
    });
});

it('requires product creation permission for AI drafts', function () {
    config()->set('ai.enabled', true);
    config()->set('ai.gemini.api_key', 'test-key');
    Http::fake();
    $user = productAiUser(false);

    $this->actingAs($user)
        ->postJson(route('admin.products.ai-draft'), ['context' => 'A product'])
        ->assertForbidden();

    Http::assertNothingSent();
});

it('fails closed when Gemini is disabled', function () {
    config()->set('ai.enabled', false);
    config()->set('ai.gemini.api_key', null);
    Http::fake();
    $user = productAiUser();

    $this->actingAs($user)
        ->postJson(route('admin.products.ai-draft'), ['context' => 'A product'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'La asistencia con IA no está configurada.');

    Http::assertNothingSent();
});

it('rejects invalid files before contacting Gemini', function () {
    config()->set('ai.enabled', true);
    config()->set('ai.gemini.api_key', 'test-key');
    Http::fake();
    $user = productAiUser();

    $this->actingAs($user)
        ->post(route('admin.products.ai-draft'), [
            'image' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    Http::assertNothingSent();
});
