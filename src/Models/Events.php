<?php

namespace Wonderfulso\WonderAb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wonderfulso\WonderAb\Events\Track;

/**
 * @property int $id
 * @property string $name
 * @property string $value
 * @property int $instance_id
 * @property int $experiments_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Instance|null $instance
 * @property-read Experiments|null $experiment
 */
class Events extends Model
{
    protected $table = 'ab_events';

    protected $fillable = ['name', 'value', 'instance_id', 'experiments_id'];

    protected $touches = ['instance'];

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
        /** @var Instance|null $instance */
        $instance = $this->instance()->first();

        return ! empty($instance) ? $instance->instance : null;
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo('Wonderfulso\WonderAb\Models\Experiments', 'experiments_id', 'id');
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
        /** @var Experiments|null $experimentModel */
        $experimentModel = $this->experiment()->first();

        $data['instance'] = $instanceModel ? $instanceModel->instance : null;
        $data['experiment'] = $experimentModel ? $experimentModel->experiment : null;

        return $data;
    }
}
