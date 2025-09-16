<?php

namespace App\Models;

use App\Models\KLayanan;
use Illuminate\Database\Eloquent\Model;

class KStep extends Model
{

     protected $fillable = [
        'layanan_id',
        'service_step_name',
        'step_order',
        'std_step_time'
    ];    

    public function layanan() {
        return $this->belongsTo(KLayanan::class, 'layanan_id','id');
    }

    public function issues()
{
    return $this->hasMany(KIssue::class, 'steplayanan_id', 'id');
}
}
