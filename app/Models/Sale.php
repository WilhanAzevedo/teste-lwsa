<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'total_amount',
        'total_cost',
        'total_profit',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_cost'   => 'decimal:2',
        'total_profit' => 'decimal:2',
        'status'       => SaleStatus::class, 
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}