<?php
/**
 * Installation and Setup Script
 * Automated installation for the CSV Consolidator Web Service
 * 
 * Run this script once to set up the application
 * Usage: php install.php or visit /install.php in browser
 */

// Prevent running if already installed
if (file_exists('.env') && file_exists('data/installed.lock')) {
    die("Application already installed. Delete 'data/installed.lock' to reinstall.\n");
}

class Installer {
    
    private $errors = [];
    private $warnings = [];
    private $config = [];
    
    public function __construct() {
        $this->checkEnvironment();
    }
    
    public function run() {
        if (php_sapi_name() === 'cli') {
            $this->runCLI();
        } else {
            $this->runWeb();
        }
    }
    
    private function runCLI() {
        echo "🚀 CSV Consolidator Installation\n";
        echo str_repeat("=", 40) . "\n\n";
        
        // System checks
        $this->log("Checking system requirements...");
        $this->checkSystemRequirements();
        
        if (!empty($this->errors)) {
            $this->log("❌ Installation failed. Please fix the following errors:");
            foreach ($this->errors as $error) {
                $this->log("   - " . $error);
            }
            exit(1);
        }
        
        // Create directories
        $this->log("Creating directories...");
        $this->createDirectories();
        
        // Install dependencies
        $this->log("Installing dependencies...");
        $this->installDependencies();
        
        // Setup configuration
        $this->log("Setting up configuration...");
        $this->setupConfiguration();
        
        // Initialize database
        $this->log("Initializing database...");
        $this->initializeDatabase();
        
        // Set permissions
        $this->log("Setting permissions...");
        $this->setPermissions();
        
        // Create installation lock
        $this->createInstallationLock();
        
        $this->log("✅ Installation completed successfully!");
        $this->showNextSteps();
    }
    
