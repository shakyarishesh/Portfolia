<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'portfolio:admin {email} {--name=Admin}';

    protected $description = 'Create an admin account, or reset an existing admin\'s password';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->secret('Password (min 12 characters)');
        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $this->secret('Confirm password')],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12', 'confirmed']],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $password, 'is_admin' => true]);
        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." admin {$email}.");

        return self::SUCCESS;
    }
}
