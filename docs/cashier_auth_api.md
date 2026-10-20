# توثيق واجهات برمجة التطبيقات لصلاحية الكاشير (Cashier Auth API Documentation)

هذا الدليل يوضح نقاط النهاية (Endpoints) الجديدة والمعدلة المتاحة لمستخدمي الكاشير (`auth:cashier`).

---

## 1. الحصول على قائمة مناديب التوصيل (Deliveries Select)

تُرجع هذه النقطة قائمة ببيانات مناديب التوصيل المتاحين لفرع الكاشير، وتحتوي فقط على المعرف (`id`) واسم المندوب (`name`) لاستخدامها في القوائم المنسدلة (`Select Dropdowns`).

- **المسار (Endpoint):** `GET /api/cashier/deliveries/select`
- **المسار البديل (Alias):** `GET /api/cashier/deliveries`
- **الصلاحية (Guard):** `auth:cashier`

### الترويسات (Headers)
| الترويسة (Header) | القيمة (Value) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `Authorization` | `Bearer <cashier_jwt_token>` | نعم | رمز الدخول الخاص بالكاشير |
| `Accept` | `application/json` | نعم | نوع الاستجابة المتوقعة |

### المعاملات (Query Params)
*لا يتطلب أية معاملات، حيث يتم الفلترة تلقائياً بناءً على فرع الكاشير المسجل حالياً ومندوبي التوصيل العامين.*

### جسم الطلب (Request Body)
*لا يوجد جسم للطلب (Empty Body).*

