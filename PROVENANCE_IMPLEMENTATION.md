# Provenance System Implementation Summary

## Overview
Complete implementation of supply chain provenance tracking with IPFS storage (Pinata API) and optional blockchain anchoring. The system records product lifecycle events with immutable metadata stored on IPFS and SHA-256 hashes optionally anchored to Polygon blockchain.

## Components Created

### 1. ProvenanceService (`app/Services/ProvenanceService.php`)
**Purpose**: Interface to Pinata API for IPFS operations

**Key Methods**:
- `uploadJson($data, $name)` - Upload JSON metadata to IPFS via Pinata
- `computeHash($data)` - Generate SHA-256 hash for blockchain anchoring
- `unpin($ipfsHash)` - Remove content from Pinata (cleanup)
- `listPins($limit)` - Get list of pinned files
- `testConnection()` - Verify Pinata API connectivity
- `getGatewayUrl($ipfsHash)` - Get public IPFS gateway URL
- `isMockMode()` - Check if running in mock mode

**Features**:
- **Mock Mode**: Deterministic IPFS hashes for development without API keys
- **Dual Authentication**: Supports JWT token or API key/secret
- **Error Handling**: Comprehensive logging and exception handling
- **Consistent Hashing**: JSON normalization for deterministic SHA-256
- **Configurable Gateway**: Custom IPFS gateway support

**Configuration** (`config/services.php`):
```php
'pinata' => [
    'api_key' => env('PINATA_API_KEY'),
    'api_secret' => env('PINATA_API_SECRET'),
    'jwt' => env('PINATA_JWT'),
    'gateway' => env('PINATA_GATEWAY', 'gateway.pinata.cloud'),
    'mock_mode' => env('PINATA_MOCK_MODE', true),
    'timeout' => env('PINATA_TIMEOUT', 30),
]
```

### 2. ProvenanceController (`app/Http/Controllers/ProvenanceController.php`)
**Purpose**: RESTful API endpoints for provenance tracking

**Endpoints**:

#### POST `/api/provenance/events`
Create provenance event with IPFS upload and optional blockchain anchoring

```json
Request:
{
  "product_id": 1,
  "event_type": "MANUFACTURED",
  "data": {
    "location": "Factory A, Colombia",
    "inspector": "John Doe",
    "timestamp": "2024-01-01T10:30:00Z",
    "notes": "Quality check passed"
  },
  "anchor": true  // Optional: anchor to blockchain
}

Response (201):
{
  "success": true,
  "event": {
    "id": 1,
    "product_id": 1,
    "event_type": "MANUFACTURED",
    "data": {...},
    "ipfs_hash": "QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG",
    "ipfs_url": "https://gateway.pinata.cloud/ipfs/QmYwAP...",
    "sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
    "tx_hash": "0xabc...",  // If anchored
    "polygonscan_url": "https://mumbai.polygonscan.com/tx/0xabc...",
    "created_at": "2024-01-01T00:00:00Z"
  },
  "anchored": true,
  "mock_mode": true
}
```

**Validation**:
- `product_id` - Required, must exist in products table
- `event_type` - Required, max 100 chars (e.g., MANUFACTURED, SHIPPED, DELIVERED)
- `data` - Required, JSON object with event-specific details
- `anchor` - Optional boolean (default: false)

**Process Flow**:
1. Validate request
2. Prepare metadata with timestamp
3. Upload metadata to IPFS via Pinata
4. Compute SHA-256 hash
5. Optionally anchor hash to blockchain (non-blocking on failure)
6. Save event to database
7. Return complete event details

#### GET `/api/provenance/events/{id}`
Get provenance event details

```json
Response:
{
  "success": true,
  "event": {
    "id": 1,
    "product_id": 1,
    "product": {
      "id": 1,
      "title": "Colombian Coffee Beans",
      "sku": "COFFEE-001"
    },
    "event_type": "MANUFACTURED",
    "data": {...},
    "ipfs_hash": "QmYwAP...",
    "ipfs_url": "https://gateway.pinata.cloud/ipfs/QmYwAP...",
    "tx_hash": "0xabc...",
    "polygonscan_url": "https://mumbai.polygonscan.com/tx/0xabc...",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```

#### GET `/api/products/{productId}/provenance`
Get complete provenance chain for a product (chronological order)

