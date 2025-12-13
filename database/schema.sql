-- Departments
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Checklists
CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    department_id INT,
    status VARCHAR(50) DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Checklist Questions
CREATE TABLE IF NOT EXISTS checklist_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    order_num INT NOT NULL,
    text TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_required BOOLEAN DEFAULT true,
    photo_required BOOLEAN DEFAULT false,
    help_text TEXT,
    min_score INT,
    max_score INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Field Tours
CREATE TABLE IF NOT EXISTS field_tours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    inspector_id INT NOT NULL,
    location VARCHAR(255),
    status VARCHAR(50) DEFAULT 'in_progress',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    summary TEXT,
    overall_score DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Field Tour Responses
CREATE TABLE IF NOT EXISTS field_tour_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_tour_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (field_tour_id) REFERENCES field_tours(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES checklist_questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Actions
CREATE TABLE IF NOT EXISTS actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    department_id INT,
    assigned_to_user_id INT,
    assigned_by_user_id INT,
    source VARCHAR(50) DEFAULT 'field_tour',
    risk_level INT,
    risk_score DECIMAL(5,2),
    priority VARCHAR(50),
    status VARCHAR(50) DEFAULT 'open',
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Action Comments
CREATE TABLE IF NOT EXISTS action_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (action_id) REFERENCES actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT false,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Data
INSERT IGNORE INTO departments (name, code) VALUES 
('İSG Departmanı', 'ISG'),
('Üretim', 'PROD'),
('Kalite', 'QA');

INSERT IGNORE INTO checklists (name, description, status, created_by) VALUES
('Genel Güvenlik Denetimi', 'Tüm alanlar için genel güvenlik kontrolü', 'active', 1),
('Yangın Güvenliği Kontrolü', 'Yangın söndürücü ve acil çıkış kontrolleri', 'active', 1);

INSERT IGNORE INTO checklist_questions (checklist_id, order_num, text, type, is_required, photo_required) VALUES
(1, 1, 'Yangın söndürücüler erişilebilir mi?', 'yes_no', true, true),
(1, 2, 'Acil çıkış işaretleri görünür mü?', 'yes_no', true, false),
(1, 3, 'Genel temizlik durumu (1-5)', 'score', true, false);
