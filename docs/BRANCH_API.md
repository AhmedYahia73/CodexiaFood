# توثيق واجهات برمجة التطبيقات لنظام الفرع والفروع (Branches & Branch Role APIs Documentation)

دليل شامل وتفصيلي لكافة واجهات الـ APIs الخاصة بدور الفرع (`/api/branch/*`) وإدارة الفروع (`/api/admin/branches`).
يوضح هذا المستند لكل واجهة: **الوصف (Description)**، **الرابط والطريقة (Method & URL)**، **الترويسات (Headers)**، **المعاملات (Parameters)**، **جسم الطلب (Request Body)**، و **نماذج الاستجابة الكاملة بالـ JSON (Success & Error Responses)**.

---

## 📌 الفهرس العام (Table of Contents)

0. [إدارة الفروع (Admin Branches CRUD)](#0-إدارة-الفروع-admin-branches-crud)
1. [المصادقة والترويسات (Authentication & Headers)](#1-المصادقة-والترويسات-authentication--headers)
2. [نقاط الكاشير (Cashiers)](#2-نقاط-الكاشير-cashiers)
3. [موظفو الكاشير (Cashier Men)](#3-موظفو-الكاشير-cashier-men)
4. [لوحة التحكم والإحصائيات (Dashboard)](#4-لوحة-التحكم-والإحصائيات-dashboard)
5. [طيارو التوصيل (Deliveries)](#5-طيارو-التوصيل-deliveries)
6. [الحسابات المالية والخزن (Financial Accounts)](#6-الحسابات-المالية-والخزن-financial-accounts)
7. [الصالات (Halls)](#7-الصالات-halls)
8. [طاولات الصالة ورمز QR (Hall Tables)](#8-طاولات-الصالة-ورمز-qr-hall-tables)
9. [جرد المواد الخام (Inventory Materials)](#9-جرد-المواد-الخام-inventory-materials)
10. [جرد وصفات المنتجات (Inventory Recipes)](#10-جرد-وصفات-المنتجات-inventory-recipes)
11. [المطابخ (Kitchens)](#11-المطابخ-kitchens)
12. [توزيع منتجات المطابخ (Kitchen Products Assignment)](#12-توزيع-منتجات-المطابخ-kitchen-products-assignment)
13. [التصنيع وتشغيل الوصفات (Manufacturing)](#13-التصنيع-وتشغيل-الوصفات-manufacturing)
14. [الطلبات (Orders)](#14-الطلبات-orders)
15. [المشتريات وإضافة الرصيد (Purchases)](#15-المشتريات-وإضافة-الرصيد-purchases)
16. [تقارير الشيفتات والخزينة (Reports)](#16-تقارير-الشيفتات-والخزينة-reports)
17. [الشيفتات (Shifts)](#17-الشيفتات-shifts)
18. [الهوالك وإهلاك المخزون (Wastes)](#18-الهوالك-وإهلاك-المخزون-wastes)
19. [الإضافات (Addons)](#19-الإضافات-addons)
20. [الاستجابات العامة للأخطاء (Error Responses)](#20-الاستجابات-العامة-للأخطاء-error-responses)

---

## 1. المصادقة والترويسات (Authentication & Headers)

* **Base URL**: `http://your-domain/api`
* **Authentication**: **JWT Bearer Token**

### 1.1. الترويسات الإلزامية (Mandatory Headers):
| Header | النوع | الوصف | مثال |
|---|---|---|---|
| `Authorization` | String | توكن الدخول الصادر بعد تسجيل الدخول | `Bearer eyJhbGciOiJIUzI1Ni...` |
| `Accept` | String | تحديد صيغة استلام البيانات | `application/json` |
| `Content-Type` | String | صيغة البيانات المرسلة مع طلبات POST/PUT | `application/json` |
| `lang` / `Accept-Language` | String | لغة الردود والأسماء (`ar` أو `en`) | `ar` |

### 1.2. تسجيل دخول الفرع (Branch Login)
* **Method**: `POST`
* **URL**: `/api/auth/login`
* **Request Body**:
```json
{
  "name": "branch_user_name",
  "password": "password123",
  "guard": "branch"
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Successfully authenticated",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "bearer",
    "expires_in": 3600,
    "guard": "branch",
    "user": {
      "id": 1,
      "name": {
        "ar": "فرع المعادي",
        "en": "Maadi Branch"
      },
      "user_name": "branch_user_name",
      "address": "شارع النصر، المعادي",
      "role": "branch"
    }
  }
}
```

---

## 0. إدارة الفروع (Admin Branches CRUD)
> **Controller**: `App\Http\Controllers\api\admin\BranchController`  
> **Auth**: `auth:admin`, `role:admin`

### 0.1. خيارات الفروع للقوائم المنسدلة (Select Options)
* **Method**: `GET`
* **URL**: `/api/admin/branches/select-options`
* **Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [
      {
        "id": 1,
        "name": { "ar": "فرع المعادي", "en": "Maadi Branch" }
      }
    ]
  }
}
```

### 0.2. قائمة الفروع (List Branches)
* **Method**: `GET`
* **URL**: `/api/admin/branches`
* **Query Params**:
  * `page` (integer, opt): رقم الصفحة.
  * `per_page` (integer, opt): عدد العناصر (افتراضي 15).
* **Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": { "ar": "فرع المعادي", "en": "Maadi Branch" },
      "user_name": "branch_maadi",
      "address": "شارع النصر، المعادي",
      "location": [
        { "lat": 29.9602, "lng": 31.2569 }
      ],
      "watts": "01000000000",
      "facebook": "https://facebook.com/branch",
      "status": true,
      "role": "branch",
      "created_at": "2026-10-12T01:58:00+03:00",
      "updated_at": "2026-10-12T01:58:00+03:00"
    }
  ],
  "links": {
    "first": "http://.../api/admin/branches?page=1",
    "last": "http://.../api/admin/branches?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### 0.3. إضافة فرع جديد (Create Branch)
* **Method**: `POST`
* **URL**: `/api/admin/branches`
* **Request Body**:
```json
{
  "name": {
    "ar": "فرع التجمع",
    "en": "Tagamoa Branch"
  },
  "user_name": "branch_tagamoa",
  "password": "Password@123",
  "address": "شارع التسعين",
  "location": [
    { "lat": 30.0298, "lng": 31.4721 }
  ],
  "watts": "+201012345678",
  "facebook": "https://facebook.com/tagamoa",
  "status": true
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Branch created successfully.",
  "data": {
    "id": 2,
    "name": {
      "ar": "فرع التجمع",
      "en": "Tagamoa Branch"
    },
    "user_name": "branch_tagamoa",
    "address": "شارع التسعين",
    "location": [
      { "lat": 30.0298, "lng": 31.4721 }
    ],
    "watts": "+201012345678",
    "facebook": "https://facebook.com/tagamoa",
    "status": true,
    "role": "branch",
    "created_at": "2026-10-20T02:00:00+03:00",
    "updated_at": "2026-10-20T02:00:00+03:00"
  }
}
```

### 0.4. تفاصيل فرع (Show Branch)
* **Method**: `GET`
* **URL**: `/api/admin/branches/{id}`
* **Response (200 OK)**:
```json
{
  "data": {
    "id": 1,
    "name": { "ar": "فرع المعادي", "en": "Maadi Branch" },
    "user_name": "branch_maadi",
    "address": "شارع النصر، المعادي",
    "location": [{ "lat": 29.9602, "lng": 31.2569 }],
    "watts": "01000000000",
    "facebook": "https://facebook.com/branch",
    "status": true,
    "role": "branch",
    "created_at": "2026-10-12T01:58:00+03:00",
    "updated_at": "2026-10-12T01:58:00+03:00"
  }
}
```

### 0.5. تعديل فرع (Update Branch)
* **Method**: `PUT`
* **URL**: `/api/admin/branches/{id}`
* **Request Body**:
```json
{
  "name": {
    "ar": "فرع المعادي الجديد",
    "en": "New Maadi Branch"
  },
  "address": "المعادي الجديدة"
}
```
* **Response (200 OK)**:
```json
{
  "status": true,
  "message": "Branch updated successfully.",
  "data": {
    "id": 1,
    "name": { "ar": "فرع المعادي الجديد", "en": "New Maadi Branch" },
    "user_name": "branch_maadi",
    "address": "المعادي الجديدة",
    "location": [{ "lat": 29.9602, "lng": 31.2569 }],
    "watts": "01000000000",
    "facebook": "https://facebook.com/branch",
    "status": true,
    "role": "branch",
    "created_at": "2026-10-12T01:58:00+03:00",
    "updated_at": "2026-10-20T02:05:00+03:00"
  }
}
```

### 0.6. حذف فرع (Delete Branch)
* **Method**: `DELETE`
* **URL**: `/api/admin/branches/{id}`
* **Response (200 OK)**:
```json
{
  "status": true,
  "message": "Branch deleted successfully."
}
```

---

## 2. نقاط الكاشير (Cashiers)
> **Controller**: `App\Http\Controllers\api\branch\CashierController`  
> **Auth**: `auth:branch`, `role:branch`

### 2.1. خيارات الكاشير (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/cashiers/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [
      { "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }
    ],
    "cashier_men": [
      { "id": 2, "name": "أحمد محمود" }
    ]
  }
}
```

### 2.2. قائمة كاشيرات الفرع (List Cashiers)
* **Method**: `GET`
* **URL**: `/api/branch/cashiers`
* **Query Params**: `page` (opt), `per_page` (opt, default: 15)
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "كاشير الصالة الرئيسي",
      "cashier_man_id": 2,
      "branch_id": 1,
      "branch": {
        "id": 1,
        "name": { "ar": "فرع المعادي", "en": "Maadi Branch" }
      },
      "created_at": "2026-10-16T17:00:00+03:00",
      "updated_at": "2026-10-16T17:00:00+03:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "total": 1 },
  "select_options": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }],
    "cashier_men": [{ "id": 2, "name": "أحمد محمود" }]
  }
}
```

### 2.3. إضافة كاشير جديد للفرع (Create Cashier)
* **Method**: `POST`
* **URL**: `/api/branch/cashiers`
* **Request Body**:
```json
{
  "name": "كاشير التيك أواي 2",
  "cashier_man_id": 2
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Cashier created successfully.",
  "data": {
    "id": 3,
    "name": "كاشير التيك أواي 2",
    "cashier_man_id": 2,
    "branch_id": 1,
    "branch": {
      "id": 1,
      "name": { "ar": "فرع المعادي", "en": "Maadi Branch" }
    },
    "created_at": "2026-10-20T02:10:00+03:00",
    "updated_at": "2026-10-20T02:10:00+03:00"
  },
  "select_options": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }],
    "cashier_men": [{ "id": 2, "name": "أحمد محمود" }]
  }
}
```

### 2.4. تفاصيل كاشير (Show Cashier)
* **Method**: `GET`
* **URL**: `/api/branch/cashiers/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "كاشير الصالة الرئيسي",
    "cashier_man_id": 2,
    "branch_id": 1,
    "branch": {
      "id": 1,
      "name": { "ar": "فرع المعادي", "en": "Maadi Branch" }
    },
    "created_at": "2026-10-16T17:00:00+03:00",
    "updated_at": "2026-10-16T17:00:00+03:00"
  },
  "select_options": { ... }
}
```

### 2.5. تعديل كاشير (Update Cashier)
* **Method**: `PUT`
* **URL**: `/api/branch/cashiers/{id}`
* **Request Body**:
```json
{
  "name": "كاشير الصالة 1 - معدل",
  "cashier_man_id": 2
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Cashier updated successfully.",
  "data": {
    "id": 1,
    "name": "كاشير الصالة 1 - معدل",
    "cashier_man_id": 2,
    "branch_id": 1,
    "created_at": "2026-10-16T17:00:00+03:00",
    "updated_at": "2026-10-20T02:12:00+03:00"
  },
  "select_options": { ... }
}
```

### 2.6. حذف كاشير (Delete Cashier)
* **Method**: `DELETE`
* **URL**: `/api/branch/cashiers/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Cashier deleted successfully."
}
```

---

## 3. موظفو الكاشير (Cashier Men)
> **Controller**: `App\Http\Controllers\api\branch\CashierManController`

### 3.1. خيارات موظفي الكاشير (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/cashier-men/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }],
    "cashiers": [{ "id": 1, "name": "كاشير 1" }],
    "shifts": [
      {
        "id": 1,
        "name": { "ar": "صباحي", "en": "Morning" },
        "branch_id": 1,
        "start_time": "08:00",
        "end_time": "16:00",
        "is_tomorrow": false
      }
    ]
  }
}
```

### 3.2. قائمة موظفي الكاشير (List Cashier Men)
* **Method**: `GET`
* **URL**: `/api/branch/cashier-men`
* **Query Params**: `page`, `per_page`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 2,
      "name": "محمود سعيد",
      "cashier_id": 1,
      "branch_id": 1,
      "shift_id": 1,
      "role": "cashier_man",
      "branch": { ... },
      "cashier": { "id": 1, "name": "كاشير 1" },
      "shift": { "id": 1, "name": { "ar": "صباحي", "en": "Morning" } },
      "created_at": "2026-10-16T17:06:00+03:00",
      "updated_at": "2026-10-16T17:06:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... },
  "select_options": { ... }
}
```

### 3.3. إضافة موظف كاشير (Create Cashier Man)
* **Method**: `POST`
* **URL**: `/api/branch/cashier-men`
* **Request Body**:
```json
{
  "name": "محمود سعيد",
  "password": "password123",
  "cashier_id": 1,
  "shift_id": 1
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Cashier man created successfully.",
  "data": {
    "id": 3,
    "name": "محمود سعيد",
    "cashier_id": 1,
    "branch_id": 1,
    "shift_id": 1,
    "role": "cashier_man",
    "created_at": "2026-10-20T02:15:00+03:00",
    "updated_at": "2026-10-20T02:15:00+03:00"
  },
  "select_options": { ... }
}
```

### 3.4. تفاصيل موظف كاشير (Show Cashier Man)
* **Method**: `GET`
* **URL**: `/api/branch/cashier-men/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 2,
    "name": "محمود سعيد",
    "cashier_id": 1,
    "branch_id": 1,
    "shift_id": 1,
    "role": "cashier_man",
    "branch": { ... },
    "cashier": { ... },
    "shift": { ... }
  },
  "select_options": { ... }
}
```

### 3.5. تعديل موظف كاشير (Update Cashier Man)
* **Method**: `PUT`
* **URL**: `/api/branch/cashier-men/{id}`
* **Request Body**:
```json
{
  "name": "محمود سعيد الألفي",
  "password": "new_password_123"
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Cashier man updated successfully.",
  "data": {
    "id": 2,
    "name": "محمود سعيد الألفي",
    "cashier_id": 1,
    "branch_id": 1,
    "shift_id": 1
  },
  "select_options": { ... }
}
```

### 3.6. حذف موظف كاشير (Delete Cashier Man)
* **Method**: `DELETE`
* **URL**: `/api/branch/cashier-men/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Cashier man deleted successfully."
}
```

---

## 4. لوحة التحكم والإحصائيات (Dashboard)
> **Controller**: `App\Http\Controllers\api\branch\DashboardController`

### 4.1. إحصائيات الفرع السنوية والشهرية (Branch Dashboard Analytics)
* **Method**: `GET`
* **URL**: `/api/branch/dashboard` (أو `/api/branch/dashboard/statistics`)
* **Query Params**: `year` (integer, opt, default: current year)
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "year": 2026,
    "branch_id": 1,
    "total_orders": 2450,
    "total_final_price": 75200.00,
    "top_products": [
      {
        "product_id": 12,
        "product_name": { "ar": "وجبة شاورما دجاج", "en": "Chicken Shawarma" },
        "total_quantity": 420,
        "product": { ... }
      }
    ],
    "monthly_orders": [
      { "month": 1, "month_name": "January", "orders_count": 180 },
      { "month": 2, "month_name": "February", "orders_count": 210 }
    ],
    "monthly_final_price": [
      { "month": 1, "month_name": "January", "total_final_price": 6200.50 }
    ],
    "chart": {
      "months": ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
      "orders_count": [180, 210, 195, 240, 260, 310, 290, 275, 230, 250, 0, 0],
      "final_price": [6200.5, 7100.0, 6800.0, 7900.0, 8400.0, 9500.0, 8900.0, 8500.0, 7800.0, 8100.0, 0, 0]
    }
  }
}
```

---

## 5. طيارو التوصيل (Deliveries)
> **Controller**: `App\Http\Controllers\api\branch\DeliveryController`

### 5.1. خيارات التوصيل (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/deliveries/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }]
  }
}
```

### 5.2. قائمة مناديب التوصيل (List Deliveries)
* **Method**: `GET`
* **URL**: `/api/branch/deliveries`
* **Query Params**: `page`, `per_page`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "أحمد الطيار",
      "phone": "01123456789",
      "id_images": [
        "http://your-domain/storage/deliveries/id_card_front.jpg",
        "http://your-domain/storage/deliveries/id_card_back.jpg"
      ],
      "branch_id": 1,
      "created_at": "2026-10-12T01:59:00+03:00",
      "updated_at": "2026-10-12T01:59:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 5.3. إضافة مندوب توصيل (Create Delivery)
* **Method**: `POST`
* **URL**: `/api/branch/deliveries`
* **Content-Type**: `multipart/form-data`
* **Request Body**:
  * `name`: "أحمد الطيار" (string, req)
  * `phone`: "01123456789" (string, req)
  * `id_images[]`: (files, opt)
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Delivery created successfully.",
  "data": {
    "id": 2,
    "name": "أحمد الطيار",
    "phone": "01123456789",
    "id_images": [
      "http://your-domain/storage/deliveries/front.jpg"
    ],
    "branch_id": 1,
    "created_at": "2026-10-20T02:20:00+03:00",
    "updated_at": "2026-10-20T02:20:00+03:00"
  }
}
```

### 5.4. تفاصيل مندوب توصيل (Show Delivery)
* **Method**: `GET`
* **URL**: `/api/branch/deliveries/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "أحمد الطيار",
    "phone": "01123456789",
    "id_images": ["http://your-domain/storage/deliveries/front.jpg"],
    "branch_id": 1
  }
}
```

### 5.5. تعديل مندوب توصيل (Update Delivery)
* **Method**: `POST` أو `PUT`
* **URL**: `/api/branch/deliveries/{id}`
* **Content-Type**: `multipart/form-data`
* **Request Body**: `name`, `phone`, `id_images[]` (opt), `deleted_images[]` (opt)
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Delivery updated successfully.",
  "data": {
    "id": 1,
    "name": "أحمد الطيار المعدل",
    "phone": "01199999999",
    "id_images": [...]
  }
}
```

### 5.6. حذف مندوب توصيل (Delete Delivery)
* **Method**: `DELETE`
* **URL**: `/api/branch/deliveries/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Delivery deleted successfully."
}
```

---

## 6. الحسابات المالية والخزن (Financial Accounts)
> **Controller**: `App\Http\Controllers\api\branch\FinancialAccountController`

### 6.1. خيارات الحسابات المالية (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/financial-accounts/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }]
  }
}
```

### 6.2. قائمة خزن وحسابات الفرع (List Financial Accounts)
* **Method**: `GET`
* **URL**: `/api/branch/financial-accounts`
* **Query Params**: `page`, `per_page`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": { "ar": "الخزينة الرئيسية للفرع", "en": "Branch Main Safe" },
      "icon": "http://your-domain/storage/icons/safe.png",
      "balance": 15000.50,
      "status": true,
      "branch_id": 1,
      "created_at": "2026-10-12T01:59:00+03:00",
      "updated_at": "2026-10-12T01:59:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 6.3. إضافة حساب مالي / خزينة (Create Financial Account)
* **Method**: `POST`
* **URL**: `/api/branch/financial-accounts`
* **Request Body**:
```json
{
  "name": {
    "en": "Branch Daily Safe",
    "ar": "خزينة يومية الفرع"
  },
  "balance": 5000.00,
  "status": true
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Financial account created successfully.",
  "data": {
    "id": 2,
    "name": {
      "en": "Branch Daily Safe",
      "ar": "خزينة يومية الفرع"
    },
    "balance": 5000.00,
    "status": true,
    "branch_id": 1,
    "created_at": "2026-10-20T02:25:00+03:00",
    "updated_at": "2026-10-20T02:25:00+03:00"
  }
}
```

### 6.4. تفاصيل الخزينة (Show Financial Account)
* **Method**: `GET`
* **URL**: `/api/branch/financial-accounts/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": { "ar": "الخزينة الرئيسية للفرع", "en": "Branch Main Safe" },
    "balance": 15000.50,
    "status": true,
    "branch_id": 1
  }
}
```

### 6.5. تعديل الخزينة (Update Financial Account)
* **Method**: `PUT`
* **URL**: `/api/branch/financial-accounts/{id}`
* **Request Body**:
```json
{
  "name": { "ar": "خزينة الفرع المعدلة", "en": "Updated Safe" },
  "balance": 8000.00
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Financial account updated successfully.",
  "data": {
    "id": 1,
    "name": { "ar": "خزينة الفرع المعدلة", "en": "Updated Safe" },
    "balance": 8000.00
  }
}
```

### 6.6. حذف الخزينة (Delete Financial Account)
* **Method**: `DELETE`
* **URL**: `/api/branch/financial-accounts/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Financial account deleted successfully."
}
```

---

## 7. الصالات (Halls)
> **Controller**: `App\Http\Controllers\api\branch\HallController`

### 7.1. خيارات الصالات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/halls/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }]
  }
}
```

### 7.2. قائمة صالات الفرع (List Halls)
* **Method**: `GET`
* **URL**: `/api/branch/halls`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": { "ar": "الصالة الرئيسية", "en": "Main Dining Area" },
      "status": true,
      "branch_id": 1,
      "created_at": "2026-10-12T01:59:00+03:00",
      "updated_at": "2026-10-12T01:59:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 7.3. إضافة صالة جديدة للفرع (Create Hall)
* **Method**: `POST`
* **URL**: `/api/branch/halls`
* **Request Body**:
```json
{
  "name": {
    "en": "Outdoor Terrace",
    "ar": "التراس الخارجي"
  },
  "status": true
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Hall created successfully.",
  "data": {
    "id": 2,
    "name": {
      "en": "Outdoor Terrace",
      "ar": "التراس الخارجي"
    },
    "status": true,
    "branch_id": 1,
    "created_at": "2026-10-20T02:28:00+03:00",
    "updated_at": "2026-10-20T02:28:00+03:00"
  }
}
```

### 7.4. تفاصيل صالة (Show Hall)
* **Method**: `GET`
* **URL**: `/api/branch/halls/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": { "ar": "الصالة الرئيسية", "en": "Main Dining Area" },
    "status": true,
    "branch_id": 1
  }
}
```

### 7.5. تعديل صالة (Update Hall)
* **Method**: `PUT`
* **URL**: `/api/branch/halls/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Hall updated successfully.",
  "data": {
    "id": 1,
    "name": { "ar": "الصالة الرئيسية VIP", "en": "Main VIP Dining Area" }
  }
}
```

### 7.6. حذف صالة (Delete Hall)
* **Method**: `DELETE`
* **URL**: `/api/branch/halls/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Hall deleted successfully."
}
```

---

## 8. طاولات الصالة ورمز QR (Hall Tables)
> **Controller**: `App\Http\Controllers\api\branch\HallTableController`

### 8.1. خيارات الطاولات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/hall-tables/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }],
    "halls": [{ "id": 1, "name": { "ar": "الصالة الرئيسية", "en": "Main Hall" } }]
  }
}
```

