# توثيق واجهات برمجة التطبيقات لنظام الفرع (Branch Role APIs Documentation)

دليل شامل وتفصيلي لكافة واجهات الـ APIs الخاصة بدور الفرع (Prefix: `/api/branch`).
تتطلب هذه الواجهات مصادقة عبر **JWT Token** بدور فرع (`guard = branch`, `role = branch`).
تم تصميم هذه الواجهات بعزل تام لبيانات كل فرع (**Multi-Tenancy Isolation**):
1. **Fetch**: يتم جلب السجلات التي تتبع الفرع فقط `where branch_id = auth()->id()`.
2. **Store**: يتم إسناد `branch_id = auth()->id()` تلقائياً في السيرفر.
3. **Show / Update / Delete**: يتم التحقق الصارم من ملكية السجل للفرع، ومحاولة الوصول لسجل فرع آخر تُرجع خطأ `403 Forbidden`.
4. **Select Options**: القوائم المنسدلة (كاشيرات، شيفتات، صالات، أرصدة مخزون) مفلترة ومخصصة لرصيد وسجلات الفرع الحالي فقط.

---

## 📌 الفهرس العام (Table of Contents)

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

---

## 1. المصادقة والترويسات (Authentication & Headers)

- **Base URL**: `http://your-domain/api/branch`
- **Authentication**: **JWT Bearer Token** (Guard: `branch`)

### 1.1. تسجيل دخول الفرع (Login)
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
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI...",
    "token_type": "bearer",
    "expires_in": 3600,
    "guard": "branch",
    "user": {
      "id": 1,
      "role": "branch",
      "user_name": "branch_user_name",
      "name": {"en": "Main Branch", "ar": "الفرع الرئيسي"}
    }
  }
}
```

### 1.2. الترويسات الإلزامية لجميع الواجهات (Mandatory Headers):
| Header | النوع | الوصف | مثال |
|---|---|---|---|
| `Authorization` | String | توكن الدخول للفرع | `Bearer eyJhbGciOiJIUzI1Ni...` |
| `Accept` | String | نوع الاستجابة | `application/json` |
| `Content-Type` | String | نوع البيانات المرسلة | `application/json` |
| `lang` أو `Accept-Language` | String | لغة الردود والأسماء (`ar` أو `en`) | `ar` |

---

## 2. نقاط الكاشير (Cashiers)

### 2.1. خيارات الكاشير (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/cashiers/select-options`
* **الوصف**: جلب الموظفين والفرع الحالي فقط لإدراجهم في القوائم المنسدلة.

### 2.2. قائمة كاشيرات الفرع (List Cashiers)
* **Method**: `GET`
* **URL**: `/api/branch/cashiers`
* **Query Params**:
  - `page` (integer, opt): رقم الصفحة
  - `per_page` (integer, opt): عدد العناصر بالصفحة (افتراضي 15)

### 2.3. إضافة كاشير جديد للفرع (Create Cashier)
* **Method**: `POST`
* **URL**: `/api/branch/cashiers`
* **الوصف**: يربط الكاشير تلقائياً بالفرع الحالي `branch_id = auth()->id()`.
* **Request Body**:
```json
{
  "name": "كاشير 1 الصالة",
  "cashier_man_id": 2
}
```

### 2.4. تفاصيل كاشير (Show Cashier)
* **Method**: `GET`
* **URL**: `/api/branch/cashiers/{id}`

### 2.5. تعديل كاشير (Update Cashier)
* **Method**: `PUT`
* **URL**: `/api/branch/cashiers/{id}`
* **Request Body**:
```json
{
  "name": "كاشير 1 معدل",
  "cashier_man_id": 3
}
```

### 2.6. حذف كاشير (Delete Cashier)
* **Method**: `DELETE`
* **URL**: `/api/branch/cashiers/{id}`

---

## 3. موظفو الكاشير (Cashier Men)

### 3.1. خيارات موظفي الكاشير (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/cashier-men/select-options`
* **الوصف**: كاشيرات وشيفتات تابعة لهذا الفرع فقط.

