<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


// CategoryController.php (Enhanced)
class CategoryGroupController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request):JsonResponse
    {
        $categoryGroups = $request->user()->categoryGroups()
            ->with(['categories' => function($query) {
                $query->where('hidden', false)->orderBy('sort_order');
            }])
            ->where('hidden', false)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['category_groups' => $categoryGroups]);
    }

    public function store(Request $request):JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'sometimes|integer'
        ]);

        $categoryGroup = $request->user()->categoryGroups()->create([
            'name' => $request->name,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json(['category_group' => $categoryGroup], 201);
    }

    public function show(Request $request, CategoryGroup $categoryGroup):JsonResponse
    {
        $this->authorize('view', $categoryGroup);

        return response()->json([
            'category_group' => $categoryGroup->load('categories')
        ]);
    }

    public function update(Request $request, CategoryGroup $categoryGroup):JsonResponse
    {
        $this->authorize('update', $categoryGroup);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'hidden' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer'
        ]);

        $categoryGroup->update($request->all());

        return response()->json(['category_group' => $categoryGroup]);
    }

    public function destroy(CategoryGroup $categoryGroup):JsonResponse
    {
        $this->authorize('delete', $categoryGroup);

        // Check if category group has categories
        if ($categoryGroup->categories()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category group with existing categories'
            ], 422);
        }

        $categoryGroup->delete();

        return response()->json(['message' => 'Category group deleted successfully']);
    }

    public function reorder(Request $request):JsonResponse
    {
        $request->validate([
            'category_groups' => 'required|array',
            'category_groups.*.id' => 'required|exists:category_groups,id',
            'category_groups.*.sort_order' => 'required|integer'
        ]);

        foreach ($request->category_groups as $groupData) {
            CategoryGroup::where('id', $groupData['id'])
                ->where('user_id', $request->user()->id)
                ->update(['sort_order' => $groupData['sort_order']]);
        }

        return response()->json(['message' => 'Category groups reordered successfully']);
    }
}
