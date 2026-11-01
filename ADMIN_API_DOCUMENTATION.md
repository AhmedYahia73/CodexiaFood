# توثيق الـ API الخاص بلوحة الإدارة (Admin API Documentation)
## تحديثات إعدادات المتجر وضريبة الصالة (Business Setup & Dine-in Tax)

هذا الملف موجه لمطوري الواجهة الأمامية (Frontend Developers) للوحة تحكم الإدارة (Admin Dashboard).

---

## 📌 نبذة عامة عن التعديل (Overview)
تمت إضافة حقل جديد في إعدادات النظام `business_setups` باسم:
- **`dinein_tax`**: نسبة ضريبة الصالة / الطلب المحلي (Dine-in Tax Percentage).
- **النوع البرمجي**: `double` / `float` (رقم عشري يمثل نسبة مئوية، مثل: `14.0` أو `12.5`).
- **القيمة الافتراضية**: `0.0`.
- **الوظيفة**: يتم تطبيق هذه النسبة تلقائياً كضريبة على أسعار المنتجات في شاشة الكاشير عند اختيار نوع الطلب محلي / صالة (`module = dinein`).

---

## 🔐 معلومات المصادقة والترويسات المشتركة (Headers)
يجب إرسال الترويسات التالية مع جميع طلبات الـ Admin:

```http
Authorization: Bearer <ADMIN_JWT_TOKEN>
Accept: application/json
```

---

## 1️⃣ استرجاع إعدادات المتجر (Get Business Setup)

جلب بيانات إعدادات المطعم لعرضها في شاشة الإعدادات العامة (General Settings).

- **Method**: `GET`
- **URL**: `/api/admin/business-setup`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  ```
- **Query Parameters**:
  | المعامل (Param) | النوع | إجباري/اختياري | الوصف |
  |---|---|---|---|
  | `lang` | string | اختياري | لغة النصوص (`ar` أو `en`). القيمة الافتراضية: `ar`. |

### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
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
      "http://127.0.0.1:8000/storage/business_setup/menu1.png"
    ],
    "raw_menu": [
      "business_setup/menu1.png"
    ],
    "description": "أفضل تجربة طعام لجميع العائلة",
    "branch_cover": 5.0,
    "start_day": "09:00",
    "end_day": "03:00",
    "table_order_url": "https://order.codexa.com",
    "has_gift": false,
    "price_gift": 1,
    "dinein_tax": 14.0,
    "is_open": true,
    "is_overnight": true,
    "working_hours_text": "من 09:00 إلى 03:00",
    "created_at": "2026-10-22T20:28:45.000000Z",
    "updated_at": "2026-11-01T21:30:00.000000Z"
  }
}
```

> 💡 **ملاحظة**: الحقل الجديد `dinein_tax` يرجع كـ `float` ويمثل النسبة المئوية لضريبة الصالة (في المثال أعلاه `14.0` تعني `14%`).

---

## 2️⃣ إنشاء أو تحديث إعدادات المتجر (Update Business Setup)

حفظ أو تعديل بيانات المتجر بما فيها نسبة ضريبة الصالة.

