# توثيق واجهات إدارة البانرات الإعلانية - لوحة الإدارة
## Admin Banners API Documentation

دليل تقني مخصص لمطوري لوحة التحكم (Admin Dashboard) لإدارة البانرات الإعلانية (`Banner`)، بما يشمل الرفع، العرض، والحذف مع حذف الصورة القديمة تلقائياً من التخزين.

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة وآلية العمل (Overview)](#1-نظرة-عامة-وآلية-العمل-overview)
2. [الهيدرز والمصادقة (Headers & Authentication)](#2-الهيدرز-والمصادقة-headers--authentication)
3. [عرض قائمة البانرات (GET /api/admin/banners)](#3-عرض-قائمة-البانرات-get-apiadminbanners)
4. [إضافة ورفع بانر جديد (POST /api/admin/banners)](#4-إضافة-ورفع-بانر-جديد-post-apiadminbanners)
5. [عرض تفاصيل بانر محدد (GET /api/admin/banners/{id})](#5-عرض-تفاصيل-بانر-محدد-get-apiadminbannersid)
6. [حذف بانر (DELETE /api/admin/banners/{id})](#6-حذف-بانر-delete-apiadminbannersid)
7. [أكواد الاستجابة ومعالجة الأخطاء (Error Handling)](#7-أكواد-الاستجابة-ومعالجة-الأخطاء-error-handling)
8. [أمثلة برمجية (Code Examples)](#8-أمثلة-برمجية-code-examples)

---

## 1. نظرة عامة وآلية العمل (Overview)

* **الرابط المباشر للصورة (`image`)**: تُرجع جميع الاستجابات حقل `image` كرابط `URL` مباشر وكامل (مثل: `https://domain.com/storage/banners/...`) لعرضه مباشرة دون الحاجة لتركيب مسار الـ Storage في الفرونت إند.
* **إدارة التخزين (Storage Cleanup)**: عند حذف أي بانر (`DELETE`)، يتم حذف ملف الصورة الفعلي من التخزين أولاً (`Storage::disk('public')->delete(...)`) ثم حذف السجل من قاعدة البيانات لتوفير المساحة وتجنب تراكم الملفات اليتيمة.
* **عدم وجود تعديل (No Edit)**: لا يوجد مسار لتعديل البانر (`PUT/PATCH`) وفقاً لقواعد العمل؛ لتغيير البانر يتم حذفه ورفع بانر جديد.

---

## 2. الهيدرز والمصادقة (Headers & Authentication)

تتطلب جميع واجهات لوحة التحكم توثيقاً صالحاً بصلاحية الأدمن (`auth:admin`).

| الهيدر (Header) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `Authorization` | String | **نعم** | توكن الأدمن بنظام Bearer Token | `Bearer eyJhbGciOi...` |
| `Accept` | String | **نعم** | صيغة الاستجابة المطلوبة | `application/json` |
| `Content-Type` | String | نعم (عند الرفع) | نوع البيانات المرسلة للرفع | `multipart/form-data` |
| `lang` | String | اختياري | لغة رسائل الاستجابة (`ar` أو `en`) | `ar` |

---

## 3. عرض قائمة البانرات (GET /api/admin/banners)

جلب قائمة البانرات مرتبة من الأحدث إلى الأقدم مع دعم الترقيم (Pagination).

* **الرابط (URL)**: `/api/admin/banners`
* **نوع الطلب (HTTP Method)**: `GET`
* **المعاملات (Query Parameters)**:
  * `page` (integer, اختياري): رقم الصفحة، الافتراضي `1`.
  * `per_page` (integer, اختياري): عدد العناصر في الصفحة، الافتراضي `15`.

### استجابة ناجحة (`200 OK`):
```json
{
  "data": [
    {
      "id": 12,
      "image": "https://ecommerce.mazoom.online/storage/banners/promo_1729712345.webp",
      "created_at": "2026-10-23T19:30:00.000000Z",
      "updated_at": "2026-10-23T19:30:00.000000Z"
    },
    {
      "id": 11,
      "image": "https://ecommerce.mazoom.online/storage/banners/offer_1729712100.webp",
      "created_at": "2026-10-23T18:45:00.000000Z",
      "updated_at": "2026-10-23T18:45:00.000000Z"
    }
  ],
  "links": {
    "first": "https://ecommerce.mazoom.online/api/admin/banners?page=1",
    "last": "https://ecommerce.mazoom.online/api/admin/banners?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://ecommerce.mazoom.online/api/admin/banners",
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

---

## 4. إضافة ورفع بانر جديد (POST /api/admin/banners)

رفع صورة بانر جديدة وحفظها.

* **الرابط (URL)**: `/api/admin/banners`
* **نوع الطلب (HTTP Method)**: `POST`
* **نوع المحتوى (Content-Type)**: `multipart/form-data`
* **الحقول (Form Data)**:

| الحقل | النوع | إلزامي؟ | القيود | الوصف |
|---|---|---|---|---|
| `image` | File | **نعم** | `image|mimes:jpeg,png,jpg,gif,webp|max:4096` | ملف الصورة (الحد الأقصى 4MB) |

### استجابة ناجحة (`201 Created`):
```json
{
  "status": true,
  "message": "Banner created successfully.",
  "data": {
    "id": 13,
    "image": "https://ecommerce.mazoom.online/storage/banners/banner_new.jpg",
    "created_at": "2026-10-23T20:00:00.000000Z",
    "updated_at": "2026-10-23T20:00:00.000000Z"
  }
}
```

---

## 5. عرض تفاصيل بانر محدد (GET /api/admin/banners/{id})

جلب بيانات بانر واحد برقم المعرف (`id`).

* **الرابط (URL)**: `/api/admin/banners/{id}`
* **نوع الطلب (HTTP Method)**: `GET`

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "data": {
    "id": 13,
    "image": "https://ecommerce.mazoom.online/storage/banners/banner_new.jpg",
    "created_at": "2026-10-23T20:00:00.000000Z",
    "updated_at": "2026-10-23T20:00:00.000000Z"
  }
}
```

---

## 6. حذف بانر (DELETE /api/admin/banners/{id})

حذف البانر؛ يقوم السيرفر بحذف ملف الصورة القديم من مساحة التخزين أولاً، ثم حذف السجل من قاعدة البيانات.

* **الرابط (URL)**: `/api/admin/banners/{id}`
* **نوع الطلب (HTTP Method)**: `DELETE`

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "message": "Banner deleted successfully."
}
```

---

## 7. أكواد الاستجابة ومعالجة الأخطاء (Error Handling)

| كود الحالة (Status Code) | السبب | مثال الاستجابة |
|---|---|---|
| `401 Unauthorized` | التوكن مفقود أو غير صالح أو منتهي | `{"status": false, "message": "Unauthenticated."}` |
| `403 Forbidden` | المستخدم ليس أدمن | `{"status": false, "message": "Forbidden: Invalid role access."}` |
| `404 Not Found` | البانر المطلوب حذفه أو عرضه غير موجود | `{"message": "No query results for model [App\\Models\\Banner] 99"}` |
| `422 Unprocessable Content` | فشل التحقق (مثل عدم إرسال الصورة أو إرسال ملف ليس صورة) | `{"message": "The image field is required.", "errors": {"image": ["The image field is required."]}}` |

---

## 8. أمثلة برمجية (Code Examples)

### أ. إضافة بانر باستخدام cURL:
```bash
curl -X POST "https://ecommerce.mazoom.online/api/admin/banners" \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Accept: application/json" \
  -F "image=@/path/to/promo.jpg"
```

### ب. حذف بانر باستخدام cURL:
```bash
curl -X DELETE "https://ecommerce.mazoom.online/api/admin/banners/13" \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Accept: application/json"
```

### ج. إضافة بانر في JavaScript (Fetch API / FormData):
```javascript
const formData = new FormData();
formData.append('image', fileInputElement.files[0]);

const response = await fetch('https://ecommerce.mazoom.online/api/admin/banners', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${adminToken}`,
    'Accept': 'application/json'
  },
  body: formData
});

const result = await response.json();
console.log(result.data.image); // الرابط الكامل للصورة المرفوعة
```
