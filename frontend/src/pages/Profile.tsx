import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import authService from '../services/auth';

export default function Profile() {
  const navigate = useNavigate();
  const [user, setUser] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!authService.isAuthenticated()) {
      navigate('/login');
      return;
    }

    const userData = authService.getUser();
    setUser(userData);
    setLoading(false);
  }, [navigate]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-neon-cyan font-rajdhani text-xl">Loading...</div>
      </div>
    );
  }

  if (!user) {
    return null;
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <h1 className="text-4xl font-orbitron font-bold text-neon-pink mb-8">My Profile</h1>

      <div className="bg-bg-dark-2 border-2 border-neon-cyan rounded-sm p-6 shadow-neon-cyan">
        <div className="flex items-center space-x-4 mb-6">
          <div className="w-20 h-20 bg-gradient-to-br from-neon-purple to-neon-cyan rounded-full flex items-center justify-center">
            <span className="text-white font-orbitron font-bold text-3xl">
              {user.name?.charAt(0).toUpperCase() || 'U'}
            </span>
          </div>
          <div>
            <h2 className="text-2xl font-orbitron font-bold text-neon-cyan">{user.name}</h2>
            <p className="text-neon-purple font-rajdhani">{user.email}</p>
            <span className="inline-block px-3 py-1 bg-neon-cyan/20 text-neon-cyan text-xs font-tech uppercase tracking-wider mt-2 border border-neon-cyan">
              {user.role}
            </span>
          </div>
        </div>

        <div className="border-t-2 border-neon-cyan/30 pt-6 space-y-4">
          <h3 className="text-xl font-orbitron font-bold text-neon-pink mb-4">Account Details</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="bg-bg-dark-1 p-4 border border-neon-cyan/30 rounded-sm">
              <p className="text-sm font-tech text-neon-purple uppercase">Email</p>
              <p className="text-neon-cyan font-rajdhani font-semibold">{user.email}</p>
            </div>
            
            <div className="bg-bg-dark-1 p-4 border border-neon-cyan/30 rounded-sm">
              <p className="text-sm font-tech text-neon-purple uppercase">Role</p>
              <p className="text-neon-cyan font-rajdhani font-semibold">{user.role}</p>
            </div>
            
            <div className="bg-bg-dark-1 p-4 border border-neon-cyan/30 rounded-sm">
              <p className="text-sm font-tech text-neon-purple uppercase">Member Since</p>
              <p className="text-neon-cyan font-rajdhani font-semibold">
                {new Date(user.created_at).toLocaleDateString()}
              </p>
            </div>
            
            <div className="bg-bg-dark-1 p-4 border border-neon-cyan/30 rounded-sm">
              <p className="text-sm font-tech text-neon-purple uppercase">Email Verified</p>
              <p className="text-neon-cyan font-rajdhani font-semibold">
                {user.email_verified_at ? '✓ Verified' : '✗ Not Verified'}
              </p>
            </div>
          </div>

          {user.profile && Object.keys(user.profile).length > 0 && (
            <div className="mt-6">
              <h3 className="text-xl font-orbitron font-bold text-neon-pink mb-4">Additional Information</h3>
              <div className="bg-bg-dark-1 p-4 border border-neon-cyan/30 rounded-sm">
                <pre className="text-neon-cyan font-rajdhani text-sm overflow-auto">
                  {JSON.stringify(user.profile, null, 2)}
                </pre>
              </div>
            </div>
          )}

          {user.seller && (
            <div className="mt-6">
              <h3 className="text-xl font-orbitron font-bold text-neon-pink mb-4">Seller Information</h3>
              <div className="bg-bg-dark-1 p-4 border border-neon-cyan/30 rounded-sm space-y-2">
                <p className="text-neon-cyan font-rajdhani">
                  <span className="text-neon-purple font-tech">Store:</span> {user.seller.store_name}
                </p>
                <p className="text-neon-cyan font-rajdhani">
                  <span className="text-neon-purple font-tech">Verified:</span>{' '}
                  {user.seller.verified ? '✓ Yes' : '✗ No'}
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
