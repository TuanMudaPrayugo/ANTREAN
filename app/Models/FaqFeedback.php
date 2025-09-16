<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqFeedback extends Model
{
    protected $table = 'faq_feedbacks';
    protected $fillable = [
        'issue_id','session_id','user_query','is_helpful','alternatives'
    ];

    // cast manual biar kompatibel semua versi
    protected $attributes = ['alternatives' => '[]'];

    public function getAlternativesAttribute($v)
    {
        if (is_array($v)) return $v;
        $arr = json_decode($v ?? '[]', true);
        return is_array($arr) ? $arr : [];
    }
    public function setAlternativesAttribute($v)
    {
        $this->attributes['alternatives'] = json_encode(
            is_array($v) ? array_values($v) : (json_decode($v ?? '[]', true) ?: [])
        );
    }

    public function issue()
    {
        return $this->belongsTo(KIssue::class, 'issue_id');
    }
}
