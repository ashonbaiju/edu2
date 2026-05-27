-- Demo Subjects
INSERT IGNORE INTO subjects (name, code) VALUES
('Mathematics','MATH101'),
('Physics','PHY101'),
('Chemistry','CHEM101'),
('English','ENG101'),
('Computer Science','CS101'),
('Biology','BIO101'),
('History','HIST101'),
('Economics','ECO101');

-- Demo Users (password is 'password' hashed)
DELETE FROM users WHERE email IN ('admin@edusys.com','teacher@edusys.com','student@edusys.com');
SET @pass_hash = '$2y$10$34qKnG3WOUWKHdIPr4ejmO18.5o3guvklIp.Fskdd6BW2qEp0zZ6i';

INSERT INTO users (name,email,password,role,status) VALUES ('System Admin','admin@edusys.com',@pass_hash,'admin','active');
INSERT INTO users (name,email,password,role,status) VALUES ('Demo Teacher','teacher@edusys.com',@pass_hash,'teacher','active');
SET @teacher_uid = LAST_INSERT_ID();
INSERT IGNORE INTO teachers (user_id,qualification,specialization,phone,experience_years,salary,approval_status)
VALUES (@teacher_uid,'M.Sc Mathematics','Mathematics, Physics','9876543210',5,35000.00,'approved');

INSERT INTO users (name,email,password,role,status) VALUES ('Demo Student','student@edusys.com',@pass_hash,'student','active');
SET @student_uid = LAST_INSERT_ID();
INSERT IGNORE INTO students (user_id,roll_number,phone,grade,parent_name,admission_date)
VALUES (@student_uid,'STU0001','9876543211','Grade 10','Demo Parent',CURDATE());

-- Demo Notices
SET @admin_uid = (SELECT id FROM users WHERE email='admin@edusys.com');
INSERT IGNORE INTO notices (title,content,target_role,created_by,is_pinned) VALUES
('Welcome to EduSys!','Welcome to the AI-powered tuition system.','all',@admin_uid,1),
('Fee Reminder','Please pay fees before month end.','student',@admin_uid,0);

-- Demo Batch & Enrollment
SET @teacher_tbl_id = (SELECT id FROM teachers WHERE user_id=@teacher_uid);
SET @student_tbl_id = (SELECT id FROM students WHERE user_id=@student_uid);
INSERT IGNORE INTO batches (name,teacher_id,subject_id,grade,status)
VALUES ('Demo Batch - Mathematics',@teacher_tbl_id,1,'Grade 10','active');
SET @demo_batch_id = LAST_INSERT_ID();
INSERT IGNORE INTO batch_students (batch_id,student_id) VALUES (@demo_batch_id,@student_tbl_id);
