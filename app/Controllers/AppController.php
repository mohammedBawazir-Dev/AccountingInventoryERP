<?php
declare(strict_types=1);

final class AppController
{
    private PDO $db;
    private SchemaInspector $schema;
    private CrudRepository $repo;

    public function __construct()
    {
        $this->db = Database::connect();
        Auth::ensureSchema($this->db);
        Auth::ensureDoctorAccount($this->db);
        $this->schema = new SchemaInspector($this->db);
        $this->repo = new CrudRepository($this->db, $this->schema);
    }

    public function dispatch(): void
    {
        $page = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['page'] ?? 'dashboard')) ?: 'dashboard';

        // حساب الدكتور يُنشأ مرة واحدة، وبعدها تصبح كل شاشات النظام محمية بتسجيل الدخول.
        if ($page === 'logout') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                exit('استخدم زر تسجيل الخروج.');
            }
            Security::verifyCsrf();
            Auth::logout();
            header('Location: ?page=login');
            exit;
        }

        if (!Auth::isLoggedIn()) {
            $this->loginPage();
            return;
        }
        if (!Auth::refreshSession($this->db)) {
            header('Location: ?page=login');
            exit;
        }
        if ($page === 'login' || $page === 'doctor_setup') {
            header('Location: ?page=' . rawurlencode(Auth::landingPage()));
            exit;
        }
        $signedInUser = Auth::currentUser();
        if (Auth::isSuperAdmin() && $page === 'change_password') {
            header('Location: ?page=' . rawurlencode(Auth::landingPage()));
            exit;
        }
        if ((int)($signedInUser['MustChangePassword'] ?? 0) === 1 && $page !== 'change_password') {
            header('Location: ?page=change_password');
            exit;
        }
        if ($page === 'users' || $page === 'user_permissions') {
            (new UserController($this->db))->dispatch($page);
            return;
        }
        if ($page === 'change_password') {
            $this->changePassword();
            return;
        }
        if ($page === 'access_denied') {
            $this->forbidden('لم يتم منح هذا الحساب صلاحية عرض أي شاشة. تواصل مع مدير النظام.');
            return;
        }

        if ($page === 'system_check') {
            if (!Auth::can('system_check', 'view')) { $this->forbidden(); return; }
            $this->systemCheck();
            return;
        }
        if ($page === 'dashboard' || $page === 'home') {
            if (!Auth::can('dashboard', 'view')) { $this->forbidden(); return; }
            $this->dashboard();
            return;
        }

        $module = Modules::get($page);
        if (!$module) {
            http_response_code(404);
            $this->render('errors/not_found', ['message' => 'الشاشة المطلوبة غير موجودة.']);
            return;
        }

        $action = preg_replace('/[^a-z_]/i', '', (string)($_GET['action'] ?? 'index')) ?: 'index';
        $permissionAction = match ($action) {
            'create', 'store', 'new_detail', 'store_detail' => 'create',
            'edit', 'update', 'edit_detail', 'update_detail', 'toggle' => 'edit',
            'delete', 'delete_detail' => 'delete',
            default => 'view',
        };
        if (!Auth::can($page, $permissionAction)) { $this->forbidden(); return; }

        try {
            $table = Modules::resolveTable($this->db, $module);
            if (!$this->schema->tableExists($table)) {
                throw new RuntimeException('لم يتم العثور على الجدول dbo.' . $table . ' في قاعدة البيانات الحالية. تحقق من تشغيل سكربت قاعدة البيانات ومطابقة أسماء الجداول.');
            }
            $this->handleModule($page, $module, $table, $action);
        } catch (Throwable $e) {
            if (isset($table) && !$this->schema->tableExists($table)) {
                http_response_code(500);
                $this->render('errors/not_found', ['message' => $e->getMessage()]);
                return;
            }
            Security::flash('danger', $e->getMessage());
            header('Location: ?page=' . rawurlencode($page));
            exit;
        }
    }

    private function handleModule(string $key, array $module, string $table, string $action): void
    {
        if (in_array($action, ['details','new_detail','edit_detail','store_detail','update_detail','delete_detail'], true)) {
            if (empty($module['details_table']) || empty($module['details_fk'])) {
                throw new RuntimeException('هذه الشاشة لا تحتوي على جدول تفاصيل مرتبط في الإعدادات الحالية.');
            }
            $this->handleDetails($key, $module, $table, $action);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::verifyCsrf();
            if ($action === 'store') {
                $this->repo->save($table, $_POST);
                Security::flash('success', 'تمت إضافة السجل بنجاح.');
            } elseif ($action === 'update') {
                $this->repo->save($table, $_POST, (string)($_POST['record_id'] ?? ''));
                Security::flash('success', 'تم تحديث السجل بنجاح.');
            } elseif ($action === 'delete') {
                $this->repo->delete($table, (string)($_POST['record_id'] ?? ''));
                Security::flash('success', 'تم حذف السجل بنجاح.');
            } elseif ($action === 'toggle') {
                $status = $this->findStatusColumn($table);
                if (!$status) {
                    throw new RuntimeException('لا يوجد حقل حالة معروف لهذه الشاشة.');
                }
                $this->repo->toggle($table, (string)($_POST['record_id'] ?? ''), $status);
                Security::flash('success', 'تم تغيير حالة السجل.');
            } else {
                throw new RuntimeException('عملية غير معروفة.');
            }
            header('Location: ?page=' . rawurlencode($key));
            exit;
        }

        if ($action === 'create') {
            $this->form($key, $module, $table, null, 'store');
            return;
        }
        if ($action === 'edit') {
            $id = (string)($_GET['id'] ?? '');
            $row = $this->repo->find($table, $id);
            if (!$row) {
                throw new RuntimeException('السجل المطلوب غير موجود.');
            }
            if ($key === 'chart_accounts') {
                $locked = $this->accountIsLocked($row);
                if ($locked) {
                    throw new RuntimeException('لا يمكن تعديل هذا الحساب لأنه نظامي أو مرتبط بكيان آخر.');
                }
            }
            $this->form($key, $module, $table, $row, 'update');
            return;
        }

        $search = trim((string)($_GET['search'] ?? ''));
        $rows = $this->repo->rows($table, $search);
        if ($key === 'chart_accounts') {
            $rows = $this->orderAccountTree($rows);
        }
        $info = $this->repo->tableInfo($table);
        $this->render('table/index', [
            'pageKey' => $key,
            'module' => $module,
            'table' => $table,
            'rows' => $rows,
            'info' => $info,
            'search' => $search,
            'isDetails' => false,
            'parentId' => null,
            'parentTitle' => null,
            'statusColumn' => $this->findStatusColumn($table),
            'accountLocks' => $key === 'chart_accounts' ? $this->accountLocksForRows($rows) : [],
            'canCreate' => Auth::can($key, 'create'),
            'canEdit' => Auth::can($key, 'edit'),
            'canDelete' => Auth::can($key, 'delete'),
        ]);
    }

    private function handleDetails(string $key, array $module, string $parentTable, string $action): void
    {
        $detailTable = (string)$module['details_table'];
        $foreignKey = (string)$module['details_fk'];
        if (!$this->schema->tableExists($detailTable)) {
            throw new RuntimeException('جدول التفاصيل dbo.' . $detailTable . ' غير موجود.');
        }
        $parentId = (string)($_GET['id'] ?? $_GET['parent_id'] ?? $_POST['parent_id'] ?? '');
        if ($parentId === '') {
            throw new RuntimeException('رقم المستند الأب غير محدد.');
        }
        $parent = $this->repo->find($parentTable, $parentId);
        if (!$parent) {
            throw new RuntimeException('المستند الرئيسي غير موجود.');
        }
        $detailModule = $module;
        $detailModule['title'] = 'تفاصيل ' . $module['title'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::verifyCsrf();
            if ($action === 'store_detail') {
                $this->repo->save($detailTable, $_POST, null, [$foreignKey => $parentId]);
                Security::flash('success', 'تمت إضافة سطر التفاصيل.');
            } elseif ($action === 'update_detail') {
                $this->repo->save($detailTable, $_POST, (string)($_POST['detail_id'] ?? ''), [$foreignKey => $parentId]);
                Security::flash('success', 'تم تحديث سطر التفاصيل.');
            } elseif ($action === 'delete_detail') {
                $detailId = (string)($_POST['detail_id'] ?? $_POST['record_id'] ?? '');
                $detailRow = $this->repo->find($detailTable, $detailId);
                if (!$detailRow || (string)($detailRow[$foreignKey] ?? '') !== $parentId) {
                    throw new RuntimeException('سطر التفاصيل غير تابع لهذا المستند.');
                }
                $this->repo->delete($detailTable, $detailId);
                Security::flash('success', 'تم حذف سطر التفاصيل.');
            }
            header('Location: ?page=' . rawurlencode($key) . '&action=details&id=' . rawurlencode($parentId));
            exit;
        }

        if ($action === 'new_detail' || $action === 'edit_detail') {
            $row = null;
            $saveAction = 'store_detail';
            if ($action === 'edit_detail') {
                $row = $this->repo->find($detailTable, (string)($_GET['detail_id'] ?? ''));
                if (!$row || (string)($row[$foreignKey] ?? '') !== $parentId) {
                    throw new RuntimeException('سطر التفاصيل غير موجود لهذا المستند.');
                }
                $saveAction = 'update_detail';
            }
            $this->form($key, $detailModule, $detailTable, $row, $saveAction, $parentId, $foreignKey, true);
            return;
        }

        $search = trim((string)($_GET['search'] ?? ''));
        $rows = $this->repo->rows($detailTable, $search, ['column' => $foreignKey, 'value' => $parentId]);
        $info = $this->repo->tableInfo($detailTable);
        $this->render('table/index', [
            'pageKey' => $key,
            'module' => $detailModule,
            'table' => $detailTable,
            'rows' => $rows,
            'info' => $info,
            'search' => $search,
            'isDetails' => true,
            'parentId' => $parentId,
            'parentTitle' => $this->parentLabel($parent),
            'statusColumn' => null,
            'accountLocks' => [],
            'canCreate' => Auth::can($key, 'create'),
            'canEdit' => Auth::can($key, 'edit'),
            'canDelete' => Auth::can($key, 'delete'),
        ]);
    }

    private function form(string $key, array $module, string $table, ?array $row, string $saveAction, ?string $parentId = null, ?string $parentField = null, bool $isDetails = false): void
    {
        $info = $this->repo->tableInfo($table);
        $options = [];
        foreach ($info['foreign_keys'] as $field => $fk) {
            try {
                $options[$field] = $this->schema->foreignOptions($fk['table'], $fk['column']);
            } catch (Throwable) {
                $options[$field] = [];
            }
        }
        $this->render('table/form', [
            'pageKey' => $key,
            'module' => $module,
            'table' => $table,
            'row' => $row ?? [],
            'info' => $info,
            'options' => $options,
            'saveAction' => $saveAction,
            'isEdit' => $row !== null,
            'parentId' => $parentId,
            'parentField' => $parentField,
            'isDetails' => $isDetails,
        ]);
    }

    private function dashboard(): void
    {
        $modules = Modules::all();
        $counts = [];
        foreach ($modules as $key => $module) {
            if (!Auth::can($key, 'view')) continue;
            if (!isset($module['table']) && !isset($module['table_candidates'])) continue;
            $table = Modules::resolveTable($this->db, $module);
            $counts[$key] = $this->repo->count($table);
        }
        $canSales = Auth::can('sales_invoices', 'view');
        $canPurchases = Auth::can('purchase_invoices', 'view');
        $canMovements = Auth::can('inventory_movements', 'view');
        $salesTotal = $canSales ? $this->safeSum('sales_invoices', 'total_in_base_currency') : 0.0;
        $purchaseTotal = $canPurchases ? $this->safeSum('purchaseInvoices', 'total_in_base_currency') : 0.0;
        $recentSales = $canSales ? $this->safeRows('sales_invoices') : [];
        $recentPurchases = $canPurchases ? $this->safeRows('purchaseInvoices') : [];
        $recentMovements = $canMovements ? $this->safeRows('inventory_movements') : [];
        $this->render('dashboard/index', compact('counts','salesTotal','purchaseTotal','recentSales','recentPurchases','recentMovements','canSales','canPurchases','canMovements'));
    }

    private function safeSum(string $table, string $column): float
    {
        if (!$this->schema->tableExists($table)) return 0.0;
        $columns = $this->schema->columns($table);
        foreach ($columns as $item) {
            if (strcasecmp((string)$item['column_name'], $column) === 0) {
                $stmt = $this->db->query('SELECT COALESCE(SUM(TRY_CONVERT(DECIMAL(20,6), ' . $this->schema->quote($column) . ')),0) FROM dbo.' . $this->schema->quote($table));
                return (float)$stmt->fetchColumn();
            }
        }
        return 0.0;
    }

    private function safeRows(string $table): array
    {
        try { return $this->repo->rows($table); } catch (Throwable) { return []; }
    }

    private function findStatusColumn(string $table): ?string
    {
        $candidates = ['is_active','Is_active','IsActive','SuplierStoped','CusStoped'];
        foreach ($this->schema->columns($table) as $column) {
            foreach ($candidates as $candidate) {
                if (strcasecmp((string)$column['column_name'], $candidate) === 0) {
                    return (string)$column['column_name'];
                }
            }
        }
        return null;
    }

    private function orderAccountTree(array $rows): array
    {
        $byId = [];
        $children = [];
        foreach ($rows as $row) {
            $id = (string)($row['AccountID'] ?? $row['accountID'] ?? '');
            if ($id === '') continue;
            $byId[$id] = $row;
            $parent = $row['AccParent'] ?? null;
            $children[(string)($parent ?? '')][] = $id;
        }
        foreach ($children as &$childIds) {
            usort($childIds, function ($a, $b) use ($byId) {
                return strnatcasecmp((string)($byId[$a]['AccCode'] ?? ''), (string)($byId[$b]['AccCode'] ?? ''));
            });
        }
        unset($childIds);
        $ordered = [];
        $visited = [];
        $walk = function (string $parentId, int $depth) use (&$walk, &$ordered, &$visited, $children, $byId): void {
            foreach ($children[$parentId] ?? [] as $id) {
                if (isset($visited[$id])) continue;
                $visited[$id] = true;
                $row = $byId[$id];
                $row['_tree_depth'] = $depth;
                $row['_has_children'] = !empty($children[$id]);
                $ordered[] = $row;
                $walk($id, $depth + 1);
            }
        };
        $walk('', 0);
        foreach ($byId as $id => $row) {
            if (!isset($visited[$id])) {
                $row['_tree_depth'] = max(0, (int)($row['AccLevel'] ?? 1) - 1);
                $row['_has_children'] = !empty($children[$id]);
                $ordered[] = $row;
                $visited[$id] = true;
                $walk($id, (int)$row['_tree_depth'] + 1);
            }
        }
        return $ordered;
    }

    private function accountIsLocked(array $row): bool
    {
        $system = (int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1;
        $id = $row['AccountID'] ?? $row['accountID'] ?? null;
        return $system || ($id !== null && count($this->schema->accountLockReasons((string)$id)) > 0);
    }

    private function accountLocksForRows(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $id = $row['AccountID'] ?? $row['accountID'] ?? null;
            if ($id === null) continue;
            $reasons = $this->schema->accountLockReasons((string)$id);
            if ($reasons || (int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1) {
                $map[(string)$id] = $reasons;
            }
        }
        return $map;
    }

    private function parentLabel(array $parent): string
    {
        foreach (['invoice_number','display_id','entryNo','VoucherNumber','AccName','StockName','BoxName','bankName','SuplierName','CusName'] as $field) {
            if (isset($parent[$field]) && $parent[$field] !== '') return (string)$parent[$field];
        }
        return 'رقم ' . (string)reset($parent);
    }

    private function loginPage(): void
    {
        $error = '';
        $email = trim((string)($_POST['email'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Security::verifyCsrf();
                $password = (string)($_POST['password'] ?? '');
                if (!Auth::login($this->db, $email, $password)) {
                    throw new RuntimeException('بيانات الدخول غير صحيحة أو أن الحساب معطّل. لحساب المدير استخدم البريد المحدد فقط.');
                }
                $signedIn = Auth::currentUser();
                $destination = (int)($signedIn['MustChangePassword'] ?? 0) === 1 ? 'change_password' : Auth::landingPage();
                header('Location: ?page=' . rawurlencode($destination));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $this->renderAuth('auth/login', ['error' => $error, 'email' => $email]);
    }

    private function changePassword(): void
    {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Security::verifyCsrf();
                $user = Auth::currentUser();
                $current = (string)($_POST['current_password'] ?? '');
                $new = (string)($_POST['new_password'] ?? '');
                $confirm = (string)($_POST['password_confirmation'] ?? '');
                $stmt = $this->db->prepare('SELECT PasswordHash FROM dbo.AppUsers WHERE UserID = ?');
                $stmt->execute([(int)$user['UserID']]);
                $hash = (string)$stmt->fetchColumn();
                if (!password_verify($current, $hash)) throw new RuntimeException('كلمة المرور الحالية غير صحيحة.');
                if (strlen($new) < 12) throw new RuntimeException('كلمة المرور الجديدة يجب ألا تقل عن 12 حرفًا.');
                if ($new !== $confirm) throw new RuntimeException('تأكيد كلمة المرور غير مطابق.');
                $update = $this->db->prepare('UPDATE dbo.AppUsers SET PasswordHash = ?, MustChangePassword = 0, AuthVersion = AuthVersion + 1 WHERE UserID = ?');
                $update->execute([password_hash($new, PASSWORD_DEFAULT), (int)$user['UserID']]);
                if (!Auth::login($this->db, (string)$user['Email'], $new)) {
                    throw new RuntimeException('تم تغيير كلمة المرور، لكن تعذر تحديث الجلسة. سجّل الدخول مجددًا.');
                }
                Security::flash('success', 'تم تغيير كلمة المرور بنجاح.');
                header('Location: ?page=' . rawurlencode(Auth::landingPage()));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $this->renderAuth('auth/change_password', ['error' => $error]);
    }

    private function forbidden(string $message = 'ليس لديك الصلاحية المطلوبة لفتح هذه الصفحة.'): void
    {
        http_response_code(403);
        $this->render('errors/forbidden', ['message' => $message]);
    }

    private function renderAuth(string $view, array $data = []): void
    {
        require_once dirname(__DIR__, 2) . '/resources/views/helpers.php';
        $viewFile = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            exit('Auth view not found: ' . Security::e($viewFile));
        }
        extract($data, EXTR_SKIP);
        require $viewFile;
    }

    private function systemCheck(): void
    {
        $status = [
            'PHP Version' => PHP_VERSION,
            'PDO_SQLSRV' => extension_loaded('pdo_sqlsrv') ? 'مفعل' : 'غير مفعل',
            'قاعدة البيانات' => 'غير متصل',
        ];
        $tables = [];
        try {
            $name = $this->db->query('SELECT DB_NAME()')->fetchColumn();
            $status['قاعدة البيانات'] = (string)$name;
            $tables = $this->db->query("SELECT name FROM sys.tables WHERE schema_id=SCHEMA_ID('dbo') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $status['خطأ الاتصال'] = $e->getMessage();
        }
        $this->render('system/check', compact('status','tables'));
    }

    private function render(string $view, array $data = []): void
    {
        require_once dirname(__DIR__, 2) . '/resources/views/helpers.php';
        $viewFile = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . Security::e($viewFile);
            return;
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        $flashes = Security::takeFlashes();
        $pageTitle = $module['title'] ?? 'لوحة التحكم';
        require dirname(__DIR__, 2) . '/resources/views/layout.php';
    }
}
