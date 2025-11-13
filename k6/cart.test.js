import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '30s', target: 30 },
    { duration: '1m', target: 30 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.02'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// Helper to create authenticated user
function createUser() {
  const payload = JSON.stringify({
    name: `CartUser ${__VU}_${Date.now()}`,
    email: `cartuser${__VU}_${Date.now()}@test.com`,
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

  group('Cart Operations', () => {
    // Add item to cart
    const productId = Math.floor(Math.random() * 10) + 1;
    const addPayload = JSON.stringify({
      product_id: productId,
      quantity: Math.floor(Math.random() * 3) + 1,
      size: 'M',
    });

    const addRes = http.post(`${BASE_URL}/api/cart`, addPayload, { headers });

    const addSuccess = check(addRes, {
      'add to cart status is 201': (r) => r.status === 201,
    });

    errorRate.add(!addSuccess);

    sleep(1);

    // Get cart
    const getCartRes = http.get(`${BASE_URL}/api/cart`, { headers });

    const getSuccess = check(getCartRes, {
      'get cart status is 200': (r) => r.status === 200,
      'cart contains items': (r) => {
        try {
          const cart = JSON.parse(r.body);
          return cart.items && cart.items.length > 0;
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!getSuccess);

    sleep(1);

    // Update cart item
    if (getSuccess && getCartRes.status === 200) {
      try {
        const cart = JSON.parse(getCartRes.body);
        if (cart.items && cart.items.length > 0) {
          const cartItemId = cart.items[0].id;
          const updatePayload = JSON.stringify({
            quantity: 2,
          });

          const updateRes = http.patch(
            `${BASE_URL}/api/cart/${cartItemId}`,
            updatePayload,
            { headers }
          );

          check(updateRes, {
            'update cart item status is 200': (r) => r.status === 200,
          });

          sleep(1);

          // Remove cart item
          const deleteRes = http.del(`${BASE_URL}/api/cart/${cartItemId}`, null, {
            headers,
          });

          check(deleteRes, {
            'remove cart item status is 200 or 204': (r) =>
              r.status === 200 || r.status === 204,
          });
        }
      } catch (e) {
        console.error('Cart update/delete error:', e);
        errorRate.add(1);
      }
    }

    sleep(1);
  });

  // Clear cart
  const clearRes = http.del(`${BASE_URL}/api/cart`, null, { headers });

  check(clearRes, {
    'clear cart status is 200 or 204': (r) => r.status === 200 || r.status === 204,
  });

  sleep(2);
}
