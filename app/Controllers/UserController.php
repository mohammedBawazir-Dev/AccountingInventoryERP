<?php
declare(strict_types=1);

final class UserController
{
    private PDO $db;

    private const ROLES = [
        'accountant' => 'محاسب',
        'storekeeper' => 'أمين مخزن',
        'sales' => 'موظف مبيعات',
        'purchasing' => 'موظف مشتريات',
        'viewer' => 'مشاهد تقارير',
        'custom' => 'مستخدم مخصص',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function dispatch(string $page): void
    {
        try {
            if (!Auth::isSuperAdmin()) {
                http_response_code(403);
                $this->render('errors/forbidden', ['message' => 'هذه الشاشة متاحة لمدير النظام فقط.'], 'غير مصرح');
                return;
            }

            if ($page === 'user_permissions') {
                $this->permissionsPage();
                return;
            }

            $action = strtolower((string)($_GET['action'] ?? 'index'));
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Security::verifyCsrf();
                $postAction = strtolower((string)($_GET['action'] ?? ''));
                if ($postAction === 'store') $this->store();
                if ($postAction === 'update') $this->update();
                if ($postAction === 'toggle') $this->toggle();
                throw new RuntimeException('عملية إدارة المستخدمين غير معروفة.');
            }

            if ($action === 'create') {
                $this->form(null);
                return;
            }
            if ($action === 'edit') {
                $this->form($this->findUser((int)($_GET['id'] ?? 0)));
                return;
            }
            $this->index();
        } catch (Throwable $e) {
            Security::flash('danger', $e->getMessage());
            header('Location: ?page=users');
            exit;
        }
    }