### 8.2. قائمة طاولات الفرع (List Hall Tables)
* **Method**: `GET`
* **URL**: `/api/branch/hall-tables`
* **Query Params**: `page`, `per_page`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "code": "TAB-001",
      "name": "طاولة رقم 1",
      "branch_id": 1,
      "hall_id": 1,
      "status": true,
      "qr": "http://your-domain/storage/qr_codes/table_1_qr.svg",
      "created_at": "2026-10-12T02:15:00+03:00",
      "updated_at": "2026-10-12T02:15:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 8.3. إضافة طاولة جديدة وتوليد QR (Create Hall Table)
* **Method**: `POST`
* **URL**: `/api/branch/hall-tables`
* **Request Body**:
```json
{
  "name": "طاولة رقم 12",
  "hall_id": 1,
  "status": true,
  "base_url": "https://order.restaurant.com"
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Hall table created successfully.",
  "data": {
    "id": 12,
    "code": "TAB-012",
    "name": "طاولة رقم 12",
    "branch_id": 1,
    "hall_id": 1,
    "status": true,
    "qr": "http://your-domain/storage/qr_codes/table_12_qr.svg",
    "created_at": "2026-10-20T02:30:00+03:00",
    "updated_at": "2026-10-20T02:30:00+03:00"
  }
}
```

