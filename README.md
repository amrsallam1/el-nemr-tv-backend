# El Nemr TV Backend

باك إند Laravel لتطبيق El Nemr TV، وفيه لوحة تحكم وAPI واستيراد أفلام تلقائي من TMDB.

## المتطلبات

- PHP 8.3 أو أحدث، مع `mbstring` و`pdo_mysql`.
- MySQL للإنتاج.
- Composer.
- مفتاح TMDB API أو TMDB Access Token.

## الإعداد المحلي

```bash
composer install
php artisan migrate
php artisan serve
```

ما تحطش أي مفتاح أو كلمة سر في Git. انسخ `.env.example` إلى `.env` واضبط القيم محليًا أو من Railway Variables.

## إضافة الأفلام تلقائيًا

تجربة من غير تعديل قاعدة البيانات:

```bash
php artisan movies:sync-popular --limit=50 --dry-run
```

تشغيل فعلي:

```bash
php artisan movies:sync-popular --limit=50
```

الأمر بيعمل الآتي:

1. يحمّل صفحات الأفلام الرائجة من TMDB.
2. يتجاهل أي `type + tmdb_id` موجود، بما فيه المحتوى المحذوف Soft Delete.
3. يكمل صفحات TMDB لحد ما يضيف العدد المطلوب من الأفلام الجديدة.
4. يجرب مصادر المشاهدة بالترتيب ويحفظ أول رابط بيرد.
5. يضيف الفيلم والرابط في transaction واحدة.
6. يحفظ CSV تشخيصي محلي في `storage/app` لو اتضاف محتوى جديد.

قاعدة البيانات عليها unique constraint لـ`type + tmdb_id`، والأمر عليه distributed lock، فإعادة تشغيله ما تعملش نسخ مكررة.

ملف CSV المحلي مش تخزين دائم على Railway إلا لو ركبت Volume أو استخدمت S3. المحتوى نفسه بيتضاف مباشرة إلى قاعدة بيانات التطبيق ومش محتاج رفع CSV للوحة التحكم.

## الجدولة على Railway

راجع [railway/MOVIE_SYNC_SETUP_AR.md](railway/MOVIE_SYNC_SETUP_AR.md). الطريقة الأبسط هي Railway Cron Service باستخدام `railway.cron.json`.

## الاختبارات

```bash
php artisan test
```

الاختبارات بتستخدم SQLite مؤقت وطلبات HTTP وهمية، ومش بتتصل بـTMDB أو مواقع المشاهدة الحقيقية.

## ملاحظات توافقية

الأمر القديم ده ما زال متاح كاختصار علشان زر لوحة التحكم والربط القديم:

```bash
php artisan scraper:run
```

وهو دلوقتي بيشغّل نفس منطق `movies:sync-popular` داخل Laravel؛ ملفات Node القديمة داخل `scraper/` مش داخلة في مسار التشغيل الجديد.
