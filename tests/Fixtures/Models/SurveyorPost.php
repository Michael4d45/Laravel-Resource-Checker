<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyorUser extends Model
{
}

class SurveyorPost extends Model
{
    protected $fillable = ['title'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(SurveyorUser::class);
    }

    public function getStatusAttribute(): string
    {
        return 'draft';
    }
}