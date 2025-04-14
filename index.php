<?php

    ini_set('display_startup_errors', 1);
    ini_set('display_errors', 1);
    error_reporting(-1);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    define('PATH', '/' . trim(strtok($_SERVER["REQUEST_URI"], '?'), '/'));
    define('PATH_ARRAY', [...array_filter(explode('/', PATH))]);

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $host = $_ENV['DB_HOST'];
    $db_name = $_ENV['DB_NAME'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // You can use $pdo now
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }


    require_once __DIR__ . '/vendor/autoload.php';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8",$user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    function postvar(string|int $var): mixed
    {
        return $_POST[$var] ?? false;
    }

    function getvar(string|int $var): mixed
    {
        return $_GET[$var] ?? false;
    }

    define('IS_LOGGED_IN', array_key_exists('user', $_SESSION) && array_key_exists('id', $_SESSION['user']));
    define('IS_ADMIN', IS_LOGGED_IN && $_SESSION['user']['type'] === 'admin');

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);

    $template = 'index.html';
    $render_data = [];
     
    // Fetch latest three news articles
    $stmt = $pdo->query("SELECT id, title, body FROM news ORDER BY created_at DESC LIMIT 3");
    $render_data['dailyNews'] = $stmt->fetchAll();

    // Handle AJAX request for updating complaint status
    if (PATH === '/ajax/update-complain' && IS_ADMIN && postvar('complaintId') && postvar('status')) {
        $complaintId = (int) postvar('complaintId');
        $newStatus = postvar('status');

        if (!in_array($newStatus, ['pending', 'in-process', 'resolved'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE complaints SET status = :status WHERE id = :id");
            $stmt->execute([
                ':status' => $newStatus,
                ':id' => $complaintId,
            ]);

            if ($stmt->rowCount() === 1) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes were made']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Normal form submissions and page routing
    $action = postvar("action");

    // Handle news creation (Admins only)
    if (IS_ADMIN && $action === 'create-news' && postvar('title') && postvar('body')) {

        $title = postvar('title');
        $body = postvar('body');
        $createdBy = $_SESSION['user']['id'];

        try {
            $stmt = $pdo->prepare("INSERT INTO news (title, body, created_by) VALUES (:title, :body, :created_by)");
            $stmt->execute([
                ':title' => $title,
                ':body' => $body,
                ':created_by' => $createdBy,
            ]);

            header("Location: /daily-news");
            die;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    } elseif (!IS_LOGGED_IN && 'signup' == $action && postvar('password') && postvar('mobile')) {
        $password = postvar('password');
        $mobile = postvar('mobile');
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT IGNORE INTO users (password, mobile) VALUES (:password, :mobile)");
        $stmt->execute([':password' => $hashed_password, ':mobile' => $mobile]);

        if ($stmt->rowCount() === 1) {
            $_SESSION['user'] = ['id' => $pdo->lastInsertId(), 'mobile' => $mobile];
            header("Location: " . (postvar('returnUrl') ?: "/"));
            die;
        } else {
            echo "Signup failed. User might already exist.";
        }
    } elseif (!IS_LOGGED_IN && 'login' == $action && postvar('mobile') && postvar('password')) {
        $password = postvar('password');
        $mobile = postvar('mobile');

        $stmt = $pdo->prepare("SELECT id, password, mobile, type FROM users WHERE mobile = :mobile");
        $stmt->execute([':mobile' => $mobile]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);

            $_SESSION['user'] = $user;
            header("Location: " . (postvar('returnUrl') ?: "/"));
            die;
        } else {
            echo "Login failed. Invalid username or password.";
        }
    } elseif (IS_LOGGED_IN && 'submit-complaint' == $action && postvar('complain_type') && postvar('description')) {

        $complain_type = postvar('complain_type');
        $description = postvar('description');
        $createdBy = $_SESSION['user']['id'];

        try {
            $stmt = $pdo->prepare("INSERT INTO complaints (complain_type, description, createdBy) VALUES (:complain_type, :description, :createdBy)");
            $stmt->execute([
                ':complain_type' => $complain_type,
                ':description' => $description,
                ':createdBy' => $createdBy,
            ]);

            header("Location: /thank-you");
            die;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    } elseif (PATH_ARRAY && array_key_exists(0, PATH_ARRAY)) {
        $navTo = PATH_ARRAY[0];

        if ('login' == $navTo) {
            $template = "login.html";
            $render_data['returnUrl'] = getvar('returnUrl') ?: "/";
            if (IS_LOGGED_IN) {
                header("Location: " . $render_data['returnUrl']);
                die;
            }
        } elseif ('signup' == $navTo) {
            $template = "signup.html";
            $render_data['returnUrl'] = getvar('returnUrl');
        } elseif ('new-complain' == $navTo) {
            $template = "new-complain.html";
        } elseif ('thank-you' == $navTo) {
            $template = "thank-you.html";
        } elseif ('logout' == $navTo) {
            session_destroy();
            header("Location: /");
            die;
        } elseif ('complaints' == $navTo && IS_LOGGED_IN) {
            $template = "track-complains.html";

            if (IS_ADMIN) {
                // Admin can see all complaints with user mobile numbers
                $stmt = $pdo->prepare("
                    SELECT c.*, u.mobile 
                    FROM complaints c 
                    JOIN users u ON c.createdBy = u.id 
                    ORDER BY c.createdAt DESC
                ");
            } else {
                // Regular users see only their complaints
                $stmt = $pdo->prepare("
                    SELECT * FROM complaints 
                    WHERE createdBy = :user_id 
                    ORDER BY createdAt DESC
                ");
                $stmt->bindParam(':user_id', $_SESSION['user']['id']);
            }

            $stmt->execute();
            $render_data['complaints'] = $stmt->fetchAll();
        } elseif ('daily-news' == $navTo) {

            if (isset(PATH_ARRAY[1]) && is_numeric(PATH_ARRAY[1])) {
                // Show individual news
                $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id");
                $stmt->execute([':id' => (int) PATH_ARRAY[1]]);
                $news = $stmt->fetch();

                if (!$news) {
                    die("News not found");
                }

                $template = "single-news.html";
                $render_data['news'] = $news;
            } elseif (isset(PATH_ARRAY[1]) && PATH_ARRAY[1] === 'create' && IS_ADMIN) {
                // Show create news form
                $template = "create-news.html";
            } else {
                // Show all news
                $stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
                $render_data['newsList'] = $stmt->fetchAll();
                $template = "daily-news.html";
            }

        }

    }

    $render_data['isLoggedIn'] = IS_LOGGED_IN;
    $render_data['isAdmin'] = IS_ADMIN;

    if (IS_LOGGED_IN) {
        $render_data['user'] = $_SESSION['user'];
    }

    $twig->display($template, $render_data);
