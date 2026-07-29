# ملخص تكامل El-Nemr TV

## 1) مكونات المشروع

- تطبيق Android مبني على نسخة EasyPlex.
- الباك إند مبني باستخدام Laravel 13.
- قاعدة البيانات الحالية PostgreSQL على Railway.
- لوحة التحكم Web مبنية داخل Laravel.
- اسم التطبيق: El-Nemr TV.
- المطور الظاهر في اللوحة: Dev by Dr Amr.

## 2) الروابط

الرابط الأساسي للباك إند:

```text
https://el-nemr-tv-backend-production.up.railway.app
```

لوحة التحكم:

```text
https://el-nemr-tv-backend-production.up.railway.app/admin
```

صفحة استيراد CSV:

```text
https://el-nemr-tv-backend-production.up.railway.app/admin/media-import
```

رابط فحص حالة الخدمة:

```text
GET /api/health
```

## 3) أفضل طريقة لعمل السكربت

الطريقة الأسهل والأكثر أمانًا أن السكربت يجلب البيانات من الـAPI ، ثم ينتج ملف CSV. يتم رفع الملف من لوحة التحكم.

لا يتم الاتصال بقاعدة البيانات مباشرة، ولا يتم تخزين كلمات المرور أو مفاتيح Railway داخل السكربت.

## 4) صيغة ملف CSV

الأعمدة المقبولة:

```csv
title,type,year,tmdb_id,overview,poster_url,backdrop_url,stream_url,quality,language,featured,published
```

شرح الأعمدة:

- `title`: اسم الفيلم أو المسلسل.
- `type`: واحدة من `movie`, `series`, `anime`, `live`.
- `year`: سنة الإصدار، ويمكن تركها فارغة.
- `tmdb_id`: رقم TMDB إن كان متاحًا.
- `overview`: الوصف، اختياري.
- `poster_url`: رابط البوستر، اختياري.
- `backdrop_url`: رابط الخلفية، اختياري.
- `stream_url`: رابط التشغيل المباشر، اختياري في مرحلة إدخال البيانات.
- `quality`: مثل `1080p` أو `720p`.
- `language`: مثل `ar` أو `en`.
- `embed`: رقم `1` إذا كان الرابط صفحة Embed مصرحًا باستخدامها، أو `0` إذا كان رابط فيديو مباشر.
- `featured`: رقم `1` للمحتوى المميز أو `0` لغير ذلك.
- `published`: رقم `1` للنشر أو `0` للمسودة.

مهم: `stream_url` يجب أن يكون رابط فيديو مباشرًا وقابلًا للتشغيل مثل `.mp4` أو `.m3u8` أو `.mpd`، وليس رابط صفحة مشاهدة.

مثال:

```csv
title,type,year,tmdb_id,overview,poster_url,backdrop_url,stream_url,quality,language,featured,published
Example Movie,movie,2025,,Description,,,,1080p,en,1,1
```

## 5) جلب بيانات TMDB

الاستيراد يدعم إثراء بيانات الفيلم من TMDB عند توفر `TMDB_API_KEY` في Railway. يمكن للسكربت إرسال `tmdb_id`، أو إرسال الاسم فقط ليتم البحث عنه.

بيانات TMDB التي يمكن جلبها:

- الوصف.
- البوستر.
- الخلفية.
- تاريخ الإصدار.
- التقييم.
- TMDB ID.

في الملفات الكبيرة، يفضل تقسيم CSV إلى دفعات صغيرة (10 إلى 20 صفًا) حتى لا يتجاوز الطلب حد التنفيذ في Railway.

## 6) API العام الذي يستخدمه تطبيق Android

```text
GET /api/settings/{code}
GET /api/media/latestcontent/{code}
GET /api/media/featuredcontent/{code}
GET /api/media/recommendedcontent/{code}
GET /api/media/trendingcontent/{code}
GET /api/media/thisweekcontent/{code}
GET /api/media/choosedcontent/{code}
GET /api/movies/latest/{code}
GET /api/series/recents/{code}
GET /api/animes/recents/{code}
GET /api/livetv/latest/{code}
GET /api/media/show/{media}/{code}
GET /api/media/detail/{media}/{code}
```

## 7) تسجيل الدخول للـAPI

```text
POST /api/login
```

Body:

```json
{
  "username": "admin-email@example.com",
  "password": "ADMIN_PASSWORD"
}
```

الرد يحتوي على:

```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "expires_in": 2592000
}
```

يتم استخدام التوكن هكذا:

```text
Authorization: Bearer ACCESS_TOKEN
```

## 8) API الإداري المباشر

كل المسارات التالية تحتاج توكن مستخدم مدير:

```text
POST   /api/admin/media
GET    /api/admin/media
PUT    /api/admin/media/{media}
DELETE /api/admin/media/{media}
POST   /api/admin/media/{media}/streams
PUT    /api/admin/streams/{stream}
DELETE /api/admin/streams/{stream}
```

مثال إضافة فيلم:

```json
{
  "type": "movie",
  "title": "Example Movie",
  "slug": "example-movie",
  "tmdb_id": "12345",
  "overview": "Movie description",
  "poster_path": "https://example.com/poster.jpg",
  "backdrop_path": "https://example.com/backdrop.jpg",
  "release_date": "2025-01-01",
  "vote_average": 8.2,
  "is_featured": true,
  "is_published": true
}
```

مثال إضافة رابط تشغيل لفيلم موجود:

```text
POST /api/admin/media/{media}/streams
```

```json
{
  "name": "Server 1",
  "url": "https://authorized-domain.example/video.m3u8",
  "quality": "1080p",
  "language": "ar",
  "embed": false,
  "is_active": true
}
```

## 9) منطق منع التكرار

- إذا وُجد `tmdb_id` يتم استخدامه للمطابقة والتحديث.
- إذا لم يوجد، يتم استخدام Slug مبني من النوع والعنوان.
- يجب أن يحتفظ السكربت بسجل للعناصر التي أرسلها حتى لا يعيد إنشاء نفس المحتوى.
- يفضل تنفيذ العملية على دفعات وإعادة المحاولة عند أخطاء الشبكة.

## 10) بيانات لا يتم إرسالها أو مشاركتها

لا يتم وضع أي من التالي داخل السكربت أو GitHub:

- كلمة مرور لوحة التحكم.
- `TMDB_API_KEY`.
- Railway variables.
- GitHub tokens.
- مفاتيح أو بيانات قاعدة البيانات.

هذه القيم يتم وضعها كـ Environment Variables في Railway أو على جهاز التشغيل فقط.

## 11) المطلوب من السكربت

1. قراءة API 
2. تحويل كل عنصر إلى صيغة CSV أعلاه أو JSON مطابق للـAPI الإداري.
3. إزالة العناصر المكررة.
4. تحويل التواريخ والتقييمات للنوع الصحيح.
5. ترك `stream_url` فارغًا إذا لم يوجد رابط تشغيل مباشر.
6. تسجيل الأخطاء في ملف Log.
7. دعم التشغيل اليدوي أو Cron يومي.

الاختيار المفضل للنسخة الأولى: السكربت ينتج CSV، ثم يتم رفعه من لوحة التحكم. بعد نجاح الاختبار يمكن تحويله إلى مزامنة API تلقائية.
