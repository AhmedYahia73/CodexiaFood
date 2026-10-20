# Mobile Application Architecture & Backend Integration Guide
## Complete Technical Specification & AI Development Instructions for Cashier / POS Mobile App

> **Instruction for the Mobile AI Assistant (Cursor / Windsurf / Claude / ChatGPT / Copilot / Gemini):**  
> You are an expert Senior Mobile Application Engineer (Flutter / React Native / Kotlin / Swift).  
> This document is the **single source of truth** for implementing the updated Cashier & POS features on the mobile application for **Mazoom Restaurant POS**.  
> Read all architectural rules, data contracts, and workflows carefully before generating or modifying mobile application code.

---

## 1. 🌐 Global Connection & Environment Settings

- **Production API Base URL**: `https://ecommerce.mazoom.online/api`
- **Authentication**: JWT / Sanctum Bearer Token.
- **Required Global Headers**:
  ```http
  Authorization: Bearer <CASHIER_MAN_JWT_TOKEN>
  Accept: application/json
  Content-Type: application/json
  lang: ar
  ```
  *(Pass `lang: ar` or `lang: en` or the query param `?lang=ar` / `?lang=en` to automatically localize product names, categories, hall names, expense categories, and accounts).*

---

## 2. 🏛️ Core Business Architecture & Key Updates

### A. Terminology & Order Modules
| Backend Key (`module`) | Arabic UI Label | English UI Label | Workflow |
| :--- | :--- | :--- | :--- |
| `dinein` | **طاولة** | Table / Dine-in | Cashier picks a Hall Tab -> selects a Table -> opens/manages that table's cart. |
| `takeaway` | **تيك أواى** | Takeaway | Direct counter pickup order. |
| `delivery` | **دليفري / توصيل** | Delivery | Customer address, phone, and name required. |

### B. Dine-In Hall & Table Workflow
1. User taps **"طاولة"** (Dine-in).
2. Screen displays a horizontal Tab bar of Halls (`GET /api/cashier/halls`).
3. Below the tabs, a responsive grid of Hall Tables (`GET /api/cashier/hall-tables?hall_id=<id>`).
4. Each table item visually indicates:
   - Table name / number.
   - Active status (`has_active_cart: true/false`).
   - Cart item count badge (`cart_items_count: number`).
5. Selecting a table sets the active table context (`hall_table_id`). All cart operations (`GET`, `POST`, `DELETE /cart`) are strictly isolated by `hall_table_id`.
6. At checkout, `hall_table_id` is automatically attached; no need to ask the cashier to re-select the table.

### C. Financial Accounts & Order Balance Auto-Increment
1. **Treasury Integration**: Every order must be assigned a `financial_account_id` representing the cash register or financial treasury collecting the money.
2. **Automatic Balance Increment**:
   $$\text{financial\_accounts.balance} \mathrel{+}= \text{order.final\_price}$$
   *The backend automatically increases the balance of the selected `financial_account` by `final_price` inside the database transaction.*
3. **Self-Healing Fallback**: If `financial_account_id` sent is non-existent or empty, the backend automatically resolves it to the cashier's active shift treasury or the default main treasury.

### D. Shift Management & Deficit Calculation
1. **Start Shift (`POST /api/cashier/start-shift`)**:
   - Cashier selects physical device/desk (`cashier_id`).
   - Cashier enters opening drawer float (`start_amount`).
   - Cashier selects starting treasury (`financial_account_id`).
2. **End Shift (`POST /api/cashier/end-shift`)**:
   - Cashier enters actual counted cash in drawer (`total_mony`).
   - Backend computes the exact deficit:
     $$\text{Deficit} = (\text{orders\_total} + \text{start\_amount}) - \text{expenses\_total} - \text{total\_mony}$$
     *(Where `orders_total` = sum of POS orders delivered/pickup during shift).*
   - Returns a comprehensive `financial_accounts_summary` breaking down every treasury's orders, expenses, starting cash, and net balance.

