<?php
/**
 * Generic Google OAuth 2.0 Authentication Handler
 * 
 * This file provides a complete Google OAuth 2.0 implementation that can be
 * easily integrated into any PHP application. It handles both the initial
 * authorization request and the callback with user data processing.
 * 
 * Features:
 * - OAuth 2.0 state parameter for security
 * - Automatic user creation/login
 * - Profile picture and email handling
 * - Generic database schema support
 * 
 * @author Ciprian Popescu
 * @version 1.0.0
 * @license MIT
 */

// ============================================================================
// CONFIGURATION - UPDATE THESE VALUES
// ============================================================================

// Google OAuth 2.0 Credentials (from Google Developer Console)
define('', '');
define('', '');

// Your application's callback URL
define('GOOGLE_REDIRECT_URI', 'http://localhost:8080/src/google-auth-generic.php');

// Database configuration (update with your database connection)
define('DB_HOST', null);
define('DB_NAME', null);
define('DB_USER', null);
define('DB_PASS', null);
define('DB_PATH', __DIR__ . '/../db/database.sqlite'); // For SQLite

// Application settings
define('APP_NAME', 'Authenticate Project');
define('APP_URL', 'http://localhost:8080');

// ============================================================================
// DATABASE SCHEMA (Generic - works with MySQL, SQLite, PostgreSQL)
// ============================================================================

/*
Required database table structure:

For SQLite, use:
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT,
    google_id TEXT,
    auth_provider TEXT DEFAULT 'local',
    profile_picture TEXT,
    profile_public INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
*/

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get database connection (update this function for your database system)
 * 
 * @return PDO Database connection
 */
function get_db_connection() {
    try {
               
        // For SQLite (uncomment and modify as needed)
        $pdo = new PDO("sqlite:" . DB_PATH);
        //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // For PostgreSQL (uncomment and modify as needed)
        // $pdo = new PDO("pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Convert text to URL-friendly slug
 * 
 * @param string $text Input text
 * @return string URL-friendly slug
 */
function slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ============================================================================
// MAIN AUTHENTICATION LOGIC
// ============================================================================

// Start session
session_start();

// Get the action from URL parameter
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'google_login':
            // Step 1: Generate OAuth state parameter for security
            $state = bin2hex(random_bytes(16));
            $_SESSION['google_oauth_state'] = $state;
            
            // Step 2: Build Google OAuth authorization URL
            $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id'     => GOOGLE_CLIENT_ID,
                'redirect_uri'  => GOOGLE_REDIRECT_URI,
                'response_type' => 'code',
                'scope'         => 'openid email profile',
                'access_type'   => 'online',
                'prompt'        => 'select_account',
                'state'         => $state,
            ]);
            
            // Step 3: Redirect user to Google
            header('Location: ' . $auth_url);
            exit;

        case 'google_callback':
            // Step 4: Verify OAuth state parameter
            if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
                echo 'Invalid state parameter. Possible CSRF attack.';
                echo '<br><br><a href="../public/index.html">Return to home page</a>';
                exit;
            }
            
            // Clear the state parameter
            unset($_SESSION['google_oauth_state']);
            
            // Step 5: Check for authorization code
            if (!isset($_GET['code'])) {
                echo 'Authorization failed. No code received from Google.';
                echo '<br><br><a href="../public/index.html">Return to home page</a>';
                exit;
            }
            
            $code = $_GET['code'];
            
            // Step 6: Exchange authorization code for access token
            $token_response = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => http_build_query([
                        'code'          => $code,
                        'client_id'     => GOOGLE_CLIENT_ID,
                        'client_secret' => GOOGLE_CLIENT_SECRET,
                        'redirect_uri'  => GOOGLE_REDIRECT_URI,
                        'grant_type'    => 'authorization_code',
                    ]),
                ],
            ]));
            
            $token_data = json_decode($token_response, true);
            $access_token = $token_data['access_token'] ?? null;
            
            if ($access_token) {
                // Step 7: Get user information from Google
                $user_info = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($access_token));
                $user = json_decode($user_info, true);
                
                if (isset($user['email'])) {
                    $pdo = get_db_connection();
                    
                    // Step 8: Check if user exists in database
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$user['email']]);
                    $existing_user = $stmt->fetch();
                    
                    if ($existing_user) {
                        // Step 9a: User exists - log them in
                        $_SESSION['user_id'] = $existing_user['id'];
                        $_SESSION['email'] = $existing_user['email'];
                        $_SESSION['username'] = $existing_user['username'];
                        
                        // Update Google ID if not set
                        if (empty($existing_user['google_id'])) {
                            $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                            $stmt->execute([$user['id'], $existing_user['id']]);
                        }
                    } else {
                        // Step 9b: User doesn't exist - create new account
                        $username = slugify($user['name'] ?? 'user');
                        
                        // Ensure username is unique
                        $original_username = $username;
                        $counter = 1;
                        while (true) {
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                            $stmt->execute([$username]);
                            if (!$stmt->fetch()) {
                                break;
                            }
                            $username = $original_username . $counter;
                            $counter++;
                        }
                        
                        // Insert new user
                        $stmt = $pdo->prepare("
                            INSERT INTO users (email, username, google_id, auth_provider, profile_picture, profile_public) 
                            VALUES (?, ?, ?, 'google', ?, 1)
                        ");
                        $stmt->execute([
                            $user['email'],
                            $username,
                            $user['id'],
                            $user['picture'] ?? null
                        ]);
                        
                        $user_id = $pdo->lastInsertId();
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['username'] = $username;
                    }
                    
                    // Step 10: Redirect to success page
                    header('Location: ../public/user_template.html');
                    exit;
                } else {
                    echo 'Failed to get user information from Google.';
                    echo '<br><br><a href="../public/index.html">Return to home page</a>';
                    exit;
                }
            } else {
                echo 'Failed to get access token from Google.';
                echo '<br><br><a href="../public/index.html">Return to home page</a>';
                exit;
            }
            break;
            
        default:
            echo 'Invalid action specified.';
            echo '<br><br><a href="../public/index.html">Return to home page</a>';
            exit;
    }
    
} catch (Exception $e) {
    echo 'Authentication error: ' . $e->getMessage();
    echo '<br><br><a href="../public/index.html">Return to home page</a>';
}