const axios = require('axios');
const dotenv = require('dotenv');

dotenv.config();

const api = axios.create({
  baseURL: process.env.APP_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

let token = null;

async function login() {
  if (token) return token;
  const response = await api.post('/api/login', {
    email: process.env.ADMIN_EMAIL,
    password: process.env.ADMIN_PASSWORD,
  });
  token = response.data.token || response.data.accessToken || response.data.access_token;
  if (!token) throw new Error('Login did not return a token');
  api.defaults.headers.common.Authorization = `Bearer ${token}`;
  return token;
}

async function addMovie(movieData) {
  await login();
  const response = await api.post('/api/admin/media', movieData);
  return response.data;
}

async function addStream(mediaSlug, streamData) {
  await login();
  const response = await api.post(`/api/admin/media/${encodeURIComponent(mediaSlug)}/streams`, streamData);
  return response.data;
}

module.exports = { addMovie, addStream };
