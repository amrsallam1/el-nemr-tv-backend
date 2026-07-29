const { chromium } = require('playwright');
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const config = require('./config');

// إعدادات السكربت
const MAX_MOVIES = 5;
const DELAY_BETWEEN_REQUESTS = 3000;

// الدوال المساعدة
function log(message, type = 'info') {
    const timestamp = new Date().toISOString();
    const prefix = {
        info: 'ℹ️',
        success: '✅',
        error: '❌',
        warning: '⚠️'
    }[type] || 'ℹ️';
    console.log(`${timestamp} ${prefix} ${message}`);
}

// دالة لجلب روابط الأفلام من أكوام
async function getMovieUrlsFromAkwams(page) {
    log('جاري البحث عن روابط الأفلام في أكوام...', 'info');
    
    try {
        await page.waitForSelector('a[href*="/watch/"], a[href*="/movie/"]', { timeout: 10000 });
    } catch (e) {
        log('لم نجد روابط محددة، سنحاول البحث بشكل أوسع...', 'warning');
    }
    
    const movieLinks = await page.evaluate(() => {
        const allLinks = document.querySelectorAll('a');
        const urls = [];
        const baseUrl = 'https://akwams.org';
        
        allLinks.forEach(link => {
            let href = link.getAttribute('href');
            if (!href) return;
            
            if (href.startsWith('/')) {
                href = baseUrl + href;
            }
            
            // استبعاد الروابط غير المرغوب فيها
            const excludePatterns = [
                '/category/', '/movies/page/', '/search', 
                '/login', '/register', '/genre', '/tag',
                '/user/', '/admin', '/wp-', '/feed'
            ];
            
            if (excludePatterns.some(pattern => href.includes(pattern))) {
                return;
            }
            
            // قبول فقط الروابط التي تشبه صفحة فيلم
            const isMovieLink = href.includes('/watch/') || 
                               href.includes('/movie/') ||
                               (href.startsWith(baseUrl + '/') && 
                                !href.includes('/category/') && 
                                !href.includes('/page/') &&
                                !href.includes('/movies') &&
                                href.split('/').length === 4);
            
            if (isMovieLink && !urls.includes(href)) {
                urls.push(href);
            }
        });
        
        return urls;
    });
    
    const limitedUrls = movieLinks.slice(0, MAX_MOVIES);
    log(`تم العثور على ${limitedUrls.length} رابط فيلم من أكوام`, 'success');
    return limitedUrls;
}

// دالة لجلب روابط الأفلام من سيما لايت
async function getMovieUrlsFromCimalight(page) {
    log('جاري البحث عن روابط الأفلام في سيما لايت...', 'info');
    
    try {
        await page.waitForSelector('a[href*="/watch/"], a[href*="/movie/"]', { timeout: 10000 });
    } catch (e) {
        log('لم نجد روابط محددة، بننتظر تحميل المحتوى الديناميكي...', 'warning');
        await page.waitForTimeout(5000);
    }
    
    await page.waitForTimeout(3000);
    
    const movieLinks = await page.evaluate(() => {
        const allLinks = document.querySelectorAll('a');
        const urls = [];
        const baseUrl = 'https://r.cimalight.co';
        
        allLinks.forEach(link => {
            let href = link.getAttribute('href');
            if (!href) return;
            
            if (href.startsWith('/')) {
                href = baseUrl + href;
            }
            
            const excludePatterns = [
                '/categories/', '/search', '/login', '/register', 
                '/page/', '/genre', '/main24', '/category'
            ];
            
            if (excludePatterns.some(pattern => href.includes(pattern))) {
                return;
            }
            
            const isMovieLink = href.includes('/watch/') || 
                               href.includes('/movie/') ||
                               (href.startsWith(baseUrl + '/') && 
                                !href.includes('/main24') &&
                                href.split('/').length === 4);
            
            if (isMovieLink && !urls.includes(href)) {
                urls.push(href);
            }
        });
        
        return urls;
    });
    
    const limitedUrls = movieLinks.slice(0, MAX_MOVIES);
    log(`تم العثور على ${limitedUrls.length} رابط فيلم من سيما لايت`, 'success');
    return limitedUrls;
}

