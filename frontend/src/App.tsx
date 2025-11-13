import { BrowserRouter, Routes, Route, Link, useLocation } from 'react-router-dom';
import { useState, useEffect } from 'react';
import Home from './components/Home';
import ProductList from './components/ProductList';
import ProductDetail from './components/ProductDetail';
import Cart from './components/Cart';
import Checkout from './components/Checkout';
import GroupBuyList from './components/GroupBuyList';
import ChatAssistant from './components/ChatAssistant';
import SpinWheel from './components/SpinWheel';

function Navigation() {
  const [cartCount, setCartCount] = useState(0);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const location = useLocation();

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 10);
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    // Close mobile menu when location changes
    setMobileMenuOpen(false);
    
    // Load cart count from localStorage
    const cart = localStorage.getItem('cart');
    if (cart) {
      try {
        const items = JSON.parse(cart);
        const totalItems = Array.isArray(items) 
          ? items.reduce((sum: number, item: any) => sum + (item.quantity || 0), 0)
          : 0;
        setCartCount(totalItems);
      } catch (error) {
        console.error('Error parsing cart:', error);
        setCartCount(0);
      }
    }
  }, [location]);

  const isActive = (path: string) => {
    return location.pathname === path || location.pathname.startsWith(`${path}/`);
  };
  
  const navItems = [
    { to: '/products', label: 'Products', emoji: '' },
    { to: '/group-buys', label: 'Group Buys', emoji: '🤝' },
    { to: '/spin', label: 'Spin & Win', emoji: '🎰' },
    { to: '/chat', label: 'AI Assistant', emoji: '💬' },
  ];

  return (
    <header 
      className={`sticky top-0 z-50 bg-white/80 backdrop-blur-sm transition-all duration-300 ${
        isScrolled ? 'shadow-md py-2' : 'py-3'
      }`}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center">
          {/* Logo */}
          <Link 
            to="/" 
            className="flex items-center space-x-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md p-1 -m-1"
            aria-label="Home"
          >
            <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center transition-transform hover:scale-105">
              <span className="text-white font-bold text-xl">M</span>
            </div>
            <span className="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
              MyShop
            </span>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center space-x-6 lg:space-x-8">
            {navItems.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className={`${
                  isActive(item.to)
                    ? 'text-blue-600 border-b-2 border-blue-600'
                    : 'text-gray-700 hover:text-blue-600'
                } font-medium transition-all duration-200 pb-1 flex items-center group`}
              >
                {item.emoji && <span className="mr-1.5 group-hover:scale-110 transition-transform">{item.emoji}</span>}
                {item.label}
              </Link>
            ))}
            <Link 
              to="/cart" 
              className={`${
                isActive('/cart') ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600'
              } font-medium transition-colors relative p-2 -m-2 rounded-full hover:bg-gray-100`}
              aria-label={`Cart ${cartCount > 0 ? `(${cartCount} items)` : ''}`}
            >
              <div className="flex items-center">
                <svg 
                  className="w-6 h-6" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path 
                    strokeLinecap="round" 
                    strokeLinejoin="round" 
                    strokeWidth={2} 
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" 
                  />
                </svg>
                {cartCount > 0 && (
                  <span 
                    className="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center animate-bounce"
                    aria-live="polite"
                    aria-atomic="true"
                  >
                    {cartCount}
                  </span>
                )}
              </div>
            </Link>
          </nav>

          {/* Mobile menu button */}
          <button
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            className="md:hidden p-2 -m-2 rounded-md text-gray-700 hover:text-blue-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            aria-expanded={mobileMenuOpen}
            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
          >
            <svg 
              className="w-6 h-6" 
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              {mobileMenuOpen ? (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              ) : (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              )}
            </svg>
          </button>
        </div>

        {/* Mobile Navigation */}
        <div 
          className={`md:hidden transition-all duration-300 ease-in-out overflow-hidden ${
            mobileMenuOpen ? 'max-h-96 py-4 border-t mt-3' : 'max-h-0 py-0 border-t-0 mt-0'
          }`}
          aria-hidden={!mobileMenuOpen}
        >
          <nav className="flex flex-col space-y-3">
            {navItems.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className={`${
                  isActive(item.to) ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-gray-50'
                } font-medium px-3 py-2 rounded-md transition-colors duration-200 flex items-center`}
                onClick={() => setMobileMenuOpen(false)}
              >
                {item.emoji && <span className="mr-2 text-lg">{item.emoji}</span>}
                {item.label}
              </Link>
            ))}
            <Link 
              to="/cart" 
              className={`${
                isActive('/cart') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-gray-50'
              } font-medium px-3 py-2 rounded-md transition-colors duration-200 flex items-center`}
              onClick={() => setMobileMenuOpen(false)}
            >
              <span className="mr-2">🛒</span>
              Cart
              {cartCount > 0 && (
                <span className="ml-auto bg-blue-100 text-blue-800 text-xs font-bold px-2.5 py-0.5 rounded-full">
                  {cartCount}
                </span>
              )}
            </Link>
          </nav>
        </div>
      </div>
    </header>
  );
}

