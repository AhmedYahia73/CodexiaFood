# توثيق واجهات برمجة التطبيقات للمستخدم (User API Documentation)

دليل شامل وتفصيلي لكافة واجهات الـ APIs الخاصة بالمستخدم (Prefix: `/api/user`).
جميع هذه الواجهات **عامة ومتاحة بدون تسجيل دخول (No Auth)**، ويتم التعرف على سلة وطلبات المستخدم بواسطة المعرّف الفريد `uu_id` المُرسل من تطبيق الفرونت إند.

---

## 📌 الفهرس العام (Table of Contents)

1. [معلومات عامة وتوجيهات الاتصال (General Info & Headers)](#1-معلومات-عامة-وتوجيهات-الاتصال-general-info--headers)
2. [واجهات الصفحة الرئيسية والمنتجات (Home & Catalog APIs)](#2-واجهات-الصفحة-الرئيسية-والمنتجات-home--catalog-apis)
   - [جلب الأقسام الرئيسية (Parent Categories)](#21-جلب-الأقسام-الرئيسية-get-apiusercategoriesparents)
   - [جلب الأقسام الفرعية (Sub Categories)](#22-جلب-الأقسام-الفرعية-get-apiusercategoriessub)
   - [جلب قائمة المنتجات والأسعار (Products)](#23-جلب-قائمة-المنتجات-get-apiuserproducts)
   - [تفاصيل منتج معين والخيارات (Product Details)](#24-تفاصيل-منتج-معين-get-apiuserproductsproduct)
   - [جلب قائمة الإضافات (Addons)](#25-جلب-قائمة-الإضافات-get-apiuseraddons)
   - [إعدادات وبيانات المتجر ونطاق التغطية (Business Setup)](#26-بيانات-المتجر-ونطاق-التغطية-get-apiuserbusiness-setup)
3. [واجهات سلة المشتريات (Cart APIs)](#3-واجهات-سلة-المشتريات-cart-apis)
   - [عرض عناصر السلة والإجماليات (Get Cart)](#31-عرض-عناصر-السلة-get-apiusercart)
   - [إضافة منتج إلى السلة (Add to Cart)](#32-إضافة-منتج-إلى-السلة-post-apiusercart)
   - [عرض عنصر محدد في السلة (Get Cart Item)](#33-عرض-عنصر-محدد-get-apiusercartcart)
   - [تعديل عنصر في السلة (Update Cart Item)](#34-تعديل-عنصر-بالسلة-putapiusercartcart)
   - [حذف عنصر من السلة (Delete Cart Item)](#35-حذف-عنصر-من-السلة-delete-apiusercartcart)
   - [تفريغ السلة بالكامل (Clear Cart)](#36-تفريغ-سلة-المستخدم-delete-apiusercartclear)
4. [واجهات إتمام الطلبات (Orders APIs)](#4-واجهات-إتمام-الطلبات-orders-apis)
   - [إنشاء الطلب والدفع/التأكيد (Checkout)](#41-إتمام-الطلب-post-apiuserorderscheckout)
   - [عرض تفاصيل طلب معين (Show Order)](#42-عرض-تفاصيل-طلب-get-apiuserordersorder)

---

## 1. معلومات عامة وتوجيهات الاتصال (General Info & Headers)

- **Base URL**: `http://your-domain/api/user`
- **Authentication**: **None** (بدون توثيق)
- **Headers الشائعة**:
  | Header | النوع | الوصف | مثال |
  |---|---|---|---|
  | `Accept` | String | نوع الاستجابة | `application/json` |
  | `Content-Type` | String | نوع البيانات المرسلة | `application/json` |
  | `Accept-Language` أو `lang` | String | لغة النصوص المعروضة (`ar` أو `en`) | `ar` |
  | `uu_id` أو `X-UU-ID` | String | المعرف الفريد للعميل/الجهاز (اختياري بالهيدر وبديل للـ Query/Body) | `device-uuid-98765` |

---

## 2. واجهات الصفحة الرئيسية والمنتجات (Home & Catalog APIs)

### 2.1. جلب الأقسام الرئيسية
* **Method**: `GET`
* **URL**: `/api/user/categories/parents`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `lang` | string | اختياري | لغة العرض (`ar` أو `en`) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "وجبات رئيسية",
      "description": "أشهى الوجبات والمأكولات الطازجة",
      "image": "http://your-domain/storage/categories/main.jpg",
      "status": true,
      "type": "product"
    }
  ]
}
```

---

### 2.2. جلب الأقسام الفرعية
* **Method**: `GET`
* **URL**: `/api/user/categories/sub`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `category_id` | integer | اختياري | فلترة الأقسام الفرعية التابعة لقسم رئيسي معين |
  | `lang` | string | اختياري | لغة العرض (`ar` أو `en`) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 2,
      "category_id": 1,
      "name": "ساندوتشات برجر",
      "description": "برجر لحم ودجاج طازج",
      "image": "http://your-domain/storage/categories/burgers.jpg",
      "status": true,
      "type": "product"
    }
  ]
}
```

---

### 2.3. جلب قائمة المنتجات
* **Method**: `GET`
* **URL**: `/api/user/products`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `category_id` | integer | اختياري | فلترة بالقسم الرئيسي |
  | `sub_category_id` | integer | اختياري | فلترة بالقسم الفرعي |
  | `lang` | string | اختياري | لغة العرض (`ar` أو `en`) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 10,
      "name": "برجر لحم كلاسيك",
      "description": "شريحة لحم صافي مع الجبنة والخس والصوص",
      "image": "http://your-domain/storage/products/beef.jpg",
      "category_id": 1,
      "sub_category_id": 2,
      "price": 100.00,
      "discount_val": 10.00,
      "tax_val": 12.60,
      "final_price": 102.60,
      "discount": {
        "id": 1,
        "name": "خصم 10%",
        "type": "percentage",
        "amount": 10.00
      },
      "tax": {
        "id": 1,
        "name": "ضريبة القيمة المضافة 14%",
        "type": "percentage",
        "amount": 14.00
      }
    }
  ]
}
```

---

### 2.4. تفاصيل منتج معين
* **Method**: `GET`
* **URL**: `/api/user/products/{product}`
* **URL Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `product` | integer | إلزامي | معرّف المنتج (ID) |
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `lang` | string | اختياري | لغة العرض (`ar` أو `en`) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 10,
    "name": "برجر لحم كلاسيك",
    "description": "شريحة لحم صافي مع الجبنة",
    "image": "http://your-domain/storage/products/beef.jpg",
    "category_id": 1,
    "sub_category_id": 2,
    "price": 100.00,
    "discount_val": 10.00,
    "tax_val": 12.60,
    "final_price": 102.60,
    "variations": [
      {
        "id": 1,
        "name": "الحجم",
        "status": true,
        "required": true,
        "options": [
          {
            "id": 5,
            "name": "دابل (Double)",
            "price": 30.00,
            "discount_val": 3.00,
            "tax_val": 3.78,
            "final_price": 30.78,
            "status": true
          }
        ]
      }
    ]
  }
}
```

---

### 2.5. جلب قائمة الإضافات
* **Method**: `GET`
* **URL**: `/api/user/addons`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `lang` | string | اختياري | لغة العرض (`ar` أو `en`) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": [
    {
      "id": 3,
      "name": "بطاطس مقلية كبيرة",
      "image": "http://your-domain/storage/addons/fries.jpg",
      "price": 25.00,
      "discount_val": 0.00,
      "tax_val": 3.50,
      "final_price": 28.50
    }
  ]
}
```

---

### 2.6. بيانات المتجر ونطاق التغطية
* **Method**: `GET`
* **URL**: `/api/user/business-setup`
* **الوصف**: يعرض بيانات المطعم/المتجر بما في ذلك نطاق تغطية التوصيل بالكيلومتر `branch_cover`.

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "مطعم كودكسا",
    "phone": "01012345678",
    "face": "https://facebook.com/codexa",
    "instagram": "https://instagram.com/codexa",
    "whats": "01012345678",
    "logo": "http://your-domain/storage/business_setup/logo.png",
    "raw_logo": "business_setup/logo.png",
    "description": "أفضل تجربة طعام لجميع العائلة",
    "branch_cover": 5.00,
    "created_at": "2026-09-28T04:00:00Z",
    "updated_at": "2026-09-28T04:00:00Z"
  }
}
```

---

## 3. واجهات سلة المشتريات (Cart APIs)

تعتمد جميع واجهات السلة على معرف المستخدم `uu_id`. يمكن إرسال `uu_id` في:
1. الـ Query Parameters: `?uu_id=xyz`
2. الـ Request Body (في POST/PUT): `{"uu_id": "xyz"}`
3. الـ Headers: `uu_id: xyz` أو `X-UU-ID: xyz`

---

### 3.1. عرض عناصر السلة
* **Method**: `GET`
* **URL**: `/api/user/cart`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `uu_id` | string | **إلزامي** | معرّف جهاز العميل |
  | `module` | string | اختياري | فلترة بالقسم (`delivery`, `takeaway`, `dinein`) |
  | `lang` | string | اختياري | لغة العرض (`ar` أو `en`) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "uu_id": "client-uuid-12345",
  "data": [
    {
      "id": 45,
      "product_id": 10,
      "name": "برجر لحم كلاسيك",
      "image": "http://your-domain/storage/products/beef.jpg",
      "quantity": 2,
      "price": 100.00,
      "discount_val": 10.00,
      "tax_val": 12.60,
      "final_price": 102.60,
      "item_total_price": 200.00,
      "item_total_discount": 20.00,
      "item_total_tax": 25.20,
      "item_final_price": 205.20,
      "notes": "بدون بصل",
      "variations": [
        {
          "id": 12,
          "variation_id": 1,
          "name": "الحجم",
          "options": [
            {
              "id": 5,
              "name": "دابل (Double)",
              "price": 30.00,
              "discount_val": 3.00,
              "tax_val": 3.78,
              "final_price": 30.78
            }
          ]
        }
      ],
      "addons": [
        {
          "id": 8,
          "addon_id": 3,
          "name": "بطاطس مقلية كبيرة",
          "price": 25.00,
          "discount_val": 0.00,
          "tax_val": 3.50,
          "final_price": 28.50
        }
      ]
    }
  ],
  "grand_totals": {
    "grand_total_price": 310.00,
    "grand_total_discount": 26.00,
    "grand_total_tax": 39.76,
    "grand_final_price": 323.76
  }
}
```

---

### 3.2. إضافة منتج إلى السلة
* **Method**: `POST`
* **URL**: `/api/user/cart`
* **Request Body**:
```json
{
  "uu_id": "client-uuid-12345",
  "product_id": 10,
  "module": "delivery",
  "quantity": 2,
  "notes": "بدون بصل",
  "variations": [
    {
      "variation_id": 1,
      "option_ids": [5]
    }
  ],
  "addons": [
    {
      "addon_id": 3
    }
  ]
}
```
* **Validation Rules**:
  | Field | Type | Rules | Description |
  |---|---|---|---|
  | `uu_id` | string | `required` | معرّف العميل (أو يرسل في الـ Header) |
  | `product_id` | integer | `required, exists:products,id` | معرّف المنتج |
  | `module` | string | `nullable, in:delivery,takeaway,dinein` | القسم (الافتراضي: delivery) |
  | `quantity` | integer | `nullable, min:1` | الكمية المطلوبة (الافتراضي: 1) |
  | `notes` | string | `nullable` | ملاحظات خاصة على المنتج |
  | `variations` | array | `nullable` | مصفوفة الأحجام/الخيارات |
  | `variations.*.variation_id` | integer | `required_with:variations, exists:variations,id` | معرّف المتغير |
  | `variations.*.option_ids` | array | `required_with:variations` | مصفوفة معرّفات الخيارات |
  | `addons` | array | `nullable` | مصفوفة الإضافات |
  | `addons.*.addon_id` | integer | `required_with:addons, exists:addons,id` | معرّف الإضافة |

* **Response Example (201 Created)**:
```json
{
  "status": true,
  "message": "تمت إضافة المنتج إلى السلة بنجاح",
  "uu_id": "client-uuid-12345",
  "data": {
    "id": 45,
    "product_id": 10,
    "quantity": 2,
    "item_final_price": 205.20
  }
}
```

---

### 3.3. عرض عنصر محدد في السلة
* **Method**: `GET`
* **URL**: `/api/user/cart/{cart}`
* **Query Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `uu_id` | string | **إلزامي** | معرّف العميل للتحقق من ملكية العنصر |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 45,
    "product_id": 10,
    "quantity": 2,
    "item_final_price": 205.20
  }
}
```

---

### 3.4. تعديل عنصر بالسلة
* **Method**: `PUT` أو `PATCH`
* **URL**: `/api/user/cart/{cart}`
* **Request Body**:
```json
{
  "uu_id": "client-uuid-12345",
  "quantity": 3,
  "notes": "زيادة صوص الكاتشب",
  "variations": [
    {
      "variation_id": 1,
      "option_ids": [5]
    }
  ],
  "addons": [
    {
      "addon_id": 3
    }
  ]
}
```
* **Response Example (200 OK)**:
```json
{
  "status": true,
  "message": "تم تحديث عنصر السلة بنجاح",
  "data": {
    "id": 45,
    "quantity": 3,
    "notes": "زيادة صوص الكاتشب"
  }
}
```

---

### 3.5. حذف عنصر من السلة
* **Method**: `DELETE`
* **URL**: `/api/user/cart/{cart}`
* **Query Parameters / Headers**:
  - `uu_id`: إلزامي.
* **Response Example (200 OK)**:
```json
{
  "status": true,
  "message": "تم حذف العنصر من السلة بنجاح"
}
```

---

### 3.6. تفريغ سلة المستخدم
* **Method**: `DELETE`
* **URL**: `/api/user/cart/clear`
* **Query Parameters / Headers**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `uu_id` | string | **إلزامي** | معرّف العميل |
  | `module` | string | اختياري | تفريغ قسم معين فقط |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "message": "تم تفريغ السلة بنجاح"
}
```

---

## 4. واجهات إتمام الطلبات (Orders APIs)

### 4.1. إتمام الطلب (Checkout)
* **Method**: `POST`
* **URL**: `/api/user/orders/checkout`
* **الوصف**:
  يقوم بإنشاء طلب جديد من عناصر سلة العميل المحددة بـ `uu_id`، وفحص المسافة الجغرافية بين إحداثيات العميل `(lat, lng)` وجميع الفروع النشطة. يتم ربط الطلب بأقرب فرع تلقائياً بشرط ألا تزيد المسافة عن نطاق التغطية `branch_cover` (الافتراضي 5 كم). كما يتم خصم المخزون ومسح السلة.

* **Request Body**:
```json
{
  "uu_id": "client-uuid-12345",
  "lat": 29.9650,
  "lng": 31.2580,
  "address": "شارع 9، المعادي، برج النيل، الدور الرابع",
  "phone": "01012345678",
  "name": "أحمد يحيى",
  "note": "يرجى ترك الطلب عند الباب",
  "module": "delivery"
}
```

* **Validation Rules**:
  | Field | Type | Rules | Description | Example |
  |---|---|---|---|---|
  | `uu_id` | string | `required, string, max:255` | معرّف العميل الفريد | `"client-uuid-12345"` |
  | `lat` | float | `required, numeric, between:-90,90` | خط عرض موقع العميل | `29.9650` |
  | `lng` | float | `required, numeric, between:-180,180` | خط طول موقع العميل | `31.2580` |
  | `address` | string | `required, string, max:500` | تفاصيل عنوان التوصيل | `"شارع 9، المعادي"` |
  | `phone` | string | `required, string, max:50` | رقم هاتف المستلم للتواصل | `"01012345678"` |
  | `name` | string | `required, string, max:255` | اسم العميل بالكامل | `"أحمد يحيى"` |
  | `note` | string | `nullable, string, max:1000` | ملاحظات التوصيل | `"بدون مخلل"` |
  | `module` | string | `nullable, in:delivery,takeaway,dinein` | نوع الطلب (افتراضياً: delivery) | `"delivery"` |

* **Response Success (201 Created)**:
```json
{
  "status": true,
  "message": "تم إنشاء الطلب بنجاح من السلة",
  "distance_km": 0.85,
  "data": {
    "id": 101,
    "shift_id": null,
    "shift_name": null,
    "cashier_id": null,
    "cashier_man_id": null,
    "hall_table_id": null,
    "branch_id": 2,
    "module": "delivery",
    "address": "شارع 9، المعادي، برج النيل، الدور الرابع",
    "lat": 29.965,
    "lng": 31.258,
    "note": "يرجى ترك الطلب عند الباب",
    "phone": "01012345678",
    "name": "أحمد يحيى",
    "is_pos": false,
    "total": 310.00,
    "total_tax": 39.76,
    "total_discount": 26.00,
    "final_price": 323.76,
    "branch": {
      "id": 2,
      "name": "فرع المعادي"
    },
    "products": [
      {
        "id": 1,
        "product_id": 10,
        "quantity": 2,
        "price": 100.00
      }
    ],
    "created_at": "2026-09-28T04:30:00Z",
    "updated_at": "2026-09-28T04:30:00Z"
  }
}
```

* **Response Error - خارج نطاق التوصيل (422 Unprocessable Entity)**:
```json
{
  "status": false,
  "message": "عذراً، موقعك الحالي خارج نطاق التوصيل المتاح. المسافة لأقرب فرع (18.5 كم) تتجاوز الحد الأقصى المسموح به (5 كم).",
  "distance": 18.5,
  "max_cover": 5.00
}
```

* **Response Error - السلة فارغة (400 Bad Request)**:
```json
{
  "status": false,
  "message": "السلة فارغة، يرجى إضافة منتجات إلى السلة أولاً"
}
```

---

### 4.2. عرض تفاصيل طلب
* **Method**: `GET`
* **URL**: `/api/user/orders/{order}`
* **URL Parameters**:
  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `order` | integer | إلزامي | معرّف الطلب (ID) |

* **Response Example (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 101,
    "branch_id": 2,
    "address": "شارع 9، المعادي",
    "lat": 29.965,
    "lng": 31.258,
    "final_price": 323.76,
    "branch": {
      "id": 2,
      "name": "فرع المعادي"
    }
  }
}
```
