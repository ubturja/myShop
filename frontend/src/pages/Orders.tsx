import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import authService from '../services/auth';
import api from '../services/api';

export default function Orders() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!authService.isAuthenticated()) {
      navigate('/login');
      return;
    }

    fetchOrders();
  }, [navigate]);

  const fetchOrders = async () => {
    try {
      setLoading(true);
      const data = await api.getOrders();
      setOrders(data);
    } catch (err: any) {
      console.error('Error fetching orders:', err);
      setError('Failed to load orders');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-neon-cyan font-rajdhani text-xl">Loading orders...</div>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <h1 className="text-4xl font-orbitron font-bold text-neon-pink mb-8">My Orders</h1>

      {error && (
        <div className="bg-red-900/30 border-2 border-red-500 text-red-200 px-4 py-3 rounded-sm mb-6">
          {error}
        </div>
      )}

      {orders.length === 0 ? (
        <div className="bg-bg-dark-2 border-2 border-neon-cyan rounded-sm p-12 text-center">
          <div className="text-6xl mb-4">📦</div>
          <h2 className="text-2xl font-orbitron font-bold text-neon-cyan mb-4">No Orders Yet</h2>
          <p className="text-neon-purple font-rajdhani mb-6">
            You haven't placed any orders yet. Start shopping now!
          </p>
          <Link
            to="/products"
            className="cyber-button cyber-button-pink inline-block px-6 py-3"
          >
            Browse Products
          </Link>
        </div>
      ) : (
        <div className="space-y-6">
          {orders.map((order) => (
            <div
              key={order.id}
              className="bg-bg-dark-2 border-2 border-neon-cyan rounded-sm p-6 shadow-neon-cyan hover:border-neon-pink transition-all"
            >
              <div className="flex justify-between items-start mb-4">
                <div>
                  <h3 className="text-xl font-orbitron font-bold text-neon-cyan">
                    Order #{order.id}
                  </h3>
                  <p className="text-sm font-tech text-neon-purple">
                    {new Date(order.created_at).toLocaleDateString()} at{' '}
                    {new Date(order.created_at).toLocaleTimeString()}
                  </p>
                </div>
                <div className="text-right">
                  <span className={`inline-block px-3 py-1 text-xs font-tech uppercase tracking-wider border ${
                    order.status === 'COMPLETED'
                      ? 'bg-green-500/20 text-green-400 border-green-500'
                      : order.status === 'PROCESSING'
                      ? 'bg-blue-500/20 text-blue-400 border-blue-500'
                      : order.status === 'PENDING'
                      ? 'bg-yellow-500/20 text-yellow-400 border-yellow-500'
                      : 'bg-red-500/20 text-red-400 border-red-500'
                  }`}>
                    {order.status}
                  </span>
                  <p className="text-2xl font-orbitron font-bold text-neon-pink mt-2">
                    ${(order.total_amount_cents / 100).toFixed(2)}
                  </p>
                </div>
              </div>

              {order.items && order.items.length > 0 && (
                <div className="border-t-2 border-neon-cyan/30 pt-4">
                  <h4 className="text-sm font-tech text-neon-purple uppercase mb-3">Order Items</h4>
                  <div className="space-y-2">
                    {order.items.map((item: any, index: number) => (
                      <div
                        key={index}
                        className="flex justify-between items-center bg-bg-dark-1 p-3 border border-neon-cyan/30 rounded-sm"
                      >
                        <div className="flex-1">
                          <p className="font-rajdhani font-semibold text-neon-cyan">
                            {item.product?.title || 'Product'}
                          </p>
                          <p className="text-sm text-neon-purple">Quantity: {item.quantity}</p>
                        </div>
                        <p className="font-orbitron font-bold text-neon-pink">
                          ${(item.price_cents / 100).toFixed(2)}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {order.shipping_address && (
                <div className="border-t-2 border-neon-cyan/30 mt-4 pt-4">
                  <h4 className="text-sm font-tech text-neon-purple uppercase mb-2">Shipping Address</h4>
                  <p className="text-neon-cyan font-rajdhani text-sm">
                    {order.shipping_address.street}, {order.shipping_address.city},{' '}
                    {order.shipping_address.state} {order.shipping_address.postal_code}
                  </p>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
