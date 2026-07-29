const { chromium } = require('playwright');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');
const dotenv = require('dotenv');
const { addMovie, addStream } = require('./api-client');

dotenv.config();

const runtimeConfig = {
  maxMoviesPerRun: parseInt(process.env.MAX_MOVIES || '5', 10),
  headless: process.env.HEADLESS !== 'false',
  delayBetweenRequests: parseInt(process.env.DELAY_BETWEEN_REQUESTS || '2500', 10),
  timeout: parseInt(process.env.REQUEST_TIMEOUT || '45000', 10),
  retryCount: parseInt(process.env.RETRY_COUNT || '3', 10),
  maxCandidatesPerSource: parseInt(process.env.MAX_CANDIDATES_PER_SOURCE || '80', 10),
  snapshotDir: path.join(__dirname, 'snapshots'),
  reportFile: path.join(__dirname, 'run-report.json'),
  sources: [
    { name: 'Akwams', url: 'https://akwams.org/movies/', baseUrl: 'https://akwams.org' },
    { name: 'Cimalight', url: 'https://r.cimalight.co/main24', baseUrl: 'https://r.cimalight.co' },
  ],
};

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function ensureDir(dirPath) {
  if (!fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
}

function slugify(text) {
  return String(text || '')
    .toLowerCase()
    .trim()
    .replace(/[^\w\s\u0600-\u06FF-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 100);
}

function absolutizeUrl(url, baseUrl) {
  if (!url) return '';
  if (/^https?:\/\//i.test(url)) return url;
  return `${baseUrl.replace(/\/$/, '')}/${url.replace(/^\//, '')}`;
}

function normalizeUrl(url) {
  try {
    const parsed = new URL(url);
    parsed.hash = '';
    parsed.search = '';
    return parsed.toString().replace(/\/$/, '');
  } catch {
    return String(url || '').replace(/\/$/, '');
  }
}

async function gotoWithRetry(page, url, options = {}) {
  let lastError = null;

  for (let attempt = 1; attempt <= runtimeConfig.retryCount; attempt += 1) {
    try {
      return await page.goto(url, {
        waitUntil: options.waitUntil || 'domcontentloaded',
        timeout: options.timeout || runtimeConfig.timeout,
      });
    } catch (error) {
      lastError = error;
      console.error(`Load failed (${attempt}/${runtimeConfig.retryCount}) ${url}: ${error.message}`);
      if (attempt < runtimeConfig.retryCount) {
        await sleep(1500);
      }
    }
  }

  throw lastError;
}

async function saveSnapshot(page, label) {
  try {
    ensureDir(runtimeConfig.snapshotDir);
    const safeLabel = slugify(label || 'snapshot') || 'snapshot';
    const snapshotPath = path.join(runtimeConfig.snapshotDir, `${Date.now()}-${safeLabel}.html`);
    await fs.promises.writeFile(snapshotPath, await page.content(), 'utf8');
    return snapshotPath;
  } catch (error) {
    console.error(`Snapshot failed for ${label}: ${error.message}`);
    return null;
  }
}

async function writeReport(report) {
  try {
    await fs.promises.writeFile(runtimeConfig.reportFile, JSON.stringify(report, null, 2), 'utf8');
  } catch (error) {
    console.error(`Failed to write report: ${error.message}`);
  }
}

function extractText($, selectors, fallback = '') {
  for (const selector of selectors) {
    const value = $(selector).first().text().trim();
    if (value) return value;
  }
  return fallback;
}

function extractAttr($, selectors, attr, fallback = '') {
  for (const selector of selectors) {
    const value = $(selector).first().attr(attr);
    if (value) return value.trim();
  }
  return fallback;
}

function extractMeta($, names) {
  for (const name of names) {
    const value = $(`meta[property="${name}"], meta[name="${name}"]`).first().attr('content');
    if (value) return value.trim();
  }
  return '';
}

function extractYear(text) {
  const match = String(text || '').match(/\b(19|20)\d{2}\b/);
  return match ? match[0] : '2025';
}

function parseJsonLd(html) {
  const matches = [];
  const regex = /<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi;
  let match;
  while ((match = regex.exec(html)) !== null) {
    try {
      const payload = JSON.parse(match[1]);
      matches.push(payload);
    } catch {
      continue;
    }
  }
  return matches.flatMap((entry) => (Array.isArray(entry) ? entry : [entry]));
}

function isMovieLikeUrl(href) {
  if (!href) return false;
  const value = href.toLowerCase();
  return (
    value.includes('/movie/') ||
    value.includes('/movies/') ||
    value.includes('/film/') ||
    value.includes('/films/') ||
    /\b(movie|film)\b/.test(value)
  );
}

function extractLocsFromXml(xml) {
  const matches = [];
  const regex = /<loc>(.*?)<\/loc>/gi;
  let match;
  while ((match = regex.exec(xml)) !== null) {
    const value = match[1]?.trim();
    if (value) matches.push(value);
  }
  return matches;
}

async function collectUrlsFromPage(page, source) {
  return page.evaluate(({ baseUrl, maxCandidatesPerSource }) => {
    const selectorParts = [
      'a[href*="/movie/"]',
      'a[href*="/movies/"]',
      'a[href*="/film/"]',
      'a[href*="/films/"]',
      'a[href*="movie"]',
      'a[href*="film"]',
      'article a',
      'main a',
      '.card a',
      '.movie a',
      '.film a',
      '.post a',
      '.item a',
      '[data-href]',
    ];

    const anchors = Array.from(document.querySelectorAll(selectorParts.join(', ')));
    const urls = [];

    const isCandidate = (href, text) => {
      const value = `${href || ''} ${text || ''}`.toLowerCase();
      return /movie|film|watch|stream|episode|series/.test(value);
    };

    for (const element of anchors) {
      const rawHref = element.getAttribute('href') || element.getAttribute('data-href') || element.href || '';
      const text = (element.textContent || '').trim();
      if (!rawHref) continue;

      let href = rawHref;
      if (!/^https?:\/\//i.test(href)) {
        href = `${baseUrl.replace(/\/$/, '')}/${href.replace(/^\//, '')}`;
      }

      if (!isCandidate(href, text)) continue;
      if (!urls.includes(href)) urls.push(href);
      if (urls.length >= maxCandidatesPerSource) break;
    }

    return urls;
  }, source);
}

async function collectUrlsFromRobots(page, source) {
  const robotsUrl = `${source.baseUrl.replace(/\/$/, '')}/robots.txt`;
  try {
    const response = await page.goto(robotsUrl, {
      waitUntil: 'domcontentloaded',
      timeout: runtimeConfig.timeout,
    });
    const status = response?.status?.() || 0;
    if (status >= 400) return [];

    const content = await page.content();
    const text = cheerio.load(content).text();
    const urls = [];
    for (const line of text.split(/\r?\n/)) {
      const match = line.match(/sitemap:\s*(.+)$/i);
      if (match?.[1]) {
        const sitemapUrl = match[1].trim();
        if (sitemapUrl.endsWith('.xml')) {
          urls.push(sitemapUrl);
        }
      }
    }
    return urls;
  } catch {
    return [];
  }
}

async function collectUrlsFromSitemap(page, source) {
  const sitemapCandidates = new Set([
    `${source.baseUrl.replace(/\/$/, '')}/sitemap.xml`,
    `${source.baseUrl.replace(/\/$/, '')}/sitemap_index.xml`,
    `${source.baseUrl.replace(/\/$/, '')}/post-sitemap.xml`,
    `${source.baseUrl.replace(/\/$/, '')}/page-sitemap.xml`,
    `${source.baseUrl.replace(/\/$/, '')}/category-sitemap.xml`,
  ]);

  const visited = new Set();
  const collectedUrls = new Set();
  const queue = [...sitemapCandidates];

  while (queue.length > 0 && collectedUrls.size < runtimeConfig.maxCandidatesPerSource) {
    const sitemapUrl = queue.shift();
    if (!sitemapUrl || visited.has(sitemapUrl)) continue;
    visited.add(sitemapUrl);

    try {
      const response = await page.goto(sitemapUrl, {
        waitUntil: 'domcontentloaded',
        timeout: runtimeConfig.timeout,
      });
      const status = response?.status?.() || 0;
      if (status >= 400) continue;

      const xml = await page.content();
      const locs = extractLocsFromXml(xml);

      for (const loc of locs) {
        if (loc.endsWith('.xml')) {
          if (!visited.has(loc)) queue.push(loc);
          continue;
        }

        if (isMovieLikeUrl(loc)) {
          collectedUrls.add(normalizeUrl(loc));
        }
      }
    } catch {
      continue;
    }
  }

  return [...collectedUrls].slice(0, runtimeConfig.maxCandidatesPerSource);
}

async function extractStreamUrl(page, html) {
  const selectors = [
    'iframe[src*="vidsrc"]',
    'iframe[src*="embed"]',
    'iframe[src*="player"]',
    'iframe[src*="m3u8"]',
    'video source',
    'video',
    'source[src*=".m3u8"]',
    'source[src*=".mp4"]',
  ];

  for (const selector of selectors) {
    const candidate = await page.$eval(selector, (element) => element.src || element.getAttribute('src') || '').catch(() => '');
    if (candidate && /^https?:\/\//i.test(candidate)) {
      return candidate;
    }
  }

  const textCandidate = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('a'));
    for (const link of links) {
      const href = link.href || link.getAttribute('href') || '';
      const value = href.toLowerCase();
      if (value.includes('.m3u8') || value.includes('.mp4') || value.includes('vidsrc') || value.includes('embed')) {
        return href;
      }
    }
    return '';
  });

  if (textCandidate) return textCandidate;

  const scriptMatch = html.match(/https?:\/\/[^\s"'<>]+\.(m3u8|mp4)(\?[^\s"'<>]*)?/i);
  if (scriptMatch) return scriptMatch[0];

  return '';
}

async function scrapeMovieFromUrl(pageUrl) {
  const browser = await chromium.launch({ headless: runtimeConfig.headless });
  const page = await browser.newPage();

  try {
    await page.setExtraHTTPHeaders({
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
      'Accept-Language': 'ar,en-US;q=0.9,en;q=0.8',
    });

    await gotoWithRetry(page, pageUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);

    const html = await page.content();
    const $ = cheerio.load(html);
    const jsonLd = parseJsonLd(html);

    const schemaMovie = jsonLd.find((item) => {
      const type = item?.['@type'];
      return type === 'Movie' || (Array.isArray(type) && type.includes('Movie'));
    });

    const title = extractText($, [
      'h1',
      '.title',
      '.movie-title',
      '.entry-title',
      '.post-title',
    ], schemaMovie?.name || extractMeta($, ['og:title', 'twitter:title']) || 'فيلم بدون عنوان');

    const overview = extractText($, [
      '.description',
      '.story',
      '.plot',
      '.summary',
      '.content',
      '.entry-content',
      '.movie-description',
    ], schemaMovie?.description || extractMeta($, ['og:description', 'description']) || 'لا يوجد وصف');

    const poster = absolutizeUrl(
      extractAttr($, [
        '.poster img',
        'img.poster',
        'img[alt*="poster"]',
        'img[class*="poster"]',
      ], 'src', extractMeta($, ['og:image', 'twitter:image'])),
      new URL(pageUrl).origin,
    );

    const yearText = extractText($, [
      '.year',
      '.date',
      '.release-date',
      '.movie-year',
    ], schemaMovie?.datePublished || extractMeta($, ['og:release_date']));

    const ratingText = extractText($, [
      '.rating',
      '.rate',
      '.imdb',
      '.score',
    ], schemaMovie?.aggregateRating?.ratingValue ? String(schemaMovie.aggregateRating.ratingValue) : '0');

    const streamUrl = await extractStreamUrl(page, html);

    return {
      title,
      overview,
      poster,
      year: extractYear(yearText),
      rating: parseFloat(String(ratingText).replace(',', '.')) || 0,
      streamUrl,
      sourceUrl: pageUrl,
    };
  } catch (error) {
    await saveSnapshot(page, `movie-error-${slugify(pageUrl)}`);
    console.error(`Scrape failed ${pageUrl}: ${error.message}`);
    return null;
  } finally {
    await browser.close();
  }
}

async function processMovie(movieData) {
  if (!movieData || !movieData.title) return false;

  const mediaData = {
    type: 'movie',
    title: movieData.title.substring(0, 255),
    slug: slugify(movieData.title) || `movie-${Date.now()}`,
    overview: movieData.overview || 'لا يوجد وصف',
    poster_path: movieData.poster || '',
    backdrop_path: movieData.poster || '',
    release_date: `${movieData.year || '2025'}-01-01`,
    vote_average: movieData.rating || 0,
    is_featured: false,
    is_published: true,
  };

  const result = await addMovie(mediaData);

  if (movieData.streamUrl && /^https?:\/\//i.test(movieData.streamUrl)) {
    const streamData = {
      name: 'Stream Server',
      url: movieData.streamUrl,
      quality: '1080p',
      language: 'ar',
      is_active: true,
    };

    const mediaSlug = result.slug || result.id || mediaData.slug;
    await addStream(mediaSlug, streamData);
  }

  return true;
}

async function main() {
  ensureDir(runtimeConfig.snapshotDir);

  const browser = await chromium.launch({ headless: runtimeConfig.headless });
  const page = await browser.newPage();
  let processedCount = 0;
  let skippedSources = 0;
  let skippedMovies = 0;
  const collected = [];
  const report = {
    startedAt: new Date().toISOString(),
    sources: [],
    totals: {},
    errors: [],
  };

  try {
    await page.setExtraHTTPHeaders({
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
      'Accept-Language': 'ar,en-US;q=0.9,en;q=0.8',
    });

    for (const source of runtimeConfig.sources) {
      try {
        console.log(`Opening source: ${source.name} -> ${source.url}`);
        await gotoWithRetry(page, source.url, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        const pageUrls = await collectUrlsFromPage(page, source);
        const robotsSitemaps = await collectUrlsFromRobots(page, source);
        const sitemapUrls = await collectUrlsFromSitemap(page, source);
        const urls = [...new Set([...pageUrls, ...sitemapUrls, ...robotsSitemaps])];
        console.log(`Collected ${pageUrls.length} page URLs, ${sitemapUrls.length} sitemap URLs and ${robotsSitemaps.length} robots URLs from ${source.name}`);

        if (urls.length === 0) {
          const sample = await page.evaluate(() => Array.from(document.querySelectorAll('a')).slice(0, 25).map((link) => ({
            text: (link.textContent || '').trim(),
            href: link.href || link.getAttribute('href') || '',
          })));
          console.log(`Sample links from ${source.name}: ${JSON.stringify(sample)}`);
          await saveSnapshot(page, `empty-${source.name}`);
        }

        report.sources.push({
          name: source.name,
          url: source.url,
          pageUrls: pageUrls.length,
          robotsUrls: robotsSitemaps.length,
          sitemapUrls: sitemapUrls.length,
          total: urls.length,
        });

        collected.push(...urls);
      } catch (error) {
        skippedSources += 1;
        console.error(`Skipping source ${source.name}: ${error.message}`);
        report.errors.push({ source: source.name, message: error.message });
        await saveSnapshot(page, `source-error-${source.name}`);
      }
    }

    await browser.close();

    const uniqueUrls = [...new Set(collected)].map(normalizeUrl);
    const urlsToProcess = uniqueUrls.slice(0, runtimeConfig.maxMoviesPerRun);

    console.log(`Sources checked: ${runtimeConfig.sources.length}, skipped: ${skippedSources}, collected URLs: ${uniqueUrls.length}, processing: ${urlsToProcess.length}`);

    for (let index = 0; index < urlsToProcess.length; index += 1) {
      const url = urlsToProcess[index];
      try {
        console.log(`\n[${index + 1}/${urlsToProcess.length}] Processing ${url}`);
        const movieData = await scrapeMovieFromUrl(url);
        if (!movieData) {
          skippedMovies += 1;
          continue;
        }

        const saved = await processMovie(movieData);
        if (saved) {
          processedCount += 1;
          console.log(`Saved: ${movieData.title}`);
        } else {
          skippedMovies += 1;
        }
      } catch (error) {
        skippedMovies += 1;
        console.error(`Skipping movie ${url}: ${error.message}`);
      }

      if (index < urlsToProcess.length - 1) {
        await sleep(runtimeConfig.delayBetweenRequests);
      }
    }

    console.log(`Summary: processed=${processedCount}, skipped_sources=${skippedSources}, skipped_movies=${skippedMovies}, total_urls=${uniqueUrls.length}`);

    report.totals = {
      processed: processedCount,
      skippedSources,
      skippedMovies,
      collectedUrls: uniqueUrls.length,
      processedUrls: urlsToProcess.length,
      finishedAt: new Date().toISOString(),
    };
    await writeReport(report);
  } catch (error) {
    await browser.close();
    report.errors.push({ source: 'fatal', message: error.message });
    report.totals = {
      processed: processedCount,
      skippedSources,
      skippedMovies,
      collectedUrls: [...new Set(collected)].length,
      finishedAt: new Date().toISOString(),
    };
    await writeReport(report);
    throw error;
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
