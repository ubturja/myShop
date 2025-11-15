import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import api, { Product } from '../services/api';
import { normalizeTags } from '../utils/productHelpers';

interface ProductListProps {
  category?: string;
  isQuickItem?: boolean;
}

type SortOption = 'newest' | 'price-low' | 'price-high' | 'name-asc' | 'name-desc';
type ViewMode = 'grid' | 'list';

const PER_PAGE = 12;

const ProductList: React.FC<ProductListProps> = ({ category, isQuickItem }) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [products, setProducts] = useState<Product[]>([]);
  const [filteredProducts, setFilteredProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('search') || '');
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || 'all');
  const [sortBy, setSortBy] = useState<SortOption>((searchParams.get('sort') as SortOption) || 'newest');
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [showFilters, setShowFilters] = useState(false);

  const categories = ['all', 'Cyber Tech', 'Neon Fashion', 'Street Gear', 'Holo Accessories', 'Gaming', 'Audio', 'Smart Home', 'Wearables'];

  useEffect(() => {
    loadProducts();
  }, [category, isQuickItem, currentPage]);

  useEffect(() => {
    filterAndSortProducts();
  }, [products, searchQuery, selectedCategory, sortBy]);

  const loadProducts = async () => {
    try {
      setLoading(true);
      setError(null);
      const params: {
        page: number;
        per_page: number;
        category?: string;
        is_quick_item?: boolean;
      } = {
        page: currentPage,
        per_page: PER_PAGE,
      };

      if (category) {
        params.category = category;
      } else if (selectedCategory !== 'all') {
        params.category = selectedCategory;
      }

      if (isQuickItem) {
        params.is_quick_item = true;
      }

      const response = await api.getProducts(params);
      setProducts(response.data);
      setTotalPages(response.last_page);
      if (typeof response.current_page === 'number') {
        setCurrentPage(response.current_page);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to load products');
    } finally {
      setLoading(false);
    }
  };

  const filterAndSortProducts = () => {
    let filtered = [...products];

    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        (p) =>
          p.title.toLowerCase().includes(query) ||
          p.description.toLowerCase().includes(query) ||
          p.category.toLowerCase().includes(query) ||
          (p.tags && p.tags.some((tag) => tag.toLowerCase().includes(query)))
      );
    }

    if (selectedCategory !== 'all') {
      filtered = filtered.filter((p) => p.category === selectedCategory);
    }

    filtered.sort((a, b) => {
      switch (sortBy) {
        case 'price-low':
          return a.price_cents - b.price_cents;
        case 'price-high':
          return b.price_cents - a.price_cents;
        case 'name-asc':
          return a.title.localeCompare(b.title);
        case 'name-desc':
          return b.title.localeCompare(a.title);
        case 'newest':
        default:
          return b.id - a.id;
      }
    });

    setFilteredProducts(filtered);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const params: Record<string, string> = {};

    if (searchQuery) {
      params.search = searchQuery;
    }

    if (selectedCategory !== 'all') {
      params.category = selectedCategory;
    }

    params.sort = sortBy;

    setSearchParams(params);
    setCurrentPage(1);
  };

  const addToCart = async (product: Product, e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const existingItem = cart.find((item: any) => item.id === product.id);
    
    if (existingItem) {
      existingItem.quantity += 1;
    } else {
      cart.push({ ...product, quantity: 1 });
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    window.dispatchEvent(new Event('cartUpdated'));
    
    // Show feedback
    const button = e.currentTarget as HTMLButtonElement;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="font-orbitron">ADDED ✓</span>';
    button.classList.add('bg-neon-green/20', 'border-neon-green', 'text-neon-green');
    setTimeout(() => {
      button.innerHTML = originalText;
      button.classList.remove('bg-neon-green/20', 'border-neon-green', 'text-neon-green');
    }, 1500);
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center" role="status" aria-live="polite">
        <div className="text-center">
          <div className="cyber-spinner mx-auto mb-4"></div>
          <p className="text-neon-cyan font-orbitron text-xl animate-pulse">LOADING PRODUCTS...</p>
          <div className="mt-4 flex justify-center space-x-2">
            <span className="w-2 h-2 bg-neon-pink rounded-full animate-bounce"></span>
            <span className="w-2 h-2 bg-neon-cyan rounded-full animate-bounce" style={{ animationDelay: '0.1s' }}></span>
            <span className="w-2 h-2 bg-neon-yellow rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></span>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <div className="hologram-card p-8 max-w-md">
          <div className="text-red-500 text-6xl mb-4 text-center">⚠</div>
          <h2 className="text-2xl font-orbitron font-bold text-neon-pink mb-4 text-center">ERROR</h2>
          <p className="text-neon-cyan/70 mb-6 text-center font-rajdhani">{error}</p>
          <button onClick={() => loadProducts()} className="cyber-button w-full">
            RETRY
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-4xl md:text-5xl font-orbitron font-black text-neon-pink mb-2 animate-neon-glow">
          {category || (isQuickItem ? 'QUICK ITEMS' : 'PRODUCT CATALOG')}
        </h1>
        <div className="h-1 w-32 bg-gradient-to-r from-neon-cyan to-neon-purple"></div>
        <p className="text-neon-cyan/70 mt-4 font-rajdhani text-lg">
          Browse our collection of {filteredProducts.length} futuristic products
        </p>
      </div>

      {/* Search and Filters */}
      <div className="hologram-card p-6 mb-8">
        <form onSubmit={handleSearch} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {/* Search */}
            <div className="relative">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search products..."
                className="cyber-input w-full bg-bg-dark-3/50 px-4 py-3 pr-12 text-neon-cyan placeholder-neon-purple/50 border-2 border-neon-cyan/30 focus:border-neon-pink"
              />
              <button
                type="submit"
                className="absolute right-2 top-1/2 -translate-y-1/2 text-neon-cyan hover:text-neon-pink transition-colors"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </button>
            </div>

            {/* Category */}
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="cyber-input w-full bg-bg-dark-3/50 px-4 py-3 text-neon-cyan border-2 border-neon-cyan/30 focus:border-neon-pink appearance-none cursor-pointer"
            >
              {categories.map((cat) => (
                <option key={cat} value={cat} className="bg-bg-dark-2">
                  {cat.toUpperCase()}
                </option>
              ))}
            </select>

            {/* Sort */}
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as SortOption)}
              className="cyber-input w-full bg-bg-dark-3/50 px-4 py-3 text-neon-cyan border-2 border-neon-cyan/30 focus:border-neon-pink appearance-none cursor-pointer"
            >
              <option value="newest" className="bg-bg-dark-2">NEWEST FIRST</option>
              <option value="price-low" className="bg-bg-dark-2">PRICE: LOW TO HIGH</option>
              <option value="price-high" className="bg-bg-dark-2">PRICE: HIGH TO LOW</option>
              <option value="name-asc" className="bg-bg-dark-2">NAME: A-Z</option>
              <option value="name-desc" className="bg-bg-dark-2">NAME: Z-A</option>
            </select>
          </div>
        </form>
      </div>

      {/* Products Grid */}
      {filteredProducts.length === 0 ? (
        <div className="hologram-card p-12 text-center">
          <div className="text-6xl mb-4">🔍</div>
          <h3 className="text-2xl font-orbitron font-bold text-neon-cyan mb-2">No products found</h3>
          <p className="text-neon-purple/70 font-rajdhani">Try adjusting your search or filters.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {filteredProducts.map((product) => (
            <Link
              key={product.id}
              to={`/products/${product.id}`}
              className="hologram-card group overflow-hidden transition-all duration-300 hover:scale-105 corner-brackets"
            >
              {/* Product Image */}
              <div className="relative h-48 overflow-hidden bg-bg-dark-3 scan-line">
                <img
                  src={product.image_url}
                  alt={product.title}
                  className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110 neon-border"
                  loading="lazy"
                  onError={(e) => {
                    const target = e.target as HTMLImageElement;
                    if (!target.src.includes('product-placeholder')) {
                      target.src = '/images/product-placeholder.svg';
                    }
                  }}
                />
                {product.is_quick_item && (
                  <div className="absolute top-2 right-2">
                    <span
                      className="status-badge status-badge-active font-tech text-xs inline-flex items-center gap-1"
                      data-testid="quick-item-badge"
                    >
                      <span aria-hidden="true">⚡</span>
                      <span>Quick Delivery</span>
                    </span>
                  </div>
                )}
                {/* Glitch overlay on hover */}
                <div className="absolute inset-0 bg-gradient-to-t from-bg-dark-1 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              </div>

              {/* Product Info */}
              <div className="p-4">
                {/* Category */}
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-tech text-neon-purple uppercase tracking-wider">
                    {product.category}
                  </span>
                  {product.status === 'ACTIVE' && (
                    <span className="w-2 h-2 bg-neon-green rounded-full animate-pulse"></span>
                  )}
                </div>

                {/* Title */}
                <h3 className="text-lg font-orbitron font-bold text-neon-cyan mb-2 line-clamp-2 group-hover:text-neon-pink transition-colors">
                  {product.title}
                </h3>

                {/* Description */}
                <p className="text-sm text-neon-cyan/60 mb-3 line-clamp-2 font-rajdhani">
                  {product.description}
                </p>

                {/* Tags */}
                {(() => {
                  const tags = normalizeTags(product.tags);
                  return tags.length > 0 && (
                    <div className="flex flex-wrap gap-1 mb-3">
                      {tags.slice(0, 3).map((tag: string, index: number) => (
                        <span
                          key={index}
                          className="text-xs px-2 py-0.5 bg-neon-purple/20 text-neon-purple border border-neon-purple/50 font-tech"
                        >
                          {tag}
                        </span>
                      ))}
                    </div>
                  );
                })()}

                {/* Price and Add to Cart */}
                <div className="flex items-center justify-between mt-4">
                  <div className="price-badge text-lg font-orbitron font-black">
                    ${(product.price_cents / 100).toFixed(2)}
                  </div>
                  <button
                    onClick={(e) => addToCart(product, e)}
                    className="px-4 py-2 bg-transparent border-2 border-neon-cyan text-neon-cyan hover:bg-neon-cyan/20 hover:border-neon-pink hover:text-neon-pink transition-all font-orbitron font-bold text-sm uppercase tracking-wider"
                  >
                    ADD
                  </button>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-12 flex flex-col items-center space-y-4">
          <span className="font-rajdhani text-sm text-neon-cyan/80">Page {currentPage} of {totalPages}</span>
          <div className="flex justify-center items-center space-x-4">
            <button
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              disabled={currentPage === 1}
              className="cyber-button disabled:opacity-30 disabled:cursor-not-allowed px-6 py-2"
            >
              Prev
            </button>
            <div className="flex items-center space-x-2">
              {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                const page = i + 1;
                return (
                  <button
                    key={page}
                    onClick={() => setCurrentPage(page)}
                    className={`w-10 h-10 font-orbitron font-bold ${
                      currentPage === page
                        ? 'bg-neon-pink/20 border-2 border-neon-pink text-neon-pink'
                        : 'bg-transparent border-2 border-neon-cyan/30 text-neon-cyan hover:border-neon-cyan'
                    } transition-all`}
                  >
                    {page}
                  </button>
                );
              })}
            </div>
            <button
              onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
              disabled={currentPage === totalPages}
              className="cyber-button disabled:opacity-30 disabled:cursor-not-allowed px-6 py-2"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductList;
