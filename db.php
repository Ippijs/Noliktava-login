<?php

function app_pdo(): PDO
{
    $host = '127.0.0.1';
    $port = '3309';
    $database = 'noliktava_login';
    $username = 'root';
    $password = '';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, $options);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, $options);
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'item_manager', 'shelf_staff') NOT NULL DEFAULT 'shelf_staff',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `shelves` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `shelf_name` VARCHAR(50) NOT NULL UNIQUE,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `item_name` VARCHAR(120) NOT NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
                `shelf_id` INT UNSIGNED NOT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_items_shelf` FOREIGN KEY (`shelf_id`) REFERENCES `shelves` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
                CONSTRAINT `fk_items_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `item_movements` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `item_id` INT UNSIGNED NULL,
                `action` ENUM('add', 'remove', 'move') NOT NULL,
                `from_shelf_id` INT UNSIGNED NULL,
                `to_shelf_id` INT UNSIGNED NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
                `acted_by` INT UNSIGNED NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT `fk_movements_from_shelf` FOREIGN KEY (`from_shelf_id`) REFERENCES `shelves` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT `fk_movements_to_shelf` FOREIGN KEY (`to_shelf_id`) REFERENCES `shelves` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT `fk_movements_user` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $movementRule = $pdo->prepare(
                        "SELECT rc.DELETE_RULE, col.IS_NULLABLE
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                         INNER JOIN information_schema.COLUMNS col
                                 ON col.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
                                AND col.TABLE_NAME = rc.TABLE_NAME
                                AND col.COLUMN_NAME = 'item_id'
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND rc.TABLE_NAME = 'item_movements'
               AND rc.CONSTRAINT_NAME = 'fk_movements_item'
             LIMIT 1"
        );
        $movementRule->execute();
        $movementRuleData = $movementRule->fetch();

        if (!$movementRuleData || (string) $movementRuleData['DELETE_RULE'] !== 'SET NULL' || (string) $movementRuleData['IS_NULLABLE'] !== 'YES') {
            $pdo->exec('ALTER TABLE `item_movements` DROP FOREIGN KEY `fk_movements_item`');
            $pdo->exec('ALTER TABLE `item_movements` MODIFY `item_id` INT UNSIGNED NULL');
            $pdo->exec(
                'ALTER TABLE `item_movements` ADD CONSTRAINT `fk_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
            );
        }

        $shelfCount = (int) $pdo->query('SELECT COUNT(*) FROM `shelves`')->fetchColumn();
        if ($shelfCount === 0) {
            $seedShelves = $pdo->prepare('INSERT INTO `shelves` (`shelf_name`) VALUES (:shelf_name)');
            foreach (['A1', 'A2', 'B1', 'B2', 'C1'] as $shelfName) {
                $seedShelves->execute(['shelf_name' => $shelfName]);
            }
        }

        $checkAdmin = $pdo->prepare('SELECT COUNT(*) FROM `users` WHERE `username` = :username');
        $checkAdmin->execute(['username' => 'admin']);

        if ((int) $checkAdmin->fetchColumn() === 0) {
            $createAdmin = $pdo->prepare(
                'INSERT INTO `users` (`username`, `password`, `role`) VALUES (:username, :password, :role)'
            );
            $createAdmin->execute([
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
            ]);
        }

        return $pdo;
    } catch (PDOException $exception) {
        http_response_code(500);
        exit('Database connection failed. Make sure MySQL is running and check db.php credentials.');
    }
}

function app_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function app_verify_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return is_string($token) && isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function app_is_valid_username(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]{3,50}$/', $username);
}

function app_is_valid_password(string $password): bool
{
    $length = strlen($password);
    if ($length < 6 || $length > 72) {
        return false;
    }

    if (preg_match('/\s/', $password) === 1) {
        return false;
    }

    return preg_match('/[A-Za-z]/', $password) === 1 && preg_match('/\d/', $password) === 1;
}

