-- MySQL dump 10.13  Distrib 5.6.24, for osx10.8 (x86_64)
--
-- Host: localhost    Database: di-gui
-- ------------------------------------------------------
-- Server version	5.5.5-10.0.25-MariaDB-1~trusty

CREATE SCHEMA IF NOT EXISTS `di-gui` DEFAULT CHARACTER SET = utf8 DEFAULT COLLATE = utf8_unicode_ci;

USE `di-gui`;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `AdAnalysis`
--

DROP TABLE IF EXISTS `AdAnalysis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AdAnalysis` (
  `idAdAnalysis` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `targetAd` tinyint(1) NOT NULL,
  `currentAd` tinyint(1) NOT NULL,
  `adType` enum('brochures','products') COLLATE utf8_bin NOT NULL,
  `timeChecked` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idAdAnalysis`),
  KEY `fk.Company1_idx` (`idCompany`),
  CONSTRAINT `fk.Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1870 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `AdditionalRetailerInfos`
--

DROP TABLE IF EXISTS `AdditionalRetailerInfos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AdditionalRetailerInfos` (
  `idStore` int(11) NOT NULL,
  `idCompany` int(11) NOT NULL,
  `action` enum('ignore','update') COLLATE utf8_bin NOT NULL,
  `infosToChange` text COLLATE utf8_bin,
  `user` varchar(45) COLLATE utf8_bin NOT NULL,
  `timeAdded` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `validityLength` varchar(45) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`idStore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `AdvertisingSettings`
--

DROP TABLE IF EXISTS `AdvertisingSettings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AdvertisingSettings` (
  `idAdvertisingSettings` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `title` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  `description` text COLLATE utf8_bin,
  `intervall` int(11) NOT NULL,
  `intervallType` enum('day','week','month','year','unique') COLLATE utf8_bin NOT NULL,
  `startDate` timestamp NULL DEFAULT NULL,
  `endDate` timestamp NULL DEFAULT NULL,
  `adType` enum('brochures','products') COLLATE utf8_bin NOT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `adStatus` enum('active','inactive') COLLATE utf8_bin NOT NULL,
  `nextDate` timestamp NULL DEFAULT NULL,
  `weekDays` text COLLATE utf8_bin NOT NULL,
  `ticketCheck` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`idAdvertisingSettings`),
  KEY `fk_AdvertisingSettings_idCompany1_idx` (`idCompany`),
  CONSTRAINT `fk_AdvertisingSettings_idCompany1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_AdvertisingSettings_idCompany2` FOREIGN KEY (`idCompany`) REFERENCES `Redmine` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `AmountBrochures`
--

DROP TABLE IF EXISTS `AmountBrochures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AmountBrochures` (
  `idAmountBrochures` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `amountBrochures` int(11) NOT NULL,
  `startDate` timestamp NULL DEFAULT NULL,
  `endDate` timestamp NULL DEFAULT NULL,
  `lastTimeModified` timestamp NULL DEFAULT NULL,
  `lastTimeChecked` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lastImport` timestamp NULL DEFAULT NULL,
  `countStores` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`idAmountBrochures`),
  KEY `fk_AmountProducts_Company1_idx` (`idCompany`),
  CONSTRAINT `fk_AmountBrochures_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1983821 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `AmountProducts`
--

DROP TABLE IF EXISTS `AmountProducts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AmountProducts` (
  `idAmountProducts` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `amountProducts` int(11) NOT NULL,
  `startDate` timestamp NULL DEFAULT NULL,
  `endDate` timestamp NULL DEFAULT NULL,
  `lastTimeModified` timestamp NULL DEFAULT NULL,
  `lastTimeChecked` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lastImport` timestamp NULL DEFAULT NULL,
  `countStores` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`idAmountProducts`),
  KEY `fk_AmountProducts_Company1_idx` (`idCompany`),
  CONSTRAINT `fk_AmountProducts_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2178336 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `AmountStores`
--

DROP TABLE IF EXISTS `AmountStores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AmountStores` (
  `idAmountStores` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `amountStores` int(11) NOT NULL,
  `lastTimeModified` timestamp NULL DEFAULT NULL,
  `lastTimeChecked` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lastImport` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`idAmountStores`),
  KEY `fk_AmountStores_Company1_idx` (`idCompany`),
  CONSTRAINT `fk_AmountStores_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4336653 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Author`