### E. In-Shift Expenses
- Cashier can record shift operational expenses (`POST /api/cashier/expenses`) by picking an expense list category and treasury account.
- The expense is automatically linked to `shift_id`, `cashier_id`, and `cashier_man_id`.

---

## 3. 📱 Mobile Data Models & DTOs (TypeScript / Dart Compatible)

```typescript
// ==================== FINANCIAL ACCOUNT ====================
export interface FinancialAccount {
  id: number;
  name: string; // Auto-localized according to lang param
  icon: string | null; // Absolute URL to account icon
  balance: number;
}

// ==================== HALL & TABLE ====================
export interface Hall {
  id: number;
  name: string;
  branch_id: number;
  status: boolean;
  tables_count: number;
}

export interface HallTable {
  id: number;
  name: string;
  hall_id: number;
  branch_id: number;
  status: boolean;
  qr: string | null;
  has_active_cart: boolean;
  cart_items_count: number;
}

// ==================== EXPENSE ====================
export interface ExpenseList {
  id: number;
  name: string;
  description?: string | null;
  financial_id: number | null;
  financial_account_id: number | null;
  financial_account?: {
    id: number;
    name: string;
    icon: string | null;
  } | null;
}

export interface ExpenseRecord {
  id: number;
  expense_list_id: number;
  expense_list_name: string;
  financial_id: number;
  financial_account_name: string;
  financial_account?: {
    id: number;
    name: string;
    icon: string | null;
  } | null;
  amount: number;
  description?: string | null;
  shift_id: number;
  cashier_id: number;
  cashier_man_id: number;
  created_at: string;
}

// ==================== SHIFT & SUMMARY ====================
export interface FinancialAccountSummaryItem {
  financial_account_id: number;
  financial_account_name: string;
  orders_total: number;
  expenses_total: number;
  start_amount: number;
  net_amount: number;
}

export interface Shift {
  id: number;
  start_amount: number;
  financial_account_id: number | null;
  financial_account?: FinancialAccount | null;
  financial_accounts_summary?: FinancialAccountSummaryItem[];
  orders_total: number;
  expenses_total: number;
  default_total_amount: number; // start_amount + orders_total - expenses_total
  total_mony: number | null;    // Counted physical cash at end of shift
  deficit: number | null;       // default_total_amount - total_mony
  cashier_id: number;
  cashier_name?: string;
  branch_id: number;
  branch_name?: string;
  cashier_man_id: number;
  cashier_man_name?: string;
  start: string;
  end: string | null;
}
```

---

## 4. 🚀 Comprehensive API Reference

### 4.1. Financial Accounts
#### `GET /api/cashier/financial-accounts`
Returns all active treasury accounts. Multi-tier backend fallback guarantees the list is never empty.
- **Headers**: `Authorization: Bearer <token>`, `lang: ar`
- **Query Parameters**:
  - `lang`: `ar` | `en` (optional)
  - `branch_id`: `int` (optional)
- **Response `200 OK`**:
```json
{
  "status": true,
  "message": "تم جلب الحسابات المالية بنجاح",
  "data": [
    {
      "id": 1,
      "name": "الخزينة النقدية الرئيسية",
      "icon": "https://ecommerce.mazoom.online/storage/accounts/cash.png",
      "balance": 2450.00
    },
    {
      "id": 2,
      "name": "شبكة / فيزا (POS Terminal)",
      "icon": "https://ecommerce.mazoom.online/storage/accounts/visa.png",
      "balance": 1820.50
    }
  ]
}
```

---

### 4.2. Dine-In: Halls & Tables

