<?php

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\PdfDocumentPdfController;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jacobtims\FilamentLogger\FilamentLoggerPlugin;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('admin')
            ->profile()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                //
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(fn () => setting('general.logo') ? asset('storage/'.setting('general.logo')) : '')
            ->brandName(config('app.name'))
            ->favicon(fn () => setting('general.favicon') ? asset('storage/'.setting('general.favicon')) : '')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()->globallySearchable(false)->registerNavigation(false),
                FilamentLoggerPlugin::make(),
                FilamentLogViewer::make(),
                SpatieTranslatablePlugin::make()->persist(),
            ])
            // Closures so the labels still match after the locale switches to Arabic.
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('Documents')),
                NavigationGroup::make(fn (): string => __('Settings')),
                // shortcut: the log viewer freezes its group label in the boot locale, so it stays "System" in Arabic. Translate it if the package fixes this.
                NavigationGroup::make('System'),
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('documents/{document}/pdf', DocumentPdfController::class)->name('documents.pdf');
                Route::get('pdf-documents/{pdfDocument}/pdf', PdfDocumentPdfController::class)->name('pdf-documents.pdf');
            })
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (): string => view('components/developer-login')->render())
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
