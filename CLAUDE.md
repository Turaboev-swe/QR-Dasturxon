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
- Migratsiyalar tayyor: `restaurants` (+ `is_verified`/`badge_text`, +
  `kitchen_chat_id`/`waiter_chat_id` — quyidagi Telegram xabarnoma
  bo'limiga qarang), `restaurant_tables` (+ `assigned_waiter_telegram_id`/
  `assigned_waiter_name` — pastdagi "sticky assignment" bo'limiga
  qarang), `categories`, `dishes` (+
  `allergens` json, + flash-chegirma maydonlari `discount_percent`/
  `discount_ends_at`/`discount_portions_total`/
  `discount_portions_remaining`, + ta'm profili `taste_spicy`/
  `taste_sweet`/`taste_salty`), `telegram_users`, `staff` (+
  `telegram_id` unique nullable, `phone` endi faqat ma'lumot uchun
  ixtiyoriy — `pin_code_hash`/`api_token_hash` butunlay olib tashlangan,
  `2026_08_24_000001_switch_staff_auth_to_telegram.php` migratsiyasida),
  `orders` (+ `payment_status`: `unpaid`/`paid`, standart `unpaid` —
  **`payment_preference` (`now`/`later`, mijoz checkout'da tanlagan)
  butunlay olib tashlangan**: mijoz endi to'lov usulini tanlamaydi,
  to'lov doim oxirida ofitsiantga to'g'ridan-to'g'ri qilinadi;
  `payment_status`ni hozircha hech qayerda hech kim `paid`ga
  o'zgartirmaydi — bu alohida, keyingi vazifa), `order_items`, `reviews`
  (+ `order_id` unique **nullable** — sharh buyurtmasiz ham qoldirilishi
  mumkin, quyidagi ReviewController bo'limiga qarang), `waiter_calls`
  (+ `type`: `waiter`/`bill`, + `handled_by_telegram_id`/
  `handled_by_name`), `payments`, `chefs`
  (restoran oshpazi: `title`, `experience_years`,
  `specialty`, `tier_badge`, `photo_path`).
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
- **Xodimlarga Telegram guruh orqali xabarnoma** — `app/Services/TelegramNotifier.php`
  (`sendMessage(chatId, text, replyMarkup=null)` / `editMessageText(chatId,
  messageId, text, replyMarkup=null)`, Laravel `Http` fasadi orqali to'g'ridan-to'g'ri
  Bot API'ga so'rov yuboradi; `AppServiceProvider`da `TelegramAuth` bilan
  bir xil naqshda singleton sifatida bog'langan). Har bir restoranning
  o'z `kitchen_chat_id`/`waiter_chat_id`si bor (config emas — DB
  ustunlari, chunki kelajakda ko'p restoranli bo'lishi mumkin;
  `config('services.telegram.kitchen_chat_id'/`waiter_chat_id`)` faqat
  seederda restoran #1'ni to'ldirish uchun ishlatiladi, ular `.env`da
  — haqiqiy raqamlarni bu faylga yozmang). Ustun `null` bo'lsa,
  xabarnoma jim o'tkazib yuboriladi, buyurtma/chaqiruv yaratilishi
  buzilmaydi; boshqa har qanday Telegram xatosi (tarmoq xatosi — exception,
  YOKI Telegramning o'zi `{"ok":false,...}` bilan qaytargan xato, masalan
  bot guruhga qo'shilmagan bo'lsa "chat not found" — ikkalasi ham
  `TelegramNotifier::call()` ichida markazlashtirilgan holda
  `Log::warning('telegram.api_call_failed', [...])` bilan yoziladi) hech
  qachon mijozga xato sifatida ko'rsatilmaydi. **Eslatma**: bot
  `kitchen_chat_id`/`waiter_chat_id` guruhlariga xabar yubora olishi
  uchun avval o'sha guruhlarga a'zo qilib qo'shilgan bo'lishi shart —
  aks holda har doim "chat not found" bilan jim muvaffaqiyatsiz bo'ladi
  (loglarda ko'rinadi).
  - `OrderController::store` muvaffaqiyatli bo'lgach `kitchen_chat_id`ga
    taomlar+miqdor+jami summa va inline "✅ Tayyor" tugmasi
    (`callback_data: order_ready:{order_id}`) bilan xabar yuboradi.
  - `WaiterCallController::store`da: `type=waiter` — `waiter_chat_id`ga
    "Ofitsiant chaqirildi" + "✅ Bajarildi" tugmasi
    (`call_done:{call_id}`); `type=bill` — **doim** butun guruhga
    broadcast + "✅ Bajarildi" tugmasi bilan ketadi (bu hech qachon
    o'zgarmaydi — hisobni kim bo'lsa ham olib borishi mumkin bo'lishi
    kerak), lekin xabar matni ikki xil: agar
    `restaurant_tables.assigned_waiter_telegram_id` shu stolga
    biriktirilgan bo'lsa — "{assigned_waiter_name}, siz xizmat
    ko'rsatgan Stol {code} hisob so'ramoqda" bilan boshlanadi (xodimni
    chaqiradi, lekin faqat **o'qiydi** — bu chaqiruv `assigned_waiter_*`ni
    o'zgartirmaydi); hech kim biriktirilmagan bo'lsa — oddiy "🧾 Hisob
    so'raldi — Stol {code}" ishlatiladi. Ikkala holatda ham keyin
    "Jami: {summa} so'm" davom etadi — summa `Order::calculateTotal()`
    orqali (hozircha faqat `order_items` yig'indisi) shu stolning
    barcha `payment_status=unpaid` buyurtmalari bo'yicha qo'shiladi —
    **bilingan cheklov**: hech kim hali `payment_status`ni `paid`ga
    o'zgartirmagani uchun, agar bir xil stol avvalgi (allaqachon
    yakunlangan) tashrifidan hali "unpaid" buyurtmalarga ega bo'lsa,
    ular ham hisobga qo'shiladi — bu keyingi "hisobni yopish" vazifasi
    qo'shilgach avtomatik tuzaladi.
  - **Frontend**: `POST /api/waiter-calls` (`type=bill`) muvaffaqiyatli
    bo'lgach `customer.js` avtomatik "Rahmat!" xayrlashuv oynasini
    ochadi — ichida ixtiyoriy 1-5 yulduz+izoh (mavjud sharh formasi
    bilan bir xil, `POST /api/reviews`ga to'g'ridan-to'g'ri, buyurtma
    tanlashsiz yuboriladi) va "O'tkazib yuborish" tugmasi bor. Bu oyna
    hech narsani TO'SMAYDI — yopilgach yoki o'tkazib yuborilgach mijoz
    ilovada istalgan amalni (masalan yana ofitsiant chaqirish) davom
    ettira oladi; alohida "buyurtmani yakunlash" tugmasi yo'q, buni
    "🧾 Hisob" allaqachon anglatadi.
  - **Stolga ofitsiant "yopishib qolishi" (sticky assignment)** — faqat
    `type=waiter` chaqiruvlarga tegishli, `type=bill` hech qachon bunga
    aralashmaydi va doim guruhga to'liq broadcast + tugma bilan ketadi:
    - `restaurant_tables.assigned_waiter_telegram_id`/
      `assigned_waiter_name` — "hozir bu stol bilan kim shug'ullanmoqda"
      degan holat, `null` bo'lsa hali hech kim biriktirilmagan.
      `waiter_calls.handled_by_telegram_id`/`handled_by_name` — har bir
      aniq chaqiruv yozuvida "buni kim yopgan" degan momentli surat
      (snapshot).
    - **Birinchi** `waiter` chaqiruvi (stol hali biriktirilmagan):
      avvalgi mantiq — butun guruhga broadcast + "✅ Bajarildi" tugmasi.
      Kimdir shu tugmani bosganda (`TelegramWebhookController::handleCallDone`)
      — bu Telegram guruhidagi **istalgan a'zo** bo'lishi mumkin, `Staff`
      jadvalidagi ro'yxatdan o'tgan xodim bo'lishi shart emas — bosgan
      kishining `callback_query.from`sidan (`id`, `username` bo'lsa
      `"@username"`, bo'lmasa `first_name`) `assigned_waiter_*` (stolda)
      va `handled_by_*` (chaqiruv yozuvida) shu daqiqada to'ldiriladi.
    - **Keyingi** `waiter` chaqiruvlar (stol allaqachon biriktirilgan):
      guruhga broadcast **qilinmaydi** — o'rniga tugmasiz oddiy eslatma
      xabari: `"🔔 {assigned_waiter_name}, sizni Stol {code}dan yana
      chaqirishmoqda"`. Bu yozuv (`waiter_calls`) darhol `resolved`
      holatida yaratiladi (bajaradigan alohida amal yo'q — bu shunchaki
      eslatma), `handled_by_*` joriy biriktirilgan ofitsiant bilan bir
      xil qilib to'ldiriladi. **Diqqat**: bu holatda odatdagi
      "bir stolga bir vaqtda faqat bitta ochiq chaqiruv" cheklovi
      ishlamaydi (chunki yozuv hech qachon `pending` holatda qolmaydi)
      — mijoz istalgancha marta bosishi mumkin, har safar eslatma
      ketaveradi, ataylab shunday qoldirilgan.
    - **Tozalash**: `OrderController::store` muvaffaqiyatli buyurtma
      yaratgach, o'sha stolning `assigned_waiter_*`sini darhol `null`ga
      qaytaradi — yangi buyurtma yangi tashrif/sessiya deb hisoblanadi,
      shundan keyingi birinchi `waiter` chaqiruvi yana butun guruhga
      broadcast bo'ladi.
  - `POST /api/telegram/webhook` — **`telegram.auth`dan tashqarida**
    (bu Mini App emas, Telegramning o'zidan keladigan server-to-server
    so'rov). `TelegramWebhookController` `callback_query.data`ni
    (`order_ready:{id}` yoki `call_done:{id}`) parse qiladi, tegishli
    statusni yangilaydi (`order_ready` — kassa panelidagi bosqichma-bosqich
    `canTransitionTo()`dan farqli, oshxonaning "tayyor" signali
    `pending`/`confirmed`/`preparing`dan to'g'ridan-to'g'ri `ready`ga
    "sakraydi" — allaqachon `ready`/`served`/`paid`/`cancelled` bo'lsa
    hech narsa qilmaydi; `call_done` esa `DB::transaction()` +
    `lockForUpdate()` ichida `canTransitionTo('resolved')` orqali —
    lock shart, aks holda ikkita deyarli bir vaqtdagi so'rov (masalan
    Telegram webhook qayta urinishi yoki ikki kishi tugmani bir onda
    bosishi) ikkalasi ham hali `pending` holatni o'qib, ikkalasi ham
    stolni turli xodimlarga "biriktirib qo'yishi" mumkin edi — lock
    ikkinchisini birinchisi commit bo'lguncha kutishga majburlaydi, u
    holda `canTransitionTo` allaqachon `false` qaytaradi), so'ng
    `editMessageText` bilan asl xabarni tahrirlaydi — `call_done` uchun
    oxiriga tugmani bosgan kishining ismi bilan "✅ {handled_by_name}
    bordi" qo'shiladi (masalan "✅ @aziz_waiter bordi", username yo'q
    bo'lsa first_name), `order_ready` uchun oddiy "✅ Bajarildi".
    **Muhim**: tugmaning o'zini olib tashlash uchun `reply_markup`
    umuman yubormaslik YETARLI EMAS — Telegram buni "hozirgi
    klaviaturani o'zgartirma" deb tushunadi va eski tugma qolib
    ketadi; buning o'rniga aniq bo'sh qiymat
    (`{"inline_keyboard":[]}`) yuborilishi shart, `markDone()` shuni
    qiladi (`message_id`/`chat.id` alohida saqlanmaydi, ular har safar
    Telegramning o'zi yuborgan `callback_query.message`dan olinadi).
    `order_ready` uchun qo'shimcha ravishda `waiter_chat_id`ga "✅ Stol
    {code} buyurtmasi tayyor — olib boring" xabari yuboriladi. Xavfsizlik:
    `X-Telegram-Bot-Api-Secret-Token` header `config('services.telegram.webhook_secret')`
    (`.env`dagi `TELEGRAM_WEBHOOK_SECRET`) bilan `hash_equals()` orqali
    solishtiriladi — mos kelmasa yoki sozlanmagan bo'lsa 403.
    `callback_query`siz kelgan boshqa update turlari (masalan oddiy xabar)
    xatosiz `{"ok":true}` bilan e'tiborsiz qoldiriladi (Telegram qayta
    urinishining oldini olish uchun).
  - **Webhook'ni botga biriktirish** — bu qadam qo'lda bajarilishi
    SHART, aks holda `order_ready`/`call_done` tugmalari bosilganda
    Telegram callback'ni HECH QAYERGA yubormaydi (chunki o'zi qayerga
    yuborishni bilmaydi) — tugmalar jim ishlamay qoladi, hech qanday
    xatolik ham ko'rinmaydi. Tekshirish uchun:
    ```
    curl "https://api.telegram.org/bot<BOT_TOKEN>/getWebhookInfo"
    ```
    Javobdagi `url` maydoni joriy domenga mos kelishi shart (bo'sh
    bo'lsa yoki eski/boshqa domen ko'rsatsa — shu sabab). Sozlash:
    ```
    curl -X POST "https://api.telegram.org/bot<BOT_TOKEN>/setWebhook" \
      -d "url=https://<domen>/api/telegram/webhook" \
      -d "secret_token=<.env dagi TELEGRAM_WEBHOOK_SECRET qiymati>"
    ```
    **Deploy checklist — bu qadam har safar domen/tunnel manzili
    o'zgarganda TAKRORLANISHI kerak** (vaqtinchalik Cloudflare tunnel
    har qayta ishga tushirilganda yangi manzil beradi): serverni qayta
    ko'targanda ☰ Menu tugmasi (`setChatMenuButton`) yangilansa ham,
    webhook alohida, mustaqil sozlama — u avtomatik yangilanmaydi va
    unutilishi oson (aynan shu sabab bilan bir marta butunlay
    ro'yxatdan o'tkazilmagan holda qolib ketgan edi).
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
  va `PATCH /staff/orders/{id}/status` — `store()` muvaffaqiyatli
  bo'lgach kitchen-chatga xabar yuboradi, yuqoriga qarang),
  `ReviewController` (`GET/POST /reviews` — **buyurtma talab qilmaydi**,
  faqat `start_param` orqali resolved restoranga; `order_id` ixtiyoriy —
  berilsa (mijozning o'zinikimi, `served`/`paid`mi, hali sharhlanmaganmi
  tekshiriladi) sharh o'sha buyurtmaga bog'lanadi va "tasdiqlangan"
  hisoblanadi, berilmasa ham sharh oddiy yaratiladi, bitta buyurtmaga
  bitta sharh cheklovi faqat bog'langan holatga tegishli),
  `WaiterCallController`
  (`GET/POST /waiter-calls` — `type: waiter|bill`, xodim uchun
  `GET /staff/waiter-calls` va `PATCH /staff/waiter-calls/{id}/status` —
  bir stolga bir vaqtda har bir `type` uchun faqat bitta ochiq chaqiruv,
  ya'ni "ofitsiant" va "hisob" chaqiruvlari bir vaqtda parallel ochiq
  bo'lishi mumkin; `store()` waiter-chatga xabar yuboradi, yuqoriga
  qarang), `StaffAuthController` (`GET /staff/me` — joriy `initData`
  qaysi xodimga tegishli ekanini qaytaradi; `login`/`logout` yo'q,
  chunki kirish/chiqish umuman mavjud emas), `AdminDishController` va
  `AdminStatsController` (`/api/staff/admin/*` — faqat `role=admin`:
  mahsulot mavjudligi, flash-chegirma o'rnatish/bekor qilish, bugungi
  statistika), `TelegramWebhookController` (`POST /telegram/webhook` —
  yuqoriga qarang).
- `routes/api.php` — barcha endpointlar yozilgan va ishlaydi (20 route;
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
    backendga bog'liq emas; sharh qoldirish ("Sharh qoldirish" tugmasi
    hamda "🧾 Hisob" muvaffaqiyatli bo'lgach avtomatik ochiladigan
    "Rahmat!" xayrlashuv oynasi ichida) — **buyurtma talab qilinmaydi**,
    forma har doim ochiq; agar mijozning `served`/`paid` va hali
    sharhlanmagan buyurtmasi bo'lsa, ixtiyoriy tanlov (`<select>`)
    ko'rsatiladi (tanlansa sharh o'sha buyurtmaga bog'lanadi va
    "✓ Tasdiqlangan mehmon" belgisi bilan chiqadi), bo'lmasa forma
    picker'siz, faqat yulduz+izoh bilan ko'rinadi; checkout ekranida
    to'lov usuli tanlovi **yo'q** —
    mijoz to'lovni tanlamaydi, to'lov doim oxirida ofitsiantga
    to'g'ridan-to'g'ri qilinadi. `POST /api/session`
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
  sharh, ism+baho+izoh+`verified` — `order_id !== null`, PII yo'q), har
  bir taomda `allergens`,
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
  `phpunit.xml`da test uchun alohida `TELEGRAM_BOT_TOKEN` bor. `.env`da
  yana `TELEGRAM_KITCHEN_CHAT_ID`/`TELEGRAM_WAITER_CHAT_ID` (restoran
  #1 uchun seederga o'qiladigan haqiqiy guruh chat ID'lari) va
  `TELEGRAM_WEBHOOK_SECRET` bor — **haqiqiy qiymatlarini bu faylga
  yozmang**, faqat `.env`da saqlanadi.
- Testlar: `tests/Feature/{SessionInitTest,ReviewTest,WaiterCallTest,
  OrderTest,AdminPanelTest,MenuTest,StaffAuthTest,TelegramWebhookTest}.php`
  — `php artisan test` bilan barchasi o'tadi (73 test, 210 assertion).
  Barcha xodim testlari endi `TelegramAuth::sign()` bilan imzolangan
  `X-Telegram-Init-Data` header ishlatadi (`/api/staff/login` yo'q).
  `ReviewTest` sharh buyurtmasiz ham yaratilishini, `order_id` berilmasa
  ham 201 qaytishini, `start_param`siz hamon 422 qaytarilishini va
  `GET /api/menu`da `recent_reviews.*.verified` faqat buyurtmaga
  bog'langan sharhlarda `true` bo'lishini qamrab oladi. `MenuTest` demo
  rejimni ham qamrab oladi: `start_param`siz `demo: true`
  va faol restoran menyusi qaytishi, faol restoran umuman bo'lmasa
  422ga qaytilishi. `OrderTest`/`MenuTest` chegirma mantig'ini (narx,
  muddati/porsiyasi tugagach yo'qolishi, yetarli bo'lmagan porsiyaga
  buyurtma rad etilishi) qamrab oladi; `OrderTest` shuningdek
  `Order::TRANSITIONS`dagi ruxsatsiz o'tishlarni (pending→served
  sakrash, `paid`dan chiqish), IDOR'ni (mijoz boshqa mijozning
  buyurtmasini `GET /orders/{id}` orqali ko'ra olmasligi) va
  kitchen-chat xabarnomasini (`Http::fake()` bilan — chat id
  sozlanganda yuboriladi, sozlanmaganda `Http::assertNothingSent()`)
  ham qamrab oladi; `WaiterCallTest` xuddi shunday waiter-chat
  xabarnomasini, hisob summasi hisobini va sticky-assignment mexanizmini
  (birinchi chaqiruv broadcast+tugma bilan, biriktirilgan stolga
  keyingi chaqiruv tugmasiz eslatma bilan va yozuv darhol `resolved`
  yaratilishi, yangi buyurtmadan keyin biriktirish tozalanib keyingi
  chaqiruv yana broadcast bo'lishi, `bill` chaqiruvi biriktirishdan
  qat'i nazar doim broadcast+tugma bilan ketishi, bitta xodim ikkita
  turli stolga mustaqil biriktirilishi mumkinligi, `waiter_chat_id`
  sozlanmagan bo'lsa eslatma (reminder) yo'lida ham Telegram'ga
  so'rov yuborilmasligi, 3 xil (toza) stolda ketma-ket chaqiruvlar
  bir-biriga umuman ta'sir qilmasligi, va `bill` chaqiruvi
  biriktirilgan ofitsiantni xabarda tilga olishi (`assigned_waiter_*`ni
  o'zi o'zgartirmagan holda)) qamrab oladi;
  `TelegramWebhookTest` — noto'g'ri/yo'q `secret_token` 403,
  `callback_query`siz update xatosiz o'tkazib yuborilishi,
  `order_ready`/`call_done` statusni to'g'ri yangilashi va xabarni
  tahrirlashi, noma'lum id/allaqachon yakunlangan holat uchun
  idempotent no-op, `call_done` bosgan kishining ma'lumotidan
  `assigned_waiter_*`/`handled_by_*`ni to'g'ri to'ldirishi (username
  bo'lsa `@username`, bo'lmasa `first_name`) va `bill` chaqiruviga
  bunday biriktirish qilmasligi, ikkita turli stolga `call_done`
  kelganda ularning biriktirishi aralashmasligi, va bitta xodimning
  eski chaqiruvdagi `handled_by_name` momentli surati o'sha xodim
  boshqa (keyingi) chaqiruvda boshqa username bilan kelsa ham
  o'zgarmasligi (snapshot, live emas), va tahrirlangan xabar matnida
  bosgan kishining nomi ("✅ @username bordi" / "✅ First_name bordi")
  hamda tugmani chindan olib tashlaydigan bo'sh
  `{"inline_keyboard":[]}` yuborilishi; `AdminPanelTest`
  — faqat admin ruxsati, restoranlararo izolyatsiya, statistika
  to'g'riligi; `StaffAuthTest` — ro'yxatdan o'tgan/o'tmagan/faol
  bo'lmagan `telegram_id`ning `GET /api/staff/me`ga ta'siri.
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
   integratsiyasi (to'lov endi checkout'da tanlanmaydi — ofitsiant
   orqali oxirida amalga oshiriladi, haqiqiy to'lov shlyuzi hali
   ulanmagan).
3. Mini App'ni doimiy (vaqtinchalik tunnel emas) HTTPS manzilga deploy
   qilish va botni shunga qarab qayta sozlash — shu bilan birga
   `POST /api/telegram/webhook`ni haqiqiy `setWebhook` chaqiruvi bilan
   botga biriktirish (curl misoli yuqorida, "Xodimlarga Telegram guruh
   orqali xabarnoma" bo'limida — hozircha tunnel URL'i doimiy emasligi
   sababli ataylab ishga tushirilmagan).
4. Oshpaz/taom rasmlari (`chefs.photo_path`, `dishes.image_path`) va
   `dishes.taste_*` uchun OSHXONA EGASI panelida boshqaruv UI — hozircha
   faqat seeder orqali to'ldiriladi.
5. Ofitsiantning "hisob" oqimini yakunlash: hozir `orders.payment_status`
   hech qayerda `paid`ga o'zgartirilmaydi (standart `unpaid`da qoladi).
   Bu ustun va `WaiterCallController`dagi "hisob" xabarnomasi allaqachon
   tayyor — qolgani: ofitsiant "hisob to'landi" tugmasini bosganda
   tegishli buyurtma(lar)ni `paid`ga o'tkazish kerak.
6. `restaurants.service_charge_percent` ustuni — hali yo'q, lekin
   `Order::calculateTotal()` xuddi shunga tayyor qilib yozilgan
   (hozircha faqat `order_items` yig'indisini qaytaradi). Ustun
   qo'shilgach, shu metod ichida foizni qo'shish kifoya — barcha
   chaqiruvchilar (hisob xabarnomasi, kelajakdagi cheklar) avtomatik
   yangilanadi.
