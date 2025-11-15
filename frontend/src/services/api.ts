import axios, { AxiosInstance, AxiosError } from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

// Types
export interface User {
  id: number;
  name: string;
  email: string;
  role: 'ADMIN' | 'CUSTOMER' | 'SELLER';
  wallet_address?: string;
  seller?: {
    id: number;
    business_name: string;
    verified: boolean;
  };
}

export interface Product {
  id: number;
  seller_id: number;
  title: string;
  description: string;
  price_cents: number;
  currency: string;
  sku: string;
  image_url?: string;
  category: string;
  tags: string[];
  is_quick_item: boolean;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface CartItem {
  id: number;
  user_id: number;
  product_id: number;
  quantity: number;
  store_id?: number;
  product?: Product;
  created_at: string;
  updated_at: string;
}

export interface GroupBuy {
  id: number;
  product_id: number;
  starter_user_id: number;
  target_size: number;
  team_price_cents: number;
  solo_price_cents: number;
  status: 'ACTIVE' | 'FULFILLED' | 'FAILED' | 'EXPIRED';
  expires_at: string;
  product?: Product;
  member_count?: number;
  progress_percentage?: number;
  savings?: number;
  current_price_cents?: number;
  created_at: string;
  updated_at: string;
}

export interface Order {
  id: number;
  user_id: number;
  seller_id: number;
  fulfillment_type: string;
  status: string;
  total_cents: number;
  currency: string;
  items: OrderItem[];
  created_at: string;
  updated_at: string;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  quantity: number;
  price_cents: number;
  product?: Product;
}

export interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
}

export interface GamificationReward {
  type: string;
  label: string;
  value: number;
  color: string;
  code?: string;
  expires_at?: string;
}

export interface GamificationEvent {
  id: number;
  user_id: number;
  type: string;
  reward: GamificationReward;
  used: boolean;
  used_at?: string;
  created_at: string;
  updated_at: string;
}

export interface SpinResult {
  message: string;
  prize: GamificationReward;
  event_id: number;
  next_spin_at: string;
}

export interface Prize {
  label: string;
  color: string;
  probability: number;
}

export interface AuthResponse {
  user: User;
  access_token: string;
  token_type: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// API Client Class
class ApiClient {
  private client: AxiosInstance;
  private token: string | null = null;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Load token from localStorage
    this.token = localStorage.getItem('auth_token');
    if (this.token) {
      this.setAuthToken(this.token);
    }

