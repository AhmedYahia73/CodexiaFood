# توثيق الـ API المحدث لسلة ونقاط بيع الكاشير (Cashier POS & Cart Documentation)
## التحديث المحاسبي: فصل ضريبة الصالة في مفاتيح مستقلة وإدارة الخصومات

هذا التوثيق موجه لمطوري الواجهة الأمامية وتطبيقات الكاشير والـ POS (Frontend & Mobile App Developers).

---

## 📌 ملخص التعديل المحاسبي الجديد (Accounting Update)

تم **فصل ضريبة الصالة (`dinein_tax`) بشكل كامل ومستقل عن الضريبة الأساسية للمنتج (`tax_val`)** في استجابات السلة والطلبات وفي قاعدة البيانات، بحيث:
1. **الضريبة الأساسية للمنتج (`tax_val` / `total_tax` / `grand_total_tax`)**: تمثل ضريبة القيمة المضافة (VAT) أو الضريبة المعرفة على الصنف في المنيو فقط، ولا يُدمج معها أي ضريبة أخرى.
2. **ضريبة الصالة (`dinein_tax_val` / `total_dinein_tax` / `grand_total_dinein_tax`)**: تُحسب كبند مستقل تماماً كنسبة من السعر بعد الخصم عند اختيار الطلب محلي / صالة (`module = dinein`).
3. **تسجيل النسبة في السلة (`order_carts.dinein_tax`)**: يتم حفظ نسبة ضريبة الصالة المطبقة على الصنف داخل قاعدة البيانات في جدول `order_carts`.
4. **تسجيل إجمالي ضريبة الصالة في الفاتورة النهائية (`orders.total_dinein_tax`)**: يتم تخزين ضريبة الصالة في عمود مستقل في جدول الطلبات `orders` ومورد الطلب `OrderResource`.
5. **إدارة الخصومات والتوريث التلقائي**: إمكانية تطبيق/إلغاء خصم على السلة بالكامل، وتوريث هذا الخصم تلقائياً لأي صنف يضاف لاحقاً.

---

## 🔐 معلومات المصادقة والترويسات المشتركة (Headers)
يجب إرسال الترويسات التالية مع كافة الطلبات:

```http
Authorization: Bearer <CASHIER_MAN_JWT_TOKEN>
Accept: application/json
Content-Type: application/json
```

---

## 1️⃣ استعراض الخصومات المتاحة للاختيار (Get Active Discounts)

جلب الخصومات النشطة فقط (`status = true`) لملء قائمة الاختيار (Dropdown/Modal) في شاشة الكاشير.

- **Method**: `GET`
- **URL**: `/api/cashier/cart/discounts` *(أو المسار المباشر: `/api/cashier/discounts`)*
- **Headers**:
  ```http
  Authorization: Bearer <TOKEN>
  Accept: application/json
  ```
- **Query Parameters**:
  | المعامل (Param) | النوع | إجباري/اختياري | الوصف |
  |---|---|---|---|
  | `lang` | string | اختياري | لغة اسم الخصم (`ar` أو `en`). القيمة الافتراضية: `ar`. |

