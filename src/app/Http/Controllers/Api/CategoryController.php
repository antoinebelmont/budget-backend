<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = $request->user()->categories()
            ->with(['categoryGroup', 'goals'])
            ->where('hidden', false)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1|max:100',
            'category_group_id' => 'required|exists:category_groups,id',
            'budgeted' => 'sometimes|numeric|min:0',
            'color' => 'sometimes|nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'sometimes|integer'
        ]);

        // Verify category group belongs to user
        $categoryGroup = CategoryGroup::where('id', $request->category_group_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$categoryGroup) {
            return response()->json(['message' => 'Category group not found'], 404);
        }

        // Check for duplicate category name (case-insensitive) for this user
        $existingCategory = $request->user()->categories()
            ->whereRaw('LOWER(name) = ?', [strtolower($request->name)])
            ->first();

        if ($existingCategory) {
            // Idempotent behavior: return existing category
            return response()->json([
                'message' => 'Category already exists',
                'category' => $existingCategory->load('categoryGroup'),
                'duplicate' => true
            ], 200);
        }

        // Get the highest sort_order for this category group
        $maxSortOrder = (int)$categoryGroup->categories()->max('sort_order') ?? -1;

        $category = $request->user()->categories()->create([
            'name' => $request->name,
            'category_group_id' => $request->category_group_id,
            'budgeted' => $request->budgeted ?? 0,
            'activity' => 0,
            'available' => $request->budgeted ?? 0,
            'color' => $request->color ?? null,
            'sort_order' => $request->sort_order ?? ($maxSortOrder + 1),
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category->load('categoryGroup'),
            'duplicate' => false
        ], 201);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'category' => $category->load('categoryGroup', 'goals', 'transactions.payee')
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'budgeted' => 'sometimes|numeric|min:0',
            'color' => 'sometimes|nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'hidden' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer'
        ]);

        $category->update($request->only(['name', 'budgeted', 'color', 'hidden', 'sort_order']));

        if ($request->has('budgeted')) {
            $category->updateActivity();
        }

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->load('categoryGroup')
        ]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if this is the last category in the category group
        $categoriesInGroup = Category::where('category_group_id', $category->category_group_id)
            ->where('hidden', false)
            ->count();

        if ($categoriesInGroup <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last category in a category group. At least one category must remain.'
            ], 422);
        }

        // Set category_id to null for existing transactions
        $category->transactions()->update(['category_id' => null]);

        // Delete associated goals
        $category->goals()->delete();

        // Soft delete or hide the category
        $category->update(['hidden' => true]);

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    public function move(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'category_group_id' => 'required|exists:category_groups,id'
        ]);

        // Verify new category group belongs to user
        $categoryGroup = CategoryGroup::where('id', $request->category_group_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$categoryGroup) {
            return response()->json(['message' => 'Category group not found'], 404);
        }

        // Check if this is the last category in the current group
        if ($category->category_group_id !== $request->category_group_id) {
            $categoriesInCurrentGroup = Category::where('category_group_id', $category->category_group_id)
                ->where('hidden', false)
                ->where('id', '!=', $category->id)
                ->count();

            if ($categoriesInCurrentGroup < 1) {
                return response()->json([
                    'message' => 'Cannot move the last category from a category group. At least one category must remain.'
                ], 422);
            }
        }

        // Get the highest sort_order for the new category group
        $maxSortOrder = $categoryGroup->categories()->max('sort_order') ?? -1;

        $category->update([
            'category_group_id' => $request->category_group_id,
            'sort_order' => $maxSortOrder + 1
        ]);

        return response()->json([
            'message' => 'Category moved successfully',
            'category' => $category->load('categoryGroup')
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer',
            'categories.*.category_group_id' => 'required|exists:category_groups,id'
        ]);

        foreach ($request->categories as $categoryData) {
            Category::where('id', $categoryData['id'])
                ->where('user_id', $request->user()->id)
                ->update([
                    'sort_order' => $categoryData['sort_order'],
                    'category_group_id' => $categoryData['category_group_id']
                ]);
        }

        return response()->json(['message' => 'Categories reordered successfully']);
    }
}
