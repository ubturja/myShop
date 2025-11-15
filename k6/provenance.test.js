import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  vus: 10,
  duration: '2m',
  thresholds: {
    http_req_duration: ['p(95)<700'],
    http_req_failed: ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

function createUser() {
  const payload = JSON.stringify({
    name: `ProvenanceUser ${__VU}_${Date.now()}`,
    email: `provenance${__VU}_${Date.now()}@test.com`,
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

  // Check provenance service health
  const healthRes = http.get(`${BASE_URL}/api/provenance/health`, { headers });

  const healthSuccess = check(healthRes, {
    'provenance health status is 200': (r) => r.status === 200,
  });

  errorRate.add(!healthSuccess);

  sleep(1);

  // Create provenance event
  const productId = Math.floor(Math.random() * 10) + 1;
  const eventTypes = ['MANUFACTURED', 'QUALITY_CHECK', 'SHIPPED', 'DELIVERED'];
  const eventType = eventTypes[Math.floor(Math.random() * eventTypes.length)];

  const eventPayload = JSON.stringify({
    product_id: productId,
    event_type: eventType,
    data: {
      location: 'Factory Floor 3',
      inspector: 'John Doe',
      timestamp: new Date().toISOString(),
    },
    anchor_to_blockchain: false, // Use false for performance testing
  });

  const createEventRes = http.post(
    `${BASE_URL}/api/provenance/events`,
    eventPayload,
    { headers }
  );

  const createSuccess = check(createEventRes, {
    'create event status is 201': (r) => r.status === 201,
    'returns event id': (r) => {
      try {
        return JSON.parse(r.body).id !== undefined;
      } catch {
        return false;
      }
    },
  });

  errorRate.add(!createSuccess);

  sleep(1);

  // Get events for product
  const eventsRes = http.get(
    `${BASE_URL}/api/provenance/events/product/${productId}`,
    { headers }
  );

  check(eventsRes, {
    'get product events status is 200': (r) => r.status === 200,
    'returns events array': (r) => {
      try {
        return Array.isArray(JSON.parse(r.body));
      } catch {
        return false;
      }
    },
  });

  sleep(1);

  // Get provenance stats
  const statsRes = http.get(`${BASE_URL}/api/provenance/stats`, { headers });

  check(statsRes, {
    'get stats status is 200': (r) => r.status === 200,
  });

  sleep(2);
}