### 🟢 استجابة ناجحة (Response - 200 OK):
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
      "name": "خصم ترويجي 50 جنيه",
      "raw_name": {
        "ar": "خصم ترويجي 50 جنيه",
        "en": "Promo 50 EGP"
      },
      "type": "value",
      "amount": 50.0
    }
  ]
}
```

---

## 2️⃣ تطبيق أو إلغاء الخصم على السلة (Apply / Remove Cart Discount)

تطبيق الخصم المختار على جميع عناصر السلة الحالية المحددة بالـ `module` و (`table_id` للصالة، أو `cashier_id` للسفري/الدليفري)، أو إرسال `null` لإلغاء الخصم.

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
| `discount_id` | integer أو null | **اختياري (Nullable)** | معرف الخصم المراد تطبيقه، أو `null` لإلغاء الخصم من السلة | `1` أو `null` |
| `lang` | string | اختياري | لغة النصوص (`ar` أو `en`) | `"ar"` |

### 🟢 نموذج طلب لتطبيق خصم (Request Body Example):
```json
{
  "module": "dinein",
  "table_id": 1,
  "discount_id": 1
}
```

### 🟢 نموذج طلب لإلغاء الخصم (Remove Discount Example):
```json
{
  "module": "dinein",
  "table_id": 1,
  "discount_id": null
}
```

---

## 3️⃣ إضافة منتج للسلة مع التوريث التلقائي وضريبة الصالة (Add Item to Cart)

عند إضافة صنف جديد للسلة، يتعرف النظام تلقائياً على الخصم النشط في هذه السلة ويرثه، كما يطبق نسبة ضريبة الصالة المسجلة في إعدادات النظام تلقائياً إذا كان `module = dinein`.

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
  "product_id": 5,
  "quantity": 2,
  "notes": "حار إضافي",
  "variations": [
    {
      "variation_id": 1,
      "option_ids": [3]
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
> 💡 **ملاحظة**: حقل `discount_id` اختياري. إذا كانت السلة مطبق عليها خصم مسبقاً، فسيرث الصنف الجديد الخصم تلقائياً حتى لو لم تقم بإرسال `discount_id`.

---

## 4️⃣ استعراض عناصر السلة والإجماليات المفصلة (Get Cart & Grand Totals)

هنا يظهر التعديل المحاسبي بوضوح تام، حيث تم فصل كل مفتاح ضريبي بشكل مستقل:

- **Method**: `GET`
- **URL**: `/api/cashier/cart`
- **Query Parameters**:
  - `module`: `takeaway` أو `dinein` أو `delivery` (إجباري).
  - `table_id`: معرف الطاولة (إجباري في حالة `dinein`).
  - `lang`: `ar` أو `en` (اختياري).

### 🟢 نموذج استجابة سلة الصالة (Dine-in Cart Response - 200 OK):
```json
{
  "status": true,
  "data": [
    {
      "id": 10,
      "module": "dinein",
      "hall_table_id": 1,
      "table_id": 1,
      "captain_order_id": null,
      "status": null,
      "quantity": 2,
      "notes": "حار إضافي",
      "is_gift": false,
      "parent_id": null,
      "cashier_id": 1,
      "cashier_man_id": 2,
      "branch_id": 1,
      "discount_id": 1,
      "dinein_tax": 10.0,
      "discount": {
        "id": 1,
        "name": "خصم موظفين 10%",
        "type": "percentage",
        "amount": 10.0
      },
      "product": {
        "id": 5,
        "name": "برجر دجاج سوبر",
        "description": "ساندوتش برجر مشوي مع الجبن",
        "image": "products/burger.jpg",
        "price": 100.0,
        "discount_val": 10.0,
        "tax_val": 12.6,
        "dinein_tax_val": 9.0,
        "final_price": 111.6
      },
      "variations": [],
      "addons": [],
      "total_price": 200.0,
      "total_discount": 20.0,
      "total_tax": 25.2,
      "total_dinein_tax": 18.0,
      "total_final_price": 223.2
    }
  ],
  "grand_totals": {
    "grand_total_price": 200.0,
    "grand_total_discount": 20.0,
    "grand_total_tax": 25.2,
    "grand_total_dinein_tax": 18.0,
    "grand_final_price": 223.2
  }
}
```

---

## 🗂️ جدول مقارنة المفاتيح الضريبية (Tax Keys Dictionary)

| المفتاح (Key) | المستوى | الوصف | مثال |
|---|---|---|---|
| `dinein_tax` | عنصر السلة | النسبة المئوية المطبقة لضريبة الصالة المسجلة في قاعدة البيانات | `10.0` (أي 10%) |
| `product.tax_val` | الصنف | قيمة الضريبة الأساسية فقط للقطعة الواحدة (مثل 14% VAT) **دون جمع ضريبة الصالة** | `12.60` |
| `product.dinein_tax_val` | الصنف | **مفتاح مستقل**: قيمة ضريبة الصالة فقط للقطعة الواحدة | `9.00` |
| `product.final_price` | الصنف | السعر النهائي الشامل للقطعة: $\text{Price} - \text{Discount} + \text{Tax} + \text{DineinTax}$ | `111.60` |
| `total_tax` | بند السلة | إجمالي الضريبة الأساسية للصنف: $\text{product.tax\_val} \times \text{quantity}$ | `25.20` |
| **`total_dinein_tax`** | بند السلة | **مفتاح مستقل جديد**: إجمالي ضريبة الصالة للصنف: $\text{product.dinein\_tax\_val} \times \text{quantity}$ | `18.00` |
| `total_final_price` | بند السلة | الإجمالي النهائي المطلوب للبند بالكامل | `223.20` |
| `grand_total_tax` | الإجماليات العامة | مجموع الضرائب الأساسية فقط لكافة أصناف السلة | `25.20` |
| **`grand_total_dinein_tax`** | الإجماليات العامة | **مفتاح مستقل جديد**: مجموع ضريبة الصالة لكافة أصناف السلة | `18.00` |
| `grand_final_price` | الإجماليات العامة | الإجمالي النهائي الكلي المطلوب دفعه للفاتورة | `223.20` |

---

## 5️⃣ إنشاء الطلب والفاتورة النهائية (Checkout / Order Placement)

عند إتمام الطلب من قِبل الكاشير (`POST /api/cashier/orders/checkout`)، يتم حفظ ضريبة الصالة في عمود مستقل في جدول `orders` أيضاً:

- **Method**: `POST`
- **URL**: `/api/cashier/orders/checkout`
- **Response**: يحتوي كائن الطلب في الـ Resource على المفاتيح التالية:
  ```json
  {
    "status": true,
    "data": {
      "id": 85,
      "code": "ORD-20261103-001",
      "module": "dinein",
      "total": 200.0,
      "total_discount": 20.0,
      "total_tax": 25.2,
      "total_dinein_tax": 18.0,
      "final_price": 223.2
    }
  }
  ```

---

## 🧮 الدليل المحاسبي بالأرقام لمطور الفرونت إند (The Mathematical Proof)

### سيناريو تجريبي واقعي:
- **سعر الصنف الأصلي**: `100.00` جنيه.
- **الكمية**: `2`.
- **خصم السلة المطبق**: `10%`.
- **الضريبة الأساسية للسلعة (14% VAT)**: معرفة على الصنف في المنيو.
- **ضريبة الصالة (`dinein_tax`)**: `10%`.

### خطوات الحساب البرمجية:
1. **الخصم للقطعة**:
   $$\text{DiscountVal} = 100.00 \times 10\% = 10.00\text{ جنيه}$$
2. **السعر بعد الخصم**:
   $$\text{PriceAfterDiscount} = 100.00 - 10.00 = 90.00\text{ جنيه}$$
3. **الضريبة الأساسية للقطعة (`tax_val`)**:
   $$\text{tax\_val} = 90.00 \times 14\% = 12.60\text{ جنيه}$$
4. **ضريبة الصالة للقطعة (`dinein_tax_val`)** *(مفتاح مستقل)*:
   $$\text{dinein\_tax\_val} = 90.00 \times 10\% = 9.00\text{ جنيه}$$
5. **السعر النهائي للقطعة (`final_price`)**:
   $$\text{final\_price} = 100.00 - 10.00 + 12.60 + 9.00 = 111.60\text{ جنيه}$$
6. **إجمالي البند لكمية 2 (`quantity = 2`)**:
   - `total_price` = $100.00 \times 2 = 200.00$
   - `total_discount` = $10.00 \times 2 = 20.00$
   - `total_tax` = $12.60 \times 2 = 25.20$ *(الضريبة الأساسية فقط)*
   - `total_dinein_tax` = $9.00 \times 2 = 18.00$ *(ضريبة الصالة المستقلة)*
   - `total_final_price` = $111.60 \times 2 = 223.20$

### المعادلة المحاسبية المطابقة 100%:
$$\text{total\_price (200.00)} - \text{total\_discount (20.00)} + \text{total\_tax (25.20)} + \text{total\_dinein\_tax (18.00)} = 223.20 \equiv \text{total\_final\_price}$$

---

## 🖥️ نصائح عرض الشاشة والإيصال لمطور الفرونت إند (UI Display Guide)

في شاشة الـ POS وفي قالب طباعة الإيصال (Receipt Template)، اعرض ملخص الحساب بالصورة القياسية التالية:

```text
----------------------------------------
المجموع الفرعي (Subtotal):      200.00 EGP
الخصم (Discount - 10%):        - 20.00 EGP
ضريبة القيمة المضافة (VAT 14%): + 25.20 EGP
ضريبة الصالة (Dine-in 10%):     + 18.00 EGP
----------------------------------------
الإجمالي الكلي (Net Total):     223.20 EGP
----------------------------------------
```
- إذا كان نوع الطلب سفري (`takeaway`) أو دليفري (`delivery`)، ستكون قيمة `total_dinein_tax` و `grand_total_dinein_tax` تساوي `0.00` تلقائياً، وبالتالي يمكنك إخفاء سطر ضريبة الصالة في واجهة المستخدم لغير طلبات الصالة.
