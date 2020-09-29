<?php

namespace OwowAgency\LaravelNotifications\Tests\Feature\Notifiables;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Route;
use OwowAgency\LaravelNotifications\Tests\TestCase;
use OwowAgency\LaravelNotifications\Tests\Support\Models\User;
use OwowAgency\LaravelNotifications\Tests\Support\Models\Notifiable;

class IndexTest extends TestCase
{
    /** @test */
    public function user_can_index_notifiable_notifications_if_allowed(): void
    {
        [$user, $notifiable] = $this->prepare();

        // Allow user to index notifiable's notifications.
        Gate::define('viewNotificationsOf', function (User $user, $target) use ($notifiable) {
            // Only return true if the `authorize` method is called with the correct
            // Notifiable instance.
            return $target->is($notifiable);
        });

        $response = $this->makeRequest($user, $notifiable);

        $this->assertResponse($response);
    }

    /** @test */
    public function user_cant_index_notifiable_notifications_if_disallowed(): void
    {
        [$user, $notifiable] = $this->prepare();

        // Disallow user to index notifiable's notifications.
        Gate::define('viewNotificationsOf', function (User $user, $target) use ($notifiable) {
            return ! $target->is($notifiable);
        });

        $response = $this->makeRequest($user, $notifiable);

        $this->assertResponse($response, 403);
    }

    /** @test */
    public function user_cant_index_notifiable_notifications_if_no_policy(): void
    {
        [$user, $notifiable] = $this->prepare();

        // Do not define policy.

        $response = $this->makeRequest($user, $notifiable);

        $this->assertResponse($response, 403);
    }

    /** @test */
    public function user_can_index_own_notifications(): void
    {
        [$user, $notifiable] = $this->prepare();

        // Allow user to index own notifications.
        Gate::define('viewNotificationsOf', function (User $user, $target) {
            // Only return true if the `authorize` method is called with the correct
            // User instance.
            return $user->is($target);
        });

        // User should be able to index own notifications.
        $response1 = $this->makeRequest($user, $user);
        $this->assertResponse($response1);

        // User should not be able to index notifiable's notifications.
        $response2 = $this->makeRequest($user, $notifiable);
        $this->assertResponse($response2, 403);
    }

    /** @test */
    public function user_can_index_own_notifications_custom_route(): void
    {
        // Instruct package to use custom routes.
        Route::indexNotifications('', User::class);
        Route::indexNotifications('players', User::class);
        Route::prefix('custom')->group(fn() => Route::indexNotifications('', User::class));
        
        [$user] = $this->prepare();

        Gate::define('viewNotificationsOf', function (User $user, $target) {
            return $user->is($target);
        });

        // User should be able to index own notifications from 'GET: /{id}/notifications'.
        $response1 = $this->makeRequest($user, $user, '');
        $this->assertResponse($response1);

        // User should be able to index own notifications from 'GET: /players/{id}/notifications'.
        $response2 = $this->makeRequest($user, $user, 'players');
        $this->assertResponse($response2);

        // User should be able to index own notifications from 'GET: /custom/{id}/notifications'.
        $response3 = $this->makeRequest($user, $user, 'custom');
        $this->assertResponse($response3);
    }

    /**
     * Helper Methods
     * ========================================================================
     */

    /**
     * Prepares for tests.
     *
     * @return array
     */
    private function prepare(): array
    {
        // Prepare the API endpoints (routes).
        Route::indexNotifications('notifiables', Notifiable::class);
        Route::indexNotifications('users', User::class);
        
        return $this->prepareNotifications();
    }

    /**
     * Makes a request.
     *
     * @param  \OwowAgency\LaravelNotifications\Tests\Support\Models\User  $user
     * @param  \Illuminate\Notifications\Notifiable  $notifiable
     * @param  string  $prefix
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    private function makeRequest(User $user, $notifiable, string $prefix = null): TestResponse
    {
        if (is_null($prefix)) {
            $prefix = $notifiable instanceof User ? 'users' : 'notifiables';
        }
        
        return $this
            ->actingAs($user)
            ->json('GET', "$prefix/$notifiable->id/notifications");
    }
}