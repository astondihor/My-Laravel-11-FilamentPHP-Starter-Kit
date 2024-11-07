# Catatan

## Install

```bash
composer create-project laravel/laravel shop.solusiwebdesign --prefer-dist
cd shop.solusiwebdesign
npm install && npm run dev
php artisan storage:link
```

### Install Laravel Folio

```bash
composer require laravel/folio
php artisan folio:install
```

### Install Jetstream

```bash
composer require laravel/jetstream
php artisan jetstream:install livewire --dark
npm install && npm run build
```

### Install Doctrine/dbal

```bash
composer require doctrine/dbal
```

### Install [laravel-debugbar](https://github.com/barryvdh/laravel-debugbar)

```bash
composer require barryvdh/laravel-debugbar --dev
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"
```

### Install [Laravel-IDE-helper](https://github.com/barryvdh/laravel-ide-helper)

```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan clear-compiled
php artisan ide-helper:generate
```

configure your `composer.json` to do this each time you update your dependencies:

```json
"scripts": {
    "post-update-cmd": [
        "Illuminate\\Foundation\\ComposerScripts::postUpdate",
        "@php artisan ide-helper:generate",
        "@php artisan ide-helper:meta"
    ]
},
```

## Change primary key to UUID

from:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
```

to:

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
```

#### Change sessions table migration


```php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
```

to:

```php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignUuid('user_id')->nullable()->index();
```

#### Change other migrations to `users`

from:

```php
$table->foreignId('user_id')->constrained();
```

to:

```php
$table->foreignUuid('user_id')->constrained();
```

#### Also, polymorphic relations for the default personal_access_tokens table:

from:

```php
Schema::create('personal_access_tokens', function (Blueprint $table) {
    $table->morphs('tokenable');
```

to:

```php
Schema::create('personal_access_tokens', function (Blueprint $table) {
    $table->uuidMorphs('tokenable');
```

#### Add trait to User Model

Finally, add a trait `HasUuids` (36 characters) to the User model.

`app/Models/User.php`:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Model
{
    use HasUuids;
```

or `ULID` (26 characters)

```php
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasUlids;

    // ...
}

$user = User::create(['name' => 'Traveling to Asia', 'email' => 'anu@email.com', 'password' => 'password']);

$user->id; // "01gd4d3tgrrfqeda94gdbtdk5c"
```

### Change `name` in `users` table to `username`

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
```

to:

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('username')->unique();
    $table->string('firstname');
    $table->string('lastname');
    $table->string('email')->unique();
```

#### Modify the User Model

from:

```php
protected $fillable = [
    'name',
    'email',
    'password',
];
```

to:

```php
// ...
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    // ...
    use SoftDeletes;
    // ...

    protected $fillable = [
        'username',
        'firstname',
        'lastname',
        'email',
        'password',
    ];
```

#### Modify the Registration view

Go to `resources/views/auth/register.blade.php` and modify to this

```php
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                <x-label for="firstname" value="{{ __('First Name') }}" />
                <x-input id="firstname" class="block mt-1 w-full" type="text" name="firstname" :value="old('firstname')" required autofocus autocomplete="firstname" />
            </div>

            <div class="mt-4">
                <x-label for="lastname" value="{{ __('Last Name') }}" />
                <x-input id="lastname" class="block mt-1 w-full" type="text" name="lastname" :value="old('lastname')" autocomplete="lastname" />
            </div>

            <div class="mt-4">
                <x-label for="username" value="{{ __('Username') }}" />
                <x-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="mt-4">
                    <x-label for="terms">
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms" required />

                            <div class="ms-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-button class="ms-4">
                    {{ __('Register') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
```

#### (Optional, if yout want to change login from email to username) Modify the Login view

Go to `resources/views/auth/login.blade.php` and modify to this

```php
<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('status') }}
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-jet-label value="{{ __('Username') }}" />
                <x-jet-input class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus />
            </div>

            <div class="mt-4">
                <x-jet-label value="{{ __('Password') }}" />
                <x-jet-input class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <input type="checkbox" class="form-checkbox" name="remember">
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
                @endif

                <x-jet-button class="ml-4">
                    {{ __('Login') }}
                </x-jet-button>
            </div>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
