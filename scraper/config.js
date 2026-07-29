require('dotenv').config();

module.exports = {
    appUrl: process.env.APP_URL || 'https://el-nemr-tv-backend-production.up.railway.app',
    adminEmail: process.env.ADMIN_EMAIL || 'admin@example.com',
    adminPassword: process.env.ADMIN_PASSWORD || 'your_password_here',
    maxMoviesPerRun: parseInt(process.env.MAX_MOVIES) || 5,
    headless: true,
    delayBetweenRequests: 3000,
    timeout: 30000
};
