<?php
session_start();
require('vendor/autoload.php');
require('sqlconnect.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$request_type = $_SERVER['REQUEST_METHOD'];
parse_str(file_get_contents('php://input'), $_queries);

$email = isset($_queries['email']) ? strtolower(stripslashes($_queries['email'])) : null;
$password = isset($_queries['password']) ? mysqli_real_escape_string($conn, $_queries['password']) : null;
$password_retype = $_queries['password-retype'] ?? null;
$logout = $_queries['logout'] ?? false;

$enable_registration = filter_var($_ENV['USER_REGISTRATION'], FILTER_VALIDATE_BOOLEAN);

function check_input()
{
    global $email, $password;

    if (!$email || !$password) {
        http_response_code(400);
        die('Please enter your e-mail and password!');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        die('Please enter a valid e-mail address!');
    }
}

function check_loggedin()
{
    global $conn, $email;
    $check_if_exist = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email';");

    if (isset($_SESSION['loggedin']) && mysqli_fetch_assoc($check_if_exist) != $_SESSION['loggedin']) {
        http_response_code(405);
        die('Request method not allowed!');
    };
}

switch ($request_type) {
    case 'POST':
        // used for login/logout auth process

        if ($logout) {
            $_SESSION = [];
            session_unset();
            session_destroy();

            exit('You\'ve successfully signed out.');
        }

        if (isset($_SESSION['loggedin'])) {
            http_response_code(400);
            die('You\'ve already logged in!');
        }

        check_input();

        $check_if_exist = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email';");
        $row = mysqli_fetch_assoc($check_if_exist);

        if ($row) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['loggedin'] = $email;
                exit('Welcome!');
            }
        }

        http_response_code(400);
        die('Wrong e-mail or password!');
        break;

    case 'PUT':
        // used for user registration

        check_loggedin();
        check_input();

        if (!$enable_registration) {
            http_response_code(400);
            die('User registration is disabled!');
        }

        $check_if_exist = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email';");
        $password = password_hash($password, PASSWORD_BCRYPT);

        if (!mysqli_fetch_assoc($check_if_exist)) {
            $query = "INSERT INTO users VALUES(null, '$email', '$password');";
            mysqli_query($conn, $query);
            exit('New user successfully created!');
        }

        http_response_code(400);
        die('E-mail already exist!');
        break;

    case 'PATCH':
        // used for user update

        check_loggedin();
        check_input();

        if ($password != $password_retype) {
            http_response_code(400);
            die('Please check and retype your password!');
        }

        $check_if_exist = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email';");
        $password = password_hash($password, PASSWORD_BCRYPT);
        $row = mysqli_fetch_assoc($check_if_exist);

        if ($row) {
            $id = $row['id'];
            $query = "UPDATE users SET email = '$email', password = '$password' WHERE id = '$id';";

            mysqli_query($conn, $query);
            exit('User info updated!');
        }

        http_response_code(400);
        die('User not found!');
        break;

    case 'DELETE':
        // used for delete a user

        check_loggedin();

        $check_if_exist = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email';");

        if (mysqli_fetch_assoc($check_if_exist)) {
            $query = "DELETE FROM users WHERE email = '$email';";
            mysqli_query($conn, $query);
            exit('User deleted successfully!');
        } else {
            http_response_code(400);
            die('User not found!');
        }

        break;

    default:
        http_response_code(405);
        die('Request method not allowed!');
}
