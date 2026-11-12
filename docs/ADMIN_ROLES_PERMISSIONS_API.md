# توثيق نظام الصلاحيات والأدوار (Admin Roles & Permissions API Documentation)

هذا التوثيق موجه لمطوري الواجهة الأمامية (Frontend Developers) ومطوري الـ Backend للتعامل مع نظام الأدوار والصلاحيات في لوحة تحكم الإدارة (Admin Panel).

---

## 📌 نظرة عامة على النظام (System Overview)

يعتمد نظام الصلاحيات على العلاقة الهرمية:
$$\text{Admin} \longrightarrow \text{Role} \longrightarrow \text{Permissions}$$

### القواعد الأساسية لنظام الصلاحيات:
1. **السوبر أدمن (Super Admin)**:
   - إذا كان حساب الأدمن **لا يمتلك `role_id`** (`role_id === null` أو غير محدد)، فإن النظام يمنحه **كامل الصلاحيات تلقائياً وبشكل مطلق** بدون أي قيود، ويمر من كافة فحوصات الأمان.
2. **الأدمن التابع لدور (Role-based Admin)**:
   - إذا تم تعيين `role_id` للأدمن، يتم تقييده حصرياً بالصلاحيات المسجلة لدوره في جدول `permissions` بناءً على:
     - `name`: اسم الموديول (مشتق من الـ Controller، مثل: `purchase`, `product`, `order`...).
     - `action`: نوع الإجراء المسموح به (`all`, `view`, `add`, `update`, `delete`...).
3. **تصنيف الإجراءات (Actions Categories)**:
   - `view`: يشمل العرض، التصفح، والقوائم (`index`, `show`, `lists`, `select-options`, `statistics`, `reports`...).
   - `add`: يشمل الإضافة والإنشاء (`store`, `create`, `manufacture`...).
   - `update`: يشمل التعديل وتحديث الحالات والتعيينات ودفع الأقساط (`update`, `updateStatus`, `assign`, `pay`...).
   - `delete`: يشمل عمليات الحذف (`destroy`).
   - `all`: يمنح الصلاحية الكاملة لجميع الإجراءات على هذا الموديول.
4. **استجابة الرفض (Forbidden Response)**:
   - عند محاولة أدمن الوصول لمسار أو تنفيذ عملية لا يملك صلاحيتها، يُرجع الـ API رمز الحالة `403 Forbidden`.

---

## 🔐 الترويسات المشتركة لجميع الطلبات (Headers)

يجب إرسال الترويسات التالية مع كافة طلبات الإدارة:

```http
Authorization: Bearer <ADMIN_JWT_TOKEN>
Accept: application/json
Content-Type: application/json
```

---

## 📋 قائمة الصلاحيات والإجراءات المدعومة (Permissions & Actions Master List)

