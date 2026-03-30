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
        ])->withHeaders($this->securityHeaders());
    }

    /**
     * POST /deposit
     * Body: { "user_id": 1, "amount": 50000 }
     */
    public function deposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1|exists:users,id',
            'amount'  => 'required|numeric|min:0.01',
        ], [
            'user_id.exists' => 'User not found.',
            'amount.min'     => 'Amount must be greater than 0.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('user_id') && str_contains($errors->first('user_id'), 'not found')) {
                throw BankingException::userNotFound();
            }

            if ($errors->has('amount')) {
                throw BankingException::invalidAmount();
            }

            return response()->json([
                'error'  => $errors->first(),
                'code'   => 'VALIDATION_ERROR',
                'errors' => $errors->all(),
            ], 422);
        }

        $userId = (int) $request->input('user_id');
        $amount = (float) $request->input('amount');

        $user        = null;
        $transaction = DB::transaction(function () use ($userId, $amount, &$user) {
            // Lock row for concurrent deposit safety
            $user = User::lockForUpdate()->find($userId);

            $user->increment('balance', $amount);
            $user->refresh();

            return Transaction::create([
                'from_user_id' => null,
                'to_user_id'   => $user->id,
                'type'         => 'deposit',
                'amount'       => $amount,
                'status'       => 'success',
                'note'         => "Deposit Rp " . number_format($amount, 0, ',', '.') . " ke akun {$user->name}",
            ]);
        });

        return response()->json([
            'message'        => 'Deposit successful',
            'transaction_id' => $transaction->id,
            'user_id'        => $user->id,
            'name'           => $user->name,
            'amount'         => $amount,
            'balance'        => (float) $user->balance,   // key yg dipakai frontend
            'new_balance'    => (float) $user->balance,   // alias
        ], 201)->withHeaders($this->securityHeaders());
    }

    /**
     * GET /users
     */
    public function users(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'balance', 'created_at')
            ->orderBy('id')
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'balance'    => (float) $u->balance,
                'created_at' => $u->created_at,
            ]);

        return response()->json([
            'users' => $users,           // diperbaiki: dari 'data' → 'users'
            'total' => $users->count(),
        ])->withHeaders($this->securityHeaders());
    }

    /**
     * GET /stats — agregat untuk dashboard
     */
    public function stats(): JsonResponse
    {
        $totalUsers   = User::count();
        $totalBalance = (float) User::sum('balance');
        $totalTx      = Transaction::count();
        $successTx    = Transaction::where('status', 'success')->count();

        return response()->json([
            'total_users'         => $totalUsers,
            'total_balance'       => $totalBalance,
            'total_transactions'  => $totalTx,
            'success_transactions'=> $successTx,
        ])->withHeaders($this->securityHeaders());
    }

    private function securityHeaders(): array
    {
        return [
            'X-RateLimit-Limit'      => '100',
            'X-RateLimit-Remaining'  => '99',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
        ];
    }
}
