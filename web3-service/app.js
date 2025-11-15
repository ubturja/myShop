// TODO: Implement Express server with web3 endpoints
// Endpoints:
// - POST /mint - Mint loyalty NFT
// - GET /ownerOf/:tokenId - Get NFT owner
// - POST /anchor - Anchor hash to blockchain
// - GET /health - Health check

import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3001;
const WEB3_MOCK = process.env.WEB3_MOCK === 'true';

app.use(cors());
app.use(express.json());

// Health check
app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    service: 'myShop Web3 Service',
    mock_mode: WEB3_MOCK,
    timestamp: new Date().toISOString(),
  });
});

// TODO: Implement mint endpoint
app.post('/mint', async (req, res) => {
  // Mock response for now
  if (WEB3_MOCK) {
    return res.json({
      success: true,
      txHash: '0x' + '0'.repeat(64),
      tokenId: Math.floor(Math.random() * 1000000).toString(),
      message: 'Mock mint successful',
    });
  }
  
  res.status(501).json({ error: 'Not implemented yet' });
});

// TODO: Implement ownerOf endpoint
app.get('/ownerOf/:tokenId', async (req, res) => {
  if (WEB3_MOCK) {
    return res.json({
      tokenId: req.params.tokenId,
      owner: '0x' + '0'.repeat(40),
      message: 'Mock owner lookup',
    });
  }
  
  res.status(501).json({ error: 'Not implemented yet' });
});

// TODO: Implement anchor endpoint
app.post('/anchor', async (req, res) => {
  if (WEB3_MOCK) {
    return res.json({
      success: true,
      txHash: '0x' + '0'.repeat(64),
      hash: req.body.sha256,
      message: 'Mock anchor successful',
    });
  }
  
  res.status(501).json({ error: 'Not implemented yet' });
});

app.listen(PORT, () => {
  console.log(`🚀 Web3 service running on port ${PORT}`);
  console.log(`📋 Mock mode: ${WEB3_MOCK ? 'ENABLED' : 'DISABLED'}`);
});
