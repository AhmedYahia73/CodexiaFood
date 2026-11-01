# توثيق الـ API الخاص بسلة ونقاط بيع الكاشير (Cashier POS & Cart Documentation)
## الخصومات، التوريث التلقائي للخصم، وضريبة الصالة (Cart Discounts, Auto-Inheritance & Dine-in Tax)

هذا التوثيق موجه لمطوري الواجهة الأمامية وتطبيقات الكاشير والـ POS (Frontend & Mobile Developers).

---

## 📌 الميزات الجديدة والمعدلة في هذا التحديث (What's New)

1. **جلب قائمة الخصومات للاختيار (Discounts Selection API)**:
   - نقطة نهاية جديدة تتيح للكاشير جلب جميع الخصومات النشطة (`status = true`) لعرضها في قائمة منسدلة أو نافذة منبثقة (Modal).
2. **تطبيق أو إزالة الخصم على السلة (Apply / Remove Cart Discount)**:
   - إمكانية تحديد `discount_id` وتطبيقه على سلة الكاشير الحالية بناءً على نوع الطلب (`module`) ومعرف الطاولة (`table_id` للصالة) أو جهاز الكاشير (`cashier_id` للسفري/الدليفري).
   - إمكانية إرسال `discount_id: null` لإلغاء وتفريغ الخصم من السلة.
3. **التوريث التلقائي للخصم عند إضافة أصناف جديدة (Automatic Discount Inheritance)**:
   - بمجرد أن يختار الكاشير خصماً للسلة، أي صنف جديد تتم إضافته بعد ذلك (`POST /api/cashier/cart`) **يرث هذا الخصم تلقائياً وبشكل فوري** دون الحاجة لإعادة تحديد الخصم لكل صنف.
4. **تطبيق ضريبة الصالة كنسبة مئوية (`dinein_tax`)**:
   - عند اختيار نوع الطلب محلي / صالة (`module = dinein`)، يتم احتساب نسبة ضريبة الصالة تلقائياً على المنتجات بعد الخصم وإرجاع قيمتها في حقل `'dinein_tax_val'` مع إضافتها لإجمالي الضرائب والسعر النهائي.

---

## 🔐 معلومات المصادقة والترويسات المشتركة (Headers)
يجب إرسال الترويسات التالية مع كافة طلبات الكاشير:

```http
Authorization: Bearer <CASHIER_MAN_JWT_TOKEN>
Accept: application/json
Content-Type: application/json
```

---

## 1️⃣ جلب قائمة الخصومات المتاحة للاختيار (Get Active Discounts)

استرجاع جميع الخصومات النشطة الصالحة للاستخدام في نقاط البيع لتعبئة القائمة المنسدلة (Dropdown/Modal) لاختيار الخصم.

- **Method**: `GET`
- **URL**: `/api/cashier/cart/discounts`  *(أو المسار المختصر: `/api/cashier/discounts`)*
- **Headers**:
  ```http
  Authorization: Bearer <TOKEN>
  Accept: application/json
  ```
- **Query Parameters**:
  | المعامل (Param) | النوع | إجباري/اختياري | الوصف |
  |---|---|---|---|
  | `lang` | string | اختياري | لغة اسم الخصم (`ar` أو `en`). الافتراضي: `ar`. |

### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "خصم موظفين 10%",
      "raw_name": {
        "ar": "خصم موظفين 10%",
        "en": "Staff Discount 10%"
      },
      "type": "percentage",
      "amount": 10.0
    },
    {
      "id": 2,
      "name": "خصم VIP 20%",
      "raw_name": {
        "ar": "خصم VIP 20%",
        "en": "VIP 20%"
      },
      "type": "percentage",
      "amount": 20.0
    },
    {
      "id": 3,
      "name": "خصم مباشر 50 جنيه",
      "raw_name": {
        "ar": "خصم مباشر 50 جنيه",
        "en": "Flat 50 EGP Discount"
      },
      "type": "value",
      "amount": 50.0
    }
  ]
}
```

#### 📋 شرح حقول الخصم:
- `id`: المعرف الرقمي للخصم (هو الذي يتم إرساله كـ `discount_id`).
- `name`: اسم الخصم المترجم حسب لغة الطلب.
- `type`: نوع الخصم:
  - `"percentage"`: نسبة مئوية (مثل 10% أو 20%).
  - `"value"`: قيمة نقدية ثابتة تخصم من سعر الصنف (مثل 50 جنيه).
- `amount`: قيمة أو نسبة الخصم كـ `float`.

---

## 2️⃣ تطبيق أو إلغاء الخصم على السلة (Apply / Clear Cart Discount)

تطبيق الخصم المختار على كافة العناصر الموجودة حالياً في السلة المحددة، وحفظه كنطاق نشط لأي عناصر ستضاف لاحقاً.

- **Method**: `POST`
- **URL**: `/api/cashier/cart/apply-discount`
- **Headers**:
  ```http
  Authorization: Bearer <TOKEN>
  Accept: application/json
  Content-Type: application/json
  ```

### 📥 معاملات جسم الطلب (Request Body Parameters):

| الحقل (Field) | النوع | إجباري/اختياري | الوصف | مثال |
|---|---|---|---|---|
| `module` | string | **إجباري** | نوع الطلب: `takeaway` أو `dinein` أو `delivery` | `"dinein"` |
| `table_id` | integer | **إجباري إذا كان `module = dinein`** | معرف الطاولة (أو استخدم `hall_table_id`) | `1` |
| `hall_table_id` | integer | اختياري | بديل لـ `table_id` لطاولات الصالة | `1` |
| **`discount_id`** | **integer أو null** | **اختياري (Nullable)** | **معرف الخصم المراد تطبيقه. إذا أردت إلغاء الخصم أرسل `null`** | **`1` أو `null`** |
| `lang` | string | اختياري | لغة أسماء المنتجات والخيارات (`ar` أو `en`) | `"ar"` |

### 🟢 نموذج طلب لتطبيق خصم 10% على طاولة رقم 1 (Apply Discount):
```json
{
  "module": "dinein",
  "table_id": 1,
  "discount_id": 1
}
```

### 🟢 نموذج استجابة نجاح تطبيق الخصم (Response - 200 OK):
```json
{
  "status": true,
  "message": "تم تطبيق الخصم على السلة بنجاح",
  "applied_discount_id": 1,
  "data": [
    {
      "id": 15,
      "module": "dinein",
      "hall_table_id": 1,
      "table_id": 1,
      "quantity": 1,
      "notes": null,
      "discount_id": 1,
      "discount": {
        "id": 1,
        "name": "خصم موظفين 10%",
        "type": "percentage",
        "amount": 10.0
      },
      "product": {
        "id": 5,
        "name": "برجر دجاج سوبر",
        "price": 100.0,
        "discount_val": 10.0,
        "tax_val": 21.6,
        "dinein_tax_val": 9.0,
        "final_price": 111.6
      },
      "variations": [],
      "addons": [],
      "total_price": 100.0,
      "total_discount": 10.0,
      "total_tax": 21.6,
      "total_final_price": 111.6
    }
  ],
  "grand_totals": {
    "grand_total_price": 100.0,
    "grand_total_discount": 10.0,
    "grand_total_tax": 21.6,
    "grand_final_price": 111.6
  }
}
```

### 🟢 نموذج طلب لإلغاء وحذف الخصم من السلة (Clear Discount):
```json
{
  "module": "dinein",
  "table_id": 1,
  "discount_id": null
}
```
*يعيد السلة فوراً بالأسعار الطبيعية بدون أي خصم (`applied_discount_id: null`).*

---

## 3️⃣ إضافة منتج للسلة والتوريث التلقائي للخصم (Add Item to Cart)

عند إضافة أي صنف جديد للسلة، يتعرف النظام تلقائياً على الخصم المطبق سابقاً على هذه السلة ويرثه مباشرة.

- **Method**: `POST`
- **URL**: `/api/cashier/cart`
- **Headers**:
  ```http
  Authorization: Bearer <TOKEN>
  Accept: application/json
  Content-Type: application/json
  ```

### 📥 معاملات جسم الطلب (Request Body Parameters):
```json
{
  "module": "dinein",
  "table_id": 1,
  "product_id": 7,
  "quantity": 2,
  "notes": "بدون بصل",
  "variations": [
    {
      "variation_id": 3,
      "option_ids": [10]
    }
  ],
  "addons": [
    {
      "addon_id": 2
    }
  ],
  "discount_id": null
}
```
> 💡 **ميزة التوريث التلقائي (Automatic Inheritance)**:
> - ليس شرطاً إرسال `discount_id` في هذا الطلب!
> - إذا كانت طاولة 1 مطبق عليها خصم (مثلاً 10%)، فالمنتج رقم 7 سيكتسب خصم الـ 10% تلقائياً، وتأتي الاستجابة `201 Created` متضمنة الخصم والحسابات المحاسبية الدقيقة له مباشرة.
> - إذا أردت استثناء صنف معين من الخصم، يمكنك تمرير `discount_id: null` صراحةً.

---

## 4️⃣ استعراض عناصر السلة والإجماليات (Get Cart & Grand Totals)

- **Method**: `GET`
- **URL**: `/api/cashier/cart`
- **Query Parameters**:
  - `module`: `takeaway` أو `dinein` أو `delivery` (إجباري).
  - `table_id`: معرف الطاولة (إجباري في حالة الصالة `dinein`).
  - `lang`: `ar` أو `en` (اختياري).

### 🟢 هيكل الاستجابة وتفاصيل كل حقل محاسبي (Response Breakdown):
```json
{
  "status": true,
  "data": [
    {
      "id": 18,
      "module": "dinein",
      "hall_table_id": 1,
      "table_id": 1,
      "quantity": 2,
      "notes": "وسط",
      "is_gift": false,
      "discount_id": 1,
      "discount": {
        "id": 1,
        "name": "خصم موظفين 10%",
        "type": "percentage",
        "amount": 10.0
      },
      "product": {
        "id": 4,
        "name": "بيتزا دجاج باربيكيو",
        "price": 150.0,
        "discount_val": 15.0,
        "tax_val": 32.4,
        "dinein_tax_val": 13.5,
        "final_price": 167.4
      },
      "variations": [],
      "addons": [],
      "total_price": 300.0,
      "total_discount": 30.0,
      "total_tax": 64.8,
      "total_final_price": 334.8
    }
  ],
  "grand_totals": {
    "grand_total_price": 300.0,
    "grand_total_discount": 30.0,
    "grand_total_tax": 64.8,
    "grand_final_price": 334.8
  }
}
```

---

## 🧮 الدليل المحاسبي بالأرقام لمطور الفرونت إند (The Accounting Formula)

لتوضيح الحسابات بدقة تامة أمام المحاسبين والعملاء:

### سيناريو تجريبي واقعي:
- صنف سعره الأصلي: **100.00 جنيه**.
- الكاشير اختار خصم سلة: **10%**.
- الضريبة العامة للسلعة في المنيو (VAT): **14%**.
- ضريبة الصالة المضبوطة في إعدادات الإدارة (`dinein_tax`): **10%**.

### خطوات الاحتساب البرمجية:
1. **قيمة الخصم (`discount_val`)**:
   $$\text{Discount} = 100.00 \times 10\% = 10.00\text{ جنيه}$$
2. **السعر بعد الخصم (`Price After Discount`)**:
   $$\text{Price After Discount} = 100.00 - 10.00 = 90.00\text{ جنيه}$$
3. **ضريبة القيمة المضافة (14% VAT على السعر بعد الخصم)**:
   $$\text{Base Tax} = 90.00 \times 14\% = 12.60\text{ جنيه}$$
4. **ضريبة الصالة (10% Dine-in Tax على السعر بعد الخصم)**:
   $$\text{Dinein Tax} = 90.00 \times 10\% = 9.00\text{ جنيه}$$
5. **إجمالي الضريبة للصنف (`tax_val`)**:
   $$\text{Total Tax} = 12.60 + 9.00 = 21.60\text{ جنيه}$$
6. **السعر النهائي للصنف (`final_price`)**:
   $$\text{Final Price} = 100.00 - 10.00 + 21.60 = 111.60\text{ جنيه}$$
7. **إجمالي عنصر السلة لكمية 2 (`quantity = 2`)**:
   - `total_price`: $100.00 \times 2 = 200.00\text{ جنيه}$
   - `total_discount`: $10.00 \times 2 = 20.00\text{ جنيه}$
   - `total_tax`: $21.60 \times 2 = 43.20\text{ جنيه}$
   - `total_final_price`: $111.60 \times 2 = 223.20\text{ جنيه}$

#### التحقق الرياضي الدقيق:
$$\text{Total (200.00)} - \text{Discount (20.00)} + \text{Tax (43.20)} = 223.20\text{ جنيه} \equiv \text{Final Price}$$

---

## 🖥️ إرشادات تصميم واجهة المستخدم لشاشة الكاشير (Frontend UI Recommendations)

1. **زر اختيار الخصم (Discount Action Button)**:
   - ضع زراً في شاشة الـ POS باسم **"تطبيق خصم"** أو أيقونة نسبة `%`.
   - عند الضغط عليه، يتم استدعاء `GET /api/cashier/cart/discounts` وفتح Modal يعرض الخصومات المتاحة.
   - عند اختيار خصم، يتم إرسال `POST /api/cashier/cart/apply-discount` بالـ `discount_id`.
2. **شريط حالة الخصم المطبق (Applied Discount Badge)**:
   - في ملخص السلة، إذا كانت السلة تحتوي على خصم مطبق، اعرض شارة (Badge) بلون مميز مثل:
     `[ خصم موظفين 10%  (✕) ]`
   - عند ضغط زر `(✕)`، يتم إرسال `POST /api/cashier/cart/apply-discount` مع `discount_id: null` لإلغاء الخصم فوراً.
3. **تفصيل الفاتورة وشاشة الدفع (Checkout & Bill Breakdown)**:
   اعرض الملخص بالشكل المالي القياسي:
   - **المجموع الفرعي (Subtotal)**: يعرض `grand_totals.grand_total_price`.
   - **الخصم (Discount)**: يعرض `- grand_totals.grand_total_discount` بلون أخضر أو أحمر.
   - **الضريبة (Taxes & Service)**: يعرض `grand_totals.grand_total_tax`.
   - **الإجمالي النهائي المطلوب (Net Total)**: يعرض `grand_totals.grand_final_price` بخط عريض وواضح.
