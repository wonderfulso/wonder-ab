<?php

namespace Wonderfulso\WonderAb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wonderfulso\WonderAb\Events\Track;

/**
 * @property int $id
 * @property string $goal
 * @property mixed $value
 * @property int $instance_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Instance|null $instance
 */
class Goal extends Model
{
    protected $table = 'ab_goal';

    protected $fillable = ['goal', 'value', 'instance_id'];

    protected $appends = ['instance'];

    public static function boot(): void
    {
        parent::boot();
        self::created(function ($model) {
            event(new Track($model));
        });
    }

    public function getInstanceAttribute(): ?string
    {
        /** @var Instance|null $instanceModel */
        $instanceModel = $this->instance()->first();

        return $instanceModel ? $instanceModel->instance : null;
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo('Wonderfulso\WonderAb\Models\Experiment');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo('Wonderfulso\WonderAb\Models\Instance');
    }

    public function toExport(): array
    {
        $data = $this->toArray();
        /** @var Instance|null $instanceModel */
        $instanceModel = $this->instance()->first();
        $data['instance'] = $instanceModel ? $instanceModel->instance : null;

        return $data;
    }
}
