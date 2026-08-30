CREATE DATABASE hacky;
GRANT SELECT, INSERT, DELETE, ALTER ON hacky.* TO hacky@'%' IDENTIFIED BY 'Ju5TRE4D1t';

CREATE TABLE hacky.users (
  uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
);

INSERT INTO hacky.users (uid, username, password) VALUES (1, 'admin', 'N0tAHa5Hbu1y0urFLA9');
