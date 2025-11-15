import api, { User } from './api';

export interface LoginCredentials {
  email: string;
  password: string;
  role?: 'ADMIN' | 'CUSTOMER' | 'SELLER';
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role?: 'CUSTOMER' | 'SELLER';
  business_name?: string;
}

export interface AuthResponse {
  user: User;
  access_token: string;
  token_type: string;
}

class AuthService {
  private static TOKEN_KEY = 'auth_token';
  private static USER_KEY = 'auth_user';

  /**
   * Login with email and password
   */
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await api.login(
      credentials.email, 
      credentials.password, 
      credentials.role
    );
    
    this.setToken(response.access_token);
    this.setUser(response.user);
    
    return response;
  }

  /**
   * Register new user
   */
  async register(data: RegisterData): Promise<AuthResponse> {
    const response = await api.register(
      data.name,
      data.email,
      data.password,
      data.role,
      data.business_name
    );
    
    this.setToken(response.access_token);
    this.setUser(response.user);
    
    return response;
  }

  /**
   * Logout and clear stored data
   */
  async logout(): Promise<void> {
    try {
      await api.logout();
    } finally {
      this.clearAuth();
    }
  }

  /**
   * Store auth token
   */
  setToken(token: string): void {
    localStorage.setItem(AuthService.TOKEN_KEY, token);
    api.setAuthToken(token);
  }

  /**
   * Get stored token
   */
  getToken(): string | null {
    return localStorage.getItem(AuthService.TOKEN_KEY);
  }

  /**
   * Store user data
   */
  setUser(user: User): void {
    localStorage.setItem(AuthService.USER_KEY, JSON.stringify(user));
  }

  /**
   * Get stored user
   */
  getUser(): User | null {
    const userData = localStorage.getItem(AuthService.USER_KEY);
    return userData ? JSON.parse(userData) : null;
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return !!this.getToken();
  }

  /**
   * Check if current user has specific role
   */
  hasRole(role: 'ADMIN' | 'CUSTOMER' | 'SELLER'): boolean {
    const user = this.getUser();
    return user?.role === role;
  }

  /**
   * Clear all auth data
   */
  clearAuth(): void {
    localStorage.removeItem(AuthService.TOKEN_KEY);
    localStorage.removeItem(AuthService.USER_KEY);
    api.clearAuth();
  }

  /**
   * Initialize auth from stored token
   */
  initializeAuth(): void {
    const token = this.getToken();
    if (token) {
      api.setAuthToken(token);
    }
  }
}

export default new AuthService();