### 3.2. قائمة موظفي الكاشير (List Cashier Men)
* **Method**: `GET`
* **URL**: `/api/branch/cashier-men`
* **Query Params**: `page`, `per_page`

### 3.3. إضافة موظف كاشير (Create Cashier Man)
* **Method**: `POST`
* **URL**: `/api/branch/cashier-men`
* **Request Body**:
```json
{
  "name": "محمود سعيد",
  "password": "password123",
  "cashier_id": 1,
  "shift_id": 2
}
```

### 3.4. تفاصيل موظف كاشير (Show Cashier Man)
* **Method**: `GET`
* **URL**: `/api/branch/cashier-men/{id}`

### 3.5. تعديل موظف كاشير (Update Cashier Man)
* **Method**: `PUT`
* **URL**: `/api/branch/cashier-men/{id}`
* **Request Body**:
```json
{
  "name": "محمود سعيد معدل",
  "password": "new_password" (opt),
  "cashier_id": 1,
  "shift_id": 2
}
```

### 3.6. حذف موظف كاشير (Delete Cashier Man)
* **Method**: `DELETE`
* **URL**: `/api/branch/cashier-men/{id}`

---

## 4. لوحة التحكم والإحصائيات (Dashboard)

### 4.1. إحصائيات الفرع السنوية والشهرية (Branch Dashboard Analytics)
* **Method**: `GET`
* **URL**: `/api/branch/dashboard`
* **URL البديل**: `/api/branch/dashboard/statistics`
* **Query Params**:
  - `year` (integer, opt): السنة المراد استخراج الإحصائيات لها (افتراضي السنة الحالية)
* **الوصف**: يستخرج مبيعات وطلبات الفرع الحالي فقط مع أعلى 5 منتجات مبيعاً بالفرع وتفصيل الشهور الـ 12.
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
        "product_name": "وجبة شاورما دجاج",
        "total_quantity": 420
      }
    ],
    "monthly_orders": [...],
    "monthly_final_price": [...],
    "chart": {
      "months": ["January", "February", "..."],
      "orders_count": [120, 180, "..."],
      "final_price": [4200, 6100, "..."]
    }
  }
}
```

---

## 5. طيارو التوصيل (Deliveries)

### 5.1. خيارات التوصيل (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/deliveries/select-options`

### 5.2. قائمة مناديب التوصيل (List Deliveries)
* **Method**: `GET`
* **URL**: `/api/branch/deliveries`
* **Query Params**: `page`, `per_page`

### 5.3. إضافة مندوب توصيل للفرع (Create Delivery)
* **Method**: `POST`
* **URL**: `/api/branch/deliveries`
* **Content-Type**: `multipart/form-data`
* **Request Body**:
  - `name`: "أحمد الطيار" (string, req)
  - `phone`: "01123456789" (string, req)
  - `id_images[]`: صور البطاقة / الرخصة (files, opt)

### 5.4. تفاصيل مندوب توصيل (Show Delivery)
* **Method**: `GET`
* **URL**: `/api/branch/deliveries/{id}`

### 5.5. تعديل مندوب توصيل (Update Delivery)
* **Method**: `PUT` أو `POST`
* **URL**: `/api/branch/deliveries/{id}`
* **Content-Type**: `multipart/form-data`
* **Request Body**: `name`, `phone`, `id_images[]` (opt), `deleted_images[]` (opt)

### 5.6. حذف مندوب توصيل (Delete Delivery)
* **Method**: `DELETE`
* **URL**: `/api/branch/deliveries/{id}`

---

## 6. الحسابات المالية والخزن (Financial Accounts)

### 6.1. خيارات الحسابات المالية (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/financial-accounts/select-options`

### 6.2. قائمة خزن وحسابات الفرع (List Financial Accounts)
* **Method**: `GET`
* **URL**: `/api/branch/financial-accounts`
* **Query Params**: `page`, `per_page`

