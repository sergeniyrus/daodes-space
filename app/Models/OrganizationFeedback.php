<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationFeedback extends Model
{
    protected $table = 'organization_feedback';

    protected $fillable = [
        'organization_id', 'customer_id', 'order_id', 'type', 'subject',
        'message', 'status', 'reply', 'replied_by_user_id',
        'replied_by_employee_id', 'replied_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'customer_id' => 'integer',
            'order_id' => 'integer',
            'replied_by_user_id' => 'integer',
            'replied_by_employee_id' => 'integer',
            'replied_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function repliedByUser(): BelongsTo { return $this->belongsTo(User::class, 'replied_by_user_id'); }
    public function repliedByEmployee(): BelongsTo { return $this->belongsTo(OrganizationEmployee::class, 'replied_by_employee_id'); }
}
