<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'timezone' => $request->timezone ?? 'UTC',
            'currency' => $request->currency ?? 'USD',
            'preferences' => (new User())->getDefaultPreferences(),
        ]);

        // Create default category groups and categories
        $this->createDefaultBudgetStructure($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'account' => ['Your account has been deactivated.'],
            ]);
        }

        $user->updateLastLogin();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    private function createDefaultBudgetStructure(User $user): void
    {
        // Create default category groups
        $immediateObligations = $user->categoryGroups()->create([
            'name' => 'Immediate Obligations',
            'sort_order' => 1,
        ]);

        $trueExpenses = $user->categoryGroups()->create([
            'name' => 'True Expenses',
            'sort_order' => 2,
        ]);

        $qualityOfLife = $user->categoryGroups()->create([
            'name' => 'Quality of Life Goals',
            'sort_order' => 3,
        ]);

        // Create default categories
        $immediateObligations->categories()->createMany([
            ['name' => 'Rent/Mortgage', 'user_id' => $user->id],
            ['name' => 'Electric', 'user_id' => $user->id],
            ['name' => 'Gas', 'user_id' => $user->id],
            ['name' => 'Water', 'user_id' => $user->id],
            ['name' => 'Internet', 'user_id' => $user->id],
            ['name' => 'Groceries', 'user_id' => $user->id],
        ]);

        $trueExpenses->categories()->createMany([
            ['name' => 'Car Maintenance', 'user_id' => $user->id],
            ['name' => 'Home Maintenance', 'user_id' => $user->id],
            ['name' => 'Medical', 'user_id' => $user->id],
            ['name' => 'Clothing', 'user_id' => $user->id],
            ['name' => 'Insurance', 'user_id' => $user->id],
        ]);

        $qualityOfLife->categories()->createMany([
            ['name' => 'Dining Out', 'user_id' => $user->id],
            ['name' => 'Entertainment', 'user_id' => $user->id],
            ['name' => 'Hobbies', 'user_id' => $user->id],
            ['name' => 'Vacation', 'user_id' => $user->id],
        ]);

        // Create a default checking account
        $user->accounts()->create([
            'name' => 'Checking Account',
            'type' => 'checking',
            'balance' => 0,
        ]);
    }
}