### الاستجابة الناجحة (Success Response: 200 OK)
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "كابتن أحمد محمد"
    },
    {
      "id": 2,
      "name": "كابتن محمود علي"
    }
  ]
}
```

---

## 2. تغيير حالة الطلب وتعيين مندوب التوصيل (Update Order Status)

تُتيح للكاشير تحديث حالة الطلب وفقاً للمسارات المعتمدة لكل نوع طلب. وعند تحويل حالة طلب توصيل (`delivery`) إلى "خارج للتوصيل" (`out_of_delivery`)، يلزم إرسال معرف مندوب التوصيل (`delivery_id`)، ويتم حفظه مباشرة في جدول الطلبات (`orders.delivery_id`).

- **المسار (Endpoint):** `PUT /api/cashier/orders/{order}/status`
- **طرق HTTP المقبولة:** `PUT`, `PATCH`, `POST`
- **الصلاحية (Guard):** `auth:cashier`

### الترويسات (Headers)
| الترويسة (Header) | القيمة (Value) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `Authorization` | `Bearer <cashier_jwt_token>` | نعم | رمز الدخول الخاص بالكاشير |
| `Content-Type` | `application/json` | نعم | تنسيق جسم الطلب |
| `Accept` | `application/json` | نعم | نوع الاستجابة المتوقعة |

### معاملات المسار (URL Route Params)
| المعامل (Param) | النوع (Type) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `order` | `integer` | نعم | الرقم التعريفي للطلب (`order_id`) |

### جسم الطلب (Request Body - JSON)
| الحقل (Field) | النوع (Type) | مطلوب؟ | القواعد والوصف |
| :--- | :--- | :--- | :--- |
| `status` | `string` | **نعم** | الحالة الجديدة للطلب. يجب أن تكون ضمن الحالات المسموح بها لنوع الطلب.<br>• لطلبات **التوصيل (delivery)**: `done`, `out_of_delivery`, `delivered`, `return`, `failed_to_delivered`<br>• لطلبات **التيك أواي (takeaway)**: `done`, `pickup`, `return`<br>• لطلبات **الصالة (dinein)**: `return` |
| `delivery_id` | `integer` | **مطلوب مشروط** | **إلزامي** إذا كان الطلب من نوع `delivery` والحالة المختارة هي `out_of_delivery`. يجب أن يكون معرف مندوب توصيل موجود في جدول `deliveries`. |

#### مثال للطلب (Request Body Example - Out of Delivery):
```json
{
  "status": "out_of_delivery",
  "delivery_id": 2
}
```

#### مثال للطلب (Request Body Example - حالة أخرى لا تتطلب مندوب):
```json
{
  "status": "delivered"
}
```

### الاستجابة الناجحة (Success Response: 200 OK)
```json
{
  "status": true,
  "message": "تم تحديث حالة الطلب بنجاح.",
  "data": {
    "id": 45,
    "status": "out_of_delivery",
    "delivery_id": 2
  }
}
```

### استجابة خطأ التحقق (Validation Error: 422 Unprocessable Content)
تحدث في حال لم يتم إرسال `delivery_id` عند اختيار `out_of_delivery` لطلب توصيل:
```json
{
  "message": "The delivery id field is required when status is out of delivery.",
  "errors": {
    "delivery_id": [
      "The delivery id field is required when status is out of delivery."
    ]
  }
}
```

---

## 3. إنشاء الطلب من السلة (Cashier Checkout)

تم دعم تمرير `delivery_id` اختيارياً أثناء إنشاء طلب التوصيل مباشرة من الكاشير.

- **المسار (Endpoint):** `POST /api/cashier/orders/checkout`
- **الصلاحية (Guard):** `auth:cashier`

### الترويسات (Headers)
| الترويسة (Header) | القيمة (Value) | مطلوب؟ |
| :--- | :--- | :--- |
| `Authorization` | `Bearer <cashier_jwt_token>` | نعم |
| `Content-Type` | `application/json` | نعم |
| `Accept` | `application/json` | نعم |

### جسم الطلب (Request Body - JSON)
| الحقل (Field) | النوع (Type) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `module` | `string` | **نعم** | نوع الطلب: `takeaway`, `dinein`, `delivery` |
| `financial_account_id` | `integer` | **نعم** | الحساب المالي (الخزينة) التي تم تحصيل/قيد الطلب عليها |
| `delivery_id` | `integer` | اختياري | الرقم التعريفي لمندوب التوصيل في حال تعيينه فوراً |
| `address` | `string` | مشروط | عنوان العميل (إلزامي لطلبات `delivery`) |
| `phone` | `string` | اختياري | رقم هاتف العميل |
| `name` | `string` | اختياري | اسم العميل |
| `note` | `string` | اختياري | أية ملاحظات إضافية |
| `hall_table_id` | `integer` | مشروط | رقم الطاولة لطلبات `dinein` |

#### ملاحظة احتساب حالة الدفع (`paid`):
- إذا كان الحساب المالي المختار نقدي (`is_cash = true`) وكان الطلب توصيل (`module = delivery`)، يتم تعيين حالة الدفع تلقائياً إلى **`paid: false`** (غير مدفوع حتى يتم تحصيله من العميل نقداً بواسطة المندوب).
- في خلاف ذلك تكون حالة الدفع **`paid: true`** (مدفوع بالكامل).

### الاستجابة الناجحة (Success Response: 201 Created)
```json
{
  "status": true,
  "message": "تم إنشاء الطلب بنجاح من السلة",
  "data": {
    "id": 50,
    "status": "pending",
    "module": "delivery",
    "delivery_id": 2,
    "delivery": {
      "id": 2,
      "name": "كابتن محمود علي"
    },
    "financial_account_id": 3,
    "paid": false,
    "total": 180,
    "final_price": 180,
    "created_at": "2026-10-20T21:50:00.000000Z"
  }
}
```

---

## 4. عرض طلبات التوصيل غير المدفوعة (List Unpaid Delivery Orders)

تُستخدم هذه النقطة لعرض جميع طلبات التوصيل التي لم يتم تحصيل قيمتها بعد (`module = 'delivery'` و `paid = false`). تدعم التصفح بالصفحات (`Pagination`) وفلترة الطلبات حسب مندوب توصيل محدد (`delivery_id`)، بالإضافة إلى البحث برقم الطلب أو اسم وهاتف العميل.

- **المسار (Endpoint):** `GET /api/cashier/delivery-orders/unpaid`
- **الصلاحية (Guard):** `auth:cashier` (مخصصة للكاشير فقط - Cashier Auth)

### الترويسات (Headers)
| الترويسة (Header) | القيمة (Value) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `Authorization` | `Bearer <cashier_jwt_token>` | **نعم** | رمز الدخول الخاص بالكاشير |
| `Accept` | `application/json` | **نعم** | نوع الاستجابة المتوقعة |

### المعاملات (Query Params)
| المعامل (Param) | النوع (Type) | مطلوب؟ | الافتراضي | الوصف |
| :--- | :--- | :--- | :--- | :--- |
| `delivery_id` | `integer` | اختياري | - | فلترة الطلبات المسندة إلى مندوب توصيل معين فقط |
| `page` | `integer` | اختياري | `1` | رقم الصفحة |
| `per_page` | `integer` | اختياري | `15` | عدد الطلبات في الصفحة الواحدة |
| `search` | `string` | اختياري | - | بحث برقم الطلب (مع أو بدون `#`) أو اسم العميل أو الهاتف |

