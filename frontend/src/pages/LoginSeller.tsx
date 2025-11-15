import { useState, FormEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import authService from '../services/auth';

export default function LoginSeller() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await authService.login({ email, password, role: 'SELLER' });
      window.location.href = '/seller/dashboard';
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Login failed';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Seller Portal
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Sign in to manage your products and orders
          </p>
        </div>
        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          {error && (
            <div className="rounded-md bg-red-50 p-4">
              <p className="text-sm text-red-800">{error}</p>
            </div>
          )}
          <div className="rounded-md shadow-sm -space-y-px">
            <div>
              <label htmlFor="email" className="sr-only">
                Email address
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm"
                placeholder="Email address"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>
            <div>
              <label htmlFor="password" className="sr-only">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm"
                placeholder="Password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>
          </div>

          <div>
            <button
              type="submit"
              disabled={loading}
              className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
            >
              {loading ? 'Signing in...' : 'Sign in to Seller Portal'}
            </button>
          </div>

          <div className="text-center space-y-2">
            <p className="text-sm text-gray-600">
              Want to start selling?{' '}
              <Link to="/register/seller" className="font-medium text-green-600 hover:text-green-500">
                Register as Seller
              </Link>
            </p>
            <div className="border-t pt-4">
              <p className="text-sm text-gray-600">Looking for:</p>
              <div className="mt-2 space-x-4">
                <Link to="/login/customer" className="text-sm text-indigo-600 hover:text-indigo-500">
                  Customer Login
                </Link>
                <span className="text-gray-300">|</span>
                <Link to="/login/admin" className="text-sm text-indigo-600 hover:text-indigo-500">
                  Admin Login
                </Link>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