### 6.3. إضافة حساب مالي / خزينة للفرع (Create Financial Account)
* **Method**: `POST`
* **URL**: `/api/branch/financial-accounts`
* **Request Body**:
```json
{
  "name": {
    "en": "Branch Main Safe",
    "ar": "الخزينة الرئيسية للفرع"
  },
  "balance": 5000.00,
  "status": true
}
```

### 6.4. تفاصيل الخزينة (Show Financial Account)
* **Method**: `GET`
* **URL**: `/api/branch/financial-accounts/{id}`

### 6.5. تعديل الخزينة (Update Financial Account)
* **Method**: `PUT`
* **URL**: `/api/branch/financial-accounts/{id}`

### 6.6. حذف الخزينة (Delete Financial Account)
* **Method**: `DELETE`
* **URL**: `/api/branch/financial-accounts/{id}`

---

## 7. الصالات (Halls)

### 7.1. خيارات الصالات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/halls/select-options`

### 7.2. قائمة صالات الفرع (List Halls)
* **Method**: `GET`
* **URL**: `/api/branch/halls`
* **Query Params**: `page`, `per_page`

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

### 7.4. تفاصيل صالة (Show Hall)
* **Method**: `GET`
* **URL**: `/api/branch/halls/{id}`

### 7.5. تعديل صالة (Update Hall)
* **Method**: `PUT`
* **URL**: `/api/branch/halls/{id}`

### 7.6. حذف صالة (Delete Hall)
* **Method**: `DELETE`
* **URL**: `/api/branch/halls/{id}`

---

## 8. طاولات الصالة ورمز QR (Hall Tables)

### 8.1. خيارات الطاولات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/hall-tables/select-options`

### 8.2. قائمة طاولات الفرع (List Hall Tables)
* **Method**: `GET`
* **URL**: `/api/branch/hall-tables`
* **Query Params**: `page`, `per_page`

### 8.3. إضافة طاولة جديدة وتوليد QR (Create Hall Table)
* **Method**: `POST`
* **URL**: `/api/branch/hall-tables`
* **الوصف**: يقوم بإنشاء الطاولة وتوليد QR Code تلقائياً برابط الـ `base_url` المحدد.
* **Request Body**:
```json
{
  "name": "طاولة رقم 12",
  "hall_id": 1,
  "status": true,
  "base_url": "https://order.restaurant.com"
}
```

### 8.4. تفاصيل الطاولة وصورة الـ QR (Show Hall Table)
* **Method**: `GET`
* **URL**: `/api/branch/hall-tables/{id}`

### 8.5. تعديل الطاولة (Update Hall Table)
* **Method**: `PUT`
* **URL**: `/api/branch/hall-tables/{id}`

### 8.6. حذف الطاولة (Delete Hall Table)
* **Method**: `DELETE`
* **URL**: `/api/branch/hall-tables/{id}`

---

## 9. جرد المواد الخام (Inventory Materials)

### 9.1. خيارات جرد المواد (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/select-options`

### 9.2. فتح جرد مواد خام جديد (Start Material Inventory)
* **Method**: `POST`
* **URL**: `/api/branch/inventory/materials`
* **الوصف**: ينشئ جرد بحالة `pending` ويسحب رصيد كافة المواد الخام الحالي في هذا الفرع تلقائياً.
* **Request Body**:
```json
{
  "name": "جرد نهاية الأسبوع للمواد الخام"
}
```

### 9.3. قائمة عمليات الجرد المعلقة (Pending Inventories)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/pending`
* **Query Params**: `page`, `per_page`

### 9.4. سجل الجرد السابق (Inventory History)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/history`
* **Query Params**: `status` (`approve` أو `reject`), `page`, `per_page`

### 9.5. تفاصيل الجرد والمواد (Show Inventory Details)
* **Method**: `GET`
* **URL**: `/api/branch/inventory/materials/{inventory_id}`

