<?php
require_once('../db.php');

class SessionManager {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Start or update user session
     */
    public function startSession($userId) {
        try {
            $sessionId = session_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
            
            // Check if user_sessions table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
            if ($tableCheck->num_rows > 0) {
                // Update or insert session
                $sql = "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at, session_data)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        last_activity = NOW(),
                        expires_at = ?,
                        is_active = 1";
                
                $stmt = $this->conn->prepare($sql);
                $sessionData = json_encode([
                    'login_time' => date('Y-m-d H:i:s'),
                    'ip_address' => $ipAddress
                ]);
                
                $stmt->bind_param("sissss", $sessionId, $userId, $ipAddress, $userAgent, $expiresAt, $sessionData, $expiresAt);
                $stmt->execute();
            }
            
            // Log login activity
            $this->logActivity($userId, 'login', 'User logged in', 'success');
            
            // Update user's last_login
            $updateSql = "UPDATE users SET last_login = NOW(), login_count = COALESCE(login_count, 0) + 1 WHERE user_id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("i", $userId);
            $updateStmt->execute();
            
        } catch (Exception $e) {
            error_log("Error starting session: " . $e->getMessage());
        }
    }
    
    /**
     * End user session
     */
    public function endSession($userId = null) {
        try {
            $sessionId = session_id();
            
            if (!$userId && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            
            // Check if user_sessions table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
            if ($tableCheck->num_rows > 0) {
                $sql = "UPDATE user_sessions SET is_active = 0, logout_time = NOW() WHERE session_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("s", $sessionId);
                $stmt->execute();
            }
            
            // Log logout activity
            if ($userId) {
                $this->logActivity($userId, 'logout', 'User logged out', 'success');
            }
            
        } catch (Exception $e) {
            error_log("Error ending session: " . $e->getMessage());
        }
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        try {
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
            if ($tableCheck->num_rows > 0) {
                $sql = "UPDATE user_sessions SET is_active = 0 WHERE expires_at < NOW() AND is_active = 1";
                $this->conn->query($sql);
            }
        } catch (Exception $e) {
            error_log("Error cleaning expired sessions: " . $e->getMessage());
        }
    }
    
    /**
     * Get online users count
     */
    public function getOnlineUsersCount() {
        try {
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
            if ($tableCheck->num_rows > 0) {
                $sql = "SELECT COUNT(DISTINCT user_id) as count FROM user_sessions 
                        WHERE is_active = 1 AND last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
                $result = $this->conn->query($sql);
                return $result->fetch_assoc()['count'];
            }
            return 0;
        } catch (Exception $e) {
            error_log("Error getting online users count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $actionType, $description, $status = 'success') {
        try {
            // Check if activity_logs table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'activity_logs'");
            if ($tableCheck->num_rows == 0) {
                return; // Table doesn't exist yet
            }
            
            $sql = "INSERT INTO activity_logs (user_id, action_type, action_description, ip_address, user_agent, session_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                "issssss",
                $userId,
                $actionType,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                session_id(),
                $status
            );
            
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
    
    /**
     * Record login attempt (for login history)
     */
    public function recordLoginAttempt($userId, $success = true, $failureReason = null) {
        try {
            // Check if login_history table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'login_history'");
            if ($tableCheck->num_rows == 0) {
                return; // Table doesn't exist yet
            }
            
            $sql = "INSERT INTO login_history (user_id, ip_address, user_agent, session_id, login_status, failure_reason) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $status = $success ? 'success' : 'failed';
            
            $stmt->bind_param(
                "isssss",
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                session_id(),
                $status,
                $failureReason
            );
            
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error recording login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Update session activity
     */
    public function updateActivity($userId = null) {
        try {
            if (!$userId && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            
            if (!$userId) return;
            
            $sessionId = session_id();
            
            // Check if user_sessions table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_sessions'");
            if ($tableCheck->num_rows > 0) {
                $sql = "UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ? AND user_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("si", $sessionId, $userId);
                $stmt->execute();
            }
            
        } catch (Exception $e) {
            error_log("Error updating session activity: " . $e->getMessage());
        }
    }
}

// Global session manager instance
$GLOBALS['sessionManager'] = new SessionManager();

/**
 * Get session manager instance
 */
function getSessionManager() {
    return $GLOBALS['sessionManager'];
}
?>