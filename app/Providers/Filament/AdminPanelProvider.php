<?php

namespace App\Providers\Filament;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * The prototype's gold ornament star (docs reference:
     * ~/Downloads/Telegram Desktop/qr-dasturxon-prototype-3.html,
     * `.brandmark .ornament` — see CLAUDE.md "Dizayn manbasi"), copied
     * exactly (same viewBox/path/stroke), used as both the sidebar
     * brandmark and the favicon so both come from one definition.
     */
    private const STAR_SVG = <<<'SVG'
        <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><circle cx="30" cy="30" r="28" fill="none" stroke="#C79A3E" stroke-width="2"/><path d="M30 8 L34 26 L52 30 L34 34 L30 52 L26 34 L8 30 L26 26 Z" fill="#C79A3E"/></svg>
        SVG;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Browser tab title only ("Dashboard - QR Dasturxon") — the
            // visible sidebar/login brandmark comes from brandLogo() below.
            ->brandName('QR Dasturxon')
            ->brandLogo(new HtmlString(
                '<span style="display:inline-flex;align-items:center;gap:.5rem;font-family:\'Cormorant Garamond\',serif;font-weight:700;font-size:1.25rem;">'
                .self::STAR_SVG
                .'<span>QR <span style="color:#C79A3E;">Dasturxon</span></span></span>',
            ))
            ->brandLogoHeight('1.75rem')
            ->favicon('data:image/svg+xml;base64,'.base64_encode(self::STAR_SVG))
            // Filament's dark mode is opt-in per browser/OS preference by
            // default; kept explicit here since the brand toggle in CLAUDE.md
            // asks for it — this call changes nothing, it just documents
            // the (already-true) default rather than forcing either mode.
            ->darkMode(true, isForced: false)
            ->login()
            // Own guard/provider (config/auth.php) — see App\Models\PlatformAdmin.
            // Deliberately unrelated to the customer (telegram.auth) or
            // staff (staff.auth) auth paths.
            ->authGuard('platform_admin')
            // Brend ranglari — prototype's :root tokens (CLAUDE.md
            // "Dizayn manbasi"): --maroon/--maroon-deep/--maroon-dark,
            // --gold. `warning` uses Color::hex('#C79A3E') as-is — gold's
            // real lightness (0.71) is already close to what Filament's
            // auto-generated mid-shade would pick, so the result reads
            // true to the swatch. `primary` needed a hand-built ramp
            // instead: --maroon is a genuinely DARK, muted red
            // (oklch lightness 0.40) — Color::hex() only reuses a color's
            // HUE and re-derives every shade from Filament's own fixed
            // lightness/chroma curve, which places shade 600 (what solid
            // buttons/active nav items use) at lightness 0.60 — so the
            // button rendered as a bright coral/salmon, not maroon, even
            // though the hue was technically correct. Anchoring 600/800/900
            // at the exact brand hexes (with 700/950 interpolated between
            // them) keeps every other Filament UI piece that reads
            // primary-* looking like the real brand color instead.
            ->colors([
                'primary' => [
                    50 => '#FBF2EE',
                    100 => '#F3DEDD',
                    200 => '#E3B7BD',
                    300 => '#D0919C',
                    400 => '#B15A6C',
                    500 => '#973F4C',
                    600 => '#7A2331', // --maroon
                    700 => '#631C28',
                    800 => '#4E1620', // --maroon-deep
                    900 => '#3A1420', // --maroon-dark
                    950 => '#210B12',
                ],
                'warning' => Color::hex('#C79A3E'), // --gold
            ])
            // Manrope everywhere Filament uses its default (sans) font —
            // matches the customer/staff pages' body font (CLAUDE.md).
            ->font('Manrope', provider: GoogleFontProvider::class)
            // Cormorant Garamond is registered the same way the customer
            // pages load it, and IS used above in the brandmark — but
            // Filament's own stock views (page headings, table headers,
            // etc.) never apply the `font-serif` utility class anywhere
            // in their Blade templates, only `font-sans`. Without writing
            // a custom view/CSS to add that class to those elements —
            // explicitly out of scope here — registering the font alone
            // doesn't change what font any *existing* Filament UI text
            // renders in beyond the brandmark, which sets it inline
            // itself. Documented in CLAUDE.md.
            ->serifFont('Cormorant Garamond', provider: GoogleFontProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