### 8.4. تفاصيل الطاولة وصورة الـ QR (Show Hall Table)
* **Method**: `GET`
* **URL**: `/api/branch/hall-tables/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "code": "TAB-001",
    "name": "طاولة رقم 1",
    "branch_id": 1,
    "hall_id": 1,
    "status": true,
    "qr": "http://your-domain/storage/qr_codes/table_1_qr.svg"
  }
}
```

### 8.5. تعديل الطاولة (Update Hall Table)
* **Method**: `PUT`
* **URL**: `/api/branch/hall-tables/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Hall table updated successfully.",
  "data": {
    "id": 1,
    "name": "طاولة رقم 1 - معدلة"
  }
}
```

### 8.6. حذف الطاولة (Delete Hall Table)
* **Method**: `DELETE`
* **URL**: `/api/branch/hall-tables/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Hall table deleted successfully."
}
```

---

## 9. جرد المواد الخام (Inventory Materials)
> **Controller**: `App\Http\Controllers\api\branch\InventoryMaterialController`

### 9.1. خيارات جرد المواد (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }]
  }
}
```

### 9.2. فتح جرد مواد خام جديد (Start Material Inventory)
* **Method**: `POST`
* **URL**: `/api/branch/inventory/materials`
* **Request Body**:
```json
{
  "name": "جرد نهاية الأسبوع للمواد الخام"
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Inventory created successfully.",
  "data": {
    "id": 5,
    "name": "جرد نهاية الأسبوع للمواد الخام",
    "status": "pending",
    "branch_id": 1,
    "items_count": 28,
    "created_at": "2026-10-20T02:35:00+03:00"
  }
}
```

### 9.3. قائمة عمليات الجرد المعلقة (Pending Inventories)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/pending`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 5,
      "name": "جرد نهاية الأسبوع للمواد الخام",
      "status": "pending",
      "created_at": "2026-10-20T02:35:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 9.4. سجل الجرد السابق (Inventory History)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/history?status=approve`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 3,
      "name": "جرد شهر سبتمبر للمواد الخام",
      "status": "approve",
      "created_at": "2026-09-30T22:00:00+03:00"
    }
  ]
}
```

### 9.5. تفاصيل الجرد والمواد (Show Inventory Details)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/{inventory_id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 5,
    "name": "جرد نهاية الأسبوع للمواد الخام",
    "status": "pending",
    "branch_id": 1,
    "items": [
      {
        "id": 101,
        "material_id": 2,
        "material_name": "صدور دجاج متبلة",
        "expected_stock": 50.0,
        "actual_stock": 48.0,
        "difference": -2.0
      }
    ]
  }
}
```

