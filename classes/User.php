<?php
class User {
    protected int $id;
    protected string $name;
    protected string $email;
    protected string $password;
    protected string $role;
    protected string $status;
    protected bool $isValidated;

    public function __construct(int $id, string $name, string $email, string $password, string $role, string $status, bool $isValidated) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->status = $status;
        $this->isValidated = $isValidated;
    }

    private static function register(PDO $dbConnection, string $name, string $email, string $password, string $role, bool $isValidated): bool {
        try {
            $query = "INSERT INTO Users (name, email, password, role, status, is_validated) VALUES (:name, :email, :password, :role, 'suspended', :isValidated)";
            $stmt = $dbConnection->prepare($query);
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            return $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':role' => $role,
                ':isValidated' => (int)$isValidated
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("The email '$email' is already registered.", 409);
            }
            throw new Exception("An unexpected error occurred: " . $e->getMessage(), 500);
        }
    }

    public static function registerUser(PDO $dbConnection, string $name, string $email, string $password, string $role, bool $isValidated): bool {
        return self::register($dbConnection, $name, $email, $password, $role, $isValidated);
    }

    public static function viewCourses(PDO $dbConnection, int $limit, int $offset): array {
        $query = "SELECT * FROM Courses LIMIT :limit OFFSET :offset";
        $stmt = $dbConnection->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function searchCourses(PDO $dbConnection, string $keyword): array {
        $query = "SELECT * FROM Courses WHERE title LIKE :keyword OR description LIKE :keyword";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function login(PDO $dbConnection, string $email, string $password): bool {
        $query = "SELECT * FROM Users WHERE email = :email";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }
        return false;
    }

    public static function logout(): void {
        session_start();
        session_unset();
        session_destroy();
    }

    public function getRole(): string {
        return $this->role;
    }
}
