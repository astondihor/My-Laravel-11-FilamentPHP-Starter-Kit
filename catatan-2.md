# Catatan 2

# Filament Starter kit

## 1. Install FilamentPHP

```bash
composer require filament/filament:"^3.2" -W
php artisan filament:install --panels
php artisan make:filament-user
```

```php
namespace App\Models;
 
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
 
class User extends Authenticatable implements FilamentUser
{
    // ...
 
    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@yourdomain.com') && $this->hasVerifiedEmail();
    }
    
    public function getFilamentName(): string
    {
        return $this->username;
    }

    // Define an accessor for the 'name' attribute
    public function getNameAttribute()
    {
        return "{$this->firstname} {$this->lastname}";
    }
}
```

```bash
php artisan vendor:publish --tag=filament-config
php artisan vendor:publish --tag=filament-panels-translations
php artisan vendor:publish --tag=filament-actions-translations
php artisan vendor:publish --tag=filament-forms-translations
php artisan vendor:publish --tag=filament-infolists-translations
php artisan vendor:publish --tag=filament-notifications-translations
php artisan vendor:publish --tag=filament-tables-translations
php artisan vendor:publish --tag=filament-translations
composer require flowframe/laravel-trend
```

## 2. Database Notifications

```bash
php artisan make:notifications-table
php artisan migrate
```

Adding the database notifications modal to a panel:

```php
use Filament\Panel;
 
public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->databaseNotifications()->databaseNotificationsPolling('30s')
        // ...
}
```

## 3. Custom Login Page

create file `app/Filament/Pages/Auth/Login.php`

```bash
php artisan make:class Filament/Pages/Auth/Login.php
```

```php
<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BasePage;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BasePage
{
    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'email' => 'superadmin@starter-kit.com',
            'password' => 'superadmin',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent()->label('Email'),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }
}
```

## 4. Custom Request Password Reset

create file `app/Filament/Pages/Auth/PasswordReset/RequestPasswordReset.php`

```bash
php artisan make:class Filament/Pages/Auth/PasswordReset/RequestPasswordReset.php
```

```php
<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use App\Settings\MailSettings;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Notifications\Auth\ResetPassword as ResetPasswordNotification;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected static string $view = 'filament-panels::pages.auth.password-reset.request-password-reset';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent()->label('Email'),
            ]);
    }

    public function request(): void
    {
        try {
            $this->rateLimit(3);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/password-reset/request-password-reset.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/password-reset/request-password-reset.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/password-reset/request-password-reset.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $data,
            function (CanResetPassword $user, string $token): void {
                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                }

                $settings = app(MailSettings::class);
                $notification = new ResetPasswordNotification($token);
                $notification->url = Filament::getResetPasswordUrl($token, $user);

                $settings->loadMailSettingsToConfig();

                $user->notify($notification);
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                ->title(__($status))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__($status))
            ->success()
            ->send();

        $this->form->fill();
    }
}
```

## 5. Custom Email Verification

create file `app/Filament/Pages/Auth/EmailVerification/EmailVerificationPrompt.php`

```bash
php artisan make:class Filament/Pages/Auth/EmailVerification/EmailVerificationPrompt.php
```

```php
<?php

namespace App\Filament\Pages\Auth\EmailVerification;

use App\Settings\MailSettings;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\VerifyEmail;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt as BasePage;

class EmailVerificationPrompt extends BasePage
{
    protected static string $view = 'filament-panels::pages.auth.email-verification.email-verification-prompt';

    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')
            ->link()
            ->label(__('filament-panels::pages/auth/email-verification/email-verification-prompt.actions.resend_notification.label') . '.')
            ->action(function (MailSettings $settings = null): void {
                try {
                    $this->rateLimit(2);
                } catch (TooManyRequestsException $exception) {
                    Notification::make()
                        ->title(__('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resend_throttled.title', [
                            'seconds' => $exception->secondsUntilAvailable,
                            'minutes' => ceil($exception->secondsUntilAvailable / 60),
                        ]))
                        ->body(array_key_exists('body', __('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resend_throttled') ?: []) ? __('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resend_throttled.body', [
                            'seconds' => $exception->secondsUntilAvailable,
                            'minutes' => ceil($exception->secondsUntilAvailable / 60),
                        ]) : null)
                        ->danger()
                        ->send();

                    return;
                }

                $user = Filament::auth()->user();

                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                }

                $notification = new VerifyEmail();
                $notification->url = Filament::getVerifyEmailUrl($user);

                $settings->loadMailSettingsToConfig();

                $user->notify($notification);

                Notification::make()
                    ->title(__('filament-panels::pages/auth/email-verification/email-verification-prompt.notifications.notification_resent.title'))
                    ->success()
                    ->send();
            });
    }

    public function getTitle(): string | Htmlable
    {
        return __('filament-panels::pages/auth/email-verification/email-verification-prompt.title');
    }

    public function getHeading(): string | Htmlable
    {
        return __('filament-panels::pages/auth/email-verification/email-verification-prompt.heading');
    }
}
```

Edit AdminPanelProvider

```php
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use App\Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...  
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class)
            ->emailVerification(EmailVerificationPrompt::class)
            // ...
```

## 6. Custom Application Info Widget

```bash
php artisan make:filament-widget ApplicationInfo
```

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ApplicationInfo extends Widget
{
    protected static ?int $sort = -2;

    protected static string $view = 'filament.widgets.application-info';
}
```

```php
<x-filament-widgets::widget>
    <x-filament::section>
        <p class="text-base font-semibold leading-6 text-gray-950 dark:text-white">{{ config('app.name') }}</p>
        <p style="margin-top: 2px;margin-bottom: 2px;" class="text-xs text-gray-500 dark:text-gray-400">{{
            env('APP_VERSION') ? "v".env('APP_VERSION'): '' }}</p>
    </x-filament::section>
</x-filament-widgets::widget>
```

## 7. Panel Footer

Create file `resources/views/filament/components/panel-footer.blade.php`

```php
<footer class="flex items-center justify-between w-full px-4 py-8 font-medium">
    <span class="text-sm text-center text-gray-400 dark:text-gray-300">
        <a href="#" class="hover:underline">{{ config('app.name') }}</a> {{
            env('APP_VERSION') ? "v".env('APP_VERSION'): '' }}
    </span>
    <span class="text-sm text-center text-gray-400 dark:text-gray-300">©{{ date('Y') }} All Rights
        Reserved.
    </span>
</footer>
```

AppServiceProvider

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ...
        
        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn (): View => view('filament.components.panel-footer'),
        );
```

## 8. Website Link Button

Create file `resources/views/filament/components/button-website.blade.php`

```php
<a
    href="{{ config('app.url') }}"
    class="fi-icon-btn relative flex items-center justify-center border dark:border-none rounded-lg bg-gray-50 dark:bg-gray-800 transition duration-75 focus-visible:ring-2 -m-1.5 ml-2 h-7 w-7 text-gray-400 hover:text-gray-500 focus-visible:ring-primary-600 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:ring-primary-500 fi-color-gray"
    title="Go to Website"
    target="blank"
>
    <x-fluentui-arrow-exit-20 class="w-4 h-4 fi-icon-btn-icon" />
</a>
```

AppServiceProvider

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ...
        
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn (): View => view('filament.components.button-website'),
        );
```

## 9. Install Blade FluentUI System Icons

`codeat3/blade-fluentui-system-icons`

```bash
composer require codeat3/blade-fluentui-system-icons
php artisan vendor:publish --tag=blade-fluentui-system-icons-config
```

## 10. Install Filament Menu Builder Plugin

`datlechin/filament-menu-builder`

```bash
composer require datlechin/filament-menu-builder
php artisan vendor:publish --tag="filament-menu-builder-migrations"
php artisan migrate
php artisan vendor:publish --tag="filament-menu-builder-config"
php artisan vendor:publish --tag="filament-menu-builder-views"
```

Create `app/Filament/Resources/MenuResource/Pages/CreateMenu.php`

```php
<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;
}
```

Create `app/Filament/Resources/MenuResource/Pages/EditMenu.php`

```php
<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
```

Create `app/Filament/Resources/MenuResource/Pages/ListMenus.php`

```php
<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

Create `app/Filament/Resources/MenuResource.php`

```php
<?php

namespace App\Filament\Resources;

use Datlechin\FilamentMenuBuilder\Resources\MenuResource as BaseMenuResource;

class MenuResource extends BaseMenuResource
{
    protected static ?int $navigationSort = 99;

    protected static ?string $navigationIcon = 'fluentui-navigation-16';

    public static function getNavigationGroup(): ?string
    {
        return __("menu.nav_group.settings");
    }
}
```

Add the plugin to AdminPanelProvider:

```php
// ...

$panel
    // ...
    ->navigationGroups([
        Navigation\NavigationGroup::make()
            ->label(__('menu.nav_group.settings'))
            ->collapsed(),
    ])
    // ...
    ->plugins([
        // ...
        \Datlechin\FilamentMenuBuilder\FilamentMenuBuilderPlugin::make()
            ->usingResource(\App\Filament\Resources\MenuResource::class)
            ->addMenuPanels([
                \Datlechin\FilamentMenuBuilder\MenuPanel\StaticMenuPanel::make()
                    ->addMany([
                        'Home' => url('/'),
                        'Blog' => url('/blog'),
                    ])
                    ->description('Default menus')
                    ->collapsed(true)
                    ->collapsible(true)
                    ->paginate(perPage: 5, condition: true)
            ]),
    ]);
```

