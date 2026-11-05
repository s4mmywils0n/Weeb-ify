CREATE TABLE anime (
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

CREATE TABLE manga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(100),
    year_published YEAR,
    number_of_volumes INT,
    number_of_chapters INT,
    genre VARCHAR(100),
    status VARCHAR(30)
);