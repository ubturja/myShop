#!/bin/bash

# Test script for Web3 integration

echo "=== Web3 Integration Tests ==="
echo ""

# Test 1: Check web3-service health
echo "1. Testing web3-service health endpoint..."
HEALTH_RESPONSE=$(curl -s http://localhost:3001/health)
echo "Response: $HEALTH_RESPONSE"
if echo "$HEALTH_RESPONSE" | grep -q "healthy"; then
    echo "✓ Web3 service is healthy"
else
    echo "✗ Web3 service health check failed"
fi
echo ""

# Test 2: Test mock mint endpoint
echo "2. Testing web3-service mint endpoint (mock mode)..."
MINT_RESPONSE=$(curl -s -X POST http://localhost:3001/mint \
    -H "Content-Type: application/json" \
    -d '{"userId":1,"metadataUri":"ipfs://QmTest123"}')
echo "Response: $MINT_RESPONSE"
if echo "$MINT_RESPONSE" | grep -q "success"; then
    echo "✓ Mock mint successful"
else
    echo "✗ Mock mint failed"
fi
echo ""

# Test 3: Test owner lookup
echo "3. Testing web3-service owner lookup..."
OWNER_RESPONSE=$(curl -s http://localhost:3001/ownerOf/12345)
echo "Response: $OWNER_RESPONSE"
if echo "$OWNER_RESPONSE" | grep -q "owner"; then
    echo "✓ Owner lookup successful"
else
    echo "✗ Owner lookup failed"
fi
echo ""

# Test 4: Test anchor endpoint
echo "4. Testing web3-service anchor endpoint..."
ANCHOR_RESPONSE=$(curl -s -X POST http://localhost:3001/anchor \
    -H "Content-Type: application/json" \
    -d '{"sha256":"e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"}')
echo "Response: $ANCHOR_RESPONSE"
if echo "$ANCHOR_RESPONSE" | grep -q "success"; then
    echo "✓ Anchor successful"
else
    echo "✗ Anchor failed"
fi
echo ""

echo "=== All web3-service tests completed ==="
