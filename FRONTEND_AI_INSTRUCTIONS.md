# Frontend AI Migration Prompt & Technical Specification
## Refactoring: Transition to Branch-Specific Inventory & Removal of Product Stock

> **Instruction for the Frontend Developer / AI Assistant:**  
> You are an expert Frontend Developer. Your task is to refactor the frontend application to adapt to backend architectural changes regarding **Branch-Specific Stock** and the **complete removal of stock from products**. Follow the specifications, types, API contracts, and component checklist below to update the codebase cleanly.

---

### Context of Backend Changes
1. **Stock is no longer centralized**:
   - Materials (`Material`) and Product Recipes (`ProductRecipe`) no longer have a direct, single global `stock` field in their creation/update forms.
   - Their stock is now tracked independently per branch (`MaterialStock`, `ProductRecipeStock`) through transactions (Purchases, Manufacturing, Wastes).
2. **Products have NO stock**:
   - Products (`Product`) are prepared on-demand from recipes at order time. Therefore, `stock` has been **completely removed** from Product creation, editing, details, and cashier/table views.
3. **Operational Endpoints Require `branch_id`**:
   - Recording a **Purchase**, recording **Waste**, and executing **Manufacturing** now **require** a `branch_id`.

---

## 1. Data Types & Interfaces (TypeScript / JSDoc)

Update your entity models / TypeScript interfaces as follows:

```typescript
// ==================== BRANCH ====================
export interface Branch {
  id: number;
  name: { en: string; ar: string } | string;
}

// ==================== MATERIAL ====================
export interface MaterialStockItem {
  branch_id: number;
  branch_name?: { en: string; ar: string } | string;
  stock: number;
}

export interface Material {
  id: number;
  name: { en: string; ar: string };
  status: boolean;
  category_id?: number | null;
  category?: any;
  stock?: number; // Stock of the requested branch (if ?branch_id=X sent) or total across branches
  total_stock?: number;
  stocks?: MaterialStockItem[] | null;
  created_at?: string;
  updated_at?: string;
}

// Creation / Update payload: REMOVE 'stock'
export interface CreateMaterialPayload {
  name: { en: string; ar: string };
  status?: boolean;
  category_id?: number | null;
  // DO NOT SEND 'stock'
}

// ==================== PRODUCT RECIPE ====================
export interface ProductRecipeStockItem {
  branch_id: number;
  branch_name?: { en: string; ar: string } | string;
  stock: number;
}

export interface ProductRecipe {
  id: number;
  name: { en: string; ar: string };
  status: boolean;
  category_id?: number | null;
  category?: any;
  stock?: number; // Branch-specific or total
  total_stock?: number;
  stocks?: ProductRecipeStockItem[] | null;
  created_at?: string;
  updated_at?: string;
}

// Creation / Update payload: REMOVE 'stock'
export interface CreateProductRecipePayload {
  name: { en: string; ar: string };
  status?: boolean;
  category_id?: number | null;
  // DO NOT SEND 'stock'
}

// ==================== PRODUCT ====================
export interface Product {
  id: number;
  name: { en: string; ar: string };
  description?: { en: string; ar: string };
  image: string;
  price: number;
  tax_id?: number | null;
  discount_id?: number | null;
  category_id?: number | null;
  sub_category_id?: number | null;
  variations?: any[];
  // NOTE: 'stock' field is REMOVED completely
}

// Creation / Update payload: REMOVE 'stock'
export interface CreateProductPayload {
  name: { en: string; ar: string };
  description?: { en: string; ar: string };
  price: number;
  image: File | string;
  tax_id?: number | null;
  discount_id?: number | null;
  category_id?: number | null;
  sub_category_id?: number | null;
  variations?: any[];
  // DO NOT SEND 'stock'
}

// ==================== PURCHASES ====================
export interface PurchaseItemPayload {
  material_id?: number | null;
  product_recipe_id?: number | null;
  quantity: number;
  cost: number;
}

export interface CreatePurchasePayload {
  branch_id: number; // REQUIRED: must select branch first
  notes?: string;
  receipt?: File | string | null;
  items: PurchaseItemPayload[];
}

export interface Purchase {
  id: number;
  branch_id: number;
  branch?: Branch;
  receipt?: string | null;
  receipt_url?: string | null;
  total_cost: number;
  total_quantity: number;
  cost: number;
  quantity: number;
  notes?: string | null;
  items: any[];
  created_at?: string;
  updated_at?: string;
}

// ==================== WASTES ====================
export interface CreateWastePayload {
  branch_id: number; // REQUIRED
  product_recipe_id?: number | null;
  material_id?: number | null;
  count: number;
}

export interface Waste {
  id: number;
  branch_id: number;
  branch?: Branch;
  product_recipe_id?: number | null;
  product_recipe?: ProductRecipe | null;
  material_id?: number | null;
  material?: Material | null;
  count: number;
  created_at?: string;
  updated_at?: string;
}

// ==================== MANUFACTURING ====================
export interface ManufacturingRecipeItemPayload {
  material_id?: number | null;
  product_recipe_id?: number | null;
  count: number;
}

export interface ExecuteManufacturingPayload {
  branch_id: number; // REQUIRED
  product_id?: number | null;
  product_recipe_id?: number | null;
  count: number;
  recipes: ManufacturingRecipeItemPayload[];
}
```

