import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '30s', target: 50 },   // Ramp up
    { duration: '2m', target: 50 },    // Sustained load
    { duration: '30s', target: 100 },  // Spike
    { duration: '1m', target: 100 },   // Peak load
    { duration: '30s', target: 0 },    // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<300', 'p(99)<800'],
    http_req_failed: ['rate<0.01'],
    errors: ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
  // Test: List Products (public endpoint)
  const listRes = http.get(`${BASE_URL}/api/products`);

  const listSuccess = check(listRes, {
    'list products status is 200': (r) => r.status === 200,
    'list returns data array': (r) => {
      try {
        const body = JSON.parse(r.body);
        return Array.isArray(body.data);
      } catch {
        return false;
      }
    },
  });

  errorRate.add(!listSuccess);

  sleep(1);

  // Test: Search Products
  const searchRes = http.get(`${BASE_URL}/api/products?search=shirt`);

  check(searchRes, {
    'search products status is 200': (r) => r.status === 200,
  });

  sleep(1);

  // Test: Get Single Product
  // Assuming product IDs exist from seeded data
  const productId = Math.floor(Math.random() * 10) + 1;
  const productRes = http.get(`${BASE_URL}/api/products/${productId}`);

  const productSuccess = check(productRes, {
    'get product status is 200 or 404': (r) => r.status === 200 || r.status === 404,
    'product has expected structure': (r) => {
      if (r.status === 200) {
        try {
          const product = JSON.parse(r.body);
          return product.id && product.name && product.price;
        } catch {
          return false;
        }
      }
      return true; // 404 is acceptable
    },
  });

  errorRate.add(!productSuccess && productRes.status !== 404);

  sleep(2);

  // Test: Filter by Category
  const categoryRes = http.get(`${BASE_URL}/api/products?category=shirts`);

  check(categoryRes, {
    'filter by category status is 200': (r) => r.status === 200,
  });

  sleep(1);

  // Test: Sort Products
  const sortRes = http.get(`${BASE_URL}/api/products?sort=price&order=desc`);

  check(sortRes, {
    'sort products status is 200': (r) => r.status === 200,
  });

  sleep(1);
}
