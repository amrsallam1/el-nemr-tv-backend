const axios = require('axios');
const cheerio = require('cheerio');
const config = require('./config');

async function scrapeMovieFromUrl(pageUrl) {
    try {
        console.log(`🕵️ جاري فتح: ${pageUrl}`);
        const { data } = await axios.get(pageUrl, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            },
            timeout: 30000
        });
        const $ = cheerio.load(data);

        // استخراج البيانات
        const movie = {
            title: $('h1').first().text().trim() || $('.title').first().text().trim() || 'فيلم بدون عنوان',
            overview: $('.description, .story, .plot, .summary').text().trim() || 'لا يوجد وصف',
            poster: $('.poster img').attr('src') || $('img.poster').attr('src') || '',
            year: $('.year, .date, .release-date').text().trim().match(/\d{4}/)?.[0] || '2025',
            rating: parseFloat($('.rating, .rate, .imdb, .score').text().trim()) || 0,
            streamUrl: await extractStreamUrlFromHtml($)
        };

        console.log(`✅ تم استخراج: ${movie.title}`);
        return movie;
    } catch (error) {
        console.error(`❌ خطأ في سحب ${pageUrl}:`, error.message);
        return null;
    }
}

async function extractStreamUrlFromHtml($) {
    // البحث عن iframe أو فيديو أو رابط مباشر في الـ HTML
    let streamUrl = $('iframe[src*="vidsrc"], iframe[src*="embed"], iframe[src*="player"]').attr('src');
    if (streamUrl) return streamUrl;

    streamUrl = $('video source').attr('src') || $('video').attr('src');
    if (streamUrl) return streamUrl;

    $('a').each((i, el) => {
        const href = $(el).attr('href');
        if (href && (href.includes('.m3u8') || href.includes('.mp4') || href.includes('vidsrc'))) {
            streamUrl = href;
            return false;
        }
    });
    return streamUrl || null;
}

// باقي الكود (main, addMovieToApp) بدون تغيير...