---

## 2. Component & Screen Refactoring Guide

### A. Material & Product Recipe Forms (Create / Edit)
1. **Files to look for**:
   - `MaterialForm`, `CreateMaterial`, `EditMaterial`, `MaterialModal`
   - `ProductRecipeForm`, `CreateProductRecipe`, `EditProductRecipe`, `RecipeModal`
2. **Action**:
   - **Remove the `stock` input field** from the form template/JSX.
   - Remove `stock` from the initial state / reactive form object (`initialValues`, `formik`, `react-hook-form`, `reactive/ref`).
   - Remove `stock` validation rule (e.g. `yup.number().min(0)` or Zod schema).
3. **Table/List View (`MaterialsTable`, `RecipesTable`)**:
   - If displaying stock in table columns:
     - Either display `item.total_stock ?? item.stock ?? 0`.
     - Or add a Branch Filter dropdown at the top of the table. When user selects Branch `id`:
       Call API: `GET /api/admin/materials?branch_id=${selectedBranchId}`.
       The returned `item.stock` will represent the stock for that branch specifically.

---

### B. Product Forms (Create / Edit) & Product Views
1. **Files to look for**:
   - `ProductForm`, `CreateProduct`, `EditProduct`, `ProductModal`
   - Cashier screens: `POS`, `CashierHome`, `ProductsGrid`
   - Table Ordering screens: `TableMenu`, `ProductCard`, `ProductDetails`
2. **Action**:
   - **Remove the `stock` input field** from Product Create/Edit forms.
   - Remove any client-side `stock > 0` validation checking if product can be ordered. Since products are prepared on demand from recipes, products are always available as long as they are active.

---

### C. Purchase Screen (`Purchases / Add Purchase Invoice`)
1. **Files to look for**:
   - `PurchaseForm`, `CreatePurchase`, `NewPurchaseModal`
2. **Action**:
   - Add a required **Branch Selection Dropdown** (`branch_id`) at the top of the invoice form.
   - Populate branches from `select_options.branches`:
     ```typescript
     // Fetch options
     const res = await api.get('/api/admin/purchases/select-options');
     const branchOptions = res.data.data.branches; // [{id, name}, ...]
     ```
   - When the user selects a branch:
     - You can re-fetch or filter options: `GET /api/admin/purchases/select-options?branch_id=${selectedBranchId}` to display each item's current stock in that branch.
   - When submitting:
     ```typescript
     const payload: CreatePurchasePayload = {
       branch_id: selectedBranchId, // MUST BE SENT
       notes: form.notes,
       receipt: form.receiptFile,
       items: form.items.map(item => ({
         material_id: item.material_id || undefined,
         product_recipe_id: item.product_recipe_id || undefined,
         quantity: Number(item.quantity),
         cost: Number(item.cost),
       })),
     };
     await api.post('/api/admin/purchases', payload);
     ```

