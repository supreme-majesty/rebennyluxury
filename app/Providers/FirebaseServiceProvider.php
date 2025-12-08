<?php

namespace App\Providers;

use Kreait\Firebase\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Exception;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            try {
                // Check if database is available and table exists
                // Wrap in try-catch as database connection might not be ready during registration
                try {
                    if (!Schema::hasTable('business_settings')) {
                        throw new Exception('Database table not available');
                    }
                } catch (\Exception $dbException) {
                    // Database not ready yet, return unconfigured factory
                    return new Factory();
                }
                
                $firebaseConfig = getWebConfig('push_notification_key');
                
                if (empty($firebaseConfig)) {
                    throw new Exception('Firebase configuration not found');
                }
                
                return (new Factory)->withServiceAccount($firebaseConfig);
            } catch (Exception $e) {
                // Return a factory instance without service account if config is missing
                // This allows the app to continue running even if Firebase isn't configured
                \Log::warning('Firebase Factory initialization failed: ' . $e->getMessage());
                return new Factory();
            }
        });

        $this->app->singleton(Auth::class, function ($app) {
            try {
                $factory = $app->make(Factory::class);
                // Check if factory was properly initialized
                if ($factory && method_exists($factory, 'createAuth')) {
                    return $factory->createAuth();
                }
            } catch (Exception $e) {
                // Log error but don't break the application
                \Log::warning('Firebase Auth initialization failed: ' . $e->getMessage());
            }
            // Return a mock/null object that won't break type hints
            return null;
        });

        $this->app->singleton(Messaging::class, function ($app) {
            try {
                $factory = $app->make(Factory::class);
                // Check if factory was properly initialized
                if ($factory && method_exists($factory, 'createMessaging')) {
                    return $factory->createMessaging();
                }
            } catch (Exception $e) {
                // Log error but don't break the application
                \Log::warning('Firebase Messaging initialization failed: ' . $e->getMessage());
            }
            // Return a mock/null object that won't break type hints
            return null;
        });

        // Optionally, you can bind it to a simpler alias
        $this->app->alias(Messaging::class, 'firebase.messaging');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
