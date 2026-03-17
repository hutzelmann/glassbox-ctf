CREATE DATABASE hacky;
GRANT SELECT ON hacky.* TO hacky@'%' IDENTIFIED BY 'Ju5TRE4D1t';

CREATE TABLE hacky.users (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(32) NOT NULL,
  password VARCHAR(32) NOT NULL
);

INSERT INTO hacky.users (username, password) VALUES('admin', 'C0n9ratu1ation5!');
INSERT INTO hacky.users (username, password) VALUES('test', '123456');
INSERT INTO hacky.users (username, password) VALUES('alice', 'N0TaT0keN');

CREATE TABLE hacky.hidden (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  text VARCHAR(128) NOT NULL
);

INSERT INTO hacky.hidden (text) VALUES ('5uper5ecret!');
