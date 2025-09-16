<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = ['layanan_id','kode','ticket_date','status'];

    protected $casts = [
        'ticket_date' => 'date',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    protected static function booted() {
        static::creating(function ($t) {
            if (!$t->ticket_date) {
                $t->ticket_date = now('Asia/Jakarta')->toDateString();
            }
            if (!$t->status) $t->status = 'running';
        });
    }

    // scopes ringkas
    public function scopeActive($q) { return $q->where('status','running'); }
    public function scopeToday($q)  { return $q->whereDate('ticket_date', now('Asia/Jakarta')); }

    // relasi
    public function layanan(){ return $this->belongsTo(KLayanan::class,'layanan_id'); }
    public function progresses(){ return $this->hasMany(Progress::class,'ticket_id'); }

    
}
