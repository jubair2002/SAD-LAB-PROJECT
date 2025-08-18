<?php
require_once 'config.php';

class ChatSystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Get all chat rooms for a user
    public function getUserChatRooms($userId) {
        $query = "SELECT cr.*, 
                 (SELECT COUNT(*) FROM messages m WHERE m.chat_room_id = cr.id AND 
                  (m.created_at > COALESCE((SELECT last_read_at FROM chat_participants 
                                   WHERE chat_room_id = cr.id AND user_id = ?), '1970-01-01'))) AS unread_count
                 FROM chat_rooms cr
                 JOIN chat_participants cp ON cr.id = cp.chat_room_id
                 WHERE cp.user_id = ?
                 ORDER BY (SELECT MAX(created_at) FROM messages WHERE chat_room_id = cr.id) DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get messages for a chat room
    public function getChatMessages($chatRoomId, $userId) {
        // Mark messages as read
        $this->markMessagesAsRead($chatRoomId, $userId);
        
        $query = "SELECT m.*, u.fname, u.lname, u.user_type, u.picture
                 FROM messages m
                 JOIN users u ON m.user_id = u.id
                 WHERE m.chat_room_id = ?
                 ORDER BY m.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("i", $chatRoomId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Send a message
    public function sendMessage($chatRoomId, $userId, $message, $isEmergency = false, $attachment = null) {
        // Convert boolean to integer for MySQL
        $emergencyInt = $isEmergency ? 1 : 0;
        
        $query = "INSERT INTO messages (chat_room_id, user_id, message, is_emergency, attachment_url, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("iisis", $chatRoomId, $userId, $message, $emergencyInt, $attachment);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception('Message insertion failed: ' . $stmt->error);
        }
        
        return $result;
    }
    
    // Create a campaign chat room
    public function createCampaignChat($campaignId, $adminId) {
        $this->conn->begin_transaction();
        
        try {
            // Get campaign info
            $campaignQuery = "SELECT name FROM campaigns WHERE id = ?";
            $stmt = $this->conn->prepare($campaignQuery);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $this->conn->error);
            }
            
            $stmt->bind_param("i", $campaignId);
            $stmt->execute();
            $campaign = $stmt->get_result()->fetch_assoc();
            
            if (!$campaign) {
                throw new Exception("Campaign not found");
            }
            
            // Create chat room
            $chatQuery = "INSERT INTO chat_rooms (name, type, campaign_id, created_by, created_at)
                         VALUES (?, 'campaign', ?, ?, NOW())";
            $stmt = $this->conn->prepare($chatQuery);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $this->conn->error);
            }
            
            $chatName = $campaign['name'] . " Group";
            $stmt->bind_param("sii", $chatName, $campaignId, $adminId);
            $stmt->execute();
            $chatRoomId = $this->conn->insert_id;
            
            // Add admin as participant
            $this->addParticipant($chatRoomId, $adminId);
            
            $this->conn->commit();
            return $chatRoomId;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    // Add a participant to a chat room
    public function addParticipant($chatRoomId, $userId) {
        $query = "INSERT INTO chat_participants (chat_room_id, user_id, joined_at) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE joined_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("ii", $chatRoomId, $userId);
        return $stmt->execute();
    }
    
    // Mark messages as read
    private function markMessagesAsRead($chatRoomId, $userId) {
        $query = "UPDATE chat_participants SET last_read_at = NOW()
                 WHERE chat_room_id = ? AND user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false; // Don't throw exception for this non-critical operation
        }
        
        $stmt->bind_param("ii", $chatRoomId, $userId);
        return $stmt->execute();
    }
    
    // Create or get private chat between two users
    public function getOrCreatePrivateChat($user1Id, $user2Id) {
        // Check if chat already exists
        $query = "SELECT cr.id FROM chat_rooms cr
                 JOIN chat_participants cp1 ON cr.id = cp1.chat_room_id AND cp1.user_id = ?
                 JOIN chat_participants cp2 ON cr.id = cp2.chat_room_id AND cp2.user_id = ?
                 WHERE cr.type = 'private'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("ii", $user1Id, $user2Id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['id'];
        }
        
        // Create new private chat
        $this->conn->begin_transaction();
        
        try {
            // Get user names for chat title
            $userQuery = "SELECT fname, lname FROM users WHERE id IN (?, ?)";
            $stmt = $this->conn->prepare($userQuery);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $this->conn->error);
            }
            
            $stmt->bind_param("ii", $user1Id, $user2Id);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            if (count($users) < 2) {
                throw new Exception("One or both users not found");
            }
            
            $chatName = $users[0]['fname'] . " & " . $users[1]['fname'];
            
            // Create chat room
            $chatQuery = "INSERT INTO chat_rooms (name, type, created_by, created_at)
                         VALUES (?, 'private', ?, NOW())";
            $stmt = $this->conn->prepare($chatQuery);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $this->conn->error);
            }
            
            $stmt->bind_param("si", $chatName, $user1Id);
            $stmt->execute();
            $chatRoomId = $this->conn->insert_id;
            
            // Add participants
            $this->addParticipant($chatRoomId, $user1Id);
            $this->addParticipant($chatRoomId, $user2Id);
            
            $this->conn->commit();
            return $chatRoomId;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    // Get chat participants
    public function getChatParticipants($chatRoomId) {
        $query = "SELECT u.id, u.fname, u.lname, u.user_type, u.status, u.picture,
                 CASE 
                     WHEN u.status = 'active' THEN 'Online'
                     ELSE 'Offline'
                 END as online_status
                 FROM chat_participants cp
                 JOIN users u ON cp.user_id = u.id
                 WHERE cp.chat_room_id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("i", $chatRoomId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Check if a chat exists for a campaign
    public function getCampaignChat($campaignId) {
        $query = "SELECT id FROM chat_rooms WHERE campaign_id = ? AND type = 'campaign' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $this->conn->error);
        }
        
        $stmt->bind_param("i", $campaignId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['id'];
        }
        
        return null;
    }
    
    // Get or create a campaign chat
    public function getOrCreateCampaignChat($campaignId, $adminId) {
        $chatId = $this->getCampaignChat($campaignId);
        
        if ($chatId) {
            return $chatId;
        }
        
        return $this->createCampaignChat($campaignId, $adminId);
    }
    
    // Add volunteer to campaign chat
    public function addVolunteerToCampaignChat($campaignId, $volunteerId, $adminId) {
        try {
            $chatId = $this->getOrCreateCampaignChat($campaignId, $adminId);
            
            if ($chatId) {
                $this->addParticipant($chatId, $volunteerId);
                
                // Send a welcome message
                $volunteerName = $this->getUserName($volunteerId);
                $message = "Volunteer $volunteerName has been assigned to this campaign and added to the group chat.";
                $this->sendMessage($chatId, $adminId, $message, false);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            // Create chat tables if they don't exist
            $this->createChatTables();
            
            // Try again after creating tables
            try {
                $chatId = $this->getOrCreateCampaignChat($campaignId, $adminId);
                
                if ($chatId) {
                    $this->addParticipant($chatId, $volunteerId);
                    
                    $volunteerName = $this->getUserName($volunteerId);
                    $message = "Volunteer $volunteerName has been assigned to this campaign and added to the group chat.";
                    $this->sendMessage($chatId, $adminId, $message, false);
                    
                    return true;
                }
            } catch (Exception $e2) {
                error_log("Failed to add volunteer to campaign chat even after creating tables: " . $e2->getMessage());
                return false;
            }
            
            return false;
        }
    }
    
    // Helper to get user name
    private function getUserName($userId) {
        $query = "SELECT CONCAT(fname, ' ', lname) AS name FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return "Unknown";
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['name'];
        }
        
        return "Unknown";
    }
    
    // Create chat tables if they don't exist (MySQL syntax)
    private function createChatTables() {
        $chatRoomsTable = "
            CREATE TABLE IF NOT EXISTS chat_rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type ENUM('global', 'private', 'campaign') NOT NULL DEFAULT 'private',
                campaign_id INT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_created_by (created_by),
                INDEX idx_campaign_id (campaign_id),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
            )
        ";
        
        $chatParticipantsTable = "
            CREATE TABLE IF NOT EXISTS chat_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                chat_room_id INT NOT NULL,
                user_id INT NOT NULL,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_read_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY unique_participant (chat_room_id, user_id),
                INDEX idx_chat_room (chat_room_id),
                INDEX idx_user (user_id),
                FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        
        $messagesTable = "
            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                chat_room_id INT NOT NULL,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                is_emergency TINYINT(1) NOT NULL DEFAULT 0,
                attachment_url VARCHAR(500) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat_room (chat_room_id),
                INDEX idx_user (user_id),
                INDEX idx_created_at (created_at),
                INDEX idx_emergency (is_emergency),
                FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        
        $this->conn->query($chatRoomsTable);
        $this->conn->query($chatParticipantsTable);
        $this->conn->query($messagesTable);
    }
}
?>