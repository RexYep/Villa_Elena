<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pinagsasaluhang status flow ng `HousekeepingTask` at `IssueReport`:
 * pending → in_progress → completed, o cancelled mula sa alinmang bukas.
 *
 * Ang bawat paglipat ay isang conditional UPDATE (`WHERE status IN (...)`),
 * hindi read-then-write: ang frontdesk ay isang monitor na maaaring
 * pindutin ng dalawang staff, at bukas din ang admin page sa parehong
 * oras. Kung naunahan na ang task, `false` ang balik at walang binago.
 */
trait HasWorkStatus
{
    public const OPEN_STATUSES = ['pending', 'in_progress'];

    public const STATUS_LABELS = [
        'pending'     => 'Pending',
        'in_progress' => 'In progress',
        'completed'   => 'Done',
        'cancelled'   => 'Cancelled',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function markStarted(): bool
    {
        return $this->transition(['pending'], [
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(): bool
    {
        return $this->transition(self::OPEN_STATUSES, [
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markCancelled(): bool
    {
        return $this->transition(self::OPEN_STATUSES, ['status' => 'cancelled']);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** CSS modifier na ginagamit ng admin at staff badge: `ws-pending`, … */
    public function getStatusClassAttribute(): string
    {
        return 'ws-' . str_replace('_', '-', (string) $this->status);
    }

    private function transition(array $from, array $changes): bool
    {
        $changes['updated_at'] = now();

        $updated = static::whereKey($this->getKey())
            ->whereIn('status', $from)
            ->update($changes);

        if ($updated) {
            $this->refresh();
        }

        return $updated > 0;
    }
}