--

DROP TABLE IF EXISTS `Author`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Author` (
  `idAuthor` int(11) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  PRIMARY KEY (`idAuthor`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Company`
--

DROP TABLE IF EXISTS `Company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Company` (
  `idCompany` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `productCategory` varchar(45) NOT NULL,
  `status` enum('active','inactive','removed') NOT NULL,
  `idPartner` int(11) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`idCompany`),
  KEY `idPartner` (`idPartner`),
  CONSTRAINT `Company_ibfk_1` FOREIGN KEY (`idPartner`) REFERENCES `Partner` (`idPartner`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `CompanyAdditionalInfos`
--

DROP TABLE IF EXISTS `CompanyAdditionalInfos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CompanyAdditionalInfos` (
  `idAdditionalInfos` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `bonusCards` text,
  `toilet` tinyint(1) DEFAULT NULL,
  `services` text,
  `section` text,
  `parking` text,
  `barrierFree` tinyint(1) DEFAULT NULL,
  `payment` text,
  PRIMARY KEY (`idAdditionalInfos`),
  KEY `idCompany_idx` (`idCompany`),
  CONSTRAINT `idCompany` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `CrawlerBehaviour`
--

DROP TABLE IF EXISTS `CrawlerBehaviour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CrawlerBehaviour` (
  `idCrawlerBehaviour` int(11) NOT NULL AUTO_INCREMENT,
  `behaviour` varchar(45) NOT NULL,
  PRIMARY KEY (`idCrawlerBehaviour`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `CrawlerConfig`
--

DROP TABLE IF EXISTS `CrawlerConfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CrawlerConfig` (
  `idCrawlerConfig` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `idCrawlerType` int(11) NOT NULL,
  `idCrawlerBehaviour` int(11) NOT NULL,
  `crawlerStatus` enum('deaktiviert','zeitgesteuert','manuell / auslösergesteuert') NOT NULL,
  `idAuthor` int(11) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `fileName` varchar(200) NOT NULL,
  `execution` varchar(45) DEFAULT NULL,
  `runtime` int(11) DEFAULT NULL,
  `lastModified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `statusChanged` timestamp NULL DEFAULT NULL,
  `ticketCreate` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`idCrawlerConfig`),
  KEY `fk_CrawlerConfig_Company` (`idCompany`),
  KEY `fk_CrawlerConfig_CrawlerType1` (`idCrawlerType`),
  KEY `fk_CrawlerConfig_CrawlerBehaviour1` (`idCrawlerBehaviour`),
  KEY `fk_CrawlerConfig_Author1` (`idAuthor`),
  CONSTRAINT `fk_CrawlerConfig_Author1` FOREIGN KEY (`idAuthor`) REFERENCES `Author` (`idAuthor`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_CrawlerConfig_Company` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_CrawlerConfig_CrawlerBehaviour1` FOREIGN KEY (`idCrawlerBehaviour`) REFERENCES `CrawlerBehaviour` (`idCrawlerBehaviour`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_CrawlerConfig_CrawlerType1` FOREIGN KEY (`idCrawlerType`) REFERENCES `CrawlerType` (`idCrawlerType`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1007 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `CrawlerLog`
--

DROP TABLE IF EXISTS `CrawlerLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CrawlerLog` (
  `idCrawlerLog` int(11) NOT NULL AUTO_INCREMENT,
  `idCrawlerConfig` int(11) NOT NULL,
  `idCrawlerLogType` int(11) NOT NULL,
  `scheduled` timestamp NULL DEFAULT NULL,
  `start` timestamp NULL DEFAULT NULL,
  `end` timestamp NULL DEFAULT NULL,
  `prio` int(11) DEFAULT NULL,
  `processNumber` int(11) DEFAULT NULL,
  `errorMessage` varchar(3000) DEFAULT NULL,
  `importId` int(11) DEFAULT NULL,
  `importStart` timestamp NULL DEFAULT NULL,
  `importEnd` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`idCrawlerLog`),
  KEY `fk_CrawlerLog_CrawlerLogType1` (`idCrawlerLogType`),
  KEY `fk_CrawlerLog_CrawlerConfig1` (`idCrawlerConfig`),
  CONSTRAINT `fk_CrawlerLog_CrawlerConfig1` FOREIGN KEY (`idCrawlerConfig`) REFERENCES `CrawlerConfig` (`idCrawlerConfig`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_CrawlerLog_CrawlerLogType1` FOREIGN KEY (`idCrawlerLogType`) REFERENCES `CrawlerLogType` (`idCrawlerLogType`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=134906 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `CrawlerLogType`
--

DROP TABLE IF EXISTS `CrawlerLogType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CrawlerLogType` (
  `idCrawlerLogType` int(11) NOT NULL AUTO_INCREMENT,
  `logType` varchar(100) NOT NULL,
  PRIMARY KEY (`idCrawlerLogType`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `CrawlerType`
--

DROP TABLE IF EXISTS `CrawlerType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CrawlerType` (
  `idCrawlerType` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(45) NOT NULL,
  PRIMARY KEY (`idCrawlerType`),
  UNIQUE KEY `type_UNIQUE` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `IntervallType`
--

DROP TABLE IF EXISTS `IntervallType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `IntervallType` (
  `idIntervallType` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idIntervallType`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

CREATE TABLE `Partner` (
  `idPartner` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `description` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `apiHost` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `apiKey` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `apiPassword` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (`idPartner`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Table structure for table `QualityCheckCompanyInfos`
--

DROP TABLE IF EXISTS `QualityCheckCompanyInfos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `QualityCheckCompanyInfos` (
  `idQualityCheckCompanyInfos` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `stores` tinyint(1) NOT NULL,
  `brochures` tinyint(1) NOT NULL,
  `products` tinyint(1) NOT NULL,
  `limitStores` float DEFAULT NULL,
  `limitBrochures` float DEFAULT NULL,
  `limitProducts` float DEFAULT NULL,
  `freshnessStores` tinyint(1) DEFAULT NULL,
  `freshnessBrochures` tinyint(1) DEFAULT NULL,
  `freshnessProducts` tinyint(1) DEFAULT NULL,
  `futureBrochures` tinyint(1) DEFAULT NULL,
  `futureProducts` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`idQualityCheckCompanyInfos`),
  KEY `fk_QualityCheckErrors_Company1_idx` (`idCompany`),
  CONSTRAINT `fk_QualityCheckCompanyInfos_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7989 DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='	';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `QualityCheckErrors`
--

DROP TABLE IF EXISTS `QualityCheckErrors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `QualityCheckErrors` (
  `idQualityCheckErrors` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `type` varchar(45) COLLATE utf8_bin NOT NULL,
  `actualAmount` int(11) NOT NULL,
  `lastAmount` int(11) NOT NULL,
  `lastTimeModified` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `lastImport` timestamp NULL DEFAULT NULL,
  `timeAdded` timestamp NULL DEFAULT NULL,
  `errorStatus` enum('active','inactive') COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`idQualityCheckErrors`),
  KEY `fk_QualityCheckErrors_Company1_idx` (`idCompany`),
  KEY `fk_QualityCheckErrors_User1_idx` (`idUser`),
  CONSTRAINT `fk_QualityCheckErrors_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_QualityCheckErrors_User1` FOREIGN KEY (`idUser`) REFERENCES `User` (`idUser`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16758 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Redmine`
--

DROP TABLE IF EXISTS `Redmine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Redmine` (
  `idCompany` int(11) NOT NULL,
  `idRedmine` int(11) NOT NULL,
  PRIMARY KEY (`idCompany`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Role`
--

DROP TABLE IF EXISTS `Role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Role` (
  `idRole` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`idRole`),
  UNIQUE KEY `name_UNIQUE` (`name`),
  UNIQUE KEY `level_UNIQUE` (`level`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Status`
--

DROP TABLE IF EXISTS `Status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Status` (
  `idStatus` int(11) NOT NULL AUTO_INCREMENT,
  `statusName` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idStatus`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Stellwerk`
--

DROP TABLE IF EXISTS `Stellwerk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Stellwerk` (
  `idCompany` int(11) NOT NULL,
  PRIMARY KEY (`idCompany`),
  CONSTRAINT `fk.idCompany1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Task`
--

DROP TABLE IF EXISTS `Task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Task` (
  `idTask` int(11) NOT NULL AUTO_INCREMENT,
  `idRedmine` int(11) DEFAULT NULL,
  `dateCreation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `title` varchar(100) DEFAULT NULL,
  `description` text,
  `intervall` int(11) NOT NULL,
  `idIntervallType` int(11) NOT NULL,
  `startDate` timestamp NULL DEFAULT NULL,
  `nextDate` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL,
  `taskStatus` enum('active','inactive') NOT NULL,
  `idCompany` int(11) NOT NULL,
  `intervallType` enum('day','week','month','year','unique') NOT NULL,
  PRIMARY KEY (`idTask`),
  KEY `fk_Task_IntervallType1_idx` (`idIntervallType`)
) ENGINE=InnoDB AUTO_INCREMENT=878 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `TriggerConfig`
--

DROP TABLE IF EXISTS `TriggerConfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TriggerConfig` (
  `idTriggerConfig` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `idCrawlerType` int(11) NOT NULL,
  `idTriggerType` int(11) NOT NULL,
  `patternFileName` varchar(150) NOT NULL,
  `idCrawlerConfig` int(11) NOT NULL,
  PRIMARY KEY (`idTriggerConfig`),
  KEY `fk_TriggerConfig_Company1_idx` (`idCompany`),
  KEY `fk_TriggerConfig_CrawlerType1_idx` (`idCrawlerType`),
  KEY `fk_TriggerConfig_TriggerType1_idx` (`idTriggerType`),
  CONSTRAINT `fk_TriggerConfig_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_TriggerConfig_CrawlerType1` FOREIGN KEY (`idCrawlerType`) REFERENCES `CrawlerType` (`idCrawlerType`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_TriggerConfig_TriggerType1` FOREIGN KEY (`idTriggerType`) REFERENCES `TriggerType` (`idTriggerType`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `TriggerLog`
--

DROP TABLE IF EXISTS `TriggerLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TriggerLog` (
  `idTriggerLog` int(11) NOT NULL AUTO_INCREMENT,
  `idCompany` int(11) NOT NULL,
  `idTriggerType` int(11) NOT NULL,
  `userName` varchar(100) NOT NULL,
  `fileName` varchar(200) NOT NULL,
  `action` varchar(50) NOT NULL,
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idTriggerLog`),
  KEY `fk_TriggerLog_Company1_idx` (`idCompany`),
  KEY `fk_TriggerLog_TriggerType1_idx` (`idTriggerType`),
  CONSTRAINT `fk_TriggerLog_Company1` FOREIGN KEY (`idCompany`) REFERENCES `Company` (`idCompany`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_TriggerLog_TriggerType1` FOREIGN KEY (`idTriggerType`) REFERENCES `TriggerType` (`idTriggerType`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=271846 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `TriggerType`
--

DROP TABLE IF EXISTS `TriggerType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TriggerType` (
  `idTriggerType` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`idTriggerType`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `User` (
  `idUser` int(11) NOT NULL AUTO_INCREMENT,
  `userName` varchar(50) NOT NULL,
  `password` varchar(100) DEFAULT NULL,
  `passwordSalt` varchar(100) DEFAULT NULL,
  `realName` varchar(150) DEFAULT NULL,
  `idRole` int(11) NOT NULL,
  PRIMARY KEY (`idUser`),
  UNIQUE KEY `userName_UNIQUE` (`userName`),
  KEY `fk_User_Role1_idx` (`idRole`),
  CONSTRAINT `fk_User_Role1` FOREIGN KEY (`idRole`) REFERENCES `Role` (`idRole`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `geo_region`
--

DROP TABLE IF EXISTS `geo_region`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `geo_region` (
  `region_id` int(10) unsigned NOT NULL,
  `city_id` int(10) unsigned NOT NULL,
  `region_zipcode` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `region_city` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `region_longitude` double NOT NULL,
  `region_latitude` double NOT NULL,
  `region_state` varchar(128) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Nur wegen Abwärtskompatibilität',
  `region_county` varchar(128) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Nur wegen Abwärtskompatibilität',
  `region_url` varchar(128) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Nur wegen Abwärtskompatibilität',
  `region_pid` int(10) unsigned NOT NULL COMMENT 'Nur wegen Abwärtskompatibilität',
  `munic_code` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Municipality Code/Gemeindeschlüssel',
  PRIMARY KEY (`region_id`),
  KEY `city_zipcode` (`city_id`,`region_zipcode`),
  KEY `zipcode` (`region_zipcode`),
  KEY `coordinates` (`region_longitude`,`region_latitude`),
  KEY `munic` (`munic_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'di-gui'
--

--
-- Dumping routines for database 'di-gui'
--
/*!50003 DROP FUNCTION IF EXISTS `searchClosestRegionId` */;
ALTER DATABASE `di-gui` CHARACTER SET latin1 COLLATE latin1_swedish_ci ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`di_prod`@`127.0.0.1` FUNCTION `searchClosestRegionId`(lon DOUBLE, lat DOUBLE, maxRadius DOUBLE) RETURNS int(10) unsigned
    DETERMINISTIC
BEGIN
  DECLARE region INT UNSIGNED;
  DECLARE temp, latDist, lonDist DOUBLE;
  DECLARE radius DOUBLE DEFAULT maxRadius / 16;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET region := 0;
  label1: LOOP
    SET lonDist := ACOS( (COS(radius/6371.0) - POW(SIN(lat),2)) / POW(COS(lat),2) );
    SET latDist := radius / ( 2 * PI() * 6371.0/360 );
    SELECT region_id, (udf_geo_dist(lon, lat, RADIANS(region_longitude),
           RADIANS(region_latitude))) AS dist INTO region, temp FROM geo_region
    WHERE region_longitude <= DEGREES(lon+lonDist) AND region_longitude >= DEGREES(lon-lonDist) AND
          region_latitude <= DEGREES(lat+latDist) AND region_latitude >= DEGREES(lat-latDist) AND
          (udf_geo_dist(lon, lat, RADIANS(region_longitude), RADIANS(region_latitude))) <= radius 
    ORDER BY dist LIMIT 1;
    IF region > 0 THEN
      LEAVE label1;
    ELSE
      SET radius := radius * 2;
      IF radius > maxRadius THEN
        LEAVE label1;
      END IF;
    END IF;
  END LOOP label1;
  RETURN region;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
ALTER DATABASE `di-gui` CHARACTER SET utf8 COLLATE utf8_bin ;
/*!50003 DROP PROCEDURE IF EXISTS `getAllZipcodes` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER' */ ;
DELIMITER ;;
CREATE DEFINER=`di_prod`@`127.0.0.1` PROCEDURE `getAllZipcodes`(digits INT UNSIGNED)
    DETERMINISTIC
BEGIN
    SELECT DISTINCT SUBSTRING(region_zipcode,1,digits) AS zipcode 
    FROM geo_region ORDER BY region_zipcode;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `getNeighborhoodRegions` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER' */ ;
DELIMITER ;;
CREATE DEFINER=`di_prod`@`127.0.0.1` PROCEDURE `getNeighborhoodRegions`(regionId INT UNSIGNED, radius DOUBLE, returnType VARCHAR(20))
    DETERMINISTIC
BEGIN
  DECLARE latDist, lonDist, lon, lat DOUBLE;
  SELECT RADIANS(region_longitude), RADIANS(region_latitude) INTO lon, lat 
  FROM geo_region WHERE region_id = regionId;
  SET lonDist := ACOS( (COS(radius/6371.0) - POW(SIN(lat),2)) / POW(COS(lat),2) );
  SET latDist := radius / ( 2 * PI() * 6371.0/360 );
  IF returnType = 'zipcode' THEN        
    SELECT DISTINCT region_zipcode FROM geo_region
    WHERE region_longitude <= DEGREES(lon+lonDist) AND region_longitude >= DEGREES(lon-lonDist) AND
          region_latitude <= DEGREES(lat+latDist) AND region_latitude >= DEGREES(lat-latDist) AND
          (udf_geo_dist(lon, lat, RADIANS(region_longitude), RADIANS(region_latitude))) <= radius; 
  ELSE 
    SELECT region_id FROM geo_region
    WHERE region_longitude <= DEGREES(lon+lonDist) AND region_longitude >= DEGREES(lon-lonDist) AND
          region_latitude <= DEGREES(lat+latDist) AND region_latitude >= DEGREES(lat-latDist) AND
          (udf_geo_dist(lon, lat, RADIANS(region_longitude), RADIANS(region_latitude))) <= radius;
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `getRegionGrid` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_CREATE_USER' */ ;
DELIMITER ;;
CREATE DEFINER=`di_prod`@`127.0.0.1` PROCEDURE `getRegionGrid`(net INT UNSIGNED)
    DETERMINISTIC
BEGIN
    DECLARE longA,longB,latA,latB,nsDist,ewDist FLOAT;
    DECLARE x,y,dx,dy FLOAT;

    
    CREATE TEMPORARY TABLE tmp_grid (`long` FLOAT,`lat` FLOAT);

    
    SET longA := 6;
    SET longB := 15;
    SET latA := 47.5;
    SET latB := 54.8;

    
    SET nsDist = ( latB - latA ) * ( 2 * PI() * 6371 / 360 );
    
    
    SET dy = ( net / nsDist ) * ( latB - latA );
    
    
    SET y := latA;
    WHILE y <= latB DO
        
        
        SET ewDist = acos( cos(radians( longB-longA )) * pow(cos(radians( y )),2) + pow(sin(radians( y )),2) ) * 6371;

        
        SET dx = ( net / ewDist ) * ( longB - longA );
        
        
        SET x := longA;
        WHILE x <= longB DO
            
            
            INSERT INTO tmp_grid VALUES (x,y);
            
            SET x := x + dx;
        END WHILE;
        
        SET y := y + dy;
    END WHILE;

    
    SELECT *,udf_geo_dist(radians(`long`),radians(`lat`),radians(region_longitude),radians(region_latitude)) as 'dist' 
    FROM tmp_grid,geo_region WHERE region_id=searchClosestRegionId(radians(`long`),radians(`lat`),net);
    DROP TEMPORARY TABLE tmp_grid;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-06-20 16:46:37
-- MySQL dump 10.13  Distrib 5.6.24, for osx10.8 (x86_64)
--
-- Host: localhost    Database: ftp
-- ------------------------------------------------------
-- Server version	5.5.5-10.0.25-MariaDB-1~trusty

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfers` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'name of the ftp-user (see users-table)',
  `command` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'the executed command (eg STOR, DELE, etc)',
  `file` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'name of the target file, including the path relative to the user''s home',
  `old_name` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'the old name/path of the file, before an rename-operation',
  `handeled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'custom-fied used by third-party tools to mark an operation as handeled',
  KEY `time` (`time`),
  KEY `handeled` (`handeled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='used to store all upload-, remove- and rename-operations initiated by an ftp-user';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `login` varchar(32) COLLATE utf8_unicode_ci NOT NULL COMMENT 'the login-name of the user (unique)',
  `password` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'md5/sha2-hash of the password',
  `salt` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'prepended salt-string for the password',
  `public_key` text COLLATE utf8_unicode_ci COMMENT 'public-key for sftp (alternative for password)',
  `directory` varchar(256) COLLATE utf8_unicode_ci NOT NULL COMMENT 'home directory, should be /srv/ftp/…',
  `allowed_ips` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'IP-based access control, multiple clients seperated by comma or space "192.168.0.10, 192.168.0.1"',
  `comment` text COLLATE utf8_unicode_ci COMMENT 'optional notes about the user, eg what it is used for',
  PRIMARY KEY (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'ftp'
--

--
-- Dumping routines for database 'ftp'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-06-20 16:46:39
