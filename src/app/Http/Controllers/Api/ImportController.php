<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\ImportOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportOrchestrator $importOrchestrator,
    ) {}

    /**
     * Upload a CSV file and import its rows as transactions.
     *
     * POST /api/transactions/import
     */
    public function __invoke(Request $request): JsonResponse
    {
        // ------------------------------------------------------------------
        // 1. Validate the request
        // ------------------------------------------------------------------
        $validated = $request->validate([
            'file'       => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $accountId = (int) $validated['account_id'];

        // ------------------------------------------------------------------
        // 2. Authorise — the account must belong to the authenticated user
        // ------------------------------------------------------------------
        $account = Account::find($accountId);
        if (! $account || $account->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'The selected account is invalid.',
            ], 422);
        }

        // ------------------------------------------------------------------
        // 3. Read file content
        // ------------------------------------------------------------------
        $file    = $request->file('file');
        $content = $file->get();

        // ------------------------------------------------------------------
        // 4. Delegate to the orchestrator
        // ------------------------------------------------------------------
        $result = $this->importOrchestrator->import($content, $accountId, $request->user());

        // ------------------------------------------------------------------
        // 5. Respond
        // ------------------------------------------------------------------
        return response()->json($result->toArray());
    }
}
