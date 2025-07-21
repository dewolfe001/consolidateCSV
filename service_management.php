<?php
/**
 * Service Management Script
 * Manages the CSV Consolidator service lifecycle
 * 
 * Usage: php service.php [command] [options]
 * Commands: start, stop, restart, status, health, backup, restore
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

define('CSV_CONSOLIDATOR_ACCESS', true);

require_once 'includes/config.php';
require_once 'includes/FileManager.php';
require_once 'includes/UserSession.php';

class ServiceManager {
    
    private $pidFile = './data/service.pid';
    private $commands = [
        'start' => 'Start the background services',
        'stop' => 'Stop the background services',
        'restart' => 'Restart the background services',
        'status' => 'Show service status',
        'health' => 'Run health check',
        'backup' => 'Create backup of data',
        'restore' => 'Restore from backup',
        'monitor' => 'Monitor service in real-time',
        'logs' => 'Show recent logs',
        'help' => 'Show this help message'
    ];
    
    public function run($argv) {
        $command = $argv[1] ?? 'help';
        $options = array_slice($argv, 2);
        
        if (!isset($this->commands[$command])) {
            $this->showHelp();
            exit(1);
        }
        
        $method = 'command' . ucfirst($command);
        if (method_exists($this, $method)) {
            $this->$method($options);
        } else {
            echo "Command not implemented: {$command}\n";
            exit(1);
        }
    }
    
    private function commandStart($options) {
        echo "🚀 Starting CSV Consolidator services...\n";
        
        if ($this->isRunning()) {
            echo "❌ Services already running (PID: " . $this->getPid() . ")\n";
            exit(1);
        }
        
        // Start background worker
        $workerPid = $this->startWorker();
        
        // Start scheduler
        $schedulerPid = $this->startScheduler();
        
        // Save main PID
        file_put_contents($this->pidFile, $workerPid);
        
        echo "✅ Services started successfully\n";
        echo "   Worker PID: {$workerPid}\n";
        echo "   Scheduler PID: {$schedulerPid}\n";
        
        $this->logMessage("Services started - Worker: {$workerPid}, Scheduler: {$schedulerPid}");
    }
    
    private function commandStop($options) {
        echo "🛑 Stopping CSV Consolidator services...\n";
        
        if (!$this->isRunning()) {
            echo "❌ Services not running\n";
            exit(1);
        }
        
        $pid = $this->getPid();
        
        // Stop processes gracefully
        $this->stopProcess($pid);
        $this->stopAllWorkers();
        
        // Remove PID file
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
        
        echo "✅ Services stopped successfully\n";
        $this->logMessage("Services stopped");
    }
    
    private function commandRestart($options) {
        echo "🔄 Restarting CSV Consolidator services...\n";
        
        if ($this->isRunning()) {
            $this->commandStop([]);
            sleep(2);
        }
        
        $this->commandStart([]);
    }
    
    private function commandStatus($options) {
        echo "📊 CSV Consolidator Service Status\n";
        echo str_repeat("=", 40) . "\n";
        
        // Service status
        if ($this->isRunning()) {
            $pid = $this->getPid();
            echo "🟢 Status: Running (PID: {$pid})\n";
            
            // Check if process is actually alive
            if ($this->isProcessAlive($pid)) {
                echo "🟢 Process: Healthy\n";
            } else {
                echo "🔴 Process: Dead (stale PID file)\n";
                unlink($this->pidFile);
            }
        } else {
            echo "🔴 Status: Stopped\n";
        }
        
        // System resources
        $memoryUsage = memory_get_peak_usage(true);
        echo "💾 Memory: " . formatFileSize($memoryUsage) . "\n";
        
        // Disk usage
        $fileManager = new FileManager();
        $diskUsage = $fileManager->getDiskUsage();
        echo "💽 Disk: " . $diskUsage['total_size_formatted'] . " (" . $diskUsage['file_count'] . " files)\n";
        
        // Active sessions
        $stats = UserSession::getGlobalStatistics();
        echo "👥 Sessions: {$stats['total_sessions']} total, {$stats['active_sessions_24h']} active (24h)\n";
        echo "💳 Users: {$stats['free_users']} free, {$stats['pro_users']} pro\n";
        echo "⚙️  Jobs: {$stats['total_processing_jobs']} processed\n";
        
        // Recent errors
        $errorCount = $this->getRecentErrorCount();
        if ($errorCount > 0) {
            echo "⚠️  Errors: {$errorCount} in last 24h\n";
        } else {
            echo "✅ Errors: None in last 24h\n";
        }
        
        echo "\nLast updated: " . date('Y-m-d H:i:s') . "\n";
    }
    
    private function commandHealth($options) {
        echo "🏥 Running health check...\n";
        
        $checks = [
            'Service Running' => $this->isRunning(),
            'Database Accessible' => $this->checkDatabase(),
            'Temp Directory Writable' => is_writable(TEMP_PATH),
            'Data Directory Writable' => is_writable(DATA_PATH),
            'Log Directory Writable' => is_writable(LOG_PATH),
            'Disk Space Available' => $this->checkDiskSpace(),
            'Memory Available' => $this->checkMemory(),
            'AI APIs Accessible' => $this->checkAIAPIs(),
            'Stripe API Accessible' => $this->checkStripe()
        ];
        
        $allHealthy = true;
        
        foreach ($checks as $check => $status) {
            $icon = $status ? '✅' : '❌';
            echo "   {$icon} {$check}\n";
            
            if (!$status) {
                $allHealthy = false;
            }
        }
        
        echo "\n";
        
        if ($allHealthy) {
            echo "🎉 All health checks passed!\n";
            exit(0);
        } else {
            echo "⚠️  Some health checks failed. Please review the issues above.\n";
            exit(1);
        }
    }
    
    private function commandBackup($options) {
        $backupDir = $options[0] ?? './backups';
        
        echo "💾 Creating backup...\n";
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "{$backupDir}/csv_consolidator_backup_{$timestamp}.tar.gz";
        
        // Create backup
        $command = "tar -czf " . escapeshellarg($backupFile) . " data/ logs/ .env";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $fileSize = formatFileSize(filesize($backupFile));
            echo "✅ Backup created: {$backupFile} ({$fileSize})\n";
            
            $this->logMessage("Backup created: {$backupFile}");
        } else {
            echo "❌ Backup failed\n";
            exit(1);
        }
    }
    
    private function commandRestore($options) {
        $backupFile = $options[0] ?? null;
        
        if (!$backupFile || !file_exists($backupFile)) {
            echo "❌ Backup file not found: {$backupFile}\n";
            echo "Usage: php service.php restore /path/to/backup.tar.gz\n";
            exit(1);
        }
        
        echo "🔄 Restoring from backup...\n";
        echo "⚠️  This will overwrite existing data. Continue? (y/N): ";
        
        $handle = fopen("php://stdin", "r");
        $confirm = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($confirm) !== 'y') {
            echo "Restore cancelled.\n";
            exit(0);
        }
        
        // Stop services
        if ($this->isRunning()) {
            $this->commandStop([]);
        }
        
        // Extract backup
        $command = "tar -xzf " . escapeshellarg($backupFile);
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✅ Backup restored successfully\n";
            echo "🚀 Starting services...\n";
            
            $this->commandStart([]);
            $this->logMessage("Restored from backup: {$backupFile}");
        } else {
            echo "❌ Restore failed\n";
            exit(1);
        }
    }
    
    private function commandMonitor($options) {
        echo "👀 Monitoring CSV Consolidator service (Press Ctrl+C to stop)\n";
        echo str_repeat("=", 60) . "\n";
        
        while (true) {
            // Clear screen
            system('clear');
            
            echo "CSV Consolidator Monitor - " . date('Y-m-d H:i:s') . "\n";
            echo str_repeat("=", 60) . "\n";
            
            $this->commandStatus([]);
            
            echo "\nPress Ctrl+C to stop monitoring...\n";
            
            sleep(5);
        }
    }
    
    private function commandLogs($options) {
        $lines = intval($options[0] ?? 50);
        $logType = $options[1] ?? 'application';
        
        $logFiles = [
            'application' => config('LOG_FILE', './logs/application.log'),
            'error' => config('ERROR_LOG_FILE', './logs/errors.log'),
            'cleanup' => TEMP_PATH . '/logs/cleanup.log'
        ];
        
        $logFile = $logFiles[$logType] ?? $logFiles['application'];
        
        if (!file_exists($logFile)) {
            echo "❌ Log file not found: {$logFile}\n";
            exit(1);
        }
        
        echo "📝 Showing last {$lines} lines from {$logType} log:\n";
        echo str_repeat("=", 60) . "\n";
        
        $command = "tail -n {$lines} " . escapeshellarg($logFile);
        passthru($command);
    }
    
    private function commandHelp($options) {
        echo "CSV Consolidator Service Manager\n";
        echo str_repeat("=", 40) . "\n\n";
        
        echo "Usage: php service.php [command] [options]\n\n";
        
        echo "Available commands:\n";
        foreach ($this->commands as $command => $description) {
            echo sprintf("  %-12s %s\n", $command, $description);
        }
        
        echo "\nExamples:\n";
        echo "  php service.php start\n";
        echo "  php service.php status\n";
        echo "  php service.php backup ./my-backups\n";
        echo "  php service.php logs 100 error\n\n";
    }
    
    // Helper methods
    
    private function isRunning() {
        return file_exists($this->pidFile) && $this->isProcessAlive($this->getPid());
    }
    
    private function getPid() {
        if (!file_exists($this->pidFile)) {
            return null;
        }
        
        return intval(file_get_contents($this->pidFile));
    }
    
    private function isProcessAlive($pid) {
        if (!$pid) return false;
        
        return posix_kill($pid, 0);
    }
    
    private function startWorker() {
        $command = "php " . __DIR__ . "/worker.php > /dev/null 2>&1 & echo $!";
        return intval(trim(shell_exec($command)));
    }
    
    private function startScheduler() {
        $command = "php " . __DIR__ . "/scheduler.php > /dev/null 2>&1 & echo $!";
        return intval(trim(shell_exec($command)));
    }
    
    private function stopProcess($pid) {
        if ($this->isProcessAlive($pid)) {
            posix_kill($pid, SIGTERM);
            
            // Wait for graceful shutdown
            $attempts = 0;
            while ($this->isProcessAlive($pid) && $attempts < 10) {
                sleep(1);
                $attempts++;
            }
            
            // Force kill if still running
            if ($this->isProcessAlive($pid)) {
                posix_kill($pid, SIGKILL);
            }
        }
    }
    
    private function stopAllWorkers() {
        // Find and stop all related processes
        $command = "pgrep -f 'process_job.php|worker.php|scheduler.php'";
        $pids = array_filter(explode("\n", shell_exec($command)));
        
        foreach ($pids as $pid) {
            $this->stopProcess(intval($pid));
        }
    }
    
    private function checkDatabase() {
        try {
            $dbPath = config('DATABASE_PATH', './data/sessions.db');
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkDiskSpace() {
        $freeBytes = disk_free_space('.');
        $totalBytes = disk_total_space('.');
        
        if ($freeBytes === false || $totalBytes === false) {
            return false;
        }
        
        $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        return $usagePercent < 90; // Less than 90% full
    }
    
    private function checkMemory() {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseBytes($memoryLimit);
        $currentUsage = memory_get_usage(true);
        
        $usagePercent = ($currentUsage / $memoryLimitBytes) * 100;
        return $usagePercent < 80; // Less than 80% used
    }
    
    private function checkAIAPIs() {
        $provider = config('AI_PROVIDER', 'openai');
        
        try {
            if ($provider === 'openai') {
                $apiKey = config('OPENAI_API_KEY');
                if (empty($apiKey) || strpos($apiKey, 'your_') === 0) {
                    return false;
                }
                
                // Simple API check
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/models');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $apiKey
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                return $httpCode === 200;
            }
            
            return true; // Other providers not checked
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkStripe() {
        try {
            $secretKey = config('STRIPE_SECRET_KEY');
            if (empty($secretKey) || strpos($secretKey, 'your_') === 0) {
                return false;
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/account');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getRecentErrorCount() {
        $errorLog = config('ERROR_LOG_FILE', './logs/errors.log');
        
        if (!file_exists($errorLog)) {
            return 0;
        }
        
        $yesterday = time() - 86400;
        $command = "grep '" . date('Y-m-d', $yesterday) . "\\|" . date('Y-m-d') . "' " . escapeshellarg($errorLog) . " | wc -l";
        
        return intval(trim(shell_exec($command)));
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
    
    private function logMessage($message) {
        appLog("[SERVICE] " . $message);
    }
}

// Main execution
try {
    $manager = new ServiceManager();
    $manager->run($argv);
} catch (Exception $e) {
    echo "Service management error: " . $e->getMessage() . "\n";
    exit(1);
}