```

#### (Optional, if yout want to change login from email to username) Modify the Fortify configuration file

go to `config/fortify.php`, you will see an array, find the **username** key and change it to **username** from **email**

```php
'username' => 'login',
```

Then clear your config and cache

```bash
php artisan config:cache
```

#### Go to `app/Actions/Fortify/CreateNewUser.php` customize the create method to this

```php
public function create(array $input)
{
    Validator::make($input, [
        'firstname' => ['required', 'string', 'max:255'],
        'lastname' => ['required', 'string', 'max:255'],
        'username' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'password' => $this->passwordRules(),
    ])->validate();

    return User::create([
        'firstname' => $input['firstname'],
        'lastname' => $input['lastname'],
        'username' => $input['username'],
        'email' => $input['email'],
        'password' => Hash::make($input['password']),
    ]);
}
```

In the `validate( )` method, If you want to login with `username`, then removed **email**, and added **firstname**, **lastname** and **username**. Also in the `User::create`, I added the new fields.

#### Modify `FortifyServiceProvider.php` (Optional, if you want to use username for login too)

```php
namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use App\Models\User;
use Hash;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function(Request $request){
            $user = User::where("email", $request->login)
                        ->orWhere("username", $request->login)
                        ->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
```

#### Modify jetstream Admin Panel Navigation Bar

Goto `resources/views/navigation-menu.blade.php` and change `Auth::user()->name` to `Auth::user()->username`

```php
<!-- Settings Dropdown -->
<div class="ms-3 relative">
    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                <button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                    <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->username }}" />
                </button>
            @else
                <span class="inline-flex rounded-md">
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                        {{ Auth::user()->username }}

// ...

<!-- Responsive Settings Options -->
<div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
    <div class="flex items-center px-4">
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div class="shrink-0 me-3">
                <img class="h-10 w-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->username }}" />
            </div>
        @endif

        <div>
            <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->username }}</div>
```

#### Modify jetstream Profile Page

Goto `app/Actions/Fortify/UpdateUserProfileInformation.php` and change to this:

```php
namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'firstname' => $input['firstname'],
                'lastname' => $input['lastname'],
                'username' => $input['username'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'firstname' => $input['firstname'],
            'lastname' => $input['lastname'],
            'username' => $input['username'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
```

Goto `resources/views/profile/update-profile-information-form.blade.php` and change to this:

```php
<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div class="mt-2" x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="rounded-full h-20 w-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Select A New Photo') }}
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Remove Photo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- First Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="firstname" value="{{ __('First Name') }}" />
            <x-input id="firstname" type="text" class="mt-1 block w-full" wire:model="state.firstname" required autocomplete="firstname" />
            <x-input-error for="firstname" class="mt-2" />
        </div>

        <!-- Last Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lastname" value="{{ __('Last Name') }}" />
            <x-input id="lastname" type="text" class="mt-1 block w-full" wire:model="state.lastname" required autocomplete="lastname" />
            <x-input-error for="lastname" class="mt-2" />
        </div>

        <!-- Username -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="username" value="{{ __('Username') }}" />
            <x-input id="username" type="text" class="mt-1 block w-full" wire:model="state.username" required autocomplete="username" />
            <x-input-error for="username" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="email" value="{{ __('Email') }}" />
            <x-input id="email" type="email" class="mt-1 block w-full" wire:model="state.email" required autocomplete="email" />
            <x-input-error for="email" class="mt-2" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <p class="text-sm mt-2 dark:text-white">
                    {{ __('Your email address is unverified.') }}

                    <button type="button" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" wire:click.prevent="sendEmailVerification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
```

Source:
- [Customize Laravel Jetstream Registration and Login Example](https://www.itsolutionstuff.com/post/customize-laravel-jetstream-registration-and-login-exampleexample.html)
- [Customize Laravel Jetstream (Registration and Login)](https://dev.to/kingsconsult/customize-laravel-jetstream-registration-and-login-210f)

## Install TailwindCSS PostCSS preprocessor

```bash
npm install -D postcss-import postcss-nesting sass
```

Modify `postcss.config.js`:

```js
// postcss.config.js
module.exports = {
  plugins: {
    'postcss-import': {},
    'tailwindcss/nesting': 'postcss-nesting',
    tailwindcss: {},
    autoprefixer: {},
  }
}
```

create these files:

- `resources/sass/app.scss`
- `resources/sass/_custom-base-styles.scss`
- `resources/sass/_custom-components.scss`
- `resources/sass/_custom-utilities.scss`

Fill `resources/sass/app.scss` with this code:

```scss
@import "tailwindcss/base";
@import "./custom-base-styles.scss";

@import "tailwindcss/components";
@import "./custom-components.scss";

@import "tailwindcss/utilities";
@import "./custom-utilities.scss";
```

Edit `vite.config.js`

```js
input: [
    'resources/sass/app.scss',
    'resources/js/app.js',
],
```

Edit `resources/views/layouts/app.blade.php`:

```php
<!-- Scripts -->
@vite(['resources/sass/app.scss', 'resources/js/app.js'])
```

Edit `resources/views/layouts/guest.blade.php`

```php
<!-- Scripts -->
@vite(['resources/sass/app.scss', 'resources/js/app.js'])
```

## Enabling Profile Photos

Open `config/jetstream.php`, and modify `features` configuration.

```php
'features' => [
    Features::termsAndPrivacyPolicy(),
    Features::profilePhotos(),
    Features::api(),
    // Features::teams(['invitations' => true]),
    Features::accountDeletion(),
],
```

if you not done execute the `storage:link`, now it's time to run that command:

```bash
php artisan storage:link
php artisan optimize:clear
```