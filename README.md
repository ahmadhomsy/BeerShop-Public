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

## 3. بيانات الدخول التجريبية والأمان في الإنتاج (Demo & Production Accounts)

> ⚠️ **تنبيه أمني هام**: بيانات الدخول التجريبية أدناه مخصصة **حصراً لبيئات التطوير والاختبار المحلي (`local` / `testing`)** لأغراض العرض الأكاديمي، ويتم تعطيل زراعتها تلقائياً عند تشغيل النظام في بيئة الإنتاج (`APP_ENV=production`).

| الدور (Role) | البريد الإلكتروني (Email) | كلمة المرور (Password) | البيئة المسموحة | الصلاحيات |
|---|---|---|---|---|
| **مدير (Admin)** | `aa@gmail.com` | `0000` | Local / Testing فقط | إضافة وتعديل وحذف المنتجات، تحديث حالات الطلبات، عرض جميع طلبات النظام |
| **زبون (Customer)** | `cc@gmail.com` | `0000` | Local / Testing فقط | إنشاء الطلبات، عرض طلباته الخاصة فقط (يُمنع من تعديل الحالات أو رؤية طلبات غيره) |
| **بائع (Seller)** | `bb@gmail.com` | `0000` | Local / Testing فقط | إدارة المنتجات التابعة لمتجره |

### إنشاء حساب المدير في بيئة الإنتاج (Production Admin Provisioning):
في بيئة الإنتاج، يتم إنشاء حساب المدير عبر أمر Artisan المخصص الآمن:
```bash
docker compose exec app php artisan user:create-admin admin@yourdomain.com --name="Super Admin"
```
أو عبر تمرير المتغيرات البيئية عند أول تشغيل فقط:
```env
INITIAL_ADMIN_EMAIL=admin@yourdomain.com
INITIAL_ADMIN_PASSWORD=YourStrongPasswordHere!
INITIAL_ADMIN_NAME="Production Admin"
```

---

## 4. أوامر سكريبتات الإثبات (Verification & Proof Scripts)

### الإثبات الأول: سباق التزامن عبر HTTP (Race Condition Proof)
إرسال 10 طلبات متزامنة في أجزاء من الثانية لطلب آخر قطعة متوفرة في المخزون (`total_stock = 1`):
```bash
python tests/Concurrency/test_http_orders_race.py
```
- **النتيجة المتوقعة**: طلب واحد فقط ينجح برمز `201 Created`، و 9 طلبات تُرفض برمز `422 Unprocessable Entity` (مع التأكد من عدم وجود أي خطأ 500 أو 401 أو 429).
- **التحقق من قاعدة بيانات PostgreSQL**: يتم فحص القيم الفعلية برمجياً وقراءتها من قاعدة البيانات:
```bash
docker compose exec db_inventory psql -U inventory_user -d inventory_db -c "SELECT product_id, total_stock, reserved_stock FROM inventory WHERE product_id = 1;"
# النتيجة المؤكدة: total_stock = 1, reserved_stock = 1, available_stock = 0
```

### الإثبات الثاني: سباق التزامن المباشر عبر gRPC (Direct gRPC Race Condition)
إرسال 10 طلبات حجز مخزون `ReserveStock` متزامنة مباشرة عبر عميل gRPC نحو الخدمة المصغرة:
```bash
python tests/Concurrency/test_race_condition.py
```
- **النتيجة المتوقعة**: نجاح حجز واحد فقط (`success: True`)، ورفض 9 حجوزات (`success: False`)، واستقرار المخزون النهائي عند `reserved_stock = 1` و `available_stock = 0` دون أي بيع زائد (Zero Overselling).

### الإثبات الثالث: توقف خدمة المخزون ومهلة الـ Deadline (HTTP 503)
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

