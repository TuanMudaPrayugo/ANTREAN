<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyaratDokumen extends Model
{
   public function layanan()
    {
        // baris ini karena di tabel ada foreign key layanan_id
        return $this->belongsTo(KLayanan::class, 'layanan_id', 'id');
    }

}
