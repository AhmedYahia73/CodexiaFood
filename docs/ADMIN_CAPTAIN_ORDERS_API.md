# توثيق واجهات إدارة كباتن الطلبات - لوحة الإدارة
## Admin Captain Orders API Documentation

دليل تقني مفصل وشامل لمطوري لوحة التحكم والتطبيقات لإدارة كباتن الطلبات (`CaptainOrder`) وإسنادها للطلبات (`Order`)، بما يشمل عمليات الـ CRUD كاملة، رفع وتحديث وحذف الصور من السيرفر.

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة وآلية العمل (Overview)](#1-نظرة-عامة-وآلية-العمل-overview)
2. [الهيدرز والمصادقة (Headers & Authentication)](#2-الهيدرز-والمصادقة-headers--authentication)
3. [خيارات القوائم المنسدلة (GET /api/admin/captain-orders/select-options)](#3-خيارات-القوائم-المنسدلة-get-apiadmincaptain-ordersselect-options)
4. [عرض قائمة الكباتن (GET /api/admin/captain-orders)](#4-عرض-قائمة-الكباتن-get-apiadmincaptain-orders)
5. [إضافة كابتن جديد (POST /api/admin/captain-orders)](#5-إضافة-كابتن-جديد-post-apiadmincaptain-orders)
6. [عرض تفاصيل كابتن محدد (GET /api/admin/captain-orders/{id})](#6-عرض-تفاصيل-كابتن-محدد-get-apiadmincaptain-ordersid)
7. [تعديل بيانات كابتن وإدارة الصور (POST /api/admin/captain-orders/{id})](#7-تعديل-بيانات-كابتن-وإدارة-الصور-post-apiadmincaptain-ordersid)
8. [حذف كابتن (DELETE /api/admin/captain-orders/{id})](#8-حذف-كابتن-delete-apiadmincaptain-ordersid)
9. [ربط الكابتن بالطلبات (Order - captain_order_id)](#9-ربط-الكابتن-بالطلبات-order---captain_order_id)
10. [أكواد الاستجابة ومعالجة الأخطاء (Error Handling)](#10-أكواد-الاستجابة-ومعالجة-الأخطاء-error-handling)
11. [أمثلة برمجية في الفرونت إند (JavaScript / Axios Example)](#11-أمثلة-برمجية-في-الفرونت-إند-javascript--axios-example)

---

## 1. نظرة عامة وآلية العمل (Overview)

* **الصور كمصفوفة (`images`)**: يتم تخزين مسارات الصور في قاعدة البيانات بصيغة JSON Array، وتُرجع الـ API روابط كاملة ومباشرة (`images`) بالإضافة للمسارات النسبية (`raw_images`).
* **رفع الصور عند الإضافة (`Add`)**: يتم رفع الصور عبر حقل `images[]` وتخزينها في التخزين العام `storage/app/public/captain_orders`.
* **آلية التعديل وحذف الصور (`Edit / Update`)**:
  - `deleted_images`: مصفوفة بالصور المراد حذفها من السيرفر. يقوم السيرفر بـ **مسح الملفات الفعلية من الهارد ديسك أولاً** قبل استبعادها من مصفوفة الصور بقاعدة البيانات.
  - `new_images`: مصفوفة ملفات صور جديدة يتم رفعها وإضافتها إلى مصفوفة الصور المتبقية.
* **الحذف التلقائي للصور عند الحذف (`Destroy`)**: عند استدعاء `DELETE /api/admin/captain-orders/{id}` يتم حذف **جميع صور الكابتن الفعلية من السيرفر أولاً** قبل حذف السجل من قاعدة البيانات لمنع تراكم الملفات المهملة.
* **كلمة المرور (`password`)**: يتم تشفيرها تلقائياً بتقنية Hashing في الـ Model وتكون مخفية دائماً من الاستجابات (`hidden`).
* **ربط الطلبات (`captain_order_id`)**: تمت إضافة المفتاح الخارجي `captain_order_id` بجدول `orders` لربط الطلبات بالكابتن مع إمكانية استخدام `captain_orders.orders` و `orders.captainOrder`.

---

## 2. الهيدرز والمصادقة (Headers & Authentication)

تتطلب جميع واجهات لوحة التحكم توثيقاً صالحاً بصلاحية الأدمن (`auth:admin`).

| الهيدر (Header) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `Authorization` | String | **نعم** | توكن الأدمن بنظام Bearer Token | `Bearer eyJhbGciOi...` |
| `Accept` | String | **نعم** | صيغة الاستجابة المطلوبة | `application/json` |
| `Content-Type` | String | نعم (عند إرسال ملفات) | نوع البيانات المرسلة للطلب | `multipart/form-data` |
| `lang` | String | اختياري | لغة الاستجابة (`ar` أو `en`) | `ar` |

---

## 3. خيارات القوائم المنسدلة (GET /api/admin/captain-orders/select-options)

لجلب قائمة الكباتن النشطين فقط بالـ `id` و `name` و `user_name` لاستخدامها في حقول الاختيار (Dropdowns) عند إنشاء أو تعديل الطلبات.

* **الرابط (URL)**: `/api/admin/captain-orders/select-options`
* **نوع الطلب (HTTP Method)**: `GET`

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "data": {
    "captain_orders": [
      {
        "id": 1,
        "name": "أحمد محمود",
        "user_name": "ahmed_captain"
      },
      {
        "id": 2,
        "name": "محمد علي",
        "user_name": "mohamed_captain"
      }
    ]
  }
}
```

---

## 4. عرض قائمة الكباتن (GET /api/admin/captain-orders)

جلب قائمة الكباتن مع الترقيم والبحث والفلترة بالحالة مرتبة من الأحدث إلى الأقدم.

* **الرابط (URL)**: `/api/admin/captain-orders`
* **نوع الطلب (HTTP Method)**: `GET`
* **المعاملات (Query Parameters)**:
  * `page` (integer, اختياري): رقم الصفحة، الافتراضي `1`.
  * `per_page` (integer, اختياري): عدد العناصر في الصفحة، الافتراضي `15`.
  * `search` (string, اختياري): بحث بالاسم (`name`) أو اسم المستخدم (`user_name`).
  * `status` (boolean, اختياري): فلتر بالحالة (`1` أو `true` للنشط، `0` أو `false` لغير النشط).

### استجابة ناجحة (`200 OK`):
```json
{
  "data": [
    {
      "id": 1,
      "name": "أحمد محمود",
      "user_name": "ahmed_captain",
      "status": true,
      "images": [
        "http://your-domain.com/storage/captain_orders/lic_1729712345.webp",
        "http://your-domain.com/storage/captain_orders/id_1729712346.webp"
      ],
      "raw_images": [
        "captain_orders/lic_1729712345.webp",
        "captain_orders/id_1729712346.webp"
      ],
      "created_at": "2026-10-24T18:55:00+03:00",
      "updated_at": "2026-10-24T18:55:00+03:00"
    }
  ],
  "links": {
    "first": "http://your-domain.com/api/admin/captain-orders?page=1",
    "last": "http://your-domain.com/api/admin/captain-orders?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

---

## 5. إضافة كابتن جديد (POST /api/admin/captain-orders)

إضافة كابتن جديد ورفع صوره الخاصة (مثل رخصة القيادة، بطاقة الهوية، إلخ).

* **الرابط (URL)**: `/api/admin/captain-orders`
* **نوع الطلب (HTTP Method)**: `POST`
* **نوع المحتوى (Content-Type)**: `multipart/form-data`
* **بيانات الطلب (Body Fields)**:
  * `name` (string, **إلزامي**): اسم الكابتن بالكامل (حد أقصى 255 حرف).
  * `user_name` (string, **إلزامي**): اسم مستخدم فريد للدخول (Unique).
  * `password` (string, **إلزامي**): كلمة المرور (6 أحرف على الأقل).
  * `status` (boolean, اختياري): حالة الكابتن (`1` نشط، `0` غير نشط)، الافتراضي `1`.
  * `images[]` (files, اختياري): مصفوفة ملفات صور (jpeg, png, jpg, gif, webp - حد أقصى 4MB لكل ملف).

### استجابة ناجحة (`201 Created`):
```json
{
  "status": true,
  "message": "Captain order created successfully.",
  "data": {
    "id": 1,
    "name": "أحمد محمود",
    "user_name": "ahmed_captain",
    "status": true,
    "images": [
      "http://your-domain.com/storage/captain_orders/lic_1729712345.webp"
    ],
    "raw_images": [
      "captain_orders/lic_1729712345.webp"
    ],
    "created_at": "2026-10-24T18:55:00+03:00",
    "updated_at": "2026-10-24T18:55:00+03:00"
  }
}
```

---

## 6. عرض تفاصيل كابتن محدد (GET /api/admin/captain-orders/{id})

* **الرابط (URL)**: `/api/admin/captain-orders/{id}`
* **نوع الطلب (HTTP Method)**: `GET`

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "أحمد محمود",
    "user_name": "ahmed_captain",
    "status": true,
    "images": [
      "http://your-domain.com/storage/captain_orders/lic_1729712345.webp"
    ],
    "raw_images": [
      "captain_orders/lic_1729712345.webp"
    ],
    "created_at": "2026-10-24T18:55:00+03:00",
    "updated_at": "2026-10-24T18:55:00+03:00"
  }
}
```

---

## 7. تعديل بيانات كابتن وإدارة الصور (POST /api/admin/captain-orders/{id})

تعديل بيانات الكابتن مع إمكانية حذف صور محددة وإضافة صور جديدة في نفس الطلب.

> ⚠️ **ملاحظة هامة للفرونت إند**: لضمان قراءة ملفات الصور الجديدة بشكل سليم داخل بيئة PHP/Laravel، يُرجى إرسال الطلب بطريقة `POST` مع إضافة حقل `_method = PUT` داخل الـ Form-Data (أو استخدام `PUT` المباشر المدعوم أيضاً).

* **الرابط (URL)**: `/api/admin/captain-orders/{id}`
* **نوع الطلب (HTTP Method)**: `POST` (مع `_method=PUT`) أو `PUT`
* **نوع المحتوى (Content-Type)**: `multipart/form-data`
* **بيانات الطلب (Body Fields)**:
  * `_method` (string, موصى به): قيمته `PUT`.
  * `name` (string, اختياري): الاسم الجديد.
  * `user_name` (string, اختياري): اسم مستخدم جديد (يجب أن يكون فريداً).
  * `password` (string, اختياري): كلمة مرور جديدة في حال الرغبة بتغييرها فقط.
  * `status` (boolean, اختياري): تحديث الحالة (`1` أو `0`).
  * `deleted_images[]` (array/strings, اختياري): مصفوفة روابط أو مسارات الصور المطلوب **مسحها من السيرفر نهائياً**، تقبل صيغة Array أو JSON String.
  * `new_images[]` (files, اختياري): ملفات صور جديدة يتم رفعها ودمجها مع الصور المتبقية.

### مثال على جسم الطلب في FormData:
```
_method: PUT
name: أحمد محمود المحدث
status: 1
deleted_images[0]: captain_orders/old_license.webp
new_images[0]: (binary file)
```

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "message": "Captain order updated successfully.",
  "data": {
    "id": 1,
    "name": "أحمد محمود المحدث",
    "user_name": "ahmed_captain",
    "status": true,
    "images": [
      "http://your-domain.com/storage/captain_orders/new_license_1729719999.webp"
    ],
    "raw_images": [
      "captain_orders/new_license_1729719999.webp"
    ],
    "created_at": "2026-10-24T18:55:00+03:00",
    "updated_at": "2026-10-24T19:10:00+03:00"
  }
}
```

---

## 8. حذف كابتن (DELETE /api/admin/captain-orders/{id})

يقوم هذا المسار بحذف الكابتن نهائياً، مع **حذف جميع الصور المخزنة الخاصة به من قرص السيرفر أولاً** لضمان عدم وجود ملفات يتيمة.

* **الرابط (URL)**: `/api/admin/captain-orders/{id}`
* **نوع الطلب (HTTP Method)**: `DELETE`

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "message": "Captain order deleted successfully."
}
```

---

## 9. ربط الكابتن بالطلبات (Order - captain_order_id)

تمت إضافة الحقل `captain_order_id` إلى جدول `orders`.

### 1. إسناد الكابتن عند إنشاء أو تحديث طلب (`POST /api/admin/orders`):
```json
{
  "module": "delivery",
  "captain_order_id": 1,
  "total": 120.00,
  "final_price": 120.00,
  "products": [...]
}
```

### 2. ظهور الكابتن في تفاصيل الطلب (`GET /api/admin/orders/{id}`):
يحتوي كائن الطلب في الاستجابة على:
```json
{
  "id": 55,
  "captain_order_id": 1,
  "captain_order": {
    "id": 1,
    "name": "أحمد محمود",
    "user_name": "ahmed_captain",
    "status": true,
    "images": [
      "http://your-domain.com/storage/captain_orders/lic_1729712345.webp"
    ]
  },
  "module": "delivery",
  "final_price": 120.00
}
```

---

## 10. أكواد الاستجابة ومعالجة الأخطاء (Error Handling)

| كود الحالة (Status Code) | السبب | شكل الاستجابة |
|---|---|---|
| `200 OK` | نجاح العملية واسترجاع أو تحديث أو حذف البيانات | `{"status": true, "data": ...}` |
| `201 Created` | نجاح إنشاء كابتن جديد | `{"status": true, "message": "...", "data": ...}` |
| `401 Unauthorized` | التوكن غير موجود أو منتهي الصلاحية | `{"message": "Unauthenticated."}` |
| `403 Forbidden` | المستخدم لا يمتلك صلاحية أدمن | `{"message": "Unauthorized role."}` |
| `404 Not Found` | رقم الكابتن غير موجود بقاعدة البيانات | `{"message": "Record not found."}` |
| `422 Unprocessable Content` | فشل في التحقق من صحة المدخلات (Validation Error) | `{"message": "The user name has already been taken.", "errors": {...}}` |

---

## 11. أمثلة برمجية في الفرونت إند (JavaScript / Axios Example)

### أ) إضافة كابتن مع صور متعددة:
```javascript
const formData = new FormData();
formData.append('name', 'أحمد محمود');
formData.append('user_name', 'ahmed_captain');
formData.append('password', 'secret123');
formData.append('status', 1);

// إضافة ملفات الصور
const imageFiles = document.querySelector('#imagesInput').files;
for (let i = 0; i < imageFiles.length; i++) {
  formData.append('images[]', imageFiles[i]);
}

const response = await axios.post('/api/admin/captain-orders', formData, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'multipart/form-data'
  }
});
```

### ب) تعديل كابتن وحذف صور قديمة ورفع صور جديدة:
```javascript
const formData = new FormData();
formData.append('_method', 'PUT');
formData.append('name', 'أحمد محمود المحدث');

// تحديد الصور المراد حذفها من السيرفر
formData.append('deleted_images[]', 'captain_orders/old_lic.webp');

// رفع صور جديدة
const newFiles = document.querySelector('#newImagesInput').files;
for (let i = 0; i < newFiles.length; i++) {
  formData.append('new_images[]', newFiles[i]);
}

const response = await axios.post(`/api/admin/captain-orders/${captainId}`, formData, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'multipart/form-data'
  }
});
```
