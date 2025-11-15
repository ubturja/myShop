import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import authService from '../services/auth';

export default function SellerDashboard() {
  const navigate = useNavigate();

  useEffect(() => {
    if (!authService.isAuthenticated() || !authService.hasRole('SELLER')) {
      navigate('/login/seller');
    }
  }, [navigate]);

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-6">Seller Dashboard</h1>
        <div className="bg-white rounded-lg shadow p-6">
          <p className="text-gray-600">Welcome to your seller portal!</p>
          <p className="text-sm text-gray-500 mt-2">Dashboard coming soon in PR3...</p>
        </div>
      </div>
    </div>
  );
}
