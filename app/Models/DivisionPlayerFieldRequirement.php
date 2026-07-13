<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DivisionPlayerFieldRequirement extends Model
{
    use HasFactory;

    public const FIELDS = [
        'name' => ['label' => 'Name', 'default' => true, 'locked' => true],
        'gender' => ['label' => 'Gender', 'default' => true, 'locked' => false],
        'email' => ['label' => 'Email', 'default' => true, 'locked' => false],
        'phone' => ['label' => 'Phone', 'default' => true, 'locked' => false],
        'level' => ['label' => 'Level', 'default' => true, 'locked' => false],
        'dob' => ['label' => 'Date of Birth', 'default' => false, 'locked' => false],
    ];

    protected $fillable = [
        'division_id',
        'field_key',
        'required',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
