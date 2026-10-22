# توثيق واجهات إعدادات المتجر وإدارة صور المنيو - لوحة الإدارة
## Admin Business Setup & Menu API Documentation

دليل تقني تفصيلي لمطوري لوحة التحكم (Admin Dashboard) والفرونت إند للتعامل مع إعدادات المطعم ورفع وإدارة مصفوفة صور المنيو (`menu`) بروابطها المباشرة.

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة وآلية العمل (Overview & Features)](#1-نظرة-عامة-وآلية-العمل-overview--features)
2. [الهيدرز والتوثيق (Headers & Authentication)](#2-الهيدرز-والتوثيق-headers--authentication)
3. [واجهة عرض إعدادات المتجر (GET /api/admin/business-setup)](#3-واجهة-عرض-إعدادات-المتجر-get-apiadminbusiness-setup)
4. [واجهة إنشاء وتحديث إعدادات المتجر والمنيو (POST / PUT /api/admin/business-setup)](#4-واجهة-إنشاء-وتحديث-إعدادات-المتجر-والمنيو-post--put-apiadminbusiness-setup)
5. [حالات التعامل مع حقل المنيو menu (Scenarios)](#5-حالات-التعامل-مع-حقل-المنيو-menu-scenarios)
6. [حالات الخطأ وأكواد الاستجابة (Error Handling)](#6-حالات-الخطأ-وأكواد-الاستجابة-error-handling)
7. [أمثلة كود للربط مع الفرونت إند (Code Examples)](#7-أمثلة-كود-للربط-مع-الفرونت-إند-code-examples)

---

## 1. نظرة عامة وآلية العمل (Overview & Features)

* **نوع الحقل `menu`**: يتم تخزين صور المنيو في قاعدة البيانات كـ `JSON` يقبل القيمة الفارغة (`nullable`).
* **الروابط المباشرة**: تُرجع الواجهات مصفوفة `menu` بروابط `URL` كاملة ومباشرة للصور (`http://domain/storage/...` أو روابط خارجية) لتسهيل عرضها المباشر في الفرونت إند.
* **المسارات الخام (`raw_menu`)**: يُرجع الـ Resource أيضاً مصفوفة `raw_menu` تحتوي على المسارات التخزينية كما هي داخل الـ storage لاستخدامها عند الرغبة.
* **إدارة التخزين الذكية (Storage Management)**:
  - عند رفع صور جديدة، يتم حفظها في مسار `public/business_setup`.
  - عند تحديث المنيو بحذف بعض الصور أو استبدالها، يقوم السيرفر بحذف الملفات القديمة المحذوفة من التخزين تلقائياً لتوفير المساحة وتجنب الملفات اليتيمة.

---

## 2. الهيدرز والتوثيق (Headers & Authentication)

تتطلب جميع واجهات الإدارة توثيق من نوع `Bearer Token` لمستخدم يحمل صلاحية `admin`.

| الهيدر (Header) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `Authorization` | String | **نعم** | توكن الأدمن الصالح (JWT) | `Bearer eyJhbGciOi...` |
| `Accept` | String | **نعم** | نوع الاستجابة المطلوبة | `application/json` |
| `Content-Type` | String | نعم (عند الرفع) | نوع البيانات المرسلة | `multipart/form-data` |
| `lang` | String | اختياري | لغة نصوص الاستجابة | `ar` أو `en` |

---

## 3. واجهة عرض إعدادات المتجر (GET /api/admin/business-setup)

تُستخدم هذه الواجهة لجلب إعدادات المطعم الحالية، بما في ذلك بيانات التواصل وساعات العمل واللوجو وروابط صور المنيو.

* **الرابط (URL)**: `/api/admin/business-setup`
* **نوع الطلب (HTTP Method)**: `GET`
* **التوثيق (Auth)**: `auth:admin` (Admin Token Required)

### 3.1. معلمات الاستعلام (Query Parameters)
| المعلمة | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `lang` | string | اختياري | لغة الاستجابة (`ar` أو `en`) | `ar` |

### 3.2. استجابة النجاح (200 OK Response)
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
    "logo": "http://127.0.0.1:8000/storage/business_setup/logo.png",
    "raw_logo": "business_setup/logo.png",
    "menu": [
      "http://127.0.0.1:8000/storage/business_setup/menu_page_1.webp",
      "http://127.0.0.1:8000/storage/business_setup/menu_page_2.webp"
    ],
    "raw_menu": [
      "business_setup/menu_page_1.webp",
      "business_setup/menu_page_2.webp"
    ],
    "description": "أفضل تجربة طعام لجميع العائلة",
    "branch_cover": 5.0,
    "start_day": "09:00",
    "end_day": "03:00",
    "table_order_url": "https://restaurant.com/table",
    "has_gift": false,
    "price_gift": 1,
    "is_open": true,
    "is_overnight": true,
    "working_hours_text": "من 09:00 إلى 03:00",
    "created_at": "2026-10-22T20:20:00+03:00",
    "updated_at": "2026-10-22T20:25:00+03:00"
  }
}
```

> **ملاحظة**: إذا لم يكن هناك إعدادات مخزنة بعد، فإن حقل `data` يُرجع `null`.

---

## 4. واجهة إنشاء وتحديث إعدادات المتجر والمنيو (POST / PUT /api/admin/business-setup)

تتيح هذه الواجهة إنشاء إعدادات المتجر إذا لم تكن موجودة مسبقاً، أو تحديث السجل الحالي في حال وجوده.

* **الرابط (URL)**:
  - `POST /api/admin/business-setup`
  - أو `PUT /api/admin/business-setup`
  - أو `PUT /api/admin/business-setup/{businessSetup}`
* **نوع الطلب (HTTP Method)**: `POST` أو `PUT`
* **Content-Type**: `multipart/form-data` (عند إرسال ملفات صور جديدة)
* **التوثيق (Auth)**: `auth:admin` (Admin Token Required)

### 4.1. جدول معلمات الطلب (Request Parameters)

| الحقل (Field) | النوع (Type) | الحالة | الوصف | مثال |
|---|---|---|---|---|
| `name` | string | **إلزامي** | اسم المتجر / المطعم (حد أقصى 255 حرف) | `"مطعم كودكسا"` |
| `phone` | string | **إلزامي** | رقم هاتف المتجر الرئيسي | `"01012345678"` |
| `face` | string | **إلزامي** | رابط صفحة الفيسبوك | `"https://facebook.com/codexa"` |
| `instagram` | string | **إلزامي** | رابط حساب الإنستجرام | `"https://instagram.com/codexa"` |
| `whats` | string | **إلزامي** | رقم الواتساب | `"01012345678"` |
| `description` | string | **إلزامي** | وصف تفصيلي للمطعم | `"أفضل المأكولات الشرقية والغربية"` |
| `logo` | File أو String | **إلزامي** | ملف صورة اللوجو أو مساره السابق كـ String | ملف صورة أو `"business_setup/logo.png"` |
| `menu` | Array | **اختياري (Nullable)** | مصفوفة صور المنيو (ملفات جديدة أو روابط/مسارات موجودة) | انظر التفاصيل أدناه |
| `menu.*` | File أو String | اختياري | عنصر صورة داخل مصفوفة المنيو | ملف صورة أو مسار تخزين |
| `branch_cover` | numeric | اختياري | أقصى نطاق تغطية للتوصيل بالكيلومتر (افتراضي 5.0) | `7.5` |
| `start_day` | string | اختياري | وقت بداية يوم العمل بتنسيق `HH:mm` أو `HH:mm:ss` | `"09:00"` |
| `end_day` | string | اختياري | وقت نهاية يوم العمل بتنسيق `HH:mm` أو `HH:mm:ss` | `"03:00"` |
| `table_order_url` | string | اختياري | رابط واجهة طلبات الطاولة المخصص | `"https://table.domain.com"` |
| `has_gift` | boolean | اختياري | تفعيل عرض الهدايا الترويجي | `true` أو `false` |
| `price_gift` | integer | اختياري | سعر الهدية عند تفعيل العرض | `1` |

---

## 5. حالات التعامل مع حقل المنيو menu (Scenarios)

تم تصميم المعالجة لتكون مرنة تماماً لجميع السيناريوهات الشائعة في الفرونت إند:

### الحالة الأولى: رفع صور منيو جديدة بالكامل لأول مرة
عند استخدام `FormData` في المتصفح أو تطبيق الموبايل:
```text
name: "مطعم كودكسا"
... [باقي الحقول الإلزامية] ...
menu[0]: [ملف صورة 1 - UploadedFile]
menu[1]: [ملف صورة 2 - UploadedFile]
```

### الحالة الثانية: الإبقاء على بعض الصور القديمة وإضافة صور جديدة
عندما يقوم الأدمن بحذف صفحة معينة وإبقاء صفحة وإضافة صفحة جديدة:
```text
name: "مطعم كودكسا"
... [باقي الحقول الإلزامية] ...
menu[0]: "business_setup/page1.webp"  <-- صورة قديمة تم الاحتفاظ بها (أو الرابط الكامل)
menu[1]: [ملف صورة جديدة - UploadedFile] <-- صورة جديدة تم رفعها
```
> **ملاحظة**: يقوم السيرفر تلقائياً بمقارنة الصور، وحذف أي صورة قديمة أزيلت من المصفوفة من مسار التخزين.

### الحالة الثالثة: تفريغ المنيو بالكامل (Clear Menu)
لحذف جميع صور المنيو وجعل الحقل فارغاً:
إرسال `menu` كـ `null`:
```json
{
  "name": "مطعم كودكسا",
  ...
  "menu": null
}
```
أو في `FormData`:
```text
menu: null
```
سيتم حذف جميع ملفات المنيو من مسار التخزين وتعيين الحقل إلى `null` في قاعدة البيانات.

### الحالة الرابعة: عدم تعديل المنيو
إذا لم يتم إرسال حقل `menu` نهائياً في الـ Request، سيحتفظ السيرفر بقيمة المنيو الحالية في قاعدة البيانات دون أي تغيير.

---

## 6. حالات الخطأ وأكواد الاستجابة (Error Handling)

### 6.1. غير مصرح (401 Unauthorized)
يحدث عند عدم إرسال التوكن أو إرسال توكن غير صالح أو منتهي الصلاحية:
```json
{
  "message": "Unauthenticated."
}
```

### 6.2. أخطاء التحقق من صحة المدخلات (422 Unprocessable Entity)
يحدث عند نقص أي حقل إلزامي أو إرسال صيغة غير مقبولة:
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "phone": [
      "The phone field is required."
    ],
    "logo": [
      "The logo field is required."
    ]
  }
}
```

---

## 7. أمثلة كود للربط مع الفرونت إند (Code Examples)

### 7.1. مثال cURL (رفع صور جديدة)
```bash
curl -X POST "http://127.0.0.1:8000/api/admin/business-setup" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Accept: application/json" \
  -F "name=مطعم كودكسا" \
  -F "phone=01012345678" \
  -F "face=https://facebook.com/codexa" \
  -F "instagram=https://instagram.com/codexa" \
  -F "whats=01012345678" \
  -F "description=أفضل وجبات طعام" \
  -F "logo=@/path/to/logo.png" \
  -F "menu[]=@/path/to/menu_page1.png" \
  -F "menu[]=@/path/to/menu_page2.png"
```

### 7.2. مثال JavaScript / Axios (لوحة التحكم)
```javascript
import axios from 'axios';

async function updateBusinessSetup(adminToken, formDataValues, existingImages, newImageFiles) {
  const formData = new FormData();

  // 1. إضافة البيانات الأساسية
  formData.append('name', formDataValues.name);
  formData.append('phone', formDataValues.phone);
  formData.append('face', formDataValues.face);
  formData.append('instagram', formDataValues.instagram);
  formData.append('whats', formDataValues.whats);
  formData.append('description', formDataValues.description);
  
  // اللوجو (إذا كان ملفاً جديداً أو الرابط القديم)
  if (formDataValues.newLogoFile) {
    formData.append('logo', formDataValues.newLogoFile);
  } else {
    formData.append('logo', formDataValues.existingLogoPath);
  }

  // 2. إضافة الصور المحتفظ بها من المنيو السابق
  let menuIndex = 0;
  existingImages.forEach((imgUrl) => {
    formData.append(`menu[${menuIndex}]`, imgUrl);
    menuIndex++;
  });

  // 3. إضافة الصور الجديدة المرفوعة للمنيو
  newImageFiles.forEach((file) => {
    formData.append(`menu[${menuIndex}]`, file);
    menuIndex++;
  });

  try {
    const response = await axios.post('/api/admin/business-setup', formData, {
      headers: {
        'Authorization': `Bearer ${adminToken}`,
        'Accept': 'application/json',
        'Content-Type': 'multipart/form-data',
      },
    });

    console.log('Updated successfully:', response.data.data.menu);
    return response.data;
  } catch (error) {
    console.error('Validation errors:', error.response?.data?.errors);
    throw error;
  }
}
```
