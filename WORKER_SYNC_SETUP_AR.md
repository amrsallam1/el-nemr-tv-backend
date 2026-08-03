# ربط El-Nemr TV بمصدر المحتوى

هذا الربط مخصص فقط للمحتوى الذي تملك حق توزيعه أو لديك تصريح باستخدامه.

## الفكرة

- أمر Laravel يستورد بيانات الكتالوج والصور وروابط صفحات المصدر إلى PostgreSQL.
- التطبيق يقرأ المحتوى من API الحالي، لذلك لا يحتاج APK جديدًا.
- روابط الفيديو المباشرة لا تُحفظ لأنها قد تنتهي.
- كل Stream مستورد يظهر للتطبيق كرابط ثابت `/api/play/{stream}`.
- عند التشغيل، Laravel يطلب رابطًا حديثًا من الـWorker ثم يعيد توجيه ExoPlayer إليه.

## متغيرات Railway

أضف القيم التالية إلى خدمة الـbackend وخدمة Cron:

```text
CONTENT_SYNC_ENABLED=true
CONTENT_WORKER_URL=https://akwam-stream-fetcher.meroo3292.workers.dev/
CONTENT_CATALOG_ORIGIN=https://akwam.it
CONTENT_ALLOWED_SOURCE_HOSTS=akwam.it,www.akwam.it
CONTENT_SYNC_DAILY_AT=04:00
CONTENT_SYNC_TIMEZONE=Africa/Cairo
CONTENT_SYNC_PAGES=2
CONTENT_SYNC_LIMIT=50
CONTENT_SYNC_MAX_PAGES=10
CONTENT_SYNC_MAX_ITEMS=200
CONTENT_WORKER_TIMEOUT_SECONDS=20
CONTENT_WORKER_RETRIES=2
CONTENT_SYNC_LOCK_SECONDS=3600
CONTENT_PLAYBACK_CACHE_SECONDS=120
CONTENT_SYNC_TOKEN=ضع-قيمة-عشوائية-طويلة-هنا
```

## النشر

بعد رفع الكود إلى Railway شغّل:

```bash
php artisan migrate --force
php artisan optimize:clear
```

## تجربة بدون حفظ

```bash
php artisan elnemr:sync-worker --type=movies --pages=1 --limit=10 --dry-run
php artisan elnemr:sync-worker --type=series --pages=1 --limit=10 --dry-run
```

## مزامنة فعلية

```bash
php artisan elnemr:sync-worker --type=all --pages=2 --limit=50
```

يمكن أيضًا تشغيل دفعة محدودة عبر endpoint الداخلي المحمي:

```text
POST /api/internal/content-sync
Authorization: Bearer CONTENT_SYNC_TOKEN
```

إعادة تشغيل الأمر لا تنشئ نسخًا مكررة؛ المطابقة تتم بواسطة `source + type + source_id`.

## Railway Cron

ملف `railway.cron.json` يشغّل المزامنة مرة يوميًا. أنشئ خدمة Cron منفصلة من نفس المستودع، واجعل ملف الإعداد الخاص بها `railway.cron.json`.

## فحص التشغيل

1. افتح عنصرًا مستوردًا من API التفاصيل.
2. يجب أن يكون `videos[0].link` بالشكل:

```text
https://YOUR-BACKEND/api/play/STREAM_ID
```

3. طلب الرابط يعيد HTTP 302 إلى أحدث MP4 أو M3U8 من الـWorker.
