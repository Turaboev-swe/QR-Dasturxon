<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Models\Restaurant;
use App\Models\Staff;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Staff have no login screen, no PIN, no password — a member is
 * "registered" simply by writing their real Telegram id here (see
 * CLAUDE.md: staff.auth reads X-Telegram-Init-Data, looks up
 * Staff::where('telegram_id', ...)). This form IS that registration.
 */
class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('restaurant_id')
                    ->label('Restoran')
                    ->relationship('restaurant', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Restaurant $record) => $record->translate('name_translations', 'uz'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Ismi')
                    ->required()
                    ->maxLength(255),
                Select::make('role')
                    ->label('Roli')
                    ->options([
                        Staff::ROLE_WAITER => 'Ofitsiant',
                        Staff::ROLE_CASHIER => 'Kassir',
                        Staff::ROLE_ADMIN => 'Egasi (admin)',
                    ])
                    ->required(),
                TextInput::make('telegram_id')
                    ->label('Telegram ID')
                    ->helperText(
                        "Xodimning haqiqiy Telegram foydalanuvchi ID'si — login/parol/PIN YO'Q, ".
                        'xodim ilovani ochganda shu ID orqali avtomatik aniqlanadi. '.
                        "ID'ni bilish uchun xodim botga (masalan @userinfobot) yozishi mumkin.",
                    )
                    ->numeric()
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label('Telefon (ixtiyoriy)')
                    ->helperText("Faqat ma'lumot uchun — autentifikatsiyada ishlatilmaydi.")
                    ->tel(),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->helperText("O'chirilsa, xodim /api/staff/* ga kira olmaydi.")
                    ->default(true)
                    ->required(),
            ]);
    }
}
