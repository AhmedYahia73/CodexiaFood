# توثيق واجهات المنيو وبيانات المتجر العامة للمستخدمين (Public User Menu API)
## User Menu & Business Setup API Documentation (No Auth)

دليل موجه لمطوري تطبيقات الموبايل (Flutter / React Native) ومواقع وتطبيقات الويب (React / Vue / Next.js) لعرض صور المنيو وبيانات المطعم العامة مباشرة بدون الحاجة لأي تسجيل دخول أو توكن (`No Auth`).

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة (Overview)](#1-نظرة-عامة-overview)
2. [الهيدرز العامة وتحديد اللغة (General Headers & Localization)](#2-الهيدرز-العامة-وتحديد-اللغة-general-headers--localization)
3. [واجهة جلب صور المنيو المباشرة (GET /api/user/menu)](#3-واجهة-جلب-صور-المنيو-المباشرة-get-apiusermenu)
4. [واجهة إعدادات وبيانات المتجر الشاملة (GET /api/user/business-setup)](#4-واجهة-إعدادات-وبيانات-المتجر-الشاملة-get-apiuserbusiness-setup)
5. [أمثلة الربط والتكامل (Integration Code Examples)](#5-أمثلة-الربط-والتكامل-integration-code-examples)
6. [نصائح وتوجيهات للفرونت إند والموبايل (Frontend & Mobile Best Practices)](#6-نصائح-وتوجيهات-للفرونت-إند-والموبايل-frontend--mobile-best-practices)

---

## 1. نظرة عامة (Overview)

* **عام وبدون توكن (Public / No Token)**: الواجهات مفتوحة للعملاء والزوار ولا تتطلب أي توكن أو صلاحيات.
* **روابط صور جاهزة (Full Direct URLs)**: جميع الصور في مصفوفة `menu` تُرجع كروابط كاملة ومباشرة تبدأ بـ `http://` أو `https://` لتمريرها مباشرة لمكونات العرض (`<img src="...">` أو `Image.network(...)`).
* **استجابة آمنة وموحدة**: في حال عدم وجود صور منيو مضافة، تُرجع واجهة `/api/user/menu` مصفوفة فارغة `[]` لتفادي أخطاء الـ `null pointer` في تطبيقات الموبايل والفرونت إند.

---

## 2. الهيدرز العامة وتحديد اللغة (General Headers & Localization)

| الهيدر (Header) | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `Accept` | String | **نعم** | تحديد صيغة الاستجابة المطلوبة | `application/json` |
| `Accept-Language` أو `lang` | String | اختياري | لغة نصوص الاستجابة ورسائل النظام (`ar` أو `en`) | `ar` |

---

## 3. واجهة جلب صور المنيو المباشرة (GET /api/user/menu)

واجهة مخصصة وسريعة ومباشرة مخصصة لجلب مصفوفة روابط صور صفحات المنيو لعرضها في معرض صور، سلايدر (Slider/Carousel)، أو قارئ PDF/صور.

* **الرابط (URL)**: `/api/user/menu`
* **نوع الطلب (HTTP Method)**: `GET`
* **التوثيق (Auth)**: **None (بدون توكن)**
* **جسم الطلب (Request Body)**: لا يوجد (`None`)

### 3.1. معلمات الاستعلام (Query Parameters)
| المعلمة (Parameter) | النوع | إلزامي؟ | الوصف | القيم المتاحة |
|---|---|---|---|---|
| `lang` | string | اختياري | لغة الاستجابة | `ar` أو `en` |

---

### 3.2. استجابة النجاح عند وجود صور منيو (200 OK Response)
```json
{
  "status": true,
  "data": [
    "http://127.0.0.1:8000/storage/business_setup/menu_page_1_1729628400.webp",
    "http://127.0.0.1:8000/storage/business_setup/menu_page_2_1729628405.webp",
    "http://127.0.0.1:8000/storage/business_setup/menu_page_3_1729628410.webp"
  ]
}
```

---

### 3.3. استجابة النجاح عند عدم وجود صور منيو (200 OK - Empty State)
عندما لا يكون المطعم قد قام برفع صور للمنيو، تُرجع الواجهة مصفوفة فارغة `[]` بنجاح:
```json
{
  "status": true,
  "data": []
}
```

---

## 4. واجهة إعدادات وبيانات المتجر الشاملة (GET /api/user/business-setup)

تُستخدم هذه الواجهة عند فتح التطبيق لجلب كامل بيانات المطعم: اللوجو، ساعات العمل، حالة الفتح/الإغلاق اللحظية، نطاق التغطية، وحسابات التواصل الاجتماعي، بالإضافة إلى روابط صور المنيو.

* **الرابط (URL)**: `/api/user/business-setup`
* **نوع الطلب (HTTP Method)**: `GET`
* **التوثيق (Auth)**: **None (بدون توكن)**
* **جسم الطلب (Request Body)**: لا يوجد (`None`)

### 4.1. معلمات الاستعلام (Query Parameters)
| المعلمة | النوع | إلزامي؟ | الوصف | مثال |
|---|---|---|---|---|
| `lang` | string | اختياري | لغة الاستجابة (`ar` أو `en`) | `ar` |

---

### 4.2. استجابة النجاح الكاملة (200 OK Response)
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
    "logo": "http://127.0.0.1:8000/storage/business_setup/restaurant_logo.png",
    "raw_logo": "business_setup/restaurant_logo.png",
    "menu": [
      "http://127.0.0.1:8000/storage/business_setup/menu_page_1.webp",
      "http://127.0.0.1:8000/storage/business_setup/menu_page_2.webp"
    ],
    "raw_menu": [
      "business_setup/menu_page_1.webp",
      "business_setup/menu_page_2.webp"
    ],
    "description": "أفضل تجربة طعام لجميع العائلة مع خدمة توصيل سريعة",
    "branch_cover": 5.0,
    "start_day": "09:00",
    "end_day": "03:00",
    "table_order_url": "https://table.codexa.com",
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

---

## 5. أمثلة الربط والتكامل (Integration Code Examples)

### 5.1. مثال تطبيق موبايل بـ Flutter (Dart)
```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class MenuService {
  static const String baseUrl = 'http://your-domain.com/api/user';

  static Future<List<String>> fetchMenuImages({String lang = 'ar'}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/menu?lang=$lang'),
      headers: {
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final jsonBody = jsonDecode(response.body);
      if (jsonBody['status'] == true && jsonBody['data'] is List) {
        return List<String>.from(jsonBody['data']);
      }
    }
    return [];
  }
}

// طريقة الاستخدام داخل Widget (عرض صور المنيو في Carousel/PageView):
/*
final List<String> menuUrls = await MenuService.fetchMenuImages();

PageView.builder(
  itemCount: menuUrls.length,
  itemBuilder: (context, index) {
    return InteractiveViewer(
      child: Image.network(
        menuUrls[index],
        fit: BoxFit.contain,
        loadingBuilder: (context, child, progress) =>
            progress == null ? child : Center(child: CircularProgressIndicator()),
      ),
    );
  },
);
*/
```

---

### 5.2. مثال تطبيق ويب بـ React / Vue (Axios & Fetch)
```javascript
import axios from 'axios';

// جلب روابط صور المنيو
export async function getMenuImages(lang = 'ar') {
  try {
    const response = await axios.get('/api/user/menu', {
      params: { lang },
      headers: {
        'Accept': 'application/json',
      },
    });

    if (response.data.status) {
      return response.data.data; // Array of image URLs: ["http://...", "http://..."]
    }
    return [];
  } catch (error) {
    console.error('Error fetching menu images:', error);
    return [];
  }
}

// جلب إعدادات وبيانات المتجر كاملة بما فيها المنيو
export async function getBusinessSetup(lang = 'ar') {
  const response = await axios.get('/api/user/business-setup', {
    params: { lang },
  });
  return response.data.data;
}
```

---

### 5.3. استدعاء سريع عبر cURL
```bash
# جلب صور المنيو فقط
curl -X GET "http://127.0.0.1:8000/api/user/menu?lang=ar" \
  -H "Accept: application/json"

# جلب تفاصيل المتجر والمنيو
curl -X GET "http://127.0.0.1:8000/api/user/business-setup?lang=ar" \
  -H "Accept: application/json"
```

---

## 6. نصائح وتوجيهات للفرونت إند والموبايل (Frontend & Mobile Best Practices)

1. **الزووم والتكبير (Pinch-to-Zoom)**:
   - صفحات المنيو غالباً ما تحتوي على نصوص وأسعار تفصيلية، لذا يُنصح بوضع الصور داخل مكون يدعم الزووم مثل `InteractiveViewer` في Flutter أو مكتبات مثل `react-medium-image-zoom` في الويب.
2. **الكاش والتحميل المسبق (Caching & Preloading)**:
   - روابط الصور ثابتة وتتغير فقط عند تحديث الصورة، لذا يُفضل تفعيل الكاش لصور المنيو في تطبيقات الموبايل عبر مكتبات مثل `cached_network_image`.
3. **الحالة الفارغة (Empty State)**:
   - إذا عادت `data` كمصفوفة فارغة `[]`، اعرض للمستخدم رسالة لطيفة تفيد بأن قائمة الطعام الورقية غير متوفرة حالياً مع إمكانية التصفح المباشر للأقسام والمنتجات عبر واجهة `/api/user/products`.
