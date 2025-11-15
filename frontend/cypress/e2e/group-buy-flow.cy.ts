/// <reference types="cypress" />

describe('Group Buy User Flow', () => {
  const testEmail = `test${Date.now()}@example.com`;
  const testPassword = 'TestPassword123!';
  const testEmail2 = `test2${Date.now()}@example.com`;

  beforeEach(() => {
    // Set base URL for API
    cy.intercept('POST', '**/api/auth/register').as('register');
    cy.intercept('POST', '**/api/auth/login').as('login');
    cy.intercept('GET', '**/api/products*').as('getProducts');
    cy.intercept('POST', '**/api/cart').as('addToCart');
    cy.intercept('POST', '**/api/group-buys').as('createGroupBuy');
    cy.intercept('POST', '**/api/group-buys/*/join').as('joinGroupBuy');
  });

  it('completes full group buy flow: register -> browse -> add to cart -> start group buy -> join', () => {
    // Step 1: Visit homepage
    cy.visit('/');
    cy.contains('MyShop').should('be.visible');

    // Step 2: Register new user
    cy.contains('Register').click();
    cy.get('input[name="name"]').type('Test User');
    cy.get('input[name="email"]').type(testEmail);
    cy.get('input[name="password"]').type(testPassword);
    cy.get('input[name="password_confirmation"]').type(testPassword);
    cy.get('button[type="submit"]').contains('Register').click();
    
    cy.wait('@register').its('response.statusCode').should('eq', 201);
    cy.contains('Welcome, Test User').should('be.visible');

    // Step 3: Browse products
    cy.visit('/products');
    cy.wait('@getProducts');
    cy.get('[data-testid^="product-card-"]').should('have.length.at.least', 1);

    // Step 4: Click on a product
    cy.get('[data-testid^="product-card-"]').first().click();
    cy.url().should('include', '/products/');
    cy.get('h1').should('be.visible');

    // Step 5: Add to cart
    cy.get('[data-testid="quantity-input"]').clear().type('2');
    cy.get('[data-testid="add-to-cart-button"]').click();
    cy.wait('@addToCart').its('response.statusCode').should('eq', 201);
    cy.on('window:alert', (text) => {
      expect(text).to.contains('Added to cart');
    });

    // Step 6: View cart
    cy.visit('/cart');
    cy.get('[data-testid^="cart-item-"]').should('have.length.at.least', 1);
    cy.get('[data-testid="cart-total"]').should('be.visible');

    // Step 7: Start a group buy from product detail
    cy.go('back'); // Return to product page
    cy.get('[data-testid="start-group-buy-button"]').click();
    
    // Fill in group buy form (if modal appears)
    cy.get('input[name="target_size"]').type('5');
    cy.get('input[name="team_price_cents"]').type('7999');
    cy.get('input[name="expires_at"]').type('2025-12-31T23:59');
    cy.get('button').contains('Create Group Buy').click();
    
    cy.wait('@createGroupBuy').its('response.statusCode').should('eq', 201);
    cy.on('window:alert', (text) => {
      expect(text).to.contains('Group buy created');
    });

    // Step 8: View group buys list
    cy.visit('/group-buys');
    cy.get('[data-testid^="group-buy-card-"]').should('have.length.at.least', 1);

    // Step 9: Verify group buy details
    cy.get('[data-testid^="group-buy-card-"]').first().within(() => {
      cy.contains('1 / 5 members').should('be.visible');
      cy.contains('ACTIVE').should('be.visible');
    });

    // Step 10: Logout and register second user
    cy.contains('Logout').click();
    cy.contains('Register').click();
    cy.get('input[name="name"]').type('Test User 2');
    cy.get('input[name="email"]').type(testEmail2);
    cy.get('input[name="password"]').type(testPassword);
    cy.get('input[name="password_confirmation"]').type(testPassword);
    cy.get('button[type="submit"]').contains('Register').click();
    
    cy.wait('@register').its('response.statusCode').should('eq', 201);

    // Step 11: Join the group buy as second user
    cy.visit('/group-buys');
    cy.get('[data-testid^="join-button-"]').first().click();
    cy.wait('@joinGroupBuy').its('response.statusCode').should('eq', 200);
    cy.on('window:alert', (text) => {
      expect(text).to.contains('Joined group buy');
    });

    // Step 12: Verify member count increased
    cy.reload();
    cy.get('[data-testid^="group-buy-card-"]').first().within(() => {
      cy.contains('2 / 5 members').should('be.visible');
    });

    // Step 13: Complete checkout for group buy
    cy.get('[data-testid^="group-buy-card-"]').first().within(() => {
      cy.get('[data-testid*="id"]').invoke('text').then((id) => {
        cy.visit('/checkout');
        cy.get('[data-testid="fulfillment-group-buy"]').check();
        cy.get('[data-testid="group-buy-id-input"]').type(id);
      });
    });

    // Fill payment details
    cy.get('[data-testid="card-name-input"]').type('Test User 2');
    cy.get('[data-testid="card-number-input"]').type('4242424242424242');
    cy.get('[data-testid="card-expiry-input"]').type('12/25');
    cy.get('[data-testid="card-cvc-input"]').type('123');

    // Place order
    cy.get('[data-testid="place-order-button"]').click();
    cy.on('window:alert', (text) => {
      expect(text).to.contains('Order placed successfully');
    });
  });

  it('allows browsing products without authentication', () => {
    cy.visit('/products');
    cy.wait('@getProducts');
    cy.get('[data-testid^="product-card-"]').should('have.length.at.least', 1);
  });

  it('prevents checkout without authentication', () => {
    cy.visit('/checkout');
    // Should redirect to login
    cy.url().should('include', '/login');
  });

  it('filters products by category', () => {
    cy.visit('/products?category=Electronics');
    cy.wait('@getProducts');
    cy.get('[data-testid^="product-card-"]').each(($card) => {
      cy.wrap($card).should('contain', 'Electronics');
    });
  });

  it('shows quick delivery items', () => {
    cy.visit('/products?quick=true');
    cy.wait('@getProducts');
    cy.get('[data-testid^="product-card-"]').each(($card) => {
      cy.wrap($card).should('contain', 'Quick Delivery');
    });
  });

  it('manages cart items: add, update quantity, remove', () => {
    // Register and login first
    cy.visit('/');
    cy.contains('Register').click();
    cy.get('input[name="name"]').type('Cart Test User');
    cy.get('input[name="email"]').type(`carttest${Date.now()}@example.com`);
    cy.get('input[name="password"]').type(testPassword);
    cy.get('input[name="password_confirmation"]').type(testPassword);
    cy.get('button[type="submit"]').contains('Register').click();
    cy.wait('@register');

    // Add item to cart
    cy.visit('/products');
    cy.wait('@getProducts');
    cy.get('[data-testid^="product-card-"]').first().click();
    cy.get('[data-testid="add-to-cart-button"]').click();
    cy.wait('@addToCart');

    // Go to cart
    cy.visit('/cart');
    cy.get('[data-testid^="cart-item-"]').should('have.length', 1);

    // Increase quantity
    cy.get('[data-testid^="increase-quantity-"]').first().click();
    cy.get('[data-testid^="quantity-"]').first().should('contain', '2');

    // Decrease quantity
    cy.get('[data-testid^="decrease-quantity-"]').first().click();
    cy.get('[data-testid^="quantity-"]').first().should('contain', '1');

    // Remove item
    cy.get('[data-testid^="remove-item-"]').first().click();
    cy.on('window:confirm', () => true);
    cy.contains('Your cart is empty').should('be.visible');
  });
});
