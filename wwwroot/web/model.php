<?php
class GDKSModel {
    private string $baseDir;
    public PDO $db;

    public function __construct(string $baseDir) {
        $this->baseDir = realpath($baseDir);
        $this->db = new PDO('sqlite:' . __DIR__ . '/accounts.db');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
        $this->initializeAdminIfEmpty();
    }

    private function initializeSchema(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS accounts (
                username TEXT PRIMARY KEY,
                password TEXT NOT NULL,
                permissions TEXT DEFAULT '[]',
                disabled INTEGER DEFAULT 0
            )
        ");

        $columns = $this->db->query("PRAGMA table_info(accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('disabled', $columns)) {
            $this->db->exec("ALTER TABLE accounts ADD COLUMN disabled INTEGER DEFAULT 0");
        }
    }

    public function sanitizePath(string $path): string {
        return str_replace(['..', "\0"], '', $path);
    }

    public function fullPath(string $relativePath): ?string {
        $clean = $this->sanitizePath($relativePath);
        $full = realpath($this->baseDir . DIRECTORY_SEPARATOR . $clean);
        if ($full === false || strpos($full, $this->baseDir) !== 0) {
            return null;
        }
        return $full;
    }

    public function listItems(string $relativePath = ''): array {
        $fullPath = $this->fullPath($relativePath) ?: $this->baseDir;
        $items = scandir($fullPath);
        $files = [];
        $dirs = [];

        foreach ($items as $item) {
            if ($item === '.') continue;
            if ($item === '..') {
                $parentPath = dirname($relativePath);
                if ($parentPath === '.') $parentPath = '';
                $dirs[] = ['name' => '..', 'path' => $parentPath];
                continue;
            }
            $itemPath = $relativePath ? $relativePath . DIRECTORY_SEPARATOR . $item : $item;
            if (is_dir($this->baseDir . DIRECTORY_SEPARATOR . $itemPath)) {
                $dirs[] = ['name' => $item, 'path' => $itemPath];
            } else {
                $files[] = ['name' => $item, 'path' => $itemPath];
            }
        }
        return ['dirs' => $dirs, 'files' => $files];
    }

    public function readFile(string $relativeFile): ?string {
        $fullPath = $this->fullPath($relativeFile);
        if ($fullPath && is_file($fullPath)) {
            return file_get_contents($fullPath);
        }
        return null;
    }

    public function saveFile(string $relativeFile, string $content): bool {
        $fullPath = $this->fullPath($relativeFile);
        if ($fullPath && is_file($fullPath)) {
            return (file_put_contents($fullPath, $content) !== false);
        }
        return false;
    }

    public function startServer(): void {
        shell_exec("docker start gkds-game");
    }

    public function stopServer(): void {
        shell_exec("docker stop gkds-game");
    }

    public function installGamemode(string $url, string $targetFolder): bool {
        $gamemodesPath = $this->fullPath('garrysmod/gamemodes');
        if (!$gamemodesPath || !is_dir($gamemodesPath)) return false;

        $tmpZip = tempnam(sys_get_temp_dir(), 'gmod_') . '.zip';
        if (!file_put_contents($tmpZip, file_get_contents($url))) return false;

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) {
            $extractPath = $gamemodesPath . DIRECTORY_SEPARATOR . 'temp_extract';
            mkdir($extractPath);
            $zip->extractTo($extractPath);
            $zip->close();
            unlink($tmpZip);

            $dirs = scandir($extractPath);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $sourceDir = $extractPath . DIRECTORY_SEPARATOR . $dir;
                $destDir = $gamemodesPath . DIRECTORY_SEPARATOR . $targetFolder;
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $targetPath = $destDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                    if ($item->isDir()) {
                        mkdir($targetPath, 0777, true);
                    } else {
                        copy($item, $targetPath);
                    }
                }

                return $this->deleteDir($extractPath);
            }
        }
        return false;
    }

    public function deleteDir(string $dir): bool {
        if (!is_dir($dir)) return false;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
        return true;
    }

    public function isGamemodeInstalled(string $folder): bool {
        return is_dir($this->baseDir . '/garrysmod/gamemodes/' . $folder);
    }

    public function getAllAccounts(): array {
        $stmt = $this->db->query("SELECT username, permissions, disabled FROM accounts");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $accounts = [];
        foreach ($results as $row) {
            $accounts[$row['username']] = [
                'permissions' => json_decode($row['permissions'], true),
                'disabled' => (bool) $row['disabled']
            ];
        }
        return $accounts;
    }

    public function saveAccounts(array $accounts): bool {
        try {
            $this->db->beginTransaction();
            $this->db->exec("DELETE FROM accounts");
            $stmt = $this->db->prepare("INSERT INTO accounts (username, password, permissions, disabled) VALUES (:username, :password, :permissions, :disabled)");

            foreach ($accounts as $username => $data) {
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $data['password'] ?? '',
                    ':permissions' => json_encode($data['permissions'] ?? []),
                    ':disabled' => !empty($data['disabled']) ? 1 : 0
                ]);
            }

            return $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function setAccountDisabled(string $username, bool $disabled): bool {
        $stmt = $this->db->prepare("UPDATE accounts SET disabled = :disabled WHERE username = :username");
        return $stmt->execute([
            ':disabled' => $disabled ? 1 : 0,
            ':username' => $username
        ]);
    }

    public function addOrUpdateAccount(string $username, ?string $password, array $permissions): bool {
        $exists = $this->db->prepare("SELECT COUNT(*) FROM accounts WHERE username = :username");
        $exists->execute([':username' => $username]);

        if ($exists->fetchColumn() > 0) {
            if ($password) {
                $stmt = $this->db->prepare("UPDATE accounts SET password = :password, permissions = :permissions WHERE username = :username");
                return $stmt->execute([
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':permissions' => json_encode($permissions),
                    ':username' => $username
                ]);
            } else {
                $stmt = $this->db->prepare("UPDATE accounts SET permissions = :permissions WHERE username = :username");
                return $stmt->execute([
                    ':permissions' => json_encode($permissions),
                    ':username' => $username
                ]);
            }
        } else {
            $stmt = $this->db->prepare("INSERT INTO accounts (username, password, permissions) VALUES (:username, :password, :permissions)");
            return $stmt->execute([
                ':username' => $username,
                ':password' => $password ? password_hash($password, PASSWORD_DEFAULT) : '',
                ':permissions' => json_encode($permissions)
            ]);
        }
    }

    public function deleteAccount(string $username): bool {
        $stmt = $this->db->prepare("DELETE FROM accounts WHERE username = :username");
        return $stmt->execute([':username' => $username]);
    }

    private function initializeAdminIfEmpty(): void {
        $stmt = $this->db->query("SELECT COUNT(*) FROM accounts");
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            $randomPassword = bin2hex(random_bytes(4));
            $hash = password_hash($randomPassword, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("INSERT INTO accounts (username, password, permissions) VALUES (:username, :password, :permissions)");
            $stmt->execute([
                'username' => 'admin',
                'password' => $hash,
                'permissions' => json_encode(['all'])
            ]);

            error_log("-------- ADMIN INITIAL CREATED --------");
            error_log("Identifiant : admin");
            error_log("Mot de passe : $randomPassword");
            error_log("-------------------------------------");
        }
    }
}
