import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { Product } from '../services/api';

const Home: React.FC = () => {
  const [featuredProducts, setFeaturedProducts] = useState<Product[]>([]);
  const [quickItems, setQuickItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadHomeData();
  }, []);

  const loadHomeData = async () => {
    try {
      setLoading(true);
      const [featured, quick] = await Promise.all([
        api.getProducts({ page: 1, per_page: 8 }),
        api.getProducts({ page: 1, per_page: 4, is_quick_item: true }),
      ]);
      setFeaturedProducts(featured.data);
      setQuickItems(quick.data);
    } catch (error) {
      console.error('Failed to load home data:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
  };

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <section className="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div className="max-w-7xl mx-auto px-4 py-20 sm:py-24">
          <div className="text-center">
            <h1 className="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-6 animate-fade-in">
              Shop Smarter with AI & Group Buying
            </h1>
            <p className="text-xl sm:text-2xl mb-8 text-blue-100 max-w-3xl mx-auto">
              Save more when you shop together. Join group buys, get instant delivery, and let AI help you find the perfect products.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                to="/products"
                className="bg-white text-blue-600 px-8 py-3 rounded-full font-bold text-lg hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg"
              >
                Start Shopping
              </Link>
              <Link
                to="/group-buys"
                className="bg-transparent border-2 border-white text-white px-8 py-3 rounded-full font-bold text-lg hover:bg-white hover:text-blue-600 transition-all transform hover:scale-105"
              >
                Explore Group Buys
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div className="text-center">
              <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-3xl">🤝</span>
              </div>
              <h3 className="font-bold text-lg mb-2">Group Buying</h3>
              <p className="text-gray-600 text-sm">Team up with others to unlock bulk discounts</p>
            </div>
            <div className="text-center">
              <div className="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-3xl">⚡</span>
              </div>
              <h3 className="font-bold text-lg mb-2">Quick Delivery</h3>
              <p className="text-gray-600 text-sm">Get items delivered in minutes from nearby stores</p>
            </div>
            <div className="text-center">
              <div className="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-3xl">🤖</span>
              </div>
              <h3 className="font-bold text-lg mb-2">AI Assistant</h3>
              <p className="text-gray-600 text-sm">Get personalized product recommendations</p>
            </div>
            <div className="text-center">
              <div className="bg-yellow-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-3xl">🎁</span>
              </div>
              <h3 className="font-bold text-lg mb-2">Rewards</h3>
              <p className="text-gray-600 text-sm">Earn points and spin for prizes with every purchase</p>
            </div>
          </div>
        </div>
      </section>

      {/* Quick Items */}
      {quickItems.length > 0 && (
        <section className="py-16 bg-gradient-to-r from-green-50 to-emerald-50">
          <div className="max-w-7xl mx-auto px-4">
            <div className="flex justify-between items-center mb-8">
              <div>
                <h2 className="text-3xl font-bold text-gray-900 mb-2">⚡ Quick Delivery Items</h2>
                <p className="text-gray-600">Get these items delivered in minutes!</p>
              </div>
              <Link to="/products?quick=true" className="text-green-600 hover:text-green-700 font-semibold flex items-center">
                View All
                <svg className="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {quickItems.map((product) => (
                <Link
                  key={product.id}
                  to={`/products/${product.id}`}
                  className="group bg-white rounded-xl shadow-md overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1"
                >
                  <div className="relative">
                    <img
                      src={product.image_url || '/placeholder.jpg'}
                      alt={product.title}
                      className="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300"
                    />
                    <div className="absolute top-2 right-2 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-bold">
                      Quick ⚡
                    </div>
                  </div>
                  <div className="p-4">
                    <h3 className="font-semibold text-gray-900 mb-2 line-clamp-2">{product.title}</h3>
                    <div className="flex items-center justify-between">
                      <span className="text-xl font-bold text-green-600">{formatPrice(product.price_cents)}</span>
                      <span className="text-sm text-gray-500">15-30 min</span>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Featured Products */}
      <section className="py-16">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex justify-between items-center mb-8">
            <div>
              <h2 className="text-3xl font-bold text-gray-900 mb-2">Featured Products</h2>
              <p className="text-gray-600">Discover our top picks for you</p>
            </div>
            <Link to="/products" className="text-blue-600 hover:text-blue-700 font-semibold flex items-center">
              View All
              <svg className="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {loading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {[...Array(8)].map((_, i) => (
                <div key={i} className="bg-white rounded-xl shadow-md overflow-hidden animate-pulse">
                  <div className="w-full h-48 bg-gray-300"></div>
                  <div className="p-4">
                    <div className="h-4 bg-gray-300 rounded mb-2"></div>
                    <div className="h-4 bg-gray-300 rounded w-2/3"></div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {featuredProducts.map((product) => (
                <Link
                  key={product.id}
                  to={`/products/${product.id}`}
                  className="group bg-white rounded-xl shadow-md overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1"
                >
                  <div className="relative">
                    <img
                      src={product.image_url || '/placeholder.jpg'}
                      alt={product.title}
                      className="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300"
                    />
                  </div>
                  <div className="p-4">
                    <h3 className="font-semibold text-gray-900 mb-2 line-clamp-2">{product.title}</h3>
                    <p className="text-sm text-gray-600 mb-3 line-clamp-2">{product.description}</p>
                    <div className="flex items-center justify-between">
                      <span className="text-xl font-bold text-blue-600">{formatPrice(product.price_cents)}</span>
                      <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">{product.category}</span>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-16 bg-gradient-to-r from-purple-600 to-pink-600 text-white">
        <div className="max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-4">Ready to Start Saving?</h2>
          <p className="text-xl mb-8 text-purple-100">
            Join thousands of shoppers who save money every day with group buying!
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link
              to="/chat"
              className="bg-white text-purple-600 px-8 py-3 rounded-full font-bold text-lg hover:bg-gray-100 transition-all transform hover:scale-105"
            >
              Chat with AI Assistant
            </Link>
            <Link
              to="/spin"
              className="bg-transparent border-2 border-white text-white px-8 py-3 rounded-full font-bold text-lg hover:bg-white hover:text-purple-600 transition-all transform hover:scale-105"
            >
              Try Your Luck! 🎰
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
};

export default Home;
