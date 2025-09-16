<?php

namespace App\Observers;

use App\Models\KIssue;

class KIssueObserver
{
    public function saving(KIssue $issue): void
    {
        $issue->applyNorms();
    }
}
