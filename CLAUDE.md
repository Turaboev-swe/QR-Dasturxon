# QR Dasturxon

Telegram Mini App restoran buyurtma tizimi. Backend: Laravel + MySQL.

## Asosiy tamoyil
Foydalanuvchi hech qanday login/register sahifasini ko'rmaydi. Stoldagi QR
kod to'g'ridan-to'g'ri Telegram bot ichidagi Mini App'ni ochadi
(`t.me/Bot/menu?startapp=r1_t5`), foydalanuvchi Telegram `initData` orqali
avtomatik aniqlanadi va yaratiladi.

## Buzilmasligi kerak bo'lgan xavfsizlik qoidalari
- Narx HAR DOIM serverda `dishes` jadvalidan qayta hisoblanadi. Frontenddan
  kelgan narxga hech qachon ishonilmaydi.
- **Geolokatsiya endi TEKSHIRILMAYDI** (ataylab olib tashlangan — har bir
  restoran/stol o'zining alohida QR kodiga ega, shuning uchun signed
  `start_param`ning o'zi yetarli isbot hisoblanadi; qurilma GPS'i shart
  emas). `Restaurant::isWithinRadius()` va unga bog'liq frontend
  geolokatsiya so'rovi butunlay o'chirilgan — `restaurants.latitude/
  longitude/radius_meters` ustunlari faqat umumiy ma'lumot sifatida
  qoladi, hech qanday gate uchun ishlatilmaydi.
- Stol identifikatori foydalanuvchi tanlovi emas — QR'dagi `start_param`
  (`r{restaurant_id}_t{table_code}`) orqali keladi. Yagona ataylab
  qo'yilgan istisno: `GET /api/menu` `start_param` yo'q/noto'g'ri bo'lsa
  (masalan oddiy ☰ Menu tugmasi orqali ochilganda — u start_param
  yubormaydi) 422 qaytarish o'rniga eng faol restoranning menyusini
  `demo: true` bilan **faqat o'qish uchun** qaytaradi — bu faqat UX uchun
  (o'lik ekran o'rniga). Har qanday YOZUV endpointi (`POST /orders`,
  `POST /waiter-calls`, `POST /reviews`) hamon mustaqil ravishda
  `TableResolver` orqali `start_param`ni talab qiladi va uni topolmasa
  422 qaytaradi — demo rejimida frontend ularning tugmalarini shunchaki
  yashiradi, lekin haqiqiy xavfsizlik chegarasi serverda, o'zgarmagan.
- Har bir mijoz API so'rovi `telegram.auth` middleware orqali o'tadi
  (`X-Telegram-Init-Data` header). Bu middleware `TelegramUser`ni
  `updateOrCreate` bilan avtomatik topadi/yaratadi.