```json
Response:
{
  "success": true,
  "product": {
    "id": 1,
    "title": "Colombian Coffee Beans",
    "sku": "COFFEE-001"
  },
  "events": [
    {
      "id": 1,
      "event_type": "MANUFACTURED",
      "data": {...},
      "ipfs_hash": "QmYwAP...",
      "ipfs_url": "https://gateway.pinata.cloud/ipfs/QmYwAP...",
      "tx_hash": "0xabc...",
      "polygonscan_url": "https://mumbai.polygonscan.com/tx/0xabc...",
      "created_at": "2024-01-01T00:00:00Z"
    },
    {
      "id": 2,
      "event_type": "QUALITY_CHECK",
      "data": {...},
      "ipfs_hash": "QmXyz...",
      "ipfs_url": "https://gateway.pinata.cloud/ipfs/QmXyz...",
      "tx_hash": "0xdef...",
      "polygonscan_url": "https://mumbai.polygonscan.com/tx/0xdef...",
      "created_at": "2024-01-02T00:00:00Z"
    },
    // ... ordered chronologically (supply chain sequence)
  ],
  "count": 8
}
```

#### GET `/api/provenance/stats`
Get provenance system statistics

```json
Response:
{
  "success": true,
  "stats": {
    "total_events": 150,
    "anchored_events": 45,
    "anchored_percentage": 30.0,
    "events_by_type": {
      "MANUFACTURED": 25,
      "QUALITY_CHECK": 20,
      "SHIPPED": 30,
      "DELIVERED": 25,
      "CUSTOMS": 15,
      "PACKAGED": 20,
      "IN_TRANSIT": 15
    }
  },
  "mock_mode": true
}
```

#### GET `/api/provenance/health`
Check IPFS/Pinata service health

```json
Response:
{
  "status": "ok",
  "provenance_service": {
    "status": "ok",
    "mock_mode": true,
    "message": "Mock mode enabled"
  }
}
```

### 3. ProvenanceEvent Model (`app/Models/ProvenanceEvent.php`)
**Attributes**:
- `product_id` - Foreign key to products table
- `event_type` - Event classification (MANUFACTURED, SHIPPED, etc.)
- `data` - JSON event-specific data
- `ipfs_hash` - IPFS CID (Content Identifier)
- `tx_hash` - Blockchain transaction hash (nullable)

**Relationships**:
- `product()` - Belongs to Product

**Accessors**:
- `ipfs_url` - Full IPFS gateway URL
- `polygonscan_url` - Blockchain explorer URL (Mumbai testnet)

### 4. ProvenanceEventFactory (`database/factories/ProvenanceEventFactory.php`)
Generates realistic test data with:
- Random event types (MANUFACTURED, SHIPPED, DELIVERED, etc.)
- Mock IPFS hashes
- Optional blockchain anchoring (30% by default)
- Location and timestamp data

**States**:
- `notAnchored()` - Event without blockchain anchoring
- `anchored()` - Event with blockchain transaction
- `forProduct($product)` - Event for specific product
- `eventType($type)` - Specific event type

## Database Schema

### `provenance_events` table
```php
Schema::create('provenance_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->onDelete('cascade');
    $table->string('event_type');           // Event classification
    $table->json('data');                   // Event-specific data
    $table->string('ipfs_hash')->nullable(); // IPFS CID
    $table->string('tx_hash')->nullable();  // Blockchain tx hash
    $table->timestamps();
    
    $table->index('product_id');
    $table->index('event_type');
    $table->index('ipfs_hash');
});
```

## Routes (`routes/api.php`)
All routes require `auth:sanctum` authentication:
```php
Route::get('/provenance/health', [ProvenanceController::class, 'health']);
Route::post('/provenance/events', [ProvenanceController::class, 'store']);
Route::get('/provenance/events/{id}', [ProvenanceController::class, 'show']);
Route::get('/provenance/stats', [ProvenanceController::class, 'stats']);
Route::get('/products/{productId}/provenance', [ProvenanceController::class, 'getByProduct']);
```

## Test Coverage

### Unit Tests (`tests/Unit/ProvenanceServiceTest.php`)
**21 tests, 53 assertions** - All passing ✅

Tests cover:
- Mock mode detection
- Mock JSON upload (deterministic, different data produces different hashes)
- Real API upload (success, error handling, response validation)
- SHA-256 computation (deterministic, changes with data)
- Unpinning (success and error cases)
- Pin listing
- Connection testing
- Gateway URL formatting
- Authentication headers (JWT vs API key)

**Mock Strategy**: Uses `Http::fake()` to simulate Pinata API responses

### Feature Tests (`tests/Feature/ProvenanceControllerTest.php`)
**17 tests, 57 assertions** - All passing ✅

