<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriIssue extends Model
{
    protected $fillable = [
        'category_name'
    ];

    public function issues()
    {
        return $this->hasMany(KIssue::class, 'categoryissue_id', 'id');
    }
}
