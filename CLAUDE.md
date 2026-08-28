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
  `specialty`, `tier_badge`, `photo_path`), `daily_stats` (bepul pilot
  sinovda foydalanishni kuzatish uchun — hozircha obuna/to'lov/bloklash
  mantig'i YO'Q, faqat statistika: restoran+kun bo'yicha agregat
  `orders_count`/`orders_total_amount`/`waiter_calls_count`/
  `bill_requests_count`/`unique_users_count`/`reviews_count`, unique
  `(restaurant_id, date)` — `orders`dan har safar hisoblash o'rniga
  tayyor yig'indi saqlanadi, quyidagi `DailyStatsService` bo'limiga
  qarang), `daily_restaurant_visits` (modeli yo'q —
  `daily_stats.unique_users_count` uchun ichki dedup jadvali, quyiga
  qarang).
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
  xatosi hisoblanadi — 404 emas). Obunasi faol bo'lmagan restoran uchun
  **403** qaytaradi (yuqoridagi xavfsizlik qoidalari bo'limiga qarang).
- `app/Services/DailyStatsService.php` — `daily_stats`ni to'ldiradigan
  YAGONA joy (controller'lar to'g'ridan-to'g'ri yozmaydi, thin-controller
  qoidasiga mos): `recordOrder()` (`OrderController::store` muvaffaqiyatli
  bo'lgach), `recordWaiterCall()` (`WaiterCallController::store` — `type`
  ga qarab `waiter_calls_count` yoki `bill_requests_count`ni oshiradi,
  darhol `resolved` yaratilgan eslatma-chaqiruvlar ham hisoblanadi,
  chunki yozuv baribir yaratilgan), `recordReview()`
  (`ReviewController::store`), `recordVisit()` (`SessionController::resolve`
  — "sessiya ochilganda" hodisasi; `unique_users_count`ni faqat o'sha
  kuni o'sha restoranga birinchi marta kelgan `telegram_user` uchun
  oshiradi). Har bir oshirish `insertOrIgnore()` + `column = column + N`
  UPDATE juftligi orqali race-safe (bir vaqtda kelgan ikkita so'rov bir
  xil kunning birinchi yozuvini yaratishga urinsa, ikkalasi ham xatosiz
  o'tadi, keyin ikkalasi ham to'g'ri oshiradi). Noyob foydalanuvchi
  hisoblash uchun Redis/cache emas, alohida `daily_restaurant_visits`
  jadvali (`restaurant_id`+`telegram_user_id`+`date` bo'yicha unique)
  tanlandi — sabab: loyihada Redis umuman yo'q va `CACHE_STORE=database`
  allaqachon MySQL'ning o'zi, ya'ni cache-based yechim shunchaki shu
  MySQL jadvalining kamroq shaffof versiyasi bo'lardi; haqiqiy jadval esa
  har kecha yarim tunda tugaydigan TTL boshqarish shart emasligini,
  qayta ishga tushirishlarga chidamliligini va oddiy SQL bilan tekshirish
  mumkinligini beradi. **Statistika yozish asosiy amalni hech qachon
  buzmaydi**: har bir public metod o'z ichida `try/catch(\Throwable)`
  bilan o'ralgan (`safely()` private helper) — stats yozishda xato bo'lsa
  (masalan DB vaqtincha ishlamasa), xato faqat `Log::warning('daily_stats.
  record_failed', [...])` bilan yoziladi, buyurtma/chaqiruv/sharh/sessiya
  esa baribir muvaffaqiyatli yakunlanadi; chaqiruvchi controller'lar hech
  qanday try/catch yozishi shart emas.
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
  OrderTest,AdminPanelTest,MenuTest,StaffAuthTest,TelegramWebhookTest,
  QrBridgeTest,DailyStatsTest,PlatformAdminAuthTest}.php`
  — `php artisan test` bilan barchasi o'tadi (87 test, 255 assertion).
  `PlatformAdminAuthTest` — Filament `/admin` panelining guard
  izolyatsiyasini qamrab oladi (quyidagi Filament bo'limiga qarang).
  `DailyStatsTest` — 3 ta buyurtma → `orders_count=3`, bitta
  foydalanuvchi bir kunda 5 marta sessiya ochsa `unique_users_count`
  baribir 1 qolishi, boshqa foydalanuvchi kelsa 2ga o'tishi,
  `waiter_calls_count`/`bill_requests_count` alohida-alohida oshishi,
  `reviews_count`, ikki xil restoran statistikasi aralashmasligi, va
  `daily_stats` jadvali qasddan o'chirib qo'yilganda ham (simulyatsiya
  qilingan yozish xatosi) buyurtma/ofitsiant chaqiruvi baribir
  muvaffaqiyatli yaratilishi va xato faqat `Log::warning(
  'daily_stats.record_failed', ...)` bilan yozilishini qamrab oladi.
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
- **QR "bridge" (ko'prik) manzili** — `routes/web.php`dagi
  `GET /t/{startParam}` (regex `r\d+_t.+` bilan cheklangan, mos kelmasa
  404) `config('services.telegram.bot_username')`/
  `config('services.telegram.miniapp_short_name')` (`TELEGRAM_BOT_USERNAME`/
  `TELEGRAM_MINIAPP_SHORT_NAME` env, standart qiymatlar
  `qr_dasturxon_bot`/`qrmenu` — bular sirli emas, allaqachon har bir
  bosilgan QR kodda ochiq ko'rinadi) asosida haqiqiy
  `https://t.me/<bot>/<app>?startapp={startParam}` manziliga 302 bilan
  yo'naltiradi. `startapp` qiymatining o'zi bu yerda TEKSHIRILMAYDI —
  bu shunchaki yo'naltiruvchi ko'prik, haqiqiy tekshiruv keyinroq Mini
  App ichida `TableResolver` orqali bo'ladi. Sabab: bosib chiqarilgan
  QR kodlar endi `t.me` havolasini to'g'ridan-to'g'ri emas, balki shu
  bridge manzilini kodlaydi — ba'zi uchinchi tomon QR skaner ilovalar
  `t.me://` deep link'ni Telegram'ga uzata olmaydi (faqat telefonning
  o'z kamerasi buni ishonchli qiladi), oddiy `https://` havolani esa
  har qanday skaner ochadi. `tests/Feature/QrBridgeTest.php` buni
  qamrab oladi.
- **Stol QR kodlarini generatsiya qilish** (bosib chiqarish uchun) —
  ikkita mustaqil vosita, ikkalasi ham yuqoridagi bridge manzilini
  kodlaydi (`https://{APP_URL}/t/r{restaurant_id}_t{table_code}`):
  - `qr-generator.html` (loyiha ildizida) — brauzerda ochiladigan,
    o'z-o'zini qamrab oluvchi (vendor qilingan MIT `qrcode-generator`
    JS kutubxonasi bilan) vosita: sayt manzili (APP_URL)/bot
    username/app qisqartmasi/restoran ID/stollar sonini kiritib,
    barcha stollar uchun bir zumda QR kod to'plami yasaydi va
    "🖨️ Chop etish / PDF saqlash" tugmasi (`@media print`, A4) orqali
    bosib chiqaradi.
  - `generate_qrs.py` (loyiha ildizida, `pip install "qrcode[pil]"`
    talab qiladi) — buyruq qatoridan PNG (yuqori piksel zichlikda) +
    SVG (vektor) juftligini `qr_codes/`ga yozadi (`ThreadPoolExecutor`
    bilan parallel).
  - Ikkalasi ham QR kod ostiga "Faqat telefon kamerasi orqali
    skanerlang — boshqa skaner ilovalar ishlamasligi mumkin"
    ogohlantirishini qo'shadi. Buni tahrirlash/tarjima qilish kerak
    bo'lsa: `qr-generator.html`da `.qr-camera-warn` elementi (matni
    JS'dagi `generate()` funksiyasi ichida), `generate_qrs.py`da
    `WARNING_TEXT` konstantasi (PNG rasmning pastki qismiga PIL bilan
    chiziladi — QR kodning o'zini yopmaydi; SVG chiqishi dizayn/
    bosmaxona uchun matnsiz sof vektor QR bo'lib qoladi).
  - **Diqqat**: loyiha ildizidagi `qr_codes/` papkasi hozircha ESKI
    (bridge'dan oldingi — `t.me` havolasini to'g'ridan-to'g'ri
    kodlagan, ogohlantirish matnisiz) fayllarni saqlaydi — real bosib
    chiqarishdan oldin `python3 generate_qrs.py`ni qayta ishga
    tushirib, ularni yangilash SHART.
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

## Filament SaaS operator paneli (`/admin`)
Platforma egasi (siz) uchun — **oddiy veb sayt, Telegram Mini App EMAS**,
brauzerda `https://qr-dasturxon.uz/admin` (yoki lokal `http://127.0.0.1:8091/admin`)
orqali ochiladi, Filament'ning standart email+parol login'i bilan. Uchinchi,
**butunlay mustaqil** kirish yo'li — mijoz (`telegram.auth`/`TelegramUser`)
va xodim (`staff.auth`/`Staff`) bilan hech qanday umumiy jihati yo'q.

- **PHP kengaytmalar talabi**: Filament v5 `ext-intl` va (uning
  `openspout` bog'liqligi orqali, export funksiyasi uchun) `ext-zip`ni
  talab qiladi. Bu loyihaning mahalliy dasturchi muhitida standart PHP
  o'rnatilishida ular yo'q edi — production serverga deploy qilishdan
  oldin **`sudo apt install php8.5-intl php8.5-zip`** (yoki mos PHP
  versiyasi) ishga tushirilishi SHART, aks holda `composer install`/
  ilovaning o'zi butunlay ishlamaydi. Mahalliy muhitda vaqtinchalik yechim
  sifatida bu ikkalasi `~/.local/php-extra/`ga qo'lda joylashtirilgan va
  `PHP_INI_SCAN_DIR` orqali yoqilgan (`.ini` fayllar tizim `conf.d`siga
  yozilmagan, chunki bunga root kerak) — bu **faqat vaqtinchalik**,
  haqiqiy o'rnatish keyinroq qo'lda qilinishi kerak.
- **Auth**: `App\Models\PlatformAdmin` (`platform_admins` jadvali —
  `name`/`email`/`password`, oddiy Laravel `Authenticatable`, `Filament
  Models\Contracts\FilamentUser`/`HasName` implement qiladi). Alohida
  guard/provider — `config/auth.php`dagi `platform_admin` guard +
  `platform_admins` provider, `User`/`TelegramUser`/`Staff`dan butunlay
  mustaqil. `App\Providers\Filament\AdminPanelProvider` panelni
  `->authGuard('platform_admin')` bilan shu guard'ga bog'laydi.
  `php artisan platform-admin:create` — birinchi (yoki keyingi) adminni
  interaktiv yaratadi (`app/Console/Commands/CreatePlatformAdmin.php`):
  ism/email/parol so'raladi (parol `Command::secret()` bilan
  yashiriladi, ekranga chiqmaydi, hech qayerda log qilinmaydi), email
  unique va parol kamida 8 belgi tekshiriladi, parol/tasdiqlash mos
  kelishi tekshiriladi. `tests/Feature/PlatformAdminAuthTest.php` guard
  izolyatsiyasini qamrab oladi (mehmon → login sahifasiga redirect,
  platform admin → 200, boshqa (`web`) guard'dagi sessiya → hamon
  redirect).
- **Dashboard** (`GET /admin`) — barcha widget `daily_stats`dan
  o'qiydi, hech qachon `orders`dan qayta hisoblamaydi:
  - `TodayStatsWidget`/`WeeklyStatsWidget`/`MonthlyStatsWidget`
    (`app/Filament/Widgets/`, umumiy mantiq
    `App\Filament\Support\BaseWindowStatsWidget`da) — bugun/oxirgi 7
    kun/oxirgi 30 kun kesimida buyurtmalar soni, jami summa, noyob
    foydalanuvchilar, ofitsiant chaqiruvlari, hisob so'rovlari — har biri
    shunchaki `SUM(daily_stats.ustun)` shu oyna bo'yicha. **Bilingan
    cheklov**: ko'p kunlik oynadagi "noyob foydalanuvchilar" — har
    kunning allaqachon-noyob sonini qo'shish, ya'ni haqiqiy davr-bo'yi
    noyob son EMAS (bir mijoz 2 xil kunda kelsa 2 marta sanaladi) — buni
    to'g'irlash uchun `daily_restaurant_visits`dan to'g'ridan-to'g'ri
    distinct hisoblash kerak bo'lardi, ataylab qilinmadi (barcha
    ko'rsatkichlarni bir xil oddiy SUM shakliga mos qilish uchun).
  - `DailyActivityChartWidget` — barcha restoranlar yig'indisida, oxirgi
    30 kunlik chiziqli grafik (buyurtmalar + noyob foydalanuvchilar);
    faoliyat bo'lmagan kunlar `daily_stats`da umuman yo'q, shuning uchun
    kun oralig'i qo'lda (`for` sikli) to'ldiriladi — bo'sh kunlar
    grafikdan tushib qolmasligi uchun.
  - `RestaurantActivityWidget` — har bir restoran uchun bugun/7 kun/30
    kunlik buyurtmalar soni + **oxirgi faollik sanasi**
    (`MAX(daily_stats.date)`) rangli belgi bilan (yashil = bugun/kecha,
    sariq = 7 kun ichida, qizil = undan eski yoki umuman bo'lmagan) —
    pilot davrida "qaysi restoran tashlab qo'ygan"ni bir qarashda
    ko'rsatish uchun ataylab shunday qilingan.
- **Resurslar** (`app/Filament/Resources/`, hammasi shu joyda
  generatsiya qilingan standart Filament v5 tuzilishi bilan —
  `{Resource}/{Schemas/*Form,Tables/*Table,Pages/*}`):
  - `RestaurantResource` — nomi (6 tilda tab, pastga qarang), faol/
    tasdiqlangan holat, belgi matni, oshxona/ofitsiant chat ID, geo-
    ma'lumot (faqat ma'lumot uchun, hech qanday gate emas — yuqoridagi
    xavfsizlik qoidalariga qarang). `TablesRelationManager` orqali shu
    restoranning stollarini (kod/nomi/faol) to'g'ridan-to'g'ri shu
    sahifada qo'shish/tahrirlash/o'chirish mumkin (assotsiatsiya/
    dissotsiatsiya YO'Q — stol mustaqil obyekt emas, faqat hasMany).
  - `CategoryResource`/`DishResource` — "Menyu" navigatsiya guruhida.
    Restoran tanlovi tarjima orqali ko'rsatiladi
    (`getOptionLabelFromRecordUsing`). `DishResource`da kategoriya
    tanlovi restoran tanlanganidan keyin FAQAT o'sha restoranning
    kategoriyalarini ko'rsatadi (`->live()` + `modifyQueryUsing`).
    `image_path` ataylab oddiy matn maydoni (fayl yuklash UI hali yo'q —
    yuqoridagi "Keyingi vazifalar"ga qarang), `discount_*`/`taste_*`
    maydonlari BU yerda YO'Q (ular OSHXONA EGASI panelining ishlash
    vaqti konsepsiyasi, operator paneliga tegishli emas).
  - `OrderResource` — **faqat o'qish uchun**: `canCreate()`/`canEdit()`/
    `canDelete()`/`canDeleteAny()` hammasi `false`, `getPages()`da
    faqat `index`/`view` bor (`create`/`edit` route'lari umuman mavjud
    emas) — buyurtma tarixiy moliyaviy yozuv, hech qachon
    tahrirlanmaydi/o'chirilmaydi. Ko'rish sahifasida taomlar ro'yxati
    (`RepeatableEntry`) bilan.
  - `StaffResource` — **haqiqiy joriy auth mexanizmiga mos**: Telegram
    ID maydoni (aniq helper matn bilan: "login/parol/PIN YO'Q"), rol
    (ofitsiant/kassir/egasi), telefon faqat ma'lumot uchun. PIN/parol
    maydoni YO'Q, chunki bunday tizim umuman mavjud emas
    (`2026_08_24_000001_switch_staff_auth_to_telegram.php`da olib
    tashlangan).
- Barcha tarjima maydonlari (`name_translations` va h.k.) uchun umumiy
  `App\Filament\Support\TranslatableTabs::input()/textarea()` — har bir
  tilga (uz/en/ru/ko/fr/zh) alohida tab + maydon, `{ustun}.{til_kodi}`
  dot-notation orqali JSON ustunga bog'lanadi. Faqat `uz` majburiy.
  Bo'sh qoldirilgan til `null` sifatida saqlanadi (bo'sh satr emas) —
  `dehydrateStateUsing` bilan ataylab shunday, aks holda
  `HasTranslations::translate()`ning `?? $fallback` zanjiri bo'sh satrni
  "mavjud qiymat" deb hisoblab, `uz`ga qaytmagan bo'lardi.

### Filament paneli — brend uslubi
Hammasi `app/Providers/Filament/AdminPanelProvider.php`da, faqat
Filament'ning o'z sozlash metodlari orqali (yangi Blade/CSS komponent
yozilmagan) — manba: yuqoridagi "Dizayn manbasi" bo'limidagi prototip
faylining `:root` o'zgaruvchilari.

- **Ranglar** — `->colors([...])`: `warning` uchun
  `Color::hex('#C79A3E')` (--gold) to'g'ridan-to'g'ri ishlatildi, chunki
  uning haqiqiy yorqinligi (oklch lightness ≈0.71) Filament'ning
  avtomatik hosil qiladigan shkalasiga allaqachon yaqin. `primary` uchun
  esa **qo'lda yig'ilgan to'liq 11-bosqichli shkala** kerak bo'ldi:
  `Color::hex('#7A2331')` faqat RANGNING TUSINI (hue) qayta ishlatib,
  qolgan hamma narsani (yorqinlik/to'yinganlik) Filament'ning o'zining
  qattiq belgilangan shkalasidan oladi — bu shkala 600-bosqichni
  (tugmalar/faol holatlar ishlatadigan bosqich) yorqinlik ≈0.60'da
  kutadi, lekin haqiqiy `--maroon` juda qorong'i (yorqinlik ≈0.40) —
  natijada tugma to'q maroon o'rniga och pushti/korall rangda chiqdi.
  Tuzatish: `600`/`800`/`900` bosqichlarga aynan `--maroon`/
  `--maroon-deep`/`--maroon-dark` hexlarini, `700`/`950`ga ular orasidan
  interpolyatsiya qilingan qiymatlarni, `50`-`500`ga esa och, kamroq
  muhim bosqichlar uchun qo'lda tanlangan yumshoq tonlarni qo'ydim.
- **Shriftlar** — `->font('Manrope', provider: GoogleFontProvider::class)`
  — Filament ishlatadigan asosiy (sans) shrift endi Manrope, mijoz/xodim
  sahifalari bilan bir xil. `->serifFont('Cormorant Garamond', ...)` ham
  ro'yxatdan o'tkazilgan (shrift fayli yuklanadi, CSS o'zgaruvchisi
  mavjud) — **lekin bilib qo'ying**: Filament'ning o'z tayyor
  view'lari (sahifa sarlavhalari, jadval sarlavhalari va h.k.) HECH
  QAYERDA `font-serif` Tailwind klassini ishlatmaydi, faqat
  `font-sans`ni. Shuning uchun bu shriftni ro'yxatdan o'tkazishning
  o'zi mavjud Filament matnlarini Cormorant Garamond'ga
  o'zgartirmaydi — buning uchun Filament'ning o'z Blade view'larini
  qayta yozish kerak bo'lardi, bu esa "yangi komponent yozma" cheklovini
  buzardi. Amalda Cormorant Garamond hozircha faqat quyidagi brendmark
  ichida (o'zim qo'lda shu shriftni belgilagan joyda) ko'rinadi.
- **Brendmark** — `->brandName('QR Dasturxon')` (faqat brauzer
  tab sarlavhasi uchun — "Sahifa - QR Dasturxon"), `->brandLogo()`ga
  prototipning oltin yulduzcha SVG'si (`.brandmark .ornament`, aynan
  bir xil viewBox/path) + "QR **Dasturxon**" matni (Cormorant Garamond,
  "Dasturxon" so'zi oltin rangda, "QR" so'zi `currentColor` — Filament
  buni light/dark rejimda avtomatik oq/qora qilib beradi) birlashtirilgan
  bitta `HtmlString` sifatida beriladi (Filament yon panelidagi logo joyi
  logotip YOKI matn ko'rsatadi, ikkalasini emas — shuning uchun
  ikkalasini bitta HTML'ga birlashtirish kerak bo'ldi). Xuddi shu star
  SVG base64 sifatida `->favicon()`ga ham beriladi — alohida fayl shart
  emas. **Dark mode** — Filament'da standart yoqilgan
  (`$hasDarkMode = true` default), `->darkMode(true, isForced: false)`
  chaqiruvi shuni faqat aniq hujjatlashtiradi, hech narsani
  o'zgartirmaydi.
- **O'zbekcha interfeys** — ajablanarli tarzda, **Filament'ning o'zi
  allaqachon yaxshi sifatli hamjamiyat tarjimasini o'z ichiga oladi**
  (`uz` ko'plab boshqa tillar qatorida standart bilan keladi). Shunchaki
  `php artisan vendor:publish --tag={filament-panels,filament-tables,
  filament-actions,filament-forms,filament-infolists,
  filament-notifications,filament-schemas,filament-widgets,
  filament}-translations` orqali `lang/vendor/`ga chiqarildi (faqat
  `en`/`uz` papkalari qoldirildi, boshqa ~60 til o'chirildi — kerak
  emas), va `.env`da `APP_LOCALE=uz` (`APP_FALLBACK_LOCALE=en` —
  `uz` faylida yo'q kalit avtomatik inglizchaga qaytadi, hech qachon
  xom kalit nomi ko'rinmaydi) qo'yildi. **Bu global o'zgarish xavfsiz**:
  loyihaning boshqa hech bir joyi (mijoz/xodim API'lari) Laravel'ning
  `__()`/`trans()` tizimidan umuman foydalanmaydi — hammasi
  to'g'ridan-to'g'ri PHP satrlari (tekshirib chiqilgan), shuning uchun
  `app.locale`ni o'zgartirish FAQAT Filament panelining o'ziga ta'sir
  qiladi. Ba'zi chuqurroq/kamdan-kam kalitlar (masalan
  `filament-tables::table.result_count`) `uz` faylida yo'q — bular ham
  xavfsiz inglizchaga qaytadi (`hammasini emas — faqat asosiy
  matnlar" talabiga mos, ataylab to'liq audit qilinmadi).

## Kod konvensiyalari
- Controller'lar yupqa (thin) bo'lsin — biznes mantiq Service/Model'da.
- Har bir API javobi JSON, xatolarda mos HTTP status kod.
- Yangi funksiya yozishdan oldin mavjud model/migratsiyani tekshir —
  aksariyat jadval allaqachon mavjud.
- Migratsiya birinchi marta ishga tushgach, uni tahrirlamang — yangi
  migratsiya qo'shing (masalan `reviews.order_id` unique shunday qo'shilgan).

## Keyingi vazifalar (ustuvorlik tartibida)
1. Laravel Reverb (hozir KASSA/OSHXONA EGASI ~6 soniyalik polling bilan
   ishlaydi — buni real-time push'ga almashtirish) va Click/Payme
   integratsiyasi (to'lov endi checkout'da tanlanmaydi — ofitsiant
   orqali oxirida amalga oshiriladi, haqiqiy to'lov shlyuzi hali
   ulanmagan).
2. Mini App'ni doimiy (vaqtinchalik tunnel emas) HTTPS manzilga deploy
   qilish va botni shunga qarab qayta sozlash — shu bilan birga
   `POST /api/telegram/webhook`ni haqiqiy `setWebhook` chaqiruvi bilan
   botga biriktirish (curl misoli yuqorida, "Xodimlarga Telegram guruh
   orqali xabarnoma" bo'limida — hozircha tunnel URL'i doimiy emasligi
   sababli ataylab ishga tushirilmagan). **Endi shu bilan birga**
   `/admin` (Filament SaaS operator paneli — pastga qarang) production
   domenida ham ishlashini tekshirish, va production serverga
   `php8.5-intl`/`php8.5-zip` PHP kengaytmalarini o'rnatish kerak
   bo'ladi (quyidagi Filament bo'limidagi eslatmaga qarang).
3. Oshpaz/taom rasmlari (`chefs.photo_path`, `dishes.image_path`) va
   `dishes.taste_*` uchun OSHXONA EGASI panelida (yoki endi Filament
   `DishResource`da) boshqaruv UI — hozircha faqat seeder orqali
   to'ldiriladi, `DishResource`da `image_path` ataylab oddiy matn
   maydoni (fayl yuklash emas).
4. Ofitsiantning "hisob" oqimini yakunlash: hozir `orders.payment_status`
   hech qayerda `paid`ga o'zgartirilmaydi (standart `unpaid`da qoladi).
   Bu ustun va `WaiterCallController`dagi "hisob" xabarnomasi allaqachon
   tayyor — qolgani: ofitsiant "hisob to'landi" tugmasini bosganda
   tegishli buyurtma(lar)ni `paid`ga o'tkazish kerak.
5. `restaurants.service_charge_percent` ustuni — hali yo'q, lekin
   `Order::calculateTotal()` xuddi shunga tayyor qilib yozilgan
   (hozircha faqat `order_items` yig'indisini qaytaradi). Ustun
   qo'shilgach, shu metod ichida foizni qo'shish kifoya — barcha
   chaqiruvchilar (hisob xabarnomasi, kelajakdagi cheklar) avtomatik
   yangilanadi.
6. Filament panelining o'z UI matnlari (tugmalar: "New restoran",
   "Save changes", "Showing X to Y of Z results" va h.k.) hali
   inglizcha — faqat MEN yozgan maydon/label/xabarlar o'zbekcha.
   To'liq o'zbekcha qilish uchun Filament'ning o'z tarjima fayllarini
   qayta e'lon qilish (til paketi) kerak bo'ladi — alohida, kattaroq ish.
