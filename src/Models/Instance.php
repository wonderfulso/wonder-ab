<?php

namespace Wonderfulso\WonderAb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wonderfulso\WonderAb\Events\Track;

/**
 * @property int $id
 * @property string $instance
 * @property string|null $identifier
 * @property array $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, Events> $events
 * @property \Illuminate\Database\Eloquent\Collection<int, Goal> $goals
 */
class Instance extends Model
{
    protected $table = 'ab_instance';

    protected $fillable = ['instance', 'metadata', 'identifier'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            event(new Track($model));
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany('Wonderfulso\WonderAb\Models\Events');
    }

    public function goals(): HasMany
    {
        return $this->hasMany('Wonderfulso\WonderAb\Models\Goal');
    }

    public function toExport(): array
    {
        return $this->toArray();
    }
}
