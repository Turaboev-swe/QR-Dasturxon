<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::input('name_translations', 'Nomi'),

                Section::make('Holat')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Faol')
                            ->helperText("O'chirilsa, mijozlar bu restoranga QR orqali kira olmaydi.")
                            ->default(true)
                            ->required(),
                        Toggle::make('is_verified')
                            ->label('Tasdiqlangan')
                            ->helperText('Mijoz sahifasida tasdiqlangan belgisi.')
                            ->required(),
                        TextInput::make('badge_text')
                            ->label('Belgi matni')
                            ->helperText('Masalan: "TOP-10 milliy oshxona"')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Telegram guruh xabarnomalari')
                    ->description("Bot shu guruhlarga a'zo bo'lishi shart, aks holda xabarnoma jim yuborilmaydi.")
                    ->columns(2)
                    ->schema([
                        TextInput::make('kitchen_chat_id')
                            ->label('Oshxona chat ID')
                            ->numeric()
                            ->helperText('Manfiy son (guruh/superguruh).'),
                        TextInput::make('waiter_chat_id')
                            ->label('Ofitsiant chat ID')
                            ->numeric()
                            ->helperText('Manfiy son (guruh/superguruh).'),
                    ]),

                Section::make("Joylashuv (faqat ma'lumot uchun)")
                    ->description("Geolokatsiya hech qanday tekshiruv uchun ishlatilmaydi — faqat umumiy ma'lumot.")
                    ->columns(3)
                    ->schema([
                        TextInput::make('latitude')
                            ->numeric()
                            ->required(),
                        TextInput::make('longitude')
                            ->numeric()
                            ->required(),
                        TextInput::make('radius_meters')
                            ->numeric()
                            ->default(150)
                            ->required(),
                    ]),
            ]);
    }
}
