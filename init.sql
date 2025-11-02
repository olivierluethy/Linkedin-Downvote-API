CREATE TABLE dislikes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id VARCHAR(255) NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_vote (post_id, client_id)
);

CREATE TABLE post_dislike_count (
  post_id VARCHAR(255) PRIMARY KEY,
  dislike_count INT DEFAULT 0
);