Tests cover:
- Health endpoint (service status and errors)
- Event creation:
  - Without blockchain anchoring
  - With blockchain anchoring
  - Continues on anchor failure (graceful degradation)
  - Authentication requirement
  - Validation (required fields, product exists, data is array)
  - Rollback on IPFS error
- Show endpoint (success and 404)
- Get by product:
  - Returns events chronologically
  - 404 for nonexistent product
  - Empty array when no events
- Stats endpoint with aggregated data
- Authentication on all endpoints

**Mock Strategy**: Uses `Mockery` to mock ProvenanceService and Web3Client

## Error Handling

### ProvenanceService Errors
- **IPFS Upload Failure**: Exception with Pinata error message
- **Invalid Response**: Exception when IpfsHash missing
- **Connection Timeout**: Configurable timeout (30s default)
- **Authentication Failure**: Logged with context

### Controller Error Responses
- **422 Unprocessable Entity**: Validation errors
- **404 Not Found**: Product/event not found
- **500 Internal Server Error**: IPFS upload or system failures (with rollback)
- **503 Service Unavailable**: Pinata service unreachable

### Graceful Degradation
- **Blockchain Anchoring**: If anchoring fails, event is still saved without tx_hash
- **IPFS Errors**: Transaction rolled back to prevent partial data

## Usage Examples

### Creating a Provenance Event (cURL)
```bash
# Without blockchain anchoring
curl -X POST http://localhost:8000/api/provenance/events \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "event_type": "MANUFACTURED",
    "data": {
      "location": "Factory A, Colombia",
      "inspector": "John Doe",
      "batch": "BATCH-2024-001"
    }
  }'

# With blockchain anchoring
curl -X POST http://localhost:8000/api/provenance/events \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "event_type": "SHIPPED",
    "data": {
      "carrier": "DHL",
      "tracking": "DHL123456789",
      "destination": "New York, USA"
    },
    "anchor": true
  }'
```

### Getting Product Provenance Chain (cURL)
```bash
curl http://localhost:8000/api/products/1/provenance \
  -H "Authorization: Bearer $TOKEN"
```

### PHP/Laravel Usage
```php
use App\Services\ProvenanceService;
use App\Services\Web3Client;
use App\Models\ProvenanceEvent;

// Upload to IPFS
$provenance = app(ProvenanceService::class);
$result = $provenance->uploadJson([
    'product_id' => 1,
    'event_type' => 'MANUFACTURED',
    'data' => ['location' => 'Factory A'],
    'timestamp' => now()->toIso8601String(),
], 'provenance-event-001');

// Get IPFS hash and SHA-256
$ipfsHash = $result['ipfs_hash'];
$sha256 = $result['sha256'];

// Optionally anchor to blockchain
$web3 = app(Web3Client::class);
$anchor = $web3->anchorHash($sha256);
$txHash = $anchor['txHash'];

// Save to database
$event = ProvenanceEvent::create([
    'product_id' => 1,
    'event_type' => 'MANUFACTURED',
    'data' => ['location' => 'Factory A'],
    'ipfs_hash' => $ipfsHash,
    'tx_hash' => $txHash,
]);

// Get provenance chain
$chain = ProvenanceEvent::where('product_id', 1)
    ->orderBy('created_at', 'asc')
    ->get();
```

## Test Execution

### Run all Provenance tests
```bash
docker compose exec backend php artisan test --filter="Provenance"
```

### Run unit tests only
```bash
docker compose exec backend php artisan test tests/Unit/ProvenanceServiceTest.php
```

### Run feature tests only
```bash
docker compose exec backend php artisan test tests/Feature/ProvenanceControllerTest.php
```

## Configuration Requirements

### Environment Variables
```env
# Pinata IPFS Configuration
PINATA_API_KEY=your_api_key_here
PINATA_API_SECRET=your_api_secret_here
PINATA_JWT=your_jwt_token_here          # Optional, preferred over API key
PINATA_GATEWAY=gateway.pinata.cloud
PINATA_MOCK_MODE=true                    # Set to false for production
PINATA_TIMEOUT=30

# Web3 Configuration (for anchoring)
WEB3_SERVICE_URL=http://web3-service:3001
WEB3_MOCK_MODE=true
WEB3_CONTRACT_ADDRESS=0x...
```

## Mock Mode vs Production

### Mock Mode (Development)
- Set `PINATA_MOCK_MODE=true`
- Returns deterministic IPFS hashes based on SHA-256
- No API calls to Pinata
- Instant responses
- Used in tests
- IPFS hash format: `Qm` + base64 of SHA-256

