# توثيق واجهات إدارة الأقساط والديون الآجلة (المشتريات والمبيعات)
## Admin Purchase & Sale Dues (Installments) API Documentation

دليل تقني تفصيلي لمطوري لوحة التحكم (Admin Dashboard) والفرونت إند للتعامل مع أقساط المشتريات والمبيعات الآجلة (`purchase_dues` و `sale_dues`)، فلترة الأقساط (المتأخرة، غير المدفوعة، المدفوعة)، وإجراء عمليات سداد الأقساط مع التحديث التلقائي لأرصدة الموردين والعملاء.

---

## 📌 المحتويات (Table of Contents)

1. [نظرة عامة وقواعد العمل (Overview & Business Logic)](#1-نظرة-عامة-وقواعد-العمل-overview--business-logic)
2. [الهيدرز والتوثيق (Headers & Authentication)](#2-الهيدرز-والتوثيق-headers--authentication)
3. [واجهات أقساط المشتريات (Purchase Dues APIs)](#3-واجهات-أقساط-المشتريات-purchase-dues-apis)
   - 3.1. [الأقساط المتأخرة (Overdue Purchase Dues)](#31-الأقساط-المتأخرة-get-apiadminpurchase-duesoverdue)
   - 3.2. [الأقساط غير المدفوعة بالكامل (Unpaid Purchase Dues)](#32-الأقساط-غير-المدفوعة-بالكامل-get-apiadminpurchase-duesunpaid)
   - 3.3. [الأقساط المسددة بالكامل (Paid Purchase Dues)](#33-الأقساط-المسددة-بالكامل-get-apiadminpurchase-duespaid)
   - 3.4. [قائمة الأقساط مع الفلاتر المتقدمة (List All with Filters)](#34-قائمة-الأقساط-مع-الفلاتر-get-apiadminpurchase-dues)
   - 3.5. [عرض تفاصيل قسط مشتريات (Show Purchase Due)](#35-عرض-تفاصيل-قسط-get-apiadminpurchase-duesid)
   - 3.6. [سداد قسط مشتريات (Pay Purchase Due)](#36-سداد-قسط-مشتريات-post-apiadminpurchase-duesidpay)
4. [واجهات أقساط المبيعات (Sale Dues APIs)](#4-واجهات-أقساط-المبيعات-sale-dues-apis)
   - 4.1. [الأقساط المتأخرة (Overdue Sale Dues)](#41-الأقساط-المتأخرة-get-apiadminsale-duesoverdue)
   - 4.2. [الأقساط غير المدفوعة بالكامل (Unpaid Sale Dues)](#42-الأقساط-غير-المدفوعة-بالكامل-get-apiadminsale-duesunpaid)
   - 4.3. [الأقساط المسددة بالكامل (Paid Sale Dues)](#43-الأقساط-المسددة-بالكامل-get-apiadminsale-duespaid)
   - 4.4. [قائمة الأقساط مع الفلاتر المتقدمة (List All with Filters)](#44-قائمة-الأقساط-مع-الفلاتر-get-apiadminsale-dues)
   - 4.5. [عرض تفاصيل قسط مبيعات (Show Sale Due)](#45-عرض-تفاصيل-قسط-get-apiadminsale-duesid)
   - 4.6. [سداد قسط مبيعات (Pay Sale Due)](#46-سداد-قسط-مبيعات-post-apiadminsale-duesidpay)
5. [أكواد الاستجابة وحالات الخطأ (Error Responses)](#5-أكواد-الاستجابة-وحالات-الخطأ-error-responses)
6. [أمثلة كود الربط (Frontend Integration Examples)](#6-أمثلة-كود-الربط-frontend-integration-examples)

---

## 1. نظرة عامة وقواعد العمل (Overview & Business Logic)

### 1.1. الحقول الرئيسية في جداول الأقساط
تم إضافة حقل `payment` (المدفوع حتى الآن من قيمة هذا القسط) في كل من `purchase_dues` و `sale_dues` بقيمة افتراضية `0.00`.

* `amount`: إجمالي قيمة القسط المستحق.
* `payment`: المبلغ الذي تم سداده من هذا القسط.
* `remaining`: المبلغ المتبقي على هذا القسط (`amount - payment`).
* `date`: تاريخ استحقاق القسط.
* `status`: حالة القسط المحسوبة تلقائياً:
  - `paid`: إذا كان `payment >= amount` (تم سداده بالكامل).
  - `overdue`: إذا كان `payment < amount` وتاريخ القسط جاء في الماضي (`date < today()`).
  - `unpaid`: إذا كان `payment < amount` (سواء كان تاريخه في المستقبل أو لم يُسدد بعد).

### 1.2. التأثير المالي عند سداد قسط مشتريات (`purchase_dues/{id}/pay`):
عند تسجيل دفعة لقسط مشتريات بقيمة `X`:
1. يزيد `payment` في سجل القسط بمقدار `X`.
2. في فاتورة الشراء (`purchases`):
   - يزيد `paid_amount` بمقدار `X`.
   - يقل `remaining_amount` بمقدار `X`.
3. في حساب المورد (`suppliers`):
   - يقل رصيد المورد (`balance`) بمقدار `X` (لأن رصيد المورد يعبر عن مديونية المتجر للمورد، فالسداد يُنقص هذا الدين).

### 1.3. التأثير المالي عند سداد قسط مبيعات (`sale_dues/{id}/pay`):
عند تسجيل سداد لقسط بيع آجل لعميل بقيمة `X`:
1. يزيد `payment` في سجل القسط بمقدار `X`.
2. في فاتورة البيع (`sales`):
   - يزيد `paid_amount` بمقدار `X`.
   - يقل `remaining_amount` بمقدار `X`.
3. في حساب العميل / المشتري (`salers`):
   - يقل رصيد العميل (`balance`) بمقدار `X` (لأن رصيد العميل يعبر عن المبلغ المستحق عليه، فالسداد يخفض مديونيته).

---

## 2. الهيدرز والتوثيق (Headers & Authentication)

تتطلب جميع الواجهات توثيق الأدمن عبر الـ Header:

| الهيدر (Header) | النوع | إلزامي؟ | القيمة |
|---|---|---|---|
| `Authorization` | String | **نعم** | `Bearer {ADMIN_JWT_TOKEN}` |
| `Accept` | String | **نعم** | `application/json` |
| `Content-Type` | String | نعم (مع طلبات POST) | `application/json` |

---

## 3. واجهات أقساط المشتريات (Purchase Dues APIs)

### 3.1. الأقساط المتأخرة (`GET /api/admin/purchase-dues/overdue`)
تعرض جميع أقساط المشتريات التي حل أجلها ولم تُسدد بالكامل (`payment < amount` و `date < today()`).

* **الرابط (URL)**: `/api/admin/purchase-dues/overdue`
* **النوع (Method)**: `GET`
* **معلمات الاستعلام (Query Parameters)**:
  - `purchase_id` (integer, اختياري): فلترة برقم فاتورة الشراء.
  - `supplier_id` (integer, اختياري): فلترة برقم المورد.
  - `branch_id` (integer, اختياري): فلترة بالفرع.
  - `page` (integer, اختياري): رقم الصفحة (افتراضي: 1).
  - `per_page` (integer, اختياري): عدد العناصر بالصفحة (افتراضي: 15).

#### نموذج استجابة النجاح (200 OK):
```json
{
  "data": [
    {
      "id": 12,
      "purchase_id": 4,
      "amount": 1000.0,
      "payment": 200.0,
      "remaining": 800.0,
      "status": "overdue",
      "date": "2026-10-15",
      "notes": "دفعة شراء مستلزمات",
      "supplier": {
        "id": 2,
        "name": "شركة الدلتا للتوريدات",
        "phone": "01011112222",
        "balance": 5400.0
      },
      "purchase": {
        "id": 4,
        "supplier_id": 2,
        "branch_id": 1,
        "total_cost": 3000.0,
        "paid_amount": 1200.0,
        "remaining_amount": 1800.0
      },
      "created_at": "2026-10-01T12:00:00+03:00",
      "updated_at": "2026-10-15T15:30:00+03:00"
    }
  ],
  "links": {
    "first": "https://example.com/api/admin/purchase-dues/overdue?page=1",
    "last": "https://example.com/api/admin/purchase-dues/overdue?page=1",
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

### 3.2. الأقساط غير المدفوعة بالكامل (`GET /api/admin/purchase-dues/unpaid`)
تعرض جميع الأقساط التي لم يتم سدادها بالكامل (`payment < amount`) سواء حل موعد استحقاقها أو لا يزال في المستقبل.

* **الرابط (URL)**: `/api/admin/purchase-dues/unpaid`
* **النوع (Method)**: `GET`
* **معلمات الاستعلام (Query Parameters)**:
  - `purchase_id`, `supplier_id`, `branch_id`, `page`, `per_page` (كما سبق).

---

### 3.3. الأقساط المسددة بالكامل (`GET /api/admin/purchase-dues/paid`)
تعرض الأقساط التي تم تسديدها بالكامل (`payment >= amount`).

* **الرابط (URL)**: `/api/admin/purchase-dues/paid`
* **النوع (Method)**: `GET`
* **معلمات الاستعلام (Query Parameters)**:
  - `purchase_id`, `supplier_id`, `branch_id`, `page`, `per_page` (كما سبق).

---

### 3.4. قائمة الأقساط مع الفلاتر (`GET /api/admin/purchase-dues`)
واجهة مرنة تدعم البحث والفلترة الشاملة.

* **الرابط (URL)**: `/api/admin/purchase-dues`
* **النوع (Method)**: `GET`
* **معلمات الاستعلام (Query Parameters)**:
  - `status` (string, اختياري): `all` (الكل)، `overdue` (متأخرة)، `unpaid` (غير مدفوعة بالكامل)، `paid` (مدفوعة بالكامل).
  - `purchase_id` (integer, اختياري): فلترة بفاتورة معينة.
  - `supplier_id` (integer, اختياري): فلترة بمورد معين.
  - `branch_id` (integer, اختياري): فلترة بفرع معين.
  - `date` (string, اختياري): تاريخ محدد `YYYY-MM-DD`.
  - `from_date` (string, اختياري): من تاريخ `YYYY-MM-DD`.
  - `to_date` (string, اختياري): إلى تاريخ `YYYY-MM-DD`.
  - `page`, `per_page` (اختياري).

---

### 3.5. عرض تفاصيل قسط (`GET /api/admin/purchase-dues/{id}`)
* **الرابط (URL)**: `/api/admin/purchase-dues/{id}`
* **النوع (Method)**: `GET`
* **الاستجابة (200 OK)**:
```json
{
  "status": true,
  "data": {
    "id": 12,
    "purchase_id": 4,
    "amount": 1000.0,
    "payment": 200.0,
    "remaining": 800.0,
    "status": "overdue",
    "date": "2026-10-15",
    "notes": "دفعة شراء مستلزمات",
    "supplier": {
      "id": 2,
      "name": "شركة الدلتا للتوريدات",
      "phone": "01011112222",
      "balance": 5400.0
    },
    "purchase": {
      "id": 4,
      "supplier_id": 2,
      "branch_id": 1,
      "total_cost": 3000.0,
      "paid_amount": 1200.0,
      "remaining_amount": 1800.0
    }
  }
}
```

---

### 3.6. سداد قسط مشتريات (`POST /api/admin/purchase-dues/{id}/pay`)
سداد قيمة القسط أو جزء منه.
* التحقق (Validation): يقبل حقل `payment` (أو `amount`) بحد أدنى `0.01` وحد أقصى يساوي المبلغ المتبقي على القسط (`remaining`).
* إذا كان القسط مدفوعاً بالفعل، يُرجع الخطأ 422.

* **الرابط (URL)**: `/api/admin/purchase-dues/{id}/pay`
* **النوع (Method)**: `POST`
* **جسم الطلب (Request Body)**:
```json
{
  "payment": 400.0,
  "notes": "سداد دفعة نقدية عن طريق الخزينة"
}
```

#### استجابة النجاح (200 OK):
```json
{
  "status": true,
  "message": "تم تسجيل سداد القسط بنجاح وخصم المبلغ من رصيد المورد.",
  "data": {
    "id": 12,
    "purchase_id": 4,
    "amount": 1000.0,
    "payment": 600.0,
    "remaining": 400.0,
    "status": "overdue",
    "date": "2026-10-15",
    "notes": "سداد: 400 في 2026-10-23 21:55:00 - سداد دفعة نقدية عن طريق الخزينة",
    "supplier": {
      "id": 2,
      "name": "شركة الدلتا للتوريدات",
      "balance": 5000.0
    },
    "purchase": {
      "id": 4,
      "paid_amount": 1600.0,
      "remaining_amount": 1400.0
    }
  },
  "meta": {
    "paid_now": 400.0,
    "due_remaining": 400.0,
    "due_status": "overdue",
    "purchase_remaining": 1400.0,
    "supplier_balance": 5000.0
  }
}
```

---

## 4. واجهات أقساط المبيعات (Sale Dues APIs)

### 4.1. الأقساط المتأخرة (`GET /api/admin/sale-dues/overdue`)
تعرض جميع أقساط المبيعات الآجلة المتأخرة عن موعدها (`payment < amount` و `date < today()`).

* **الرابط (URL)**: `/api/admin/sale-dues/overdue`
* **النوع (Method)**: `GET`
* **معلمات الاستعلام (Query Parameters)**:
  - `sale_id` (integer, اختياري): فلترة برقم فاتورة البيع.
  - `saler_id` (integer, اختياري): فلترة بالعميل/المشتري.
  - `branch_id` (integer, اختياري): فلترة بالفرع.
  - `page`, `per_page` (اختياري).

#### نموذج استجابة النجاح (200 OK):
```json
{
  "data": [
    {
      "id": 8,
      "sale_id": 3,
      "amount": 1500.0,
      "payment": 500.0,
      "remaining": 1000.0,
      "status": "overdue",
      "date": "2026-10-20",
      "notes": "قسط فاتورة الجملة رقم 3",
      "saler": {
        "id": 5,
        "name": "مؤسسة الأمل التجارية",
        "phone": "01099998888",
        "balance": 2500.0
      },
      "sale": {
        "id": 3,
        "saler_id": 5,
        "branch_id": 1,
        "total_cost": 4000.0,
        "paid_amount": 2500.0,
        "remaining_amount": 1500.0
      },
      "created_at": "2026-10-05T10:00:00+03:00",
      "updated_at": "2026-10-20T11:00:00+03:00"
    }
  ],
  "links": {
    "first": "https://example.com/api/admin/sale-dues/overdue?page=1",
    "last": "https://example.com/api/admin/sale-dues/overdue?page=1",
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

### 4.2. الأقساط غير المدفوعة بالكامل (`GET /api/admin/sale-dues/unpaid`)
تعرض جميع أقساط المبيعات التي لم تُسدد بالكامل (`payment < amount`).

* **الرابط (URL)**: `/api/admin/sale-dues/unpaid`
* **النوع (Method)**: `GET`

---

### 4.3. الأقساط المسددة بالكامل (`GET /api/admin/sale-dues/paid`)
تعرض أقساط المبيعات التي تم سدادها بالكامل (`payment >= amount`).

* **الرابط (URL)**: `/api/admin/sale-dues/paid`
* **النوع (Method)**: `GET`

---

### 4.4. قائمة أقساط المبيعات مع الفلاتر (`GET /api/admin/sale-dues`)
* **الرابط (URL)**: `/api/admin/sale-dues`
* **النوع (Method)**: `GET`
* **معلمات الاستعلام (Query Parameters)**:
  - `status` (string, اختياري): `all`، `overdue`، `unpaid`، `paid`.
  - `sale_id` (integer, اختياري).
  - `saler_id` (integer, اختياري).
  - `branch_id` (integer, اختياري).
  - `date`, `from_date`, `to_date` (تنسيق: `YYYY-MM-DD`).
  - `page`, `per_page`.

---

### 4.5. عرض تفاصيل قسط مبيعات (`GET /api/admin/sale-dues/{id}`)
* **الرابط (URL)**: `/api/admin/sale-dues/{id}`
* **النوع (Method)**: `GET`

---

### 4.6. سداد قسط مبيعات (`POST /api/admin/sale-dues/{id}/pay`)
سداد قيمة القسط أو جزء منه، وخصمها من رصيد مديونية العميل (`saler.balance`)، وزيادة المبلغ المدفوع وتخفيض المتبقي على فاتورة البيع (`sale`).

* **الرابط (URL)**: `/api/admin/sale-dues/{id}/pay`
* **النوع (Method)**: `POST`
* **جسم الطلب (Request Body)**:
```json
{
  "payment": 500.0,
  "notes": "سداد عبر تحويل بنكي"
}
```

#### استجابة النجاح (200 OK):
```json
{
  "status": true,
  "message": "تم تسجيل سداد القسط بنجاح وخصم المبلغ من حساب العميل/المشتري.",
  "data": {
    "id": 8,
    "sale_id": 3,
    "amount": 1500.0,
    "payment": 1000.0,
    "remaining": 500.0,
    "status": "overdue",
    "date": "2026-10-20",
    "notes": "سداد: 500 في 2026-10-23 21:55:00 - سداد عبر تحويل بنكي",
    "saler": {
      "id": 5,
      "name": "مؤسسة الأمل التجارية",
      "balance": 2000.0
    },
    "sale": {
      "id": 3,
      "paid_amount": 3000.0,
      "remaining_amount": 1000.0
    }
  },
  "meta": {
    "paid_now": 500.0,
    "due_remaining": 500.0,
    "due_status": "overdue",
    "sale_remaining": 1000.0,
    "saler_balance": 2000.0
  }
}
```

---

## 5. أكواد الاستجابة وحالات الخطأ (Error Responses)

### 5.1. محاولة سداد مبلغ أكبر من المتبقي على القسط (422 Unprocessable Content)
إذا كان المتبقي على القسط مثلاً 500 وحاولت إرسال `payment: 600`:
```json
{
  "message": "The payment field must not be greater than 500.",
  "errors": {
    "payment": [
      "The payment field must not be greater than 500."
    ]
  }
}
```

### 5.2. محاولة سداد قسط مدفوع بالكامل بالفعل (422 Unprocessable Content)
```json
{
  "status": false,
  "message": "هذا القسط مدفوع بالكامل بالفعل ولا توجد مبالغ متبقية عليه."
}
```

### 5.3. عدم وجود صلاحية أو انتهاء التوكن (401 Unauthorized)
```json
{
  "message": "Unauthenticated."
}
```

---

## 6. أمثلة كود الربط (Frontend Integration Examples)

### 6.1. كود سداد قسط مشتريات بـ JavaScript (Fetch API):
```javascript
async function payPurchaseDue(dueId, amountToPay, notes = '') {
  const token = localStorage.getItem('admin_token');

  const response = await fetch(`https://your-domain.com/api/admin/purchase-dues/${dueId}/pay`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      payment: amountToPay,
      notes: notes
    })
  });

  const result = await response.json();
  if (!response.ok) {
    throw new Error(result.message || 'فشلت عملية السداد');
  }

  return result;
}
```

### 6.2. كود جلب الأقساط المتأخرة للمبيعات مع الترقيم:
```javascript
async function getOverdueSaleDues(page = 1, perPage = 15) {
  const token = localStorage.getItem('admin_token');

  const response = await fetch(`https://your-domain.com/api/admin/sale-dues/overdue?page=${page}&per_page=${perPage}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });

  return await response.json();
}
```