| اسم الصلاحية (`name`) | الوصف | الإجراءات المدعومة (`actions`) |
|---|---|---|
| `addon` | الإضافات والمقبلات | `all`, `view`, `add`, `update`, `delete` |
| `admin` | إدارة حسابات مسؤولي النظام | `all`, `view`, `add`, `update`, `delete` |
| `banner` | البانرات والإعلانات | `all`, `view`, `add`, `delete` |
| `branch` | الفروع | `all`, `view`, `add`, `update`, `delete` |
| `business_setup` | إعدادات النظام والمطعم | `all`, `view`, `update` |
| `captain_order` | كباتن الصالة / الويترز | `all`, `view`, `add`, `update`, `delete` |
| `cashier` | نقاط البيع والكاشيرات (POS) | `all`, `view`, `add`, `update`, `delete` |
| `cashier_man` | موظفو الكاشير | `all`, `view`, `add`, `update`, `delete` |
| `category` | الأقسام والتصنيفات | `all`, `view`, `add`, `update`, `delete` |
| `dashboard` | لوحة الإحصائيات العامة | `all`, `view` |
| `delivery` | طياري التوصيل والدليفري | `all`, `view`, `add`, `update`, `delete` |
| `discount` | الخصومات والعروض | `all`, `view`, `add`, `update`, `delete` |
| `expense_list` | بنود المصروفات | `all`, `view`, `add`, `update`, `delete` |
| `financial_account` | الخزائن والحسابات المالية | `all`, `view`, `add`, `update`, `delete` |
| `hall` | الصالات | `all`, `view`, `add`, `update`, `delete` |
| `hall_table` | طاولات الصالة ورموز الـ QR | `all`, `view`, `add`, `update`, `delete` |
| `inventory` | جرد المخازن (مواد خام، وصفات، إضافات) | `all`, `view`, `add`, `update`, `delete` |
| `kitchen` | المطابخ | `all`, `view`, `add`, `update`, `delete` |
| `kitchen_product` | تعيين وتوزيع المنتجات على المطابخ | `all`, `view`, `update` |
| `manufacturing` | عمليات التصنيع والإنتاج | `all`, `view`, `add` |
| `material` | المواد الخام والمكونات | `all`, `view`, `add`, `update`, `delete` |
| `order` | الطلبات والمبيعات | `all`, `view`, `add`, `update`, `delete` |
| `payment_method` | طرق الدفع | `all`, `view`, `add`, `update`, `delete` |
| `product` | المنتجات وقائمة الطعام | `all`, `view`, `add`, `update`, `delete` |
| `product_manufacturing` | أوامر تصنيع الوجبات | `all`, `view`, `add`, `update`, `delete` |
| `product_recipe` | وصفات المنتجات وتكاليفها | `all`, `view`, `add`, `update`, `delete` |
| `purchase` | فواتير المشتريات | `all`, `view`, `add`, `update`, `delete` |
| `purchase_due` | أقساط ومستحقات المشتريات | `all`, `view`, `update` |
| `report` | تقارير الورديات والمبيعات | `all`, `view` |
| `role` | إدارة الأدوار والصلاحيات | `all`, `view`, `add`, `update`, `delete` |
| `sale` | المبيعات الخارجية | `all`, `view`, `add`, `update`, `delete` |
| `sale_due` | أقساط ومستحقات المبيعات | `all`, `view`, `update` |
| `saler` | مسؤولو المبيعات / العملاء | `all`, `view`, `add`, `update`, `delete` |
| `shift` | الورديات والشيفتات | `all`, `view`, `add`, `update`, `delete` |
| `supplier` | الموردون | `all`, `view`, `add`, `update`, `delete` |
| `tax` | الضرائب | `all`, `view`, `add`, `update`, `delete` |
| `unit` | وحدات القياس | `all`, `view`, `add`, `update`, `delete` |
| `waste` | الهوالك والتوالف | `all`, `view`, `add`, `update`, `delete` |

---

## 🚀 توثيق نقاط النهاية (Endpoints API Documentation)

---

### 1️⃣ جلب قائمة الصلاحيات والإجراءات المتاحة (Get Available Permissions List)

يُستخدم هذا الـ API لعرض قائمة الصلاحيات والإجراءات في شاشات إضافة وتعديل الأدوار (Checkboxes / Select options).

