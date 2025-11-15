import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api, { CartItem } from '../services/api';

const Cart: React.FC = () => {
  const navigate = useNavigate();
  const [cartItems, setCartItems] = useState<CartItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updating, setUpdating] = useState<number | null>(null);

  useEffect(() => {
    loadCart();
    
    // Listen for cart updates from other components
    const handleStorageEvent = () => {
      loadCart();
    };
    
    window.addEventListener('storage', handleStorageEvent);
    return () => window.removeEventListener('storage', handleStorageEvent);
  }, []);

  const loadCart = async () => {
    try {
      setLoading(true);
      setError(null);
      const items = await api.getCart();
      // Ensure items is always an array
      setCartItems(Array.isArray(items) ? items : []);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to load cart');
      setCartItems([]); // Set empty array on error
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateQuantity = async (itemId: number, newQuantity: number) => {
    if (newQuantity < 1) return;

    try {
      setUpdating(itemId);
      await api.updateCartItem(itemId, newQuantity);
      await loadCart();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to update quantity');
    } finally {
      setUpdating(null);
    }
  };

  const handleRemoveItem = async (itemId: number) => {
    if (!confirm('Remove this item from cart?')) return;

    try {
      setUpdating(itemId);
      await api.removeFromCart(itemId);
      await loadCart();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to remove item');
      setUpdating(null);
    }
  };

  const handleClearCart = async () => {
    if (!confirm('Clear all items from cart?')) return;

    try {
      setLoading(true);
      await api.clearCart();
      await loadCart();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to clear cart');
      setLoading(false);
    }
  };

  const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
  };

  const calculateTotal = (): number => {
    return cartItems.reduce((sum, item) => {
      return sum + (item.product?.price_cents || 0) * item.quantity;
    }, 0);
  };

  const calculateItemTotal = (item: CartItem): number => {
    return (item.product?.price_cents || 0) * item.quantity;
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          {error}
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">🛒 Shopping Cart</h1>
          <p className="text-gray-600">{cartItems?.length || 0} {(cartItems?.length || 0) === 1 ? 'item' : 'items'} in your cart</p>
        </div>
        {cartItems && cartItems.length > 0 && (
          <button
            onClick={handleClearCart}
            className="px-4 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 font-medium rounded-lg transition-colors"
            data-testid="clear-cart-button"
          >
            🗑️ Clear All
          </button>
        )}
      </div>

      {!cartItems || cartItems.length === 0 ? (
        <div className="text-center py-20 bg-white rounded-xl shadow-md">
          <div className="mb-6">
            <svg
              className="mx-auto h-32 w-32 text-gray-300 mb-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1}
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
          <p className="text-gray-600 mb-8">Looks like you haven't added any products yet.</p>
          <button
            onClick={() => navigate('/products')}
            className="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-3 rounded-full font-bold text-lg hover:shadow-xl transition-all transform hover:scale-105"
          >
            Start Shopping 🛍️
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Cart Items */}
          <div className="lg:col-span-2 space-y-4">
            {cartItems.map((item, index) => (
              <div
                key={item.id}
                className="bg-white rounded-xl shadow-md p-6 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1"
                style={{ animationDelay: `${index * 100}ms` }}
                data-testid={`cart-item-${item.id}`}
              >
                <div className="flex gap-4">
                  {/* Product Image */}
                  <div className="flex-shrink-0">
                    {item.product?.image_url ? (
                      <img
                        src={item.product.image_url}
                        alt={item.product.title}
                        className="w-24 h-24 object-cover rounded"
                      />
                    ) : (
                      <div className="w-24 h-24 bg-gray-200 rounded flex items-center justify-center">
                        <span className="text-gray-400 text-xs">No Image</span>
                      </div>
                    )}
                  </div>

                  {/* Product Info */}
                  <div className="flex-grow">
                    <div className="flex justify-between items-start mb-2">
                      <h3 className="font-semibold text-gray-900 text-lg">
                        {item.product?.title || 'Unknown Product'}
                      </h3>
                      <button
                        onClick={() => handleRemoveItem(item.id)}
                        disabled={updating === item.id}
                        className="text-red-600 hover:text-red-800 disabled:opacity-50"
                        data-testid={`remove-item-${item.id}`}
                      >
                        <svg
                          className="h-5 w-5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M6 18L18 6M6 6l12 12"
                          />
                        </svg>
                      </button>
                    </div>

                    <p className="text-gray-600 text-sm mb-3 line-clamp-2">
                      {item.product?.description}
                    </p>

                    <div className="flex justify-between items-center">
                      {/* Quantity Controls */}
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => handleUpdateQuantity(item.id, item.quantity - 1)}
                          disabled={updating === item.id || item.quantity <= 1}
                          className="w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                          data-testid={`decrease-quantity-${item.id}`}
                        >
                          -
                        </button>
                        <span
                          className="w-12 text-center font-medium"
                          data-testid={`quantity-${item.id}`}
                        >
                          {item.quantity}
                        </span>
                        <button
                          onClick={() => handleUpdateQuantity(item.id, item.quantity + 1)}
                          disabled={updating === item.id}
                          className="w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                          data-testid={`increase-quantity-${item.id}`}
                        >
                          +
                        </button>
                      </div>

                      {/* Price */}
                      <div className="text-right">
                        <p className="text-sm text-gray-500">
                          {formatPrice(item.product?.price_cents || 0)} each
                        </p>
                        <p className="text-lg font-bold text-blue-600">
                          {formatPrice(calculateItemTotal(item))}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Order Summary */}
          <div className="lg:col-span-1">
            <div className="bg-gradient-to-br from-white to-blue-50 rounded-xl shadow-xl p-6 sticky top-24 border border-blue-100">
              <h2 className="text-2xl font-bold text-gray-900 mb-6">📋 Order Summary</h2>

              <div className="space-y-3 mb-6">
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium">Subtotal ({cartItems.length} {cartItems.length === 1 ? 'item' : 'items'})</span>
                  <span className="font-semibold">{formatPrice(calculateTotal())}</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium">Shipping</span>
                  <span className="text-green-600 font-semibold">Calculated at checkout</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium">Tax</span>
                  <span className="text-gray-500">Included</span>
                </div>
              </div>

              <div className="border-t-2 border-gray-300 pt-4 mb-6">
                <div className="flex justify-between text-xl font-bold text-gray-900">
                  <span>Total</span>
                  <span className="text-blue-600" data-testid="cart-total">
                    {formatPrice(calculateTotal())}
                  </span>
                </div>
              </div>

              <button
                onClick={() => navigate('/checkout')}
                className="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition-all transform hover:scale-105 mb-3"
                data-testid="checkout-button"
              >
                Proceed to Checkout 🚀
              </button>

              <button
                onClick={() => navigate('/products')}
                className="w-full bg-white border-2 border-gray-300 text-gray-800 px-6 py-3 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all"
              >
                ← Continue Shopping
              </button>

              <div className="mt-6 pt-6 border-t border-gray-200">
                <div className="flex items-center gap-2 text-sm text-gray-600 mb-2">
                  <svg className="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                  </svg>
                  <span className="font-medium">Secure Checkout</span>
                </div>
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <svg className="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" />
                  </svg>
                  <span className="font-medium">Free Shipping on Orders $50+</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Cart;
