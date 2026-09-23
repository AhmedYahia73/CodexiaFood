# Frontend AI Migration Prompt & Technical Specification
## Refactoring: Transition to Branch-Specific Inventory & Removal of Product Stock

> **Instruction for the AI Coding Assistant (Cursor / Windsurf / Claude / ChatGPT / Copilot):**  
> You are an expert Senior Frontend Engineer. Your task is to refactor the frontend application to adapt to recent backend architectural changes:
> 1. **Inventory is now branch-specific**: Stock is tracked per branch for Materials and Product Recipes via Purchases, Wastes, and Manufacturing.
> 2. **Products have NO stock**: Final products are prepared on-demand from recipes at order time, so the `stock` field has been **completely removed** from products.
> 3. **Role-Based Permissions (الصلاحيات)**: The system enforces strict role-based access. **NEVER use a single shared endpoint** (like `/branches/select-options`) across different modules. Every screen MUST use its own dedicated module endpoint to avoid `403 Forbidden` errors for users with scoped permissions.
> 
> Follow the exact types, API specifications, and step-by-step checklist below to update the codebase cleanly.

---

## 1. ⚠️ CRITICAL ARCHITECTURAL RULE: Role-Based Permissions (الصلاحيات)

> **DO NOT create a single shared service or call `/api/admin/branches/select-options` globally from all screens!**
> 
> Each module in the system is guarded by its own role/permission. If a user only has permission for **Purchases**, calling `/api/admin/branches/select-options` will result in a **403 Forbidden** error.
> 
> Therefore, **every screen must call its own dedicated endpoint**:

| Screen / Feature | Dedicated Endpoint to Call | Data Returned | Notes |
| :--- | :--- | :--- | :--- |
| **Purchases** (`/purchases`) | `GET /api/admin/purchases/select-options` | `{ branches, materials, product_recipes }` | Only requires Purchase permissions |
| **Wastes** (`/wastes`) | `GET /api/admin/wastes/select-options` | `{ branches, materials, product_recipes }` | Only requires Waste permissions |
| **Manufacturing** (`/manufacturing`) | `GET /api/admin/manufacturing/select-options` | `{ branches, products, product_recipes, materials }` | Only requires Manufacturing permissions |
| **Materials** (`/materials`) | `GET /api/admin/materials/select-options` | `{ branches, categories }` | Used for branch filter dropdown in materials |
| **Product Recipes** (`/product-recipes`) | `GET /api/admin/product-recipes/select-options` | `{ branches, categories }` | Used for branch filter dropdown in recipes |
| **Branch Management Only** (`/branches`) | `GET /api/admin/branches/select-options` | `{ branches }` | Requires Branch Admin permissions |

*Note: All `branches` lists returned in these endpoints share the exact same format:*
```typescript
interface BranchOption {
  id: number;
  name: { en: string; ar: string } | string;
}
```

---

## 2. Updated Data Types & Models (TypeScript)

Update your entity models and TypeScript interfaces:

```typescript
// ==================== BRANCH ====================
export interface Branch {
  id: number;
  name: { en: string; ar: string } | string;
  address?: string | null;
  status?: boolean;
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
  stock?: number; // Stock of the selected branch (if ?branch_id=X was queried), or total stock
  total_stock?: number; // Total stock summed across all branches
  stocks?: MaterialStockItem[] | null; // Detailed breakdown per branch
  created_at?: string;
  updated_at?: string;
}

// Material Create / Edit Payload: 'stock' IS REMOVED
export interface CreateMaterialPayload {
  name: { en: string; ar: string };
  status?: boolean;
  category_id?: number | null;
  // DO NOT SEND 'stock' - initial stock is registered through purchases
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
  stock?: number; // Stock of selected branch or total
  total_stock?: number;
  stocks?: ProductRecipeStockItem[] | null;
  created_at?: string;
  updated_at?: string;
}

// Recipe Create / Edit Payload: 'stock' IS REMOVED
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
  // NOTE: 'stock' is COMPLETELY REMOVED from products
}

// Product Create / Edit Payload: 'stock' IS REMOVED
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
  branch_id: number; // REQUIRED: The branch where items are stocked
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
  notes?: string | null;
  items: any[];
  created_at?: string;
  updated_at?: string;
}

// ==================== WASTES ====================
export interface CreateWastePayload {
  branch_id: number; // REQUIRED: The branch where waste occurred
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
  branch_id: number; // REQUIRED: Branch performing the manufacturing
  product_id?: number | null;
  product_recipe_id?: number | null;
  count: number;
  recipes: ManufacturingRecipeItemPayload[];
}
```

---

## 3. Screen-by-Screen Implementation Guide

### A. Products (Forms, Tables, POS, Cashier)
1. **Forms (`ProductForm`, `CreateProduct`, `EditProduct`)**:
   - Locate and delete the `stock` input field from the UI template / JSX.
   - Remove `stock` from form validation schemas (Zod, Yup, VeeValidate).
   - Remove `stock` from form state / initial values.
2. **POS / Cashier / Table Ordering**:
   - Products are made on-demand. Remove any client-side condition like `product.stock <= 0` that disables product selection or displays "Out of Stock" badges. Products are active and orderable whenever `product.status === true`.

### B. Materials & Recipes (Forms & Tables)
1. **Forms (`MaterialForm`, `ProductRecipeForm`)**:
   - Locate and delete the `stock` input field from Create and Edit forms.
   - Initial stock is never set during creation; it is accumulated via Purchases and Manufacturing.
