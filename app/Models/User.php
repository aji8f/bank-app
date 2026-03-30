<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Get transactions where user is the sender.
     */
    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_user_id');
    }

    /**
     * Get transactions where user is the receiver.
     */
    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'to_user_id');
    }
}