---

### D. Waste Screen (`Wastes / Record Waste`)
1. **Files to look for**:
   - `WasteForm`, `CreateWasteModal`, `WastesList`
2. **Action**:
   - Add a **Branch Dropdown** (`branch_id`) as the first step or required field.
   - Call `GET /api/admin/wastes/select-options?branch_id=${selectedBranchId}` when branch is selected.
   - Display the current branch stock next to the selected Material / Recipe name:
     `"Available in Branch: " + item.stock`
   - In form submit payload, include `branch_id`:
     ```typescript
     await api.post('/api/admin/wastes', {
       branch_id: selectedBranchId,
       material_id: isMaterial ? selectedItemId : null,
       product_recipe_id: isRecipe ? selectedItemId : null,
       count: Number(count),
     });
     ```
   - **Handle 422 Error**:
     If user tries to waste more than available in that branch, backend returns status 422:
     ```json
     {
       "status": false,
       "message": "المخزون المتوفر للمادة الخام في هذا الفرع (2) غير كافٍ لتسجيل الهالك المطلوب (3)."
     }
     ```
     Display `error.response.data.message` in a toast/alert notification.

---

### E. Manufacturing Screen (`Manufacturing / Execution`)
1. **Files to look for**:
   - `Manufacturing`, `ManufactureModal`, `ProduceForm`
2. **Action**:
   - Add a required **Branch Selector** (`branch_id`).
   - When requesting standard specifications:
     `GET /api/admin/manufacturing/specifications?product_id=${productId}&branch_id=${selectedBranchId}`
     or
     `GET /api/admin/manufacturing/specifications?product_recipe_id=${recipeId}&branch_id=${selectedBranchId}`
   - The returned recipe items will contain the accurate stock for that branch:
     `recipe.material.stock` or `recipe.product_recipe.stock`.
   - Submit manufacturing with `branch_id`:
     ```typescript
     await api.post('/api/admin/manufacturing', {
       branch_id: selectedBranchId, // REQUIRED
       product_id: productId || null,
       product_recipe_id: recipeId || null,
       count: countToProduce,
       recipes: ingredientsList.map(item => ({
         material_id: item.material_id || undefined,
         product_recipe_id: item.product_recipe_id || undefined,
         count: item.requiredQuantity,
       })),
     });
     ```
   - Display insufficient branch stock error message from backend if returned.

---

## 3. Step-by-Step Task Checklist for AI / Developer

Please review and execute the following in order:

- [ ] **Step 1**: Search for all occurrences of `'stock'` in frontend product forms (`ProductForm`, `CreateProduct`, etc.) and delete the stock input field, state property, and validation.
- [ ] **Step 2**: Search for all occurrences of `'stock'` in material forms (`MaterialForm`) and recipe forms (`ProductRecipeForm`) and remove the stock input field, state property, and validation.
- [ ] **Step 3**: Update API services/endpoints for Purchases (`createPurchase` / `addPurchase`) to accept and send `branch_id`. Add branch dropdown in the UI.
- [ ] **Step 4**: Update API services/endpoints for Wastes (`createWaste`) to accept and send `branch_id`. Add branch dropdown in the UI.
- [ ] **Step 5**: Update API services/endpoints for Manufacturing (`executeManufacturing`) to accept and send `branch_id`. Add branch dropdown in the UI.
- [ ] **Step 6**: Update select-options calls to optionally pass `?branch_id=${branchId}` once the branch is selected by the user.
- [ ] **Step 7**: Test submitting a Purchase invoice with a selected branch, and verify the UI updates correctly.
