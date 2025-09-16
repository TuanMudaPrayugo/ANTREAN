<?php

namespace App\Providers;

use App\Models\KIssue;
use App\Observers\KIssueObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        KIssue::observe(KIssueObserver::class);

        if ($xfh = request()->header('X-Forwarded-Host')) {
        $proto = request()->header('X-Forwarded-Proto', request()->isSecure() ? 'https' : 'http');
        $root  = $proto.'://'.$xfh;

        URL::forceRootUrl($root);
        if ($proto === 'https') {
            URL::forceScheme('https');
        }

        // optional: supaya helper lain juga konsisten
        config(['app.url' => $root, 'app.asset_url' => null]);
    }

}
}
