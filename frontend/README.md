# MyShop Frontend

React + TypeScript frontend for the MyShop AI-powered group buying platform.

## Components Created

### 1. **ProductList** (`src/components/ProductList.tsx`)
- Displays paginated grid of products
- Filters by category and quick item status
- Responsive layout (1-4 columns)
- Product cards with image, title, price, tags
- Loading and error states

### 2. **ProductDetail** (`src/components/ProductDetail.tsx`)
- Full product information display
- Add to cart with quantity selector
- Start group buy button
- Active group buys for the product
- AI-powered product recommendations
- Join group buy functionality

### 3. **Cart** (`src/components/Cart.tsx`)
- Shopping cart with product list
- Quantity adjustment controls
- Remove items
- Clear cart
- Order summary with total
- Proceed to checkout
- Empty cart state

### 4. **Checkout** (`src/components/Checkout.tsx`)
- Fulfillment type selection:
  - Quick Local Delivery (location-based)
  - Standard Shipping (address form)
  - Group Buy (group buy ID)
- Payment form (credit card)
- Order placement
- Form validation

### 5. **GroupBuyList** (`src/components/GroupBuyList.tsx`)
- List of group buys with filters (Active, Fulfilled, Failed, All)
- Group buy cards with:
  - Progress bar (members/target)
  - Team price vs solo price
  - Savings calculation
  - Time remaining
  - Join/leave buttons
- Status badges

### 6. **ChatAssistant** (`src/components/ChatAssistant.tsx`)
- AI shopping assistant chat interface
- Message history
- Product recommendations from AI
- Quick suggestion buttons
- Real-time responses

## API Service

**`src/services/api.ts`** - Central API client with:
- Token management (localStorage)
- Auth interceptor for 401 handling
- TypeScript interfaces for all data models
- Methods for:
  - Authentication (register, login, logout)
  - Products (list, get details)
  - Cart (add, update, remove, clear)
  - Checkout (quick local, standard, group buy)
  - Group Buys (list, create, join, leave)
  - AI (chat, recommendations)

## Testing

### Unit Tests (Vitest + React Testing Library)

- **ProductList.test.tsx**: Product listing, filtering, pagination
- **Cart.test.tsx**: Cart operations, quantity updates, totals
- **ChatAssistant.test.tsx**: Chat functionality, recommendations

Run unit tests:
```bash
npm run test:unit
```

### E2E Tests (Cypress)

**`cypress/e2e/group-buy-flow.cy.ts`** - Complete user flows:
1. Register → Browse → Add to Cart → Start Group Buy → Join (as second user)
2. Browse products without authentication
3. Prevent checkout without authentication
4. Filter products by category
5. Show quick delivery items
6. Manage cart items (add, update, remove)

Run E2E tests:
```bash
# Open Cypress UI
npm run test:e2e:open

# Run Cypress headless
npm run test:e2e
```

## Setup

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Configure environment:**
   Create `.env` file:
   ```
   VITE_API_BASE_URL=http://localhost:8000/api
   ```

3. **Start development server:**
   ```bash
   npm run dev
   ```
   Access at: http://localhost:5173

## Available Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build
- `npm run test:unit` - Run unit tests
- `npm run test:e2e` - Run E2E tests (headless)
- `npm run test:e2e:open` - Open Cypress UI
- `npm run lint` - Run ESLint
- `npm run type-check` - Run TypeScript type checking

## Project Structure

```
frontend/
├── src/
│   ├── components/
│   │   ├── ProductList.tsx
│   │   ├── ProductList.test.tsx
│   │   ├── ProductDetail.tsx
│   │   ├── Cart.tsx
│   │   ├── Cart.test.tsx
│   │   ├── Checkout.tsx
│   │   ├── GroupBuyList.tsx
│   │   └── ChatAssistant.tsx
│   │   └── ChatAssistant.test.tsx
│   ├── services/
│   │   └── api.ts
│   ├── test/
│   │   └── setup.ts
│   ├── App.tsx
│   ├── main.tsx
│   └── index.css
├── cypress/
│   ├── e2e/
│   │   └── group-buy-flow.cy.ts
│   └── support/
│       └── e2e.ts
├── .env
├── cypress.config.ts
├── vite.config.ts
├── tsconfig.json
├── tailwind.config.js
└── package.json
```

## Technology Stack

- **Framework**: React 18.2
- **Language**: TypeScript 5.2
- **Build Tool**: Vite 5.0
- **Routing**: React Router 6.20
- **HTTP Client**: Axios 1.6
- **Styling**: Tailwind CSS 3.3
- **Unit Testing**: Vitest 1.0 + React Testing Library 14.1
- **E2E Testing**: Cypress 15.5
- **Web3**: ethers.js 6.9, wagmi 1.4, viem 1.19

## Features

### Product Browsing
- Paginated product grid
- Category filtering
- Quick item filtering
- Product detail view
- AI-powered recommendations

### Shopping Cart
- Add to cart
- Update quantities
- Remove items
- Cart total calculation
- Persistent cart state

### Group Buying
- View active group buys
- Create new group buys
- Join existing group buys
- Progress tracking
- Team pricing

### Quick Fulfillment
- Location-based delivery
- Find nearest store
- ETA calculation
- Inventory reservation

### AI Assistant
- Natural language product search
- Personalized recommendations
- Shopping assistance
- Product discovery

### Checkout
- Multiple fulfillment options
- Address management
- Payment processing
- Order confirmation

## API Integration

All API calls go through the centralized `api` service:

```typescript
import api from './services/api';

// Products
const products = await api.getProducts({ page: 1, per_page: 12 });
const product = await api.getProduct(productId);

// Cart
await api.addToCart(productId, quantity);
const cart = await api.getCart();
await api.updateCartItem(itemId, newQuantity);

// Group Buys
const groupBuys = await api.getGroupBuys({ status: 'ACTIVE' });
await api.joinGroupBuy(groupBuyId);

// AI
const response = await api.chatWithAssistant(messages);
const recommendations = await api.getRecommendations({ product_id: 1 });
```

## TypeScript Types

All data models are fully typed:
- `User` - User account
- `Product` - Product details
- `CartItem` - Shopping cart item
- `GroupBuy` - Group buy instance
- `Order` - Order details
- `OrderItem` - Order line item
- `ChatMessage` - Chat message
- `AuthResponse` - Auth response
- `PaginatedResponse<T>` - Paginated data

## Next Steps

1. Add authentication UI (login/register forms)
2. Add user profile management
3. Add order history view
4. Add real-time group buy updates (WebSockets)
5. Add payment processing (Stripe integration)
6. Add Web3 wallet connection
7. Add NFT token display
8. Add provenance tracking UI
9. Add gamification features
10. Add mobile responsive improvements

## Notes

- All components use Tailwind CSS for styling
- All API calls include error handling
- All forms include validation
- Loading states are handled consistently
- Error states display user-friendly messages
- Components are fully typed with TypeScript
- Unit tests cover core functionality
- E2E tests cover critical user flows