### 9.6. تحديث مجمع للأرصدة الفعلية للجرد (Batch Update Actual Stocks)
* **Method**: `PUT`
* **URL**: `/api/branch/inventory/materials/{inventory_id}/items`
* **Request Body**:
```json
{
  "items": [
    {"id": 10, "actual_stock": 45.5},
    {"id": 11, "actual_stock": 12.0}
  ]
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

### 9.8. اعتماد أو رفض الجرد (Change Status & Apply to Branch Stock)
* **Method**: `PUT`
* **URL**: `/api/branch/inventory/materials/{inventory_id}/status`
* **الوصف**: عند إرسال `approve`، يتم تحديث مخزون الفرع فوراً بالرصيد الفعلي (`actual_stock`).
* **Request Body**:
```json
{
  "status": "approve"
}
```

---

## 10. جرد وصفات المنتجات (Inventory Recipes)

تتبع نفس أسلوب ومسارات جرد المواد الخام، ولكن خاصة بوصفات المنتجات المصنعة (`ProductRecipe`):
- `GET /api/branch/inventory/recipes/select-options`
- `POST /api/branch/inventory/recipes` (Body: `{"name": "جرد وصفات أكتوبر"}`)
- `GET /api/branch/inventory/recipes/pending`
- `GET /api/branch/inventory/recipes/history?status=approve`
- `GET /api/branch/inventory/recipes/{inventory_id}`
- `PUT /api/branch/inventory/recipes/{inventory_id}/items` (Body: `{"items": [{"id": 5, "actual_stock": 20}]}`)
- `PUT /api/branch/inventory/recipe-items/{item_id}` (Body: `{"actual_stock": 22}`)
- `PUT /api/branch/inventory/recipes/{inventory_id}/status` (Body: `{"status": "approve"}`)

---

## 11. المطابخ (Kitchens)

### 11.1. خيارات المطابخ (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/kitchens/select-options`

### 11.2. قائمة مطابخ الفرع (List Kitchens)
* **Method**: `GET`
* **URL**: `/api/branch/kitchens`
* **Query Params**: `page`, `per_page`

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

### 11.4. تفاصيل المطبخ (Show Kitchen)
* **Method**: `GET`
* **URL**: `/api/branch/kitchens/{id}`

### 11.5. تعديل المطبخ (Update Kitchen)
* **Method**: `PUT`
* **URL**: `/api/branch/kitchens/{id}`

### 11.6. حذف المطبخ (Delete Kitchen)
* **Method**: `DELETE`
* **URL**: `/api/branch/kitchens/{id}`

---

## 12. توزيع منتجات المطابخ (Kitchen Products Assignment)

### 12.1. المنتجات غير المعينة لأي مطبخ (Unassigned Products)
* **Method**: `GET`
* **URL**: `/api/branch/kitchen-products/unassigned`
* **Query Params**: `lang` (`ar`/`en`)

### 12.2. المنتجات المعينة لمطبخ في الفرع (Kitchen Assigned Products)
* **Method**: `GET`
* **URL**: `/api/branch/kitchen-products/{kitchen_id}`
* **Query Params**: `lang` (`ar`/`en`)

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

---

## 13. التصنيع وتشغيل الوصفات (Manufacturing)

### 13.1. خيارات ومخزون التصنيع للفرع (Manufacturing Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing/select-options`
* **الوصف**: يعرض المواد الخام ووصفات المنتجات مع **رصيدها المتوفر في هذا الفرع حصراً**.

### 13.2. المواصفة المعيارية للمنتج (Manufacturing Specification)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing/specifications`
* **Query Params**: `product_id` (integer) أو `product_recipe_id` (integer)

### 13.3. سجل عمليات التصنيع بالفرع (List Manufacturing History)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing`
* **Query Params**: `page`, `per_page`

### 13.4. تنفيذ أمر تصنيع بالفرع (Execute Manufacturing)
* **Method**: `POST`
* **URL**: `/api/branch/manufacturing`
* **الوصف**: يفحص توفر المكونات في مخزن الفرع، يخصم المكونات، ويزيد رصيد الصنف المصنع في الفرع.
* **Request Body**:
```json
{
  "product_recipe_id": 4,
  "count": 20,
  "recipes": [
    {"material_id": 1, "count": 5},
    {"material_id": 2, "count": 2}
  ]
}
```