#### `GET /api/cashier/halls`
Fetches all active dining halls for the branch to render as horizontal tabs.
- **Headers**: `Authorization: Bearer <token>`, `lang: ar`
- **Query Parameters**: `lang=ar|en`
- **Response `200 OK`**:
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "الصالة الأرضية",
      "branch_id": 1,
      "status": true,
      "tables_count": 8
    },
    {
      "id": 2,
      "name": "صالة العائلات (الدور الثاني)",
      "branch_id": 1,
      "status": true,
      "tables_count": 6
    }
  ]
}
```

#### `GET /api/cashier/hall-tables`
Fetches tables, filterable by hall.
- **Headers**: `Authorization: Bearer <token>`, `lang: ar`
- **Query Parameters**:
  - `hall_id`: `integer` (ID of selected hall tab)
  - `lang`: `ar` | `en`
- **Response `200 OK`**:
```json
{
  "status": true,
  "data": [
    {
      "id": 12,
      "name": "طاولة 1",
      "hall_id": 1,
      "branch_id": 1,
      "status": true,
      "qr": "https://ecommerce.mazoom.online/storage/qrcodes/tables/table_12.svg",
      "has_active_cart": true,
      "cart_items_count": 3
    },
    {
      "id": 13,
      "name": "طاولة 2",
      "hall_id": 1,
      "branch_id": 1,
      "status": true,
      "qr": "https://ecommerce.mazoom.online/storage/qrcodes/tables/table_13.svg",
      "has_active_cart": false,
      "cart_items_count": 0
    }
  ]
}
```

---

### 4.3. Cart Operations

#### `GET /api/cashier/cart`
Fetches the active cart for the cashier.
- **Query Parameters**:
  - `module`: `'takeaway'` | `'dinein'` | `'delivery'` (required)
  - `hall_table_id`: `int` (required when `module=dinein`)
  - `lang`: `ar` | `en`
- **Response `200 OK`**:
```json
{
  "status": true,
  "data": [
    {
      "id": 45,
      "product_id": 10,
      "quantity": 2,
      "notes": "بدون بصل",
      "hall_table_id": 12,
      "module": "dinein",
      "product": {
        "id": 10,
        "name": "برجر دجاج كرسبي",
        "price": 85.00,
        "final_price": 96.90
      },
      "variation_carts": [],
      "addon_carts": []
    }
  ],
  "totals": {
    "grand_total_price": 170.00,
    "grand_total_discount": 0.00,
    "grand_total_tax": 23.80,
    "grand_final_price": 193.80
  }
}
```

#### `POST /api/cashier/cart`
Adds an item to cart.
- **Request Body**:
```json
{
  "module": "dinein",
  "hall_table_id": 12,
  "product_id": 10,
  "quantity": 1,
  "notes": "إكسترا صوص",
  "variations": [
    {
      "variation_id": 1,
      "option_ids": [3]
    }
  ],
  "addons": [
    {
      "addon_id": 2
    }
  ]
}
```

#### `DELETE /api/cashier/cart/clear`
Clears items in cart for the current cashier.
- **Query Parameters**:
  - `module`: `'takeaway'` | `'dinein'` | `'delivery'`
  - `hall_table_id`: `int` (required if `module=dinein`)

---

### 4.4. Order Checkout & Payment

#### `POST /api/cashier/orders/checkout?lang=ar`
Submits and creates the order from cart items.
- **Headers**: `Authorization: Bearer <token>`, `Content-Type: application/json`
- **Request Body**:
```json
{
  "module": "dinein",
  "hall_table_id": 12,
  "financial_account_id": 1,
  "name": "عميل صالة",
  "phone": null,
  "address": null,
  "note": "عاجل"
}
```

*Delivery Example:*
```json
{
  "module": "delivery",
  "financial_account_id": 1,
  "name": "محمود حسن",
  "phone": "01012345678",
  "address": "شارع النصر عمارة 4 الدور 2",
  "note": "الباب على اليمين"
}
```

- **Validation Rules**:
  - `module`: `required|in:takeaway,dinein,delivery`
  - `hall_table_id`: `required_if:module,dinein|nullable|integer`
  - `financial_account_id`: `sometimes|nullable|integer` *(auto-healed if omitted or invalid)*
  - `name`: `required_if:module,delivery|nullable|string|max:255`
  - `phone`: `required_if:module,delivery|nullable|string|max:50`
  - `address`: `required_if:module,delivery|nullable|string`

- **Response `201 Created`**:
```json
{
  "status": true,
  "message": "تم إنشاء الطلب بنجاح من السلة",
  "data": {
    "id": 1042,
    "order_number": "#1042",
    "module": "dinein",
    "status": "pickup",
    "hall_table_id": 12,
    "financial_account_id": 1,
    "total": 170.00,
    "total_discount": 0.00,
    "total_tax": 23.80,
    "final_price": 193.80,
    "created_at": "2026-10-20T20:30:00+03:00"
  }
}
```
*(Notice: `financial_accounts.balance` has now automatically incremented by `193.80`).*

---

### 4.5. Shift Lifecycle & Deficit Calculation

#### `POST /api/cashier/start-shift`
Starts a shift for the logged-in cashier.
- **Request Body**:
```json
{
  "cashier_id": 2,
  "start_amount": 250.00,
  "financial_account_id": 1
}
```
- **Response `201 Created`**:
```json
{
  "status": true,
  "message": "تم بدء الشيفت بنجاح",
  "data": {
    "id": 88,
    "start_amount": 250.00,
    "financial_account_id": 1,
    "orders_total": 0.00,
    "expenses_total": 0.00,
    "default_total_amount": 0.00,
    "total_mony": null,
    "deficit": null,
    "cashier_id": 2,
    "cashier_name": "كاشير رقم 1",
    "start": "2026-10-20T14:00:00+03:00",
    "end": null
  }
}
```

#### `GET /api/cashier/check-start-shift`
Checks if the cashier man currently has an open shift.
- **Response `200 OK`**:
```json
{
  "status": true,
  "has_shift": true,
  "data": {
    "id": 88,
    "start_amount": 250.00,
    "financial_account_id": 1,
    "start": "2026-10-20T14:00:00+03:00"
  }
}
```

#### `POST /api/cashier/end-shift`
Ends the open shift and calculates the deficit.
- **Request Body**:
```json
{
  "total_mony": 1400.00
}
```
- **Response `200 OK`**:
```json
{
  "status": true,
  "message": "تم إنهاء الشيفت بنجاح",
  "data": {
    "id": 88,
    "start_amount": 250.00,
    "orders_total": 1350.00,
    "expenses_total": 120.00,
    "default_total_amount": 1480.00,
    "total_mony": 1400.00,
    "deficit": 80.00,
    "financial_accounts_summary": [
      {
        "financial_account_id": 1,
        "financial_account_name": "الخزينة النقدية الرئيسية",
        "orders_total": 950.00,
        "expenses_total": 120.00,
        "start_amount": 250.00,
        "net_amount": 1080.00
      },
      {
        "financial_account_id": 2,
        "financial_account_name": "شبكة / فيزا (POS Terminal)",
        "orders_total": 400.00,
        "expenses_total": 0.00,
        "start_amount": 0.00,
        "net_amount": 400.00
      }
    ],
    "start": "2026-10-20T14:00:00+03:00",
    "end": "2026-10-20T22:00:00+03:00"
  }
}
```

---

### 4.6. Expenses System

#### `GET /api/cashier/expense-lists?lang=ar`
Lists all available expense categories.
- **Response `200 OK`**:
```json
{
  "status": true,
  "message": "تم جلب بنود المصروفات بنجاح",
  "data": [
    {
      "id": 1,
      "name": "نثريات وضيافة",
      "description": "مصاريف شاي وقهوة وضيافة",
      "financial_id": 1,
      "financial_account_id": 1,
      "financial_account": {
        "id": 1,
        "name": "الخزينة النقدية الرئيسية",
        "icon": null
      }
    },
    {
      "id": 2,
      "name": "صيانة طارئة",
      "description": "أدوات كهرباء وسباكة",
      "financial_id": 1,
      "financial_account_id": 1
    }
  ]
}
```

#### `POST /api/cashier/expenses`
Records a new expense during shift.
- **Request Body**:
```json
{
  "expense_list_id": 1,
  "financial_id": 1,
  "amount": 75.00,
  "description": "شراء أدوات نظافة للصالة"
}
```
- **Response `201 Created`**:
```json
{
  "status": true,
  "message": "تم تسجيل المصروف بنجاح",
  "data": {
    "id": 31,
    "expense_list_id": 1,
    "expense_list_name": "نثريات وضيافة",
    "financial_id": 1,
    "financial_account_name": "الخزينة النقدية الرئيسية",
    "amount": 75.00,
    "description": "شراء أدوات نظافة للصالة",
    "shift_id": 88,
    "cashier_id": 2,
    "cashier_man_id": 5,
    "created_at": "2026-10-20T17:15:00+03:00"
  }
}
```

#### `GET /api/cashier/expenses?lang=ar`
Fetches all expenses recorded during the cashier's active shift.
- **Query Parameters**:
  - `shift_id`: `int` (optional, defaults to active shift)
  - `lang`: `ar` | `en`
- **Response `200 OK`**:
```json
{
  "status": true,
  "message": "تم جلب المصروفات بنجاح",
  "data": [
    {
      "id": 31,
      "expense_list_id": 1,
      "expense_list_name": "نثريات وضيافة",
      "financial_id": 1,
      "financial_account_name": "الخزينة النقدية الرئيسية",
      "amount": 75.00,
      "description": "شراء أدوات نظافة للصالة",
      "created_at": "2026-10-20T17:15:00+03:00"
    }
  ],
  "total_amount": 75.00
}
```

---

## 5. 🎨 Mobile UI/UX Flow & Best Practices

1. **Auto-Selection & Pre-filling**:
   - In **Start Shift Screen**: pre-select the first device in `cashiers` and first treasury in `financial-accounts`.
   - In **Checkout Modal / Screen**:
     - Pre-select `financial_account_id` matching `activeShift.financial_account_id`.
     - NEVER hardcode dummy IDs (e.g., `id: 1`) while accounts are still fetching over network.
     - When `module === 'dinein'`, show a badge: `طاولة معتمدة: طاولة 1` and do NOT ask user to choose table again.
2. **Deficit / Surplus Visual Indicators in End Shift**:
   - If `deficit > 0`: Display in **Red** badge with warning: `عجز: 80.00 ر.س` (Cashier drawer has less money than expected).
   - If `deficit === 0`: Display in **Green** badge: `الدرج متطابق تماماً` (No deficit).
   - If `deficit < 0`: Display in **Blue/Amber** badge: `زيادة: X ر.س` (Cashier drawer has surplus).
3. **Table Picker Screen**:
   - Show quick badges on each table card:
     - `🟢 متاح` if `has_active_cart === false`.
     - `🟡 بها طلبية نشطة (3 أصناف)` if `has_active_cart === true`.

---

## 6. 📋 Step-by-Step AI Execution Prompt for Mobile App

Copy and paste the prompt below into the Mobile Project AI:

```markdown
You are updating the Mazoom Mobile POS app according to MOBILE_APP_AI_INSTRUCTIONS.md:

1. Update translations:
   - "سفرى" -> "طاولة" (Dine-in).
   - "محلى" -> "تيك أواى" (Takeaway).
   - "دليفري" -> (Delivery).

2. Implement Dine-in Halls & Tables Tab Flow:
   - Call GET /api/cashier/halls to show halls as top tabs.
   - Call GET /api/cashier/hall-tables?hall_id=<id> to display tables grid.
   - When table is tapped, isolate active cart by hall_table_id.

3. Update Checkout Flow:
   - In checkout dialog, show Financial Account dropdown from GET /api/cashier/financial-accounts.
   - Default selection to activeShift.financial_account_id.
   - Send financial_account_id in POST /api/cashier/orders/checkout.
   - Note: backend automatically increments financial_accounts.balance by order.final_price.

4. Implement Expenses Feature:
   - Expense categories from GET /api/cashier/expense-lists.
   - Record expense via POST /api/cashier/expenses with amount, expense_list_id, financial_id.
   - Display expense history for current shift from GET /api/cashier/expenses.

5. Update Start Shift & End Shift Screens:
   - Start Shift: add financial_account_id and start_amount inputs.
   - End Shift: prompt for total_mony, display deficit, and render financial_accounts_summary breakdown per treasury.
```
