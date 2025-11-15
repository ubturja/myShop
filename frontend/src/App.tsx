import { BrowserRouter, Routes, Route, Link, useLocation } from 'react-router-dom';
import { useState, useEffect, useRef } from 'react';
import Home from './components/Home';
import ProductList from './components/ProductList';
import ProductDetail from './components/ProductDetail';
import Cart from './components/Cart';
import Checkout from './components/Checkout';
import GroupBuyList from './components/GroupBuyList';
import ChatAssistant from './components/ChatAssistant';
import SpinWheel from './components/SpinWheel';
import Login from './components/Login';
import LoginCustomer from './pages/LoginCustomer';
import LoginSeller from './pages/LoginSeller';
import LoginAdmin from './pages/LoginAdmin';
import RegisterCustomer from './pages/RegisterCustomer';
import RegisterSeller from './pages/RegisterSeller';
import SellerDashboard from './pages/SellerDashboard';
import AdminDashboard from './pages/AdminDashboard';
import Profile from './pages/Profile';
import Orders from './pages/Orders';

function Navigation() {
  const [cartCount, setCartCount] = useState(0);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [user, setUser] = useState<any>(null);
  const [showUserMenu, setShowUserMenu] = useState(false);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const location = useLocation();

  // Handle click outside to close user menu
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
        setShowUserMenu(false);
      }
    };

    if (showUserMenu) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [showUserMenu]);

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
    
    // Check authentication status
    const token = localStorage.getItem('auth_token');
    const userData = localStorage.getItem('auth_user');
    
    console.log('Auth check - Token:', !!token, 'User data:', !!userData);
    
    if (token && userData) {
      try {
        const parsedUser = JSON.parse(userData);
        console.log('Parsed user:', parsedUser);
        setIsAuthenticated(true);
        setUser(parsedUser);
      } catch (error) {
        console.error('Error parsing user data:', error);
        setIsAuthenticated(false);
        setUser(null);
      }
    } else {
      setIsAuthenticated(false);
      setUser(null);
    }
    
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

  const handleLogout = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    setIsAuthenticated(false);
    setUser(null);
    setShowUserMenu(false);
    window.location.href = '/';
  };

  const isActive = (path: string) => {
    return location.pathname === path || location.pathname.startsWith(`${path}/`);
  };
  
  const navItems = [
    { to: '/products', label: 'Products', emoji: '⚡' },
    { to: '/group-buys', label: 'Group Buys', emoji: '🤝' },
    { to: '/spin', label: 'Spin & Win', emoji: '🎰' },
    { to: '/chat', label: 'AI Assistant', emoji: '🤖' },
  ];

  return (
    <header 
      className={`sticky top-0 z-50 backdrop-blur-md transition-all duration-300 border-b-2 ${
        isScrolled 
          ? 'bg-bg-dark-2/95 border-neon-pink shadow-neon-pink py-2' 
          : 'bg-bg-dark-2/80 border-neon-cyan py-3'
      }`}
    >
      <div className="cyber-background absolute inset-0 opacity-20 pointer-events-none"></div>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div className="flex justify-between items-center">
          {/* Logo */}
          <Link 
            to="/" 
            className="flex items-center space-x-3 focus:outline-none focus:ring-2 focus:ring-neon-cyan rounded-md p-1 -m-1 group"
            aria-label="Home"
          >
            <div className="relative">
              <div className="w-10 h-10 bg-gradient-to-br from-neon-purple to-neon-pink rounded-sm flex items-center justify-center transition-all group-hover:shadow-cyber-intense border-2 border-neon-cyan animate-hologram-pulse">
                <span className="text-white font-orbitron font-black text-xl">M</span>
              </div>
              <div className="absolute inset-0 bg-neon-cyan opacity-20 blur-md rounded-sm animate-neon-glow"></div>
            </div>
            <div className="flex flex-col">
              <span className="text-2xl font-orbitron font-black text-neon-cyan group-hover:text-neon-pink transition-colors animate-neon-glow">
                myShop
              </span>
              <span className="text-[10px] font-tech text-neon-purple uppercase tracking-wider -mt-1">
                Shop the Future
              </span>
            </div>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center space-x-6 lg:space-x-8">
            {navItems.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className={`${
                  isActive(item.to)
                    ? 'text-neon-pink border-b-2 border-neon-pink shadow-neon-pink'
                    : 'text-neon-cyan hover:text-neon-pink border-b-2 border-transparent'
                } font-rajdhani font-semibold text-lg transition-all duration-200 pb-1 flex items-center group uppercase tracking-wider`}
              >
                {item.emoji && <span className="mr-2 group-hover:scale-125 transition-transform">{item.emoji}</span>}
                {item.label}
              </Link>
            ))}
            <Link 
              to="/cart" 
              className={`${
                isActive('/cart') ? 'text-neon-pink' : 'text-neon-cyan hover:text-neon-pink'
              } font-medium transition-all relative p-2 -m-2 rounded-sm hover:bg-neon-cyan/10 border-2 border-transparent hover:border-neon-cyan`}
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
                    className="absolute -top-1 -right-1 bg-neon-yellow text-bg-dark-1 text-xs font-orbitron font-bold rounded-none h-5 w-5 flex items-center justify-center animate-pulse shadow-neon-yellow"
                    aria-live="polite"
                    aria-atomic="true"
                  >
                    {cartCount}
                  </span>
                )}
              </div>
            </Link>
            {isAuthenticated && user ? (
              <div className="relative" ref={userMenuRef}>
                <button
                  onClick={() => setShowUserMenu(!showUserMenu)}
                  className="flex items-center space-x-2 text-neon-cyan hover:text-neon-pink transition-all px-3 py-2 rounded-sm border-2 border-neon-cyan hover:border-neon-pink focus:outline-none focus:ring-2 focus:ring-neon-pink"
                  aria-label="User menu"
                >
                  <div className="w-8 h-8 bg-gradient-to-br from-neon-purple to-neon-cyan rounded-full flex items-center justify-center">
                    <span className="text-white font-orbitron font-bold text-sm">
                      {user.name?.charAt(0).toUpperCase() || 'U'}
                    </span>
                  </div>
                  <span className="font-rajdhani font-semibold hidden lg:block">{user.name}</span>
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                
                {showUserMenu && (
                  <div className="absolute right-0 mt-2 w-56 bg-bg-dark-2 border-2 border-neon-cyan rounded-sm shadow-neon-cyan z-50">
                    <div className="px-4 py-3 border-b-2 border-neon-cyan/30">
                      <p className="text-sm font-rajdhani text-neon-cyan">Signed in as</p>
                      <p className="text-sm font-orbitron font-bold text-neon-pink truncate">{user.email}</p>
                      <p className="text-xs font-tech text-neon-purple uppercase mt-1">{user.role}</p>
                    </div>
                    <div className="py-2">
                      {user.role === 'SELLER' && (
                        <Link
                          to="/seller/dashboard"
                          className="block px-4 py-2 text-sm font-rajdhani text-neon-cyan hover:bg-neon-cyan/10 hover:text-neon-pink transition-colors"
                          onClick={() => setShowUserMenu(false)}
                        >
                          📊 Seller Dashboard
                        </Link>
                      )}
                      {user.role === 'ADMIN' && (
                        <Link
                          to="/admin/dashboard"
                          className="block px-4 py-2 text-sm font-rajdhani text-neon-cyan hover:bg-neon-cyan/10 hover:text-neon-pink transition-colors"
                          onClick={() => setShowUserMenu(false)}
                        >
                          ⚙️ Admin Dashboard
                        </Link>
                      )}
                      <Link
                        to="/orders"
                        className="block px-4 py-2 text-sm font-rajdhani text-neon-cyan hover:bg-neon-cyan/10 hover:text-neon-pink transition-colors"
                        onClick={() => setShowUserMenu(false)}
                      >
                        📦 My Orders
                      </Link>
                      <Link
                        to="/profile"
                        className="block px-4 py-2 text-sm font-rajdhani text-neon-cyan hover:bg-neon-cyan/10 hover:text-neon-pink transition-colors"
                        onClick={() => setShowUserMenu(false)}
                      >
                        👤 Profile
                      </Link>
                      <button
                        onClick={handleLogout}
                        className="w-full text-left px-4 py-2 text-sm font-rajdhani text-neon-pink hover:bg-neon-pink/10 transition-colors border-t-2 border-neon-cyan/30"
                      >
                        🚪 Logout
                      </button>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <Link
                to="/login"
                className="cyber-button cyber-button-pink px-4 py-2 text-sm"
              >
                Login
              </Link>
            )}
          </nav>

          {/* Mobile menu button */}
          <button
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            className="md:hidden p-2 -m-2 rounded-sm text-neon-cyan hover:text-neon-pink hover:bg-neon-cyan/10 focus:outline-none focus:ring-2 focus:ring-neon-cyan border-2 border-transparent hover:border-neon-cyan"
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
            mobileMenuOpen ? 'max-h-96 py-4 border-t-2 border-neon-cyan/30 mt-3' : 'max-h-0 py-0 border-t-0 mt-0'
          }`}
          aria-hidden={!mobileMenuOpen}
        >
          <nav className="flex flex-col space-y-3">
            {navItems.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className={`${
                  isActive(item.to) 
                    ? 'text-neon-pink bg-neon-pink/10 border-neon-pink' 
                    : 'text-neon-cyan hover:text-neon-pink hover:bg-neon-cyan/10 border-neon-cyan/30'
                } font-rajdhani font-semibold px-3 py-2 border-l-2 transition-all duration-200 flex items-center uppercase tracking-wider`}
                onClick={() => setMobileMenuOpen(false)}
              >
                {item.emoji && <span className="mr-2 text-lg">{item.emoji}</span>}
                {item.label}
              </Link>
            ))}
            <Link 
              to="/cart" 
              className={`${
                isActive('/cart') 
                  ? 'text-neon-pink bg-neon-pink/10 border-neon-pink' 
                  : 'text-neon-cyan hover:text-neon-pink hover:bg-neon-cyan/10 border-neon-cyan/30'
              } font-rajdhani font-semibold px-3 py-2 border-l-2 transition-all duration-200 flex items-center uppercase tracking-wider`}
              onClick={() => setMobileMenuOpen(false)}
            >
              <span className="mr-2">🛒</span>
              Cart
              {cartCount > 0 && (
                <span className="ml-auto bg-neon-yellow text-bg-dark-1 text-xs font-orbitron font-bold px-2.5 py-0.5 shadow-neon-yellow">
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
    <BrowserRouter
      future={{
        v7_startTransition: true,
        v7_relativeSplatPath: true,
      }}
    >
      <div className="min-h-screen bg-bg-dark-1 relative overflow-x-hidden">
        {/* Animated Cyber Background */}
        <div className="cyber-background"></div>
        
        <Navigation />

        {/* Main Content */}
        <main className="relative z-10">
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/products" element={<ProductList />} />
            <Route path="/products/:id" element={<ProductDetail />} />
            <Route path="/cart" element={<Cart />} />
            <Route path="/checkout" element={<Checkout />} />
            <Route path="/group-buys" element={<GroupBuyList />} />
            <Route path="/spin" element={<SpinWheel />} />
            <Route path="/chat" element={<ChatAssistant />} />
            
            {/* Legacy login route */}
            <Route path="/login" element={<Login />} />
            
            {/* New role-specific auth routes */}
            <Route path="/login/customer" element={<LoginCustomer />} />
            <Route path="/login/seller" element={<LoginSeller />} />
            <Route path="/login/admin" element={<LoginAdmin />} />
            <Route path="/register/customer" element={<RegisterCustomer />} />
            <Route path="/register/seller" element={<RegisterSeller />} />
            
            {/* Dashboard routes */}
            <Route path="/seller/dashboard" element={<SellerDashboard />} />
            <Route path="/admin/dashboard" element={<AdminDashboard />} />
            
            {/* User Profile routes */}
            <Route path="/profile" element={<Profile />} />
            <Route path="/orders" element={<Orders />} />
          </Routes>
        </main>

        {/* Footer */}
        <footer className="bg-bg-dark-2 text-neon-cyan mt-20 border-t-2 border-neon-purple/30 relative">
          <div className="absolute inset-0 bg-cyber-grid opacity-10 pointer-events-none"></div>
          <div className="max-w-7xl mx-auto px-4 py-12 relative z-10">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
              <div>
                <h3 className="text-xl font-orbitron font-bold mb-4 text-neon-pink animate-neon-glow">myShop</h3>
                <p className="text-neon-cyan/70 text-sm font-rajdhani">
                  AI-Powered Group Buying Platform. Save more together in the cyber age!
                </p>
                <p className="text-neon-purple text-xs font-tech mt-2 uppercase tracking-wider">
                  Shop the Future
                </p>
              </div>
              <div>
                <h4 className="font-orbitron font-semibold mb-4 text-neon-cyan uppercase tracking-wider">Shop</h4>
                <ul className="space-y-2 text-sm text-neon-cyan/70 font-rajdhani">
                  <li><Link to="/products" className="hover:text-neon-pink transition-colors border-b border-transparent hover:border-neon-pink">All Products</Link></li>
                  <li><Link to="/group-buys" className="hover:text-neon-pink transition-colors border-b border-transparent hover:border-neon-pink">Group Buys</Link></li>
                  <li><Link to="/spin" className="hover:text-neon-pink transition-colors border-b border-transparent hover:border-neon-pink">Spin & Win</Link></li>
                </ul>
              </div>
              <div>
                <h4 className="font-orbitron font-semibold mb-4 text-neon-cyan uppercase tracking-wider">Support</h4>
                <ul className="space-y-2 text-sm text-neon-cyan/70 font-rajdhani">
                  <li><Link to="/chat" className="hover:text-neon-pink transition-colors border-b border-transparent hover:border-neon-pink">AI Assistant</Link></li>
                  <li><a href="#" className="hover:text-neon-pink transition-colors border-b border-transparent hover:border-neon-pink">Contact Us</a></li>
                  <li><a href="#" className="hover:text-neon-pink transition-colors border-b border-transparent hover:border-neon-pink">FAQs</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-orbitron font-semibold mb-4 text-neon-cyan uppercase tracking-wider">Features</h4>
                <ul className="space-y-2 text-sm text-neon-cyan/70 font-rajdhani">
                  <li className="flex items-center"><span className="text-neon-pink mr-2">▸</span> AI Shopping Assistant</li>
                  <li className="flex items-center"><span className="text-neon-pink mr-2">▸</span> Group Buying</li>
                  <li className="flex items-center"><span className="text-neon-pink mr-2">▸</span> Quick Fulfillment</li>
                  <li className="flex items-center"><span className="text-neon-pink mr-2">▸</span> Gamification</li>
                </ul>
              </div>
            </div>
            <div className="border-t-2 border-neon-cyan/20 mt-8 pt-8 text-center">
              <p className="text-sm text-neon-purple font-tech">
                © 2025 myShop. All rights reserved. <span className="text-neon-cyan animate-flicker">⚡</span>
              </p>
              <p className="text-xs text-neon-cyan/50 font-tech mt-2">
                Powered by Cyberpunk Technology
              </p>
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