### Add Menu Lang file

`lang/en/menu.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    */
    'nav_group.banner' => 'Banner',
    'nav_group.blog' => 'Blog',
    'nav_group.access' => 'Access',
    'nav_group.settings' => 'Settings',
    'nav_group.activities' => 'Activities',
];
```

## 11. Install Filament Shield

`bezhansalleh/filament-shield`

```bash
composer require bezhansalleh/filament-shield
```

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
 
class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
 
    // ...
}
```

publish configuration file

```bash
php artisan vendor:publish --tag=filament-shield-config
```

Register the plugin for the Filament Panels you want

```php
// ...

public function panel(Panel $panel): Panel
{
    return $panel
    
        ->navigationGroups([
            Navigation\NavigationGroup::make()
                ->label(__('menu.nav_group.access'))
                ->collapsible(false),
            Navigation\NavigationGroup::make()
                ->label(__('menu.nav_group.settings'))
                ->collapsed(),
        ])
        
        // ...
        
        ->plugins([
            \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                ->gridColumns([
                    'default' => 2,
                    'sm' => 1
                ])
                ->sectionColumnSpan(1)
                ->checkboxListColumns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 3,
                ])
                ->resourceCheckboxListColumns([
                    'default' => 1,
                    'sm' => 2,
                ]),
        ]);
}
```

Edit `xxx_xx_xx_xxxxxx_create_permission_tables.php`

```php
// ...

Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
    // ...
    $table->uuid($columnNames['model_morph_key']);
    // ...

Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
    // ...
    $table->uuid($columnNames['model_morph_key']);
    // ...
```


Now run the following command to install shield:

```bash
php artisan shield:install
```

#### Translations

Publish the translations using:

```bash
php artisan vendor:publish --tag="filament-shield-translations"
```

Panel Access

```php
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
 
class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    use HasPanelShield;
    // ...
}
```

`config/filament-shield.php`

```php
'register_role_policy' => [
    'enabled' => true,
],
```

### Create `app/Filament/Resources/Shield/RoleResource.php`

```php
<?php

namespace App\Filament\Resources\Shield;

use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Forms\ShieldSelectAllToggle;
use App\Filament\Resources\Shield\RoleResource\Pages;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RoleResource extends Resource implements HasShieldPermissions
{
    protected static ?string $recordTitleAttribute = 'name';
    protected static $permissionsCollection;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('filament-shield::filament-shield.field.name'))
                                    ->unique(ignoreRecord: true)
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('guard_name')
                                    ->label(__('filament-shield::filament-shield.field.guard_name'))
                                    ->default(Utils::getFilamentAuthGuard())
                                    ->nullable()
                                    ->maxLength(255),

                                ShieldSelectAllToggle::make('select_all')
                                    ->onIcon('heroicon-s-shield-check')
                                    ->offIcon('heroicon-s-shield-exclamation')
                                    ->label(__('filament-shield::filament-shield.field.select_all.name'))
                                    ->helperText(fn (): HtmlString => new HtmlString(__('filament-shield::filament-shield.field.select_all.message')))
                                    ->dehydrated(fn ($state): bool => $state),

                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ]),
                    ]),
                Forms\Components\Tabs::make('Permissions')
                    ->contained()
                    ->tabs([
                        static::getTabFormComponentForResources(),
                        static::getTabFormComponentForPage(),
                        static::getTabFormComponentForWidget(),
                        static::getTabFormComponentForCustomPermissions(),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->formatStateUsing(fn ($state): string => Str::headline($state))
                    ->colors(['primary'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->colors(['success']),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Model $record) => $record->name == config('filament-shield.super_admin.name')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getCluster(): ?string
    {
        return Utils::getResourceCluster() ?? static::$cluster;
    }

    public static function getModel(): string
    {
        return Utils::getRoleModel();
    }

    public static function getModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.roles');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Utils::isResourceNavigationRegistered();
    }

    public static function getNavigationGroup(): ?string
    {
        return Utils::isResourceNavigationGroupEnabled()
            ? __("menu.nav_group.access")
            : '';
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-shield::filament-shield.nav.role.label');
    }

    public static function getNavigationIcon(): string
    {
        return 'fluentui-shield-task-48';
    }

    public static function getNavigationSort(): ?int
    {
        return Utils::getResourceNavigationSort();
    }

    public static function getSlug(): string
    {
        return Utils::getResourceSlug();
    }

    public static function getNavigationBadge(): ?string
    {
        return Utils::isResourceNavigationBadgeEnabled()
            ? strval(static::getEloquentQuery()->count())
            : null;
    }

    public static function isScopedToTenant(): bool
    {
        return Utils::isScopedToTenant();
    }

    public static function canGloballySearch(): bool
    {
        return Utils::isResourceGloballySearchable() && count(static::getGloballySearchableAttributes()) && static::canViewAny();
    }

    public static function getResourceEntitiesSchema(): ?array
    {
        return collect(FilamentShield::getResources())
            ->sortKeys()
            ->map(function ($entity) {
                $sectionLabel = strval(
                    static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedResourceLabel($entity['fqcn'])
                    : $entity['model']
                );

                return Forms\Components\Section::make($sectionLabel)
                    ->description(fn () => new HtmlString('<span style="word-break: break-word;">' . Utils::showModelPath($entity['fqcn']) . '</span>'))
                    ->compact()
                    ->schema([
                        static::getCheckBoxListComponentForResource($entity),
                    ])
                    ->columnSpan(static::shield()->getSectionColumnSpan())
                    ->collapsible();
            })
            ->toArray();
    }

    public static function getResourceTabBadgeCount(): ?int
    {
        return collect(FilamentShield::getResources())
            ->map(fn ($resource) => count(static::getResourcePermissionOptions($resource)))
            ->sum();
    }

    public static function getResourcePermissionOptions(array $entity): array
    {
        return collect(Utils::getResourcePermissionPrefixes($entity['fqcn']))
            ->flatMap(function ($permission) use ($entity) {
                $name = $permission . '_' . $entity['resource'];
                $label = static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedResourcePermissionLabel($permission)
                    : $name;

                return [
                    $name => $label,
                ];
            })
            ->toArray();
    }

    public static function setPermissionStateForRecordPermissions(Component $component, string $operation, array $permissions, ?Model $record): void
    {

        if (in_array($operation, ['edit', 'view'])) {

            if (blank($record)) {
                return;
            }
            if ($component->isVisible() && count($permissions) > 0) {
                $component->state(
                    collect($permissions)
                        /** @phpstan-ignore-next-line */
                        ->filter(fn ($value, $key) => $record->checkPermissionTo($key))
                        ->keys()
                        ->toArray()
                );
            }
        }
    }

    public static function getPageOptions(): array
    {
        return collect(FilamentShield::getPages())
            ->flatMap(fn ($page) => [
                $page['permission'] => static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedPageLabel($page['class'])
                    : $page['permission'],
            ])
            ->toArray();
    }

    public static function getWidgetOptions(): array
    {
        return collect(FilamentShield::getWidgets())
            ->flatMap(fn ($widget) => [
                $widget['permission'] => static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedWidgetLabel($widget['class'])
                    : $widget['permission'],
            ])
            ->toArray();
    }

    public static function getCustomPermissionOptions(): ?array
    {
        return FilamentShield::getCustomPermissions()
            ->mapWithKeys(fn ($customPermission) => [
                $customPermission => static::shield()->hasLocalizedPermissionLabels() ? str($customPermission)->headline()->toString() : $customPermission,
            ])
            ->toArray();
    }

    public static function getTabFormComponentForResources(): Component
    {
        return static::shield()->hasSimpleResourcePermissionView()
            ? static::getTabFormComponentForSimpleResourcePermissionsView()
            : Forms\Components\Tabs\Tab::make('resources')
                ->label(__('filament-shield::filament-shield.resources'))
                ->visible(fn (): bool => (bool) Utils::isResourceEntityEnabled())
                ->badge(static::getResourceTabBadgeCount())
                ->schema([
                    Forms\Components\Grid::make()
                        ->schema(static::getResourceEntitiesSchema())
                        ->columns(static::shield()->getGridColumns()),
                ]);
    }

    public static function getCheckBoxListComponentForResource(array $entity): Component
    {
        $permissionsArray = static::getResourcePermissionOptions($entity);

        return static::getCheckboxListFormComponent($entity['resource'], $permissionsArray, false);
    }

    public static function getTabFormComponentForPage(): Component
    {
        $options = static::getPageOptions();
        $count = count($options);

        return Forms\Components\Tabs\Tab::make('pages')
            ->label(__('filament-shield::filament-shield.pages'))
            ->visible(fn (): bool => (bool) Utils::isPageEntityEnabled() && $count > 0)
            ->badge($count)
            ->schema([
                static::getCheckboxListFormComponent('pages_tab', $options),
            ]);
    }

    public static function getTabFormComponentForWidget(): Component
    {
        $options = static::getWidgetOptions();
        $count = count($options);

        return Forms\Components\Tabs\Tab::make('widgets')
            ->label(__('filament-shield::filament-shield.widgets'))
            ->visible(fn (): bool => (bool) Utils::isWidgetEntityEnabled() && $count > 0)
            ->badge($count)
            ->schema([
                static::getCheckboxListFormComponent('widgets_tab', $options),
            ]);
    }

    public static function getTabFormComponentForCustomPermissions(): Component
    {
        $options = static::getCustomPermissionOptions();
        $count = count($options);

        return Forms\Components\Tabs\Tab::make('custom')
            ->label(__('vendor.filament-shield.custom'))
            ->visible(fn (): bool => (bool) Utils::isCustomPermissionEntityEnabled() && $count > 0)
            ->badge($count)
            ->schema([
                static::getCheckboxListFormComponent('custom_permissions', $options),
            ]);
    }

    public static function getTabFormComponentForSimpleResourcePermissionsView(): Component
    {
        $options = FilamentShield::getAllResourcePermissions();
        $count = count($options);

        return Forms\Components\Tabs\Tab::make('resources')
            ->label(__('filament-shield::filament-shield.resources'))
            ->visible(fn (): bool => (bool) Utils::isResourceEntityEnabled() && $count > 0)
            ->badge($count)
            ->schema([
                static::getCheckboxListFormComponent('resources_tab', $options),
            ]);
    }

    public static function getCheckboxListFormComponent(string $name, array $options, bool $searchable = true): Component
    {
        return Forms\Components\CheckboxList::make($name)
            ->label('')
            ->options(fn (): array => $options)
            ->searchable($searchable)
            ->afterStateHydrated(
                fn (Component $component, string $operation, ?Model $record) => static::setPermissionStateForRecordPermissions(
                    component: $component,
                    operation: $operation,
                    permissions: $options,
                    record: $record
                )
            )
            ->dehydrated(fn ($state) => ! blank($state))
            ->bulkToggleable()
            ->gridDirection('row')
            ->columns(static::shield()->getCheckboxListColumns())
            ->columnSpan(static::shield()->getCheckboxListColumnSpan());
    }

    public static function shield(): FilamentShieldPlugin
    {
        return FilamentShieldPlugin::get();
    }
}
```

### Create `app/Filament/Resources/Shield/RoleResource/Pages/CreateRole.php`

```php
<?php

namespace App\Filament\Resources\Shield\RoleResource\Pages;

use App\Filament\Resources\Shield\RoleResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    public Collection $permissions;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissions = collect($data)
            ->filter(function ($permission, $key) {
                return ! in_array($key, ['name', 'guard_name', 'select_all']);
            })
            ->values()
            ->flatten()
            ->unique();

        return Arr::only($data, ['name', 'guard_name']);
    }

    protected function afterCreate(): void
    {
        $permissionModels = collect();
        $this->permissions->each(function ($permission) use ($permissionModels) {
            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                /** @phpstan-ignore-next-line */
                'name' => $permission,
                'guard_name' => $this->data['guard_name'],
            ]));
        });

        $this->record->syncPermissions($permissionModels);
    }
}
```

### Create `app/Filament/Resources/Shield/RoleResource/Pages/EditRole.php`

```php
<?php