2. **Tables (`MaterialsTable`, `ProductRecipesTable`)**:
   - You can fetch branches for filtering using their dedicated endpoints:
     - For Materials: `GET /api/admin/materials/select-options` ➔ use `response.data.data.branches`
     - For Recipes: `GET /api/admin/product-recipes/select-options` ➔ use `response.data.data.branches`
   - If a branch filter is selected (`branch_id = X`):
     - Call: `GET /api/admin/materials?branch_id=${branchId}`
     - Column display: Show `material.stock` (which is now scoped to that branch).
   - If no branch is selected:
     - Show `material.total_stock ?? material.stock ?? 0`.

### C. Purchases Screen (`/purchases`)
1. **Load Options**:
   ```typescript
   // Use Purchases' own select-options (Guarded by Purchase permissions)
   const res = await api.get('/api/admin/purchases/select-options');
   const { branches, materials, product_recipes } = res.data.data;
   ```
2. **Branch Selection Dropdown**:
   - Add a required "Branch" (`branch_id`) select dropdown at the top of the invoice form.
   - When user selects a branch:
     ```typescript
     // Optionally reload materials & recipes with stocks updated for this branch:
     const res = await api.get(`/api/admin/purchases/select-options?branch_id=${selectedBranchId}`);
     // Now materials and product_recipes items will have their current stock in that branch
     ```
3. **Form Submission**:
   - Ensure `branch_id` is sent:
     ```typescript
     const payload: CreatePurchasePayload = {
       branch_id: selectedBranchId, // REQUIRED
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

### D. Wastes Screen (`/wastes`)
1. **Load Options**:
   ```typescript
   // Use Wastes' own select-options (Guarded by Waste permissions)
   const res = await api.get('/api/admin/wastes/select-options');
   const { branches } = res.data.data;
   ```
2. **Branch Selection Dropdown**:
   - Add a required Branch dropdown as the first step.
   - When a branch is chosen:
     ```typescript
     const res = await api.get(`/api/admin/wastes/select-options?branch_id=${selectedBranchId}`);
     const { materials, product_recipes } = res.data.data;
     // materials & product_recipes will show current branch stock
     ```
3. **Display Available Branch Stock**:
   - Display a badge: `"Available in selected branch: ${item.stock}"`.
4. **Form Submission & 422 Handling**:
   ```typescript
   try {
     await api.post('/api/admin/wastes', {
       branch_id: selectedBranchId, // REQUIRED
       material_id: isMaterial ? selectedItemId : null,
       product_recipe_id: isRecipe ? selectedItemId : null,
       count: Number(form.count),
     });
     toast.success('Waste recorded successfully');
   } catch (error: any) {
     if (error.response?.status === 422 && error.response?.data?.message) {
       // Backend returns: "المخزون المتوفر للمادة الخام في هذا الفرع غير كافٍ..."
       toast.error(error.response.data.message);
     } else {
       toast.error('Failed to record waste');
     }
   }
   ```

### E. Manufacturing Screen (`/manufacturing`)
1. **Load Options**:
   ```typescript
   // Use Manufacturing's own select-options (Guarded by Manufacturing permissions)
   const res = await api.get('/api/admin/manufacturing/select-options');
   const { branches, products, product_recipes } = res.data.data;
   ```
2. **Fetch Recipe Specifications**:
   - When user selects product or recipe to manufacture and selects the branch:
     ```typescript
     const res = await api.get(
       `/api/admin/manufacturing/specifications?product_id=${productId}&branch_id=${selectedBranchId}`
     );
     // Each recipe ingredient will contain .stock representing current branch stock
     ```
3. **Execute Manufacturing**:
   ```typescript
   await api.post('/api/admin/manufacturing', {
     branch_id: selectedBranchId, // REQUIRED
     product_id: productId || null,
     product_recipe_id: recipeId || null,
     count: Number(countToProduce),
     recipes: ingredientsList.map(item => ({
       material_id: item.material_id || undefined,
       product_recipe_id: item.product_recipe_id || undefined,
       count: Number(item.count),
     })),
   });
   ```

---

## 4. Autonomous Refactoring Checklist for AI

Please execute the following steps in sequence:

- [ ] **Step 1**: Search for all occurrences of `stock` in `Product` TypeScript interfaces and Form components (`ProductForm`, `CreateProduct`, `EditProduct`). Delete the stock field and validation.
- [ ] **Step 2**: Remove any check in POS / Cashier / Table order components where `product.stock` blocks an order.
- [ ] **Step 3**: Search for `stock` in `MaterialForm` and `ProductRecipeForm`. Delete the stock input field, form state, and validation.
- [ ] **Step 4**: Update Purchases form to add `branch_id` selection. Use `GET /api/admin/purchases/select-options` to populate the dropdown. Ensure `branch_id` is passed in the POST payload.
- [ ] **Step 5**: Update Wastes form to add `branch_id` selection. Use `GET /api/admin/wastes/select-options` to populate the dropdown. Show available branch stock and handle 422 errors gracefully.
- [ ] **Step 6**: Update Manufacturing form to add `branch_id` selection. Use `GET /api/admin/manufacturing/select-options` and pass `branch_id` to specifications and manufacturing execution endpoints.
- [ ] **Step 7**: Update Materials and Product Recipes list tables: if branch filtering is implemented, populate branches using `/api/admin/materials/select-options` and `/api/admin/product-recipes/select-options` respectively.
- [ ] **Step 8**: Run application build (`npm run build` or `npm run type-check`) to confirm 0 TypeScript / compilation errors.
