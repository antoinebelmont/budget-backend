# Budget Backend API

Laravel-based REST API for personal budget management.

## Tech Stack

- **Framework**: Laravel 12
- **PHP**: 8.2+
- **Authentication**: Laravel Sanctum
- **Database**: SQLite (default), MySQL/PostgreSQL configurable
- **Testing**: PHPUnit/Pest

## Installation

```bash
cd src
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/auth/me` | Get current user |
| POST | `/api/auth/refresh` | Refresh token |

### Accounts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/accounts` | List accounts |
| POST | `/api/accounts` | Create account |
| GET | `/api/accounts/{id}` | Get account |
| PUT | `/api/accounts/{id}` | Update account |
| DELETE | `/api/accounts/{id}` | Delete account |
| GET | `/api/accounts/for-sidebar` | Sidebar account list |

### Transactions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/transactions` | List transactions (paginated) |
| POST | `/api/transactions` | Create transaction |
| GET | `/api/transactions/{id}` | Get transaction |
| PUT | `/api/transactions/{id}` | Update transaction |
| DELETE | `/api/transactions/{id}` | Delete transaction |
| POST | `/api/transactions/bulk-delete` | Delete multiple |
| POST | `/api/transactions/bulk-update-status` | Update cleared status |
| GET | `/api/transactions/export` | Export CSV |
| POST | `/api/transactions/import` | Import CSV |
| POST | `/api/transaction-goal` | Create transaction with goal |

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/categories` | List categories |
| POST | `/api/categories` | Create category |
| GET | `/api/categories/{id}` | Get category |
| PUT | `/api/categories/{id}` | Update category |
| DELETE | `/api/categories/{id}` | Delete category |
| PUT | `/api/categories/{id}/move` | Move to group |
| PUT | `/api/categories/reorder` | Reorder categories |

### Category Groups

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/category-groups` | List groups |
| POST | `/api/category-groups` | Create group |
| GET | `/api/category-groups/{id}` | Get group |
| PUT | `/api/category-groups/{id}` | Update group |
| DELETE | `/api/category-groups/{id}` | Delete group |
| PUT | `/api/category-groups/reorder` | Reorder groups |

### Payees

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/payees` | List payees |
| POST | `/api/payees` | Create payee |
| GET | `/api/payees/{id}` | Get payee |
| PUT | `/api/payees/{id}` | Update payee |
| DELETE | `/api/payees/{id}` | Delete payee |
| GET | `/api/payees/suggestions` | Search suggestions |

### Goals

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/goals` | List goals |
| POST | `/api/goals` | Create goal |
| GET | `/api/goals/{id}` | Get goal with progress |
| PUT | `/api/goals/{id}` | Update goal |
| DELETE | `/api/goals/{id}` | Delete goal |

### Budget

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/budget` | Monthly budget overview |
| PUT | `/api/categories/{id}/budget` | Update category budget |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reports/spending` | Spending by category/payee |
| GET | `/api/reports/net-worth` | Net worth breakdown |
| GET | `/api/reports/income-vs-expense` | Monthly income vs expenses |
| GET | `/api/reports/budget-vs-actual` | Budget vs actual by month |
| GET | `/api/reports/cash-flow` | Cash flow over time |
| GET | `/api/reports/saved` | List saved reports |
| POST | `/api/reports/saved` | Save report |
| DELETE | `/api/reports/saved/{id}` | Delete saved report |

### User Preferences

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user/preferences` | Get preferences |
| PUT | `/api/user/preferences` | Update preferences |

## Request/Response Examples

### Create Transaction

```json
POST /api/transactions
{
  "account_id": 1,
  "date": "2026-01-15",
  "amount": -50.00,
  "payee_id": 1,
  "category_id": 2,
  "memo": "Grocery shopping",
  "cleared": "cleared"
}
```

### Bulk Update Status

```json
POST /api/transactions/bulk-update-status
{
  "ids": [1, 2, 3],
  "cleared": "reconciled"
}
```

### Budget Response

```json
GET /api/budget?month=2026-01
{
  "month": "2026-01",
  "category_groups": [...],
  "totals": {
    "budgeted": 2500.00,
    "activity": 1800.00,
    "available": 700.00
  }
}
```

## Running Tests

```bash
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

## Data Models

- **User**: id, first_name, last_name, email, password, timezone, currency, preferences
- **Account**: id, user_id, name, type (checking/savings/credit_card/investment), balance
- **Transaction**: id, user_id, account_id, category_id, payee_id, date, amount, memo, cleared, approved, goal_id
- **Category**: id, user_id, category_group_id, name, budgeted, color, hidden, sort_order
- **CategoryGroup**: id, user_id, name, hidden, sort_order
- **Payee**: id, user_id, name, auto_assign_category_id
- **Goal**: id, category_id, type (target_balance/target_date/monthly_funding), target_amount, target_date, monthly_amount
- **SavedReport**: id, user_id, name, type, filters