- Har bir xodim (kassa/egasi) API so'rovi `staff.auth` middleware orqali
  o'tadi — **mijoz bilan bir xil** `X-Telegram-Init-Data` header orqali,
  **login/parol/PIN yo'q**: middleware initData'ni tekshiradi, undan
  Telegram `user.id`ni oladi va `staff.telegram_id` bo'yicha mos yozuvni
  qidiradi (`is_active=true` bo'lishi shart). Topilmasa — 403. Xodimni
  "ro'yxatdan o'tkazish" degani shunchaki uning haqiqiy Telegram ID'sini
  `staff` jadvaliga qo'lda/panel orqali yozish, u hech qanday login
  ekranini ko'rmaydi (mijoz uchun bosh tamoyil bilan bir xil). Faqat
  egasi (`admin`) uchun endpointlar `staff.admin` middleware bilan
  qo'shimcha qamalgan (`/api/staff/admin/*`).

## Joriy holat
- Laravel o'rnatilgan. Sanctum qo'shilmagan — autentifikatsiya ikkita
  mustaqil yo'l bilan, ikkalasi ham **faqat Telegram `initData`** orqali,
  login/parol hech qayerda yo'q: mijozlar uchun `telegram.auth`, xodimlar
  uchun `staff.auth` (quyida).
- Migratsiyalar tayyor: `restaurants` (+ `is_verified`/`badge_text`),
  `restaurant_tables`, `categories`, `dishes` (+ `allergens` json, +
  flash-chegirma maydonlari `discount_percent`/`discount_ends_at`/
  `discount_portions_total`/`discount_portions_remaining`, + ta'm profili
  `taste_spicy`/`taste_sweet`/`taste_salty`), `telegram_users`, `staff`
  (+ `telegram_id` unique nullable, `phone` endi faqat ma'lumot uchun
  ixtiyoriy — `pin_code_hash`/`api_token_hash` butunlay olib tashlangan,
  `2026_08_24_000001_switch_staff_auth_to_telegram.php` migratsiyasida),
  `orders` (+ `payment_preference`: `now`/`later`), `order_items`,
  `reviews` (+ `order_id` unique), `waiter_calls` (+ `type`:
  `waiter`/`bill`), `payments`, `chefs` (restoran oshpazi: `title`,
  `experience_years`, `specialty`, `tier_badge`, `photo_path`).
- Modellar: `app/Models/*`. `App\Models\Concerns\HasTranslations::translate()`
  orqali tarjima maydonlaridan tilga mos qiymat olinadi (fallback: `uz`).
  `Order` va `WaiterCall`da `canTransitionTo()` — ruxsat etilgan holat
  o'tishlari qattiq belgilangan (masalan `Order`: pending→confirmed→
  preparing→ready→served→paid, istalgan bosqichdan cancelled'ga; `paid`/
  `cancelled` terminal). `Dish::hasLiveDiscount()`/`effectivePrice()` —
  chegirma holati HAR DOIM so'rov vaqtida hisoblanadi (muddati/porsiyasi
  tugagan bo'lsa DB'da qiymat qolgan bo'lsa ham `false`/asl narx qaytadi
  — cron shart emas). `Staff::isAdmin()` — faqat `role===admin`
  (`canManageOrders()`dan farqli, u kassirni ham o'z ichiga oladi).
- `app/Services/TelegramAuth.php` — initData HMAC-SHA256 tekshiruvi
  (`config('services.telegram.bot_token')` / `TELEGRAM_BOT_TOKEN` env) +
  `sign()` (dev/test uchun initData imzolash).
- `app/Services/TableResolver.php` — `start_param`dan (`r{id}_t{code}`)
  restoran va stolni aniqlaydi; buni SessionController, MenuController,
  OrderController va WaiterCallController baravar ishlatadi (stol/restoran
  hech qachon request'dan emas, faqat imzolangan initData'dan olinadi).
  Resolve bo'lmasa **422** qaytaradi (URL resursi emas, mijoz kiritmasi
  xatosi hisoblanadi — 404 emas).
- `app/Services/StaffAuth.php` — **olib tashlangan** (telefon+PIN tizimi
  bilan birga). Xodimni aniqlash endi to'g'ridan-to'g'ri
  `StaffAuthMiddleware` ichida, mijoznikiga o'xshash `TelegramAuth::validate()`
  chaqirilib, `Staff::where('telegram_id', ...)` bilan.