### الإثبات الرابع: المعاملات التعويضية (Compensating Transactions)
اختبار يثبت تمييز 4 حالات فشل مختلفة:
1. الفشل بسبب عدم وجود المنتج في الكتالوج (Missing Product).
2. الفشل أثناء حفظ الطلب في قاعدة بيانات MySQL (Order Persistence Failure).
3. الفشل أثناء حجز المخزون (ReserveStock Failure - لا يتم استدعاء إجراء تعويضي لعدم حجز شيء).
4. الفشل أثناء تأكيد الحجز (ConfirmStock Failure - إلغاء الطلب المحفوظ واستدعاء الإجراء التعويضي).

```bash
docker compose exec app php artisan test --filter=CompensationTransactionTest
```

### تشغيل حزمة الاختبارات السريعة (Fast Unit & Feature Tests - SQLite):
```bash
docker compose exec app php artisan test
```

### تشغيل حزمة اختبارات التكامل الحقيقية الموزعة (Real Distributed Integration Tests):
اختبارات تكامل فعلية تتصل بـ **MySQL** الحقيقي و **PostgreSQL** الحقيقي وعميل **gRPC** الأصلي ضد الخدمة المصغرة بلغة Python، وتختبر حقن أخطاء النقل (Real Fault Injection) وحالات العزل للمستخدمين:
```bash
docker compose exec app vendor/bin/phpunit --configuration=phpunit.integration.xml
```

---

## 5. توثيق الواجهات البرمجية ونموذج الاستجابة الموحد (API Documentation & Error Contract)

- **ملف مواصفات OpenAPI 3.0**:
  [docs/openapi.yaml](docs/openapi.yaml)
- **مجموعة Postman الجاهزة للاختبار**:
  [docs/postman_collection.json](docs/postman_collection.json)
  (تحتوي على التوثيق، تسجيل الدخول لكافة الأدوار، استعراض وتصفية المنتجات، إنشاء الطلبات، وتغيير حالاتها).

### العقد الموحد لردود الأخطاء (Standardized API Error Contract):
تلتزم جميع الواجهات البرمجية بنموذج استجابة موحد دون تسريب أي بيانات حساسة أو تتبعات برمجية (Stack Traces):
```json
{
  "success": false,
  "message": "Human-readable message",
  "data": null,
  "errors": null
}
```
الحالات المدعومة:
- `401 Unauthorized`: عند غياب أو عدم صحة الـ JWT Token.
- `403 Forbidden`: عند محاولة الزبون الوصول لطلبات غيره أو تعديل حالة الطلب.
- `404 Not Found`: عند طلب مورد غير موجود.
- `422 Unprocessable Entity`: عند فشل التحقق من الحقول أو عدم كفاية رصيد المخزون (`errors: {"stock": [...]}`).
- `429 Too Many Requests`: عند تجاوز حد الطلبات المسموح (Rate Limiting).
- `500 Internal Server Error`: رسالة عامة آمنة عند حدوث خطأ غير متوقع دون تسريب تفاصيل النظام.
- `503 Service Unavailable`: عند توقف خدمة المخزون أو تجاوز مهلة الاستجابة (Deadline Exceeded).

---

## 6. إدارة اتساق المخزون الموزع والمطابقة (Distributed Saga Reconciliation & Outbox)

لمعالجة سيناريوهات الفشل في الأنظمة الموزعة (Distributed Failures) عند انقطاع الاتصال أو انتهاء المهلة (gRPC Deadline Exceeded / Transport Timeout) أثناء خطوة تأكيد الحجز `ConfirmStock`، تم تطبيق نمط **Transactional Outbox / Saga Reconciliation**:

### 1. آلة الحالات لعملية الحجز (Reservation Workflow States):
- `pending_reservation`: تسجيل نية الطلب وبدء استدعاء gRPC `ReserveStock`.
- `pending_confirmation`: تم حجز المخزون في PostgreSQL وتم حفظ الطلب في MySQL، ولكن استدعاء `ConfirmStock` واجه انقطاعاً في الشبكة أو مهلة (Transport Timeout). **لا يتم إلغاء الطلب ولا يتم تحرير المخزون**، لأن الحجز قد يكون تم تأكيده بالفعل في خدمة المخزون.
- `confirmed`: تم تأكيد الحجز بنجاح في PostgreSQL (أو عبر المطابقة اللاحقة Idempotent Confirmation).
- `pending_release`: رُفض التأكيد بسبب منطقي قاطع (Business Failure كـانتهاء صلاحية الحجز TTL)، أو فشلت معاملة MySQL، فيتم إلغاء الطلب في MySQL وجدولة الإجراء التعويضي `ReleaseStock`.
- `released`: تم تحرير المخزون بنجاح واسترجاع الكميات إلى الرصيد المتاح.
- `failed`: تجاوزت محاولات التعويض الحد الأقصى (`max_retries = 5`) وتتطلب تدخلاً إدارياً.