namespace App\Filament\Resources\Shield\RoleResource\Pages;

use App\Filament\Resources\Shield\RoleResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public Collection $permissions;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (Model $record) => $record->name == config('filament-shield.super_admin.name')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissions = collect($data)
            ->filter(function ($permission, $key) {
                return ! in_array($key, ['name', 'guard_name', 'select_all']);
            })
            ->values()
            ->flatten()
            ->unique();

        return Arr::only($data, ['name', 'guard_name']);
    }

    protected function afterSave(): void
    {
        $permissionModels = collect();
        $this->permissions->each(function ($permission) use ($permissionModels) {
            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                'name' => $permission,
                'guard_name' => $this->data['guard_name'],
            ]));
        });

        $this->record->syncPermissions($permissionModels);
    }
}
```

### Create `app/Filament/Resources/Shield/RoleResource/Pages/ListRole.php`

```php
<?php

namespace App\Filament\Resources\Shield\RoleResource\Pages;

use App\Filament\Resources\Shield\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

### Create `app/Filament/Resources/Shield/RoleResource/Pages/ViewRole.php`

```php
<?php

namespace App\Filament\Resources\Shield\RoleResource\Pages;

use App\Filament\Resources\Shield\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
```

### Defining Super Admin

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;
// ...
public function boot()
{
    // Implicitly grant "Super Admin" role all permissions
    // This works in the app by using gate-related functions like auth()->user->can() and @can()
    Gate::before(function ($user, $ability) {
        return $user->hasRole('super_admin') ? true : null;
    });
}
```

- [Spatie Laravel Permission Website](https://spatie.be/docs/laravel-permission/v6/introduction)

## 12. Creating Filament User Resource

```bash
php artisan make:filament-resource User --view --soft-deletes --generate
```

## 13. Install Riodwanto Filament Ace Editor

[`riodwanto/filament-ace-editor`](https://github.com/riodwanto/filament-ace-editor)

```bash
composer require riodwanto/filament-ace-editor
php artisan vendor:publish --tag="filament-ace-editor-views"
php artisan vendor:publish --tag="filament-ace-editor-config"
```

## 14. Create `FileService` Service

`app/Services/FileService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileService
{
    protected $allowedPaths;

    public function __construct(array $allowedPaths = null)
    {
        $this->allowedPaths = $allowedPaths ?? Config::get('filemanager.allowed_paths', [
            base_path('app'),
            base_path('resources'),
            base_path('config'),
        ]);
    }

    public function readFile(string $path)
    {
        $this->validatePath($path);

        if (!File::exists($path)) {
            Log::error("File does not exist: {$path}");
            throw new FileException("The file does not exist.");
        }

        return File::get($path);
    }

    public function writeFile(string $path, string $content)
    {
        $this->validatePath($path);

        if (!File::put($path, $content)) {
            Log::error("Unable to write to file: {$path}");
            throw new FileException("Unable to write to file.");
        }

        return true;
    }

    protected function validatePath(string &$path)
    {
        $realPath = realpath($path);
        if (!$realPath) {
            throw new \InvalidArgumentException("Invalid path provided.");
        }

        $isAllowed = array_reduce($this->allowedPaths, function ($carry, $allowedPath) use ($realPath) {
            return $carry || strpos($realPath, $allowedPath) === 0;
        }, false);

        if (!$isAllowed) {
            Log::warning("Attempt to access a path not allowed: {$path}");
            throw new \InvalidArgumentException("Access to this path is not allowed.");
        }

        $path = $realPath;
    }
}
```

## 15. Install Filament Spatie Laravel Settings Plugin

`filament/spatie-laravel-settings-plugin`

```bash
composer require filament/spatie-laravel-settings-plugin:"^3.2" -W
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"
php artisan optimize:clear
php artisan migrate
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="config"
```

### Generate General Setting Class

Before we generate general settings, we have to prepare something first.

#### Create `resources/sass/filament/admin/theme.scss`

```bash
mkdir resources/sass/filament
mkdir resources/sass/filament/admin
touch resources/sass/filament/admin/theme.scss
```

```scss
@import '/vendor/filament/filament/resources/css/theme.css';

@config 'tailwind.config.js';

/* AUTH */
.fi-logo {
    @apply h-10 #{!important};
}

/* SIDEBAR */
.fi-sidebar.fi-sidebar-open {
    @apply w-1/5 #{!important};
}

.fi-sidebar-nav-groups {
    @apply gap-y-4;
}

.fi-sidebar-header {
    @apply py-2;
    @apply ring-0 dark:bg-gray-950 bg-gray-50 shadow-none #{!important};
}

.fi-sidebar-group > .fi-sidebar-group-items { @apply gap-y-2; }

.fi-topbar nav {
    @apply py-2 bg-gray-50 dark:bg-gray-950;
    @apply ring-0 border-none shadow-none #{!important};
}

/* SIDEBAR ITEM */
.fi-sidebar-item a {
    @apply px-4 py-2 rounded-xl;
}

.fi-sidebar-item a > span,
.fi-sidebar-item a > svg {
    @apply font-semibold;
}

.fi-sidebar-item a:focus,
.fi-sidebar-item a:hover {
    @apply bg-primary-100 shadow-[rgba(50,_50,_105,_0.15)_0px_2px_5px_0px,_rgba(0,_0,_0,_0.05)_0px_1px_1px_0px] dark:bg-primary-900 dark:shadow-[rgba(50,50,93,0.25)_0px_6px_12px_-2px,_rgba(0,0,0,0.3)_0px_3px_7px_-3px] transition-all ease-in-out duration-100  #{!important};
}

.fi-sidebar-item a:hover svg,
.fi-sidebar-item a:focus span,
.fi-sidebar-item a:hover span {
    @apply text-primary-600 dark:text-gray-100 transition-all ease-in-out duration-100  #{!important};
}

.fi-sidebar-item-active a {
    @apply shadow-[rgba(50,_50,_105,_0.15)_0px_2px_5px_0px,_rgba(0,_0,_0,_0.05)_0px_1px_1px_0px] bg-primary-100 dark:bg-primary-900 dark:shadow-[rgba(50,50,93,0.25)_0px_6px_12px_-2px,_rgba(0,0,0,0.3)_0px_3px_7px_-3px];
}

.fi-sidebar-item a:hover svg,
.fi-sidebar-item a:focus svg,
.fi-sidebar-item-active svg {
    @apply transition-all ease-in-out duration-300  #{!important};
}
.fi-sidebar.fi-sidebar-open {
    .fi-sidebar-item a:hover svg,
    .fi-sidebar-item a:focus svg,
    .fi-sidebar-item-active svg {
        @apply ml-1 #{!important};
    }
}

/* Content */
.fi-main {
    @apply my-4 pb-4 bg-white dark:bg-gray-900 rounded-3xl shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px];
}

