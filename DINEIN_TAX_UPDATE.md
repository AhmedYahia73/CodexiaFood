# توثيق التعديل: فصل ضريبة الصالة في مفاتيح مستقلة
### (Dine-in Tax Separation Update)

هذا الملف مخصص للفرونت إند، ويوضح **فقط التعديل الأخير** الخاص بفصل ضريبة الصالة (`dinein_tax`) عن الضريبة الأساسية في السلة والطلبات.

---

## 📌 ملخص التعديل باختصار
- **سابقاً**: كانت ضريبة الصالة تُجمع وتُدمج داخل `tax_val` و `total_tax` و `grand_total_tax`.
- **الآن (بعد التعديل)**: 
  1. `tax_val` و `total_tax` و `grand_total_tax` أصبحت تعبر عن **الضريبة الأساسية فقط (Base Tax مثل 14% VAT)** دون أي دمج.
  2. تم إنشاء **مفاتيح مستقلة جديدة** لضريبة الصالة (`dinein_tax_val` و `total_dinein_tax` و `grand_total_dinein_tax`).

---

## 🗂️ المفاتيح الجديدة والمعدلة في الـ JSON

| المفتاح (Key) | مكانه في الـ JSON | التغيير الذي حدث | الوصف |
|---|---|---|---|
| `product.tax_val` | داخل كائن `product` | **معدل** | الضريبة الأساسية فقط للقطعة الواحدة (لا تشمل ضريبة الصالة). |
| `product.dinein_tax_val` | داخل كائن `product` | **مستقل** | قيمة ضريبة الصالة فقط للقطعة الواحدة. |
| `total_tax` | في عنصر السلة (Cart Item) | **معدل** | إجمالي الضريبة الأساسية للبند: $\text{product.tax\_val} \times \text{quantity}$. |
| **`total_dinein_tax`** | في عنصر السلة (Cart Item) | **✨ مفتاح جديد** | إجمالي ضريبة الصالة للبند: $\text{product.dinein\_tax\_val} \times \text{quantity}$. |
| `grand_total_tax` | داخل كائن `grand_totals` | **معدل** | مجموع الضرائب الأساسية فقط لكافة أصناف السلة. |
| **`grand_total_dinein_tax`** | داخل كائن `grand_totals` | **✨ مفتاح جديد** | مجموع ضريبة الصالة فقط لكافة أصناف السلة. |
| **`total_dinein_tax`** | في كائن الطلب (`Order`) | **✨ مفتاح جديد** | إجمالي ضريبة الصالة في الفاتورة النهائية بعد الـ Checkout. |

---

## 🔄 مقارنة الـ Response (قبل التعديل vs بعد التعديل)

### مثال: صنف سعره 100 ج، عليه خصم 10%، ضريبة أساسية 14%، وضريبة صالة 10%، والكمية 2:

### ❌ قبل التعديل (كانت مدموجة داخل tax):
```json
{
  "product": {
    "price": 100.0,
    "discount_val": 10.0,
    "tax_val": 21.6, // كانت مدموجة (12.6 أساسي + 9.0 صالة)
    "final_price": 111.6
  },
  "total_price": 200.0,
  "total_discount": 20.0,
  "total_tax": 43.2, // كانت مدموجة
  "total_final_price": 223.2,
  "grand_totals": {
    "grand_total_price": 200.0,
    "grand_total_discount": 20.0,
    "grand_total_tax": 43.2, // كانت مدموجة
    "grand_final_price": 223.2
  }
}
```

### ✅ بعد التعديل (مفصولة ومستقلة تماماً):
```json
{
  "product": {
    "price": 100.0,
    "discount_val": 10.0,
    "tax_val": 12.6,             // الضريبة الأساسية فقط (14% على 90 ج)
    "dinein_tax_val": 9.0,       // ضريبة الصالة مستقلة (10% على 90 ج)
    "final_price": 111.6
  },
  "total_price": 200.0,
  "total_discount": 20.0,
  "total_tax": 25.2,             // إجمالي الضريبة الأساسية فقط (12.6 * 2)
  "total_dinein_tax": 18.0,      // ✨ إجمالي ضريبة الصالة فقط (9.0 * 2)
  "total_final_price": 223.2,
  "grand_totals": {
    "grand_total_price": 200.0,
    "grand_total_discount": 20.0,
    "grand_total_tax": 25.2,         // مجموع الضريبة الأساسية فقط
    "grand_total_dinein_tax": 18.0,  // ✨ مجموع ضريبة الصالة فقط
    "grand_final_price": 223.2       // الإجمالي النهائي
  }
}
```

---

## 🧮 المعادلة المحاسبية المعتمدة للفرونت إند

$$\text{Final Price} = \text{Price} - \text{Discount} + \text{Base Tax} + \text{Dine-in Tax}$$

$$\text{200.00} - \text{20.00} + \text{25.20} + \text{18.00} = \text{223.20 EGP}$$

---

## 📡 الـ Endpoints التي تأثرت بهذا التعديل فقط

1. **عرض السلة**: `GET /api/cashier/cart`
2. **إضافة صنف للسلة**: `POST /api/cashier/cart`
3. **تطبيق الخصم على السلة**: `POST /api/cashier/cart/apply-discount`
4. **الدفع وإنشاء الطلب**: `POST /api/cashier/orders/checkout` (أضيف حقل `total_dinein_tax` في بيانات الطلب المنشأ).