> ⚠️ **قاعدة ذهبية (Critical Invariant)**: لا يتم أبداً تحرير حجز مؤكد (`confirmed`) كإجراء تعويضي. انقطاع الشبكة ليس دليلاً على فشل العملية عن بُعد.

### 2. عزل مفتاح الفرادة للمستخدم والمطالبات الذرية (User-Scoped Idempotency Isolation & Atomic Claims):
- يدعم الـ Endpoint `POST /api/v1/orders` الترويسة `Idempotency-Key` (أو `X-Idempotency-Key`) بحد أقصى 255 محرفاً.
- **عزل أمني تام للمستخدم (`user_id`)**: يتم حصر نطاق المفتاح ضمن حساب المستخدم المصادق عليه حصراً مع قيد فرادة مركب في قاعدة البيانات `(user_id, idempotency_key)`. إذا أرسل مستخدمان مختلفان نفس المفتاح، يحصل كل مستخدم على طلبه المستقل تماماً دون أي تصادم أو تسريب للبيانات.
- **مطالبة ذرية وحماية ضد السباق (Atomic In-Flight Claim)**: يتم تسجيل نية الطلب ذرياً في جدول `inventory_reconciliations` قبل استدعاء خدمة المخزون عن بُعد، مع قفل زمني مؤقت (`locked_until`, `locked_by`). في حال وصول طلبين متطابقين متزامنين لنفس المستخدم، ينتظر الطلب الرديف انتهاء الطلب الأصيل ويحصل على نفس الطلب دون تكرار الحجز في المخزون.
- يتم حساب تجزئة مشفرة قطعية (SHA-256 Payload Hash) لبيانات الطلب:
  - في حال إعادة إرسال نفس المفتاح بنفس الحمولة (Idempotent Retry): يُعاد الطلب الأصلي نفسه دون إنشاء طلب مكرر ودون حجز مخزون مضاعف.
  - في حال إعادة استخدام المفتاح مع حمولة مختلفة: يُرفض الطلب برمز `422 Unprocessable Entity` لمنع تعارض العمليات.

### 3. خادم المطابقة في الخلفية وعقود الإيجار الذرية (Atomic Lease Locking in Worker):
تعمل حاوية مخصصة `beershop_reconciliation_worker` بشكل دائم كـ Daemon لمعالجة السجلات المعلقة عبر عقود إيجار ذرية (`locked_until`, `locked_by`) تضمن عدم معالجة نفس السجل من قبل أكثر من عامل تزامنياً، مع تحرير كامل لأقفال صفوف MySQL قبل إجراء أي استدعاء شبكي خارجي عبر gRPC:
```bash
# فحص حالة حاوية عامل المطابقة
docker compose ps reconciliation_worker

# استعراض سجلات عامل المطابقة المباشرة
docker compose logs -f reconciliation_worker
```

### 4. فحص وإدارة سجلات المطابقة يدوياً (Inspecting Reconciliation Records):
```bash
# تشغيل دفعة مطابقة واحدة يدوياً
docker compose exec app php artisan inventory:reconcile

# فحص السجلات المعلقة أو الفاشلة مباشرة من MySQL
docker compose exec db_main mysql -u beershop_user -pbeershop_password aboodm4_beershop -e "
  SELECT id, order_ref, reservation_id, workflow_state, retry_count, next_retry_at, last_error
  FROM inventory_reconciliations
  ORDER BY id DESC LIMIT 10;
"
```
