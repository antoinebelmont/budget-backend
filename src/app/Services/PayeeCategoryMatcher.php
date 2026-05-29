<?php

namespace App\Services;

use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

class PayeeCategoryMatcher
{
    public function resolve(ValidatedRow $row, User $user): array
    {
        $payee = Payee::where('user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($row->payee))])
            ->first();

        if (!$payee) {
            $payee = Payee::create([
                'user_id' => $user->id,
                'name' => trim($row->payee),
            ]);
        }

        if ($payee->auto_assign_category_id) {
            return [
                'payee_id' => $payee->id,
                'category_id' => $payee->auto_assign_category_id,
            ];
        }

        $mostFrequentCategory = Transaction::where('user_id', $user->id)
            ->where('payee_id', $payee->id)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as count')
            ->groupBy('category_id')
            ->orderByDesc('count')
            ->first();

        return [
            'payee_id' => $payee->id,
            'category_id' => $mostFrequentCategory?->category_id,
        ];
    }
}