.fi-simple-main,
.fi-section,
.fi-ta-ctn,
.fi-fo-tabs,
.fi-wi-stats-overview-stat,
.fi-fo-repeater-item {
    @apply shadow-[rgba(50,_50,_105,_0.15)_0px_2px_5px_0px,_rgba(0,_0,_0,_0.05)_0px_1px_1px_0px];
}

.fi-header-heading {
    @apply text-primary-600 dark:text-primary-400;
    @apply text-2xl #{!important};
}

.fi-header-subheading {
    @apply text-sm #{!important};
}

/* Filament Actions */
.fi-btn.fi-btn-color-primary {
    @apply text-secondary-50;
}
.fi-dropdown-trigger button.fi-btn {
    @apply p-1 #{!important};
}
```

#### Create `resources/sass/filament/admin/tailwind.config.js`

```bash
touch resources/sass/filament/admin/tailwind.config.js
```

```js
import preset from "../../../../vendor/filament/filament/tailwind.config.preset";

export default {
    presets: [preset],
    content: [
        "./app/Filament/**/*.php",
        "./resources/views/filament/**/*.blade.php",
        "./vendor/filament/**/*.blade.php",
    ],
    theme: {
        extend: {
            colors: {
                secondary: {
                    50: "rgba(var(--secondary-50), <alpha-value>)",
                    100: "rgba(var(--secondary-100), <alpha-value>)",
                    200: "rgba(var(--secondary-200), <alpha-value>)",
                    300: "rgba(var(--secondary-300), <alpha-value>)",
                    400: "rgba(var(--secondary-400), <alpha-value>)",
                    500: "rgba(var(--secondary-500), <alpha-value>)",
                    600: "rgba(var(--secondary-600), <alpha-value>)",
                    700: "rgba(var(--secondary-700), <alpha-value>)",
                    800: "rgba(var(--secondary-800), <alpha-value>)",
                    900: "rgba(var(--secondary-900), <alpha-value>)",
                },
            },
        },
    },
};
```

Edit `vite.config.js` file:

```js
```

Add this line to `AdminPanelProvider.php`

```php
->viteTheme('resources/sass/filament/admin/theme.scss')
```

#### The GeneralSettings

```bash
php artisan make:setting GeneralSettings --group=general
```

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $brand_name;
    public ?string $brand_logo;
    public string $brand_logoHeight;
    public bool $site_active;
    public ?string $site_favicon;
    public array $site_theme;

    public static function group(): string
    {
        return 'general';
    }
}
```

Now, you will have to add this settings class to the `settings.php` config file in the settings array, so it can be loaded by Laravel:

```php
    /*
     * Each settings class used in your application must be registered, you can
     * add them (manually) here.
     */
    'settings' => [
        GeneralSettings::class
    ],
```

Each property in a settings class needs a default value that should be set in its migration. You can create a migration as such:

```bash
php artisan make:settings-migration CreateGeneralSettings
```

Then edit that `database/settings/xxxx_xx_xx_xxxxxx_create_general_settings.php` file

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.brand_name', 'SuperDuper Starter Kit');
        $this->migrator->add('general.brand_logo', 'sites/logo.png');
        $this->migrator->add('general.brand_logoHeight', '3rem');
        $this->migrator->add('general.site_active', true);
        $this->migrator->add('general.site_favicon', 'sites/logo.ico');
        $this->migrator->add('general.site_theme', [
            "primary" => "#3150AE",
            "secondary" => "#3be5e8",
            "gray" => "#485173",
            "success" => "#1DCB8A",
            "danger" => "#ff5467",
            "info" => "#6E6DD7",
            "warning" => "#f5de8d",
        ]);
    }
};
```

You should migrate your database to add the properties:

```bash
php artisan migrate
```
- [Github](https://github.com/spatie/laravel-settings#usage)

### Create General Setting Page

```bash
php artisan make:filament-settings-page Setting/ManageGeneral GeneralSettings
```

The file: `app/Filament/Pages/Setting/ManageGeneral.php`

```php
<?php

namespace App\Filament\Pages\Setting;

use App\Services\FileService;
use App\Settings\GeneralSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Riodwanto\FilamentAceEditor\AceEditor;

use function Filament\Support\is_app_url;

class ManageGeneral extends SettingsPage
{
    use HasPageShield;
    protected static string $settings = GeneralSettings::class;

    protected static ?int $navigationSort = 99;
    protected static ?string $navigationIcon = 'fluentui-settings-20';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public string $themePath = '';

    public string $twConfigPath = '';

    public function mount(): void
    {
        $this->themePath = resource_path('sass/filament/admin/theme.scss');
        $this->twConfigPath = resource_path('sass/filament/admin/tailwind.config.js');

        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $settings = app(static::getSettings());

        $data = $this->mutateFormDataBeforeFill($settings->toArray());

        $fileService = new FileService;

        $data['theme-editor'] = $fileService->readfile($this->themePath);

        $data['tw-config-editor'] = $fileService->readfile($this->twConfigPath);

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Site')
                    ->label(fn () => __('page.general_settings.sections.site'))
                    ->description(fn () => __('page.general_settings.sections.site.description'))
                    ->icon('fluentui-web-asset-24-o')
                    ->schema([
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\TextInput::make('brand_name')
                                ->label(fn () => __('page.general_settings.fields.brand_name'))
                                ->required(),
                            Forms\Components\Select::make('site_active')
                                ->label(fn () => __('page.general_settings.fields.site_active'))
                                ->options([
                                    0 => "Not Active",
                                    1 => "Active",
                                ])
                                ->native(false)
                                ->required(),
                        ]),
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\TextInput::make('brand_logoHeight')
                                ->label(fn () => __('page.general_settings.fields.brand_logoHeight'))
                                ->required()
                                ->columnSpanFull()
                                ->maxWidth('w-1/2'),
                            Forms\Components\Grid::make()->schema([
                                Forms\Components\FileUpload::make('brand_logo')
                                    ->label(fn () => __('page.general_settings.fields.brand_logo'))
                                    ->image()
                                    ->directory('sites')
                                    ->visibility('public')
                                    ->moveFiles()
                                    ->required(),

                                Forms\Components\FileUpload::make('site_favicon')
                                    ->label(fn () => __('page.general_settings.fields.site_favicon'))
                                    ->image()
                                    ->directory('sites')
                                    ->visibility('public')
                                    ->moveFiles()
                                    ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon'])
                                    ->required(),
                            ]),
                        ])->columns(4),
                    ]),
                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Color Palette')
                            ->schema([
                                Forms\Components\ColorPicker::make('site_theme.primary')
                                    ->label(fn () => __('page.general_settings.fields.primary')),
                                Forms\Components\ColorPicker::make('site_theme.secondary')
                                    ->label(fn () => __('page.general_settings.fields.secondary')),
                                Forms\Components\ColorPicker::make('site_theme.gray')
                                    ->label(fn () => __('page.general_settings.fields.gray')),
                                Forms\Components\ColorPicker::make('site_theme.success')
                                    ->label(fn () => __('page.general_settings.fields.success')),
                                Forms\Components\ColorPicker::make('site_theme.danger')
                                    ->label(fn () => __('page.general_settings.fields.danger')),
                                Forms\Components\ColorPicker::make('site_theme.info')
                                    ->label(fn () => __('page.general_settings.fields.info')),
                                Forms\Components\ColorPicker::make('site_theme.warning')
                                    ->label(fn () => __('page.general_settings.fields.warning')),
                            ])
                            ->columns(3),
                        Forms\Components\Tabs\Tab::make('Code Editor')
                            ->schema([
                                Forms\Components\Grid::make()->schema([
                                    AceEditor::make('theme-editor')
                                        ->label('theme.css')
                                        ->mode('css')
                                        ->height('24rem'),
                                    AceEditor::make('tw-config-editor')
                                        ->label('tailwind.config.js')
                                        ->height('24rem')
                                ])
                            ]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->mutateFormDataBeforeSave($this->form->getState());

            $settings = app(static::getSettings());

            $settings->fill($data);
            $settings->save();

            $fileService = new FileService;
            $fileService->writeFile($this->themePath, $data['theme-editor']);
            $fileService->writeFile($this->twConfigPath, $data['tw-config-editor']);

            Notification::make()
                ->title('Settings updated.')
                ->success()
                ->send();

            $this->redirect(static::getUrl(), navigate: FilamentView::hasSpaMode() && is_app_url(static::getUrl()));
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public static function getNavigationGroup(): ?string
    {
        return __("menu.nav_group.settings");
    }

    public static function getNavigationLabel(): string
    {
        return __("page.general_settings.navigationLabel");
    }

    public function getTitle(): string|Htmlable
    {
        return __("page.general_settings.title");
    }

    public function getHeading(): string|Htmlable
    {
        return __("page.general_settings.heading");
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __("page.general_settings.subheading");
    }
}
```

#### Edit AdminPanelProvider

Add this line to `$panel`

```php
->favicon(fn (GeneralSettings $settings) => Storage::url($settings->site_favicon))
->brandName(fn (GeneralSettings $settings) => $settings->brand_name)
->brandLogo(fn (GeneralSettings $settings) => Storage::url($settings->brand_logo))
->brandLogoHeight(fn (GeneralSettings $settings) => $settings->brand_logoHeight)
->colors(fn (GeneralSettings $settings) => $settings->site_theme)
->globalSearchKeyBindings(['command+k', 'ctrl+k'])
->sidebarCollapsibleOnDesktop()
```

Delete or comment out this line:

```php
// ->colors([
//     'primary' => Color::Amber,
// ])
```

### Generate Mail Setting Class

#### Create `TestMail` Mail

```bash
php artisan make:mail TestMail
```

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;

    /**
     * Create a new message instance.
     */
    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.testMail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
```