### الاستجابة الناجحة (Success Response: 200 OK)
```json
{
  "status": true,
  "data": [
    {
      "id": 101,
      "order_number": "#101",
      "status": "out_of_delivery",
      "module": "delivery",
      "paid": false,
      "total": 150.00,
      "final_price": 150.00,
      "name": "أحمد إبراهيم",
      "phone": "01012345678",
      "address": "شارع النصر، عمارة 12، الدور الثاني",
      "delivery_id": 2,
      "delivery": {
        "id": 2,
        "name": "كابتن محمود علي"
      },
      "created_at": "2026-10-20T21:40:00.000000Z"
    }
  ],
  "links": {
    "first": "http://example.com/api/cashier/delivery-orders/unpaid?page=1",
    "last": "http://example.com/api/cashier/delivery-orders/unpaid?page=1",
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

## 5. تسوية وتحصيل حساب طلبات التوصيل (Settle Delivery Orders Payment)

تُستخدم لتسوية دفع مجموعة من طلبات التوصيل المسلمة وغير المدفوعة عند عودة المندوب وتحصيل الكاشير للمبالغ النقدية منه.
بمجرد استدعاء هذا المسار يتم تنفيذ التحديثات الآتية على كل طلب تم تحديده:
- `paid = true`
- `cashier_id = auth()->cashier_id` (معرف جهاز الكاشير الحالي)
- `cashier_man_id = auth()->id` (معرف مستخدم الكاشير الحالي)
- `shift_id = auth()->shift_id` (معرف وردية العمل النشطة حالياً)

- **المسار (Endpoint):** `POST /api/cashier/delivery-orders/settle-payment`
- **الصلاحية (Guard):** `auth:cashier` (مخصصة للكاشير فقط - Cashier Auth)

### الترويسات (Headers)
| الترويسة (Header) | القيمة (Value) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `Authorization` | `Bearer <cashier_jwt_token>` | **نعم** | رمز الدخول الخاص بالكاشير |
| `Content-Type` | `application/json` | **نعم** | نوع جسم الطلب |
| `Accept` | `application/json` | **نعم** | نوع الاستجابة المتوقعة |

### جسم الطلب (Request Body - JSON)
| الحقل (Field) | النوع (Type) | مطلوب؟ | الوصف |
| :--- | :--- | :--- | :--- |
| `order_ids` | `array of integers` | **نعم** | مصفوفة تحتوي على أرقام الطلبات المراد تسوية دفعها |

#### مثال الطلب (Request Body Example):
```json
{
  "order_ids": [101, 102, 105]
}
```

### الاستجابة الناجحة (Success Response: 200 OK)
```json
{
  "status": true,
  "message": "تم تحصيل وتسوية حساب طلبات التوصيل بنجاح",
  "data": {
    "settled_count": 3,
    "settled_order_ids": [101, 102, 105],
    "total_amount_collected": 450.00,
    "cashier_id": 1,
    "cashier_man_id": 4,
    "shift_id": 2
  }
}
```

### استجابة الأخطاء الشائعة:
- **422 Unprocessable Content:** عند عدم إرسال مصفوفة `order_ids` أو إرسال مصفوفة فارغة.
- **404 Not Found:** عند عدم العثور على أي طلب توصيل غير مدفوع يتبع فرع الكاشير من الأرقام المرسلة.

