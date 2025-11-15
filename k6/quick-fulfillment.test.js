import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '30s', target: 25 },
    { duration: '1m', target: 25 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<600'],
    http_req_failed: ['rate<0.02'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

function createUser() {
  const payload = JSON.stringify({
    name: `QuickFulfillUser ${__VU}_${Date.now()}`,
    email: `quickfulfill${__VU}_${Date.now()}@test.com`,
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

  group('Quick Fulfillment Operations', () => {
    // Check nearest store for a product
    const productId = Math.floor(Math.random() * 10) + 1;
    const checkPayload = JSON.stringify({
      product_id: productId,
      quantity: 1,
      user_lat: 40.7128,  // NYC coordinates
      user_lng: -74.0060,
    });

    const checkRes = http.post(
      `${BASE_URL}/api/quick-fulfillment/check`,
      checkPayload,
      { headers }
    );

    const checkSuccess = check(checkRes, {
      'check nearest store status is 200': (r) => r.status === 200,
      'returns store or null': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.store !== undefined;
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!checkSuccess);

    sleep(1);

    // Check cart fulfillment
    const cartCheckPayload = JSON.stringify({
      cart_items: [
        { product_id: 1, quantity: 1 },
        { product_id: 2, quantity: 2 },
      ],
      user_lat: 40.7128,
      user_lng: -74.0060,
    });

    const cartCheckRes = http.post(
      `${BASE_URL}/api/quick-fulfillment/check-cart`,
      cartCheckPayload,
      { headers }
    );

    check(cartCheckRes, {
      'check cart fulfillment status is 200': (r) => r.status === 200,
    });

    sleep(1);

    // Get nearby stores
    const nearbyPayload = JSON.stringify({
      user_lat: 40.7128,
      user_lng: -74.0060,
      radius: 50, // 50km
    });

    const nearbyRes = http.post(
      `${BASE_URL}/api/quick-fulfillment/nearby-stores`,
      nearbyPayload,
      { headers }
    );

    const nearbySuccess = check(nearbyRes, {
      'get nearby stores status is 200': (r) => r.status === 200,
      'returns stores array': (r) => {
        try {
          const body = JSON.parse(r.body);
          return Array.isArray(body.stores);
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!nearbySuccess);

    sleep(1);

    // Calculate ETA
    const etaPayload = JSON.stringify({
      store_id: 1,
      user_lat: 40.7128,
      user_lng: -74.0060,
    });

    const etaRes = http.post(
      `${BASE_URL}/api/quick-fulfillment/eta`,
      etaPayload,
      { headers }
    );

    check(etaRes, {
      'calculate ETA status is 200': (r) => r.status === 200,
      'returns eta minutes': (r) => {
        try {
          return JSON.parse(r.body).eta_minutes !== undefined;
        } catch {
          return false;
        }
      },
    });
  });

  sleep(2);
}
