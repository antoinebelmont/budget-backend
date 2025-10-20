<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Payee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayeeController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request):JsonResponse
    {
        $query = $request->user()->payees()->with('autoAssignCategory');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $payees = $query->orderBy('name')->get();

        $payees->each(function ($payee) {
            $payee->total_spent = $payee->getTotalSpentAttribute();
        });

        return response()->json(['payees' => $payees]);
    }

    public function store(Request $request):JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'auto_assign_category_id' => 'nullable|exists:categories,id'
        ]);

        // Check if category belongs to user
        if ($request->auto_assign_category_id) {
            $category = Category::where('id', $request->auto_assign_category_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$category) {
                return response()->json([
                    'message' => 'Invalid category selected'
                ], 422);
            }
        }

        $payee = $request->user()->payees()->create($request->all());

        return response()->json(['payee' => $payee->load('autoAssignCategory')], 201);
    }

    public function show(Request $request, Payee $payee):JsonResponse
    {
        $this->authorize('view', $payee);

        return response()->json([
            'payee' => $payee->load('autoAssignCategory', 'transactions.category')
        ]);
    }

    public function update(Request $request, Payee $payee):JsonResponse
    {
        $this->authorize('update', $payee);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'auto_assign_category_id' => 'nullable|exists:categories,id'
        ]);

        if ($request->has('auto_assign_category_id') && $request->auto_assign_category_id) {
            $category = Category::where('id', $request->auto_assign_category_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$category) {
                return response()->json([
                    'message' => 'Invalid category selected'
                ], 422);
            }
        }

        $payee->update($request->all());

        return response()->json(['payee' => $payee->load('autoAssignCategory')]);
    }

    public function destroy(Payee $payee):JsonResponse
    {
        $this->authorize('delete', $payee);

        // Set payee_id to null for existing transactions
        $payee->transactions()->update(['payee_id' => null]);
        $payee->delete();

        return response()->json(['message' => 'Payee deleted successfully']);
    }

    public function suggestions(Request $request):JsonResponse
    {
        $search = $request->get('search', '');

        $payees = $request->user()->payees()
            ->where('name', 'like', '%' . $search . '%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'auto_assign_category_id']);

        return response()->json(['payees' => $payees]);
    }
}
