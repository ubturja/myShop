# k6 Load Testing Suite

This directory contains k6 performance tests for all myShop API endpoints.

## Prerequisites

Install k6:
```bash
# macOS
brew install k6

# Linux (Debian/Ubuntu)
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Docker
docker pull grafana/k6
```

## Test Files

- **auth.test.js** - Authentication (register, login, logout)
- **products.test.js** - Product listing, search, filtering
- **cart.test.js** - Shopping cart operations
- **group-buy.test.js** - Group buying features
- **quick-fulfillment.test.js** - Store location and fulfillment
- **gamification.test.js** - Spin wheel and rewards
- **ai.test.js** - AI recommendations and assistant
- **web3.test.js** - NFT minting and blockchain
- **provenance.test.js** - Supply chain tracking

## Running Tests

### Run all tests
```bash
chmod +x k6/run-tests.sh
./k6/run-tests.sh
```

### Run individual test
```bash
k6 run k6/auth.test.js
```

### Run with custom base URL
```bash
BASE_URL=https://api.myshop.com k6 run k6/products.test.js
```

### Run with Docker
```bash
docker run --rm -i --network=host \
  -v $PWD/k6:/k6 \
  grafana/k6 run /k6/auth.test.js
```

## Test Configuration

Each test uses stages to simulate realistic load:
- **Ramp up**: Gradually increase virtual users
- **Sustained load**: Maintain steady traffic
- **Spike**: Sudden increase in users
- **Peak load**: Maximum concurrent users
- **Ramp down**: Gradual decrease

### Performance Thresholds

Default thresholds across tests:
- `http_req_duration`: p(95) < 500ms, p(99) < 1000ms
- `http_req_failed`: < 1% error rate
- `errors`: < 5% custom error rate

Slower endpoints (AI, Web3):
- `http_req_duration`: p(95) < 1000ms

## Test Metrics

k6 automatically tracks:
- **http_req_duration**: Request latency
- **http_req_failed**: Failed requests
- **http_reqs**: Request rate
- **vus**: Virtual users
- **iterations**: Completed test iterations

Custom metrics:
- **errors**: Application-level errors

## Results

Test results are saved to `k6/results/`:
- `*.json`: Full test data
- `*_summary.json`: Test summary
- `*.log`: Test execution log

### View results
```bash
# View summary
cat k6/results/auth_summary.json | jq

# Check errors
grep ERROR k6/results/auth.log
```

## Interpreting Results

Good performance indicators:
- ✓ All checks passing (>95%)
- ✓ p95 response time under threshold
- ✓ Error rate < 1%
- ✓ Throughput meets requirements

Red flags:
- ✗ High error rates (>5%)
- ✗ Response times over 1s
- ✗ Failed thresholds
- ✗ Timeouts or connection errors

## Performance Optimization Tips

If tests fail:

1. **Database**: Add indexes for slow queries
2. **Caching**: Implement Redis for hot data
3. **API**: Optimize N+1 queries
4. **Scaling**: Increase backend resources
5. **CDN**: Cache static assets

## CI Integration

Tests can run in CI/CD pipeline:
```yaml
# See .github/workflows/k6-tests.yml
```

## Advanced Usage

### Custom scenarios
```javascript
export const options = {
  scenarios: {
    smoke: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
    },
    load: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 0 },
      ],
    },
  },
};
```

### Environment variables
```bash
export BASE_URL=http://localhost:8000
export TEST_USER_EMAIL=test@example.com
export TEST_USER_PASSWORD=password123
k6 run k6/auth.test.js
```

## Troubleshooting

### Connection refused
- Ensure backend is running: `docker-compose ps`
- Check BASE_URL is correct
- Verify network connectivity

### High error rates
- Check backend logs: `docker-compose logs backend`
- Verify database is healthy
- Check resource utilization

### Timeouts
- Increase request timeout in test
- Scale backend resources
- Optimize database queries

## Best Practices

1. **Start small**: Run smoke tests first (1 VU)
2. **Gradual increase**: Ramp up load slowly
3. **Monitor**: Watch backend metrics during tests
4. **Baseline**: Establish performance baseline
5. **Compare**: Track metrics over time
6. **Fix bottlenecks**: Address issues incrementally

## Resources

- [k6 Documentation](https://k6.io/docs/)
- [Best Practices](https://k6.io/docs/testing-guides/api-load-testing/)
- [Thresholds Guide](https://k6.io/docs/using-k6/thresholds/)
