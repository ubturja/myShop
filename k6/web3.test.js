import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';
import { randomString } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

const errorRate = new Rate('errors');

export const options = {
  vus: 10,
  duration: '2m',
  thresholds: {
    http_req_duration: ['p(95)<800'],
    http_req_failed: ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

function createUser() {
  const payload = JSON.stringify({
    name: `Web3User ${__VU}_${Date.now()}`,
    email: `web3user${__VU}_${Date.now()}@test.com`,
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

  // Check Web3 service health
  const healthRes = http.get(`${BASE_URL}/api/web3/health`, { headers });

  const healthSuccess = check(healthRes, {
    'web3 health status is 200': (r) => r.status === 200,
  });

  errorRate.add(!healthSuccess);

  sleep(1);

  // Mint NFT
  const mintPayload = JSON.stringify({
    to_address: `0x${randomString(40)}`,
    token_type: 'LOYALTY',
    metadata_uri: `ipfs://Qm${randomString(44)}`,
  });

  const mintRes = http.post(`${BASE_URL}/api/web3/nft/mint`, mintPayload, {
    headers,
  });

  const mintSuccess = check(mintRes, {
    'mint NFT status is 200 or 201': (r) => r.status === 200 || r.status === 201,
    'returns token data': (r) => {
      if (r.status === 200 || r.status === 201) {
        try {
          const body = JSON.parse(r.body);
          return body.token_id !== undefined;
        } catch {
          return false;
        }
      }
      return true;
    },
  });

  errorRate.add(!mintSuccess);

  sleep(2);

  // Get user's NFT tokens
  const tokensRes = http.get(`${BASE_URL}/api/web3/nft/my-tokens`, {
    headers,
  });

  check(tokensRes, {
    'get my tokens status is 200': (r) => r.status === 200,
    'returns tokens array': (r) => {
      try {
        return Array.isArray(JSON.parse(r.body));
      } catch {
        return false;
      }
    },
  });

  sleep(2);
}
