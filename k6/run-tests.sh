#!/bin/bash

# k6 Load Testing Script Runner
# This script runs all k6 performance tests for the myShop API

set -e

BASE_URL="${BASE_URL:-http://localhost:8000}"
RESULTS_DIR="k6/results"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}myShop k6 Performance Tests${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Base URL: $BASE_URL"
echo "Results: $RESULTS_DIR"
echo ""

# Create results directory
mkdir -p "$RESULTS_DIR"

# Array of test files
tests=(
  "auth.test.js"
  "products.test.js"
  "cart.test.js"
  "group-buy.test.js"
  "quick-fulfillment.test.js"
  "gamification.test.js"
  "ai.test.js"
  "web3.test.js"
  "provenance.test.js"
)

# Track results
passed=0
failed=0
skipped=0

# Run each test
for test in "${tests[@]}"; do
  test_name=$(basename "$test" .test.js)
  echo -e "${YELLOW}Running: $test_name${NC}"
  
  if [ ! -f "k6/$test" ]; then
    echo -e "${RED}✗ Test file not found: k6/$test${NC}"
    ((skipped++))
    continue
  fi
  
  # Run k6 test with JSON output
  if k6 run \
    --out json="$RESULTS_DIR/${test_name}.json" \
    --summary-export="$RESULTS_DIR/${test_name}_summary.json" \
    -e BASE_URL="$BASE_URL" \
    "k6/$test" > "$RESULTS_DIR/${test_name}.log" 2>&1; then
    echo -e "${GREEN}✓ $test_name passed${NC}"
    ((passed++))
  else
    echo -e "${RED}✗ $test_name failed${NC}"
    echo "  Check logs: $RESULTS_DIR/${test_name}.log"
    ((failed++))
  fi
  
  echo ""
  sleep 2  # Brief pause between tests
done

# Summary
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Test Results Summary${NC}"
echo -e "${GREEN}================================${NC}"
echo -e "Passed:  ${GREEN}$passed${NC}"
echo -e "Failed:  ${RED}$failed${NC}"
echo -e "Skipped: ${YELLOW}$skipped${NC}"
echo -e "Total:   $((passed + failed + skipped))"
echo ""

# Generate HTML report if k6-reporter is available
if command -v k6-to-junit &> /dev/null; then
  echo "Generating HTML report..."
  # Add report generation here if needed
fi

# Exit with error if any tests failed
if [ $failed -gt 0 ]; then
  echo -e "${RED}Some tests failed!${NC}"
  exit 1
else
  echo -e "${GREEN}All tests passed!${NC}"
  exit 0
fi
