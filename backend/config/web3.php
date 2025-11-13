<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web3 Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Web3 wallet connections
    | and blockchain interactions.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Blockchain Network
    |--------------------------------------------------------------------------
    |
    | This value determines the default blockchain network your application
    | will interact with. Supported networks: 'ethereum', 'polygon', 'bsc'
    |
    */
    'default_network' => env('WEB3_DEFAULT_NETWORK', 'ethereum'),

    /*
    |--------------------------------------------------------------------------
    | Supported Networks
    |--------------------------------------------------------------------------
    |
    | List of supported blockchain networks with their RPC endpoints and chain IDs
    |
    */
    'networks' => [
        'ethereum' => [
            'name' => 'Ethereum Mainnet',
            'rpc_url' => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/'.env('INFURA_PROJECT_ID')),
            'chain_id' => 1,
            'block_explorer' => 'https://etherscan.io',
            'native_currency' => [
                'name' => 'Ether',
                'symbol' => 'ETH',
                'decimals' => 18,
            ],
        ],
        'polygon' => [
            'name' => 'Polygon Mainnet',
            'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
            'chain_id' => 137,
            'block_explorer' => 'https://polygonscan.com',
            'native_currency' => [
                'name' => 'Matic',
                'symbol' => 'MATIC',
                'decimals' => 18,
            ],
        ],
        'bsc' => [
            'name' => 'Binance Smart Chain',
            'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
            'chain_id' => 56,
            'block_explorer' => 'https://bscscan.com',
            'native_currency' => [
                'name' => 'Binance Coin',
                'symbol' => 'BNB',
                'decimals' => 18,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Connect
    |--------------------------------------------------------------------------
    |
    | Configuration for Wallet Connect integration
    |
    */
    'wallet_connect' => [
        'project_id' => env('WALLET_CONNECT_PROJECT_ID'),
        'relay_url' => env('WALLET_CONNECT_RELAY_URL', 'wss://relay.walletconnect.org'),
        'metadata' => [
            'name' => env('APP_NAME', 'MyShop'),
            'description' => env('APP_DESCRIPTION', 'E-commerce with Web3 integration'),
            'url' => env('APP_URL'),
            'icons' => [
                env('APP_URL').'/images/wallet-connect-icon.png',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contract Addresses
    |--------------------------------------------------------------------------
    |
    | Smart contract addresses for different networks
    |
    */
    'contracts' => [
        'loyalty_token' => [
            'ethereum' => env('ETHEREUM_LOYALTY_TOKEN_ADDRESS'),
            'polygon' => env('POLYGON_LOYALTY_TOKEN_ADDRESS'),
            'bsc' => env('BSC_LOYALTY_TOKEN_ADDRESS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Signing
    |--------------------------------------------------------------------------
    |
    | Configuration for message signing and verification
    |
    */
    'message_prefix' => "\x19Ethereum Signed Message:\n",
    'message_expiry' => env('WEB3_MESSAGE_EXPIRY', 300), // 5 minutes in seconds
];
