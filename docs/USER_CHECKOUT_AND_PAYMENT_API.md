# توثيق واجهات طرق الدفع وتأكيد الطلب مع إيصال التحويل (Payment Methods & Checkout API)

دليل شامل وتفصيلي للمطورين وتطبيقات الفرونت إند (Web / Mobile Apps) للتعامل مع طرق الدفع وإتمام الطلبات (Checkout) وإرفاق إيصال الدفع الإلكتروني (`receipt`).

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة وآلية العمل (Overview & Workflow)](#1-نظرة-عامة-وآلية-العمل-overview--workflow)
2. [الهيدرز العامة وتوجيهات اللغة (General Headers & Localization)](#2-الهيدرز-العامة-وتوجيهات-اللغة-general-headers--localization)
3. [واجهة عرض طرق الدفع (GET /api/user/payment-methods)](#3-واجهة-عرض-طرق-الدفع-get-apiuserpayment-methods)
4. [واجهة إتمام الطلب (POST /api/user/orders/checkout)](#4-واجهة-إتمام-الطلب-post-apiuserorderscheckout)
5. [واجهة عرض تفاصيل الطلب (GET /api/user/orders/{order})](#5-واجهة-عرض-تفاصيل-الطلب-get-apiuserordersorder)
6. [حالات الخطأ ورسائل التحقق (Validation Errors & HTTP Status Codes)](#6-حالات-الخطأ-ورسائل-التحقق-validation-errors--http-status-codes)
7. [دليل الربط لفرونت إند (Frontend Integration Guide)](#7-دليل-الربط-لفرونت-إند-frontend-integration-guide)

---

## 1. نظرة عامة وآلية العمل (Overview & Workflow)

1. **طرق الدفع (Payment Methods)**:
   - يتوفر نوعان من طرق الدفع:
     - **نقدية (`is_cash = true`)**: مثل الدفع عند الاستلام (Cash on Delivery).
     - **غير نقدية / إلكترونية (`is_cash = false`)**: مثل المحافظ الإلكترونية (فودافون كاش، إنستاباي، تحويل بنكي).
2. **إيصال الدفع (`receipt`)**:
   - إذا اختار العميل طريقة دفع **نقدية (`is_cash = true`)**: حقل الإيصال اختياري (`nullable`).
   - إذا اختار العميل طريقة دفع **غير نقدية (`is_cash = false`)**: حقل الإيصال **إلزامي (`required`)** ويجب أن يكون ملف صورة (`image`).
3. **رابط الإيصال في الاستجابة (Receipt URL)**:
   - يتم تخزين صورة الإيصال بأمان في مسار التخزين العام (`storage/orders/receipts/...`).
   - يُرجع `OrderResource` رابط الصورة كاملاً ومباشراً:
     `url("storage/" . $receipt)`
     كما تم توفير كلٍ من المفتاحين `receipt` و `reciept` لضمان التوافق التام مع أي تسمية في الفرونت إند.

---

## 2. الهيدرز العامة وتوجيهات اللغة (General Headers & Localization)

### الهيدرز المقبولة (Request Headers)
| الهيدر (Header) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `Accept` | String | نعم | لتحديد نوع الاستجابة | `application/json` |
| `Content-Type` | String | نعم | عند إرسال ملفات/صور بالطلب | `multipart/form-data` |
| `Accept-Language` أو `lang` | String | اختياري | لغة النصوص المعروضة (`ar` أو `en`) | `ar` |
| `uu_id` أو `X-UU-ID` | String | اختياري | بديل لإرسال معرّف الجهاز/السلة في الهيدر | `device-uuid-12345` |

> **أولوية اللغة**: يتم تحديد اللغة بحسب الأولوية التالية: `query param (?lang=)` ثم `header (lang)` ثم `header (Accept-Language)` ثم لغة التطبيق الافتراضية.

---

## 3. واجهة عرض طرق الدفع (GET /api/user/payment-methods)

تُستخدم هذه الواجهة لجلب طرق الدفع النشطة لعرضها في قائمة اختيار (Dropdown أو Radio Buttons) في شاشة الدفع (Checkout).

* **الرابط (URL)**: `/api/user/payment-methods`
* **نوع الطلب (HTTP Method)**: `GET`
* **التوثيق (Auth)**: **None (عام بدون تسجيل دخول)**

### 3.1. معلمات الاستعلام (Query Parameters)
| المعلمة (Parameter) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `lang` | string | اختياري | لغة أسماء طرق الدفع (`ar` أو `en`) | `ar` |

### 3.2. استجابة النجاح باللغة العربية (Response Example - Arabic)
`GET /api/user/payment-methods?lang=ar`

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "نقداً عند الاستلام",
      "is_cash": true,
      "icon": "http://localhost:8000/storage/payment_methods/cash.png"
    },
    {
      "id": 2,
      "name": "فودافون كاش / إنستاباي",
      "is_cash": false,
      "icon": "http://localhost:8000/storage/payment_methods/instapay.png"
    },
    {
      "id": 3,
      "name": "بطاقة ائتمان (فيزا / ماستركارد)",
      "is_cash": false,
      "icon": "http://localhost:8000/storage/payment_methods/visa.png"
    }
  ]
}
```

### 3.3. استجابة النجاح باللغة الإنجليزية (Response Example - English)
`GET /api/user/payment-methods?lang=en`

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "Cash on Delivery",
      "is_cash": true,
      "icon": "http://localhost:8000/storage/payment_methods/cash.png"
    },
    {
      "id": 2,
      "name": "Vodafone Cash / InstaPay",
      "is_cash": false,
      "icon": "http://localhost:8000/storage/payment_methods/instapay.png"
    },
    {
      "id": 3,
      "name": "Credit / Debit Card",
      "is_cash": false,
      "icon": "http://localhost:8000/storage/payment_methods/visa.png"
    }
  ]
}
```

### 3.4. شرح حقول الاستجابة (Response Fields Breakdown)
- `id` (integer): رقم معرّف طريقة الدفع (يتم إرساله في `payment_method_id` عند إتمام الطلب).
- `name` (string): اسم طريقة الدفع مُعرّب أو مترجم تلقائياً وفقاً للغة المطلوبة (`name[$lang]`).
- `is_cash` (boolean):
  - `true`: دفع كاش / نقدي (لا يتطلب صورة إيصال).
  - `false`: دفع إلكتروني / تحويل (يتطلب إرفاق صورة إيصال `receipt`).
- `icon` (string|null): الرابط الكامل لأيقونة طريقة الدفع (إن وُجدت).

---

## 4. واجهة إتمام الطلب (POST /api/user/orders/checkout)

تقوم بإنشاء الطلب من عناصر سلة المستخدم، والتحقق من أقرب فرع، وحساب الضرائب والخصومات، وتخزين إيصال الدفع.

* **الرابط (URL)**: `/api/user/orders/checkout`
* **نوع الطلب (HTTP Method)**: `POST`
* **نوع المحتوى (Content-Type)**: `multipart/form-data` *(ضروري لدعم رفع الصور)*
* **التوثيق (Auth)**: **None**

### 4.1. حقول جسم الطلب (Request Body Parameters)

| الحقل (Field) | النوع (Type) | إلزامي؟ (Requirement) | قواعد التحقق (Validation Rules) | الوصف | مثال |
|---|---|---|---|---|---|
| `uu_id` | string | **إلزامي** | `required\|string\|max:255` | معرّف جهاز/عميل السلة | `"client-uuid-12345"` |
| `payment_method_id` | integer | **إلزامي** | `required\|integer\|exists:payment_methods,id` | معرّف طريقة الدفع المختارة | `2` |
| `receipt` | File (Image) | **مشروط** | `required_if (is_cash=false)\|image\|mimes:jpeg,png,jpg,webp\|max:5120` | صورة إيصال التحويل (مطلوبة إذا كانت طريقة الدفع غير نقدية) | `receipt.jpg` (ملف) |
| `lat` | float | **إلزامي** | `required\|numeric\|between:-90,90` | خط عرض موقع التوصيل | `29.9650` |
| `lng` | float | **إلزامي** | `required\|numeric\|between:-180,180` | خط طول موقع التوصيل | `31.2580` |
| `address` | string | **إلزامي** | `required\|string\|max:500` | العنوان التفصيلي | `"شارع 9، المعادي، القاهرة"` |
| `phone` | string | **إلزامي** | `required\|string\|max:50` | رقم هاتف المستلم للتواصل | `"01012345678"` |
| `name` | string | **إلزامي** | `required\|string\|max:255` | اسم صاحب الطلب | `"أحمد محمد"` |
| `note` | string | اختياري | `nullable\|string\|max:1000` | ملاحظات العميل على الطلب | `"يرجى الاتصال قبل الوصول"` |
| `module` | string | اختياري | `nullable\|in:delivery,takeaway,dinein` | نوع الطلب (الافتراضي: delivery) | `"delivery"` |
| `lang` | string | اختياري | `nullable\|in:ar,en` | لغة رسائل الاستجابة | `"ar"` |

---

### 4.2. أمثلة إرسال الطلب (Request Body Examples)

#### أ) دفع كاش عند الاستلام (Cash - لا يتطلب إيصال):
```http
POST /api/user/orders/checkout HTTP/1.1
Host: localhost:8000
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryXYZ
Accept: application/json

------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="uu_id"

client-uuid-12345
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="payment_method_id"

1
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="lat"

29.9650
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="lng"

31.2580
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="address"

شارع 9، المعادي، القاهرة
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="phone"

01012345678
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="name"

أحمد محمد
------WebKitFormBoundaryXYZ--
```

#### ب) دفع غير نقدي مع إرفاق إيصال التحويل (Non-Cash - يتطلب إيصال صورة):
```http
POST /api/user/orders/checkout HTTP/1.1
Host: localhost:8000
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryXYZ
Accept: application/json

------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="uu_id"

client-uuid-12345
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="payment_method_id"

2
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="lat"

29.9650
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="lng"

31.2580
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="address"

شارع 9، المعادي، القاهرة
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="phone"

01012345678
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="name"

أحمد محمد
------WebKitFormBoundaryXYZ
Content-Disposition: form-data; name="receipt"; filename="vodafone_receipt.jpg"
Content-Type: image/jpeg

<Binary Image Data Here>
------WebKitFormBoundaryXYZ--
```

---

### 4.3. استجابة النجاح (Response Success - 201 Created)
```json
{
  "status": true,
  "message": "تم إنشاء الطلب بنجاح من السلة",
  "distance_km": 0.85,
  "data": {
    "id": 142,
    "shift_id": null,
    "shift_name": null,
    "cashier_id": null,
    "cashier_man_id": null,
    "hall_table_id": null,
    "financial_account_id": null,
    "financial_id": null,
    "financial_account": null,
    "payment_method_id": 2,
    "payment_method": {
      "id": 2,
      "name": {
        "ar": "فودافون كاش / إنستاباي",
        "en": "Vodafone Cash / InstaPay"
      },
      "description": {
        "ar": "الدفع عبر المحافظ الإلكترونية",
        "en": "Electronic wallet transfer"
      },
      "icon": "http://localhost:8000/storage/payment_methods/instapay.png",
      "status": true,
      "is_cash": false,
      "created_at": "2026-10-20T21:05:00.000000Z",
      "updated_at": "2026-10-20T21:05:00.000000Z"
    },
    "receipt": "http://localhost:8000/storage/orders/receipts/x8b20Gk41pL9.jpg",
    "reciept": "http://localhost:8000/storage/orders/receipts/x8b20Gk41pL9.jpg",
    "delivery_id": null,
    "delivery": null,
    "branch_id": 1,
    "module": "delivery",
    "address": "شارع 9، المعادي، القاهرة",
    "lat": 29.965,
    "lng": 31.258,
    "note": null,
    "phone": "01012345678",
    "name": "أحمد محمد",
    "is_pos": false,
    "paid": true,
    "status": "preparing",
    "total": 240.0,
    "total_tax": 33.6,
    "total_discount": 24.0,
    "final_price": 249.6,
    "branch": {
      "id": 1,
      "name": "فرع المعادي"
    },
    "shift": null,
    "cashier": null,
    "cashier_man": null,
    "hall_table": null,
    "products": [
      {
        "id": 210,
        "product_id": 5,
        "quantity": 2,
        "price": 120.0,
        "note": null
      }
    ],
    "created_at": "2026-10-22T23:15:00.000000Z",
    "updated_at": "2026-10-22T23:15:00.000000Z"
  }
}
```

---

## 5. واجهة عرض تفاصيل الطلب (GET /api/user/orders/{order})

* **الرابط (URL)**: `/api/user/orders/{order}`
* **نوع الطلب (HTTP Method)**: `GET`
* **التوثيق (Auth)**: **None**

### استجابة النجاح (200 OK):
تُرجع بيانات الطلب كاملة بما فيها رابط الإيصال `receipt` وبيانات طريقة الدفع `payment_method`.

---

## 6. حالات الخطأ ورسائل التحقق (Validation Errors & HTTP Status Codes)

### أ) عدم إرسال طريقة الدفع `payment_method_id` (422 Unprocessable Content)
```json
{
  "message": "يرجى اختيار طريقة الدفع.",
  "errors": {
    "payment_method_id": [
      "يرجى اختيار طريقة الدفع."
    ]
  }
}
```

### ب) اختيار طريقة دفع غير نقدية بدون إرفاق صورة الإيصال (422 Unprocessable Content)
```json
{
  "message": "إيصال الدفع مطلوب عند اختيار طريقة دفع غير نقدية.",
  "errors": {
    "receipt": [
      "إيصال الدفع مطلوب عند اختيار طريقة دفع غير نقدية."
    ]
  }
}
```

### ج) إرسال ملف ليس صورة (مثل PDF أو نص) (422 Unprocessable Content)
```json
{
  "message": "يجب أن يكون إيصال الدفع صورة.",
  "errors": {
    "receipt": [
      "يجب أن يكون إيصال الدفع صورة.",
      "يجب أن تكون صورة الإيصال بصيغة: jpeg, png, jpg, webp."
    ]
  }
}
```

### د) السلة فارغة (400 Bad Request)
```json
{
  "status": false,
  "message": "السلة فارغة، يرجى إضافة منتجات إلى السلة أولاً"
}
```

### هـ) خارج نطاق التوصيل (422 Unprocessable Content)
```json
{
  "status": false,
  "message": "عذراً، موقعك الحالي خارج نطاق التوصيل المتاح. المسافة لأقرب فرع (15.5 كم) تتجاوز الحد الأقصى المسموح به (5.00 كم).",
  "distance": 15.5,
  "max_cover": 5.0
}
```

---

## 7. دليل الربط لفرونت إند (Frontend Integration Guide)

### كود توضيحي بـ JavaScript / React / React Native:

```javascript
import axios from 'axios';

// 1. جلب طرق الدفع
async function fetchPaymentMethods(lang = 'ar') {
  const response = await axios.get(`http://your-domain/api/user/payment-methods?lang=${lang}`);
  return response.data.data; 
  // [ { id: 1, name: "نقداً عند الاستلام", is_cash: true }, { id: 2, name: "إنستاباي / فودافون كاش", is_cash: false } ]
}

// 2. إتمام الطلب (Checkout)
async function submitCheckout({ uuId, selectedMethod, receiptFile, customerData }) {
  const formData = new FormData();
  formData.append('uu_id', uuId);
  formData.append('payment_method_id', selectedMethod.id);
  formData.append('lat', customerData.lat);
  formData.append('lng', customerData.lng);
  formData.append('address', customerData.address);
  formData.append('phone', customerData.phone);
  formData.append('name', customerData.name);
  if (customerData.note) formData.append('note', customerData.note);

  // إذا كانت طريقة الدفع غير نقدية، نرفق ملف الصورة
  if (!selectedMethod.is_cash) {
    if (!receiptFile) {
      alert('يرجى إرفاق صورة إيصال التحويل');
      return;
    }
    formData.append('receipt', receiptFile);
  }

  const response = await axios.post('http://your-domain/api/user/orders/checkout', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      'Accept': 'application/json',
      'Accept-Language': 'ar'
    }
  });

  return response.data;
}
```

### كود توضيحي بـ Flutter / Dart:

```dart
import 'dart:io';
import 'package:http/http.dart' as http;

Future<void> checkout({
  required String uuId,
  required int paymentMethodId,
  required bool isCash,
  File? receiptImage,
  required Map<String, dynamic> customerData,
}) async {
  var uri = Uri.parse('http://your-domain/api/user/orders/checkout');
  var request = http.MultipartRequest('POST', uri);

  request.headers['Accept'] = 'application/json';
  request.headers['Accept-Language'] = 'ar';

  request.fields['uu_id'] = uuId;
  request.fields['payment_method_id'] = paymentMethodId.toString();
  request.fields['lat'] = customerData['lat'].toString();
  request.fields['lng'] = customerData['lng'].toString();
  request.fields['address'] = customerData['address'];
  request.fields['phone'] = customerData['phone'];
  request.fields['name'] = customerData['name'];

  if (!isCash && receiptImage != null) {
    request.files.add(await http.MultipartFile.fromPath('receipt', receiptImage.path));
  }

  var response = await request.send();
  var responseData = await response.stream.bytesToString();
  print(responseData);
}
```