#### The view: `resources/views/emails/testMail.blade.php`

```php
<!DOCTYPE html>
<html>
<head>
    <title>TestMail</title>
</head>
<body>
    <h1>{{ $mailData['title'] }}</h1>
    <p>{{ $mailData['body'] }}</p>

    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
    tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
    quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
    consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
    cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
    proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

    <p>Thank you</p>
</body>
</html>
```

#### Create `MailSettings` Class

```bash
php artisan make:setting MailSettings --group=mail
```

And the file: `app/Settings/MailSettings.php`

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $from_address;
    public string $from_name;
    public ?string $driver;
    public ?string $host;
    public int $port;
    public string $encryption;
    public ?string $username;
    public ?string $password;
    public ?int $timeout;
    public ?string $local_domain;

    public static function group(): string
    {
        return 'mail';
    }

    public static function encrypted(): array
    {
        return [
            'username',
            'password',
        ];
    }

    public function loadMailSettingsToConfig($data = null): void
    {
        config([
            'mail.mailers.smtp.host' => $data['host'] ?? $this->host,
            'mail.mailers.smtp.port' => $data['port'] ?? $this->port,
            'mail.mailers.smtp.encryption' => $data['encryption'] ?? $this->encryption,
            'mail.mailers.smtp.username' => $data['username'] ?? $this->username,
            'mail.mailers.smtp.password' => $data['password'] ?? $this->password,
            'mail.from.address' => $data['from_address'] ?? $this->from_address,
            'mail.from.name' => $data['from_name'] ?? $this->from_name,
        ]);
    }

    /**
     * Check if MailSettings is configured with necessary values.
     */
    public function isMailSettingsConfigured(): bool
    {
        // Check if the essential fields are not null
        return $this->host && $this->username && $this->password && $this->from_address;
    }
}
```

#### `setting.php` configuration

```php
    'settings' => [
        // ...
        MailSettings::class,
    ],
```

#### Create MailSettings Migration

```bash
php artisan make:settings-migration CreateMailSettings
```

#### Fill up the file: 

`database/settings/xxxx_xx_xx_xxxxxx_create_mail_settings.php`

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.from_address', 'no-reply@starter-kit.com');
        $this->migrator->add('mail.from_name', 'SuperDuper Starter Kit');
        $this->migrator->add('mail.driver', 'smtp');
        $this->migrator->add('mail.host', null);
        $this->migrator->add('mail.port', 587);
        $this->migrator->add('mail.encryption', 'tls');
        $this->migrator->addEncrypted('mail.username', null);
        $this->migrator->addEncrypted('mail.password', null);
        $this->migrator->add('mail.timeout', null);
        $this->migrator->add('mail.local_domain', null);
    }
};
```

Last thing is.. migration.

```bash
php artisan migrate
```

### Generate Mail Setting Page

```bash
php artisan make:filament-settings-page Setting/ManageMail MailSettings
```

#### The ManageMail

```php
<?php

namespace App\Filament\Pages\Setting;

use App\Mail\TestMail;
use App\Settings\MailSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;

use function Filament\Support\is_app_url;

class ManageMail extends SettingsPage
{
    use HasPageShield;

    protected static string $settings = MailSettings::class;

    protected static ?int $navigationSort = 99;
    protected static ?string $navigationIcon = 'fluentui-mail-settings-20';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $data = $this->mutateFormDataBeforeFill(app(static::getSettings())->toArray());

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Configuration')
                            ->label(fn () => __('page.mail_settings.sections.config.title'))
                            ->icon('fluentui-calendar-settings-32-o')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Select::make('driver')->label(fn () => __('page.mail_settings.fields.driver'))
                                            ->options([
                                                "smtp" => "SMTP (Recommended)",
                                                "mailgun" => "Mailgun",
                                                "ses" => "Amazon SES",
                                                "postmark" => "Postmark",
                                            ])
                                            ->native(false)
                                            ->required()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('host')->label(fn () => __('page.mail_settings.fields.host'))
                                            ->required(),
                                        Forms\Components\TextInput::make('port')->label(fn () => __('page.mail_settings.fields.port')),
                                        Forms\Components\Select::make('encryption')->label(fn () => __('page.mail_settings.fields.encryption'))
                                            ->options([
                                                "ssl" => "SSL",
                                                "tls" => "TLS",
                                            ])
                                            ->native(false),
                                        Forms\Components\TextInput::make('timeout')->label(fn () => __('page.mail_settings.fields.timeout')),
                                        Forms\Components\TextInput::make('username')->label(fn () => __('page.mail_settings.fields.username')),
                                        Forms\Components\TextInput::make('password')->label(fn () => __('page.mail_settings.fields.password'))
                                            ->password()
                                            ->revealable(),
                                    ])
                                    ->columns(3),
                            ])
                    ])
                    ->columnSpan([
                        "md" => 2
                    ]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('From (Sender)')
                            ->label(fn () => __('page.mail_settings.section.sender.title'))
                            ->icon('fluentui-person-mail-48-o')
                            ->schema([
                                Forms\Components\TextInput::make('from_address')->label(fn () => __('page.mail_settings.fields.email'))
                                    ->required(),
                                Forms\Components\TextInput::make('from_name')->label(fn () => __('page.mail_settings.fields.name'))
                                    ->required(),
                            ]),

                        Forms\Components\Section::make('Mail to')
                            ->label(fn () => __('page.mail_settings.section.mail_to.title'))
                            ->schema([
                                Forms\Components\TextInput::make('mail_to')
                                    ->label(fn () => __('page.mail_settings.fields.mail_to'))
                                    ->hiddenLabel()
                                    ->placeholder(fn () => __('page.mail_settings.fields.placeholder.receiver_email'))
                                    ->required(),
                                Forms\Components\Actions::make([
                                        Forms\Components\Actions\Action::make('Send Test Mail')
                                            ->label(fn () => __('page.mail_settings.actions.send_test_mail'))
                                            ->action('sendTestMail')
                                            ->color('warning')
                                            ->icon('fluentui-mail-alert-28-o')
                                    ])->fullWidth(),
                            ])
                    ])
                    ->columnSpan([
                        "md" => 1
                    ]),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function save(MailSettings $settings = null): void
    {
        try {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeSave($data);

            $this->callHook('beforeSave');

            $settings->fill($data);
            $settings->save();

            $this->callHook('afterSave');

            $this->sendSuccessNotification('Mail Settings updated.');

            $this->redirect(static::getUrl(), navigate: FilamentView::hasSpaMode() && is_app_url(static::getUrl()));
        } catch (\Throwable $th) {
            $this->sendErrorNotification('Failed to update settings. '.$th->getMessage());
            throw $th;
        }
    }

    public function sendTestMail(MailSettings $settings = null)
    {
        $data = $this->form->getState();

        $settings->loadMailSettingsToConfig($data);
        try {
            $mailTo = $data['mail_to'];
            $mailData = [
                'title' => 'This is a test email to verify SMTP settings',
                'body' => 'This is for testing email using smtp.'
            ];

            Mail::to($mailTo)->send(new TestMail($mailData));

            $this->sendSuccessNotification('Mail Sent to: '.$mailTo);
        } catch (\Exception $e) {
            $this->sendErrorNotification($e->getMessage());
        }
    }

    public function sendSuccessNotification($title)
    {
        Notification::make()
                ->title($title)
                ->success()
                ->send();
    }

    public function sendErrorNotification($title)
    {
        Notification::make()
                ->title($title)
                ->danger()
                ->send();
    }

    public static function getNavigationGroup(): ?string
    {
        return __("menu.nav_group.settings");
    }

    public static function getNavigationLabel(): string
    {
        return __("page.mail_settings.navigationLabel");
    }

    public function getTitle(): string|Htmlable
    {
        return __("page.mail_settings.title");
    }

    public function getHeading(): string|Htmlable
    {
        return __("page.mail_settings.heading");
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __("page.mail_settings.subheading");
    }
}
```

## 16. Install Filament Spatie Laravel Media Plugin