- Middleware: `app/Http/Middleware/TelegramAuthMiddleware.php`
  (`telegram.auth`), `StaffAuthMiddleware.php` (`staff.auth` — Telegram
  `initData`dan `telegram_id`ni o'qiydi, `Staff`da qidiradi, request
  attributega `staff`ni qo'yadi) — `bootstrap/app.php`da ro'yxatdan
  o'tgan.
- Controllerlar (`app/Http/Controllers/Api/`): `SessionController`
  (`POST /session`), `MenuController` (`GET /menu`), `OrderController`
  (`GET/POST /orders`, `GET /orders/{id}`, xodim uchun `GET /staff/orders`
  va `PATCH /staff/orders/{id}/status`), `ReviewController`
  (`GET/POST /reviews` — faqat `served`/`paid` buyurtmaga, bitta
  buyurtmaga bitta sharh), `WaiterCallController` (`GET/POST
  /waiter-calls` — `type: waiter|bill`, xodim uchun
  `GET /staff/waiter-calls` va `PATCH /staff/waiter-calls/{id}/status` —
  bir stolga bir vaqtda har bir `type` uchun faqat bitta ochiq chaqiruv,
  ya'ni "ofitsiant" va "hisob" chaqiruvlari bir vaqtda parallel ochiq
  bo'lishi mumkin), `StaffAuthController` (`GET /staff/me` — joriy
  `initData` qaysi xodimga tegishli ekanini qaytaradi; `login`/`logout`
  yo'q, chunki kirish/chiqish umuman mavjud emas), `AdminDishController`
  va `AdminStatsController`
  (`/api/staff/admin/*` — faqat `role=admin`: mahsulot mavjudligi,
  flash-chegirma o'rnatish/bekor qilish, bugungi statistika).
- `routes/api.php` — barcha endpointlar yozilgan va ishlaydi (19 route;
  `/staff/login` va `/staff/logout` olib tashlangan — login umuman yo'q).
- Frontend **uchta alohida route/sahifaga** bo'lingan — bittasi emas:
  - `GET /` → `resources/views/miniapp.blade.php` — faqat **MIJOZ** ko'rinishi.
  - `GET /staff` → `resources/views/staff.blade.php` — **KASSA**.
  - `GET /owner` → `resources/views/owner.blade.php` — **OSHXONA EGASI**.
  Uchalasi ham HTML uchun `resources/views/miniapp/_customer.blade.php`,
  `_cashier.blade.php`, `_owner.blade.php`, `_styles.blade.php`
  partial'laridan, JS uchun `public/js/miniapp/{i18n,api,customer,
  cashier,owner}.js`dan foydalanadi (`<script src="/js/miniapp/...">` —
  **root-relative yo'l, `asset()` EMAS**: `asset()` `http://` deb
  absolyut URL quradi, chunki mahalliy `php artisan serve` oldida tunnel
  HTTPS'ni tugatib qo'yayotganini bilmaydi; natijada haqiqiy `https://`
  tunnel/bot manzilida brauzer buni "Mixed Content" deb bloklagan va
  **butun ilova cheksiz "Yuklanmoqda…"da qotib qolgan edi**. Kelajakda
  yangi statik asset qo'shsangiz ham shu qoidaga rioya qiling.).
  **KASSA/OSHXONA EGASI mijoz sahifasida ko'rinmaydi va u yerga
  bog'lanmagan** — xodim/egasi shu manzillarni to'g'ridan-to'g'ri biladi,
  tasodifiy mijoz ularni tab sifatida ko'rmaydi (avval bittasida edi,
  ataylab ajratildi).
  `/staff` va `/owner`da hech qanday `localStorage` token yo'q — har bir
  so'rov sahifa ochilgan zahoti Telegramning o'zi bergan `initData`ni
  ishlatadi (xuddi mijoz sahifasidagi kabi), shuning uchun eski
  qurilmada qolib ketgan noto'g'ri token muammosi endi butunlay mavjud
  emas: bitta Telegram akkaunt qaysi restoranga, qaysi rolga tegishli
  ekanini server har safar `staff.telegram_id`dan qayta aniqlaydi.
  Dizayn: **prototipning aynan o'zidagi** CSS token va shriftlar
  ishlatiladi — `--paper:#F6F1E7`, `--maroon:#7A2331`,
  `--maroon-deep:#4E1620`, `--gold:#C79A3E`, `--teal:#1F6F6A`,
  `--radius:18px`, `--shadow:0 10px 30px rgba(46,27,18,.16)`, sarlavhalar
  uchun **Cormorant Garamond** (serif), matn uchun **Manrope** — o'zboshim-
  chalik bilan o'ylab topilgan palitra emas. Sessiya xatosi (masalan
  noto'g'ri `start_param`) endi kichik pushti alert emas, balki
  to'liq ekranli maroon gradient + oltin "QR Dasturxon" brandmark bilan
  ko'rsatiladi (`#c-status.error`). Menyu hali yuklanmagan paytda
  "🔔 Ofitsiant"/"🧾 Hisob"/"Sharh qoldirish" kabi elementlar bo'sh
  matn bilan ko'rinib qolmasligi uchun ular endi `#c-loaded-content`
  ichida — faqat menyu muvaffaqiyatli yuklangandan keyin ko'rinadi.
  Barcha uchala sahifa 6 tilda (uz/en/ru/ko/fr/zh) —
  `public/js/miniapp/i18n.js`.
  - **MIJOZ**: til qatori menyuni qayta `?lang=` bilan yuklaydi;
    kategoriya tablari mijoz tomonida filtrlaydi; flash-chegirma banneri
    (agar faol bo'lsa) server bergan `ends_at`dan hisoblangan sanoq bilan;
    taom detali modali (tarkib, allergenlar, ta'm balansi grafikasi —
    faqat `taste_*` maydonlari to'ldirilgan bo'lsa); "🔔 Ofitsiant" va
    "🧾 Hisob" alohida `POST /api/waiter-calls` (`type` bilan) chaqiradi;
    SOS tugmasi — statik favqulodda raqamlar modali (102/103/1178),
    backendga bog'liq emas; sharh qoldirish — mijozning o'zining
    `served`/`paid` va hali sharhlanmagan buyurtmalaridan birini tanlab,
    1-5 yulduz bilan; checkout ekranida "Hozir/Keyinroq to'lash" tanlovi
    `orders.payment_preference`ga yoziladi. `POST /api/session`
    (`start_param`ni talab qiladi) muvaffaqiyatsiz bo'lsa —
    `customer.js`dagi `enterDemoMode()` ishga tushadi: to'liq ekranli
    xatolik o'rniga `GET /api/menu`ning `demo: true` javobi bilan
    **faqat ko'rish** rejimi ko'rsatiladi (stol nishonchasi o'rniga
    "Demo ko'rinish", yashil ma'lumot banneri, "🔔 Ofitsiant"/"🧾
    Hisob"/"Sharh qoldirish" tugmalari va barcha "+" savatga qo'shish
    tugmalari yashirilgan) — bu faqat noto'g'ri havola (☰ Menu tugmasi)
    orqali ochilgan taqdirda o'lik ekran ko'rsatmaslik uchun, xavfsizlik
    chegarasini yumshatmaydi (yozuv endpointlari hamon 422 qaytaradi).
  - **KASSA** (`/staff`): sahifa ochilgan zahoti `GET /api/staff/me`
    chaqiriladi — muvaffaqiyatli bo'lsa darhol dashboard, aks holda
    (403) brendga mos "Siz xodim sifatida ro'yxatdan o'tmagansiz..."
    xabari (hech qanday login formasi yo'q). So'ng
    `GET /api/staff/orders` va `GET /api/staff/waiter-calls`ni ~6
    soniyada bir marta so'raydi (Reverb hali yo'q), har buyurtma uchun
    "Keyingi bosqich"/bekor qilish, har chaqiruv uchun qabul
    qilish/bajarildi tugmalari.
  - **OSHXONA EGASI** (`/owner`): xuddi shunday `GET /api/staff/me`dan
    boshlanadi; `role!==admin` bo'lsa "bu bo'lim faqat egasi (admin)
    uchun" xabari ko'rsatiladi (server tomonda `staff.admin` middleware
    haqiqiy to'siq, klient tomon faqat UX). Flash-chegirma muharriri
    (taom/%/porsiya/daqiqa — bitta restoranda bir vaqtda faqat bitta
    faol chegirma, yangisini qo'yish eskisini avtomatik bekor qiladi),
    mahsulot mavjudligi almashtirgichlari, bugungi statistika
    (buyurtmalar soni, to'langanlar, umumiy summa — faqat `paid`
    holatidagilar hisoblanadi).
  - Push-bildirishnoma banneri **qo'shilmadi** (faqat dekorativ bo'lardi,
    haqiqiy Telegram push — alohida katta ish, Reverb/bot xabar yuborish
    talab qiladi).
  `MenuController` javobiga qo'shilgan: `demo` (yuqoridagi demo rejim
  bayrog'i), `restaurant.is_verified`, `restaurant.badge_text`,
  `restaurant.rating`/`reviews_count` (haqiqiy `reviews` jadvalidan
  `loadCount`/`loadAvg` bilan hisoblanadi, sharh bo'lmasa `rating: null`),
  `restaurant.chef`, `restaurant.recent_reviews` (oxirgi 6 ta izohli
  sharh, faqat ism+baho+izoh — PII yo'q), har bir taomda `allergens`,
  `discount` (faol bo'lsa) va `taste` (to'ldirilgan bo'lsa).
- `php artisan telegram:fake-init-data [--start-param=r1_t1]` — imzolangan
  fake initData va tayyor curl misolini chiqaradi (dev/test uchun).
  `database/seeders/DatabaseSeeder.php` — "Chorbog' Milliy Oshxonasi"
  namunasi: 1 stol, 1 oshpaz, 4 kategoriya (Asosiy/Sho'rva/Salat/
  Ichimliklar), 4 taom (ta'm profili bilan), 6 ta haqiqiy sharh (o'rtacha
  ~4.8), 2 ta xodim — **faqat `telegram_id` bo'yicha**, login/parol yo'q:
  egasi (admin) `telegram_id=1746546661` (**tasdiqlangan haqiqiy restoran
  egasi** — Telegram `@abdulqayum_dev`, F_Name "Mr.Abdulqayum"; bu
  dasturchi placeholder emas, real production qiymat) va kassir (namuna)
  `telegram_id=900000100` (haqiqiy Telegram akkaunti yo'q, faqat
  schema/testlar uchun — real kassir qo'shish uchun uning haqiqiy
  `telegram_id`sini yozish kerak).
  `updateOrCreate` bilan yozilgan (endi `telegram_id` bo'yicha moslashtiradi)
  — qayta ishga tushirish xavfsiz, faqat `radius_meters`ga tegmaydi
  (birinchi yaratilishdan keyin qo'lda kengaytirilgan bo'lishi mumkin,
  masalan haqiqiy qurilmada sinash uchun).
- `.env` va `phpunit.xml` haqiqiy MySQL'ga ulangan (mos ravishda
  `qr_dasturxon` / `qr_dasturxon_testing`, `127.0.0.1:3306`, `root`/`root`
  — mahalliy dasturchi muhiti; CI/production uchun almashtirilishi kerak).
  `phpunit.xml`da test uchun alohida `TELEGRAM_BOT_TOKEN` bor.
- Testlar: `tests/Feature/{SessionInitTest,ReviewTest,WaiterCallTest,
  OrderTest,AdminPanelTest,MenuTest,StaffAuthTest}.php` — `php artisan test`
  bilan barchasi o'tadi (41 test, 100 assertion). Barcha xodim testlari
  endi `TelegramAuth::sign()` bilan imzolangan `X-Telegram-Init-Data`
  header ishlatadi (`/api/staff/login` yo'q). `MenuTest` demo rejimni
  ham qamrab oladi: `start_param`siz `demo: true` va faol restoran
  menyusi qaytishi, faol restoran umuman bo'lmasa 422ga qaytilishi.
  `OrderTest`/`MenuTest` chegirma mantig'ini (narx, muddati/porsiyasi
  tugagach yo'qolishi, yetarli bo'lmagan porsiyaga buyurtma rad etilishi) qamrab oladi;
  `AdminPanelTest` — faqat admin ruxsati, restoranlararo izolyatsiya,
  statistika to'g'riligi; `StaffAuthTest` — ro'yxatdan o'tgan/o'tmagan/
  faol bo'lmagan `telegram_id`ning `GET /api/staff/me`ga ta'siri.
- Haqiqiy Telegram botida (`@qr_dasturxon_bot`) Cloudflare quick tunnel
  orqali sinovdan o'tkazilgan: BotFather'da nomlangan Mini App
  (short name orqali, masalan `qrmenu`) yaratilib, shu havola ishlatiladi:
  `t.me/qr_dasturxon_bot/<short_name>?startapp=r1_t1`. **Muhim**: oddiy
  Menu Button (☰) `start_param` yubormaydi — faqat shu nomlangan-app
  havolasi orqali start_param uzatiladi.
- Hali yozilmagan: Filament admin panel, Laravel Reverb, Click/Payme.

## Dizayn manbasi
Frontend dizayni foydalanuvchi yuborgan HTML/CSS/JS prototipdan olingan.
Bu fayl loyiha ichida **emas** — `~/Downloads/Telegram Desktop/
qr-dasturxon-prototype-3.html` da (mahalliy fayl tizimida, repo tashqarisida).
`docs/prototype-reference.html` degan fayl loyihada yo'q — agar kimdir shu
nomni eslatsa, yuqoridagi haqiqiy faylni nazarda tutmoqda.

## Ko'p tillilik
Tarjima maydonlari JSON ustun sifatida saqlanadi:
`{"uz":"...", "en":"...", "ru":"...", "ko":"...", "fr":"...", "zh":"..."}`
(`name_translations`, `ingredients_translations` va h.k.)

## Kod konvensiyalari
- Controller'lar yupqa (thin) bo'lsin — biznes mantiq Service/Model'da.
- Har bir API javobi JSON, xatolarda mos HTTP status kod.
- Yangi funksiya yozishdan oldin mavjud model/migratsiyani tekshir —
  aksariyat jadval allaqachon mavjud.
- Migratsiya birinchi marta ishga tushgach, uni tahrirlamang — yangi
  migratsiya qo'shing (masalan `reviews.order_id` unique shunday qo'shilgan).

## Keyingi vazifalar (ustuvorlik tartibida)
1. Filament admin panel — xodim qo'shish endi shunchaki uning haqiqiy
   Telegram ID'sini kiritish (login/PIN emas); panel orqali `staff`
   jadvaliga qo'lda `INSERT`/`UPDATE` qilish o'rniga qulay UI berish.
2. Laravel Reverb (hozir KASSA/OSHXONA EGASI ~6 soniyalik polling bilan
   ishlaydi — buni real-time push'ga almashtirish) va Click/Payme
   integratsiyasi (`orders.payment_preference` allaqachon "hozir/keyin"
   niyatini saqlaydi, lekin haqiqiy to'lov shlyuzi ulanmagan).
3. Mini App'ni doimiy (vaqtinchalik tunnel emas) HTTPS manzilga deploy
   qilish va botni shunga qarab qayta sozlash.
4. Oshpaz/taom rasmlari (`chefs.photo_path`, `dishes.image_path`) va
   `dishes.taste_*` uchun OSHXONA EGASI panelida boshqaruv UI — hozircha
   faqat seeder orqali to'ldiriladi.
