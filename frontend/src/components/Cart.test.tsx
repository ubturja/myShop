import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Cart from './Cart';
import api from '../services/api';

vi.mock('../services/api');
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => vi.fn(),
  };
});

const mockCartItems = [
  {
    id: 1,
    user_id: 1,
    product_id: 1,
    quantity: 2,
    product: {
      id: 1,
      title: 'Test Product 1',
      description: 'Test description 1',
      price_cents: 9999,
      sku: 'TEST001',
      category: 'Electronics',
      is_quick_item: true,
      status: 'ACTIVE',
      image_url: 'https://example.com/image1.jpg',
      tags: ['featured'],
      store_id: 1,
    },
  },
  {
    id: 2,
    user_id: 1,
    product_id: 2,
    quantity: 1,
    product: {
      id: 2,
      title: 'Test Product 2',
      description: 'Test description 2',
      price_cents: 4999,
      sku: 'TEST002',
      category: 'Books',
      is_quick_item: false,
      status: 'ACTIVE',
      image_url: null,
      tags: [],
      store_id: 1,
    },
  },
];

const renderCart = () => {
  return render(
    <BrowserRouter>
      <Cart />
    </BrowserRouter>
  );
};

describe('Cart', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays loading spinner initially', () => {
    vi.mocked(api.getCart).mockReturnValue(new Promise(() => {}));
    renderCart();
    const spinner = screen.getByRole('status');
    expect(spinner).toBeInTheDocument();
  });

  it('displays cart items after loading', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    renderCart();

    await waitFor(() => {
      expect(screen.getByText('Test Product 1')).toBeInTheDocument();
      expect(screen.getByText('Test Product 2')).toBeInTheDocument();
    });
  });

  it('displays empty cart message when no items', async () => {
    vi.mocked(api.getCart).mockResolvedValue([]);
    renderCart();

    await waitFor(() => {
      expect(screen.getByText(/Your cart is empty/)).toBeInTheDocument();
    });
  });

  it('calculates total price correctly', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    renderCart();

    await waitFor(() => {
      // 2 * $99.99 + 1 * $49.99 = $249.97
      expect(screen.getByTestId('cart-total')).toHaveTextContent('$249.97');
    });
  });

  it('updates quantity when increase button clicked', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    vi.mocked(api.updateCartItem).mockResolvedValue(mockCartItems[0]);
    renderCart();

    await waitFor(() => {
      expect(screen.getByTestId('quantity-1')).toHaveTextContent('2');
    });

    const increaseButton = screen.getByTestId('increase-quantity-1');
    fireEvent.click(increaseButton);

    await waitFor(() => {
      expect(api.updateCartItem).toHaveBeenCalledWith(1, 3);
    });
  });

  it('updates quantity when decrease button clicked', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    vi.mocked(api.updateCartItem).mockResolvedValue(mockCartItems[0]);
    renderCart();

    await waitFor(() => {
      expect(screen.getByTestId('quantity-1')).toHaveTextContent('2');
    });

    const decreaseButton = screen.getByTestId('decrease-quantity-1');
    fireEvent.click(decreaseButton);

    await waitFor(() => {
      expect(api.updateCartItem).toHaveBeenCalledWith(1, 1);
    });
  });

  it('disables decrease button when quantity is 1', async () => {
    const singleItemCart = [mockCartItems[1]]; // quantity = 1
    vi.mocked(api.getCart).mockResolvedValue(singleItemCart);
    renderCart();

    await waitFor(() => {
      const decreaseButton = screen.getByTestId('decrease-quantity-2');
      expect(decreaseButton).toBeDisabled();
    });
  });

  it('removes item when remove button clicked and confirmed', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    vi.mocked(api.removeFromCart).mockResolvedValue(undefined);
    global.confirm = vi.fn(() => true);

    renderCart();

    await waitFor(() => {
      expect(screen.getByTestId('cart-item-1')).toBeInTheDocument();
    });

    const removeButton = screen.getByTestId('remove-item-1');
    fireEvent.click(removeButton);

    await waitFor(() => {
      expect(api.removeFromCart).toHaveBeenCalledWith(1);
    });
  });

  it('does not remove item when remove button clicked but not confirmed', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    global.confirm = vi.fn(() => false);

    renderCart();

    await waitFor(() => {
      expect(screen.getByTestId('cart-item-1')).toBeInTheDocument();
    });

    const removeButton = screen.getByTestId('remove-item-1');
    fireEvent.click(removeButton);

    expect(api.removeFromCart).not.toHaveBeenCalled();
  });

  it('clears cart when clear button clicked and confirmed', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    vi.mocked(api.clearCart).mockResolvedValue(undefined);
    global.confirm = vi.fn(() => true);

    renderCart();

    await waitFor(() => {
      expect(screen.getByTestId('clear-cart-button')).toBeInTheDocument();
    });

    const clearButton = screen.getByTestId('clear-cart-button');
    fireEvent.click(clearButton);

    await waitFor(() => {
      expect(api.clearCart).toHaveBeenCalled();
    });
  });

  it('displays product image or placeholder', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    renderCart();

    await waitFor(() => {
      const images = screen.getAllByRole('img');
      expect(images.length).toBeGreaterThan(0);
    });
  });

  it('displays item subtotals correctly', async () => {
    vi.mocked(api.getCart).mockResolvedValue(mockCartItems);
    renderCart();

    await waitFor(() => {
      // Item 1: 2 * $99.99 = $199.98
      expect(screen.getByText('$199.98')).toBeInTheDocument();
      // Item 2: 1 * $49.99 = $49.99
      expect(screen.getByText('$49.99')).toBeInTheDocument();
    });
  });
});
