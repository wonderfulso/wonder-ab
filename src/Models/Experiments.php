<?php

namespace Wonderfulso\WonderAb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wonderfulso\WonderAb\Events\Track;

/**
 * @property int $id
 * @property string $experiment
 * @property string $goal
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Experiments extends Model
{
    protected $table = 'ab_experiments';

    protected $fillable = ['experiment', 'goal'];

    public static function boot(): void
    {
        parent::boot();
        self::created(function ($model) {
            event(new Track($model));
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany('Wonderfulso\WonderAb\Models\Events');
    }

    public function toExport(): array
    {
        return $this->toArray();
    }
}