### 13.5. تفاصيل أمر تصنيع (Show Manufacturing Order)
* **Method**: `GET`
* **URL**: `/api/branch/manufacturing/{id}`

---

## 14. الطلبات (Orders)

### 14.1. خيارات الطلبات للفرع (Order Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/orders/select-options`
* **الوصف**: جلب الكاشيرات، الشيفتات، الطاولات، المنتجات والإضافات المتاحة لهذا الفرع.

### 14.2. قائمة طلبات الفرع مع الفلاتر الشاملة (List Orders)
* **Method**: `GET`
* **URL**: `/api/branch/orders`
* **Query Params المتاحة**:
  - `is_pos` (bool / "true" / "false"): نوع الطلب كاشير/صالة أو أونلاين
  - `shift_id` (integer): فلترة برقم الشيفت
  - `module` (string): `takeaway` أو `dinein` أو `delivery`
  - `date` (string: YYYY-MM-DD): تاريخ محدد أو `all`
  - `from_date` / `to_date` (string: YYYY-MM-DD): نطاق تواريخ
  - `all_dates` (bool): جلب كامل السجل التاريخي
  - `page`, `per_page`

### 14.3. طلبات الكاشير والصالة فقط (POS Orders)
* **Method**: `GET`
* **URL**: `/api/branch/orders/pos`

### 14.4. الطلبات الأونلاين والتطبيقات فقط (Online Orders)
* **Method**: `GET`
* **URL**: `/api/branch/orders/online`

### 14.5. فحص الطلبات الجديدة للإشعارات (Check New Orders)
* **Method**: `GET` أو `POST`
* **URL**: `/api/branch/orders/check-new`
* **Request Body / Query**:
```json
{
  "count": 15
}
```

### 14.6. إنشاء طلب جديد بالفرع (Create Order)
* **Method**: `POST`
* **URL**: `/api/branch/orders`
* **Request Body**:
```json
{
  "module": "takeaway",
  "shift_id": 1,
  "cashier_id": 2,
  "cashier_man_id": 3,
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
      "note": "حار زيادة",
      "variations": [
        {
          "variation_id": 1,
          "options": [
            {"option_id": 3, "price": 10.00}
          ]
        }
      ],
      "addons": [
        {"addon_id": 2, "price": 15.00}
      ]
    }
  ]
}
```

### 14.7. تفاصيل طلب (Show Order)
* **Method**: `GET`
* **URL**: `/api/branch/orders/{id}`

### 14.8. تحديث حالة الطلب (Update Status)
* **Method**: `PUT` أو `PATCH` أو `POST`
* **URL**: `/api/branch/orders/{id}/status`
* **الحالات المسموحة**:
  - `delivery`: `done`, `out_of_delivery`, `delivered`, `return`, `failed_to_delivered`
  - `takeaway`: `done`, `pickup`, `return`
  - `dinein`: `return`
* **Request Body**:
```json
{
  "status": "out_of_delivery"
}
```

### 14.9. حذف طلب (Delete Order)
* **Method**: `DELETE`
* **URL**: `/api/branch/orders/{id}`

---

## 15. المشتريات وإضافة الرصيد (Purchases)

### 15.1. خيارات المشتريات وأرصدة الفرع (Purchases Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/purchases/select-options`

### 15.2. فواتير مشتريات الفرع (List Purchases)
* **Method**: `GET`
* **URL**: `/api/branch/purchases`
* **Query Params**: `page`, `per_page`

### 15.3. تسجيل فاتورة مشتريات وتحديث المخزون (Create Purchase)
* **Method**: `POST`
* **URL**: `/api/branch/purchases`
* **Content-Type**: `multipart/form-data`
* **الوصف**: يسجل الفاتورة ويزيد رصيد المواد الخام أو الوصفات بمخزن الفرع الحالي فوراً.
* **Request Body**:
```json
{
  "receipt": "(file - اختياري)",
  "notes": "فاتورة توريد لحوم",
  "items": [
    {
      "material_id": 1,
      "quantity": 25.0,
      "cost": 1250.00
    },
    {
      "product_recipe_id": 3,
      "quantity": 10.0,
      "cost": 300.00
    }
  ]
}
```

