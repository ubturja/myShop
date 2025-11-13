import React, { useState } from 'react';
import { useWeb3 } from '../../contexts/Web3Context';
import { toast } from 'react-toastify';
import api from '../../services/api';

const Web3LoginButton: React.FC = () => {
  const { isConnected, account, connectWallet, signMessage } = useWeb3();
  const [isLoading, setIsLoading] = useState(false);

  const handleWeb3Login = async () => {
    if (!isConnected) {
      try {
        await connectWallet();
      } catch (error) {
        console.error('Failed to connect wallet:', error);
        toast.error('Failed to connect wallet. Please make sure MetaMask is installed.');
      }
      return;
    }

    if (!account) {
      toast.error('No wallet account found');
      return;
    }

    setIsLoading(true);

    try {
      // Create a message to sign
      const message = `Welcome to MyShop!\n\nSign this message to login with your wallet.\n\nNonce: ${Math.random().toString(36).substring(2, 15)}`;
      
      // Sign the message with the wallet
      const signature = await signMessage(message);
      
      // Send to backend for verification
      const response = await api.post('/auth/web3/login', {
        walletAddress: account,
        signature,
        message,
      });

      // Save token and user data
      const { access_token, user } = response.data;
      localStorage.setItem('auth_token', access_token);
      localStorage.setItem('user', JSON.stringify(user));

      toast.success('Wallet connected successfully!');
      window.location.reload(); // Refresh to update auth state
    } catch (error: any) {
      console.error('Web3 login failed:', error);
      const errorMessage = error.response?.data?.message || 'Failed to login with wallet. Please try again.';
      toast.error(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  const truncateAddress = (address: string) => {
    return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
  };

  return (
    <button
      onClick={handleWeb3Login}
      disabled={isLoading}
      className={`flex items-center justify-center w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${
        isLoading ? 'opacity-50 cursor-not-allowed' : ''
      }`}
    >
      {isLoading ? (
        <span>Connecting...</span>
      ) : isConnected && account ? (
        <span className="flex items-center">
          <span className="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
          {truncateAddress(account)}
        </span>
      ) : (
        <span className="flex items-center">
          <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 1.5C6.20101 1.5 1.5 6.20101 1.5 12C1.5 17.799 6.20101 22.5 12 22.5C17.799 22.5 22.5 17.799 22.5 12C22.5 6.20101 17.799 1.5 12 1.5Z" fill="#E2761B" stroke="#E2761B" strokeWidth="0.5" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M17.5 10.5V8.5C17.5 8.10218 17.342 7.72064 17.0607 7.43934C16.7794 7.15804 16.3978 7 16 7H8C7.60218 7 7.22064 7.15804 6.93934 7.43934C6.65804 7.72064 6.5 8.10218 6.5 8.5V10.5" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M5.5 10.5H18.5V16.5C18.5 16.8978 18.342 17.2794 18.0607 17.5607C17.7794 17.842 17.3978 18 17 18H7C6.60218 18 6.22064 17.842 5.93934 17.5607C5.65804 17.2794 5.5 16.8978 5.5 16.5V10.5Z" fill="#E4761B" stroke="#E4761B" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M8.5 12.5H6.5" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M17.5 12.5H15.5" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M9.5 15.5H14.5" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
          Connect Wallet
        </span>
      )}
    </button>
  );
};

export default Web3LoginButton;
