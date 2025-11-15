# PR1: Role-Based Authentication Implementation

## Summary
Implemented comprehensive role-based authentication system with separate login flows for CUSTOMER, SELLER, and ADMIN roles.

## Backend Changes

### Modified Files

#### `/backend/app/Models/User.php`
- Added `isCustomer()` helper method for consistency with existing `isAdmin()` and `isSeller()` methods
- All three methods now check user role via `hasRole()` function

#### `/backend/app/Http/Controllers/AuthController.php`
- **register()**: Enhanced to accept optional `role` parameter (defaults to 'CUSTOMER')
  - Added validation for `business_name` field (required when role=SELLER)
  - Auto-creates Seller profile when registering as SELLER
  - Loads seller relationship in response for SELLER users
- **login()**: Enhanced to accept optional `role` parameter for portal-specific authentication
  - Returns 403 with message "Role mismatch. Your account does not have access to this login portal" if role doesn't match user's actual role
  - Backward compatible: works without role parameter for general login
  - Loads seller relationship in response for SELLER users

#### `/backend/app/Http/Middleware/EnsureUserHasRole.php` (NEW)
- Created middleware for protecting routes by role
- Takes role parameter (e.g., 'ADMIN', 'SELLER', 'CUSTOMER')
- Returns 401 if not authenticated
- Returns 403 if authenticated but role doesn't match
- Usage: `Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(...)`

#### `/backend/bootstrap/app.php`
- Registered `'role' => \App\Http\Middleware\EnsureUserHasRole::class` middleware alias

#### `/backend/tests/Feature/AuthControllerTest.php`
- Replaced stub with 8 comprehensive test methods:
  1. `test_customer_can_register`: Verifies CUSTOMER registration
  2. `test_seller_can_register_with_business_name`: Verifies SELLER registration creates both User and Seller records
  3. `test_login_returns_token_and_user`: Basic login functionality
  4. `test_login_with_wrong_role_returns_403`: Core role enforcement test
  5. `test_login_with_correct_role_succeeds`: Role-specific login success
  6. `test_login_without_role_succeeds`: Backward compatibility
  7. `test_invalid_credentials_return_422`: Error handling
  8. `test_logout_revokes_token`: Token revocation

**All 8 tests passing ✅**

## Frontend Changes

### New Files

#### `/frontend/src/services/auth.ts`
- AuthService singleton for authentication state management
- Methods: `login()`, `register()`, `logout()`, `isAuthenticated()`, `hasRole()`
- Token and user data stored in localStorage
- Auto-initializes auth state on app load

#### `/frontend/src/services/api.ts` (MODIFIED)
- Updated `User` interface to include `role` field and optional `seller` relationship
- Enhanced `login()` method to accept optional role parameter
- Enhanced `register()` method to accept optional role and business_name parameters

#### `/frontend/src/pages/LoginCustomer.tsx`
- Customer portal login page with indigo theme
- Posts to `/api/auth/login` with `role: 'CUSTOMER'`
- On success, navigates to home page
- Links to customer registration and other portals

#### `/frontend/src/pages/LoginSeller.tsx`
- Seller portal login page with green theme
- Posts to `/api/auth/login` with `role: 'SELLER'`
- On success, navigates to `/seller/dashboard`
- Links to seller registration and other portals

#### `/frontend/src/pages/LoginAdmin.tsx`
- Admin portal login page with red theme
- Posts to `/api/auth/login` with `role: 'ADMIN'`
- On success, navigates to `/admin/dashboard`
- Links to customer and seller portals (no public admin registration)

#### `/frontend/src/pages/RegisterCustomer.tsx`
- Customer registration form
- Fields: name, email, password, password_confirmation
- Posts to `/api/auth/register` with `role: 'CUSTOMER'`
- Links to customer login and seller registration

#### `/frontend/src/pages/RegisterSeller.tsx`
- Seller registration form
- Fields: business_name, name, email, password, password_confirmation
- Posts to `/api/auth/register` with `role: 'SELLER'` and `business_name`
- Links to seller login and customer registration

#### `/frontend/src/pages/SellerDashboard.tsx`
- Placeholder seller dashboard
- Protected: redirects to `/login/seller` if not authenticated as SELLER

#### `/frontend/src/pages/AdminDashboard.tsx`
- Placeholder admin dashboard
- Protected: redirects to `/login/admin` if not authenticated as ADMIN

#### `/frontend/src/App.tsx` (MODIFIED)
- Added routes for all new auth pages:
  - `/login/customer`, `/login/seller`, `/login/admin`
  - `/register/customer`, `/register/seller`
  - `/seller/dashboard`, `/admin/dashboard`