### 9.6. تحديث مجمع للأرصدة الفعلية للجرد (Batch Update Actual Stocks)
* **Method**: `PUT`
* **URL**: `/api/branch/inventory/materials/{inventory_id}/items`
* **Request Body**:
```json
{
  "items": [
    { "id": 101, "actual_stock": 48.0 },
    { "id": 102, "actual_stock": 15.5 }
  ]
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Actual stocks updated successfully."
}
```

### 9.7. تحديث الرصيد الفعلي لمادة واحدة (Update Single Item)
* **Method**: `PUT`
* **URL**: `/api/branch/inventory/material-items/{item_id}`
* **Request Body**:
```json
{
  "actual_stock": 48.0
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Item actual stock updated successfully."
}
```

### 9.8. اعتماد أو رفض الجرد (Change Status & Apply to Branch Stock)
* **Method**: `PUT`
* **URL**: `/api/branch/inventory/materials/{inventory_id}/status`
* **Request Body**:
```json
{
  "status": "approve"
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Inventory status updated and branch stock synchronized successfully."
}
```

---

## 10. جرد وصفات المنتجات (Inventory Recipes)
> **Controller**: `App\Http\Controllers\api\branch\InventoryRecipeController`

تتبع نفس أسلوب ومسارات جرد المواد الخام، ولكن خاصة بوصفات المنتجات المصنعة (`ProductRecipe`):
- `GET /api/branch/inventory/recipes/select-options`
- `POST /api/branch/inventory/recipes` (Body: `{"name": "جرد وصفات أكتوبر"}`) -> **201 Created**
- `GET /api/branch/inventory/recipes/pending` -> **200 OK**
- `GET /api/branch/inventory/recipes/history?status=approve` -> **200 OK**
- `GET /api/branch/inventory/recipes/{inventory_id}` -> **200 OK**
- `PUT /api/branch/inventory/recipes/{inventory_id}/items` -> **200 OK**
- `PUT /api/branch/inventory/recipe-items/{item_id}` -> **200 OK**
- `PUT /api/branch/inventory/recipes/{inventory_id}/status` -> **200 OK**