- **Method**: `POST` أو `PUT`
- **URL**: `/api/admin/business-setup` أو `/api/admin/business-setup/{id?}`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  Content-Type: multipart/form-data
  ```
  *(أو `application/json` إذا لم يكن هناك ملف شعار أو صور منيو يتم رفعها).*

### 📥 معاملات جسم الطلب (Request Body Parameters):

| الحقل (Field) | النوع | إجباري/اختياري | الوصف | مثال |
|---|---|---|---|---|
| `name` | string | إجباري | اسم المطعم | `"مطعم كودكسا"` |
| `phone` | string | إجباري | رقم هاتف المطعم | `"01012345678"` |
| `face` | string | إجباري | رابط صفحة الفيسبوك | `"https://facebook.com/codexa"` |
| `instagram` | string | إجباري | رابط حساب الانستجرام | `"https://instagram.com/codexa"` |
| `whats` | string | إجباري | رقم الواتساب | `"01012345678"` |
| `description` | string | إجباري | وصف المطعم | `"أفضل المأكولات الشرقية والغربية"` |
| `logo` | File أو string | إجباري | ملف صورة الشعار (image) أو مسار نصي لصورة موجودة | ملف صورة |
| **`dinein_tax`** | **number (float)** | **اختياري (Nullable)** | **نسبة ضريبة الصالة / الطلب المحلي (كنسبة مئوية)، يجب أن تكون أكبر من أو تساوي 0** | **`14.0` أو `12.5` أو `0`** |
| `branch_cover` | number | اختياري | نطاق تغطية الفرع بالكيلومتر | `5.0` |
| `start_day` | string | اختياري | وقت بدء العمل (HH:MM) | `"09:00"` |
| `end_day` | string | اختياري | وقت انتهاء العمل (HH:MM) | `"03:00"` |
| `table_order_url` | string | اختياري | رابط طلبات الطاولات | `"https://order.codexa.com"` |
| `has_gift` | boolean | اختياري | تفعيل عروض الهدايا (1 / 0 / true / false) | `false` |
| `price_gift` | integer | اختياري | سعر عنصر الهدية | `1` |
| `menu` | array | اختياري | مصفوفة ملفات صور المنيو أو روابط نصية | صور المنيو |

### 🟢 نموذج جسم الطلب (Request Body Example - JSON):
```json
{
  "name": "مطعم كودكسا",
  "phone": "01012345678",
  "face": "https://facebook.com/codexa",
  "instagram": "https://instagram.com/codexa",
  "whats": "01012345678",
  "description": "وصف المطعم وتفاصيله",
  "logo": "business_setup/logo.png",
  "dinein_tax": 14.0,
  "has_gift": false,
  "price_gift": 1,
  "branch_cover": 5.0,
  "start_day": "09:00",
  "end_day": "03:00"
}
```

### 🟢 نموذج استجابة الحفظ الناجح (Success Response - 200 OK):
```json
{
  "status": true,
  "message": "Business setup updated successfully.",
  "data": {
    "id": 1,
    "name": "مطعم كودكسا",
    "phone": "01012345678",
    "face": "https://facebook.com/codexa",
    "instagram": "https://instagram.com/codexa",
    "whats": "01012345678",
    "logo": "http://127.0.0.1:8000/storage/business_setup/logo.png",
    "raw_logo": "business_setup/logo.png",
    "menu": [],
    "raw_menu": null,
    "description": "وصف المطعم وتفاصيله",
    "branch_cover": 5.0,
    "start_day": "09:00",
    "end_day": "03:00",
    "table_order_url": null,
    "has_gift": false,
    "price_gift": 1,
    "dinein_tax": 14.0,
    "is_open": true,
    "is_overnight": true,
    "working_hours_text": "من 09:00 إلى 03:00",
    "created_at": "2026-10-22T20:28:45.000000Z",
    "updated_at": "2026-11-01T21:35:10.000000Z"
  }
}
```

### 🔴 نموذج خطأ التحقق (Validation Error - 422 Unprocessable Entity):
إذا تم إرسال قيمة سالبة أو نص غير رقمي لحقل `dinein_tax`:
```json
{
  "message": "The dinein tax field must be at least 0.",
  "errors": {
    "dinein_tax": [
      "The dinein tax field must be at least 0."
    ]
  }
}
```

---

## 🎨 تعليمات واجهة المستخدم لمطور الفرونت إند (Frontend UI Guidelines)

1. **حقل إدخال النسبة في شاشة الإعدادات (Settings Form Input)**:
   - يفضل استخدام عنصر `input` من نوع رقمي:
     ```html
     <input type="number" step="0.01" min="0" max="100" name="dinein_tax" placeholder="مثال: 14.0" />
     ```
   - ضع علامة مئوية `%` بجانب الحقل لتوضيح أن القيمة المدخلة هي نسبة مئوية.
2. **العرض الأولي في النموذج (Initial Form State)**:
   - عند فتح صفحة الإعدادات، يتم ملء الحقل بالقيمة المرجعة من `GET /api/admin/business-setup` في حقل `data.dinein_tax` (إذا كانت فارغة، يتم وضع `0.0`).
3. **التأثير على بقية أجهزة النظام**:
   - بمجرد قيام الأدمن بحفظ `dinein_tax`، تنعكس النسبة تلقائياً على جميع نقاط بيع الكاشير وأجهزة الـ POS لطلبات الصالة فوراً دون الحاجة لأي تعديل إضافي.
