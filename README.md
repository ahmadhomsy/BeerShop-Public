# BeerShop - نظام إدارة الطلبات والواجهات البرمجية الهجينة (Polyglot Microservices)

مشروع تطبيقي متكامل مبني باستخدام **Laravel 10** بنمط Modular Architecture، مع خدمة مصغرة للمخزون بلغة **Python** متصلة عبر بروتوكول **gRPC** الأصلي، مع قاعدتي بيانات منفصلتين (**MySQL** للتطبيق الرئيسي و **PostgreSQL** لخدمة المخزون)، ونظام أمان متقدم (JWT & RBAC & Security Headers & Rate Limiting).

---

## 1. المعمارية العامة للمشروع (Architecture Overview)

```
                       +-----------------------+
                       |   Nginx Web Server    |  (Port: 8080)
                       +-----------+-----------+
                                   |
                                   v
             +---------------------+---------------------+
             |         Laravel 10 Application            |  (PHP 8.2-FPM)
             |  - Modular Design: Users, Products,       |
             |    Orders, Notifications                  |
             |  - Native PHP gRPC Client (Extension)    |
             +----------+--------------------+-----------+
                        |                    |
        (MySQL Client)  |                    |  (gRPC Protocol - Port 50051)
                        v                    v
              +------------------+  +--------------------------------+
              |  MySQL 8.0 (Main)|  | Inventory Microservice (Python)|
              |  - App & Users   |  | - Atomic Stock Reservation     |
              |  - Orders & Items|  | - Idempotency & TTL Cleaner    |
              +------------------+  +---------------+----------------+
                                                    |
                                    (psycopg2 Pool) |
                                                    v
                                    +--------------------------------+
                                    | PostgreSQL 16 (Inventory DB)   |
                                    | - Table: inventory             |
                                    | - Table: reservations          |
                                    | - Table: reservation_items     |
                                    +--------------------------------+
```

### الميزات والمعايير الأساسية:
- **Modular Codebase**: الكود منظم داخل `app/Modules/` ضمن 4 وحدات مستقلة:
  - `Users`: التوثيق، صلاحيات الأدوار (RBAC)، JWT Tokens.
  - `Products`: إدارة المنتجات، الصور، الفئات، والتصفية.
  - `Orders`: إدارة الطلبات ودورة حياتها والتكامل مع المخزون.
  - `Notifications`: إشعارات النظام وتغير حالات الطلب.
- **Polyglot Microservice**: خدمة المخزون مبنية بلغة Python وتعمل مع **PostgreSQL 16** حصراً بعمليات ذرية ذرية (Atomic Statements) وتدعم مبدأ (All-or-Nothing).
- **Native PHP gRPC**: تم تفعيل امتدادات `grpc` و `protobuf` الأصلية (C-Extensions) في PHP، وحذف أي جسور خارجية (Python CLI Bridge).
  > **ملاحظة تقنية هامة**: كلاس العميل `app/Grpc/Inventory/InventoryServiceClient.php` مكتوب يدوياً (Stub يرث من `\Grpc\BaseStub`) لأن أداة الترجمة `grpc_php_plugin` غير متوفرة كحزمة جاهزة في بيئات دبيان، بينما تم توليد رسائل Protobuf عبر `protoc --php_out`.
- **Compensating Transactions (Saga-Lite)**: عند فشل إتمام الطلب أو فشل حفظه في قاعدة البيانات بعد نجاح الحجز، يتم استدعاء إجراء تعويضي فوري `ReleaseStock` لإلغاء الحجز واسترجاع الكميات بدقة.
- **Security & Headers**: ترويسات أمان مفروضة (`Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Strict-Transport-Security`, `X-XSS-Protection`).

---

## 2. أوامر التشغيل من الصفر (Getting Started)

جميع الخدمات تعمل بالكامل داخل حاويات Docker، وتعمل الـ Migrations والـ Seeders **تلقائياً** بمجرد بدء الحاويات عبر الـ Entrypoint دون الحاجة لأي أمر يدوي:

