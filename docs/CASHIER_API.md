# توثيق واجهات برمجة التطبيقات لنظام الكاشير (Cashier API Documentation)

دليل شامل وتفصيلي لكافة واجهات الـ APIs الخاصة بنظام الكاشير ونقاط البيع (Prefix: `/api/cashier`).
تتطلب هذه الواجهات مصادقة عبر **JWT Token** بدور كاشير (`cashier_man` أو `cashier`).

---

## 📌 الفهرس العام (Table of Contents)

1. [معلومات عامة والمصادقة (General Info & Auth)](#1-معلومات-عامة-والمصادقة-general-info--auth)
2. [إدارة الشيفت (Shift Management APIs)](#2-إدارة-الشيفت-shift-management-apis)
   - [بدء شيفت جديد (Start Shift)](#21-بدء-شيفت-جديد-post-apicashierstart-shift)
   - [فحص إمكانية بدء الشيفت (Check Start Shift)](#22-فحص-إمكانية-بدء-الشيفت-get-apicashiercheck-start-shift)
   - [إنهاء الشيفت الحالي (End Shift)](#23-إنهاء-الشيفت-الحالي-post-apicashierend-shift)
3. [الكتالوج والصالات والمنتجات (Catalog & POS Setup APIs)](#3-الكتالوج-والصالات-والمنتجات-catalog--pos-setup-apis)
   - [جلب الأقسام الرئيسية (Parent Categories)](#31-جلب-الأقسام-الرئيسية-get-apicashiercategoriesparents)
   - [جلب الأقسام الفرعية (Sub Categories)](#32-جلب-الأقسام-الفرعية-get-apicashiercategoriessub)
   - [جلب أجهزة الكاشير المتاحة (Cashiers)](#33-أجهزة-الكاشير-المتاحة-get-apicashiercashiers)
   - [جلب قائمة المنتجات (Products)](#34-جلب-قائمة-المنتجات-get-apicashierproducts)
   - [تفاصيل منتج معين (Product Details)](#35-تفاصيل-منتج-معين-get-apicashierproductsproduct)
   - [جلب الإضافات (Addons)](#36-جلب-الإضافات-get-apicashieraddons)
   - [جلب الصالات (Halls)](#37-جلب-الصالات-get-apicashierhalls)
   - [جلب طاولات الصالة (Hall Tables)](#38-جلب-طاولات-الصالة-get-apicashierhall-tables)
   - [بيانات المتجر العامة (Business Setup)](#39-بيانات-المتجر-العامة-get-apibusiness-setup)
4. [سلة مشتريات الكاشير (Cashier Cart APIs)](#4-سلة-مشتريات-الكاشير-cashier-cart-apis)
   - [عرض سلة الكاشير (Get Cart)](#41-عرض-عناصر-سلة-الكاشير-get-apicashiercart)
   - [إضافة منتج إلى السلة (Add to Cart)](#42-إضافة-منتج-إلى-السلة-post-apicashiercart)
   - [عرض تفاصيل عنصر بالسلة (Get Cart Item)](#43-عرض-تفاصيل-عنصر-بالسلة-get-apicashiercartcart)
   - [تعديل عنصر بالسلة (Update Cart Item)](#44-تعديل-عنصر-بالسلة-putapicashiercartcart)
   - [حذف عنصر من السلة (Delete Cart Item)](#45-حذف-عنصر-من-السلة-delete-apicashiercartcart)
   - [تفريغ سلة الكاشير (Clear Cart)](#46-تفريغ-سلة-الكاشير-delete-apicashiercartclear)
5. [إدارة الطلبات والـ Checkout (Orders APIs)](#5-إدارة-الطلبات-والـ-checkout-orders-apis)
   - [إتمام الطلب من السلة (Checkout)](#51-إتمام-الطلب-من-السلة-post-apicashierorderscheckout)
   - [عرض قائمة طلبات الكاشير (List Orders)](#52-عرض-قائمة-طلبات-الكاشير-get-apicashierorders)
   - [عرض تفاصيل طلب محدد (Show Order)](#53-عرض-تفاصيل-طلب-محدد-get-apicashierordersorder)

---

## 1. معلومات عامة والمصادقة (General Info & Auth)

- **Base URL**: `http://your-domain/api/cashier`
- **Authentication**: **JWT Bearer Token**
- **تسجيل الدخول للكاشير**: `POST /api/auth/login`
- **Headers الإلزامية لجميع الواجهات**:
  | Header | النوع | الوصف | مثال |
  |---|---|---|---|
  | `Authorization` | String | توكن الدخول | `Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6Ik...` |
  | `Accept` | String | نوع الاستجابة | `application/json` |
  | `Content-Type` | String | نوع البيانات | `application/json` |
  | `Accept-Language` أو `lang` | String | لغة النصوص المعروضة (`ar` أو `en`) | `ar` |

---

## 2. إدارة الشيفت (Shift Management APIs)

### 2.1. بدء شيفت جديد
* **Method**: `POST`
* **URL**: `/api/cashier/start-shift`
* **الوصف**: يبدأ شيفت عمل جديد للكاشير مان ويربطه بجهاز الكاشير المحدد.
* **Request Body**:
```json
{
  "cashier_id": 1,
  "cashier_man_id": null
}
```
* **Validation Rules**:
  | Field | Type | Rules | Description |
  |---|---|---|---|
  | `cashier_id` | integer | `required, exists:cashiers,id` | معرّف جهاز الكاشير (POS) |
  | `cashier_man_id` | integer | `nullable, exists:cashier_men,id` | اختياري (يؤخذ من توكن المستخدم تلقائياً) |

* **Response Success (201 Created)**:
```json
{
  "status": true,
  "message": "تم بدء الشيفت بنجاح",
  "data": {
    "id": 15,
    "start": "2026-09-28 08:00:00",
    "end": null,
    "branch_id": 1,
    "cashier_id": 1,
    "cashier_man_id": 3,
    "default_total_amount": 0,
    "total_mony": null,
    "branch": { "id": 1, "name": "الفرع الرئيسي" },
    "cashier": { "id": 1, "name": "POS 1" },
    "cashier_man": { "id": 3, "name": "أحمد كاشير" }
  }
}
```

* **Response Error - يوجد شيفت مفتوح (200/400)**:
```json
{
  "status": false,
  "message": "يرجى غلق الشيفت السابق اولا"
}
```

---

### 2.2. فحص إمكانية بدء الشيفت
* **Method**: `GET`
* **URL**: `/api/cashier/check-start-shift`
* **الوصف**: يتحقق هل يمكن للكاشير مان الحالي بدء شيفت جديد أم لديه شيفت مفتوح.

* **Response Success (201 Created)**:
```json
{
  "status": true,
  "message": "تقدر تبدأ الشيفت"
}
```

---

### 2.3. إنهاء الشيفت الحالي
* **Method**: `POST`
* **URL**: `/api/cashier/end-shift`
* **الوصف**: يغلق الشيفت المفتوح للكاشير الحالي، ويحسب الإجمالي المحقق من الطلبات `default_total_amount` تلقائياً، ويقارنه مع المبلغ الفعلي المدخل `total_mony`.
* **Request Body**:
```json
{
  "total_mony": 2500.50
}
```
* **Validation Rules**:
  | Field | Type | Rules | Description |
  |---|---|---|---|
  | `total_mony` | numeric | `required, numeric, min:0` | المبلغ النقدي الفعلي في الدرج عند الإغلاق |

* **Response Success (200 OK)**:
```json
{
  "status": true,
  "message": "تم إنهاء الشيفت بنجاح",
  "data": {
    "id": 15,
    "start": "2026-09-28 08:00:00",
    "end": "2026-09-28 16:00:00",
    "default_total_amount": 2500.50,
    "total_mony": 2500.50
  }
}
```

---

## 3. الكتالوج والصالات والمنتجات (Catalog & POS Setup APIs)

### 3.1. جلب الأقسام الرئيسية
* **Method**: `GET`
* **URL**: `/api/cashier/categories/parents`
* **Query Parameters**: `lang=ar|en`

---

### 3.2. جلب الأقسام الفرعية
* **Method**: `GET`
* **URL**: `/api/cashier/categories/sub`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `category_id` | integer | اختياري | فلترة بالقسم الرئيسي |
  | `lang` | string | اختياري | لغة العرض |

---

### 3.3. أجهزة الكاشير المتاحة
* **Method**: `GET`
* **URL**: `/api/cashier/cashiers`
* **الوصف**: جلب أجهزة الكاشير غير المشغولة والتابعة لفرع الكاشير مان الحالي.

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    { "id": 1, "name": "كاشير 1 الصالة" },
    { "id": 2, "name": "كاشير 2 الدليفري" }
  ]
}
```

---

### 3.4. جلب قائمة المنتجات
* **Method**: `GET`
* **URL**: `/api/cashier/products`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `category_id` | integer | اختياري | فلترة بالقسم الرئيسي |
  | `sub_category_id` | integer | اختياري | فلترة بالقسم الفرعي |
  | `lang` | string | اختياري | لغة العرض |

---

### 3.5. تفاصيل منتج معين
* **Method**: `GET`
* **URL**: `/api/cashier/products/{product}`
* **الوصف**: يعرض تفاصيل المنتج والخيارات والأحجام مع الأسعار المحسوبة شاملاً الخصم والضريبة.

---

### 3.6. جلب الإضافات
* **Method**: `GET`
* **URL**: `/api/cashier/addons`

---

### 3.7. جلب الصالات
* **Method**: `GET`
* **URL**: `/api/cashier/halls`
* **الوصف**: جلب الصالات المتاحة والنشطة في المطعم.

---

### 3.8. جلب طاولات الصالة
* **Method**: `GET`
* **URL**: `/api/cashier/hall-tables`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `hall_id` | integer | اختياري | فلترة بصالة معينة |
  | `lang` | string | اختياري | لغة العرض |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 10,
      "name": "طاولة T1",
      "hall_id": 1,
      "branch_id": 1,
      "status": true,
      "qr": "http://your-domain/storage/qrcodes/tables/table_10.svg"
    }
  ]
}
```

---

### 3.9. بيانات المتجر العامة
* **Method**: `GET`
* **URL**: `/api/business-setup`
* **الوصف**: جلب إعدادات المطعم وشعاره وبيانات التواصل ونطاق التغطية `branch_cover`.

---

## 4. سلة مشتريات الكاشير (Cashier Cart APIs)

تعتمد سلة الكاشير على جهاز الكاشير المحدد في الشيفت الحالي (`cashier_id`) ونوع القسم (`module: takeaway, dinein, delivery`).

---

### 4.1. عرض عناصر سلة الكاشير
* **Method**: `GET`
* **URL**: `/api/cashier/cart`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `module` | string | **إلزامي** | القسم: `takeaway` أو `dinein` أو `delivery` |
  | `lang` | string | اختياري | لغة العرض |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 30,
      "product_id": 10,
      "name": "برجر دجاج سوبر",
      "quantity": 1,
      "price": 100.00,
      "discount_val": 20.00,
      "tax_val": 11.20,
      "final_price": 91.20,
      "item_total_price": 100.00,
      "item_total_discount": 20.00,
      "item_total_tax": 11.20,
      "item_final_price": 91.20,
      "notes": "بدون صوص حار",
      "variations": [],
      "addons": []
    }
  ],
  "grand_totals": {
    "grand_total_price": 100.00,
    "grand_total_discount": 20.00,
    "grand_total_tax": 11.20,
    "grand_final_price": 91.20
  }
}
```

---

### 4.2. إضافة منتج إلى السلة
* **Method**: `POST`
* **URL**: `/api/cashier/cart`
* **الوصف**: إضافة عنصر للسلة مع فحص كفاية مخزون مكونات التصنيع للفرع في حال كان المنتج يعتمد على وصفة (`ProductManufacturing`).
* **Request Body**:
```json
{
  "module": "takeaway",
  "product_id": 10,
  "quantity": 2,
  "without_recipe": false,
  "notes": "إكسترا جبنة",
  "variations": [
    {
      "variation_id": 1,
      "option_ids": [5]
    }
  ],
  "addons": [
    {
      "addon_id": 2
    }
  ]
}
```
* **Validation Rules**:
  | Field | Type | Rules | Description |
  |---|---|---|---|
  | `module` | string | `required, in:takeaway,dinein,delivery` | نوع الطلب |
  | `product_id` | integer | `required, exists:products,id` | معرّف المنتج |
  | `quantity` | integer | `nullable, integer, min:1` | الكمية المطلوبة |
  | `without_recipe` | boolean | `sometimes, boolean` | تجاوز فحص مكونات الوصفة إذا كان `true` |
  | `notes` | string | `nullable` | ملاحظات التحضير |
  | `variations` | array | `nullable` | المتغيرات والخيارات |
  | `addons` | array | `nullable` | الإضافات المطلوبة |

* **Response Success (201 Created)**:
```json
{
  "status": true,
  "message": "تمت إضافة المنتج إلى السلة بنجاح",
  "data": { "id": 30, "product_id": 10, "quantity": 2 }
}
```

* **Response Error - نقص مخزون المكونات (422 Unprocessable Entity)**:
```json
{
  "status": false,
  "message": "المخزون المتوفر للمادة الخام (شريحة لحم) في هذا الفرع غير كافٍ. المتاح: 1، المطلوب: 2.",
  "insufficient_ingredient": {
    "type": "material",
    "id": 4,
    "name": "شريحة لحم",
    "available_stock": 1,
    "required_quantity": 2,
    "can_bypass": true
  }
}
```

---

### 4.3. عرض تفاصيل عنصر بالسلة
* **Method**: `GET`
* **URL**: `/api/cashier/cart/{cart}`

---

### 4.4. تعديل عنصر بالسلة
* **Method**: `PUT` أو `PATCH`
* **URL**: `/api/cashier/cart/{cart}`
* **Request Body**:
```json
{
  "quantity": 3,
  "notes": "ملاحظة جديدة",
  "module": "takeaway"
}
```

---

### 4.5. حذف عنصر من السلة
* **Method**: `DELETE`
* **URL**: `/api/cashier/cart/{cart}`

---

### 4.6. تفريغ سلة الكاشير
* **Method**: `DELETE`
* **URL**: `/api/cashier/cart/clear`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `module` | string | اختياري | تفريغ قسم محدد (`takeaway`, `dinein`, `delivery`) |

---

## 5. إدارة الطلبات والـ Checkout (Orders APIs)

### 5.1. إتمام الطلب من السلة (Checkout)
* **Method**: `POST`
* **URL**: `/api/cashier/orders/checkout`
* **الوصف**:
  يقوم بترحيل عناصر السلة للقسم المحدد إلى طلب رسمي (`orders`)، وربطه بالشيفت الحالي (`shift_id`) والكاشير والكاشير مان والفرع، وخصم مخزون المكونات للمنتجات المصنعة، ومسح عناصر السلة.

* **Request Body**:
```json
{
  "module": "dinein",
  "hall_table_id": 10,
  "address": null,
  "phone": "01012345678",
  "name": "أحمد علي",
  "note": "خدمة سريعة"
}
```

* **Validation Rules**:
  | Field | Type | Rules | Description |
  |---|---|---|---|
  | `module` | string | `required, in:takeaway,dinein,delivery` | نوع الطلب |
  | `hall_table_id` | integer | `required_if:module,dinein, nullable, exists:hall_tables,id` | رقم الطاولة (إلزامي في الصالة) |
  | `address` | string | `required_if:module,delivery, nullable, string` | عنوان العميل (إلزامي في التوصيل) |
  | `phone` | string | `required_if:module,delivery, nullable, string, max:50` | هاتف العميل (إلزامي في التوصيل) |
  | `name` | string | `required_if:module,delivery, nullable, string, max:255` | اسم العميل (إلزامي في التوصيل) |
  | `note` | string | `nullable, string` | ملاحظات عامة على الطلب |

* **Response Success (201 Created)**:
```json
{
  "status": true,
  "message": "تم إنشاء الطلب بنجاح من السلة",
  "data": {
    "id": 204,
    "shift_id": 15,
    "shift_name": "شيفت صباحي",
    "cashier_id": 1,
    "cashier_man_id": 3,
    "hall_table_id": 10,
    "branch_id": 1,
    "module": "dinein",
    "is_pos": true,
    "total": 200.00,
    "total_discount": 20.00,
    "total_tax": 25.20,
    "final_price": 205.20,
    "products": [
      {
        "id": 1,
        "product_id": 10,
        "quantity": 2,
        "price": 100.00
      }
    ],
    "created_at": "2026-09-28T05:00:00Z"
  }
}
```

---

### 5.2. عرض قائمة طلبات الكاشير
* **Method**: `GET`
* **URL**: `/api/cashier/orders`
* **الوصف**: عرض وتصفح الطلبات الخاصة بجهاز الكاشير مع خيارات بحث وفلترة متقدمة وترقيم الصفحات (Pagination).
* **Query Parameters**:
  | Parameter | Type | Required | Description | Example |
  |---|---|---|---|---|
  | `search` | string | اختياري | بحث عام برقم الطلب، هاتف العميل، أو الاسم | `"010123"` |
  | `id` | integer | اختياري | فلترة برقم المعرف المباشر | `204` |
  | `order_number` | string | اختياري | فلترة برقم الطلب | `"#204"` |
  | `module` | string | اختياري | فلترة بنوع الطلب (`takeaway`, `dinein`, `delivery`) | `"takeaway"` |
  | `phone` | string | اختياري | بحث برقم الهاتف | `"01012345678"` |
  | `name` | string | اختياري | بحث باسم العميل | `"أحمد"` |
  | `per_page` | integer | اختياري | عدد العناصر بالصفحة (الافتراضي 15، أقصى حد 100) | `20` |
  | `page` | integer | اختياري | رقم الصفحة المطلوب عرضها | `1` |

* **Response Success (200 OK)**:
```json
{
  "data": [
    {
      "id": 204,
      "shift_id": 15,
      "module": "dinein",
      "total": 200.00,
      "final_price": 205.20,
      "created_at": "2026-09-28T05:00:00Z"
    }
  ],
  "links": {
    "first": "http://your-domain/api/cashier/orders?page=1",
    "last": "http://your-domain/api/cashier/orders?page=5",
    "prev": null,
    "next": "http://your-domain/api/cashier/orders?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 75
  }
}
```

---

### 5.3. عرض تفاصيل طلب محدد
* **Method**: `GET`
* **URL**: `/api/cashier/orders/{order}`
* **URL Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `order` | integer | إلزامي | معرّف الطلب (ID) |

* **Response Success (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 204,
    "shift_id": 15,
    "cashier_id": 1,
    "cashier_man_id": 3,
    "hall_table_id": 10,
    "branch_id": 1,
    "module": "dinein",
    "is_pos": true,
    "total": 200.00,
    "total_discount": 20.00,
    "total_tax": 25.20,
    "final_price": 205.20,
    "cashier": { "id": 1, "name": "POS 1" },
    "hall_table": { "id": 10, "name": "طاولة T1" },
    "products": [
      {
        "id": 1,
        "product_id": 10,
        "quantity": 2,
        "price": 100.00
      }
    ]
  }
}
```
