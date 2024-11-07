<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\Navigation;
use Filament\PanelProvider;
use App\Settings\GeneralSettings;
use App\Filament\Pages\Auth\Login;
use Filament\Support\Colors\Color;
use App\Livewire\MyProfileExtended;
use Illuminate\Support\Facades\Storage;
use Filament\Http\Middleware\Authenticate;
use Filament\FontProviders\GoogleFontProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use App\Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('fadmin')
            ->path('fadmin')
            ->spa(true)
            ->font('Inter', provider: GoogleFontProvider::class)
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class)
            ->emailVerification(EmailVerificationPrompt::class)
            ->favicon(fn(GeneralSettings $settings) => Storage::url($settings->site_favicon))
            ->brandName(fn(GeneralSettings $settings) => $settings->brand_name)
            ->brandLogo(fn(GeneralSettings $settings) => Storage::url($settings->brand_logo))
            ->brandLogoHeight(fn(GeneralSettings $settings) => $settings->brand_logoHeight)
            ->colors(fn(GeneralSettings $settings) => $settings->site_theme)
            ->maxContentWidth('7xl')
            ->databaseNotifications()->databaseNotificationsPolling('30s')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop(true)
            ->navigationGroups([
                Navigation\NavigationGroup::make()
                    ->label('Content') // !! To-Do: lang
                    ->collapsible(false),
                Navigation\NavigationGroup::make()
                    ->label(__('menu.nav_group.access'))
                    ->collapsible(false),
                Navigation\NavigationGroup::make()
                    ->label(__('menu.nav_group.settings'))
                    ->collapsed(),
                Navigation\NavigationGroup::make()
                    ->label(__('menu.nav_group.activities'))
                    ->collapsed(),
            ])
            ->navigationItems([
                Navigation\NavigationItem::make('Log Viewer') // !! To-Do: lang
                    ->visible(fn(): bool => auth()->user()->can('access_log_viewer'))
                    ->url(config('app.url') . '/' . config('log-viewer.route_path'), shouldOpenInNewTab: true)
                    ->icon('fluentui-document-bullet-list-multiple-20-o')
                    ->group(__('menu.nav_group.activities'))
                    ->sort(99),
            ])
            ->viteTheme('resources/sass/filament/admin/theme.scss')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                config('filament-logger.activity_resource')
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                \Awcodes\Overlook\Widgets\OverlookWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 2,
                        'sm' => 1,
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
                \Datlechin\FilamentMenuBuilder\FilamentMenuBuilderPlugin::make()->usingResource(\App\Filament\Resources\MenuResource::class)
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
                \BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin::make(),
                \TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin::make()
                    ->allowSubFolders(),
                // \Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin::make()->color(app(GeneralSettings::class)->site_theme['primary']),
                \Swis\Filament\Backgrounds\FilamentBackgroundsPlugin::make()
                    ->showAttribution(false),
                \Awcodes\LightSwitch\LightSwitchPlugin::make()
                    ->position(\Awcodes\LightSwitch\Enums\Alignment::BottomCenter)
                    ->enabledOn([
                        'auth.login',
                        'auth.password',
                    ]),
                \Awcodes\Overlook\OverlookPlugin::make()
                    // ->includes([
                    //     \App\Filament\Resources\UserResource::class,
                    // ])
                    // ->sort(2)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'md' => 3,
                        'lg' => 4,
                        'xl' => 5,
                        '2xl' => null,
                    ]),
            ]);

    }
}
