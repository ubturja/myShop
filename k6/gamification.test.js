import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '20s', target: 10 },
    { duration: '40s', target: 10 },
    { duration: '20s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<600'],
    http_req_failed: ['rate<0.02'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

function createUser() {
  const payload = JSON.stringify({
    name: `GamificationUser ${__VU}_${Date.now()}`,
    email: `gamify${__VU}_${Date.now()}@test.com`,
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

  group('Gamification Operations', () => {
    // Get available prizes (public endpoint)
    const prizesRes = http.get(`${BASE_URL}/api/gamification/prizes`);

    const prizesSuccess = check(prizesRes, {
      'get prizes status is 200': (r) => r.status === 200,
      'returns prizes array': (r) => {
        try {
          return Array.isArray(JSON.parse(r.body));
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!prizesSuccess);

    sleep(1);

    // Spin the wheel
    const spinRes = http.post(`${BASE_URL}/api/gamification/spin`, null, {
      headers,
    });

    const spinSuccess = check(spinRes, {
      'spin status is 200 or 429': (r) => r.status === 200 || r.status === 429,
      'returns prize or rate limit': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.prize !== undefined || body.message !== undefined;
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!spinSuccess && spinRes.status !== 429);

    sleep(1);

    // Get user's rewards
    const rewardsRes = http.get(`${BASE_URL}/api/gamification/rewards`, {
      headers,
    });

    const rewardsSuccess = check(rewardsRes, {
      'get rewards status is 200': (r) => r.status === 200,
      'returns rewards data': (r) => {
        try {
          const body = JSON.parse(r.body);
          return (
            body.rewards !== undefined && body.can_spin_today !== undefined
          );
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!rewardsSuccess);

    sleep(1);

    // Try spinning again (should hit daily limit)
    const spinAgainRes = http.post(
      `${BASE_URL}/api/gamification/spin`,
      null,
      { headers }
    );

    check(spinAgainRes, {
      'second spin returns 429 (rate limited)': (r) => r.status === 429,
      'rate limit message present': (r) => {
        try {
          return JSON.parse(r.body).message !== undefined;
        } catch {
          return false;
        }
      },
    });
  });

  sleep(2);
}
