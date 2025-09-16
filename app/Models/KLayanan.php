<?php

namespace App\Models;

use App\Models\KStep;
use Illuminate\Database\Eloquent\Model;

class KLayanan extends Model
{
     protected $fillable = [
        'services_name',
        'status_layanan'
    ];

    public function steps(){
        return $this->hasMany(KStep::class, 'layanan_id','id');
    }

    public function issues()
    {
        return $this->hasMany(KIssue::class, 'layanan_id', 'id');
    }

    public function syaratDokumens()
    {
        // satu layanan punya banyak syarat dokumen
        return $this->hasMany(SyaratDokumen::class, 'layanan_id', 'id');
    }
}
