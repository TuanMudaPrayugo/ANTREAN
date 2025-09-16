<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Progress extends Model
{
    protected $table = 'progress';

    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_STOPPED = 'stopped';

    protected $fillable = [
        'ticket_id',
        'step_id',
        'started_at',
        'ended_at',
        'status',
        'issue_id',      // legacy (single issue)
        'issue_note',    // kalau kolom ini ada—boleh abaikan jika tidak ada
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    protected $hidden = ['pivot'];

    // Relations
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(KStep::class, 'step_id');
    }

    // Legacy single-issue
    public function issue(): BelongsTo
    {
        return $this->belongsTo(KIssue::class, 'issue_id');
    }

    // Many-to-many issues via pivot
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(
            KIssue::class,
            'progress_issue',
            'progress_id',
            'issue_id'
        )->withTimestamps(); // pivot punya created_at/updated_at (sesuai SS kamu)
    }

    // Helpers
    public function durationSeconds(): ?int
    {
        if (!$this->started_at) return null;
        $end = $this->ended_at ?? now();
        return $end->diffInSeconds($this->started_at);
    }

    // Scopes
    public function scopeForTicket($q, int $ticketId) { return $q->where('ticket_id', $ticketId); }
    public function scopeRunning($q) { return $q->where('status', self::STATUS_RUNNING); }
    public function scopeDone($q)    { return $q->where('status', self::STATUS_DONE); }
}