[`filament/spatie-laravel-media-library-plugin`](https://filamentphp.com/plugins/filament-spatie-media-library)

```bash
composer require filament/spatie-laravel-media-library-plugin:"^3.2" -W
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

Edit migration file: `database/migrations/xxxx_xx_xx_xxxxxx_create_media_table.php`

```php
Schema::create('media', function (Blueprint $table) {
    $table->id();

    $table->uuidMorphs('model');
    // ...
```

```bash
php artisan migrate
```

Edit User Models

```php
namespace App\Models;

// ...
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Filament\Models\Contracts\HasAvatar;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class User extends Authenticatable implements FilamentUser, HasMedia, HasAvatar
{
    use InteractsWithMedia;
    
    // ...
    
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->getMedia('avatars')?->first()?->getUrl() ?? $this->getMedia('avatars')?->first()?->getUrl('thumb') ?? null;
    }
    
    public function registerMediaConversions(Media|null $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }
```

- [Introduction to Spatie Laravel MediaLibrary](https://spatie.be/docs/laravel-medialibrary/v11/introduction)
- [Preparing Your Model](https://spatie.be/docs/laravel-medialibrary/v11/basic-usage/preparing-your-model)


## 17. Install TomatoPHP Filament Media Manager

[`tomatophp/filament-media-manager`](https://github.com/tomatophp/filament-media-manager)

```bash
composer require tomatophp/filament-media-manager
php artisan filament-media-manager:install
```

finally register the plugin on /app/Providers/Filament/AdminPanelProvider.php, if you like to use GUI and Folder Browser.

```php
->navigationGroups([
    Navigation\NavigationGroup::make()
        ->label('Content') // !! To-Do: lang
        ->collapsible(false),
    // ...
])
->plugins([
    // ...
    \TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin::make(),
])
```

### Publish Assets

#### Config

```bash
php artisan vendor:publish --tag="filament-media-manager-config"
```

#### Views

```bash
php artisan vendor:publish --tag="filament-media-manager-views"
```

#### Languages

```bash
php artisan vendor:publish --tag="filament-media-manager-lang"
```

#### Migration

```bash
php artisan vendor:publish --tag="filament-media-manager-migrations"
```



## 18. Install Filament Breezy Plugin

[`jeffgreco13/filament-breezy`](https://github.com/jeffgreco13/filament-breezy)

```bash
composer require jeffgreco13/filament-breezy
php artisan breezy:install
php artisan vendor:publish --tag="filament-breezy-views"
```

Add `MyProfileExtended` livewire component:

```bash
php artisan make:livewire MyProfileExtended
```

```php
<?php

namespace App\Livewire;

use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

use function Filament\Support\is_app_url;

class MyProfileExtended extends MyProfileComponent
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public $user;

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $data = $this->getUser()->attributesToArray();

        $this->form->fill($data);
    }

    public function getUser(): Authenticatable & Model
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Model) {
            throw new Exception('The authenticated user object must be an Eloquent model to allow the profile page to update it.');
        }

        return $user;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                SpatieMediaLibraryFileUpload::make('media')->label('Avatar')
                        ->collection('avatars')
                        ->avatar()
                        ->required(),
                    Grid::make()->schema([
                        TextInput::make('username')
                            ->disabled()
                            ->required(),
                        TextInput::make('email')
                            ->disabled()
                            ->required(),
                    ]),
                    Grid::make()->schema([
                        TextInput::make('firstname')
                            ->required(),
                        TextInput::make('lastname')
                            ->required()
                    ]),
            ])
            ->operation('edit')
            ->model($this->getUser())
            ->statePath('data');
    }

    public function submit()
    {
        try {
            $data = $this->form->getState();

            $this->handleRecordUpdate($this->getUser(), $data);

            Notification::make()
                ->title('Profile updated')
                ->success()
                ->send();

            $this->redirect('my-profile', navigate: FilamentView::hasSpaMode() && is_app_url('my-profile'));
        } catch (\Throwable $th) {
            Notification::make()
                ->title('Failed to update.')
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    public function render(): View
    {
        return view("livewire.my-profile-extended");
    }
}
```

```php
<x-filament-breezy::grid-section md=2 :title="__('filament-breezy::default.profile.personal_info.heading')" :description="__('filament-breezy::default.profile.personal_info.subheading')">
    <x-filament::card>
        <form wire:submit.prevent="submit" class="space-y-6">

            {{ $this->form }}

            <div class="text-right">
                <x-filament::button type="submit" form="submit" class="align-right">
                    Update
                </x-filament::button>
            </div>
        </form>
    </x-filament::card>
</x-filament-breezy::grid-section>
```

Add to AdminPanelProvider plugins:

```php
->plugins([
    // ...
    \Jeffgreco13\FilamentBreezy\BreezyCore::make()
        ->myProfile(
            shouldRegisterUserMenu: true,
            shouldRegisterNavigation: false,
            navigationGroup: 'Settings',
            hasAvatars: true,
            slug: 'my-profile'
        )
        ->myProfileComponents([
            'personal_info' => MyProfileExtended::class,
        ]),
```

Edit User Model

```php
// ...
use Filament\Models\Contracts\HasName;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail, HasName
{
    // ...
    
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name'));
    }
    
```


## 19. Install Filament Record Navigation Plugin

Seamless navigation through records in a Filament resource's view.

[`josespinal/filament-record-navigation`](https://filamentphp.com/plugins/jose-espinal-record-navigation)

```bash
composer require josespinal/filament-record-navigation
```

### Edit UserResouce

`app/Filament/Resources/UserResource/Pages/CreateUser.php`

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use Exception;
use Filament\Actions;
use App\Settings\MailSettings;
use Filament\Facades\Filament;
use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Auth\VerifyEmail;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $settings = app(MailSettings::class);

        if (! method_exists($user, 'notify')) {
            $userClass = $user::class;

            throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
        }

        if ($settings->isMailSettingsConfigured()) {
            $notification = new VerifyEmail();
            $notification->url = Filament::getVerifyEmailUrl($user);

            $settings->loadMailSettingsToConfig();

            $user->notify($notification);


            Notification::make()
                ->title(__('resource.user.notifications.verify_sent.title'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('resource.user.notifications.verify_warning.title'))
                ->body(__('resource.user.notifications.verify_warning.description'))
                ->warning()
                ->send();
        }
    }
}
```

`app/Filament/Resources/UserResource/Pages/EditUser.php`

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Support;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Filament\Support\Enums\Alignment;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use JoseEspinal\RecordNavigation\Traits\HasRecordNavigation;

class EditUser extends EditRecord
{
    use HasRecordNavigation;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        // return [
        //     Actions\ViewAction::make(),
        //     Actions\DeleteAction::make(),
        //     Actions\ForceDeleteAction::make(),
        //     Actions\RestoreAction::make(),
        // ];

        $actions = [
            Actions\ActionGroup::make([
                Actions\EditAction::make()
                    ->label('Change password')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->revealable()
                            ->required(),
                        Forms\Components\TextInput::make('passwordConfirmation')
                            ->password()
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->revealable()
                            ->same('password')
                            ->required(),
                    ])
                    ->modalWidth(Support\Enums\MaxWidth::Medium)
                    ->modalHeading('Update Password')
                    ->modalDescription(fn($record) => $record->email)
                    ->modalAlignment(Alignment::Center)
                    ->modalCloseButton(false)
                    ->modalSubmitActionLabel('Submit')
                    ->modalCancelActionLabel('Cancel'),

                Actions\DeleteAction::make()
                    ->extraAttributes(["class" => "border-b"]),

                Actions\CreateAction::make()
                    ->label('Create new user')
                    ->url(fn(): string => static::$resource::getNavigationUrl() . '/create'),
            ])
            ->icon('heroicon-m-ellipsis-horizontal')
            ->hiddenLabel()
            ->button()
            ->tooltip('More Actions')
            ->color('gray')
        ];

        return array_merge($this->getNavigationActions(), $actions);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string|Htmlable
    {
        $title = $this->record->name;
        $badge = $this->getBadgeStatus();

        return new HtmlString("
            <div class='flex items-center space-x-2'>
                <div>$title</div>
                $badge
            </div>
        ");
    }

    public function getBadgeStatus(): string|Htmlable
    {
        if (empty($this->record->email_verified_at)) {
            $badge = "<span class='inline-flex items-center px-2 py-1 text-xs font-semibold rounded-md text-danger-700 bg-danger-50 ring-1 ring-inset ring-danger-600/20'>Unverified</span>";
        } else {
            $badge = "<span class='inline-flex items-center px-2 py-1 text-xs font-semibold rounded-md text-success-700 bg-success-50 ring-1 ring-inset ring-success-600/20'>Verified</span>";
        }

        return $badge;
    }
}
```

`app/Filament/Resources/UserResource/Pages/ListUsers.php`

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions;
use Filament\Resources\Components\Tab;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use JoseEspinal\RecordNavigation\Traits\HasRecordsList;

class ListUsers extends ListRecords
{
    use ExposesTableToWidgets;
    use HasRecordsList;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return static::$resource::getWidgets();
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $tabs = [
            null => Tab::make('All'),
            'admin' => Tab::make()->query(fn ($query) => $query->with('roles')->whereRelation('roles', 'name', '=', 'admin')),
            'author' => Tab::make()->query(fn ($query) => $query->with('roles')->whereRelation('roles', 'name', '=', 'author')),
        ];

        if ($user->isSuperAdmin()) {
            $tabs['superadmin'] = Tab::make()->query(fn ($query) => $query->with('roles')->whereRelation('roles', 'name', '=', config('filament-shield.super_admin.name')));
        }

        return $tabs;
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $model = (new (static::$resource::getModel()))->with('roles')->where('id', '!=', auth()->user()->id);

        if (!$user->isSuperAdmin()) {
            $model = $model->whereDoesntHave('roles', function ($query) {
                $query->where('name', '=', config('filament-shield.super_admin.name'));
            });
        }

        return $model;
    }
}
```

`app/Filament/Resources/UserResource.php`

```php
<?php

namespace App\Filament\Resources;

use Exception;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Settings\MailSettings;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Notifications\Auth\VerifyEmail;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static int $globalSearchResultsLimit = 20;

    protected static ?int $navigationSort = -1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('media')
                            ->hiddenLabel()
                            ->avatar()
                            ->collection('avatars')
                            ->alignCenter()
                            ->columnSpanFull(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('resend_verification')
                                ->label(__('resource.user.actions.resend_verification'))
                                ->color('info')
                                ->action(fn(MailSettings $settings, Model $record) => static::doResendEmailVerification($settings, $record)),
                        ])
                            // ->hidden(fn (User $user) => $user->email_verified_at != null)
                            ->hiddenOn('create')
                            ->fullWidth(),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                                    ->dehydrated(fn(?string $state): bool => filled($state))
                                    ->revealable()
                                    ->required(),
                                Forms\Components\TextInput::make('passwordConfirmation')
                                    ->password()
                                    ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                                    ->dehydrated(fn(?string $state): bool => filled($state))
                                    ->revealable()
                                    ->same('password')
                                    ->required(),
                            ])
                            ->compact()
                            ->hidden(fn(string $operation): bool => $operation === 'edit'),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Placeholder::make('email_verified_at')
                                    ->label(__('resource.general.email_verified_at'))
                                    ->content(fn(User $record): ?string => new HtmlString("$record->email_verified_at")),
                                Forms\Components\Placeholder::make('created_at')
                                    ->label(__('resource.general.created_at'))
                                    ->content(fn(User $record): ?string => $record->created_at?->diffForHumans()),
                                Forms\Components\Placeholder::make('updated_at')
                                    ->label(__('resource.general.updated_at'))
                                    ->content(fn(User $record): ?string => $record->updated_at?->diffForHumans()),
                            ])
                            ->compact()
                            ->hidden(fn(string $operation): bool => $operation === 'create'),
                    ])
                    ->columnSpan(1),

                    Forms\Components\Tabs::make()
                    ->schema([
                        Forms\Components\Tabs\Tab::make('Details')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('username')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->rules(function ($record) {
                                        $userId = $record?->id;
                                        return $userId
                                            ? ['unique:users,username,' . $userId]
                                            : ['unique:users,username'];
                                    }),

                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(function ($record) {
                                        $userId = $record?->id;
                                        return $userId
                                            ? ['unique:users,email,' . $userId]
                                            : ['unique:users,email'];
                                    }),

                                Forms\Components\TextInput::make('firstname')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('lastname')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Roles')
                            ->icon('fluentui-shield-task-48')
                            ->schema([
                                Forms\Components\Select::make('roles')
                                    ->hiddenLabel()
                                    ->relationship('roles', 'name')
                                    ->getOptionLabelFromRecordUsing(fn(Model $record) => Str::headline($record->name))
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->optionsLimit(5)
                                    ->columnSpanFull(),
                            ])
                    ])
                    ->columnSpan([
                        'sm' => 1,
                        'lg' => 2
                    ]),

                // Forms\Components\DateTimePicker::make('email_verified_at'),
                // Forms\Components\Textarea::make('two_factor_secret')
                //     ->columnSpanFull(),
                // Forms\Components\Textarea::make('two_factor_recovery_codes')
                //     ->columnSpanFull(),
                // Forms\Components\DateTimePicker::make('two_factor_confirmed_at'),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('media')->label('Avatar')
                    ->collection('avatars')
                    ->wrap(),
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->description(fn(Model $record) => $record->firstname . ' ' . $record->lastname)
                    ->searchable(),
                // Tables\Columns\TextColumn::make('firstname')
                //     ->searchable(),
                // Tables\Columns\TextColumn::make('lastname')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Role')
                    ->formatStateUsing(fn($state): string => Str::headline($state))
                    ->colors(['info'])
                    ->badge(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('Verified at')
                    ->dateTime()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('two_factor_confirmed_at')
                //     ->dateTime()
                //     ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->email;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['email', 'firstname', 'lastname'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'name' => $record->firstname . ' ' . $record->lastname,
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __("menu.nav_group.access");
    }

    public static function doResendEmailVerification($settings = null, $user): void
    {
        if (!method_exists($user, 'notify')) {
            $userClass = $user::class;

            throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
        }

        if ($settings->isMailSettingsConfigured()) {
            $notification = new VerifyEmail();
            $notification->url = Filament::getVerifyEmailUrl($user);

            $settings->loadMailSettingsToConfig();

            $user->notify($notification);


            Notification::make()
                ->title(__('resource.user.notifications.verify_sent.title'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('resource.user.notifications.verify_warning.title'))
                ->body(__('resource.user.notifications.verify_warning.description'))
                ->warning()
                ->send();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
```






## 20. Install Filament Exception Plugin

[`bezhansalleh/filament-exceptions`](https://github.com/bezhanSalleh/filament-exceptions)

```bash
composer require bezhansalleh/filament-exceptions
```

Publish and run the migration via:

```bash
php artisan exceptions:install
```

Translations

```bash
php artisan vendor:publish --tag=filament-exceptions-translations
```

Register the plugin for the Filament Panel

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            \BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin::make()
        ]);
}
```

### Create Exception Handler

`app/Exceptions/Handler.php`

```php
<?php