---

## 11. المطابخ (Kitchens)
> **Controller**: `App\Http\Controllers\api\branch\KitchenController`

### 11.1. خيارات المطابخ (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/kitchens/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }]
  }
}
```

### 11.2. قائمة مطابخ الفرع (List Kitchens)
* **Method**: `GET`
* **URL**: `/api/branch/kitchens`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": { "ar": "مطبخ المشويات والوجبات الساخنة", "en": "Grill & Hot Kitchen" },
      "user_name": "grill_kitchen_b1",
      "branch_id": 1,
      "status": true,
      "created_at": "2026-10-12T02:00:00+03:00",
      "updated_at": "2026-10-12T02:00:00+03:00"
    }
  ]
}
```

### 11.3. إضافة مطبخ جديد للفرع (Create Kitchen)
* **Method**: `POST`
* **URL**: `/api/branch/kitchens`
* **Request Body**:
```json
{
  "name": {
    "en": "Cold Kitchen & Salads",
    "ar": "المطبخ البارد والسلطات"
  },
  "user_name": "cold_kitchen_b1",
  "password": "kitchen_pass_123",
  "status": true
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Kitchen created successfully.",
  "data": {
    "id": 2,
    "name": {
      "en": "Cold Kitchen & Salads",
      "ar": "المطبخ البارد والسلطات"
    },
    "user_name": "cold_kitchen_b1",
    "branch_id": 1,
    "status": true,
    "created_at": "2026-10-20T02:40:00+03:00",
    "updated_at": "2026-10-20T02:40:00+03:00"
  }
}
```