### 15.4. تفاصيل فاتورة مشتريات (Show Purchase)
* **Method**: `GET`
* **URL**: `/api/branch/purchases/{id}`

### 15.5. حذف فاتورة المشتريات (Delete Purchase)
* **Method**: `DELETE`
* **URL**: `/api/branch/purchases/{id}`
* **الوصف**: يحذف الفاتورة ويخصم الكميات المضافة سابقاً من مخزون الفرع لاستعادة الرصيد السابق.

---

## 16. تقارير الشيفتات والخزينة (Reports)

### 16.1. خيارات فلترة تقارير الشيفتات (Report Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/reports/start-shifts/select-options`
* **مسارات بديلة**: `/api/branch/start-shifts/select-options`, `/api/branch/reports/start-shifts/lists`

### 16.2. تقرير عهدة وشيفتات الكاشير بالفرع (Start Shift Report)
* **Method**: `GET`
* **URL**: `/api/branch/reports/start-shifts`
* **مسار بديل**: `/api/branch/start-shifts/report`
* **Query Params**:
  - `cashier_id` (integer, opt)
  - `cashier_man_id` (integer, opt)
  - `start` (date/datetime, opt)
  - `end` (date/datetime, opt)
  - `page`, `per_page`
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
  "data": [...],
  "select_options": {...}
}
```

---

## 17. الشيفتات (Shifts)

### 17.1. خيارات الشيفتات (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/shifts/select-options`

### 17.2. قائمة شيفتات الفرع (List Shifts)
* **Method**: `GET`
* **URL**: `/api/branch/shifts`
* **Query Params**: `page`, `per_page`

### 17.3. إنشاء شيفت جديد للفرع (Create Shift)
* **Method**: `POST`
* **URL**: `/api/branch/shifts`
* **الوصف**: يربط الشيفت بالفرع، ويحدد تلقائياً `is_tomorrow` إذا كان ينتهي باليوم التالي.
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

### 17.4. تفاصيل شيفت (Show Shift)
* **Method**: `GET`
* **URL**: `/api/branch/shifts/{id}`

### 17.5. تعديل شيفت (Update Shift)
* **Method**: `PUT`
* **URL**: `/api/branch/shifts/{id}`

### 17.6. حذف شيفت (Delete Shift)
* **Method**: `DELETE`
* **URL**: `/api/branch/shifts/{id}`

---

## 18. الهوالك وإهلاك المخزون (Wastes)

### 18.1. خيارات تسجيل الهالك (Select Options)
* **Method**: `GET`
* **URL**: `/api/branch/wastes/select-options`

### 18.2. قائمة الهوالك المسجلة بالفرع (List Wastes)
* **Method**: `GET`
* **URL**: `/api/branch/wastes`
* **Query Params**: `page`, `per_page`

### 18.3. تسجيل هالك وخصمه من مخزون الفرع (Record Waste)
* **Method**: `POST`
* **URL**: `/api/branch/wastes`
* **الوصف**: يتحقق أولاً من توفر الرصيد في مخزن هذا الفرع ثم يخصمه فوراً.
* **Request Body**:
```json
{
  "material_id": 2,
  "count": 3
}
```
*(أو `product_recipe_id` بدلاً من `material_id`)*

### 18.4. تفاصيل سجل هالك (Show Waste)
* **Method**: `GET`
* **URL**: `/api/branch/wastes/{id}`

### 18.5. تعديل كمية الهالك (Update Waste)
* **Method**: `PUT`
* **URL**: `/api/branch/wastes/{id}`
* **الوصف**: يقوم بتسوية الفارق في مخزون الفرع بالزيادة أو النقصان تلقائياً.
* **Request Body**:
```json
{
  "count": 5
}
```

### 18.6. حذف سجل الهالك (Delete Waste)
* **Method**: `DELETE`
* **URL**: `/api/branch/wastes/{id}`
* **الوصف**: يحذف السجل ويعيد الكمية المهدرة لمخزون الفرع.