namespace App\Exceptions;

use BezhanSalleh\FilamentExceptions\FilamentExceptions;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e)) {
                FilamentExceptions::report($e);
            }
        });
    }
}
```

## 21. Install Z3d0x Filament Logger

[`z3d0x/filament-logger`](https://github.com/Z3d0X/filament-logger)
[Spatie Laravel Activity Log](https://spatie.be/docs/laravel-activitylog/v4/installation-and-setup)

```bash
composer require z3d0x/filament-logger
php artisan filament-logger:install
php artisan vendor:publish --tag="filament-logger-translations"
```

### Edit Activity Log table

Rollback Migration

```bash
php artisan migrate:rollback
```

Goto `` and change:

```php
$table->nullableUuidMorphs('subject', 'subject');
$table->nullableUuidMorphs('causer', 'causer');
```

Then migrate

```bash
php artisan migrate
```

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            config('filament-logger.activity_resource')
        ]);
}
```

```bash
art shield:generate --resource=UserResource
art shield:generate --resource=ExceptionResource
art shield:generate --resource=MediaResource
art shield:generate --resource=FolderResource
art shield:generate --resource=ActivityResource
```

Create AuthServiceProvider

```bash
php artisan make:provider AuthServiceProvider
```

Edit `app/Providers/AuthServiceProvider.pnp`

```php
public function boot(): void
    {
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);
        Gate::policy(\BezhanSalleh\FilamentExceptions\Models\Exception::class, \App\Policies\ExceptionPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);
    }
```

## 22. Install Opcodesio Log Viewer

