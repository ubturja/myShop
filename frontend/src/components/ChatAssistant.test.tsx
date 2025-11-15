import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ChatAssistant from './ChatAssistant';
import api from '../services/api';

vi.mock('../services/api');

const mockChatResponse = {
  message: 'Here are some great electronics under $500!',
  recommended_product_ids: [1, 2, 3],
};

const mockProduct = {
  id: 1,
  title: 'Test Product',
  description: 'Test description',
  price_cents: 29999,
  sku: 'TEST001',
  category: 'Electronics',
  is_quick_item: true,
  status: 'ACTIVE',
  image_url: 'https://example.com/image.jpg',
  tags: ['featured'],
  store_id: 1,
};

const renderChatAssistant = () => {
  return render(
    <BrowserRouter>
      <ChatAssistant />
    </BrowserRouter>
  );
};

describe('ChatAssistant', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays initial welcome message', () => {
    renderChatAssistant();
    expect(screen.getByText(/Hi! I'm your shopping assistant/)).toBeInTheDocument();
  });

  it('displays empty recommendations panel initially', () => {
    renderChatAssistant();
    expect(screen.getByText(/Ask me for recommendations/)).toBeInTheDocument();
  });

  it('displays quick suggestions', () => {
    renderChatAssistant();
    expect(screen.getByText(/Show me electronics under \$500/)).toBeInTheDocument();
    expect(screen.getByText(/What are your best-selling items?/)).toBeInTheDocument();
  });

  it('sends message when send button clicked', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);
    vi.mocked(api.getProduct).mockResolvedValue(mockProduct);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Show me electronics' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(api.chatWithAssistant).toHaveBeenCalled();
    });
  });

  it('sends message when Enter key pressed', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');

    fireEvent.change(input, { target: { value: 'Show me electronics' } });
    fireEvent.keyPress(input, { key: 'Enter', code: 'Enter', charCode: 13 });

    await waitFor(() => {
      expect(api.chatWithAssistant).toHaveBeenCalled();
    });
  });

  it('displays user message after sending', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Test message' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(screen.getByText('Test message')).toBeInTheDocument();
    });
  });

  it('displays AI response after sending message', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);
    vi.mocked(api.getProduct).mockResolvedValue(mockProduct);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Test message' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(screen.getByText(mockChatResponse.message)).toBeInTheDocument();
    });
  });

  it('displays loading indicator while waiting for response', async () => {
    vi.mocked(api.chatWithAssistant).mockReturnValue(new Promise(() => {}));

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Test message' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      const loadingDots = screen.getAllByRole('generic').find(el => 
        el.className.includes('animate-bounce')
      );
      expect(loadingDots).toBeInTheDocument();
    });
  });

  it('loads recommendations when AI provides product IDs', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);
    vi.mocked(api.getProduct).mockResolvedValue(mockProduct);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Show products' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(api.getProduct).toHaveBeenCalledWith(1);
      expect(api.getProduct).toHaveBeenCalledWith(2);
      expect(api.getProduct).toHaveBeenCalledWith(3);
    });
  });

  it('displays recommended products in sidebar', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);
    vi.mocked(api.getProduct).mockResolvedValue(mockProduct);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Show products' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(screen.getByTestId('recommendation-1')).toBeInTheDocument();
    });
  });

  it('fills input when suggestion clicked', () => {
    renderChatAssistant();

    const suggestion = screen.getByTestId('suggestion-0');
    fireEvent.click(suggestion);

    const input = screen.getByTestId('chat-input') as HTMLInputElement;
    expect(input.value).toContain('Show me electronics under $500');
  });

  it('disables send button when input is empty', () => {
    renderChatAssistant();

    const sendButton = screen.getByTestId('send-button');
    expect(sendButton).toBeDisabled();
  });

  it('disables send button while loading', async () => {
    vi.mocked(api.chatWithAssistant).mockReturnValue(new Promise(() => {}));

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Test' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(sendButton).toBeDisabled();
    });
  });

  it('displays error message when API call fails', async () => {
    vi.mocked(api.chatWithAssistant).mockRejectedValue(new Error('API Error'));

    renderChatAssistant();

    const input = screen.getByTestId('chat-input');
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Test message' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(screen.getByText(/Sorry, I encountered an error/)).toBeInTheDocument();
    });
  });

  it('clears input after sending message', async () => {
    vi.mocked(api.chatWithAssistant).mockResolvedValue(mockChatResponse);

    renderChatAssistant();

    const input = screen.getByTestId('chat-input') as HTMLInputElement;
    const sendButton = screen.getByTestId('send-button');

    fireEvent.change(input, { target: { value: 'Test message' } });
    fireEvent.click(sendButton);

    await waitFor(() => {
      expect(input.value).toBe('');
    });
  });
});