```bash
# 1. إيقاف أي حاويات سابقة (اختياري)
docker compose down

# 2. بناء الحاويات من الصفر (تثبيت امتدادات gRPC و Protobuf تلقائياً داخل الحاوية)
docker compose build --no-cache

# 3. تشغيل جميع الخدمات في الخلفية
docker compose up -d

# 4. التأكد من أن جميع الحاويات تعمل وجاهزة (healthy)
docker compose ps
```

بعد ثوانٍ معدودة، يكون الموقع والـ APIs متاحة على الرابط:
`http://localhost:8080`

---

## 3. بيانات الدخول التجريبية (Demo Accounts)

تمت تهيئة الحسابات التالية تلقائياً في قاعدة البيانات بكلمات مرور قياسية:

| الدور (Role) | البريد الإلكتروني (Email) | كلمة المرور (Password) | الصلاحيات |
|---|---|---|---|
| **مدير (Admin)** | `aa@gmail.com` | `0000` | إضافة وتعديل وحذف المنتجات، تحديث حالات الطلبات، عرض جميع طلبات النظام |
| **زبون (Customer)** | `cc@gmail.com` | `0000` | إنشاء الطلبات، عرض طلباته الخاصة فقط (يُمنع من تعديل الحالات أو رؤية طلبات غيره) |
| **بائع (Seller)** | `bb@gmail.com` | `0000` | إدارة المنتجات التابعة لمتجره |

---

## 4. أوامر سكريبتات الإثبات (Verification & Proof Scripts)

### الإثبات الأول: سباق التزامن عبر HTTP (Race Condition Proof)
إرسال 10 طلبات متزامنة في أجزاء من الثانية لطلب آخر قطعة متوفرة في المخزون (`total_stock = 1`):
```bash
python tests/Concurrency/test_http_orders_race.py
```
- **النتيجة المتوقعة**: طلب واحد فقط ينجح برمز `201 Created`، و 9 طلبات تُرفض برمز `422 Unprocessable Entity`.
- **التحقق من قاعدة بيانات PostgreSQL**:
```bash
docker compose exec db_inventory psql -U inventory_user -d inventory_db -c "SELECT product_id, total_stock, reserved_stock FROM inventory WHERE product_id = 1;"
# النتيجة: total_stock = 1, reserved_stock = 1
```

### الإثبات الثاني: توقف خدمة المخزون ومهلة الـ Deadline (HTTP 503)
1. قم بإيقاف خدمة المخزون مؤقتاً:
```bash
docker compose stop inventory_service
```
2. شغّل سكريبت فحص التوقف:
```bash
python tests/Concurrency/test_service_down_503.py
```
أو أرسل طلب إنشاء طلب POST `/api/v1/orders` عبر أي عميل HTTP:
- **النتيجة المتوقعة**: رمز `503 Service Unavailable` مع رد JSON نظيف وعام:
```json
{
  "success": false,
  "message": "Inventory service temporarily unavailable",
  "data": null,
  "errors": null
}
```
3. أعد تشغيل الخدمة:
```bash
docker compose start inventory_service
```

### الإثبات الثالث: المعاملات التعويضية (Compensating Transactions)
اختبار يثبت أنه عند حدوث خطأ أثناء إنشاء الطلب في Laravel بعد نجاح حجز المخزون، يتم التراجع الفوري عن الحجز في خادم المخزون واسترجاع الكمية المتاحة:
```bash
docker compose exec app php artisan test --filter=CompensationTransactionTest
```

### تشغيل حزمة الاختبارات الكاملة (Full Test Suite):
```bash
docker compose exec app php artisan test
```

---

## 5. توثيق الواجهات البرمجية (API Documentation)

- **ملف مواصفات OpenAPI 3.0**:
  [docs/openapi.yaml](docs/openapi.yaml)
- **مجموعة Postman الجاهزة للاختبار**:
  [docs/postman_collection.json](docs/postman_collection.json)
  (تحتوي على التوثيق، تسجيل الدخول، استعراض وتصفية المنتجات، إنشاء الطلبات، وتغيير حالاتها).