    private function runWeb() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleWebInstallation();
            return;
        }
        
        $this->showWebInterface();
    }
    
    private function checkSystemRequirements() {
        // PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->errors[] = "PHP 7.4 or higher required. Current: " . PHP_VERSION;
        }
        
        // Required extensions
        $requiredExtensions = ['curl', 'json', 'pdo', 'sqlite3', 'mbstring'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->errors[] = "PHP extension '{$ext}' is required";
            }
        }
        
        // Composer
        if (!file_exists('vendor/autoload.php')) {
            $this->errors[] = "Composer dependencies not installed. Run 'composer install'";
        }
        
        // Write permissions
        $writableDirs = ['.', 'temp', 'data', 'logs'];
        foreach ($writableDirs as $dir) {
            if (!is_writable($dir)) {
                $this->errors[] = "Directory '{$dir}' must be writable";
            }
        }
        
        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        if ($this->parseBytes($memoryLimit) < 256 * 1024 * 1024) {
            $this->warnings[] = "Memory limit is low ({$memoryLimit}). Recommend 256M or higher";
        }
        
        // Upload limits
        $uploadLimit = ini_get('upload_max_filesize');
        if ($this->parseBytes($uploadLimit) < 10 * 1024 * 1024) {
            $this->warnings[] = "Upload limit is low ({$uploadLimit}). Recommend 10M or higher";
        }
    }
    
    private function createDirectories() {
        $directories = [
            'temp',
            'temp/uploads',
            'temp/processing',
            'temp/results',
            'temp/logs',
            'temp/rate_limits',
            'data',
            'data/sessions',
            'logs'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->errors[] = "Failed to create directory: {$dir}";
                }
            }
        }
        
        // Create .htaccess for temp directory
        $htaccess = "temp/.htaccess";
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all");
        }
    }
    
    private function installDependencies() {
        if (!file_exists('vendor/autoload.php')) {
            if (command_exists('composer')) {
                exec('composer install --no-dev --optimize-autoloader 2>&1', $output, $returnCode);
                if ($returnCode !== 0) {
                    $this->errors[] = "Composer install failed: " . implode("\n", $output);
                }
            } else {
                $this->errors[] = "Composer not found. Please install composer and run 'composer install'";
            }
        }
    }
    
    private function setupConfiguration() {
        if (!file_exists('.env')) {
            $this->createEnvFile();
        }
        
        // Load configuration for validation
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();
        }
    }
    
    private function createEnvFile() {
        $envTemplate = <<<ENV
# AI CSV Consolidator Configuration
# Generated by installation script on %s

# Application Settings
APP_NAME="AI CSV Consolidator"
APP_URL=http://localhost
APP_ENV=development
DEBUG=true

# AI API Configuration (REQUIRED - Add your keys)
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4
OPENAI_BASE_URL=https://api.openai.com/v1

ANTHROPIC_API_KEY=your_anthropic_api_key_here
ANTHROPIC_MODEL=claude-3-sonnet-20240229
ANTHROPIC_BASE_URL=https://api.anthropic.com

# Stripe Configuration (REQUIRED for payments)
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key_here
STRIPE_SECRET_KEY=sk_test_your_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# File Management
TEMP_DIRECTORY=./temp
SESSION_DIRECTORY=./data/sessions
DATABASE_PATH=./data/sessions.db
MAX_FILE_SIZE=10485760
ALLOWED_EXTENSIONS=csv
CLEANUP_AGE_HOURS=24
SESSION_MAX_AGE=2592000

# User Limits
FREE_DAILY_LIMIT=3
FREE_FILE_LIMIT=5
FREE_AI_CALLS=50
PRO_FILE_LIMIT=20
PRO_AI_CALLS=500

# AI Configuration
AI_PROVIDER=openai
SIMILARITY_THRESHOLD=0.85
ENABLE_AI_CONSOLIDATION=true
MAX_AI_CALLS_PER_RUN=100

# Security
SESSION_LIFETIME=86400
CSRF_TOKEN_LIFETIME=3600
ADMIN_PASSWORD=admin123

# Logging
LOG_LEVEL=info
LOG_FILE=./logs/application.log
ERROR_LOG_FILE=./logs/errors.log

# Maintenance
MAINTENANCE_MODE=false

ENV;
        
        $envContent = sprintf($envTemplate, date('Y-m-d H:i:s'));
        file_put_contents('.env', $envContent);
        
        $this->log("Created .env configuration file");
        $this->warnings[] = "Please edit .env file and add your API keys before using the application";
    }
    
    private function initializeDatabase() {
        try {
            require_once 'vendor/autoload.php';
            require_once 'includes/config.php';
            require_once 'includes/UserSession.php';
            
            // Initialize session will create the database
            $userSession = new UserSession();
            
            $this->log("Database initialized successfully");
            
        } catch (Exception $e) {
            $this->errors[] = "Database initialization failed: " . $e->getMessage();
        }
    }
    
    private function setPermissions() {
        $permissions = [
            'temp' => 0755,
            'data' => 0755,
            'logs' => 0755,
            'process_job.php' => 0755,
            'cleanup.php' => 0755
        ];
        
        foreach ($permissions as $path => $perm) {
            if (file_exists($path)) {
                chmod($path, $perm);
            }
        }
    }
    
    private function createInstallationLock() {
        $lockData = [
            'installed_at' => time(),
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'installer_version' => '1.0.0'
        ];
        
        file_put_contents('data/installed.lock', json_encode($lockData, JSON_PRETTY_PRINT));
    }
    
    private function showNextSteps() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🎉 INSTALLATION COMPLETE\n";
        echo str_repeat("=", 50) . "\n\n";
        
        echo "Next Steps:\n";
        echo "1. Edit .env file and add your API keys:\n";
        echo "   - OpenAI API key for AI processing\n";
        echo "   - Stripe keys for payment processing\n\n";
        
        echo "2. Configure your web server:\n";
        echo "   - Point document root to this directory\n";
        echo "   - Enable SSL for production use\n\n";
        
        echo "3. Set up automated maintenance:\n";
        echo "   - Add to crontab: 0 2 * * * php " . __DIR__ . "/cleanup.php\n\n";
        
        echo "4. Test the installation:\n";
        echo "   - Visit your website\n";
        echo "   - Try uploading a test CSV file\n\n";
        
        echo "5. Access admin dashboard:\n";
        echo "   - Visit /admin.php\n";
        echo "   - Default password: admin123 (change this!)\n\n";
        
        if (!empty($this->warnings)) {
            echo "⚠️  Warnings:\n";
            foreach ($this->warnings as $warning) {
                echo "   - " . $warning . "\n";
            }
            echo "\n";
        }
        
        echo "For support, visit: https://docs.csvconsolidator.com\n\n";
    }
    
    private function handleWebInstallation() {
        // Process form data and run installation
        $this->checkSystemRequirements();
        
        if (empty($this->errors)) {
            $this->createDirectories();
            $this->installDependencies();
            $this->setupConfiguration();
            $this->initializeDatabase();
            $this->setPermissions();
            $this->createInstallationLock();
            
            $this->showSuccessPage();
        } else {
            $this->showErrorPage();
        }
    }
    
    private function showWebInterface() {
        $this->checkSystemRequirements();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CSV Consolidator Installation</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .container { background: white; border-radius: 16px; padding: 40px; max-width: 600px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
                h1 { color: #2d3748; margin-bottom: 30px; text-align: center; }
                .status { padding: 15px; border-radius: 8px; margin: 15px 0; }
                .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
                .btn { background: #667eea; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 16px; width: 100%; margin-top: 20px; }
                .btn:hover { background: #5a6fd8; }
                .btn:disabled { opacity: 0.6; cursor: not-allowed; }
                ul { margin: 10px 0; padding-left: 20px; }
                .check-item { margin: 5px 0; }
                .check-pass { color: #28a745; }
                .check-fail { color: #dc3545; }
                .check-warn { color: #ffc107; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🚀 CSV Consolidator Installation</h1>
                
                <div class="status">
                    <h3>System Requirements Check</h3>
                    <ul>
                        <li class="check-item <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'check-pass' : 'check-fail'; ?>">
                            PHP 7.4+ (Current: <?php echo PHP_VERSION; ?>)
                        </li>
                        <?php
                        $extensions = ['curl', 'json', 'pdo', 'sqlite3', 'mbstring'];
                        foreach ($extensions as $ext) {
                            $loaded = extension_loaded($ext);
                            echo "<li class='check-item " . ($loaded ? 'check-pass' : 'check-fail') . "'>";
                            echo "PHP {$ext} extension " . ($loaded ? '✓' : '✗');
                            echo "</li>";
                        }
                        ?>
                        <li class="check-item <?php echo file_exists('vendor/autoload.php') ? 'check-pass' : 'check-fail'; ?>">
                            Composer dependencies <?php echo file_exists('vendor/autoload.php') ? '✓' : '✗'; ?>
                        </li>
                        <li class="check-item <?php echo is_writable('.') ? 'check-pass' : 'check-fail'; ?>">
                            Write permissions <?php echo is_writable('.') ? '✓' : '✗'; ?>
                        </li>
                    </ul>
                </div>
                
                <?php if (!empty($this->errors)): ?>
                <div class="status error">
                    <h3>❌ Errors Found</h3>
                    <ul>
                        <?php foreach ($this->errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($this->warnings)): ?>
                <div class="status warning">
                    <h3>⚠️ Warnings</h3>
                    <ul>
                        <?php foreach ($this->warnings as $warning): ?>
                        <li><?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (empty($this->errors)): ?>
                <div class="status success">
                    <h3>✅ Ready to Install</h3>
                    <p>All system requirements are met. Click the button below to proceed with installation.</p>
                </div>
                
                <form method="post">
                    <button type="submit" class="btn">Install CSV Consolidator</button>
                </form>
                <?php else: ?>
                <button class="btn" disabled>Fix Errors Before Installing</button>
                <?php endif; ?>
                
                <div style="margin-top: 30px; text-align: center; color: #666; font-size: 14px;">
                    <p>After installation, you'll need to configure your API keys in the .env file</p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    private function showSuccessPage() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Installation Complete</title>
            <style>
                body { font-family: sans-serif; background: #f0f8ff; text-align: center; padding: 50px; }
                .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
                .btn { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; margin: 10px; }
            </style>
        </head>
        <body>
            <div class="success">
                <h1>🎉 Installation Complete!</h1>
                <p>CSV Consolidator has been successfully installed.</p>
                <p>Please edit your .env file and add your API keys before using the application.</p>
                <a href="index.php" class="btn">Go to Application</a>
                <a href="admin.php" class="btn">Admin Dashboard</a>
            </div>
        </body>
        </html>
        <?php
    }
    
    private function showErrorPage() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Installation Failed</title>
            <style>
                body { font-family: sans-serif; background: #fff5f5; text-align: center; padding: 50px; }
                .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>❌ Installation Failed</h1>
                <p>Please fix the following errors and try again:</p>
                <ul style="text-align: left;">
                    <?php foreach ($this->errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="install.php">Try Again</a>
            </div>
        </body>
        </html>
        <?php
    }
    
    private function parseBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = intval($val);
        
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        
        return $val;
    }
    
    private function log($message) {
        if (php_sapi_name() === 'cli') {
            echo $message . "\n";
        }
    }
}

function command_exists($command) {
    $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
    return !empty($return);
}

// Run installer
try {
    $installer = new Installer();
    $installer->run();
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "Installation failed: " . $e->getMessage() . "\n";
    } else {
        echo "<h1>Installation Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    exit(1);
}
