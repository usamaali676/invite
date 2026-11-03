<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('gbp:create-admin {--name= : Full name} {--email= : Login email} {--password= : Password (prompted when omitted)}')]
#[Description('Create or update an FTS dashboard administrator')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $plain = $this->option('password') ?: password('Password (min 12 characters)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plain],
            ['name' => 'required|string|max:255', 'email' => 'required|email|max:255', 'password' => 'required|string|min:12'],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $name, 'password' => $plain, 'is_admin' => true])->save();

        $this->info("{$email} can now sign in at ".route('login').'.');

        return self::SUCCESS;
    }
}
