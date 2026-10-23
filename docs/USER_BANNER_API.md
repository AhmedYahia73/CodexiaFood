# توثيق واجهة عرض البانرات للعملاء (تطبيق المستخدم والموقع)
## User Banners API Documentation

دليل تقني مخصص لمطوري تطبيقات الموبايل (Flutter/React Native) والويب لجلب البانرات الإعلانية بدون توكن (Public Endpoint) لعرضها في السلايدر أو الكاروسيل (Carousel).

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة (Overview)](#1-نظرة-عامة-overview)
2. [الهيدرز والمصادقة (Headers & Authentication)](#2-الهيدرز-والمصادقة-headers--authentication)
3. [واجهة جلب البانرات (GET /api/user/banners)](#3-واجهة-جلب-البانرات-get-apiuserbanners)
4. [هيكل البيانات المستلمة (Response Structure)](#4-هيكل-البيانات-المستلمة-response-structure)
5. [أمثلة كود للتكامل (Flutter & Web Examples)](#5-أمثلة-كود-للتكامل-flutter--web-examples)

---

## 1. نظرة عامة (Overview)

* **عامة وبدون تسجيل دخول (No Token Required)**: هذه الواجهة عامة ومتاحة لجميع الزوار والتطبيقات دون الحاجة لأي Bearer Token.
* **روابط صور جاهزة للعرض المباشر**: يُرجع الحقل `image` رابط الـ URL الكامل للصورة مباشرة (`https://...` أو `http://.../storage/banners/...`) بحيث يمكن تمريره مباشرة إلى أي مكون عرض صور مثل `Image.network` في Flutter أو علامة `<img>` في الويب.
* **الترتيب**: يتم ترتيب البانرات تلقائياً من الأحدث إلى الأقدم.

---

## 2. الهيدرز والمصادقة (Headers & Authentication)

لا تتطلب هذه الواجهة أي توكن مصادقة.

| الهيدر (Header) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `Accept` | String | **نعم** | صيغة الاستجابة المطلوبة | `application/json` |
| `lang` | String | اختياري | لغة الاستجابة | `ar` أو `en` |

---

## 3. واجهة جلب البانرات (GET /api/user/banners)

جلب جميع البانرات الإعلانية النشطة.

* **الرابط (URL)**: `/api/user/banners`
* **نوع الطلب (HTTP Method)**: `GET`
* **المعاملات (Query Parameters)**:
  * `lang` (string, اختياري): كود اللغة (`ar` أو `en`).

### استجابة ناجحة (`200 OK`):
```json
{
  "status": true,
  "data": [
    {
      "id": 13,
      "image": "https://ecommerce.mazoom.online/storage/banners/promo_weekend.webp",
      "created_at": "2026-10-23T20:00:00.000000Z"
    },
    {
      "id": 12,
      "image": "https://ecommerce.mazoom.online/storage/banners/coffee_offer.webp",
      "created_at": "2026-10-23T19:30:00.000000Z"
    }
  ]
}
```

### في حالة عدم وجود بانرات مسجلة:
```json
{
  "status": true,
  "data": []
}
```

---

## 4. هيكل البيانات المستلمة (Response Structure)

| الحقل (Field) | النوع | الوصف |
|---|---|---|
| `status` | Boolean | حالة العملية (`true` دائماً في حال النجاح). |
| `data` | Array | مصفوفة تحتوي على كائنات البانرات. |
| `data[].id` | Integer | المعرف الفريد للبانر. |
| `data[].image` | String (URL) | رابط الـ URL المباشر والكامل لصورة البانر. |
| `data[].created_at` | String (ISO 8601) | تاريخ ووقت إنشاء البانر. |

---

## 5. أمثلة كود للتكامل (Flutter & Web Examples)

### أ. مثال في تطبيق Flutter (Dart):

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class BannerModel {
  final int id;
  final String image;

  BannerModel({required this.id, required this.image});

  factory BannerModel.fromJson(Map<String, dynamic> json) {
    return BannerModel(
      id: json['id'],
      image: json['image'],
    );
  }
}

Future<List<BannerModel>> fetchBanners() async {
  final url = Uri.parse('https://ecommerce.mazoom.online/api/user/banners');
  final response = await http.get(
    url,
    headers: {
      'Accept': 'application/json',
      'lang': 'ar',
    },
  );

  if (response.statusCode == 200) {
    final Map<String, dynamic> body = jsonDecode(response.body);
    final List<dynamic> data = body['data'];
    return data.map((item) => BannerModel.fromJson(item)).toList();
  } else {
    throw Exception('فشل في جلب البانرات الإعلانية');
  }
}
```

### استخدام البيانات في Carousel Slider داخل Flutter:
```dart
CarouselSlider(
  options: CarouselOptions(autoPlay: true, enlargeCenterPage: true),
  items: bannersList.map((banner) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: Image.network(
        banner.image,
        fit: BoxFit.cover,
        width: double.infinity,
        errorBuilder: (context, error, stackTrace) =>
            const Icon(Icons.broken_image, size: 50),
      ),
    );
  }).toList(),
)
```

### ب. مثال باستخدام cURL:
```bash
curl -X GET "https://ecommerce.mazoom.online/api/user/banners" \
  -H "Accept: application/json"
```

### ج. مثال في JavaScript (Fetch API):
```javascript
async function getBanners() {
  try {
    const response = await fetch('https://ecommerce.mazoom.online/api/user/banners', {
      headers: {
        'Accept': 'application/json'
      }
    });
    const result = await response.json();
    if (result.status) {
      console.log('روابط البانرات:', result.data.map(b => b.image));
    }
  } catch (error) {
    console.error('خطأ في جلب البانرات:', error);
  }
}
```
