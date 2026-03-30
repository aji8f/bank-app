<?php

namespace App\Http\Controllers;

use App\Exceptions\BankingException;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Transfer funds between two users.
     *
     * POST /transfer
     * Body: { "from": 1, "to": 2, "amount": 10000 }
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from'   => 'required|integer|min:1',
            'to'     => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $isAmountError = collect($errors)->contains(fn($e) => str_contains(strtolower($e), 'amount'));

            if ($isAmountError) {
                throw BankingException::invalidAmount();
            }

            return response()->json([
                'error' => implode(', ', $errors),
                'code'  => 'VALIDATION_ERROR',
            ], 422);
        }

        $fromId = (int) $request->input('from');
        $toId   = (int) $request->input('to');
        $amount = (float) $request->input('amount');

        if ($amount <= 0) {
            throw BankingException::invalidAmount();
        }

        if ($fromId === $toId) {
            throw BankingException::sameUser();
        }

        $sender   = User::find($fromId);
        $receiver = User::find($toId);

        if (!$sender) {
            throw BankingException::userNotFound();
        }

        if (!$receiver) {
            throw BankingException::userNotFound();
        }

        if ((float) $sender->balance < $amount) {
            throw BankingException::insufficientBalance();
        }

        $transaction = DB::transaction(function () use ($sender, $receiver, $amount) {
            // Lock both rows to prevent race conditions
            $sender   = User::lockForUpdate()->find($sender->id);
            $receiver = User::lockForUpdate()->find($receiver->id);

            // Re-check balance after lock
            if ((float) $sender->balance < $amount) {
                throw BankingException::insufficientBalance();
            }

            $sender->decrement('balance', $amount);
            $receiver->increment('balance', $amount);

            return Transaction::create([
                'from_user_id' => $sender->id,
                'to_user_id'   => $receiver->id,
                'type'         => 'transfer',
                'amount'       => $amount,
                'status'       => 'success',
                'note'         => "Transfer of {$amount} from user {$sender->id} to user {$receiver->id}",
            ]);
        });

        $sender->refresh();
        $receiver->refresh();

        return response()->json([
            'message'          => 'Transfer successful',
            'transaction_id'   => $transaction->id,
            'from'             => [
                'user_id'     => $sender->id,
                'name'        => $sender->name,
                'new_balance' => (float) $sender->balance,
            ],
            'to'               => [
                'user_id'     => $receiver->id,
                'name'        => $receiver->name,
                'new_balance' => (float) $receiver->balance,
            ],
            'amount'           => $amount,
            'timestamp'        => now()->toIso8601String(),
        ], 200)->withHeaders($this->rateLimitHeaders());
    }

    /**
     * Get transaction history for a specific user.
     *
     * GET /transactions/{user_id}
     */
    public function history(int $user_id): JsonResponse
    {
        $user = User::find($user_id);

        if (!$user) {
            throw BankingException::userNotFound();
        }

        $transactions = Transaction::where('from_user_id', $user_id)
            ->orWhere('to_user_id', $user_id)
            ->with(['fromUser:id,name,email', 'toUser:id,name,email'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($tx) use ($user_id) {
                return [
                    'id'           => $tx->id,
                    'type'         => $tx->type,
                    'amount'       => (float) $tx->amount,
                    'status'       => $tx->status,
                    'direction'    => $tx->from_user_id === $user_id ? 'debit' : 'credit',
                    'from_user'    => $tx->fromUser ? [
                        'id'    => $tx->fromUser->id,
                        'name'  => $tx->fromUser->name,
                        'email' => $tx->fromUser->email,
                    ] : null,
                    'to_user'      => $tx->toUser ? [
                        'id'    => $tx->toUser->id,
                        'name'  => $tx->toUser->name,
                        'email' => $tx->toUser->email,
                    ] : null,
                    'note'         => $tx->note,
                    'created_at'   => $tx->created_at,
                ];
            });

        return response()->json([
            'user_id' => $user_id,
            'name'    => $user->name,
            'data'    => $transactions,
            'total'   => $transactions->count(),
        ])->withHeaders($this->rateLimitHeaders());
    }

    /**
     * Return common rate limiting headers.
     */
    private function rateLimitHeaders(): array
    {
        return [
            'X-RateLimit-Limit'      => '100',
            'X-RateLimit-Remaining'  => '99',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
        ];
    }
}