#### `/frontend/src/main.tsx` (MODIFIED)
- Added `authService.initializeAuth()` to restore auth state from localStorage on app load

## API Endpoints

### POST /api/auth/register
**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "CUSTOMER|SELLER",
  "business_name": "My Store" // required if role=SELLER
}
```

**Response (201):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "CUSTOMER",
    "seller": null
  },
  "access_token": "...",
  "token_type": "Bearer"
}
```

### POST /api/auth/login
**Request:**
```json
{
  "email": "john@example.com",
  "password": "password123",
  "role": "CUSTOMER|SELLER|ADMIN" // optional
}
```

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "CUSTOMER",
    "seller": null
  },
  "access_token": "...",
  "token_type": "Bearer"
}
```

**Error Response (403) - Role Mismatch:**
```json
{
  "message": "Role mismatch. Your account does not have access to this login portal."
}
```

## Testing

### Backend Tests
```bash
docker exec myshop-backend php artisan test --filter=AuthControllerTest
```
**Result: 8 tests, 27 assertions - ALL PASSING ✅**

### Manual Testing

#### Test Scenario 1: Customer Registration and Login
```bash
# Register as customer
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Customer","email":"customer@test.com","password":"password","password_confirmation":"password","role":"CUSTOMER"}'

# Login as customer
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@test.com","password":"password","role":"CUSTOMER"}'
```

#### Test Scenario 2: Seller Registration with Business Name
```bash
# Register as seller
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Seller","email":"seller@test.com","password":"password","password_confirmation":"password","role":"SELLER","business_name":"My Store"}'

# Login as seller
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"seller@test.com","password":"password","role":"SELLER"}'
```

#### Test Scenario 3: Role Mismatch (Expected 403)
```bash
# Try to login as admin with customer account
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@test.com","password":"password","role":"ADMIN"}'
```
**Expected:** HTTP 403 with message "Role mismatch..."

### Frontend Testing
1. Navigate to `http://localhost:3000/login/customer`
2. Navigate to `http://localhost:3000/login/seller`
3. Navigate to `http://localhost:3000/login/admin`
4. Navigate to `http://localhost:3000/register/customer`
5. Navigate to `http://localhost:3000/register/seller`

**Expected:** All pages render without TypeScript errors ✅

## Database Schema

No migrations required - using existing `users.role` enum field and `sellers` table.

## Security Considerations

1. **Role Enforcement**: Backend validates role at login, preventing unauthorized portal access
2. **Token-Based Auth**: Uses Laravel Sanctum for stateless API authentication
3. **Password Hashing**: All passwords hashed with bcrypt via Laravel Hash facade
4. **HTTPS Ready**: All auth endpoints should be served over HTTPS in production
5. **Middleware Protection**: Routes can be protected with `role` middleware

## Known Limitations

1. Admin accounts can only be created via seeder (no public registration)
2. Seller verification is not yet implemented (all sellers created with `verified: false`)
3. Dashboard pages are placeholders (will be implemented in PR3)

## Next Steps (PR2)

- Create `AuthSeeder.php` with test users for all roles using @myshop.test email domain
- Ensure at least one admin, 3 sellers, and 2 customers are seeded
- Update `ProductsSeeder.php` to ensure 60+ products with realistic data

## Files Changed

### Backend (6 files)
- `/backend/app/Models/User.php` (modified)
- `/backend/app/Http/Controllers/AuthController.php` (modified)
- `/backend/app/Http/Middleware/EnsureUserHasRole.php` (new)
- `/backend/bootstrap/app.php` (modified)
- `/backend/tests/Feature/AuthControllerTest.php` (modified)

### Frontend (12 files)
- `/frontend/src/services/auth.ts` (new)
- `/frontend/src/services/api.ts` (modified)
- `/frontend/src/pages/LoginCustomer.tsx` (new)
- `/frontend/src/pages/LoginSeller.tsx` (new)
- `/frontend/src/pages/LoginAdmin.tsx` (new)
- `/frontend/src/pages/RegisterCustomer.tsx` (new)
- `/frontend/src/pages/RegisterSeller.tsx` (new)
- `/frontend/src/pages/SellerDashboard.tsx` (new)
- `/frontend/src/pages/AdminDashboard.tsx` (new)
- `/frontend/src/App.tsx` (modified)
- `/frontend/src/main.tsx` (modified)

**Total: 18 files (11 new, 7 modified)**
