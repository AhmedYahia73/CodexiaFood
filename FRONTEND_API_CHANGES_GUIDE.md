# الدليل الشامل لتحديثات الـ API لفريق الـ Frontend
## Comprehensive Frontend API Integration Guide: Units, Addon Stock & Inventory, and Purchases

> **ملاحظة لفريق الـ Frontend (Web & Mobile):**
> يوثق هذا الملف جميع التعديلات والإضافات التي تمت على الـ Backend APIs في النظام، وتحديداً:
> 1. **نظام إدارة وحدات القياس (Units Management CRUD)**.
> 2. **إلزامية واختيارية `unit_id` والتحويل الرياضي التلقائي للوحدات عبر المخازن والتصنيع**.
> 3. **نظام مخزون وجرد الإضافات (Addon Stocks & Inventory)** بمحاكاة مطابقة لجرد المواد الخام.
> 4. **شراء الإضافات (Addons) عبر فواتير المشتريات (Purchases)** وتأثيرها المباشر على المخزون واسترجاعه.
>
> تم تنظيم كل نقطة بنهاية (Endpoint)، والـ Headers، والـ Params، والـ Request Body، والـ Response المتوقع.

---

## 📌 جدول المحتويات (Table of Contents)
1. [قواعد هندسية هامة (Critical Architecture Rules)](#1-قواعد-هندسية-هامة-critical-architecture-rules)
2. [معادلة تحويل الوحدات الرياضية (Unit Conversion Logic)](#2-معادلة-تحويل-الوحدات-الرياضية-unit-conversion-logic)
3. [الموديول الأول: إدارة وحدات القياس (Units CRUD)](#3-الموديول-الأول-إدارة-وحدات-القياس-units-crud)
4. [الموديول الثاني: مواصفات وتصنيع المنتجات (Product Manufacturing & Specs)](#4-الموديول-الثاني-مواصفات-وتصنيع-المنتجات-product-manufacturing--specs)
5. [الموديول الثالث: المشتريات ودعم الإضافات (Purchases & Addons Buying)](#5-الموديول-الثالث-المشتريات-ودعم-الإضافات-purchases--addons-buying)
6. [الموديول الرابع: جرد ومخزون الإضافات (Addon Inventory & Stock)](#6-الموديول-الرابع-جرد-ومخزون-الإضافات-addon-inventory--stock)
7. [الموديول الخامس: تحديثات جرد المواد الخام والوصفات (Materials & Recipes Inventory)](#7-الموديول-الخامس-تحديثات-جرد-المواد-الخام-والوصفات-materials--recipes-inventory)
8. [نماذج TypeScript الكاملة (TypeScript Interfaces & Types)](#8-نماذج-typescript-الكاملة-typescript-interfaces--types)

---

## 1. قواعد هندسية هامة (Critical Architecture Rules)

1. **عزل المخزون حسب الفروع (Branch Stock Isolation)**:
   - المخزون مسجل على مستوى الفرع لكل من: المواد الخام (`material_stocks`)، وصفات المنتجات (`product_recipe_stocks`)، والإضافات (`addon_stocks`).
2. **صلاحيات الـ Select Options لكل شاشة**:
   - لا تستدعِ نقطة مشتركة مثل `/branches/select-options` من كل الشاشات لتفادي خطأ `403 Forbidden`.
   - كل شاشة تملك نقطة `select-options` خاصة بها تعيد الفروع والوحدات والعناصر برصيد الفرع الحالي.
3. **الـ Headers الأساسية لجميع الطلبات**:
   ```http
   Authorization: Bearer <token>
   Accept: application/json
   Content-Type: application/json
   Accept-Language: ar // أو en (للتحكم في لغة الأسماء المترجمة)
   ```

---

## 2. معادلة تحويل الوحدات الرياضية (Unit Conversion Logic)

يقوم الباك إند بالتحويل التلقائي والدقيق للكميات بين وحدة الإدخال ووحدة المخزون استناداً لقيمة `value` في جدول `units`:
$$\text{الكمية المحولة} = \text{الكمية المدخلة} \times \left( \frac{\text{قيمة وحدة الإدخال}}{\text{قيمة وحدة المخزون}} \right)$$

*مثال:*
- وحدة الجرام (`Gram`): قيمتها `1`
- وحدة الكيلوجرام (`KG`): قيمتها `1000`
- وحدة الكرتونة (`Box`): قيمتها `12` (تحتوي على 12 قطعة)

عند شراء `2` كرتونة من إضافة معينة مخزونها بالقطعة (`value = 1`):
$$2 \times \left( \frac{12}{1} \right) = 24 \text{ قطعة تضاف آلياً للمخزون}$$

---

## 3. الموديول الأول: إدارة وحدات القياس (Units CRUD)

موديول جديد بالكامل مخصص للأدمن لإدارة وحدات القياس (`Unit`).

### 1.1 استعراض الوحدات (List Units)
- **Method**: `GET`
- **URL**: `/api/admin/units`
- **Headers**:
  - `Authorization: Bearer <admin_token>`
  - `Accept: application/json`
- **Query Params**:
  - `page` (اختياري، integer): رقم الصفحة.
  - `per_page` (اختياري، integer): عدد العناصر بالصفحة (افتراضي `15`، أو `-1` لجميع العناصر).
  - `search` (اختياري، string): البحث بالاسم.
- **Response (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": { "ar": "كيلوجرام", "en": "Kilogram" },
        "value": 1000.0,
        "created_at": "2026-10-31T15:00:00Z",
        "updated_at": "2026-10-31T15:00:00Z"
      },
      {
        "id": 2,
        "name": { "ar": "جرام", "en": "Gram" },
        "value": 1.0,
        "created_at": "2026-10-31T15:00:00Z",
        "updated_at": "2026-10-31T15:00:00Z"
      }
    ],
    "links": { ... },
    "meta": { ... }
  }
  ```

---

### 1.2 إضافة وحدة جديدة (Create Unit)
- **Method**: `POST`
- **URL**: `/api/admin/units`
- **Headers**:
  - `Authorization: Bearer <admin_token>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body**:
  ```json
  {
    "name": {
      "ar": "كرتونة",
      "en": "Box"
    },
    "value": 12
  }
  ```
  *(ملاحظة: حقل `name` يقبل كائن مترجم `{ ar, en }` أو نص عادي `string`).*
- **Validation**:
  - `name`: مطلوب (`required`).
  - `value`: اختياري، رقمي موجب (`numeric|min:0.0001`، افتراضي `1`).
- **Response (201 Created)**:
  ```json
  {
    "status": true,
    "message": "تم إضافة الوحدة بنجاح",
    "data": {
      "id": 3,
      "name": { "ar": "كرتونة", "en": "Box" },
      "value": 12.0,
      "created_at": "2026-10-31T15:10:00Z",
      "updated_at": "2026-10-31T15:10:00Z"
    }
  }
  ```

---

### 1.3 عرض تفاصيل وحدة (Show Unit)
- **Method**: `GET`
- **URL**: `/api/admin/units/{unit_id}`
- **Response (200 OK)**:
  ```json
  {
    "status": true,
    "data": {
      "id": 3,
      "name": { "ar": "كرتونة", "en": "Box" },
      "value": 12.0
    }
  }
  ```

---

### 1.4 تعديل وحدة (Update Unit)
- **Method**: `PUT` أو `PATCH`
- **URL**: `/api/admin/units/{unit_id}`
- **Request Body**:
  ```json
  {
    "name": { "ar": "كرتونة كبيرة", "en": "Large Box" },
    "value": 24
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم تحديث الوحدة بنجاح",
    "data": { ... }
  }
  ```

---

### 1.5 حذف وحدة (Delete Unit)
- **Method**: `DELETE`
- **URL**: `/api/admin/units/{unit_id}`
- **Response (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم حذف الوحدة بنجاح"
  }
  ```

---

## 4. الموديول الثاني: مواصفات وتصنيع المنتجات (Product Manufacturing & Specs)

### 2.1 إنشاء / تعديل مواصفة تصنيع المنتج (Product Manufacturing Recipe)
أصبح حقل `unit_id` **مطلوباً (Required)** لكل مادة خام ووصفة منتج تدخل في تكوين المنتج النهائي.

- **Method**: `POST` (إنشاء) أو `PUT` (تعديل)
- **URL**:
  - إنشاء: `/api/admin/product-manufacturings`
  - تعديل: `/api/admin/product-manufacturings/{id}`
- **Request Body**:
  ```json
  {
    "product_id": 5,
    "materials": [
      {
        "material_id": 1,
        "count": 250,
        "unit_id": 2 // ⚠️ إلزامي: معرف وحدة الجرام مثلاً
      }
    ],
    "product_recipes": [
      {
        "product_recipe_id": 4,
        "count": 1,
        "unit_id": 3 // ⚠️ إلزامي: معرف وحدة القطعة أو الحصة
      }
    ]
  }
  ```

### 2.2 تنفيذ أمر التصنيع (Execute Manufacturing)
- **Method**: `POST`
- **URL**:
  - أدمن: `/api/admin/manufacturing`
  - فرع: `/api/branch/manufacturing`
- **Request Body**:
  ```json
  {
    "branch_id": 1, // إلزامي للأدمن، غير مطلوب للفرع
    "product_recipe_id": 4, // أو product_id
    "count": 10 // الكمية المراد تصنيعها
  }
  ```
- **ما تغير في الـ Response**:
  جميع عناصر المكونات المستهلكة في `manufacturing_recipes` أصبحت تعيد:
  ```json
  {
    "unit_id": 2,
    "unit": {
      "id": 2,
      "name": "جرام",
      "value": 1.0
    }
  }
  ```

---

## 5. الموديول الثالث: المشتريات ودعم الإضافات (Purchases & Addons Buying)

### 3.1 خيارات القوائم المنسدلة لشاشة المشتريات (Purchases Select Options)
تم تحديث النقطة لتعيد قائمة الإضافات (`addons`) مع رصيد كل إضافة بالفرع، بالإضافة لقائمة الوحدات (`units`).

- **Method**: `GET`
- **URL**:
  - أدمن: `/api/admin/purchases/select-options?branch_id=1&lang=ar`
  - فرع: `/api/branch/purchases/select-options?lang=ar`
- **Query Params**:
  - `branch_id` (للأدمن فقط): لتحديد الفرع وحساب رصيده.
  - `lang` (`ar` أو `en`): لتوطين الأسماء.
- **Response (200 OK)**:
  ```json
  {
    "status": true,
    "data": {
      "branches": [...],
      "suppliers": [...],
      "materials": [
        { "id": 1, "name": "دقيق فاخر", "stock": 100.0 }
      ],
      "product_recipes": [
        { "id": 2, "name": "عجينة بيتزا", "stock": 50.0 }
      ],
      "addons": [
        // ⚠️ جديد: قائمة الإضافات مع أسعارها ورصيدها الحالي بالفرع المحدد
        {
          "id": 1,
          "name": "صوص شيدر إضافي",
          "price": 15.0,
          "stock": 24.0
        },
        {
          "id": 2,
          "name": "مشروم إضافي",
          "price": 20.0,
          "stock": 0.0
        }
      ],
      "units": [
        // ⚠️ جديد: قائمة وحدات القياس المتاحة للشراء بها
        { "id": 1, "name": "كيلوجرام", "value": 1000.0 },
        { "id": 2, "name": "جرام", "value": 1.0 },
        { "id": 3, "name": "كرتونة (12)", "value": 12.0 },
        { "id": 4, "name": "قطعة", "value": 1.0 }
      ]
    }
  }
  ```

---

### 3.2 إنشاء فاتورة مشتريات تحتوي على إضافات (Store Purchase with Addons)
يمكن الآن شراء المواد الخام أو وصفات المنتجات أو الإضافات داخل نفس الفاتورة أو كلٍ على حدة.

- **Method**: `POST`
- **URL**:
  - أدمن: `/api/admin/purchases`
  - فرع: `/api/branch/purchases`
- **Request Body (JSON أو Multipart/form-data إذا وجد إيصال receipt)**:
  ```json
  {
    "branch_id": 1, // إلزامي للأدمن، غير مطلوب للفرع
    "supplier_id": 3,
    "total_cost": 450,
    "paid_amount": 450,
    "notes": "فاتورة شراء إضافات وخامات",
    "items": [
      {
        "addon_id": 1, // ⚠️ جديد: شراء إضافة
        "quantity": 2, // كمية 2 كرتونة
        "cost": 150, // سعر الشراء للعنصر
        "unit_id": 3 // ⚠️ اختياري: كرتونة (تتحول تلقائياً إلى 24 قطعة في مخزون الفرع)
      },
      {
        "material_id": 1,
        "quantity": 5,
        "cost": 30,
        "unit_id": 1
      }
    ]
  }
  ```
- **شروط الـ Validation في عناصر `items`**:
  - يجب تحديد واحد على الأقل من: `material_id` أو `product_recipe_id` أو `addon_id`.
  - `quantity`: مطلوب، رقمي أكبر من `0`.
  - `cost`: مطلوب، رقمي أكبر من أو يساوي `0`.
  - `unit_id`: اختياري، معرف وحدة موجود في جدول `units`.
- **أثر العملية في الـ Backend**:
  - يتم إنشاء أو تحديث سجل في جدول `addon_stocks` للفرع المحدد.
  - تحويل الكمية إلى وحدة المخزون وزيادتها (`increment`).
  - عند حذف الفاتورة (`DELETE /purchases/{id}`)، يتم **خصم الكمية واسترجاع المخزون آلياً**.

---

### 3.3 استعراض المشتريات وتفاصيل العناصر (Purchase Items Response)
أصبح كل عنصر داخل مصفوفة `items` يعيد بيانات الإضافة:
```json
{
  "id": 15,
  "purchase_id": 4,
  "material_id": null,
  "material_name": null,
  "material": null,
  "product_recipe_id": null,
  "product_recipe_name": null,
  "product_recipe": null,
  "addon_id": 1,
  "addon_name": "صوص شيدر إضافي",
  "addon": {
    "id": 1,
    "name": "صوص شيدر إضافي",
    "price": 15.0,
    "image": "http://.../storage/addons/cheddar.png"
  },
  "quantity": 2.0,
  "cost": 150.0
}
```

---

## 6. الموديول الرابع: جرد ومخزون الإضافات (Addon Inventory & Stock)

موديول كامل جديد مطابق تماماً لجرد الخامات (`InventoryMaterialController`) ولكنه يختص بالإضافات (`Addon`).

### الـ Base URLs:
- **للأدمن**: `/api/admin/inventory/addons`
- **للفرع**: `/api/branch/inventory/addons`

---

### 4.1 خيارات الجرد (Select Options)
- **Method**: `GET`
- **URL**:
  - أدمن: `/api/admin/inventory/addons/select-options`
  - فرع: `/api/branch/inventory/addons/select-options`
- **Response (200 OK)**:
  ```json
  {
    "status": true,
    "data": {
      "branches": [{ "id": 1, "name": "فرع المعادي" }],
      "units": [
        { "id": 1, "name": "كيلوجرام", "value": 1000.0 },
        { "id": 4, "name": "قطعة", "value": 1.0 }
      ]
    }
  }
  ```

---

### 4.2 إنشاء جلسة جرد إضافات جديدة (Start Addon Inventory)
عند بدء الجرد، يقوم السيستم تلقائياً بأخذ لقطة فورية (Snapshot) لجميع الإضافات في النظام مع أرصدتها الحالية في الفرع.

- **Method**: `POST`
- **URL**:
  - أدمن: `/api/admin/inventory/addons`
  - فرع: `/api/branch/inventory/addons`
- **Request Body (الأدمن)**:
  ```json
  {
    "name": "جرد إضافات نهاية الأسبوع",
    "branch_id": 1
  }
  ```
- **Request Body (الفرع)**:
  ```json
  {
    "name": "جرد إضافات نهاية الأسبوع"
  }
  ```
- **Response (201 Created)**:
  ```json
  {
    "status": true,
    "message": "تم إنشاء جرد الإضافات بنجاح",
    "data": {
      "id": 7,
      "name": "جرد إضافات نهاية الأسبوع",
      "branch_id": 1,
      "branch_name": "فرع المعادي",
      "status": "pending",
      "total_items": 12,
      "total_deficit": 0.0,
      "items": [
        {
          "id": 45,
          "inventory_id": 7,
          "addon_id": 1,
          "addon_name": "صوص شيدر إضافي",
          "stock": 24.0, // الرصيد الدفتري المسجل
          "actual_stock": 24.0, // الرصيد الفعلي (افتراضياً يساوي الدفتري لحين التعديل)
          "deficit": 0.0, // العجز (stock - actual_stock)
          "difference": 0.0,
          "unit_id": 4,
          "unit": { "id": 4, "name": "قطعة", "value": 1.0 }
        }
      ]
    }
  }
  ```

---

### 4.3 استعراض الجرد المعلق (Pending Addon Inventories)
- **Method**: `GET`
- **URL**:
  - أدمن: `/api/admin/inventory/addons/pending?page=1&per_page=15&branch_id=1`
  - فرع: `/api/branch/inventory/addons/pending?page=1&per_page=15`
- **Response**: مصفوفة صفحات تحتوي على معرف الجرد، الاسم، الفرع، التاريخ، والحالة `pending`.

---

### 4.4 استعراض سجل الجرد المنتهي (History Addon Inventories)
- **Method**: `GET`
- **URL**:
  - أدمن: `/api/admin/inventory/addons/history?status=approve&page=1`
  - فرع: `/api/branch/inventory/addons/history?status=approve&page=1`
- **Query Params**:
  - `status`: اختياري (`approve` أو `reject`).
  - `branch_id` (للأدمن).

---

### 4.5 استعراض تفاصيل جلسة جرد وعناصرها (Show Addon Inventory)
- **Method**: `GET`
- **URL**:
  - أدمن: `/api/admin/inventory/addons/{inventory_id}`
  - فرع: `/api/branch/inventory/addons/{inventory_id}`
- **Response (200 OK)**:
  يعيد كائن الجرد الكامل مع قائمة `items` المحملة بالإضافات ووحداتها وقيم العجز.

---

### 4.6 تحديث الرصيد الفعلي لعنصر جرد مفرد (Update Single Addon Item)
تُستخدم أثناء قيام الموظف بعدّ الإضافات وتحديث الرصيد الفعلي لعنصر واحد.

- **Method**: `PUT`
- **URL**:
  - أدمن: `/api/admin/inventory/addon-items/{item_id}`
  - فرع: `/api/branch/inventory/addon-items/{item_id}`
- **Request Body**:
  ```json
  {
    "actual_stock": 20.0,
    "unit_id": 4 // اختياري
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم تحديث الرصيد الفعلي للإضافة بنجاح",
    "data": {
      "id": 45,
      "inventory_id": 7,
      "addon_id": 1,
      "addon_name": "صوص شيدر إضافي",
      "stock": 24.0,
      "actual_stock": 20.0,
      "deficit": 4.0, // عجز بمقدار 4
      "shortage": 4.0,
      "difference": -4.0,
      "unit_id": 4
    }
  }
  ```

---

### 4.7 التحديث الجماعي للأرصدة الفعلية (Batch Update Actual Stocks)
تُستخدم عند حفظ جميع العناصر المعدلة في الجدول دفعة واحدة.

- **Method**: `PUT`
- **URL**:
  - أدمن: `/api/admin/inventory/addons/{inventory_id}/items`
  - فرع: `/api/branch/inventory/addons/{inventory_id}/items`
- **Request Body**:
  ```json
  {
    "items": [
      {
        "id": 45, // معرف سجل inventory_addons
        "actual_stock": 20.0,
        "unit_id": 4
      },
      {
        "id": 46,
        "actual_stock": 15.0,
        "unit_id": 4
      }
    ]
  }
  ```
- **Response (200 OK)**:
  يعيد كائن تفاصيل الجرد المحدث بالكامل مع `total_deficit` الإجمالي.

---

### 4.8 اعتماد أو رفض الجرد (Approve or Reject Addon Inventory)
- **Method**: `PUT`
- **URL**:
  - أدمن: `/api/admin/inventory/addons/{inventory_id}/status`
  - فرع: `/api/branch/inventory/addons/{inventory_id}/status`
- **Request Body**:
  ```json
  {
    "status": "approve" // أو "reject"
  }
  ```
- **أثر الاعتماد (`approve`)**:
  - يتم استبدال رصيد مخزون كل إضافة في الفرع (`addon_stocks`) بالرصيد الفعلي `actual_stock`.
  - إذا تم إدخال الرصيد الفعلي بوحدة مختلفة عن وحدة المخزون، يقوم النظام آلياً بتحويل الكمية إلى وحدة المخزون باستخدام `UnitConversionService`.
  - يتم إغلاق الجرد ولا يمكن تعديله مرة أخرى.

---

## 7. الموديول الخامس: تحديثات جرد المواد الخام والوصفات (Materials & Recipes Inventory)

تمت ترقية جرد المواد الخام (`/inventory/materials`) وجرد وصفات المنتجات (`/inventory/recipes`):
1. **نقطة `select-options`**:
   - أصبحت تعيد قائمة `units: [{ id, name, value }]` لاختيار الوحدة في واجهة الجرد.
2. **تحديث العناصر**:
   - `PUT /api/admin/inventory/material-items/{item}` و `PUT /api/branch/inventory/material-items/{item}`
   - `PUT /api/admin/inventory/recipe-items/{item}` و `PUT /api/branch/inventory/recipe-items/{item}`
   - أصبح كلاهما يقبل حقل اختياري: `"unit_id": 1`.
3. **التحديث الجماعي**:
   - أصبح يقبل `items.*.unit_id`.
4. **الاعتماد وتحديث المخزون**:
   - عند الاعتماد (`status: 'approve'`)، يتم تحويل `actual_stock` آلياً لوحدة المخزون قبل التحديث.

---

## 8. نماذج TypeScript الكاملة (TypeScript Interfaces & Types)

يمكن نسخ هذه الواجهات واستخدامها مباشرة في مشروع الـ Frontend:

```typescript
// ==========================================
// 1. UNIT INTERFACE
// ==========================================
export interface Unit {
  id: number;
  name: { ar: string; en: string } | string;
  value: number; // multiplier value relative to base (e.g., 1000 for kg, 1 for gram)
  created_at?: string;
  updated_at?: string;
}

// ==========================================
// 2. ADDON & ADDON STOCK INTERFACES
// ==========================================
export interface AddonOption {
  id: number;
  name: { ar: string; en: string } | string;
  price: number;
  stock: number; // branch-specific calculated stock
}

export interface AddonStock {
  id: number;
  addon_id: number;
  branch_id: number;
  stock: number;
  unit_id: number | null;
  unit?: Unit | null;
}

// ==========================================
// 3. ADDON INVENTORY INTERFACES
// ==========================================
export interface InventoryAddonItem {
  id: number;
  inventory_id: number;
  addon_id: number;
  addon_name: string;
  stock: number; // system stock
  actual_stock: number; // physical counted stock
  deficit: number; // stock - actual_stock
  shortage: number;
  difference: number; // actual_stock - stock
  unit_id: number | null;
  unit?: Unit | null;
  addon?: {
    id: number;
    name: { ar: string; en: string } | string;
    price: number;
    image?: string | null;
  } | null;
}

export interface AddonInventoryDetail {
  id: number;
  name: string;
  branch_id: number;
  branch_name: { ar: string; en: string } | string;
  status: 'pending' | 'approve' | 'reject';
  total_items: number;
  total_deficit: number;
  items: InventoryAddonItem[];
  created_at: string;
  date: string;
}

// ==========================================
// 4. PURCHASES INTERFACES (UPDATED WITH ADDON)
// ==========================================
export interface PurchaseItemPayload {
  material_id?: number | null;
  product_recipe_id?: number | null;
  addon_id?: number | null; // ⚠️ NEW
  quantity: number;
  cost: number;
  unit_id?: number | null; // ⚠️ NEW: unit for quantity conversion
}

export interface CreatePurchasePayload {
  branch_id?: number; // required for admin
  supplier_id?: number | null;
  total_cost?: number;
  paid_amount?: number;
  due_date?: string;
  notes?: string;
  items: PurchaseItemPayload[];
  dues?: Array<{
    amount: number;
    date: string;
    notes?: string;
  }>;
}

export interface PurchaseItemDetail {
  id: number;
  purchase_id: number;
  material_id: number | null;
  material_name: string | null;
  product_recipe_id: number | null;
  product_recipe_name: string | null;
  addon_id: number | null; // ⚠️ NEW
  addon_name: string | null; // ⚠️ NEW
  addon?: {
    id: number;
    name: { ar: string; en: string } | string;
    price: number;
    image?: string | null;
  } | null;
  quantity: number;
  cost: number;
}
```

---

## 9. ملخص التغييرات السريعة (Quick Cheat Sheet)

| الموديول | الـ Endpoint الرئيسي | ما تم إضافته / تغييره |
| :--- | :--- | :--- |
| **Units** | `/api/admin/units` | CRUD جديد بالكامل لإدارة وحدات القياس وقيم التحويل (`value`). |
| **Purchases Select Options** | `/api/{admin,branch}/purchases/select-options` | إضافة `addons` مع أسعارها وأرصدتها، وإضافة `units`. |
| **Purchase Store** | `/api/{admin,branch}/purchases` (POST) | قبول `addon_id` في `items` وزيادة رصيد الإضافة مع تحويل الوحدة آلياً. |
| **Purchase Delete** | `/api/{admin,branch}/purchases/{id}` (DELETE) | خصم واسترجاع رصيد الإضافات المشتراة آلياً. |
| **Addon Inventory** | `/api/{admin,branch}/inventory/addons` | موديول جرد إضافات متكامل (بدء، جرد معلق، سجل، تعديل، واعتماد). |
| **Addon Inventory Items** | `/api/{admin,branch}/inventory/addon-items/{id}` | تحديث رصيد فعلي ووحدة لإضافة مفردة في الجرد. |
| **Material & Recipe Inventory** | `/api/{admin,branch}/inventory/{materials,recipes}` | دعم `unit_id` في `select-options` وتحديث الأرصدة الفعلية والاعتماد. |
| **Product Manufacturing** | `/api/admin/product-manufacturings` | أصبح `unit_id` إلزامي لكل مادة ووصفة تدخل في تكوين المنتج. |
| **Manufacturing Execution** | `/api/{admin,branch}/manufacturing` | استهلاك المكونات بالتحويل الرياضي الدقيق بين الوحدات. |
