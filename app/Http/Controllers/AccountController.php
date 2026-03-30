<?php

namespace App\Http\Controllers;

use App\Exceptions\BankingException;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    /**
     * Get the balance for a specific user.
     *
     * GET /balance/{user_id}
     */
    public function balance(int $user_id): JsonResponse
    {
        $user = User::find($user_id);

        if (!$user) {
            throw BankingException::userNotFound();
        }

        return response()->json([
            'user_id' => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
            'balance' => (float) $user->balance,
        ])->withHeaders($this->rateLimitHeaders());
    }

    /**
     * Deposit money into a user's account.
     *
     * POST /deposit
     * Body: { "user_id": 1, "amount": 50000 }
     */
    public function deposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1',
            'amount'  => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $isAmountError = collect($errors)->contains(fn($e) => str_contains(strtolower($e), 'amount'));

            if ($isAmountError) {
                throw BankingException::invalidAmount();
            }

            return response()->json([
                'error'   => implode(', ', $errors),
                'code'    => 'VALIDATION_ERROR',
            ], 422);
        }

        $userId = (int) $request->input('user_id');
        $amount = (float) $request->input('amount');

        if ($amount <= 0) {
            throw BankingException::invalidAmount();
        }

        $user = User::find($userId);
        if (!$user) {
            throw BankingException::userNotFound();
        }

        $transaction = DB::transaction(function () use ($user, $amount) {
            $user->increment('balance', $amount);
            $user->refresh();

            return Transaction::create([
                'from_user_id' => null,
                'to_user_id'   => $user->id,
                'type'         => 'deposit',
                'amount'       => $amount,
                'status'       => 'success',
                'note'         => "Deposit of {$amount} to user {$user->id}",
            ]);
        });

        return response()->json([
            'message'        => 'Deposit successful',
            'transaction_id' => $transaction->id,
            'user_id'        => $user->id,
            'amount'         => $amount,
            'new_balance'    => (float) $user->balance,
        ], 201)->withHeaders($this->rateLimitHeaders());
    }

    /**
     * List all users (for testing purposes).
     *
     * GET /users
     */
    public function users(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'balance', 'created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'balance'    => (float) $user->balance,
                    'created_at' => $user->created_at,
                ];
            });

        return response()->json([
            'data'  => $users,
            'total' => $users->count(),
        ])->withHeaders($this->rateLimitHeaders());
    }

    /**
     * Return common rate limiting headers.
     */
    private function rateLimitHeaders(): array
    {
        return [
            'X-RateLimit-Limit'     => '100',
            'X-RateLimit-Remaining' => '99',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
        ];
    }
}