[`opcodesio/log-viewer`](https://github.com/opcodesio/log-viewer)

```bash
composer require opcodesio/log-viewer
php artisan log-viewer:publish
```

Check it by go to http://localhost/log-viewer

Add to AdminPanelProvider

```php
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->navigationGroups([
                Navigation\NavigationGroup::make()
                    ->label(__('menu.nav_group.activities'))
                    ->collapsed(),
            ])
            ->navigationItems([
                Navigation\NavigationItem::make('Log Viewer') // !! To-Do: lang
                    ->visible(fn(): bool => auth()->user()->can('access_log_viewer'))
                    ->url(config('app.url').'/'.config('log-viewer.route_path'), shouldOpenInNewTab: true)
                    ->icon('fluentui-document-bullet-list-multiple-20-o')
                    ->group(__('menu.nav_group.activities'))
                    ->sort(99),
            ])
```

Add to AppServiceProvider

```php
// # \Opcodes\LogViewer
LogViewer::auth(function ($request) {
    $role = auth()?->user()?->roles?->first()->name;
    return $role == config('filament-shield.super_admin.name');
});
```


## 23. Install Filament Spatie Laravel Tags Plugin

[`filament/spatie-laravel-tags-plugin`](https://filamentphp.com/plugins/filament-spatie-tags)

```bash
composer require filament/spatie-laravel-tags-plugin:"^3.2" -W
php artisan vendor:publish --provider="Spatie\Tags\TagsServiceProvider" --tag="tags-migrations"
```

Goto `database/migrations/xxxx_xx_xx_xxxxxx_create_tag_tables.php` and edit on `taggables` table:

```php
$table->ulidMorphs('taggable');
```

Run the migration

```bash
php artisan migrate
```

## 24. Install League Commonmark

[`league/commonmark`](https://commonmark.thephpleague.com/)

```bash
composer require league/commonmark
```


## 25. Install Stichoza Google Translate PHP

[`stichoza/google-translate-php`](https://github.com/Stichoza/google-translate-php)

```bash
composer require stichoza/google-translate-php
```

## 26. Install `aymanalhattami/filament-slim-scrollbar` Filament Plugin

[Github](https://github.com/aymanalhattami/filament-slim-scrollbar)

```bash
composer require aymanalhattami/filament-slim-scrollbar
```

## 27. Install `joshembling/image-optimizer` Filament Plugin

[Github](https://github.com/joshembling/image-optimizer)

Optimize your Filament images before they reach your database.

```bash
composer require joshembling/image-optimizer
```

Usage Example

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

SpatieMediaLibraryFileUpload::make('attachment')
    ->image()
    ->optimize('webp'),
```

## 28. Install Filament Progressbar

[Github](https://github.com/njxqlus/filament-progressbar)

```bash
composer require njxqlus/filament-progressbar
```

Add to `AdminPanelProvider`

```php
->plugin([
    // ...
    \Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin::make()->color(app(GeneralSettings::class)->site_theme['primary']),
]);
```

## 29. Install Filament Backgrounds

Beautiful backgrounds for Filament auth pages

[Github](https://github.com/swisnl/filament-backgrounds)

```bash
composer require swisnl/filament-backgrounds
php artisan filament-backgrounds:install
```

Add to AdminPanelProvider

```php
->plugins([
    \Swis\Filament\Backgrounds\FilamentBackgroundsPlugin::make(),
])
```

## 30. Install Light Switch Filament Plugin

[Github](https://github.com/awcodes/light-switch)

```bash
composer require awcodes/light-switch
```

Add it to the AdminPanelProvider

```php
->plugins([
    \Awcodes\LightSwitch\LightSwitchPlugin::make(),
]);
```

## 31. Install Awcodes Outlook Filament Plugin

[Github](https://github.com/awcodes/overlook)

```bash
composer require awcodes/overlook
```

Add the plugin's views to your tailwind.config.js file.

`resources/sass/filament/admin/tailwind.config.js`

```js
content: [
    // ...
    "./vendor/awcodes/overlook/resources/**/*.blade.php"
]
```

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            // ...
            \Awcodes\Overlook\OverlookPlugin::make()
                ->sort(2)
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'md' => 3,
                    'lg' => 4,
                    'xl' => 5,
                    '2xl' => null,
                ]),
        ])
        ->widgets([
            \Awcodes\Overlook\Widgets\OverlookWidget::class,
        ]);
}      
```

## 32. Adding some command

### GenerateLang

```bash
php artisan make:command GenerateLang
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GenerateLang extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superduper:lang-translate {from} {to*} {--file=} {--json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate language files from one language to another using Google Translate';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = $this->argument('from');
        $targets = $this->argument('to');
        $specificFile = $this->option('file');
        $onlyJson = $this->option('json');
        $sourcePath = "lang/{$from}";

        if (!$onlyJson && !File::isDirectory($sourcePath)) {
            $this->error("The source language directory does not exist: {$sourcePath}");
            return;
        }

        if ($onlyJson) {
            $sourcePath = "lang/{$from}.json";
            if (!File::isFile($sourcePath)) {
                $this->error("The source language json file does not exist: {$sourcePath}");
                return;
            }
        }

        if ($onlyJson) {
            $this->processJsonFile($sourcePath, $from, $targets);
        } else {
            $this->processDirectory($sourcePath, $from, $targets, $specificFile);
        }

        $this->info("\n\n All files have been translated. \n");
    }

    protected function processJsonFile(string $sourceFile, string $from, array|string $targets): void
    {
        foreach ($targets as $to) {
            $this->info("\n\n 🔔 Translate to '{$to}'");

            $translations = json_decode(File::get($sourceFile), true, 512, JSON_THROW_ON_ERROR);

            $bar = $this->output->createProgressBar(count($translations));
            $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% -- %message%");
            $bar->setMessage('Initializing...');
            $bar->start();

            $bar->setMessage("🔄 Processing: {$sourceFile}");
            $bar->display();

            $translated = $this->translateArray($translations, $from, $to, $bar);

            $targetPath = "lang/{$to}.json";

            $outputContent = json_encode($translated, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            File::put($targetPath, $outputContent);

            $bar->setMessage("✅");
        }

        $bar->finish();
    }

    protected function processDirectory(string $sourcePath, string $from, array|string $targets, bool|array|string|null $specificFile): void
    {
        $filesToProcess = [];
        if ($specificFile) {
            $filePath = $sourcePath . '/' . $specificFile;
            if (!File::exists($filePath)) {
                $this->error("The specified file does not exist: {$filePath}");
                return;
            }
            $filesToProcess[] = ['path' => $filePath, 'relativePathname' => $specificFile];
        } else {
            foreach (File::allFiles($sourcePath) as $file) {
                $filesToProcess[] = ['path' => $file->getPathname(), 'relativePathname' => $file->getRelativePathname()];
            }
        }

        foreach ($targets as $to) {
            $this->info("\n\n 🔔 Translate to '{$to}'");

            $bar = $this->output->createProgressBar(count($filesToProcess));
            $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% -- %message%");
            $bar->setMessage('Initializing...');
            $bar->start();

            foreach ($filesToProcess as $fileInfo) {
                $filePath = $fileInfo['relativePathname'];

                $bar->setMessage("🔄 Processing: {$filePath}");
                $bar->display();

                $translations = include $fileInfo['path'];
                $translated = $this->translateArray($translations, $from, $to);

                $targetPath = "lang/{$to}/" . dirname($filePath);
                if (!File::isDirectory($targetPath)) {
                    File::makeDirectory($targetPath, 0755, true, true);
                }

                $outputFile = "{$targetPath}/" . basename($filePath);
                $outputContent = "<?php\n\nreturn " . $this->arrayToString($translated) . ";\n";
                File::put($outputFile, $outputContent);

                $bar->advance();

                $bar->setMessage("✅");
            }

            $bar->finish();
        }
    }

    protected function translateArray($content, $source, $target, $bar = null)
    {
        if (is_array($content)) {
            foreach ($content as $key => $value) {
                $content[$key] = $this->translateArray($value, $source, $target);
                $bar?->advance();
            }
            return $content;
        } else if ($content === '' || $content === null) {
            $this->error("Translation value missing, make sure all translation values are not empty, in the source file!");
            exit();
        } else {
            return $this->translateUsingGoogleTranslate($content, $source, $target);
        }
    }

    public function translateUsingGoogleTranslate($content, string $source, string $target)
    {
        try {
            // Use Stichoza\GoogleTranslate\GoogleTranslate for translation
            $tr = new GoogleTranslate();
            $tr->setSource($source);
            $tr->setTarget($target);
            return $tr->translate($content);
        } catch (\Exception $e) {
            $this->error("Failed to translate text: " . $e->getMessage());
            return $content; // Return original text if translation fails
        }
    }

    protected function arrayToString(array $array, $indentLevel = 1)
    {
        $indent = str_repeat('    ', $indentLevel); // 4 spaces for indentation
        $entries = [];

        foreach ($array as $key => $value) {
            $entryKey = is_string($key) ? "'$key'" : $key;
            if (is_array($value)) {
                $entryValue = $this->arrayToString($value, $indentLevel + 1);
                $entries[] = "$indent$entryKey => $entryValue";
            } else {
                $entryValue = is_string($value) ? "'" . addcslashes($value, "'") . "'" : $value;
                $entries[] = "$indent$entryKey => $entryValue";
            }
        }

        $glue = ",\n";
        $body = implode($glue, $entries);
        if ($indentLevel > 1) {
            return "[\n$body,\n" . str_repeat('    ', $indentLevel - 1) . ']';
        } else {
            return "[\n$body\n$indent]";
        }
    }
}
```

### DevIde

```bash
php artisan make:command DevIde
```


```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DevIde extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate IDE Helpers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('ide-helper:generate');
        $this->call('ide-helper:models', [
            '--nowrite' => true,
        ]);
        $this->call('ide-helper:meta');
    }
}
```

### ProjectInitialize

```bash
php artisan make:command ProjectInitialize
```


```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProjectInitialize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Project Initialization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('migrate:fresh', [
            '--force' => true,
        ]);
        $this->call('shield:generate', [
            '--all' => true,
        ]);
        $this->call('db:seed', [
            '--force' => true,
        ]);

        $this->call('optimize:clear');
    }
}
```

### ProjectUpdate

```bash
php artisan make:command ProjectUpdate
```


```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProjectUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Project Update';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('migrate');
        $this->call('shield:generate', [
            '--all' => true,
        ]);
        $this->call('optimize:clear');
    }
}
```

## 33. Seeders

### Role

```bash
php artisan make:seeder RolesAndPermissionsSeeder
```

The Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Filament\Resources\Shield\RoleResource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $accessLogViewer = Permission::findByName('access_log_viewer');

        if (! $accessLogViewer)
            Permission::create(['name' => 'access_log_viewer']);

        $roles = ["super_admin", "admin", "author"];

        foreach ($roles as $key => $role) {
            $exist = Role::where('name', '=', $role)->first();

            if (!$exist) {
                $roleCreated = (new (RoleResource::getModel()))->create(
                    [
                        'name' => $role,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if ($role == 'super_admin') {
                    $roleCreated->givePermissionTo('access_log_viewer');
                }
            } else {
                if ($role == 'super_admin') {
                    // check if super_admin has access_log_viewer permission
                     if (! $exist->hasPermissionTo('access_log_viewer')) {
                        $exist->givePermissionTo('access_log_viewer');
                     }
                }
            }
        }
    }
}
```

### User

```bash
php artisan make:seeder UserTableSeeder
```

The Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Faker\Factory as Faker;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Superadmin user
        $sid = Str::uuid();
        DB::table('users')->insert([
            'id' => $sid,
            'username' => 'superadmin',
            'firstname' => 'Super',
            'lastname' => 'Admin',
            'email' => 'superadmin@starter-kit.com',
            'email_verified_at' => now(),
            'password' => Hash::make('superadmin'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Bind superadmin user to FilamentShield
        Artisan::call('shield:super-admin', ['--user' => $sid]);

        $roles = DB::table('roles')->whereNot('name', 'super_admin')->get();
        foreach ($roles as $role) {
            for ($i = 0; $i < 10; $i++) {
                $userId = Str::uuid();
                DB::table('users')->insert([
                    'id' => $userId,
                    'username' => $faker->unique()->userName,
                    'firstname' => $faker->firstName,
                    'lastname' => $faker->lastName,
                    'email' => $faker->unique()->safeEmail,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('model_has_roles')->insert([
                    'role_id' => $role->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }
}
```
