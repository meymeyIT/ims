# IMS Database Design (Phase 2)

Database: MySQL 8 / MariaDB 10.4+ (InnoDB, utf8mb4). Laravel's default tables
(`password_reset_tokens`, `sessions`, `cache`, `jobs`, ...) are not shown.

## ERD

```mermaid
erDiagram
    roles ||--o{ users : "has"
    roles ||--o{ permission_role : ""
    permissions ||--o{ permission_role : ""
    categories ||--o{ products : "has"
    units ||--o{ products : "has"
    suppliers ||--o{ purchases : "supplies"
    users ||--o{ purchases : "created_by / approved_by"
    purchases ||--|{ purchase_items : "contains"
    products ||--o{ purchase_items : "ordered in"
    purchases ||--o{ purchase_receipts : "received via"
    users ||--o{ purchase_receipts : "received_by"
    purchase_receipts ||--|{ purchase_receipt_items : "contains"
    purchase_items ||--o{ purchase_receipt_items : "partially received by"
    products ||--o{ stock_movements : "ledger"
    users ||--o{ stock_movements : "recorded by"
    suppliers |o--o{ stock_movements : "optional"
    purchase_receipt_items ||--o| stock_movements : "creates exactly one"
    stock_movements |o--o| stock_movements : "reversal_of"
    users ||--o{ activity_logs : "performed"
    users ||--o{ notifications : "receives"

    roles { bigint id PK
        string name UK }
    permissions { bigint id PK
        string name UK
        string module }
    users { bigint id PK
        bigint role_id FK
        string email UK
        bool is_active
        timestamp deleted_at }
    categories { bigint id PK
        string name UK
        bool is_active }
    units { bigint id PK
        string name UK
        string abbreviation UK }
    products { bigint id PK
        bigint category_id FK
        bigint unit_id FK
        string sku UK
        string barcode UK
        decimal purchase_price
        decimal selling_price
        decimal average_cost
        decimal current_stock
        decimal minimum_stock
        timestamp deleted_at }
    suppliers { bigint id PK
        string name
        timestamp deleted_at }
    purchases { bigint id PK
        string purchase_number UK
        bigint supplier_id FK
        bigint created_by FK
        string status
        decimal total_amount }
    purchase_items { bigint id PK
        bigint purchase_id FK
        bigint product_id FK
        decimal quantity_ordered
        decimal quantity_received
        decimal unit_cost }
    purchase_receipts { bigint id PK
        string receipt_number UK
        uuid receipt_token UK
        bigint purchase_id FK
        datetime received_at }
    purchase_receipt_items { bigint id PK
        bigint purchase_receipt_id FK
        bigint purchase_item_id FK
        decimal quantity
        decimal unit_cost }
    stock_movements { bigint id PK
        string movement_number UK
        bigint product_id FK
        string type
        decimal quantity "signed"
        decimal previous_stock
        decimal new_stock
        string reference_type
        bigint reference_id
        bigint reversal_of_id FK "unique" }
    settings { bigint id PK
        string key UK
        text value }
    activity_logs { bigint id PK
        bigint user_id FK
        string action
        string module }
```

## Key design rules

| Rule from the spec | How the schema enforces it |
|---|---|
| Stock cannot be negative | `CHECK (current_stock >= 0)` on products and on every movement row |
| Every stock change is recorded | Only `StockService` changes `current_stock` (not fillable); it always inserts a `stock_movements` row |
| History is auditable and immutable | `StockMovement` model throws on update/delete; corrections are new rows with `reversal_of_id` |
| A movement can only be reversed once | `reversal_of_id` is UNIQUE |
| Prevent duplicate receiving | `purchase_receipts.receipt_token` UNIQUE (stops double-submit) and `stock_movements(reference_type, reference_id)` UNIQUE (one movement per receipt line) |
| Never receive more than ordered | `CHECK (quantity_received <= quantity_ordered)` plus validation in the service |
| Creating a PO doesn't change stock | Only receipts create movements |
| Can't delete categories/units in use | Foreign keys use `RESTRICT`; archived products still count |
| Deleted records don't break reports | Products, suppliers and users use soft deletes; relations use `withTrashed()` |
| Money / fractional quantities | `DECIMAL(15,2)` money, `DECIMAL(15,4)` unit cost, `DECIMAL(15,3)` quantities |
| No duplicate low-stock alerts | `products.low_stock_alerted_at` is set when alerted, cleared when restocked above minimum |

Reconciliation check (should always return 0 rows):

```sql
SELECT p.id, p.sku, p.current_stock, COALESCE(SUM(m.quantity), 0) AS ledger_total
FROM products p LEFT JOIN stock_movements m ON m.product_id = p.id
GROUP BY p.id, p.sku, p.current_stock
HAVING p.current_stock <> ledger_total;
```

## Inventory valuation: weighted average cost

On every stock increase that carries a cost (purchase receipt, manual stock-in with cost):

```
new_average_cost = (current_stock * average_cost + qty_in * unit_cost) / (current_stock + qty_in)
```

Stock-outs do not change `average_cost`; each OUT movement stores the average cost at that
moment in `unit_cost`, so the cost of goods issued is reproducible later.

Inventory value = `SUM(current_stock * average_cost)`.

## Roles (default permissions, editable by Admin)

| Role | Summary |
|---|---|
| Admin | Everything (always passes every permission check) |
| Manager | Products, categories, units, suppliers, purchases (create/approve/receive/cancel), stock in/out/adjust/reverse, reports + export |
| Staff | Dashboard, view products, stock history, stock out, notifications. Stock in only if Admin grants `stock.in` |
| Viewer | Dashboard, view products and stock balances, view reports. No create/update/delete |