    private function index(): void
    {
        $rows = $this->db->query(
            'SELECT UserID, FullName, Email, RoleName, IsActive, IsSuperAdmin, CreatedAt, LastLoginAt
             FROM dbo.AppUsers ORDER BY IsSuperAdmin DESC, FullName'
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->render('users/index', ['users' => $rows, 'roles' => self::ROLES], 'إدارة المستخدمين والصلاحيات');
    }

    private function form(?array $user): void
    {
        $this->render('users/form', [
            'user' => $user,
            'roles' => self::ROLES,
            'isEdit' => $user !== null,
        ], $user ? 'تعديل مستخدم' : 'إضافة مستخدم جديد');
    }

    private function store(): void
    {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role = (string)($_POST['role_name'] ?? 'custom');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');

        if ($name === '' || self::textLength($name) > 150) throw new RuntimeException('أدخل اسمًا صحيحًا لا يتجاوز 150 حرفًا.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || self::textLength($email) > 254) throw new RuntimeException('البريد الإلكتروني غير صالح.');
        if ($email === strtolower(Auth::DOCTOR_EMAIL)) throw new RuntimeException('هذا البريد محجوز لحساب مدير النظام الرئيسي.');
        if (!isset(self::ROLES[$role])) throw new RuntimeException('الدور الوظيفي المحدد غير صالح.');
        if (strlen($password) < 12) throw new RuntimeException('كلمة المرور يجب ألا تقل عن 12 حرفًا.');
        if ($password !== $confirm) throw new RuntimeException('تأكيد كلمة المرور غير مطابق.');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO dbo.AppUsers (FullName, Email, PasswordHash, RoleName, IsActive, IsSuperAdmin, MustChangePassword, AuthVersion)
             VALUES (?, ?, ?, ?, 1, 0, 1, 2)'
        );
        try {
            $stmt->execute([$name, $email, $hash, $role]);
        } catch (PDOException $e) {
            if (self::isDuplicateKey($e)) {
                throw new RuntimeException('هذا البريد مسجل بالفعل.');
            }
            throw $e;
        }

        Security::flash('success', 'تم إنشاء المستخدم. افتح إدارة الصلاحيات لتحديد الشاشات المسموح بها.');
        header('Location: ?page=users');
        exit;
    }

    private function update(): void
    {
        $id = (int)($_POST['user_id'] ?? 0);
        $user = $this->findUser($id);
        if ((int)$user['IsSuperAdmin'] === 1) throw new RuntimeException('لا يمكن تعديل حساب مدير النظام الرئيسي من هذه الشاشة.');

        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role = (string)($_POST['role_name'] ?? 'custom');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');
        if ($name === '' || self::textLength($name) > 150) throw new RuntimeException('أدخل اسمًا صحيحًا لا يتجاوز 150 حرفًا.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || self::textLength($email) > 254) throw new RuntimeException('البريد الإلكتروني غير صالح.');
        if ($email === strtolower(Auth::DOCTOR_EMAIL)) throw new RuntimeException('لا يمكن تحويل مستخدم آخر إلى حساب مدير النظام الرئيسي.');
        if (!isset(self::ROLES[$role])) throw new RuntimeException('الدور الوظيفي المحدد غير صالح.');

        $newHash = null;
        if ($password !== '' || $confirm !== '') {
            if (strlen($password) < 12) throw new RuntimeException('كلمة المرور الجديدة يجب ألا تقل عن 12 حرفًا.');
            if ($password !== $confirm) throw new RuntimeException('تأكيد كلمة المرور غير مطابق.');
            $newHash = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = $this->db->prepare('UPDATE dbo.AppUsers SET FullName = ?, Email = ?, RoleName = ? WHERE UserID = ? AND IsSuperAdmin = 0');
        try {
            $stmt->execute([$name, $email, $role, $id]);
        } catch (PDOException $e) {
            if (self::isDuplicateKey($e)) {
                throw new RuntimeException('هذا البريد مسجل لمستخدم آخر.');
            }
            throw $e;
        }

        if ($newHash !== null) {
            $passUpdate = $this->db->prepare('UPDATE dbo.AppUsers SET PasswordHash = ?, MustChangePassword = 1, AuthVersion = AuthVersion + 1 WHERE UserID = ? AND IsSuperAdmin = 0');
            $passUpdate->execute([$newHash, $id]);
        }

        Security::flash('success', 'تم تحديث بيانات المستخدم.');
        header('Location: ?page=users');
        exit;
    }

    private function toggle(): void
    {
        $id = (int)($_POST['user_id'] ?? 0);
        $user = $this->findUser($id);
        if ((int)$user['IsSuperAdmin'] === 1) throw new RuntimeException('لا يمكن تعطيل حساب مدير النظام الرئيسي.');
        $stmt = $this->db->prepare('UPDATE dbo.AppUsers SET IsActive = CASE WHEN IsActive = 1 THEN 0 ELSE 1 END WHERE UserID = ? AND IsSuperAdmin = 0');
        $stmt->execute([$id]);
        Security::flash('success', 'تم تغيير حالة المستخدم.');
        header('Location: ?page=users');
        exit;
    }

    private function permissionsPage(): void
    {
        $user = $this->findUser((int)($_GET['id'] ?? $_POST['user_id'] ?? 0));
        $modules = [
            'dashboard' => ['title' => 'لوحة التحكم', 'group' => 'النظام'],
            'system_check' => ['title' => 'فحص الاتصال', 'group' => 'النظام'],
        ] + Modules::all();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::verifyCsrf();
            if ((int)$user['IsSuperAdmin'] === 1) throw new RuntimeException('المدير الرئيسي يمتلك جميع الصلاحيات دائمًا.');
            $submitted = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
            $this->db->beginTransaction();
            try {
                $delete = $this->db->prepare('DELETE FROM dbo.AppUserPermissions WHERE UserID = ?');
                $delete->execute([(int)$user['UserID']]);
                $insert = $this->db->prepare(
                    'INSERT INTO dbo.AppUserPermissions (UserID, ModuleKey, CanView, CanCreate, CanEdit, CanDelete)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                foreach ($modules as $key => $_module) {
                    $entry = is_array($submitted[$key] ?? null) ? $submitted[$key] : [];
                    $view = isset($entry['view']) ? 1 : 0;
                    $create = isset($entry['create']) ? 1 : 0;
                    $edit = isset($entry['edit']) ? 1 : 0;
                    $deleteFlag = isset($entry['delete']) ? 1 : 0;
                    if ($create || $edit || $deleteFlag) $view = 1;
                    if ($view || $create || $edit || $deleteFlag) {
                        $insert->execute([(int)$user['UserID'], $key, $view, $create, $edit, $deleteFlag]);
                    }
                }
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
            Security::flash('success', 'تم حفظ صلاحيات المستخدم بنجاح. ستُطبق التغييرات ابتداءً من الطلب التالي.');
            header('Location: ?page=users');
            exit;
        }

        $saved = [];
        $stmt = $this->db->prepare('SELECT ModuleKey, CanView, CanCreate, CanEdit, CanDelete FROM dbo.AppUserPermissions WHERE UserID = ?');
        $stmt->execute([(int)$user['UserID']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $saved[(string)$row['ModuleKey']] = [
                'view' => (int)$row['CanView'] === 1,
                'create' => (int)$row['CanCreate'] === 1,
                'edit' => (int)$row['CanEdit'] === 1,
                'delete' => (int)$row['CanDelete'] === 1,
            ];
        }
        $this->render('users/permissions', [
            'user' => $user,
            'modules' => $modules,
            'permissions' => $saved,
        ], 'صلاحيات المستخدم');
    }

    private function findUser(int $id): array
    {
        if ($id < 1) throw new RuntimeException('رقم المستخدم غير صحيح.');
        $stmt = $this->db->prepare('SELECT UserID, FullName, Email, RoleName, IsActive, IsSuperAdmin FROM dbo.AppUsers WHERE UserID = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new RuntimeException('المستخدم المطلوب غير موجود.');
        return $user;
    }

    private static function isDuplicateKey(PDOException $e): bool
    {
        $info = $e->errorInfo ?? [];
        $sqlServerCode = (string)($info[1] ?? '');
        $message = strtolower($e->getMessage());
        return in_array($sqlServerCode, ['2601', '2627'], true)
            || str_contains($message, 'ux_appusers_email')
            || str_contains($message, 'duplicate');
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function render(string $view, array $data, string $title): void
    {
        require_once dirname(__DIR__, 2) . '/resources/views/helpers.php';
        $file = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!is_file($file)) throw new RuntimeException('ملف العرض غير موجود: ' . $view);
        extract($data, EXTR_SKIP);
        $pageTitle = $title;
        ob_start();
        require $file;
        $content = ob_get_clean();
        $flashes = Security::takeFlashes();
        require dirname(__DIR__, 2) . '/resources/views/layout.php';
    }
}
