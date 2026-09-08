<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\GeminiProductDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProductAiController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:crear productos');
    }

    public function store(Request $request, GeminiProductDraftService $generator): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['nullable', 'required_without:context', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=5000,max_height=5000'],
            'context' => ['nullable', 'required_without:image', 'string', 'max:2000'],
        ]);

        $store = $request->user()->store;
        abort_unless($store, 403);

        $categories = $store->categories()
            ->whereDoesntHave('children')
            ->orderBy('name')
            ->get(['id', 'name']);

        try {
            $draft = $generator->generate($request->file('image'), $validated['context'] ?? null, $categories->pluck('name')->all());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $category = $categories->first(fn ($candidate) => mb_strtolower(trim($candidate->name)) === mb_strtolower(trim($draft['category_name'])));

        return response()->json([
            'draft' => [
                ...$draft,
                'specifications' => implode(', ', $draft['specifications']),
                'meta_keywords' => implode(', ', $draft['meta_keywords']),
                'category' => $category ? $category->only(['id', 'name']) : null,
            ],
        ]);
    }
}