### 11.4. تفاصيل المطبخ (Show Kitchen)
* **Method**: `GET`
* **URL**: `/api/branch/kitchens/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": { "ar": "مطبخ المشويات والوجبات الساخنة", "en": "Grill & Hot Kitchen" },
    "user_name": "grill_kitchen_b1",
    "branch_id": 1,
    "status": true
  }
}
```

### 11.5. تعديل المطبخ (Update Kitchen)
* **Method**: `PUT`
* **URL**: `/api/branch/kitchens/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Kitchen updated successfully.",
  "data": {
    "id": 1,
    "name": { "ar": "مطبخ المشويات المعدل", "en": "Updated Grill Kitchen" }
  }
}
```

### 11.6. حذف المطبخ (Delete Kitchen)
* **Method**: `DELETE`
* **URL**: `/api/branch/kitchens/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Kitchen deleted successfully."
}
```

---

## 12. توزيع منتجات المطابخ (Kitchen Products Assignment)
> **Controller**: `App\Http\Controllers\api\branch\KitchenProductController`

### 12.1. المنتجات غير المعينة لأي مطبخ (Unassigned Products)
* **Method**: `GET`
* **URL**: `/api/branch/kitchen-products/unassigned`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 15,
      "name": "عصير برتقال فريش",
      "name_ar": "عصير برتقال فريش",
      "name_en": "Fresh Orange Juice",
      "price": 35.00
    }
  ]
}
```

### 12.2. المنتجات المعينة لمطبخ في الفرع (Kitchen Assigned Products)
* **Method**: `GET`
* **URL**: `/api/branch/kitchen-products/{kitchen_id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "برجر لحم كلاسيك",
      "name_ar": "برجر لحم كلاسيك",
      "name_en": "Classic Beef Burger",
      "price": 120.00
    }
  ]
}
```

### 12.3. ربط وتعيين منتجات بمطبخ (Assign Products to Kitchen)
* **Method**: `POST` أو `PUT`
* **URL**: `/api/branch/kitchen-products/assign`
* **Request Body**:
```json
{
  "kitchen_id": 2,
  "product_ids": [14, 18, 22]
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Products assigned to kitchen successfully."
}
```

---

## 13. التصنيع وتشغيل الوصفات (Manufacturing)
> **Controller**: `App\Http\Controllers\api\branch\ManufactringController`

### 13.1. خيارات ومخزون التصنيع للفرع (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "product_recipes": [
      {
        "id": 4,
        "name": { "ar": "صلصة برجر خاصة", "en": "Special Burger Sauce" },
        "branch_stock": 25.0
      }
    ],
    "materials": [
      {
        "id": 1,
        "name": { "ar": "مايونيز خام", "en": "Raw Mayonnaise" },
        "branch_stock": 50.0
      }
    ]
  }
}
```

### 13.2. المواصفة المعيارية للمنتج (Manufacturing Specification)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing/specifications?product_recipe_id=4`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "recipe_id": 4,
    "recipe_name": "صلصة برجر خاصة",
    "ingredients": [
      {
        "material_id": 1,
        "material_name": "مايونيز خام",
        "standard_quantity_per_unit": 0.25,
        "available_stock": 50.0
      }
    ]
  }
}
```

### 13.3. سجل عمليات التصنيع بالفرع (List Manufacturing)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 10,
      "product_recipe_id": 4,
      "count": 20,
      "branch_id": 1,
      "created_at": "2026-10-18T14:30:00+03:00"
    }
  ]
}
```

### 13.4. تنفيذ أمر تصنيع بالفرع (Execute Manufacturing)
* **Method**: `POST`
* **URL**: `/api/branch/manufacturing`
* **Request Body**:
```json
{
  "product_recipe_id": 4,
  "count": 20,
  "recipes": [
    { "material_id": 1, "count": 5 },
    { "material_id": 2, "count": 2 }
  ]
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Manufacturing completed and stock updated successfully.",
  "data": {
    "id": 11,
    "product_recipe_id": 4,
    "count": 20,
    "branch_id": 1,
    "created_at": "2026-10-20T02:45:00+03:00"
  }
}
```

### 13.5. تفاصيل أمر تصنيع (Show Manufacturing)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 10,
    "product_recipe_id": 4,
    "count": 20,
    "branch_id": 1,
    "items": [
      { "material_id": 1, "consumed_count": 5 }
    ]
  }
}
```

---

## 14. الطلبات (Orders)
> **Controller**: `App\Http\Controllers\api\branch\OrderController`

### 14.1. خيارات الطلبات للفرع (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/orders/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "shifts": [{ "id": 1, "name": { "ar": "صباحي", "en": "Morning" } }],
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }],
    "cashiers": [{ "id": 1, "name": "كاشير 1" }],
    "cashier_men": [{ "id": 2, "name": "أحمد محمود" }],
    "hall_tables": [{ "id": 1, "name": "طاولة 1" }],
    "products": [
      {
        "id": 5,
        "name": { "ar": "ساندوتش فاهيتا", "en": "Fajita Sandwich" },
        "price": 85.00,
        "variations": [...]
      }
    ],
    "addons": [
      { "id": 2, "name": "جبنة إضافية", "price": 15.00 }
    ]
  }
}
```

