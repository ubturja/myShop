import React, { useState, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api, { ChatMessage, Product } from '../services/api';

const ChatAssistant: React.FC = () => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      role: 'assistant',
      content: 'Hi! I\'m your shopping assistant. How can I help you today?',
    },
  ]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [recommendations, setRecommendations] = useState<Product[]>([]);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    // Check if user is authenticated
    const token = localStorage.getItem('auth_token');
    setIsAuthenticated(!!token);
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const handleSend = async () => {
    if (!input.trim() || loading) return;

    const userMessage: ChatMessage = {
      role: 'user',
      content: input.trim(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setLoading(true);

    try {
      const response = await api.chatWithAssistant(userMessage.content, messages);
      
      setMessages((prev) => [
        ...prev,
        {
          role: 'assistant',
          content: response.reply,
        },
      ]);

      // If AI recommends products, show them
      if (response.products && response.products.length > 0) {
        setRecommendations(response.products);
      }
    } catch (err: any) {
      setMessages((prev) => [
        ...prev,
        {
          role: 'assistant',
          content: 'Sorry, I encountered an error. Please try again.',
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">AI Shopping Assistant</h1>

      {!isAuthenticated ? (
        <div className="bg-white rounded-lg shadow-md p-8 text-center">
          <svg
            className="mx-auto h-16 w-16 text-gray-400 mb-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Login Required</h2>
          <p className="text-gray-600 mb-6">
            You need to be logged in to use the AI Shopping Assistant.
          </p>
          <Link
            to="/login"
            className="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors"
          >
            Go to Login
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Chat Panel */}
        <div className="lg:col-span-2">
          <div className="bg-white rounded-lg shadow-md overflow-hidden flex flex-col h-[600px]">
            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4" data-testid="chat-messages">
              {messages.map((message, index) => (
                <div
                  key={index}
                  className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                  data-testid={`message-${index}`}
                >
                  <div
                    className={`max-w-[80%] rounded-lg px-4 py-2 ${
                      message.role === 'user'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-900'
                    }`}
                  >
                    <p className="text-sm whitespace-pre-wrap">{message.content}</p>
                  </div>
                </div>
              ))}

              {loading && (
                <div className="flex justify-start">
                  <div className="bg-gray-100 rounded-lg px-4 py-2">
                    <div className="flex space-x-2">
                      <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                      <div
                        className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                        style={{ animationDelay: '0.1s' }}
                      ></div>
                      <div
                        className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                        style={{ animationDelay: '0.2s' }}
                      ></div>
                    </div>
                  </div>
                </div>
              )}

              <div ref={messagesEndRef} />
            </div>

            {/* Input */}
            <div className="border-t p-4">
              <div className="flex gap-2">
                <input
                  type="text"
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyPress={handleKeyPress}
                  placeholder="Ask me anything about products..."
                  className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  disabled={loading}
                  data-testid="chat-input"
                />
                <button
                  onClick={handleSend}
                  disabled={loading || !input.trim()}
                  className="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
                  data-testid="send-button"
                >
                  Send
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Recommendations Panel */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-md p-4">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Recommended Products</h2>

            {recommendations.length === 0 ? (
              <div className="text-center py-8">
                <svg
                  className="mx-auto h-12 w-12 text-gray-400 mb-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                  />
                </svg>
                <p className="text-sm text-gray-600">
                  Ask me for recommendations and I'll show you products!
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                {recommendations.map((product) => (
                  <Link
                    key={product.id}
                    to={`/products/${product.id}`}
                    className="block bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors"
                    data-testid={`recommendation-${product.id}`}
                  >
                    <div className="flex gap-3">
                      <div className="flex-shrink-0">
                        {product.image_url ? (
                          <img
                            src={product.image_url}
                            alt={product.title}
                            className="w-16 h-16 object-cover rounded"
                          />
                        ) : (
                          <div className="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                            <span className="text-gray-400 text-xs">No Image</span>
                          </div>
                        )}
                      </div>

                      <div className="flex-1 min-w-0">
                        <h3 className="font-semibold text-gray-900 text-sm line-clamp-2 mb-1">
                          {product.title}
                        </h3>
                        <p className="text-lg font-bold text-blue-600">
                          {formatPrice(product.price_cents)}
                        </p>
                        {product.is_quick_item && (
                          <span className="inline-block px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded mt-1">
                            Quick Delivery
                          </span>
                        )}
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            )}
          </div>

          {/* Quick Suggestions */}
          <div className="bg-white rounded-lg shadow-md p-4 mt-6">
            <h3 className="font-bold text-gray-900 mb-3">Try asking:</h3>
            <div className="space-y-2">
              {[
                'Show me electronics under $500',
                'What are your best-selling items?',
                'I need a gift for a tech enthusiast',
                'Show me items available for quick delivery',
                'What group buys are active?',
              ].map((suggestion, index) => (
                <button
                  key={index}
                  onClick={() => {
                    setInput(suggestion);
                    // Trigger send after a short delay to ensure input is set
                    setTimeout(() => {
                      const userMessage: ChatMessage = {
                        role: 'user',
                        content: suggestion,
                      };
                      setMessages((prev) => [...prev, userMessage]);
                      setLoading(true);
                      
                      api.chatWithAssistant(suggestion, messages)
                        .then((response) => {
                          setMessages((prev) => [
                            ...prev,
                            {
                              role: 'assistant',
                              content: response.reply,
                            },
                          ]);
                          if (response.products && response.products.length > 0) {
                            setRecommendations(response.products);
                          }
                        })
                        .catch(() => {
                          setMessages((prev) => [
                            ...prev,
                            {
                              role: 'assistant',
                              content: 'Sorry, I encountered an error. Please try again.',
                            },
                          ]);
                        })
                        .finally(() => {
                          setLoading(false);
                          setInput('');
                        });
                    }, 100);
                  }}
                  className="w-full text-left text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-3 py-2 rounded transition-colors"
                  data-testid={`suggestion-${index}`}
                >
                  "{suggestion}"
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>
      )}
    </div>
  );
};

export default ChatAssistant;