- **Method**: `GET`
- **URL**: `/api/admin/roles/permissions`
- **URL البديل**: `/api/admin/roles/lists`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  ```
- **Query Parameters**: لا يوجد.

#### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "status": true,
  "data": {
    "addon": ["all", "view", "add", "update", "delete"],
    "admin": ["all", "view", "add", "update", "delete"],
    "banner": ["all", "view", "add", "delete"],
    "branch": ["all", "view", "add", "update", "delete"],
    "business_setup": ["all", "view", "update"],
    "captain_order": ["all", "view", "add", "update", "delete"],
    "cashier": ["all", "view", "add", "update", "delete"],
    "cashier_man": ["all", "view", "add", "update", "delete"],
    "category": ["all", "view", "add", "update", "delete"],
    "dashboard": ["all", "view"],
    "delivery": ["all", "view", "add", "update", "delete"],
    "discount": ["all", "view", "add", "update", "delete"],
    "expense_list": ["all", "view", "add", "update", "delete"],
    "financial_account": ["all", "view", "add", "update", "delete"],
    "hall": ["all", "view", "add", "update", "delete"],
    "hall_table": ["all", "view", "add", "update", "delete"],
    "inventory": ["all", "view", "add", "update", "delete"],
    "kitchen": ["all", "view", "add", "update", "delete"],
    "kitchen_product": ["all", "view", "update"],
    "manufacturing": ["all", "view", "add"],
    "material": ["all", "view", "add", "update", "delete"],
    "order": ["all", "view", "add", "update", "delete"],
    "payment_method": ["all", "view", "add", "update", "delete"],
    "product": ["all", "view", "add", "update", "delete"],
    "product_manufacturing": ["all", "view", "add", "update", "delete"],
    "product_recipe": ["all", "view", "add", "update", "delete"],
    "purchase": ["all", "view", "add", "update", "delete"],
    "purchase_due": ["all", "view", "update"],
    "report": ["all", "view"],
    "role": ["all", "view", "add", "update", "delete"],
    "sale": ["all", "view", "add", "update", "delete"],
    "sale_due": ["all", "view", "update"],
    "saler": ["all", "view", "add", "update", "delete"],
    "shift": ["all", "view", "add", "update", "delete"],
    "supplier": ["all", "view", "add", "update", "delete"],
    "tax": ["all", "view", "add", "update", "delete"],
    "unit": ["all", "view", "add", "update", "delete"],
    "waste": ["all", "view", "add", "update", "delete"]
  }
}
```

---

### 2️⃣ جلب خيارات الـ Select للأدوار (Roles Select Options)

يُستخدم للحصول على قائمة مختصرة بأسماء ومعرفات الأدوار لاستخدامها في القوائم المنسدلة (Dropdowns).

- **Method**: `GET`
- **URL**: `/api/admin/roles/select-options`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  ```

#### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "status": true,
  "data": {
    "roles": [
      {
        "id": 1,
        "name": "مدير الفرع"
      },
      {
        "id": 2,
        "name": "محاسب ومشتريات"
      }
    ],
    "permissions": {
      "purchase": ["all", "view", "add", "update", "delete"],
      "product": ["all", "view", "add", "update", "delete"]
    }
  }
}
```

---

### 3️⃣ عرض قائمة الأدوار فقط (List Roles - Index)

عرض قائمة الأدوار الأساسية فقط (`id`, `name`) مع التصفح والباجينيشن بدون حشو مصفوفة الصلاحيات لضمان سرعة وخفة الاستجابة، وبدون فلترة الـ ID (حيث أن جلب الدور مع صلاحياته له API مستقل عبر الـ ID).

- **Method**: `GET`
- **URL**: `/api/admin/roles`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  ```
- **Query Parameters**:
  | المعامل (Param) | النوع | اختياري/إجباري | الوصف | مثال |
  |---|---|---|---|---|
  | `name` | string | اختياري | البحث الجزئي باسم الدور | `مشتريات` |
  | `page` | integer | اختياري | رقم الصفحة (الافتراضي: 1) | `1` |
  | `per_page` | integer | اختياري | عدد العناصر في الصفحة (الافتراضي: 15) | `15` |

#### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "data": [
    {
      "id": 1,
      "name": "مسؤول مشتريات",
      "created_at": "2026-11-12T06:15:00+02:00",
      "updated_at": "2026-11-12T06:15:00+02:00"
    },
    {
      "id": 2,
      "name": "كاشير رئيسي",
      "created_at": "2026-11-12T06:16:00+02:00",
      "updated_at": "2026-11-12T06:16:00+02:00"
    }
  ],
  "links": {
    "first": "http://localhost/api/admin/roles?page=1",
    "last": "http://localhost/api/admin/roles?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

---

### 4️⃣ جلب الدور مع صلاحياته عبر الـ ID (Show Role with its Permissions - Independent API)

هذا هو الـ **API المستقل** المخصص لجلب وتفصيل الدور المحدد بواسطة معرفه (`role_id`) مع مصفوفة الصلاحيات التابعة له (`permissions` و `grouped_permissions`).

- **Method**: `GET`
- **URL**: `/api/admin/roles/{role_id}`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  ```
- **Path Parameters**:
  | المعامل | النوع | الوصف | مثال |
  |---|---|---|---|
  | `role_id` | integer | معرف الدور المراد جلبه وعرض صلاحياته | `1` |

#### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "مسؤول مشتريات",
    "permissions_count": 2,
    "permissions": [
      {
        "id": 10,
        "name": "purchase",
        "action": "all",
        "role_id": 1
      },
      {
        "id": 11,
        "name": "supplier",
        "action": "view",
        "role_id": 1
      }
    ],
    "grouped_permissions": {
      "purchase": ["all"],
      "supplier": ["view"]
    },
    "created_at": "2026-11-12T06:15:00+02:00",
    "updated_at": "2026-11-12T06:15:00+02:00"
  },
  "select_options": {
    "permissions": { ... }
  }
}
```

---

### 5️⃣ إضافة دور جديد مع الصلاحيات (Create Role)

- **Method**: `POST`
- **URL**: `/api/admin/roles`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  Content-Type: application/json
  ```

#### 📥 معاملات جسم الطلب (Request Body Parameters):
| الحقل (Field) | النوع | إجباري/اختياري | الوصف |
|---|---|---|---|
| `name` | string | إجباري | اسم الدور الفريد (حد أقصى 255 حرف) |
| `permissions` | array \| object | اختياري | مصفوفة أو كائن الصلاحيات المسندة لهذا الدور |

#### الصيغ المدعومة لإرسال الصلاحيات (`permissions`):

**الصيغة 1: كائن مجمع (Associative Map - الأسهل والأفضل):**
```json
{
  "name": "أمين مخزن",
  "permissions": {
    "inventory": ["all"],
    "material": ["view", "add", "update"],
    "waste": ["view", "add"]
  }
}
```

**الصيغة 2: مصفوفة كائنات (Array of Objects):**
```json
{
  "name": "أمين مخزن",
  "permissions": [
    { "name": "inventory", "action": "all" },
    { "name": "material", "action": "view" },
    { "name": "material", "action": "add" }
  ]
}
```

**الصيغة 3: نصوص نقطية (Dot / Colon strings):**
```json
{
  "name": "أمين مخزن",
  "permissions": [
    "inventory.all",
    "material.view",
    "material.add"
  ]
}
```

#### 🟢 نموذج استجابة ناجحة (Success Response - 201 Created):
```json
{
  "status": true,
  "message": "Role created successfully.",
  "data": {
    "id": 3,
    "name": "أمين مخزن",
    "permissions_count": 3,
    "permissions": [
      {
        "id": 15,
        "name": "inventory",
        "action": "all",
        "role_id": 3
      },
      {
        "id": 16,
        "name": "material",
        "action": "view",
        "role_id": 3
      },
      {
        "id": 17,
        "name": "material",
        "action": "add",
        "role_id": 3
      }
    ],
    "grouped_permissions": {
      "inventory": ["all"],
      "material": ["view", "add"]
    },
    "created_at": "2026-11-12T06:17:00+02:00",
    "updated_at": "2026-11-12T06:17:00+02:00"
  }
}
```

---

### 6️⃣ تعديل دور وصلاحياته (Update Role & Permissions)

تعديل اسم الدور وتحديث الصلاحيات المسندة إليه (يتم استبدال الصلاحيات السابقة بالجديدة تلقائياً).

