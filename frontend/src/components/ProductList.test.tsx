import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ProductList from './ProductList';
import api from '../services/api';

vi.mock('../services/api');

const mockProducts = [
  {
    id: 1,
    title: 'Test Product 1',
    description: 'Test description 1',
    price_cents: 9999,
    sku: 'TEST001',
    category: 'Electronics',
    is_quick_item: true,
    status: 'ACTIVE',
    image_url: 'https://example.com/image1.jpg',
    tags: ['featured', 'new'],
    store_id: 1,
  },
  {
    id: 2,
    title: 'Test Product 2',
    description: 'Test description 2',
    price_cents: 4999,
    sku: 'TEST002',
    category: 'Books',
    is_quick_item: false,
    status: 'ACTIVE',
    image_url: null,
    tags: ['bestseller'],
    store_id: 1,
  },
];

const renderProductList = (props = {}) => {
  return render(
    <BrowserRouter>
      <ProductList {...props} />
    </BrowserRouter>
  );
};

describe('ProductList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays loading spinner initially', () => {
    vi.mocked(api.getProducts).mockReturnValue(new Promise(() => {}));
    renderProductList();
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('displays products after loading', async () => {
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockProducts,
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 2,
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText('Test Product 1')).toBeInTheDocument();
      expect(screen.getByText('Test Product 2')).toBeInTheDocument();
    });
  });

  it('displays error message on API failure', async () => {
    vi.mocked(api.getProducts).mockRejectedValue({
      response: { data: { message: 'API Error' } },
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText(/API Error/)).toBeInTheDocument();
    });
  });

  it('displays quick item badge for quick items', async () => {
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockProducts,
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 2,
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText('Quick Delivery')).toBeInTheDocument();
    });
  });

  it('formats prices correctly', async () => {
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockProducts,
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 2,
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText('$99.99')).toBeInTheDocument();
      expect(screen.getByText('$49.99')).toBeInTheDocument();
    });
  });

  it('filters by category when prop is provided', async () => {
    const mockFilteredProducts = [mockProducts[0]];
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockFilteredProducts,
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 1,
    });

    renderProductList({ category: 'Electronics' });

    await waitFor(() => {
      expect(api.getProducts).toHaveBeenCalledWith({
        page: 1,
        per_page: 12,
        category: 'Electronics',
      });
    });
  });

  it('filters by quick item when prop is provided', async () => {
    const mockQuickItems = [mockProducts[0]];
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockQuickItems,
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 1,
    });

    renderProductList({ isQuickItem: true });

    await waitFor(() => {
      expect(api.getProducts).toHaveBeenCalledWith({
        page: 1,
        per_page: 12,
        is_quick_item: true,
      });
    });
  });

  it('displays empty state when no products', async () => {
    vi.mocked(api.getProducts).mockResolvedValue({
      data: [],
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0,
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText(/No products found/)).toBeInTheDocument();
    });
  });

  it('displays pagination controls when multiple pages', async () => {
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockProducts,
      current_page: 1,
      last_page: 3,
      per_page: 12,
      total: 36,
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText(/Page 1 of 3/)).toBeInTheDocument();
      expect(screen.getByText('Next')).toBeInTheDocument();
    });
  });

  it('displays product tags', async () => {
    vi.mocked(api.getProducts).mockResolvedValue({
      data: mockProducts,
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 2,
    });

    renderProductList();

    await waitFor(() => {
      expect(screen.getByText('featured')).toBeInTheDocument();
      expect(screen.getByText('new')).toBeInTheDocument();
      expect(screen.getByText('bestseller')).toBeInTheDocument();
    });
  });
});
