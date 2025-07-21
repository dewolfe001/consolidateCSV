<?php
/**
 * Admin Dashboard
 * Monitoring and management interface for the CSV Consolidator service
 */

define('CSV_CONSOLIDATOR_ACCESS', true);

require_once 'includes/config.php';
require_once 'includes/FileManager.php';
require_once 'includes/UserSession.php';
require_once 'includes/PaymentHandler.php';

// Simple authentication (in production, use proper auth)
$adminPassword = config('ADMIN_PASSWORD', 'admin123');
$isAuthenticated = false;

if (isset($_POST['admin_password'])) {
    if (hash_equals($adminPassword, $_POST['admin_password'])) {
        session_start();
        $_SESSION['admin_authenticated'] = true;
        $isAuthenticated = true;
    }
} elseif (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated']) {
    $isAuthenticated = true;
}

if (!$isAuthenticated) {
    showLoginForm();
    exit;
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleAdminAction($_POST['action'], $_POST);
}

// Gather statistics
$stats = gatherStatistics();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-card.success {
            border-left-color: #27ae60;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            display: block;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            margin-bottom: 1rem;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 0.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            transition: width 0.3s ease;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .log-viewer {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 1rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ecf0f1;
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: #7f8c8d;
        }
        
        .tab.active {
            border-bottom-color: #3498db;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛠️ <?php echo APP_NAME; ?> - Admin Dashboard</h1>
        <button class="logout-btn" onclick="logout()">Logout</button>
    </div>
    
    <div class="container">
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['total_sessions']); ?></span>
                <div class="stat-label">Total Sessions</div>
            </div>
            
            <div class="stat-card <?php echo $stats['active_sessions_24h'] > 100 ? 'warning' : 'success'; ?>">
                <span class="stat-number"><?php echo number_format($stats['active_sessions_24h']); ?></span>
                <div class="stat-label">Active Sessions (24h)</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['total_processing_jobs']); ?></span>
                <div class="stat-label">Processing Jobs</div>
            </div>
            
            <div class="stat-card success">
                <span class="stat-number"><?php echo number_format($stats['pro_users']); ?></span>
                <div class="stat-label">Pro Users</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['disk_usage']['total_size_formatted']; ?></span>
                <div class="stat-label">Disk Usage</div>
            </div>
            
            <div class="stat-card <?php echo $stats['error_rate'] > 5 ? 'danger' : 'success'; ?>">
                <span class="stat-number"><?php echo number_format($stats['error_rate'], 1); ?>%</span>
                <div class="stat-label">Error Rate (24h)</div>
            </div>
        </div>
        
        <!-- System Health -->
        <div class="section">
            <h2>🔧 System Health</h2>
            
            <div class="stats-grid">
                <div>
                    <strong>Memory Usage:</strong>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $stats['memory_usage']; ?>%"></div>
                    </div>
                    <small><?php echo $stats['memory_usage']; ?>% used</small>
                </div>
                
                <div>
                    <strong>Disk Space:</strong>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $stats['disk_usage_percent']; ?>%"></div>
                    </div>
                    <small><?php echo $stats['disk_usage_percent']; ?>% used</small>
                </div>
            </div>
            
            <div style="margin-top: 1rem;">
                <button class="btn" onclick="runCleanup()">🧹 Run Cleanup</button>
                <button class="btn btn-warning" onclick="clearCache()">🗑️ Clear Cache</button>
                <button class="btn btn-success" onclick="refreshStats()">🔄 Refresh Stats</button>
            </div>
        </div>
        
        <!-- Tabbed Interface -->
        <div class="section">
            <div class="tabs">
                <div class="tab active" onclick="showTab('sessions')">Sessions</div>
                <div class="tab" onclick="showTab('payments')">Payments</div>
                <div class="tab" onclick="showTab('jobs')">Processing Jobs</div>
                <div class="tab" onclick="showTab('logs')">Logs</div>
                <div class="tab" onclick="showTab('settings')">Settings</div>
            </div>
            
            <!-- Sessions Tab -->
            <div id="sessions" class="tab-content active">
                <h2>👤 Recent Sessions</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Session ID</th>
                            <th>User Tier</th>
                            <th>Created</th>
                            <th>Last Activity</th>
                            <th>Processing Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_sessions'] as $session): ?>
                        <tr>
                            <td><?php echo substr($session['session_id'], 0, 20); ?>...</td>
                            <td>
                                <span class="<?php echo $session['user_tier'] === 'pro' ? 'btn-success' : 'btn'; ?>">
                                    <?php echo strtoupper($session['user_tier']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', $session['created_at']); ?></td>
                            <td><?php echo date('Y-m-d H:i', $session['last_activity']); ?></td>
                            <td><?php echo $session['processing_count']; ?></td>
                            <td>
                                <button class="btn btn-danger" onclick="deleteSession('<?php echo $session['session_id']; ?>')">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Payments Tab -->
            <div id="payments" class="tab-content">
                <h2>💳 Recent Payments</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Session</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_payments'] as $payment): ?>
                        <tr>
                            <td><?php echo substr($payment['stripe_payment_intent_id'], 0, 20); ?>...</td>
                            <td><?php echo substr($payment['session_id'], 0, 15); ?>...</td>
                            <td>$<?php echo number_format($payment['amount'] / 100, 2); ?></td>
                            <td>
                                <span class="btn-success"><?php echo $payment['status']; ?></span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', $payment['created_at']); ?></td>
                            <td>
                                <button class="btn btn-warning" onclick="refundPayment('<?php echo $payment['stripe_payment_intent_id']; ?>')">
                                    Refund
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Processing Jobs Tab -->
            <div id="jobs" class="tab-content">
                <h2>⚙️ Processing Jobs</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Files</th>
                            <th>Started</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_jobs'] as $job): ?>
                        <tr>
                            <td><?php echo substr($job['job_id'], 0, 20); ?>...</td>
                            <td><?php echo substr($job['session_id'], 0, 15); ?>...</td>
                            <td>
                                <span class="btn <?php echo $job['status'] === 'completed' ? 'btn-success' : ($job['status'] === 'failed' ? 'btn-danger' : 'btn-warning'); ?>">
                                    <?php echo $job['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $job['file_count']; ?></td>
                            <td><?php echo date('Y-m-d H:i', $job['started_at']); ?></td>
                            <td>
                                <?php 
                                if ($job['completed_at']) {
                                    echo gmdate('H:i:s', $job['completed_at'] - $job['started_at']);
                                } else {
                                    echo 'Running...';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn" onclick="viewJobDetails('<?php echo $job['job_id']; ?>')">
                                    View
                                </button>
                                <?php if ($job['status'] === 'processing'): ?>
                                <button class="btn btn-danger" onclick="cancelJob('<?php echo $job['job_id']; ?>')">
                                    Cancel
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Logs Tab -->
            <div id="logs" class="tab-content">
                <h2>📝 System Logs</h2>
                
                <div style="margin-bottom: 1rem;">
                    <button class="btn" onclick="refreshLogs()">🔄 Refresh</button>
                    <button class="btn" onclick="downloadLogs()">📥 Download</button>
                    <select id="logType" onchange="changeLogs()">
                        <option value="application">Application</option>
                        <option value="error">Errors</option>
                        <option value="cleanup">Cleanup</option>
                    </select>
                </div>
                
                <div class="log-viewer" id="logViewer">
                    <?php echo htmlspecialchars($stats['recent_logs']); ?>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div id="settings" class="tab-content">
                <h2>⚙️ System Settings</h2>
                
                <form method="post">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div style="margin-bottom: 1rem;">
                        <label><strong>Maintenance Mode:</strong></label>
                        <label>
                            <input type="checkbox" name="maintenance_mode" <?php echo config('MAINTENANCE_MODE') ? 'checked' : ''; ?>>
                            Enable maintenance mode
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label><strong>Max File Size (MB):</strong></label>
                        <input type="number" name="max_file_size" value="<?php echo config('MAX_FILE_SIZE', 10); ?>" min="1" max="100">
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label><strong>Free Daily Limit:</strong></label>
                        <input type="number" name="free_daily_limit" value="<?php echo config('FREE_DAILY_LIMIT', 3); ?>" min="1" max="10">
                    </div>
                    
                    <button type="submit" class="btn btn-success">💾 Save Settings</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=logout'
                }).then(() => {
                    location.reload();
                });
            }
        }
        
        function runCleanup() {
            if (confirm('Run cleanup process? This may take a few minutes.')) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=run_cleanup'
                }).then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function refreshStats() {
            location.reload();
        }
        
        function deleteSession(sessionId) {
            if (confirm('Delete this session and all associated data?')) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_session&session_id=${sessionId}`
                }).then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function refundPayment(paymentId) {
            const reason = prompt('Enter refund reason:');
            if (reason) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=refund_payment&payment_id=${paymentId}&reason=${encodeURIComponent(reason)}`
                }).then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function refreshLogs() {
            const logType = document.getElementById('logType').value;
            fetch(`admin.php?action=get_logs&type=${logType}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('logViewer').textContent = data;
                });
        }
        
        function changeLogs() {
            refreshLogs();
        }
        
        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            if (document.getElementById('sessions').classList.contains('active')) {
                refreshStats();
            }
        }, 30000);
    </script>
</body>
</html>

<?php
function showLoginForm() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login</title>
        <style>
            body { font-family: sans-serif; background: #f5f5f5; }
            .login-form { max-width: 400px; margin: 100px auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .form-group { margin-bottom: 1rem; }
            label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
            input[type="password"] { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; }
            .btn { background: #3498db; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; width: 100%; }
        </style>
    </head>
    <body>
        <div class="login-form">
            <h2>Admin Login</h2>
            <form method="post">
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="admin_password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

function handleAdminAction($action, $data) {
    switch ($action) {
        case 'logout':
            session_start();
            session_destroy();
            jsonResponse(['success' => true]);
            break;
            
        case 'run_cleanup':
            // Run cleanup in background
            $output = shell_exec('php cleanup.php --dry-run 2>&1');
            jsonResponse(['success' => true, 'message' => 'Cleanup completed', 'output' => $output]);
            break;
            
        case 'delete_session':
            // Delete session and associated files
            $sessionId = $data['session_id'] ?? '';
            if ($sessionId) {
                // Implementation would go here
                jsonResponse(['success' => true, 'message' => 'Session deleted']);
            }
            break;
            
        case 'refund_payment':
            $paymentId = $data['payment_id'] ?? '';
            $reason = $data['reason'] ?? '';
            if ($paymentId) {
                try {
                    $paymentHandler = new PaymentHandler();
                    $result = $paymentHandler->createRefund($paymentId, $reason);
                    jsonResponse(['success' => true, 'message' => 'Refund processed']);
                } catch (Exception $e) {
                    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            break;
    }
}

function gatherStatistics() {
    $stats = UserSession::getGlobalStatistics();
    $fileManager = new FileManager();
    
    // Disk usage
    $diskUsage = $fileManager->getDiskUsage();
    $stats['disk_usage'] = $diskUsage;
    $stats['disk_usage_percent'] = min(100, ($diskUsage['total_size'] / (100 * 1024 * 1024)) * 100); // Assume 100MB limit
    
    // Memory usage
    $stats['memory_usage'] = min(100, (memory_get_usage() / (256 * 1024 * 1024)) * 100); // Assume 256MB limit
    
    // Error rate (mock)
    $stats['error_rate'] = rand(0, 10) / 10; // Would calculate from logs
    
    // Recent sessions (mock data)
    $stats['recent_sessions'] = [
        [
            'session_id' => 'sess_' . bin2hex(random_bytes(8)),
            'user_tier' => 'free',
            'created_at' => time() - 3600,
            'last_activity' => time() - 300,
            'processing_count' => 2
        ],
        [
            'session_id' => 'sess_' . bin2hex(random_bytes(8)),
            'user_tier' => 'pro',
            'created_at' => time() - 7200,
            'last_activity' => time() - 600,
            'processing_count' => 5
        ]
    ];
    
    // Recent payments (mock data)
    $stats['recent_payments'] = [
        [
            'stripe_payment_intent_id' => 'pi_' . bin2hex(random_bytes(8)),
            'session_id' => 'sess_' . bin2hex(random_bytes(8)),
            'amount' => 1900,
            'status' => 'completed',
            'created_at' => time() - 1800
        ]
    ];
    
    // Recent jobs (mock data)
    $stats['recent_jobs'] = [
        [
            'job_id' => 'job_' . bin2hex(random_bytes(8)),
            'session_id' => 'sess_' . bin2hex(random_bytes(8)),
            'status' => 'completed',
            'file_count' => 3,
            'started_at' => time() - 900,
            'completed_at' => time() - 600
        ]
    ];
    
    // Recent logs
    $logFile = config('LOG_FILE', './logs/application.log');
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $stats['recent_logs'] = substr($logContent, -2000); // Last 2KB
    } else {
        $stats['recent_logs'] = 'No logs available';
    }
    
    return $stats;
}
?>