- **Method**: `PUT` أو `PATCH`
- **URL**: `/api/admin/roles/{role_id}`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  Content-Type: application/json
  ```
- **Path Parameters**:
  | المعامل | النوع | الوصف |
  |---|---|---|
  | `role_id` | integer | معرف الدور المراد تعديله |

#### 📥 معاملات جسم الطلب (Request Body Parameters):
| الحقل (Field) | النوع | إجباري/اختياري | الوصف |
|---|---|---|---|
| `name` | string | اختياري | الاسم الجديد للدور |
| `permissions` | array \| object | اختياري | الصلاحيات الجديدة المراد مزامنتها مع الدور |

#### 🟢 نموذج جسم الطلب (Request Body):
```json
{
  "name": "مشرف مشتريات ومخازن",
  "permissions": {
    "purchase": ["all"],
    "inventory": ["view", "add"],
    "supplier": ["all"]
  }
}
```

#### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "status": true,
  "message": "Role updated successfully.",
  "data": {
    "id": 3,
    "name": "مشرف مشتريات ومخازن",
    "permissions_count": 4,
    "permissions": [
      {
        "id": 20,
        "name": "purchase",
        "action": "all",
        "role_id": 3
      },
      {
        "id": 21,
        "name": "inventory",
        "action": "view",
        "role_id": 3
      },
      {
        "id": 22,
        "name": "inventory",
        "action": "add",
        "role_id": 3
      },
      {
        "id": 23,
        "name": "supplier",
        "action": "all",
        "role_id": 3
      }
    ],
    "grouped_permissions": {
      "purchase": ["all"],
      "inventory": ["view", "add"],
      "supplier": ["all"]
    },
    "created_at": "2026-11-12T06:17:00+02:00",
    "updated_at": "2026-11-12T06:18:00+02:00"
  }
}
```

---

### 7️⃣ حذف دور (Delete Role)

- **Method**: `DELETE`
- **URL**: `/api/admin/roles/{role_id}`
- **Headers**:
  ```http
  Authorization: Bearer <ADMIN_JWT_TOKEN>
  Accept: application/json
  ```
- **Path Parameters**:
  | المعامل | النوع | الوصف |
  |---|---|---|
  | `role_id` | integer | معرف الدور المراد حذفه |

> [!NOTE]
> عند حذف دور معين، يتم حذف الصلاحيات المرتبطة به تلقائياً (`cascade`)، وتتحول قيمة `role_id` لأي أدمن كان يتبع هذا الدور إلى `null` (`onDelete: set null`)، مما يمنحه صلاحيات Super Admin أو يمكن إعادة تعيين دور آخر له.

#### 🟢 نموذج استجابة ناجحة (Success Response - 200 OK):
```json
{
  "status": true,
  "message": "Role deleted successfully."
}
```

---

### 8️⃣ ربط الدور بحساب الأدمن (Assign Role to Admin)

لإسناد دور إلى حساب أدمن عند الإنشاء أو التعديل، يتم إرسال `role_id` إلى مسارات الأدمن:

- **إنشاء أدمن**: `POST /api/admin/admins`
- **تعديل أدمن**: `PUT /api/admin/admins/{admin_id}`

#### 📥 نموذج جسم الطلب (Request Body):
```json
{
  "name": "أحمد محمود",
  "password": "password123",
  "role_id": 1
}
```
- إذا تم إرسال `role_id: null` أو لم يُرسل، يصبح الأدمن **Super Admin** ولديه كامل الصلاحيات.
- إذا تم إرسال `role_id: 1`، يُقيد الأدمن بصلاحيات الدور رقم `1`.

---

## ⚠️ استجابات الأخطاء الشائعة (Error Responses)

### 🔴 1. خطأ عدم امتلاك الصلاحية (403 Forbidden):
يظهر عندما يحاول أدمن تنفيذ عملية غير مشمولة في صلاحيات دوره:
```json
{
  "status": false,
  "message": "Forbidden: You do not have permission to perform this action."
}
```

### 🔴 2. خطأ انتهاء الجلسة أو عدم تسجيل الدخول (401 Unauthorized):
```json
{
  "status": false,
  "message": "Unauthenticated."
}
```

### 🔴 3. خطأ في التحقق من صحة المدخلات (422 Unprocessable Entity):
يظهر عند تكرار اسم الدور أو نقص حقول إجبارية:
```json
{
  "message": "The name has already been taken.",
  "errors": {
    "name": [
      "The name has already been taken."
    ]
  }
}
```
