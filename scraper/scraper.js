const { chromium } = require('playwright');
const cheerio = require('cheerio');
const dotenv = require('dotenv');
const { addMovie, addStream } = require('./api-client');

dotenv.config();

const config = {
  maxMoviesPerRun: parseInt(process.env.MAX_MOVIES || '5', 10),
  headless: true,
  delayBetweenRequests: 3000,
  timeout: 30000,
  retryCount: 2,
};

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function gotoWithRetry(page, url, options = {}) {
  let lastError = null;

  for (let attempt = 0; attempt <= config.retryCount; attempt += 1) {
    try {
      return await page.goto(url, {
        waitUntil: options.waitUntil || 'domcontentloaded',
        timeout: options.timeout || config.timeout,
      });
    } catch (error) {
      lastError = error;
      console.error(`Failed loading ${url} (attempt ${attempt + 1}): ${error.message}`);
      if (attempt < config.retryCount) {
        await sleep(1500);
      }
    }
  }

  throw lastError;
}

async function scrapeMovieFromUrl(pageUrl) {
  const browser = await chromium.launch({ headless: config.headless });
  const page = await browser.newPage();
  try {
    await gotoWithRetry(page, pageUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    const html = await page.content();
    const $ = cheerio.load(html);
    const movie = {
      title: $('h1').first().text().trim() || $('meta[property="og:title"]').attr('content') || 'Movie',
      overview: $('.description, .story, .plot, .summary, .content').text().trim() || $('meta[property="og:description"]').attr('content') || '',
      poster: $('meta[property="og:image"]').attr('content') || '',
      year: $('meta[property="og:release_date"]').attr('content')?.match(/\d{4}/)?.[0] || '2025',
      rating: 0,
      streamUrl: null,
    };
    return movie;
  } finally {
    await browser.close();
  }
}

async function getMovieUrlsFromPage(page, selectorBaseUrl) {
  const movieLinks = await page.evaluate((baseUrl) => {
    const links = document.querySelectorAll('a[href*="/movie/"], a[href*="/film/"]');
    const urls = [];
    links.forEach((link) => {
      let href = link.getAttribute('href');
      if (href && !href.startsWith('http')) href = baseUrl + href;
      if (href && href.includes('/movie/') && !urls.includes(href)) urls.push(href);
    });
    return urls;
  }, selectorBaseUrl);
  return [...new Set(movieLinks)].slice(0, config.maxMoviesPerRun);
}

async function processMovie(movieData) {
  if (!movieData?.title) return;
  const mediaData = {
    type: 'movie',
    title: movieData.title.substring(0, 255),
    slug: movieData.title.toLowerCase().replace(/[^\w\s\u0600-\u06FF]/g, '').replace(/\s+/g, '-').substring(0, 100),
    overview: movieData.overview || '',
    poster_path: movieData.poster || '',
    backdrop_path: movieData.poster || '',
    release_date: `${movieData.year || '2025'}-01-01`,
    vote_average: movieData.rating || 0,
    is_featured: false,
    is_published: true,
  };
  const result = await addMovie(mediaData);
  if (movieData.streamUrl && movieData.streamUrl.startsWith('http')) {
    await addStream(result.slug || result.id, {
      name: 'Stream Server',
      url: movieData.streamUrl,
      quality: '1080p',
      language: 'ar',
      is_active: true,
    });
  }
}

async function main() {
  const browser = await chromium.launch({ headless: config.headless });
  const page = await browser.newPage();
  try {
    const sourceUrls = [
      { url: 'https://akwams.org/movies/', baseUrl: 'https://akwams.org' },
      { url: 'https://r.cimalight.co/main24', baseUrl: 'https://r.cimalight.co' },
    ];
    let allMovieUrls = [];
    for (const source of sourceUrls) {
      try {
        await gotoWithRetry(page, source.url, { waitUntil: 'domcontentloaded' });
        const urls = await getMovieUrlsFromPage(page, source.baseUrl);
        allMovieUrls = allMovieUrls.concat(urls);
        console.log(`Collected ${urls.length} URLs from ${source.url}`);
      } catch (error) {
        console.error(`Skipping source ${source.url}:`, error.message);
      }
    }
    await browser.close();
    allMovieUrls = [...new Set(allMovieUrls)];
    for (let index = 0; index < allMovieUrls.length; index += 1) {
      try {
        const movieData = await scrapeMovieFromUrl(allMovieUrls[index]);
        await processMovie(movieData);
        console.log(`Processed ${index + 1}/${allMovieUrls.length}: ${movieData?.title || allMovieUrls[index]}`);
      } catch (error) {
        console.error(`Skipping movie ${allMovieUrls[index]}:`, error.message);
      }
      if (index < allMovieUrls.length - 1) {
        await sleep(config.delayBetweenRequests);
      }
    }
  } catch (error) {
    await browser.close();
    throw error;
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
