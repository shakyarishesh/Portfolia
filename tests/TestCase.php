<?php

namespace Tests;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        return $user;
    }

    protected function profile(array $attributes = []): Profile
    {
        return Profile::create($attributes + ['full_name' => 'Test Person', 'headline' => 'Backend Developer', 'intro' => 'Intro text.', 'email' => 'me@example.test']);
    }
}
