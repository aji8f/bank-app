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
     * POST /transfer
     * Body: { "from": 1, "to": 2, "amount": 10000 }
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from'   => 'required|integer|min:1|exists:users,id',
            'to'     => 'required|integer|min:1|exists:users,id|different:from',
            'amount' => 'required|numeric|min:0.01',
        ], [
            'from.exists'    => 'Sender user not found.',
            'to.exists'      => 'Receiver user not found.',
            'to.different'   => 'Cannot transfer to the same account.',
            'amount.min'     => 'Amount must be greater than 0.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('from') || $errors->has('to')) {
                if (str_contains($errors->first('from') . $errors->first('to'), 'not found')) {
                    throw BankingException::userNotFound();
                }
                if ($errors->has('to') && str_contains($errors->first('to'), 'same')) {
                    throw BankingException::sameUser();
                }
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

        $fromId = (int) $request->input('from');
        $toId   = (int) $request->input('to');
        $amount = (float) $request->input('amount');

        // Pre-check balance (fast fail before acquiring DB lock)
        $sender = User::find($fromId);
        if ((float) $sender->balance < $amount) {
            throw BankingException::insufficientBalance();
        }

        $receiver    = null;
        $transaction = DB::transaction(function () use ($fromId, $toId, $amount, &$sender, &$receiver) {
            // Lock both rows in consistent order (low id first) to avoid deadlock
            $ids = [$fromId, $toId];
            sort($ids);

            $locked = User::lockForUpdate()->whereIn('id', $ids)->get()->keyBy('id');
            $sender   = $locked[$fromId];
            $receiver = $locked[$toId];

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
                'note'         => "Transfer Rp " . number_format($amount, 0, ',', '.') . " dari {$sender->name} ke {$receiver->name}",
            ]);
        });

        $sender->refresh();
        $receiver->refresh();

        return response()->json([
            'message'        => 'Transfer successful',
            'transaction_id' => $transaction->id,
            'amount'         => $amount,
            'timestamp'      => now()->toIso8601String(),
            // flat keys untuk frontend
            'from_balance'   => (float) $sender->balance,
            'to_balance'     => (float) $receiver->balance,
            // detail lengkap
            'from' => [
                'user_id'     => $sender->id,
                'name'        => $sender->name,
                'new_balance' => (float) $sender->balance,
            ],
            'to' => [
                'user_id'     => $receiver->id,
                'name'        => $receiver->name,
                'new_balance' => (float) $receiver->balance,
            ],
        ], 200)->withHeaders($this->securityHeaders());
    }

    /**
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
                    'id'         => $tx->id,
                    'type'       => $tx->type,
                    'amount'     => (float) $tx->amount,
                    'status'     => $tx->status,
                    'direction'  => ((int) $tx->from_user_id === (int) $user_id) ? 'debit' : 'credit',
                    'from_user'  => $tx->fromUser ? [
                        'id'    => $tx->fromUser->id,
                        'name'  => $tx->fromUser->name,
                        'email' => $tx->fromUser->email,
                    ] : null,
                    'to_user'    => $tx->toUser ? [
                        'id'    => $tx->toUser->id,
                        'name'  => $tx->toUser->name,
                        'email' => $tx->toUser->email,
                    ] : null,
                    'note'       => $tx->note,
                    'created_at' => $tx->created_at,
                ];
            });

        return response()->json([
            'user_id'      => $user_id,
            'name'         => $user->name,
            'transactions' => $transactions,   // diperbaiki: dari 'data' → 'transactions'
            'total'        => $transactions->count(),
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