### 14.2. قائمة طلبات الفرع (List Orders)
* **Method**: `GET`
* **URL**: `/api/branch/orders`
* **Query Params**:
  * `is_pos` (bool): كاشير وصالة أم أونلاين
  * `shift_id` (integer): فلترة بالشيفت
  * `module` (`takeaway`, `dinein`, `delivery`)
  * `date` (YYYY-MM-DD أو `all`)
  * `from_date`, `to_date` (YYYY-MM-DD)
  * `page`, `per_page`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 105,
      "shift_id": 1,
      "shift_name": "صباحي",
      "cashier_id": 1,
      "cashier_man_id": 2,
      "hall_table_id": null,
      "financial_account_id": 1,
      "branch_id": 1,
      "module": "takeaway",
      "name": "كريم أحمد",
      "phone": "01012345678",
      "is_pos": true,
      "status": "preparing",
      "total": 150.00,
      "total_tax": 21.00,
      "total_discount": 0.00,
      "final_price": 171.00,
      "products": [...],
      "created_at": "2026-10-20T02:40:00+03:00",
      "updated_at": "2026-10-20T02:40:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 14.3. فحص الطلبات الجديدة للإشعارات (Check New Orders)
* **Method**: `GET` أو `POST`
* **URL**: `/api/branch/orders/check-new`
* **Request Body / Query**: `{"count": 10}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "server_count": 12,
  "client_count": 10,
  "difference": 2,
  "has_new": true,
  "order_ids": [106, 107]
}
```

### 14.4. إنشاء طلب جديد بالفرع (Create Order)
* **Method**: `POST`
* **URL**: `/api/branch/orders`
* **Request Body**:
```json
{
  "module": "takeaway",
  "shift_id": 1,
  "cashier_id": 1,
  "cashier_man_id": 2,
  "name": "كريم أحمد",
  "phone": "01012345678",
  "is_pos": true,
  "total": 150.00,
  "total_tax": 21.00,
  "total_discount": 0.00,
  "final_price": 171.00,
  "products": [
    {
      "product_id": 5,
      "quantity": 2,
      "price": 75.00,
      "note": "بدون بصل",
      "addons": [
        { "addon_id": 2, "price": 15.00 }
      ]
    }
  ]
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Order created successfully.",
  "data": {
    "id": 108,
    "module": "takeaway",
    "status": "preparing",
    "total": 150.00,
    "final_price": 171.00,
    "branch_id": 1,
    "created_at": "2026-10-20T02:50:00+03:00"
  }
}
```

### 14.5. تحديث حالة الطلب (Update Order Status)
* **Method**: `PUT` أو `POST`
* **URL**: `/api/branch/orders/{id}/status`
* **Request Body**:
```json
{
  "status": "out_of_delivery"
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Order status updated successfully.",
  "data": {
    "id": 108,
    "status": "out_of_delivery"
  }
}
```

### 14.6. حذف طلب (Delete Order)
* **Method**: `DELETE`
* **URL**: `/api/branch/orders/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Order deleted successfully."
}
```

---

## 15. المشتريات وإضافة الرصيد (Purchases)
> **Controller**: `App\Http\Controllers\api\branch\PurchaseController`

### 15.1. خيارات المشتريات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/purchases/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "suppliers": [{ "id": 1, "name": "شركة اللحوم الطازجة" }],
    "materials": [{ "id": 1, "name": { "ar": "لحم بقري مفروم", "en": "Minced Beef" } }],
    "product_recipes": [{ "id": 2, "name": { "ar": "صوص باربكيو", "en": "BBQ Sauce" } }]
  }
}
```

### 15.2. فواتير مشتريات الفرع (List Purchases)
* **Method**: `GET`
* **URL**: `/api/branch/purchases`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "supplier_id": 1,
      "total_cost": 2500.00,
      "total_quantity": 50.0,
      "branch_id": 1,
      "items": [
        {
          "id": 1,
          "material_id": 1,
          "quantity": 50.0,
          "cost": 50.0
        }
      ],
      "created_at": "2026-10-16T22:39:00+03:00"
    }
  ],
  "status": true
}
```

### 15.3. تسجيل فاتورة مشتريات (Create Purchase)
* **Method**: `POST`
* **URL**: `/api/branch/purchases`
* **Content-Type**: `multipart/form-data`
* **Request Body**:
```json
{
  "supplier_id": 1,
  "notes": "فاتورة توريد لحوم أسبوعية",
  "items": [
    {
      "material_id": 1,
      "quantity": 25.0,
      "cost": 1250.00
    }
  ]
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Purchase created successfully.",
  "data": {
    "id": 2,
    "total_cost": 1250.00,
    "total_quantity": 25.0,
    "branch_id": 1,
    "created_at": "2026-10-20T02:55:00+03:00"
  }
}
```

### 15.4. حذف فاتورة مشتريات (Delete Purchase)
* **Method**: `DELETE`
* **URL**: `/api/branch/purchases/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Purchase deleted successfully and stock reverted."
}
```

---

## 16. تقارير الشيفتات والخزينة (Reports)
> **Controller**: `App\Http\Controllers\api\branch\ReportController`

