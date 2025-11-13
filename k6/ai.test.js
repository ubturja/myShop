import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '30s', target: 15 },
    { duration: '1m', target: 15 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'], // AI endpoints may be slower
    http_req_failed: ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

function createUser() {
  const payload = JSON.stringify({
    name: `AIUser ${__VU}_${Date.now()}`,
    email: `aiuser${__VU}_${Date.now()}@test.com`,
    password: 'Test1234!',
    password_confirmation: 'Test1234!',
  });

  const res = http.post(`${BASE_URL}/api/register`, payload, {
    headers: { 'Content-Type': 'application/json' },
  });

  if (res.status === 201) {
    return JSON.parse(res.body).token;
  }
  return null;
}

export default function () {
  const token = createUser();
  
  if (!token) {
    errorRate.add(1);
    return;
  }

  const headers = {
    Authorization: `Bearer ${token}`,
    'Content-Type': 'application/json',
  };

  sleep(1);

  group('AI Operations', () => {
    // Get product recommendations
    const productId = Math.floor(Math.random() * 10) + 1;
    const recsRes = http.get(
      `${BASE_URL}/api/ai/recommendations?productId=${productId}&n=5`,
      { headers }
    );

    const recsSuccess = check(recsRes, {
      'get recommendations status is 200': (r) => r.status === 200,
      'returns recommendations': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.recommendations && Array.isArray(body.recommendations);
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!recsSuccess);

    sleep(2);

    // Chat with AI assistant
    const messages = [
      'I need a blue suit for a wedding',
      'What accessories would go well with it?',
      'Show me some options under $200',
      'Do you have any sales going on?',
    ];

    const message = messages[Math.floor(Math.random() * messages.length)];
    const assistantPayload = JSON.stringify({
      message: message,
    });

    const assistantRes = http.post(
      `${BASE_URL}/api/ai/assistant`,
      assistantPayload,
      { headers }
    );

    const assistantSuccess = check(assistantRes, {
      'assistant status is 200': (r) => r.status === 200,
      'returns reply': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.reply !== undefined;
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!assistantSuccess);

    sleep(2);

    // Search products with AI
    const searchPayload = JSON.stringify({
      query: 'formal wear for summer',
    });

    const searchRes = http.post(
      `${BASE_URL}/api/ai/search`,
      searchPayload,
      { headers }
    );

    check(searchRes, {
      'AI search status is 200': (r) => r.status === 200,
      'returns results': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.results && Array.isArray(body.results);
        } catch {
          return false;
        }
      },
    });
  });

  sleep(3);
}
