# تشغيل إضافة الأفلام تلقائيًا على Railway

## الإعدادات المطلوبة

قبل أول deploy خد Backup من قاعدة بيانات الإنتاج. ولو باسورد الأدمن القديم اللي كان ظاهر في `.env.example` مستخدم فعلًا، غيّره قبل النشر لأن حذفه من النسخة الحالية لا يمسحه من Git history.

ضيف المتغيرات دي في خدمة الويب وخدمة الـCron:

```env
TMDB_API_KEY=your-key
MOVIE_SYNC_MAX_MOVIES=50
MOVIE_SYNC_MAX_PAGES=25
MOVIE_SYNC_REQUIRE_STREAM=true
CACHE_STORE=database
```

ممنوع تحط المفتاح أو كلمة سر الأدمن داخل Git.

## الطريقة الأبسط: Railway Cron

1. اعمل خدمة جديدة في نفس Railway Project ومن نفس Git repository.
2. خلي Config File Path للخدمة هو `/railway.cron.json`.
3. اربط الخدمة بنفس MySQL ونفس متغيرات البيئة الخاصة بخدمة الويب.
4. اختار Cron Schedule يومي مناسب. Railway Cron بيستخدم UTC.
5. اعمل أول تشغيل يدوي وراجع الـlogs وقائمة الأفلام في لوحة التحكم.

الأمر اللي بيتنفذ هو:

```bash
php artisan movies:sync-popular --no-interaction
```

خدمة الـCron لازم تنفذ الأمر وتنتهي؛ ما تستخدمش `railway/run-cron.sh` مع Railway Cron المباشر.

## تجربة آمنة قبل التفعيل

```bash
php artisan movies:sync-popular --limit=50 --dry-run
```

التجربة دي تفحص TMDB وروابط المشاهدة لكن ما بتضيفش أي بيانات.

## تشغيل Laravel Scheduler بدل Railway Cron

لو هتشغّل `railway/run-cron.sh` كخدمة دائمة، ضيف:

```env
MOVIE_SYNC_ENABLED=true
MOVIE_SYNC_DAILY_AT=03:00
MOVIE_SYNC_TIMEZONE=Africa/Cairo
```

الـscheduler فيه قفل يمنع تداخل تشغيلين، والأمر نفسه فيه قفل إضافي موزع عن طريق الـcache.
