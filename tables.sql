-- Schema for using \jmvc\classes\Session with MySQL
CREATE TABLE `sessions` (
  `id` char(32) NOT NULL,
  `data` text NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- Schema for using queued mail with Postmark
CREATE TABLE  `postmark_mail_queue` (
`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`data` TEXT NOT NULL ,
`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = INNODB;
