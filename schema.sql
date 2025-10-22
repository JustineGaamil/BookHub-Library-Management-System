-- Library Management System schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

-- Drop existing tables in dependency order (for development resets)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS loan_history;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS publishers;
DROP TABLE IF EXISTS patrons;
DROP TABLE IF EXISTS staff;
SET FOREIGN_KEY_CHECKS = 1;

-- Staff (users) with role-based access
CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  password VARCHAR(255) NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Authors
CREATE TABLE authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  bio TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- Publishers
CREATE TABLE publishers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  website VARCHAR(200) NULL
) ENGINE=InnoDB;

-- Books
CREATE TABLE books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  isbn VARCHAR(20) NULL UNIQUE,
  author_id INT NULL,
  category_id INT NULL,
  publisher_id INT NULL,
  published_year INT NULL,
  status ENUM('available','reserved','loaned') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_books_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL,
  CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Patrons (members)
CREATE TABLE patrons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Loans (active loan records)
CREATE TABLE loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  patron_id INT NOT NULL,
  staff_id INT NOT NULL,
  loan_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE NULL,
  status ENUM('borrowed','returned','overdue') NOT NULL DEFAULT 'borrowed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_loans_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT,
  CONSTRAINT fk_loans_patron FOREIGN KEY (patron_id) REFERENCES patrons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_loans_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Reservations
CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  patron_id INT NOT NULL,
  reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','fulfilled','cancelled') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_res_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_patron FOREIGN KEY (patron_id) REFERENCES patrons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Loan history (immutable records of borrow/return)
CREATE TABLE loan_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NULL,
  book_id INT NOT NULL,
  patron_id INT NOT NULL,
  staff_id INT NOT NULL,
  action ENUM('borrow','return') NOT NULL,
  action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_hist_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE SET NULL,
  CONSTRAINT fk_hist_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hist_patron FOREIGN KEY (patron_id) REFERENCES patrons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hist_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Seed admin user (username: admin, password: admin123)
-- Use MySQL's SHA2 for quick seed; in PHP we'll use password_hash for real inserts
INSERT INTO staff (username, password_hash, password, full_name, role)
VALUES ('admin', '$2y$10$gmJ6bYfXkL1YqQ2U3k2K9e3kV5m7x3a2oR2q3ZsJpVg8o4o7qv0jO', 'admin123', 'System Administrator', 'admin');
-- The above bcrypt hash corresponds to: admin123

-- Seed a regular staff user: username: staff, password: staff123
INSERT INTO staff (username, password_hash, password, full_name, role)
VALUES ('staff', '$2y$10$3i0Q3H1m7cF4l5mKQYtZ5e1fZb0t2cY2F3O9sGmTqYv2m5O4b8TqW', 'staff123', 'Library Staff', 'staff');

-- Seed basic reference data
INSERT INTO authors (name, bio) VALUES ('Jane Austen', 'English novelist known primarily for her six major novels.');
INSERT INTO categories (name, description) VALUES ('Fiction', 'Fictional works');
INSERT INTO publishers (name, address, phone, website) VALUES ('Penguin Books', '80 Strand, London', '+44 20 1234 5678', 'https://www.penguin.co.uk');

-- Seed a patron
INSERT INTO patrons (full_name, email, phone, address)
VALUES ('John Doe', 'john@example.com', '+1-555-0100', '123 Main St');

-- Seed a book
INSERT INTO books (title, isbn, author_id, category_id, publisher_id, published_year, status)
VALUES ('Pride and Prejudice', '9780141439518', 1, 1, 1, 1813, 'available');

-- Seed a loan (borrowed by John Doe by staff)
INSERT INTO loans (book_id, patron_id, staff_id, loan_date, due_date, status)
VALUES (1, 1, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'borrowed');

-- Record in loan history
INSERT INTO loan_history (loan_id, book_id, patron_id, staff_id, action, notes)
VALUES (1, 1, 1, 2, 'borrow', 'Initial sample loan');



