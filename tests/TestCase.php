<?php

namespace Tests;

use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\User;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        return $user;
    }

    /** A published portfolio (default sections, profile) made current — what most tests work inside. */
    protected function portfolio(array $attributes = [], array $profile = []): Portfolio
    {
        $portfolio = Portfolio::provision($attributes + ['name' => 'Test Person', 'username' => 'test-person', 'status' => 'published'],
            $profile + ['full_name' => 'Test Person', 'headline' => 'Backend Developer', 'intro' => 'Intro text.', 'email' => 'me@example.test']);
        CurrentPortfolio::set($portfolio);

        return $portfolio;
    }

    protected function profile(array $attributes = []): Profile
    {
        $this->portfolio([], $attributes);

        return Profile::query()->firstOrFail();
    }
}
