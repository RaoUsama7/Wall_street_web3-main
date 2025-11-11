<?php

namespace App\Providers;

use App\Constants\Status;
use App\Lib\Searchable;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\P2P\Trade;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        Builder::mixin(new Searchable);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        // Ensure asset URLs are generated correctly
        if (empty(env('ASSET_URL'))) {
            // Automatically detect the correct base URL from the request
            if (app()->runningInConsole() === false && request()->hasHeader('Host')) {
                // Get the actual scheme from the request (not forced)
                $scheme = request()->getScheme();
                
                // For local development on common ports, ensure HTTP is used
                $host = request()->getHost();
                $port = request()->getPort();
                
                // If it's localhost/127.0.0.1 on port 8000, force HTTP
                if (in_array($host, ['localhost', '127.0.0.1', '::1']) && $port == 8000) {
                    $scheme = 'http';
                } else {
                    // Only use HTTPS if the request actually came via HTTPS
                    $isActuallyHttps = request()->server('HTTPS') === 'on' || 
                                       request()->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
                                       $scheme === 'https';
                    
                    if (!$isActuallyHttps) {
                        // Force HTTP if not actually HTTPS
                        $scheme = 'http';
                    }
                }
                
                $rootUrl = $scheme . '://' . $host . ($port && !in_array($port, [80, 443]) ? ':' . $port : '');
                URL::forceRootUrl($rootUrl);
                URL::forceScheme($scheme);
            }
        }

        if (!cache()->get('SystemInstalled')) {
            $envFilePath = base_path('.env');
            if (!file_exists($envFilePath)) {
                header('Location: install');
                exit;
            }
            $envContents = file_get_contents($envFilePath);
            if (empty($envContents)) {
                header('Location: install');
                exit;
            } else {
                cache()->put('SystemInstalled', true);
            }
        }

        $viewShare['emptyMessage'] = 'Data not found';
        view()->share($viewShare);

        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount'           => User::banned()->count(),
                'emailUnverifiedUsersCount'  => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount' => User::mobileUnverified()->count(),
                'kycUnverifiedUsersCount'    => User::kycUnverified()->count(),
                'kycPendingUsersCount'       => User::kycPending()->count(),
                'pendingTicketCount'         => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount'       => Deposit::pending()->count(),
                'pendingWithdrawCount'       => Withdrawal::pending()->count(),
                'reportedTrade'              => Trade::reported()->count(),
                'updateAvailable'            => version_compare(gs('available_version'), systemDetails()['version'], '>') ? 'v' . gs('available_version') : false,
            ]);
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications'     => AdminNotification::where('is_read', Status::NO)->with('user')->orderBy('id', 'desc')->take(10)->get(),
                'adminNotificationCount' => AdminNotification::where('is_read', Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });

        // Only force HTTPS if the request is actually HTTPS, or in production environment
        // This should run AFTER URL detection to avoid conflicts
        if (gs('force_ssl')) {
            $host = request()->getHost();
            $port = request()->getPort();
            
            // Never force HTTPS for localhost/127.0.0.1 on port 8000 (common dev server)
            if (in_array($host, ['localhost', '127.0.0.1', '::1']) && $port == 8000) {
                \URL::forceScheme('http');
            } else {
                // Check if request is actually HTTPS or if we're in production
                $isHttps = request()->getScheme() === 'https' || 
                           request()->server('HTTPS') === 'on' || 
                           request()->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
                           app()->environment('production');
                
                if ($isHttps) {
                    \URL::forceScheme('https');
                } else {
                    // In development, use HTTP even if force_ssl is enabled
                    \URL::forceScheme('http');
                }
            }
        }

        Paginator::useBootstrapFive();

    }
}
