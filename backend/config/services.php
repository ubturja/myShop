<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAI API integration.
    | Used for AI-powered features like embeddings and chat completions.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'mock_mode' => env('OPENAI_MOCK_MODE', true),
        'models' => [
            'embedding' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'chat' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pinecone Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Pinecone vector database.
    | Used for semantic product search and recommendations.
    |
    */

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
        'index' => env('PINECONE_INDEX', 'myshop-products'),
        'namespace' => env('PINECONE_NAMESPACE', 'products'),
        'mock_mode' => env('PINECONE_MOCK_MODE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web3 Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for web3-service integration.
    | Used for blockchain operations like NFT minting and provenance anchoring.
    |
    */

    'web3' => [
        'url' => env('WEB3_SERVICE_URL', 'http://web3-service:3001'),
        'mock' => env('WEB3_MOCK_MODE', true),
        'timeout' => env('WEB3_TIMEOUT', 10),
        'contract_address' => env('WEB3_CONTRACT_ADDRESS', '0x0000000000000000000000000000000000000000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pinata Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Pinata IPFS service.
    | Used for uploading and pinning provenance event metadata to IPFS.
    |
    */

    'pinata' => [
        'api_key' => env('PINATA_API_KEY'),
        'api_secret' => env('PINATA_API_SECRET'),
        'jwt' => env('PINATA_JWT'),
        'gateway' => env('PINATA_GATEWAY', 'gateway.pinata.cloud'),
        'mock_mode' => env('PINATA_MOCK_MODE', true),
        'timeout' => env('PINATA_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapbox Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Mapbox API integration.
    | Used for accurate ETA calculations and routing for quick delivery.
    |
    */

    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN'),
        'mock_mode' => env('MAPBOX_MOCK_MODE', true),
        'timeout' => env('MAPBOX_TIMEOUT', 10),
        'profile' => env('MAPBOX_PROFILE', 'driving'), // driving, cycling, walking
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Stripe payment processing.
    | Used for payment intents, confirmations, refunds, and webhooks.
    |
    */

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'mock' => env('STRIPE_MOCK', true),
    ],

];
