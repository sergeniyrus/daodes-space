<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAssignment extends Model
{
    protected $table = 'order_assignments';

    protected $fillable = [
        'order_id',
        'employee_id',
        'role',
        'assigned_by_user_id',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'employee_id' => 'integer',
            'assigned_by_user_id' => 'integer',
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(OrganizationEmployee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
