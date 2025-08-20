CREATE TABLE `user` (
`Id` int NOT NULL AUTO_INCREMENT,
`Name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`Dob` date DEFAULT NULL,
`Email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`City` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`Country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`Sex` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`Id`)
) ENGINE=InnoDB AUTO_INCREMENT=113239001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE USER 'admin'@'localhost' IDENTIFIED WITH mysql_native_password BY 'admin';
GRANT ALL PRIVILEGES ON *.* TO 'admin'@'localhost' WITH GRANT OPTION;
flush privileges