function ScrollToTop() {
  const [showScroll, setShowScroll] = useState(false);

  useEffect(() => {
    const checkScrollTop = () => {
      if (!showScroll && window.pageYOffset > 400) {
        setShowScroll(true);
      } else if (showScroll && window.pageYOffset <= 400) {
        setShowScroll(false);
      }
    };

    window.addEventListener('scroll', checkScrollTop);
    return () => window.removeEventListener('scroll', checkScrollTop);
  }, [showScroll]);

  const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return showScroll ? (
    <button
      className="scroll-to-top"
      onClick={scrollTop}
      title="Scroll to top"
    >
      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 10l7-7m0 0l7 7m-7-7v18" />
      </svg>
    </button>
  ) : null;
}

function App() {
  return (
    <BrowserRouter>
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        <Navigation />

        {/* Main Content */}
        <main>
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/products" element={<ProductList />} />
            <Route path="/products/:id" element={<ProductDetail />} />
            <Route path="/cart" element={<Cart />} />
            <Route path="/checkout" element={<Checkout />} />
            <Route path="/group-buys" element={<GroupBuyList />} />
            <Route path="/spin" element={<SpinWheel />} />
            <Route path="/chat" element={<ChatAssistant />} />
          </Routes>
        </main>

        {/* Footer */}
        <footer className="bg-gray-900 text-white mt-20">
          <div className="max-w-7xl mx-auto px-4 py-12">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
              <div>
                <h3 className="text-lg font-bold mb-4">MyShop</h3>
                <p className="text-gray-400 text-sm">
                  AI-Powered Group Buying Platform. Save more together!
                </p>
              </div>
              <div>
                <h4 className="font-semibold mb-4">Shop</h4>
                <ul className="space-y-2 text-sm text-gray-400">
                  <li><Link to="/products" className="hover:text-white transition-colors">All Products</Link></li>
                  <li><Link to="/group-buys" className="hover:text-white transition-colors">Group Buys</Link></li>
                  <li><Link to="/spin" className="hover:text-white transition-colors">Spin & Win</Link></li>
                </ul>
              </div>
              <div>
                <h4 className="font-semibold mb-4">Support</h4>
                <ul className="space-y-2 text-sm text-gray-400">
                  <li><Link to="/chat" className="hover:text-white transition-colors">AI Assistant</Link></li>
                  <li><a href="#" className="hover:text-white transition-colors">Contact Us</a></li>
                  <li><a href="#" className="hover:text-white transition-colors">FAQs</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-semibold mb-4">Features</h4>
                <ul className="space-y-2 text-sm text-gray-400">
                  <li>✨ AI Shopping Assistant</li>
                  <li>🤝 Group Buying</li>
                  <li>⚡ Quick Fulfillment</li>
                  <li>🎁 Gamification</li>
                </ul>
              </div>
            </div>
            <div className="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-400">
              <p>© 2025 MyShop. All rights reserved.</p>
            </div>
          </div>
        </footer>

        {/* Scroll to Top Button */}
        <ScrollToTop />
      </div>
    </BrowserRouter>
  );
}

export default App;
