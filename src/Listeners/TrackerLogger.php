<?php

namespace Wonderfulso\WonderAb\Listeners;

use Wonderfulso\WonderAb\Events\Track;

class TrackerLogger
{
    /**
     * Handle the event.
     *
     * Note: In v2.0, events are sent via AnalyticsManager in the main PivotalAb class.
     * This listener is kept for backward compatibility but doesn't need to do anything.
     */
    public function handle(Track $track): void
    {
        // Events are now handled automatically by AnalyticsManager in PivotalAb::saveSession()
        // and PivotalAb::goal() methods. This listener is kept for compatibility.
    }
}