### Production Mode
- Set `PINATA_MOCK_MODE=false`
- Requires valid Pinata API credentials
- Real IPFS uploads with pinning
- Costs apply based on Pinata plan
- Network latency considerations
- Public IPFS gateway access

## Common Event Types

Recommended event types for supply chain tracking:
- `MANUFACTURED` - Product created/assembled
- `QUALITY_CHECK` - Quality inspection performed
- `PACKAGED` - Product packaged for shipment
- `SHIPPED` - Handed to carrier
- `IN_TRANSIT` - En route to destination
- `CUSTOMS` - Customs clearance
- `OUT_FOR_DELIVERY` - Final mile delivery
- `DELIVERED` - Received by customer
- `RETURNED` - Product returned
- `RECYCLED` - End of life processing

## IPFS Best Practices

1. **Metadata Structure**: Include all relevant details in `data` field
2. **Naming Convention**: Use descriptive pin names: `provenance-{product_id}-{event_type}-{timestamp}`
3. **Pin Management**: Unpin old events if storage limits reached
4. **Gateway Selection**: Use Pinata dedicated gateway for better performance
5. **Caching**: Consider CDN caching for frequently accessed metadata

## Blockchain Anchoring Strategy

1. **Selective Anchoring**: Not all events need blockchain anchoring
2. **Critical Events**: Anchor MANUFACTURED, QUALITY_CHECK, DELIVERED
3. **Batch Anchoring**: For high-volume products, batch anchor daily
4. **Gas Optimization**: Monitor Polygon gas prices
5. **Verification**: Store tx_hash for future verification

## Integration Points

### With Product Catalog
- Track full lifecycle of each product
- Display provenance chain on product pages
- QR codes linking to provenance timeline

### With Web3Client
- Optional blockchain anchoring via `Web3Client::anchorHash()`
- Immutable proof of event occurrence
- Public verification via Polygonscan

### With AI/ML
- Analyze provenance patterns for fraud detection
- Predict shipping delays based on historical data
- Quality correlation analysis

## Files Created/Modified

**Created**:
- `backend/app/Services/ProvenanceService.php` (310 lines)
- `backend/app/Http/Controllers/ProvenanceController.php` (330 lines)
- `backend/tests/Unit/ProvenanceServiceTest.php` (350 lines, 21 tests)
- `backend/tests/Feature/ProvenanceControllerTest.php` (480 lines, 17 tests)
- `backend/database/factories/ProvenanceEventFactory.php` (70 lines)
- This documentation file

**Modified**:
- `backend/config/services.php` - Added Pinata configuration
- `backend/routes/api.php` - Added 5 provenance endpoints

## Test Results Summary
✅ **38 tests passing** (110 assertions)
- 21 unit tests (ProvenanceService)
- 17 feature tests (ProvenanceController)
- 0 failures
- Duration: ~10 seconds

## Next Steps (Future Enhancements)

1. **Advanced Features**:
   - Batch event creation endpoint
   - Event update/amendment with audit trail
   - File attachments (images, PDFs) via IPFS
   - Multi-signature verification for critical events

2. **Performance**:
   - Background job queue for IPFS uploads
   - Pinata dedicated gateway for faster retrieval
   - Event caching with Redis
   - Pagination for large provenance chains

3. **Security**:
   - Role-based event creation (only authorized users)
   - Digital signatures for event authenticity
   - Webhook verification for Pinata callbacks
   - Rate limiting on event creation

4. **User Experience**:
   - Public provenance viewer (no auth)
   - Timeline visualization component
   - QR code generation for products
   - Export provenance as PDF report

5. **Monitoring**:
   - Pinata usage dashboard
   - Failed anchor retry mechanism
   - IPFS pin health check
   - Event creation analytics

## Conclusion

Complete provenance tracking system implemented with:
- ✅ Robust ProvenanceService with Pinata integration
- ✅ RESTful API endpoints
- ✅ Comprehensive test coverage (38 tests)
- ✅ Mock mode for development
- ✅ Optional blockchain anchoring
- ✅ Database persistence
- ✅ Error handling and rollback
- ✅ Documentation and examples

The system provides **immutable supply chain tracking** with IPFS storage and optional blockchain verification, enabling:
- Full product lifecycle transparency
- Tamper-proof event records
- Public verifiability
- Regulatory compliance
- Consumer trust

Production-ready for mock mode; requires Pinata API credentials for real IPFS operations.
