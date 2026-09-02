<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rules\Password;

class AccountProfile extends Page
{
    protected string $view = 'filament.pages.account-profile';

    protected static string $routePath = '/profile';

    protected static ?string $title = 'My Profile';

    protected static bool $shouldRegisterNavigation = false;

    public string $name = '';

    public string $email = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function saveIdentity(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255']]);
        auth()->user()->update($data);

        Notification::make()->success()->title('Profile updated')->send();
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password:web'],
            'password' => ['required', Password::default(), 'same:passwordConfirmation'],
        ]);

        $user = auth()->user();
        $user->update(['password' => $this->password]);
        session()->put('password_hash_web', $user->fresh()->getAuthPassword());
        $this->reset(['currentPassword', 'password', 'passwordConfirmation']);

        Notification::make()->success()->title('Password changed')->body('Privileged Central access will require multi-factor verification again.')->send();
    }
}