// دالة لاستخراج بيانات فيلم من صفحته
async function scrapeMovieFromUrl(pageUrl, retries = 2) {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();

    try {
        log(`جاري فتح: ${pageUrl}`, 'info');
        await page.goto(pageUrl, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(3000);

        const html = await page.content();
        const $ = require('cheerio').load(html);

        // استخراج البيانات
        const movie = {
            title: $('h1').first().text().trim() || 
                   $('.title').first().text().trim() || 
                   $('meta[property="og:title"]').attr('content') || 
                   'فيلم بدون عنوان',
                   
            overview: $('.description, .story, .plot, .summary').text().trim() || 
                      $('meta[property="og:description"]').attr('content') || 
                      'لا يوجد وصف',
                      
            poster: $('.poster img').attr('src') || 
                    $('img.poster').attr('src') || 
                    $('img[alt*="poster"]').attr('src') || 
                    $('meta[property="og:image"]').attr('content') || 
                    '',
                    
            year: $('.year, .date, .release-date').text().trim().match(/\d{4}/)?.[0] || 
                  $('meta[property="og:release_date"]').attr('content')?.match(/\d{4}/)?.[0] || 
                  '2025',
                  
            rating: parseFloat($('.rating, .rate, .imdb, .score').text().trim()) || 0,
            
            streamUrl: await extractStreamUrl(page)
        };

        // تكملة الروابط الناقصة
        if (movie.poster && !movie.poster.startsWith('http')) {
            movie.poster = 'https://akwams.org' + movie.poster;
        }

        log(`تم استخراج: ${movie.title}`, 'success');
        return movie;

    } catch (error) {
        log(`خطأ في سحب ${pageUrl}: ${error.message}`, 'error');
        if (retries > 0) {
            log(`سأعيد المحاولة ${retries} مرة أخرى...`, 'warning');
            await page.waitForTimeout(2000);
            return scrapeMovieFromUrl(pageUrl, retries - 1);
        }
        return null;
    } finally {
        await browser.close();
    }
}

// دالة لاستخراج رابط المشاهدة
async function extractStreamUrl(page) {
    try {
        // محاولة 1: البحث عن iframe
        const iframeSrc = await page.$eval(
            'iframe[src*="vidsrc"], iframe[src*="embed"], iframe[src*="player"], iframe[src*="m3u8"]', 
            el => el.src
        ).catch(() => null);
        if (iframeSrc && iframeSrc.includes('http')) {
            log(`وجدنا iframe: ${iframeSrc}`, 'success');
            return iframeSrc;
        }

        // محاولة 2: البحث عن فيديو مباشر
        const videoSrc = await page.$eval('video source, video', el => el.src || el.getAttribute('src'))
            .catch(() => null);
        if (videoSrc && videoSrc.includes('http')) {
            log(`وجدنا فيديو: ${videoSrc}`, 'success');
            return videoSrc;
        }

        // محاولة 3: البحث عن رابط في النص
        const linkInText = await page.evaluate(() => {
            const links = document.querySelectorAll('a');
            for (const link of links) {
                const href = link.href;
                if (href && (href.includes('.m3u8') || href.includes('.mp4') || 
                    href.includes('vidsrc') || href.includes('embed'))) {
                    return href;
                }
            }
            return null;
        });
        if (linkInText) {
            log(`وجدنا رابط: ${linkInText}`, 'success');
            return linkInText;
        }

        log('لم نجد أي رابط مشاهدة', 'warning');
        return null;
        
    } catch (error) {
        log(`فشل استخراج رابط المشاهدة: ${error.message}`, 'error');
        return null;
    }
}

// دالة لإضافة الفيلم للتطبيق
async function addMovieToApp(movieData) {
    if (!movieData || !movieData.title) {
        log('بيانات الفيلم غير صالحة', 'error');
        return null;
    }

    try {
        // محاولة جلب التوكن
        let token = null;
        try {
            const loginResponse = await axios.post(`${config.appUrl}/api/login`, {
                username: config.adminEmail,
                password: config.adminPassword
            });
            token = loginResponse.data.access_token;
            log('تم تسجيل الدخول بنجاح', 'success');
        } catch (error) {
            log(`فشل تسجيل الدخول: ${error.message}`, 'error');
            return null;
        }

        // إضافة الفيلم
        const mediaData = {
            type: 'movie',
            title: movieData.title.substring(0, 255),
            slug: movieData.title.toLowerCase()
                .replace(/[^\w\s\u0600-\u06FF]/g, '')
                .replace(/\s+/g, '-')
                .substring(0, 100),
            overview: movieData.overview || 'لا يوجد وصف',
            poster_path: movieData.poster || '',
            backdrop_path: movieData.poster || '',
            release_date: `${movieData.year || '2025'}-01-01`,
            vote_average: movieData.rating || 0,
            is_featured: false,
            is_published: true
        };

        log(`جاري إضافة: ${movieData.title}`, 'info');
        const mediaResponse = await axios.post(
            `${config.appUrl}/api/admin/media`,
            mediaData,
            { headers: { 'Authorization': `Bearer ${token}` } }
        );
        
        log(`تم إضافة فيلم: ${movieData.title}`, 'success');

        // إضافة رابط المشاهدة
        if (movieData.streamUrl && movieData.streamUrl.includes('http')) {
            const streamData = {
                name: 'Stream Server',
                url: movieData.streamUrl,
                quality: '1080p',
                language: 'ar',
                is_active: true
            };
            
            const mediaSlug = mediaResponse.data.slug || mediaResponse.data.id;
            await axios.post(
                `${config.appUrl}/api/admin/media/${mediaSlug}/streams`,
                streamData,
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            log(`تم إضافة رابط المشاهدة لـ ${movieData.title}`, 'success');
        } else {
            log(`مفيش رابط مشاهدة لـ ${movieData.title}`, 'warning');
        }

        return mediaResponse.data;

    } catch (error) {
        log(`فشل إضافة ${movieData.title}: ${error.message}`, 'error');
        if (error.response) {
            log(`تفاصيل: ${JSON.stringify(error.response.data)}`, 'error');
        }
        return null;
    }
}

// الدالة الرئيسية
async function main() {
    log('🚀 بدء تشغيل السكربت المتطور...', 'info');
    log(`عدد الأفلام المطلوب جلبها: ${MAX_MOVIES}`, 'info');
    
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    
    // ضبط الـ User-Agent لتجنب الحظر
    await page.setExtraHTTPHeaders({
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });

    let allMovieUrls = [];

    // جلب الروابط من أكوام
    try {
        log('جاري فتح أكوام...', 'info');
        await page.goto('https://akwams.org/movies/', { 
            waitUntil: 'networkidle', 
            timeout: 30000 
        });
        const akwamsUrls = await getMovieUrlsFromAkwams(page);
        allMovieUrls = allMovieUrls.concat(akwamsUrls);
    } catch (error) {
        log(`فشل في جلب الروابط من أكوام: ${error.message}`, 'error');
    }

    // جلب الروابط من سيما لايت
    try {
        log('جاري فتح سيما لايت...', 'info');
        await page.goto('https://r.cimalight.co/main24', { 
            waitUntil: 'networkidle', 
            timeout: 30000 
        });
        const cimalightUrls = await getMovieUrlsFromCimalight(page);
        allMovieUrls = allMovieUrls.concat(cimalightUrls);
    } catch (error) {
        log(`فشل في جلب الروابط من سيما لايت: ${error.message}`, 'error');
    }

    await browser.close();

    // إزالة التكرار
    allMovieUrls = [...new Set(allMovieUrls)];
    log(`إجمالي الروابط الفريدة المستخرجة: ${allMovieUrls.length}`, 'info');

    if (allMovieUrls.length === 0) {
        log('لم نجد أي روابط أفلام، تأكد من المواقع شغالة', 'warning');
        return;
    }

    // معالجة كل فيلم
    let successCount = 0;
    for (let i = 0; i < allMovieUrls.length; i++) {
        const url = allMovieUrls[i];
        log(`[${i+1}/${allMovieUrls.length}] معالجة: ${url}`, 'info');
        
        // ننتظر قليلاً بين كل طلب
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        const movieData = await scrapeMovieFromUrl(url);
        if (movieData) {
            await addMovieToApp(movieData);
            successCount++;
        }
        
        if (i < allMovieUrls.length - 1) {
            log(`ننتظر ${DELAY_BETWEEN_REQUESTS/1000} ثواني...`, 'info');
            await new Promise(resolve => setTimeout(resolve, DELAY_BETWEEN_REQUESTS));
        }
    }

    log(`🎉 تم الانتهاء!`, 'success');
    log(`تمت معالجة ${successCount} فيلم بنجاح من أصل ${allMovieUrls.length}`, 'info');
}

// تشغيل السكربت
main().catch(error => {
    log(`💥 خطأ غير متوقع: ${error}`, 'error');
    process.exit(1);
});