function app_is_valid_item_name(string $itemName): bool
{
    $length = strlen($itemName);

    return $length >= 2 && $length <= 120;
}

function app_is_valid_quantity(int $quantity): bool
{
    return $quantity >= 1 && $quantity <= 1000000;
}

function app_find_user(PDO $pdo, string $username): ?array
{
    $statement = $pdo->prepare('SELECT * FROM `users` WHERE `username` = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    return $user ?: null;
}

function app_find_user_by_id(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare('SELECT `id`, `username`, `role` FROM `users` WHERE `id` = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function app_all_users(PDO $pdo): array
{
    return $pdo->query('SELECT `id`, `username`, `role`, `created_at` FROM `users` ORDER BY `id` ASC')->fetchAll();
}

function app_all_shelves(PDO $pdo): array
{
    return $pdo->query('SELECT `id`, `shelf_name` FROM `shelves` ORDER BY `shelf_name` ASC')->fetchAll();
}

function app_all_items(PDO $pdo): array
{
    return $pdo->query(
        'SELECT i.`id`, i.`item_name`, i.`quantity`, i.`shelf_id`, s.`shelf_name`, i.`created_at`, i.`updated_at`
         FROM `items` i
         INNER JOIN `shelves` s ON s.`id` = i.`shelf_id`
         ORDER BY i.`item_name` ASC, i.`id` ASC'
    )->fetchAll();
}

function app_user_role(PDO $pdo, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT `role` FROM `users` WHERE `id` = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $role = $statement->fetchColumn();

    return $role !== false ? (string) $role : null;
}

function app_find_shelf(PDO $pdo, int $shelfId): ?array
{
    $statement = $pdo->prepare('SELECT `id`, `shelf_name` FROM `shelves` WHERE `id` = :id LIMIT 1');
    $statement->execute(['id' => $shelfId]);
    $shelf = $statement->fetch();

    return $shelf ?: null;
}

function app_find_item(PDO $pdo, int $itemId): ?array
{
    $statement = $pdo->prepare('SELECT `id`, `item_name`, `quantity`, `shelf_id` FROM `items` WHERE `id` = :id LIMIT 1');
    $statement->execute(['id' => $itemId]);
    $item = $statement->fetch();

    return $item ?: null;
}

function app_can_manage_items(PDO $pdo, int $userId): bool
{
    return in_array(app_user_role($pdo, $userId), ['admin', 'item_manager'], true);
}

function app_can_move_items(PDO $pdo, int $userId): bool
{
    return in_array(app_user_role($pdo, $userId), ['admin', 'shelf_staff'], true);
}

function app_add_item(PDO $pdo, int $userId, string $itemName, int $quantity, int $shelfId): bool
{
    if (!app_can_manage_items($pdo, $userId) || !app_is_valid_item_name(trim($itemName)) || !app_is_valid_quantity($quantity) || !app_find_shelf($pdo, $shelfId)) {
        return false;
    }

    $statement = $pdo->prepare(
        'INSERT INTO `items` (`item_name`, `quantity`, `shelf_id`, `created_by`) VALUES (:item_name, :quantity, :shelf_id, :created_by)'
    );
    $result = $statement->execute([
        'item_name' => trim($itemName),
        'quantity' => $quantity,
        'shelf_id' => $shelfId,
        'created_by' => $userId,
    ]);

    if ($result) {
        $itemId = (int) $pdo->lastInsertId();
        $log = $pdo->prepare(
            'INSERT INTO `item_movements` (`item_id`, `action`, `to_shelf_id`, `quantity`, `acted_by`) VALUES (:item_id, :action, :to_shelf_id, :quantity, :acted_by)'
        );
        $log->execute([
            'item_id' => $itemId,
            'action' => 'add',
            'to_shelf_id' => $shelfId,
            'quantity' => $quantity,
            'acted_by' => $userId,
        ]);
    }

    return $result;
}

function app_remove_item(PDO $pdo, int $userId, int $itemId, int $quantity): bool
{
    if (!app_can_manage_items($pdo, $userId) || !app_is_valid_quantity($quantity)) {
        return false;
    }

    $item = app_find_item($pdo, $itemId);
    if (!$item || $quantity > (int) $item['quantity']) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $itemLogId = $itemId;

        if ($quantity === (int) $item['quantity']) {
            $itemLogId = null;
        } else {
            $update = $pdo->prepare('UPDATE `items` SET `quantity` = `quantity` - :quantity WHERE `id` = :id');
            $update->execute([
                'quantity' => $quantity,
                'id' => $itemId,
            ]);
        }

        $log = $pdo->prepare(
            'INSERT INTO `item_movements` (`item_id`, `action`, `from_shelf_id`, `quantity`, `acted_by`) VALUES (:item_id, :action, :from_shelf_id, :quantity, :acted_by)'
        );
        $log->execute([
            'item_id' => $itemLogId,
            'action' => 'remove',
            'from_shelf_id' => $item['shelf_id'],
            'quantity' => $quantity,
            'acted_by' => $userId,
        ]);

        if ($quantity === (int) $item['quantity']) {
            $delete = $pdo->prepare('DELETE FROM `items` WHERE `id` = :id');
            $delete->execute(['id' => $itemId]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
}

function app_move_item(PDO $pdo, int $userId, int $itemId, int $toShelfId): bool
{
    if (!app_can_move_items($pdo, $userId)) {
        return false;
    }

    $item = app_find_item($pdo, $itemId);
    $toShelf = app_find_shelf($pdo, $toShelfId);

    if (!$item || !$toShelf || (int) $item['shelf_id'] === $toShelfId) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $update = $pdo->prepare('UPDATE `items` SET `shelf_id` = :shelf_id WHERE `id` = :id');
        $update->execute([
            'shelf_id' => $toShelfId,
            'id' => $itemId,
        ]);

        $log = $pdo->prepare(
            'INSERT INTO `item_movements` (`item_id`, `action`, `from_shelf_id`, `to_shelf_id`, `quantity`, `acted_by`) VALUES (:item_id, :action, :from_shelf_id, :to_shelf_id, :quantity, :acted_by)'
        );
        $log->execute([
            'item_id' => $itemId,
            'action' => 'move',
            'from_shelf_id' => $item['shelf_id'],
            'to_shelf_id' => $toShelfId,
            'quantity' => (int) $item['quantity'],
            'acted_by' => $userId,
        ]);

        $pdo->commit();
        return true;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
}

function app_create_user(PDO $pdo, string $username, string $password, string $role): bool
{
    $allowedRoles = ['admin', 'item_manager', 'shelf_staff'];

    if (!app_is_valid_username($username) || !app_is_valid_password($password) || !in_array($role, $allowedRoles, true)) {
        return false;
    }

    if (app_find_user($pdo, $username)) {
        return false;
    }

    $statement = $pdo->prepare(
        'INSERT INTO `users` (`username`, `password`, `role`) VALUES (:username, :password, :role)'
    );

    return $statement->execute([
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
}

function app_delete_user(PDO $pdo, int $actorUserId, int $targetUserId): bool
{
    if ($actorUserId <= 0 || $targetUserId <= 0) {
        return false;
    }

    if (app_user_role($pdo, $actorUserId) !== 'admin') {
        return false;
    }

    if ($actorUserId === $targetUserId) {
        return false;
    }

    $targetUser = app_find_user_by_id($pdo, $targetUserId);
    if (!$targetUser) {
        return false;
    }

    if (($targetUser['role'] ?? '') === 'admin') {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            return false;
        }
    }

    $statement = $pdo->prepare('DELETE FROM `users` WHERE `id` = :id LIMIT 1');

    return $statement->execute(['id' => $targetUserId]);
}