import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 20 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<800'],
    http_req_failed: ['rate<0.02'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

function createUser() {
  const payload = JSON.stringify({
    name: `GroupBuyUser ${__VU}_${Date.now()}`,
    email: `groupbuy${__VU}_${Date.now()}@test.com`,
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

  group('Group Buy Operations', () => {
    // List active group buys
    const listRes = http.get(`${BASE_URL}/api/group-buys`, { headers });

    const listSuccess = check(listRes, {
      'list group buys status is 200': (r) => r.status === 200,
      'returns array': (r) => {
        try {
          return Array.isArray(JSON.parse(r.body));
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!listSuccess);

    sleep(1);

    // Create a new group buy
    const productId = Math.floor(Math.random() * 10) + 1;
    const createPayload = JSON.stringify({
      product_id: productId,
      target_count: 10,
      discount_percent: 15,
      expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(),
    });

    const createRes = http.post(`${BASE_URL}/api/group-buys`, createPayload, {
      headers,
    });

    const createSuccess = check(createRes, {
      'create group buy status is 201': (r) => r.status === 201,
      'returns group buy id': (r) => {
        try {
          return JSON.parse(r.body).id !== undefined;
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!createSuccess);

    if (createSuccess) {
      const groupBuy = JSON.parse(createRes.body);
      const groupBuyId = groupBuy.id;

      sleep(1);

      // Get group buy details
      const detailRes = http.get(`${BASE_URL}/api/group-buys/${groupBuyId}`, {
        headers,
      });

      check(detailRes, {
        'get group buy details status is 200': (r) => r.status === 200,
      });

      sleep(1);

      // Join group buy
      const joinRes = http.post(
        `${BASE_URL}/api/group-buys/${groupBuyId}/join`,
        null,
        { headers }
      );

      const joinSuccess = check(joinRes, {
        'join group buy status is 200 or 201': (r) =>
          r.status === 200 || r.status === 201,
      });

      errorRate.add(!joinSuccess);

      sleep(1);

      // Check membership
      const checkRes = http.get(
        `${BASE_URL}/api/group-buys/${groupBuyId}/check-membership`,
        { headers }
      );

      check(checkRes, {
        'check membership status is 200': (r) => r.status === 200,
        'is member': (r) => {
          try {
            return JSON.parse(r.body).is_member === true;
          } catch {
            return false;
          }
        },
      });

      sleep(1);

      // Leave group buy
      const leaveRes = http.post(
        `${BASE_URL}/api/group-buys/${groupBuyId}/leave`,
        null,
        { headers }
      );

      check(leaveRes, {
        'leave group buy status is 200 or 204': (r) =>
          r.status === 200 || r.status === 204,
      });
    }

    sleep(1);

    // Get group buy stats
    const statsRes = http.get(`${BASE_URL}/api/group-buys/stats`, { headers });

    check(statsRes, {
      'get stats status is 200': (r) => r.status === 200,
    });
  });

  sleep(2);
}
