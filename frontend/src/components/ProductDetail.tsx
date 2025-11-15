import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api, { Product, GroupBuy } from '../services/api';
import { successToast, errorToast, infoToast } from '../utils/toast';
import { normalizeTags } from '../utils/productHelpers';

const ProductDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const [product, setProduct] = useState<Product | null>(null);
  const [groupBuys, setGroupBuys] = useState<GroupBuy[]>([]);
  const [recommendations, setRecommendations] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [addingToCart, setAddingToCart] = useState(false);
  const [showGroupBuyForm, setShowGroupBuyForm] = useState(false);

  useEffect(() => {
    if (id) {
      loadProductData();
    }
  }, [id]);

  const loadProductData = async () => {
    try {
      setLoading(true);
      setError(null);

      const [productData, groupBuysData, recommendationsData] = await Promise.all([
        api.getProduct(Number(id)),
        api.getGroupBuys({ product_id: Number(id), status: 'ACTIVE' }).catch(() => []),
        api.getRecommendations({ product_id: Number(id), n: 4 }).catch(() => []),
      ]);

      setProduct(productData);
      setGroupBuys(groupBuysData);
      setRecommendations(recommendationsData);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to load product');
    } finally {
      setLoading(false);
    }
  };

  const handleAddToCart = async () => {
    if (!product) return;

    try {
      setAddingToCart(true);
      await api.addToCart(product.id, quantity);
      successToast(`Added ${quantity} ${quantity > 1 ? 'items' : 'item'} to cart!`);
      window.dispatchEvent(new Event('storage')); // Update cart count in nav
    } catch (err: any) {
      errorToast(err.response?.data?.message || 'Failed to add to cart');
    } finally {
      setAddingToCart(false);
    }
  };

  const handleStartGroupBuy = () => {
    setShowGroupBuyForm(true);
    infoToast('Group buying feature coming soon!');
  };

  const handleJoinGroupBuy = async (groupBuyId: number) => {
    try {
      await api.joinGroupBuy(groupBuyId);
      successToast('Successfully joined group buy!');
      loadProductData();
    } catch (err: any) {
      errorToast(err.response?.data?.message || 'Failed to join group buy');
    }
  };

  const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
  };

  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString();
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error || !product) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          {error || 'Product not found'}
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Breadcrumb */}
      <nav className="flex items-center space-x-2 text-sm text-gray-600 mb-6">
        <Link to="/" className="hover:text-blue-600">Home</Link>
        <span>›</span>
        <Link to="/products" className="hover:text-blue-600">Products</Link>
        <span>›</span>
        <span className="text-gray-900 font-medium line-clamp-1">{product.title}</span>
      </nav>

      {/* Product Details */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
        {/* Product Image Gallery */}
        <div className="space-y-4">
          <div className="aspect-square bg-white rounded-2xl overflow-hidden shadow-xl border-4 border-gray-100">
            {product.image_url ? (
              <img
                src={product.image_url}
                alt={product.title}
                className="w-full h-full object-cover hover:scale-110 transition-transform duration-500"
              />
            ) : (
              <div className="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                <svg className="w-24 h-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span className="text-gray-500 text-xl">No Image Available</span>
              </div>
            )}
          </div>
          
          {/* Image thumbnails placeholder */}
          {product.image_url && (
            <div className="grid grid-cols-4 gap-4">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="aspect-square bg-white rounded-lg overflow-hidden border-2 border-gray-200 hover:border-blue-500 cursor-pointer transition-all">
                  <img src={product.image_url} alt={`${product.title} view ${i}`} className="w-full h-full object-cover opacity-70 hover:opacity-100 transition-opacity" />
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Product Info */}
        <div className="space-y-6">
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <h1 className="text-4xl font-bold text-gray-900 mb-3">{product.title}</h1>
              <div className="flex items-center gap-3 mb-4">
                {product.is_quick_item && (
                  <span className="px-4 py-1.5 text-sm font-bold bg-green-500 text-white rounded-full shadow-md">
                    ⚡ Quick Delivery
                  </span>
                )}
                <span className="px-4 py-1.5 text-sm font-medium bg-blue-100 text-blue-800 rounded-full">
                  {product.category}
                </span>
              </div>
            </div>
          </div>

          {/* Price */}
          <div className="bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl p-6 border-2 border-blue-200">
            <div className="flex items-baseline gap-3">
              <span className="text-4xl font-bold text-blue-600">
                {formatPrice(product.price_cents)}
              </span>
              <span className="text-lg text-gray-500 line-through">
                {formatPrice(product.price_cents * 1.2)}
              </span>
              <span className="px-3 py-1 bg-red-500 text-white text-sm font-bold rounded-full">
                SAVE 20%
              </span>
            </div>
          </div>

          {/* Description */}
          <div className="prose max-w-none">
            <p className="text-gray-700 text-lg leading-relaxed">{product.description}</p>
          </div>

          {/* Product Details */}
          <div className="bg-gray-50 rounded-xl p-6 space-y-3">
            <h3 className="font-bold text-lg text-gray-900 mb-4">Product Details</h3>
            <div className="flex items-center gap-3">
              <span className="font-semibold text-gray-700 w-24">SKU:</span>
              <span className="text-gray-600 font-mono text-sm">{product.sku}</span>
            </div>
            <div className="flex items-center gap-3">
              <span className="font-semibold text-gray-700 w-24">Status:</span>
              <span
                className={`px-3 py-1 rounded-full text-sm font-bold ${
                  product.status === 'ACTIVE'
                    ? 'bg-green-100 text-green-800'
                    : 'bg-gray-200 text-gray-800'
                }`}
              >
                {product.status}
              </span>
            </div>
            <div className="flex items-center gap-3">
              <span className="font-semibold text-gray-700 w-24">Shipping:</span>
              <span className="text-green-600 font-semibold">
                {product.is_quick_item ? '15-30 minutes' : 'Free shipping on orders $50+'}
              </span>
            </div>
          </div>

          {/* Tags */}
          {(() => {
            const tags = normalizeTags(product.tags);
            return tags.length > 0 && (
              <div>
                <h3 className="font-semibold text-gray-900 mb-3">Tags</h3>
                <div className="flex flex-wrap gap-2">
                  {tags.map((tag: string, index: number) => (
                    <span
                      key={index}
                      className="px-4 py-2 text-sm bg-white border-2 border-gray-200 text-gray-700 rounded-lg hover:border-blue-500 hover:text-blue-600 transition-colors cursor-pointer"
                    >
                      #{tag}
                    </span>
                  ))}
                </div>
              </div>
            );
          })()}

          {/* Add to Cart */}
          <div className="bg-white border-2 border-gray-200 rounded-xl p-6">
            <label className="block text-sm font-bold text-gray-900 mb-3">
              Quantity
            </label>
            <div className="flex items-center gap-4 mb-4">
              <div className="flex items-center border-2 border-gray-300 rounded-lg overflow-hidden">
                <button
                  onClick={() => setQuantity(Math.max(1, quantity - 1))}
                  className="px-4 py-3 bg-gray-100 hover:bg-gray-200 font-bold text-xl"
                >
                  −
                </button>
                <input
                  type="number"
                  min="1"
                  value={quantity}
                  onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
                  className="w-20 px-4 py-3 text-center font-bold text-lg border-x-2 border-gray-300"
                  data-testid="quantity-input"
                />
                <button
                  onClick={() => setQuantity(quantity + 1)}
                  className="px-4 py-3 bg-gray-100 hover:bg-gray-200 font-bold text-xl"
                >
                  +
                </button>
              </div>
              <div className="flex-1">
                <p className="text-sm text-gray-600">
                  Total: <span className="text-xl font-bold text-blue-600">{formatPrice(product.price_cents * quantity)}</span>
                </p>
              </div>
            </div>
            
            <div className="space-y-3">
              <button
                onClick={handleAddToCart}
                disabled={addingToCart || product.status !== 'ACTIVE'}
                className="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white px-8 py-4 rounded-xl font-bold text-lg hover:shadow-2xl disabled:from-gray-400 disabled:to-gray-400 disabled:cursor-not-allowed transition-all transform hover:scale-105"
                data-testid="add-to-cart-button"
              >
                {addingToCart ? '⏳ Adding...' : '🛒 Add to Cart'}
              </button>

              <button
                onClick={handleStartGroupBuy}
                className="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white px-8 py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition-all transform hover:scale-105"
                data-testid="start-group-buy-button"
              >
                🤝 Start Group Buy & Save More
              </button>
            </div>

            <div className="mt-6 pt-6 border-t-2 border-gray-200">
              <div className="flex items-center gap-3 text-sm text-gray-600">
                <svg className="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                <span>In stock and ready to ship</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Active Group Buys */}
      {groupBuys.length > 0 && (
        <div className="mb-16">
          <div className="mb-8">
            <h2 className="text-3xl font-bold text-gray-900 mb-2">🤝 Active Group Buys</h2>
            <p className="text-gray-600">Join a group and save even more!</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {groupBuys.map((groupBuy) => (
              <div
                key={groupBuy.id}
                className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                data-testid={`group-buy-${groupBuy.id}`}
              >
                <div className="flex justify-between items-start mb-2">
                  <h3 className="font-semibold text-gray-900">
                    {groupBuy.member_count || 0} / {groupBuy.target_size} members
                  </h3>
                  <span className="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">
                    {groupBuy.status}
                  </span>
                </div>

                <div className="mb-3">
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div
                      className="bg-purple-600 h-2 rounded-full transition-all"
                      style={{ width: `${groupBuy.progress_percentage || 0}%` }}
                    ></div>
                  </div>
                </div>

                <p className="text-sm text-gray-600 mb-2">
                  Team Price: <span className="font-bold text-green-600">
                    {formatPrice(groupBuy.team_price_cents)}
                  </span>
                </p>
                <p className="text-sm text-gray-600 mb-2">
                  Save: <span className="font-bold">{formatPrice(groupBuy.savings || 0)}</span>
                </p>
                <p className="text-sm text-gray-600 mb-3">
                  Expires: {formatDate(groupBuy.expires_at)}
                </p>

                <button
                  onClick={() => handleJoinGroupBuy(groupBuy.id)}
                  className="w-full bg-purple-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700 transition-colors"
                  data-testid={`join-group-buy-${groupBuy.id}`}
                >
                  Join Group Buy
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Recommendations */}
      {recommendations.length > 0 && (
        <div>
          <div className="mb-8">
            <h2 className="text-3xl font-bold text-gray-900 mb-2">💡 You May Also Like</h2>
            <p className="text-gray-600">Handpicked recommendations just for you</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {recommendations.map((rec) => (
              <Link
                key={rec.id}
                to={`/products/${rec.id}`}
                className="group bg-white rounded-xl shadow-md overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2"
              >
                <div className="relative">
                  {rec.image_url ? (
                    <img
                      src={rec.image_url}
                      alt={rec.title}
                      className="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300"
                    />
                  ) : (
                    <div className="w-full h-48 flex items-center justify-center bg-gray-200">
                      <span className="text-gray-400 text-sm">No Image</span>
                    </div>
                  )}
                  <div className="absolute top-2 right-2 bg-white px-2 py-1 rounded-full shadow-md">
                    <span className="text-xs font-bold text-blue-600">⭐ 4.5</span>
                  </div>
                </div>
                <div className="p-4">
                  <h3 className="font-semibold text-gray-900 line-clamp-2 mb-3 group-hover:text-blue-600 transition-colors">
                    {rec.title}
                  </h3>
                  <div className="flex items-center justify-between">
                    <p className="text-xl font-bold text-blue-600">
                      {formatPrice(rec.price_cents)}
                    </p>
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">{rec.category}</span>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}

      {/* Group Buy Form Modal - Simple version */}
      {showGroupBuyForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 className="text-xl font-bold mb-4">Start Group Buy</h3>
            <p className="text-gray-600 mb-4">
              Group buying feature coming soon! You can browse existing group buys above.
            </p>
            <button
              onClick={() => setShowGroupBuyForm(false)}
              className="w-full bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700"
            >
              Close
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductDetail;