    // Response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          this.clearAuth();
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    );
  }

  setAuthToken(token: string) {
    this.token = token;
    this.client.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    localStorage.setItem('auth_token', token);
  }

  clearAuth() {
    this.token = null;
    delete this.client.defaults.headers.common['Authorization'];
    localStorage.removeItem('auth_token');
  }

  isAuthenticated(): boolean {
    return !!this.token;
  }

  // Auth endpoints
  async register(
    name: string,
    email: string,
    password: string,
    role?: 'CUSTOMER' | 'SELLER',
    businessName?: string,
    passwordConfirmation?: string
  ): Promise<AuthResponse> {
    const payload = {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation || password,
      role,
      business_name: businessName,
    };
    
    console.log('Registration payload:', payload);
    
    const response = await this.client.post<AuthResponse>('/auth/register', payload);
    this.setAuthToken(response.data.access_token);
    return response.data;
  }

  async login(email: string, password: string, role?: 'ADMIN' | 'CUSTOMER' | 'SELLER'): Promise<AuthResponse> {
    const response = await this.client.post<AuthResponse>('/auth/login', {
      email,
      password,
      role,
    });
    this.setAuthToken(response.data.access_token);
    return response.data;
  }

  async logout(): Promise<void> {
    await this.client.post('/auth/logout');
    this.clearAuth();
  }

  async getUser(): Promise<User> {
    const response = await this.client.get<User>('/user');
    return response.data;
  }

  // Product endpoints
  async getProducts(params?: {
    page?: number;
    per_page?: number;
    category?: string;
    is_quick_item?: boolean;
  }): Promise<PaginatedResponse<Product>> {
    const response = await this.client.get<PaginatedResponse<Product>>('/products', {
      params,
    });
    return response.data;
  }

  async getProduct(id: number): Promise<Product> {
    const response = await this.client.get<Product>(`/products/${id}`);
    return response.data;
  }

  // Cart endpoints
  async getCart(): Promise<CartItem[]> {
    const response = await this.client.get<{ items: CartItem[] }>('/cart');
    return response.data.items;
  }

  async addToCart(productId: number, quantity: number, storeId?: number): Promise<CartItem> {
    const response = await this.client.post<{ cart_item: CartItem }>('/cart', {
      product_id: productId,
      quantity,
      store_id: storeId,
    });
    return response.data.cart_item;
  }

  async updateCartItem(id: number, quantity: number): Promise<CartItem> {
    const response = await this.client.put<{ cart_item: CartItem }>(`/cart/${id}`, {
      quantity,
    });
    return response.data.cart_item;
  }

  async removeFromCart(id: number): Promise<void> {
    await this.client.delete(`/cart/${id}`);
  }

  async clearCart(): Promise<void> {
    await this.client.delete('/cart');
  }

  // Checkout endpoints
  async checkoutQuickLocal(coordinates: {
    latitude: number;
    longitude: number;
  }): Promise<Order[]> {
    const response = await this.client.post<{ orders: Order[] }>('/checkout/quick-local', {
      fulfillment_type: 'quick_local',
      coordinates,
      payment_method: 'credit_card',
    });
    return response.data.orders;
  }

  async checkoutStandard(shippingAddress: {
    street: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
  }): Promise<Order[]> {
    const response = await this.client.post<{ orders: Order[] }>('/checkout/standard', {
      fulfillment_type: 'standard',
      shipping_address: shippingAddress,
      payment_method: 'credit_card',
    });
    return response.data.orders;
  }

  async checkoutGroupBuy(groupBuyId: number): Promise<Order[]> {
    const response = await this.client.post<{ orders: Order[] }>('/checkout/group-buy', {
      fulfillment_type: 'group_buy',
      group_buy_id: groupBuyId,
      payment_method: 'credit_card',
    });
    return response.data.orders;
  }

  // Group Buy endpoints
  async getGroupBuys(params?: {
    status?: string;
    product_id?: number;
  }): Promise<GroupBuy[]> {
    const response = await this.client.get<PaginatedResponse<GroupBuy>>('/group-buys', {
      params,
    });
    return response.data.data;
  }

  async getGroupBuy(id: number): Promise<GroupBuy> {
    const response = await this.client.get<{ group_buy: GroupBuy }>(`/group-buys/${id}`);
    return response.data.group_buy;
  }

  async createGroupBuy(data: {
    product_id: number;
    target_size: number;
    team_price_cents: number;
    solo_price_cents: number;
    expires_at: string;
  }): Promise<GroupBuy> {
    const response = await this.client.post<{ group_buy: GroupBuy }>('/group-buys', data);
    return response.data.group_buy;
  }

  async joinGroupBuy(id: number): Promise<void> {
    await this.client.post(`/group-buys/${id}/join`);
  }

  async leaveGroupBuy(id: number): Promise<void> {
    await this.client.post(`/group-buys/${id}/leave`);
  }

  // AI Assistant endpoints
  async chatWithAssistant(
    message: string,
    conversationHistory?: ChatMessage[]
  ): Promise<{ reply: string; products?: Product[] }> {
    const response = await this.client.post<{
      response: string;
      products?: Array<{
        id: number;
        title: string;
        description: string;
        price: string;
        category: string;
        similarity_score: number;
      }>;
    }>('/ai/assistant', {
      message,
      conversation_history: conversationHistory,
    });
    
    // If products are returned, fetch full details
    let fullProducts: Product[] | undefined;
    if (response.data.products && response.data.products.length > 0) {
      const productIds = response.data.products.map(p => p.id);
      try {
        const productsResponse = await this.client.get<{ data: Product[] }>('/products', {
          params: { ids: productIds.join(','), limit: productIds.length }
        });
        fullProducts = productsResponse.data.data;
      } catch (error) {
        console.error('Failed to fetch full product details:', error);
        fullProducts = undefined;
      }
    }
    
    return {
      reply: response.data.response,
      products: fullProducts,
    };
  }

  async getRecommendations(params: {
    product_id?: number;
    query?: string;
    n?: number;
    category?: string;
  }): Promise<Product[]> {
    const response = await this.client.get<{ recommendations: Product[] }>(
      '/ai/recommendations',
      { params }
    );
    return response.data.recommendations;
  }

  // Gamification endpoints
  async spin(): Promise<SpinResult> {
    const response = await this.client.post<SpinResult>('/gamification/spin');
    return response.data;
  }

  async getRewards(): Promise<{
    rewards: GamificationEvent[];
    can_spin_today: boolean;
    next_spin_at?: string;
  }> {
    const response = await this.client.get<{
      rewards: GamificationEvent[];
      can_spin_today: boolean;
      next_spin_at?: string;
    }>('/gamification/rewards');
    return response.data;
  }

  async getPrizes(): Promise<Prize[]> {
    const response = await this.client.get<{ prizes: Prize[] }>('/gamification/prizes');
    return response.data.prizes;
  }
}

// Export singleton instance
const api = new ApiClient();
export default api;
