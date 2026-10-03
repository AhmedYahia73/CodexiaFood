# Scramble API Documentation & Validation Rules

## Core Rule
All request parameters (route query params, optional body fields, filters, pagination params, `lang`, `uu_id`, etc.) MUST be declared in `$request->validate([...])` even if they are optional / nullable.

## Why?
This project uses **Dedoc Scramble** (`dedoc/scramble`) for automated OpenAPI / Swagger documentation generation. Scramble detects endpoint parameters by analyzing the validation rules array. Any parameter read via `$request->query('key')`, `$request->input('key')`, or `$request->get('key')` that is omitted from `$request->validate([...])` will NOT appear in the generated API docs.

## Guidelines:
1. **Always use `sometimes|nullable`** for optional query/body keys:
   ```php
   $validated = $request->validate([
       'page' => 'sometimes|nullable|integer|min:1',
       'per_page' => 'sometimes|nullable|integer|min:1',
       'lang' => 'sometimes|nullable|string|in:ar,en',
       'date' => 'sometimes|nullable|string',
       'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',
   ]);
   ```
2. **Add PHPDoc blocks** above keys to provide field descriptions and examples for Scramble:
   ```php
   /**
    * Specific business date filter (YYYY-MM-DD).
    *
    * @var string|null
    * @example "2026-10-03"
    */
   'date' => 'sometimes|nullable|string',
   ```
3. **Never read unvalidated request parameters** directly without ensuring they are registered in the validation rules.
