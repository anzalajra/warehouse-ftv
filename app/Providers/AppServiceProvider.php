<?php

namespace App\Providers;

use App\Models\Rental;
use App\Models\Setting;
use App\Models\FinanceTransaction;
use App\Models\JournalEntryItem;
use App\Observers\FinanceTransactionObserver;
use App\Observers\JournalEntryItemObserver;
use App\Observers\RentalObserver;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\Cart;
use App\Policies\CartPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

         \Illuminate\Support\Facades\URL::forceScheme('https');
         
        Rental::observe(RentalObserver::class);
        FinanceTransaction::observe(FinanceTransactionObserver::class);
        JournalEntryItem::observe(JournalEntryItemObserver::class);
    
        Gate::policy(Cart::class, CartPolicy::class);

        View::composer('pdf.*', function ($view) {
            $settings = Setting::where('key', 'like', 'doc_%')
                ->pluck('value', 'key')
                ->toArray();
            
            $view->with('doc_settings', $settings);
        });

        // Inject Theme Colors
        View::composer(['layouts.app', 'layouts.frontend', 'layouts.guest'], function ($view) {
            $primaryColor = \App\Services\ThemeService::getPrimaryColor();
            
            $cssVariables = [];
            foreach ($primaryColor as $shade => $value) {
                $cssVariables[] = "--primary-{$shade}: {$value};";
            }
            
            $view->with('themeCssVariables', implode(' ', $cssVariables));
        });

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                // Global App Config
                $siteName = Setting::get('site_name');
                if ($siteName) {
                    config(['app.name' => $siteName]);
                    
                    // Set default mail from name to site name if not explicitly configured
                    if (!Setting::get('mail_from_name')) {
                        config(['mail.from.name' => $siteName]);
                    }
                }

                if (Setting::get('notification_email_enabled')) {
                    $mailConfig = [];
                    
                    if ($mailer = Setting::get('mail_mailer')) $mailConfig['mail.default'] = $mailer;
                    if ($host = Setting::get('mail_host')) $mailConfig['mail.mailers.smtp.host'] = $host;
                    if ($port = Setting::get('mail_port')) $mailConfig['mail.mailers.smtp.port'] = $port;
                    if ($encryption = Setting::get('mail_encryption')) $mailConfig['mail.mailers.smtp.encryption'] = $encryption ?: null;
                    if ($username = Setting::get('mail_username')) $mailConfig['mail.mailers.smtp.username'] = $username;
                    if ($password = Setting::get('mail_password')) $mailConfig['mail.mailers.smtp.password'] = $password;
                    if ($fromAddress = Setting::get('mail_from_address')) $mailConfig['mail.from.address'] = $fromAddress;
                    if ($fromName = Setting::get('mail_from_name')) $mailConfig['mail.from.name'] = $fromName;

                    if (!empty($mailConfig)) {
                        config($mailConfig);
                    }
                }
            }
        } catch (\Exception $e) {
            // Settings table might not exist yet during migration
        }
    }
}