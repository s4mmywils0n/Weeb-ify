-- Create the database
CREATE DATABASE IF NOT EXISTS weeb_db;

USE weeb_db;

-- Create the anime table
CREATE TABLE IF NOT EXISTS anime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    season_aired VARCHAR(50),
    year_aired YEAR,
    number_of_episodes INT,
    number_of_seasons INT,
    studio VARCHAR(100),
    genre VARCHAR(100),
    status VARCHAR(30)
);

-- Create the manga table
CREATE TABLE IF NOT EXISTS manga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(100),
    year_published YEAR,
    number_of_volumes INT,
    number_of_chapters INT,
    genre VARCHAR(100),
    status VARCHAR(30)
);

-- Insert sample anime items
INSERT INTO anime
    (title, season_aired, year_aired, number_of_episodes,
     number_of_seasons, studio, genre, status)
VALUES
    ('Demon Slayer', 'Spring', 2019, 26, 1,
     'Ufotable', 'Action, Fantasy', 'Ongoing'),
    ('Attack on Titan', 'Spring', 2013, 25, 4,
     'Wit Studio / MAPPA', 'Action, Drama', 'Completed'),
    ('Jujutsu Kaisen', 'Fall', 2020, 24, 2,
     'MAPPA', 'Action, Supernatural', 'Ongoing'),
    ('Fullmetal Alchemist: Brotherhood', 'Spring', 2009, 64, 1,
     'Bones', 'Adventure, Fantasy', 'Completed'),
    ('Death Note', 'Fall', 2006, 37, 1,
     'Madhouse', 'Mystery, Thriller', 'Completed');

-- Insert sample manga items
INSERT INTO manga
    (title, author, year_published, number_of_volumes,
     number_of_chapters, genre, status)
VALUES
    ('One Piece', 'Eiichiro Oda', 1997, 110, 1120,
     'Adventure, Fantasy', 'Ongoing'),
    ('Naruto', 'Masashi Kishimoto', 1999, 72, 700,
     'Action, Adventure', 'Completed'),
    ('Berserk', 'Kentaro Miura', 1989, 42, NULL,
     'Dark Fantasy', 'Ongoing'),
    ('Death Note', 'Tsugumi Ohba', 2003, 12, 108,
     'Mystery, Thriller', 'Completed'),
    ('Attack on Titan', 'Hajime Isayama', 2009, 34, 139,
     'Action, Dark Fantasy', 'Completed');