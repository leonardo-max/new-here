<?php

namespace LeonardoMax\NewHere\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $user_type
 * @property string $user_id
 * @property string $feature_key
 * @property Carbon $seen_at
 */
class SeenFeature extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('new-here.table', 'new_here_seen');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
        ];
    }
}
