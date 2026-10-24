# توثيق واجهات كابتن الطلبات (Captain Order API Documentation)

دليل تقني مخصص ومستقل لإدارة وتكامل كباتن الطلبات (`CaptainOrder`)، يشمل تفاصيل الـ Headers، الـ Endpoints، والـ Body، والـ Responses، وآلية رفع الصور وحذفها من السيرفر.

---

## 📌 الفهرس (Table of Contents)
1. [الـ Headers والمصادقة (Headers & Authentication)](#1-الـ-headers-والمصادقة-headers--authentication)
2. [عرض قائمة كباتن الطلبات (GET /api/admin/captain-orders)](#2-عرض-قائمة-كباتن-الطلبات-get-apiadmincaptain-orders)
3. [قائمة الاختيار السريع (GET /api/admin/captain-orders/select-options)](#3-قائمة-الاختيار-السريع-get-apiadmincaptain-ordersselect-options)
4. [إضافة كابتن اوردر جديد مع رفع الصور (POST /api/admin/captain-orders)](#4-إضافة-كابتن-اوردر-جديد-مع-رفع-الصور-post-apiadmincaptain-orders)
5. [عرض تفاصيل كابتن اوردر (GET /api/admin/captain-orders/{id})](#5-عرض-تفاصيل-كابتن-اوردر-get-apiadmincaptain-ordersid)
6. [تعديل كابتن اوردر وإدارة الصور new_images و deleted_images (POST/PUT)](#6-تعديل-كابتن-اوردر-وإدارة-الصور-new_images-و-deleted_images-postput)
7. [حذف كابتن اوردر وحذف صوره من السيرفر (DELETE)](#7-حذف-كابتن-اوردر-وحذف-صوره-من-السيرفر-delete)
8. [ربط الطلبات بكابتن اوردر (Order - captain_order_id)](#8-ربط-الطلبات-بكابتن-اوردر-order---captain_order_id)

---

## 1. الـ Headers والمصادقة (Headers & Authentication)

| Header | Type | Value | Description |
|:---|:---|:---|:---|
| `Authorization` | String | `Bearer {admin_token}` | توكن المصادقة |
| `Accept` | String | `application/json` | استقبال الردود بصيغة JSON |
| `Content-Type` | String | `multipart/form-data` | إلزامي عند إرسال ملفات صور |

---

## 2. عرض قائمة كباتن الطلبات (GET /api/admin/captain-orders)

* **المسار:** `GET /api/admin/captain-orders`
* **الـ Query Parameters:**
  * `page` (اختياري, integer): رقم الصفحة (الافتراضي: `1`).
  * `per_page` (اختياري, integer): عدد النتائج في الصفحة (الافتراضي: `15`).
  * `search` (اختياري, string): بحث بالاسم أو اسم المستخدم.
  * `status` (اختياري, boolean): فلترة بالحالة (`1` للنشط، `0` للملغي/غير النشط).

* **شكل الاستجابة (Response 200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "كابتن أحمد",
      "user_name": "captain_ahmed",
      "status": true,
      "images": [
        "http://localhost:8000/storage/captain_orders/lic_1.webp"
      ],
      "raw_images": [
        "captain_orders/lic_1.webp"
      ],
      "created_at": "2026-10-24T18:55:00+03:00",
      "updated_at": "2026-10-24T18:55:00+03:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

## 3. قائمة الاختيار السريع (GET /api/admin/captain-orders/select-options)

* **المسار:** `GET /api/admin/captain-orders/select-options`
* **الوصف:** جلب كباتن الطلبات النشطين فقط لاختيارهم في شاشات إنشاء وتعديل الأوردر.
* **شكل الاستجابة (Response 200 OK):**
```json
{
  "status": true,
  "data": {
    "captain_orders": [
      {
        "id": 1,
        "name": "كابتن أحمد",
        "user_name": "captain_ahmed"
      }
    ]
  }
}
```

---

## 4. إضافة كابتن اوردر جديد مع رفع الصور (POST /api/admin/captain-orders)

* **المسار:** `POST /api/admin/captain-orders`
* **النوع:** `multipart/form-data`
* **الـ Body (Form-Data):**
  * `name` (string - إلزامي): اسم الكابتن.
  * `user_name` (string - إلزامي): اسم مستخدم فريد (unique).
  * `password` (string - إلزامي): كلمة المرور (6 حروف أو أكثر، يتم تشفيرها تلقائياً).
  * `status` (boolean - اختياري): الحالة (1 أو 0، الافتراضي 1).
  * `images[]` (file - اختياري): ملفات الصور (jpeg, png, jpg, gif, webp).

* **شكل الاستجابة (Response 201 Created):**
```json
{
  "status": true,
  "message": "Captain order created successfully.",
  "data": {
    "id": 1,
    "name": "كابتن أحمد",
    "user_name": "captain_ahmed",
    "status": true,
    "images": [
      "http://localhost:8000/storage/captain_orders/lic_1.webp"
    ],
    "raw_images": [
      "captain_orders/lic_1.webp"
    ],
    "created_at": "2026-10-24T18:55:00+03:00",
    "updated_at": "2026-10-24T18:55:00+03:00"
  }
}
```

---

## 5. عرض تفاصيل كابتن اوردر (GET /api/admin/captain-orders/{id})

* **المسار:** `GET /api/admin/captain-orders/{id}`
* **شكل الاستجابة (Response 200 OK):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "كابتن أحمد",
    "user_name": "captain_ahmed",
    "status": true,
    "images": [
      "http://localhost:8000/storage/captain_orders/lic_1.webp"
    ],
    "raw_images": [
      "captain_orders/lic_1.webp"
    ],
    "created_at": "2026-10-24T18:55:00+03:00",
    "updated_at": "2026-10-24T18:55:00+03:00"
  }
}
```

---

## 6. تعديل كابتن اوردر وإدارة الصور new_images و deleted_images (POST/PUT)

* **المسار:** `POST /api/admin/captain-orders/{id}` (مع `_method=PUT`) أو `PUT /api/admin/captain-orders/{id}`
* **النوع:** `multipart/form-data`
* **الـ Body (Form-Data):**
  * `_method`: `PUT` (موصى به عند استخدام POST).
  * `name` (string - اختياري): الاسم المعدل.
  * `user_name` (string - اختياري): اسم المستخدم المعدل.
  * `password` (string - اختياري): كلمة المرور الجديدة (إذا أردت تغييرها).
  * `status` (boolean - اختياري): الحالة الجديدة (`1` أو `0`).
  * `deleted_images[]` (array/string - اختياري): مصفوفة روابط أو مسارات الصور التي تريد **مسحها من السيرفر نهائياً**.
  * `new_images[]` (file - اختياري): ملفات صور جديدة يتم رفعها على السيرفر ودمجها مع الصور الباقية.

> ⚡ **كيف تعمل آلية الصور عند التعديل؟**
> 1. يتم أخذ مصفوفة `deleted_images`، ويقوم الكنترولر بحذف ملفات الصور الفعلية من الهارد ديسك (`Storage::disk('public')->delete(...)`).
> 2. يتم استبعاد الصور المحذوفة من مصفوفة صور الكابتن.
> 3. يتم رفع الصور الجديدة في `new_images` على السيرفر وإضافتها للمصفوفة.
> 4. حفظ البيانات المحدثة.

* **شكل الاستجابة (Response 200 OK):**
```json
{
  "status": true,
  "message": "Captain order updated successfully.",
  "data": {
    "id": 1,
    "name": "كابتن أحمد المعدل",
    "user_name": "captain_ahmed",
    "status": true,
    "images": [
      "http://localhost:8000/storage/captain_orders/new_file.webp"
    ],
    "raw_images": [
      "captain_orders/new_file.webp"
    ],
    "created_at": "2026-10-24T18:55:00+03:00",
    "updated_at": "2026-10-24T19:00:00+03:00"
  }
}
```

---

## 7. حذف كابتن اوردر وحذف صوره من السيرفر (DELETE)

* **المسار:** `DELETE /api/admin/captain-orders/{id}`
* **الآلية:** يتم استخراج كافة الصور المسجلة للكابتن، ومسح جميع الملفات فعلياً من قرص التخزين أولاً، ثم حذف السجل من قاعدة البيانات.
* **شكل الاستجابة (Response 200 OK):**
```json
{
  "status": true,
  "message": "Captain order deleted successfully."
}
```

---

## 8. ربط الطلبات بكابتن اوردر (Order - captain_order_id)

عند إرسال طلب جديد `POST /api/admin/orders`، يمكنك تمرير المفتاح:
```json
{
  "module": "delivery",
  "captain_order_id": 1,
  "total": 100.00,
  "final_price": 100.00
}
```
وفي الاستجابة `GET /api/admin/orders/{id}` يظهر الكابتن المرتبط:
```json
{
  "id": 10,
  "captain_order_id": 1,
  "captain_order": {
    "id": 1,
    "name": "كابتن أحمد",
    "user_name": "captain_ahmed",
    "status": true,
    "images": [ ... ]
  }
}
```