### 16.1. خيارات التقارير (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/reports/start-shifts/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "cashiers": [{ "id": 1, "name": "كاشير 1" }],
    "cashier_men": [{ "id": 2, "name": "أحمد محمود" }]
  }
}
```

### 16.2. تقرير عهدة وشيفتات الكاشير (Start Shift Report)
* **Method**: `GET`
* **URL**: `/api/branch/reports/start-shifts`
* **Query Params**: `cashier_id`, `cashier_man_id`, `start`, `end`, `page`, `per_page`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "summary": {
    "total_default_amount": 12000.00,
    "total_collected_mony": 11950.00,
    "total_deficit": 50.00,
    "shifts_count": 8
  },
  "data": [
    {
      "id": 1,
      "cashier_name": "كاشير 1",
      "cashier_man_name": "أحمد محمود",
      "default_amount": 1500.00,
      "collected_mony": 1500.00,
      "deficit": 0.00,
      "created_at": "2026-10-19T08:00:00+03:00"
    }
  ],
  "select_options": { ... }
}
```

---

## 17. الشيفتات (Shifts)
> **Controller**: `App\Http\Controllers\api\branch\ShiftController`

### 17.1. خيارات الشيفتات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/shifts/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "branches": [{ "id": 1, "name": { "ar": "فرع المعادي", "en": "Maadi Branch" } }]
  }
}
```

### 17.2. قائمة شيفتات الفرع (List Shifts)
* **Method**: `GET`
* **URL**: `/api/branch/shifts`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": { "ar": "الشيفت الصباحي", "en": "Morning Shift" },
      "start_time": "08:00",
      "end_time": "16:00",
      "is_tomorrow": false,
      "branch_id": 1,
      "created_at": "2026-10-12T02:02:00+03:00",
      "updated_at": "2026-10-12T02:02:00+03:00"
    }
  ]
}
```

### 17.3. إنشاء شيفت جديد (Create Shift)
* **Method**: `POST`
* **URL**: `/api/branch/shifts`
* **Request Body**:
```json
{
  "name": {
    "en": "Night Shift",
    "ar": "الشيفت المسائي"
  },
  "start_time": "16:00",
  "end_time": "02:00"
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Shift created successfully.",
  "data": {
    "id": 2,
    "name": {
      "en": "Night Shift",
      "ar": "الشيفت المسائي"
    },
    "start_time": "16:00",
    "end_time": "02:00",
    "is_tomorrow": true,
    "branch_id": 1,
    "created_at": "2026-10-20T03:00:00+03:00",
    "updated_at": "2026-10-20T03:00:00+03:00"
  }
}
```

### 17.4. حذف شيفت (Delete Shift)
* **Method**: `DELETE`
* **URL**: `/api/branch/shifts/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Shift deleted successfully."
}
```

---

## 18. الهوالك وإهلاك المخزون (Wastes)
> **Controller**: `App\Http\Controllers\api\branch\WasteController`

### 18.1. خيارات الهالك (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/wastes/select-options`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "materials": [
      {
        "id": 2,
        "name": { "ar": "طماطم طازجة", "en": "Fresh Tomatoes" },
        "branch_stock": 45.0
      }
    ],
    "product_recipes": [
      {
        "id": 1,
        "name": { "ar": "قطعة برجر جاهزة", "en": "Burger Patty" },
        "branch_stock": 20.0
      }
    ]
  }
}
```

### 18.2. قائمة الهوالك بالفرع (List Wastes)
* **Method**: `GET`
* **URL**: `/api/branch/wastes`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "material_id": 2,
      "product_recipe_id": null,
      "count": 3,
      "branch_id": 1,
      "created_at": "2026-10-12T02:02:00+03:00"
    }
  ]
}
```

### 18.3. تسجيل هالك وخصمه من مخزون الفرع (Record Waste)
* **Method**: `POST`
* **URL**: `/api/branch/wastes`
* **Request Body**:
```json
{
  "material_id": 2,
  "count": 3
}
```
* **Success Response (201 Created)**:
```json
{
  "status": true,
  "message": "Waste recorded successfully.",
  "data": {
    "id": 2,
    "material_id": 2,
    "count": 3,
    "branch_id": 1,
    "created_at": "2026-10-20T03:05:00+03:00"
  },
  "select_options": { ... }
}
```

### 18.4. تعديل كمية الهالك (Update Waste)
* **Method**: `PUT`
* **URL**: `/api/branch/wastes/{id}`
* **Request Body**:
```json
{
  "count": 5
}
```
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Waste updated successfully.",
  "data": {
    "id": 2,
    "count": 5
  }
}
```

### 18.5. حذف سجل الهالك (Delete Waste)
* **Method**: `DELETE`
* **URL**: `/api/branch/wastes/{id}`
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "message": "Waste deleted successfully."
}
```

---

## 19. الإضافات (Addons)
> **Controller**: `App\Http\Controllers\api\branch\AddonController`

### 19.1. خيارات الإضافات (Select Options / Lists)
* **Method**: `GET`
* **URL**: `/api/branch/addons/select-options` (أو `/api/branch/addons/lists`)
* **Success Response (200 OK)**:
```json
{
  "status": true,
  "data": {
    "taxes": [{ "id": 1, "name": "ضريبة القيمة المضافة 14%" }],
    "discounts": [{ "id": 1, "name": "خصم ترويجي 10%" }],
    "kitchens": [{ "id": 1, "name": "مطبخ السناكس" }]
  }
}
```

### 19.2. قائمة الإضافات (List Addons)
* **Method**: `GET`
* **URL**: `/api/branch/addons`
* **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "صوص جبنة شيدر",
      "price": 20.00,
      "kitchen_id": 1,
      "created_at": "2026-10-16T10:59:00+03:00"
    }
  ]
}
```

---

## 20. الاستجابات العامة للأخطاء (Error Responses)

### 20.1. خطأ 401 Unauthenticated (عدم إرسال التوكن أو توكن غير صالح)
```json
{
  "status": false,
  "message": "Unauthenticated."
}
```

### 20.2. خطأ 403 Forbidden (محاولة الوصول لمورد فرع آخر أو دور غير مصرح)
```json
{
  "message": "Unauthorized action: this resource does not belong to your branch."
}
```

### 20.3. خطأ 404 Not Found (العنصر غير موجود)
```json
{
  "message": "No query results for model [App\\Models\\...]."
}
```

### 20.4. خطأ 422 Unprocessable Content (أخطاء التحقق من البيانات Validation Errors)
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "password": [
      "The password field must be at least 6 characters."
    ]
  }
}
```
