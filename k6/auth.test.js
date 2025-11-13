import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');

// Test configuration
export const options = {
  stages: [
    { duration: '30s', target: 20 },  // Ramp up to 20 users
    { duration: '1m', target: 20 },   // Stay at 20 users
    { duration: '20s', target: 50 },  // Spike to 50 users
    { duration: '30s', target: 50 },  // Stay at 50 users
    { duration: '30s', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'], // 95% requests < 500ms
    http_req_failed: ['rate<0.01'],                  // Error rate < 1%
    errors: ['rate<0.05'],                           // Custom error rate < 5%
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
  // Test: User Registration
  const registerPayload = JSON.stringify({
    name: `User ${__VU}_${Date.now()}`,
    email: `user${__VU}_${Date.now()}@test.com`,
    password: 'Test1234!',
    password_confirmation: 'Test1234!',
  });

  const registerRes = http.post(`${BASE_URL}/api/register`, registerPayload, {
    headers: { 'Content-Type': 'application/json' },
  });

  const registerSuccess = check(registerRes, {
    'registration status is 201': (r) => r.status === 201,
    'registration returns token': (r) => {
      try {
        return JSON.parse(r.body).token !== undefined;
      } catch {
        return false;
      }
    },
  });

  errorRate.add(!registerSuccess);

  if (!registerSuccess) {
    console.error(`Registration failed: ${registerRes.status} ${registerRes.body}`);
    return;
  }

  const registerData = JSON.parse(registerRes.body);
  const token = registerData.token;
  const email = JSON.parse(registerPayload).email;
  const password = JSON.parse(registerPayload).password;

  sleep(1);

  // Test: User Login
  const loginPayload = JSON.stringify({
    email: email,
    password: password,
  });

  const loginRes = http.post(`${BASE_URL}/api/login`, loginPayload, {
    headers: { 'Content-Type': 'application/json' },
  });

  const loginSuccess = check(loginRes, {
    'login status is 200': (r) => r.status === 200,
    'login returns token': (r) => {
      try {
        return JSON.parse(r.body).token !== undefined;
      } catch {
        return false;
      }
    },
  });

  errorRate.add(!loginSuccess);

  sleep(1);

  // Test: Get User Profile
  const profileRes = http.get(`${BASE_URL}/api/user`, {
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });

  const profileSuccess = check(profileRes, {
    'profile status is 200': (r) => r.status === 200,
    'profile returns user data': (r) => {
      try {
        return JSON.parse(r.body).email !== undefined;
      } catch {
        return false;
      }
    },
  });

  errorRate.add(!profileSuccess);

  sleep(1);

  // Test: Logout
  const logoutRes = http.post(
    `${BASE_URL}/api/logout`,
    null,
    {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
    }
  );

  const logoutSuccess = check(logoutRes, {
    'logout status is 200 or 204': (r) => r.status === 200 || r.status === 204,
  });

  errorRate.add(!logoutSuccess);

  sleep(2);
}
