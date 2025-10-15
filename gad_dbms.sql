-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 06, 2025 at 07:24 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gad_dbms`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `BackfillCertificationsAndNotifications`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `BackfillCertificationsAndNotifications` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_papsID VARCHAR(10);
    DECLARE v_userID VARCHAR(10);
    DECLARE v_finalScore DECIMAL(5,2);
    DECLARE v_title VARCHAR(30);

    DECLARE certCounter INT DEFAULT 1;
    DECLARE notifCounter INT DEFAULT 1;

    DECLARE cur CURSOR FOR
        SELECT papsID, userID, finalScore, title
        FROM PAPs
        WHERE finalScore > 3.9
        AND papsID NOT IN (SELECT papsID FROM Certification);

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_papsID, v_userID, v_finalScore, v_title;
        IF done THEN
            LEAVE read_loop;
        END IF;

        SET @certID = CONCAT('C', LPAD(certCounter, 9, '0'));
        SET @notifID = CONCAT('N', LPAD(notifCounter, 9, '0'));

        INSERT INTO Certification (
            certificationID, papsID, userID, score
        ) VALUES (
            @certID, v_papsID, v_userID, v_finalScore
        );

        INSERT INTO Notifications (
            notifID, recipientID, recipientType, message, relatedPapsID
        ) VALUES (
            @notifID,
            v_userID,
            'EndUser',
            CONCAT('Congratulations! Your PAP titled "', v_title, '" has passed the evaluation and is now certified.'),
            v_papsID
        );

        SET certCounter = certCounter + 1;
        SET notifCounter = notifCounter + 1;
    END LOOP;

    CLOSE cur;
END$$

DROP PROCEDURE IF EXISTS `GetAssignedPAPs`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetAssignedPAPs` (IN `evaluatorIDParam` VARCHAR(10))   BEGIN
    SELECT 
        p.papsID AS docNo,
        p.organization AS college,
        p.title,
        p.dateSubmitted,
        p.status
    FROM 
        assignedeval ae
    JOIN 
        paps p ON ae.papsID = p.papsID
    WHERE 
        ae.evaluatorID = evaluatorIDParam;
END$$

DROP PROCEDURE IF EXISTS `GetPAPsByUser`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetPAPsByUser` (IN `givenUserID` VARCHAR(10))   BEGIN
    SELECT title, dateSubmitted, fileLink, status
    FROM PAPs
    WHERE userID = givenUserID
    ORDER BY dateSubmitted DESC;
END$$

DROP PROCEDURE IF EXISTS `GetUserNotifications`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetUserNotifications` (IN `in_recipientID` VARCHAR(10))   BEGIN
    SELECT 
        notifID,
        message,
        relatedPapsID,
        dateSent,
        isRead
    FROM Notifications
    WHERE recipientID = in_recipientID
    ORDER BY dateSent DESC;
END$$

DROP PROCEDURE IF EXISTS `UpdatePAPStatus`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdatePAPStatus` (IN `p_papsID` VARCHAR(10))   BEGIN
    DECLARE evaluator_count INT;
    DECLARE completed_count INT;
    
    -- Check if PAP has any assigned evaluators
    SELECT COUNT(*) INTO evaluator_count 
    FROM AssignedEval 
    WHERE papsID = p_papsID;
    
    IF evaluator_count = 0 THEN
        -- No evaluators assigned
        UPDATE PAPs 
        SET evaluationStatus = 'unassigned', finalScore = NULL
        WHERE papsID = p_papsID;
    ELSE
        -- Check how many evaluators have completed
        SELECT COUNT(DISTINCT s.evaluatorID) INTO completed_count
        FROM Score s
        JOIN AssignedEval ae ON s.evaluatorID = ae.evaluatorID AND s.papsID = ae.papsID
        WHERE s.papsID = p_papsID;
        
        IF completed_count = evaluator_count THEN
            -- All evaluators have submitted
            UPDATE PAPs 
            SET evaluationStatus = 'completed',
                finalScore = (
                    SELECT AVG(total_score)
                    FROM (
                        SELECT SUM(score) as total_score
                        FROM Score
                        WHERE papsID = p_papsID
                        GROUP BY evaluatorID
                    ) as evaluator_totals
                )
            WHERE papsID = p_papsID;
        ELSE
            -- Some evaluators still pending
            UPDATE PAPs 
            SET evaluationStatus = 'assigned', finalScore = NULL
            WHERE papsID = p_papsID;
        END IF;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `adminID` varchar(10) NOT NULL,
  `fname` varchar(25) NOT NULL,
  `lname` varchar(25) NOT NULL,
  `dob` date DEFAULT NULL,
  `sex` varchar(2) NOT NULL,
  `contactNo` varchar(12) NOT NULL,
  `email` varchar(25) DEFAULT NULL,
  `password` varchar(300) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `last_active` datetime DEFAULT NULL,
  PRIMARY KEY (`adminID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`adminID`, `fname`, `lname`, `dob`, `sex`, `contactNo`, `email`, `password`, `last_active`) VALUES
('ADMIN_001', 'System', 'Administrator', '1990-01-01', 'Ma', '0928263826', 'admin@gad.com', '$2y$10$SeQI0h71IIz/bQo8jh2bL.tfD6wfRjqlHzJcIAdL/Vn2RFfSajyd2', '2025-09-06 14:51:59');

-- --------------------------------------------------------

--
-- Table structure for table `assignedeval`
--

DROP TABLE IF EXISTS `assignedeval`;
CREATE TABLE IF NOT EXISTS `assignedeval` (
  `evaluatorID` varchar(10) NOT NULL,
  `papsID` varchar(10) NOT NULL,
  `adminID` varchar(10) NOT NULL,
  PRIMARY KEY (`evaluatorID`,`papsID`,`adminID`),
  KEY `assignedeval_ibfk_2` (`papsID`),
  KEY `assignedeval_ibfk_3` (`adminID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `assignedeval`
--

INSERT INTO `assignedeval` (`evaluatorID`, `papsID`, `adminID`) VALUES
('E174816451', 'PAPS1048', 'ADMIN_001'),
('E174817375', 'PAPS1048', 'ADMIN_001'),
('E174817596', 'PAPS1048', 'ADMIN_001'),
('E174817619', 'PAPS1048', 'ADMIN_001'),
('E174817640', 'PAPS1048', 'ADMIN_001'),
('E174817647', 'PAPS1048', 'ADMIN_001'),
('E174816451', 'PAPS2089', 'ADMIN_001'),
('E174817375', 'PAPS2089', 'ADMIN_001'),
('E174817596', 'PAPS2089', 'ADMIN_001'),
('E174817619', 'PAPS2089', 'ADMIN_001'),
('E174817640', 'PAPS2089', 'ADMIN_001'),
('E174817647', 'PAPS2089', 'ADMIN_001'),
('E174817375', 'PAPS2331', 'ADMIN_001'),
('E174817596', 'PAPS2331', 'ADMIN_001'),
('E174817647', 'PAPS2331', 'ADMIN_001'),
('E174816451', 'PAPS2819', 'ADMIN_001'),
('E174817375', 'PAPS2819', 'ADMIN_001'),
('E174817596', 'PAPS3080', 'ADMIN_001'),
('E174817619', 'PAPS3080', 'ADMIN_001'),
('E174816451', 'PAPS3620', 'ADMIN_001'),
('E174817596', 'PAPS3620', 'ADMIN_001'),
('E174817647', 'PAPS3620', 'ADMIN_001'),
('E174817647', 'PAPS4277', 'ADMIN_001'),
('E174817375', 'PAPS5328', 'ADMIN_001'),
('E174817647', 'PAPS5328', 'ADMIN_001'),
('E174817596', 'PAPS5435', 'ADMIN_001'),
('E174817375', 'PAPS7032', 'ADMIN_001'),
('E174817596', 'PAPS7991', 'ADMIN_001'),
('E174817647', 'PAPS7991', 'ADMIN_001'),
('E174817596', 'PAPS8267', 'ADMIN_001'),
('E174817647', 'PAPS8267', 'ADMIN_001'),
('E174816451', 'PAPS9126', 'ADMIN_001'),
('E174817375', 'PAPS9126', 'ADMIN_001'),
('E174817596', 'PAPS9126', 'ADMIN_001');

--
-- Triggers `assignedeval`
--
DROP TRIGGER IF EXISTS `trg_after_assign_eval`;
DELIMITER $$
CREATE TRIGGER `trg_after_assign_eval` AFTER INSERT ON `assignedeval` FOR EACH ROW BEGIN
    DECLARE notifID VARCHAR(10);

    -- Generate a random notifID with prefix 'N' and 5 digits
    SET notifID = CONCAT('N', LPAD(FLOOR(RAND() * 99999), 5, '0'));

    -- Insert a notification to the evaluator
    INSERT INTO Notifications (
        notifID, recipientID, recipientType, message, relatedPapsID
    )
    VALUES (
        notifID,
        NEW.evaluatorID,
        'Evaluator',
        CONCAT('You have been assigned a PAP titled "', 
            (SELECT title FROM PAPs WHERE papsID = NEW.papsID), 
        '".'),
        NEW.papsID
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `certification`
--

DROP TABLE IF EXISTS `certification`;
CREATE TABLE IF NOT EXISTS `certification` (
  `certificationID` varchar(10) NOT NULL,
  `papsID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `score` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`certificationID`),
  KEY `userID` (`userID`),
  KEY `papsID` (`papsID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `certification`
--

INSERT INTO `certification` (`certificationID`, `papsID`, `userID`, `score`) VALUES
('C000000001', 'PAPS2331', 'UID_8a063a', 10.89),
('C000000002', 'PAPS2819', 'UID_8a063a', 13.00),
('C000000003', 'PAPS4277', '001', 8.00),
('C000000004', 'PAPS7032', 'UID_8a063a', 7.83),
('C125277930', 'PAPS3080', 'UID_69293b', 10.75),
('C206253789', 'PAPS1048', 'UID_8a063a', 4.00),
('C394667802', 'PAPS7991', 'UID_69293b', 9.24),
('C572921691', 'PAPS8267', 'UID_69293b', 7.59);

--
-- Triggers `certification`
--
DROP TRIGGER IF EXISTS `after_certification_insert`;
DELIMITER $$
CREATE TRIGGER `after_certification_insert` AFTER INSERT ON `certification` FOR EACH ROW BEGIN
    DECLARE newNotifID VARCHAR(10);
    DECLARE papTitle VARCHAR(30);

    -- Get the title of the PAP based on the inserted papsID
    SELECT title INTO papTitle
    FROM PAPs
    WHERE papsID = NEW.papsID;

    -- Generate a random notifID (replace this logic with your own if needed)
    SET newNotifID = CONCAT('N', LPAD(FLOOR(RAND() * 1000000000), 9, '0'));

    -- Insert the notification
    INSERT INTO Notifications (
        notifID,
        recipientID,
        recipientType,
        message,
        relatedPapsID,
        dateSent,
        isRead
    )
    VALUES (
        newNotifID,
        NEW.userID,
        'EndUser',
        CONCAT('Your PAP titled "', papTitle, '" has passed evaluation and is now certified.'),
        NEW.papsID,
        NOW(),
        FALSE
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `comment`
--

DROP TABLE IF EXISTS `comment`;
CREATE TABLE IF NOT EXISTS `comment` (
  `commentID` varchar(10) NOT NULL,
  `comments` varchar(1000) DEFAULT NULL,
  `userid` varchar(10) NOT NULL,
  `evaluatorID` varchar(10) NOT NULL,
  `papsID` varchar(10) NOT NULL,
  PRIMARY KEY (`commentID`),
  KEY `userid` (`userid`),
  KEY `evaluatorID` (`evaluatorID`),
  KEY `papsID` (`papsID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Triggers `comment`
--
DROP TRIGGER IF EXISTS `after_comment_insert`;
DELIMITER $$
CREATE TRIGGER `after_comment_insert` AFTER INSERT ON `comment` FOR EACH ROW BEGIN
    INSERT INTO Notifications (notifID, recipientID, recipientType, message, relatedPapsID)
    VALUES (
        CONCAT('N', LPAD(FLOOR(RAND() * 99999), 5, '0')),
        NEW.userid,
        'EndUser',
        'An evaluator has commented on your PAP.',
        NEW.papsID
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `enduser`
--

DROP TABLE IF EXISTS `enduser`;
CREATE TABLE IF NOT EXISTS `enduser` (
  `userID` varchar(10) NOT NULL,
  `fname` varchar(25) NOT NULL,
  `lname` varchar(25) NOT NULL,
  `mname` varchar(50) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `sex` varchar(2) NOT NULL,
  `contactNo` varchar(12) NOT NULL,
  `email` varchar(25) DEFAULT NULL,
  `address` varchar(25) DEFAULT NULL,
  `orgname` varchar(25) DEFAULT NULL,
  `password` varchar(512) NOT NULL,
  `profile_photo` longblob,
  `last_active` datetime DEFAULT NULL,
  `date_joined` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `enduser`
--

INSERT INTO `enduser` (`userID`, `fname`, `lname`, `mname`, `dob`, `sex`, `contactNo`, `email`, `address`, `orgname`, `password`, `profile_photo`, `last_active`, `date_joined`) VALUES
('001', 'Gaby', 'Molina', 'Cheruvim', '2019-05-16', 'F', '09123456789', 'gabymolina@usep.edu.ph', '123 st. Davao City', 'CIC', '12345', NULL, NULL, '2025-09-06 15:20:36'),
('UID_0e65dd', 'aila', 'fernandez', 'f', NULL, '', '', 'af@email', NULL, NULL, '$2y$10$D7BcKX1.Kn5qPKw0bbBxLetwmoc4zg2EiRbfhBhyUoRlyKZJTS6Ii', NULL, NULL, '2025-09-06 15:20:36'),
('UID_0f715a', 'Michelle ', 'Sumadia', 'P', NULL, '', '', 'michellesumadia@gmail.com', NULL, NULL, '$2y$10$ukQxFfLdMEzlIrXk4FUgIuFC82k5RnP1nB1BeNoUulViQ2TmY1NS6', NULL, NULL, '2025-09-06 15:20:36'),
('UID_36bc95', 'lowi', 'molina', 'c', NULL, '', '', 'l@usep.com', NULL, NULL, '$2y$10$aTZjKKiPrqEgLVATS.pJS.sDecWITFJo1msKjbBXo2JMHDlYBSTE.', NULL, NULL, '2025-09-06 15:20:36'),
('UID_38ad7b', 'System', 'Administrator', NULL, NULL, '', '', 'gaby@usep.com', 'buhangin', 'cic', 'pass', 0xffd8ffe000104a46494600010101004800480000ffe100744578696600004d4d002a000000080005011a0005000000010000004a011b00050000000100000052012800030000000100020000021300030000000100010000c6fe0002000000110000005a0000000000000048000000010000004800000001476f6f676c6520496e632e20323031360000ffe202284943435f50524f46494c450001010000021800000000021000006d6e74725247422058595a2000000000000000000000000061637370000000000000000000000000000000000000000000000000000000010000f6d6000100000000d32d0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000964657363000000f0000000747258595a00000164000000146758595a00000178000000146258595a0000018c0000001472545243000001a00000002867545243000001a00000002862545243000001a00000002877747074000001c80000001463707274000001dc0000003c6d6c756300000000000000010000000c656e5553000000580000001c0073005200470042000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000058595a200000000000006fa2000038f50000039058595a2000000000000062990000b785000018da58595a2000000000000024a000000f840000b6cf706172610000000000040000000266660000f2a700000d59000013d000000a5b000000000000000058595a20000000000000f6d6000100000000d32d6d6c756300000000000000010000000c656e5553000000200000001c0047006f006f0067006c006500200049006e0063002e00200032003000310036ffdb00430006040506050406060506070706080a100a0a09090a140e0f0c1017141818171416161a1d251f1a1b231c1616202c20232627292a29191f2d302d283025282928ffdb0043010707070a080a130a0a13281a161a2828282828282828282828282828282828282828282828282828282828282828282828282828282828282828282828282828ffc2001108004b004b03012200021101031101ffc4001b00000203010101000000000000000000000506020304070100ffc4001801000301010000000000000000000000000102030400ffda000c03010002100310000001e60d8bdd4a1454db5ef0d9cbe228573dd2b804ff004c078ee4ee9fc9cc69c2cd6e02094f191359529e8a6307cac3114cb69702665828e8f17d7a33de7a7daba9a760ad6a746398fe1c9651fb6636962c7a1094b28d51d4109c2b56d18e25da3c6e65f2698152614e9563ad772c6ed915398ee876237d587fffc40023100003000202020202030000000000000001020300041112051410132131152432ffda00080101000105029805faa89ff5d9fa6a9c5d68b37f1d0c4f19034bf8ad70b48c795f5146943d9d96f1324d7fa49c13fad61544adf71279f63b9f659f1a3c0f5df35ead0b43cd7df28375a5367be6bf21d383961d1e355f6a44510c8f39e3a9d5fb309c8f593eeb07d46e71d0336c68d0d759995be3c3846c1c4b04c517d49b37e15da8bcab72ad9dbafc68dc46bad45ac26c0e06c755243497038385b18727e3c4539111d04ff254700fd55a4c050cf86c3e7c6ebfaf25d89319fe99432fe99ebce2a3d1a7afd5313fdd372b45f0f02f7aa7460d8c72207d8a02e739a919beb32280a8b897a452bb9723dab67b35c9ed581f77633ddd8cffc4001e11000202030100030000000000000000000002011103102131121322ffda0008010301013f0149a33cc790263e5b1f4ac0b28b1f912afa2e388f4f958dde1e106392b4cb7a87a224661b26a85719ee35ffc40020110002020202020300000000000000000001020011031012210431132241ffda0008010201013f01cd53c6e557326425a967ca5bd4fb5f7322d88cedf938545156469bd465a20d4bd0e8e8a9308d043a26158175ffc400271000010303020701010101000000000000010002110321311012132232415161a171813372ffda0008010100063f021bb09cd6d1a7da395359c2601dced50da4c3ef6a86d2a73f8af4e9ff001a8034d91ff289a54db6f217f937f8d51c007f5aa9d216dc51e16e0e0333940356d1846c67d20435eefc0b88cb0180b60610e29fb84cae84ca8ccb4ca3b69c387928bfaa54341015c2c2dcdca2caee9dc2d657ed62ad4e891ef470f2b904985c57e55d9640e8da949fb1c3ba2e735c5cdb1f7ad4072b706cf65cc16e7092bd2ce9650da8f8d25d84f734cae5c69257656d3a8eb528bba48946072af4a5c8bcab0d33aee7f53910d2038e90559b850162c80224e827ca2300f85bdf80ad8d2ca35a8e7365c1c8903b043f42a6299896038577fc0babe2eaf8acef8bafe05d7f02ffc4002310010002020201040301000000000000000100112131415110617191f081a1d1f1ffda0008010100013f2119c13982e0e442fcca5c2b743e25caff0035510bfe21828527a18218f68313940543997e5145ae894388e42d88ab42fa8586c4ab43848797a8021346be0610624d3bc9ba894aedf525f4a98577088b4e18a3849dc76aaa2081c99d12fcb1eb985eaa10d842db9d62ca0c1168cb06ddde88e416bbbbf58f6f0c4b7e2f9d080a4f0d298dfb44235379a7e2274184ae282018767338a33f93f732bad4b821471f898a01653de55b02e881a32ee29ad0188326d089985dabb82c38e8ea13991ea54cbabd4cfc046ae1358a55da6d1314b968f67c0f8180c4a0344ca5e1986f9bde0d83a03997ea21c0a88f3794d03ec4638159d31050d773630ea2169fe12ed1995cb1d995b8065adf860a2ca590d55c28777257936cf59324ae5e6445f3de56018f1adb0036cc840425655b273e9059943073980b27dba8ff00853ef118397d9fc855f77ea7dbfca7ffda000c03010002000300000010f262355b6566efdda249ab2c97018bd2ee3bb7ffc4001f11010002020202030000000000000000000100112131105141617191b1ffda0008010301013f10d2ee39366e574770bb77f7080c3e4bfd9713589f9244eb120a5c3284bccb2cbc4a913b210382954ccb0f504d73a0cc1a254fffc4001b11010003010101010000000000000000000100113121105191ffda0008010201013f10b2af6009146767c550e0459b0128ef076300c3fb047b052294d897bb2d6ad81a84d8a654362357e3cb7ca94408cdf4756a38dca9ffc400221001010002020202030101000000000000011100213141516171811091f0b1f1ffda0008010100013f1042910478b821b5111ddec5cd02a196ce2a54f389ab8a187aece717e96c1801caeb8c6e5a7073edb72a689d3c1c7ce21d2655839eb2d269508b23e71be699f70387651be51cafe871104923d388b0fa339113674f279c6ca45df7ef1a44aa1244ff0071e5442653a519f1f3804b6631e785e78f584a84eb12581cd8d3cefc6221b43d38a144e8047260991294e9f581a8820a53aee7ce331b0b698ef59ce65d15f83a31d91b17086bc84c45527e9c6a06952a2250df43ea779548c86aca08e458d1ecf78da93cb27563d71f8022b4882c4ff32591abcebcfbca63e326878cd6dc22407645cf5fbca859d0489f3809c5ca7331ab460f0edc81b9661e723c3bb1cf18502c514672615885c1a273ff007302a7d31c0a59f38c0421e208eb02e8009ba38f9cd4c601437eb0c733485c711c97202dde006f05a4181a1cc29c60de26163146c7ac3a557b842eae003efc6a61a50b850c0f2e6ecd796e1c413a99bdbc43eaea01833299bf8c58a224147ad4fd66d1965bb973765e138f7d153c0c2282af483b432c45f6ae18e70281393f27b34285d542fde5675e06368fa76ef2ca54d572e4355e5329e1c07134e001eb11151a8646fbb6e7d4e70b78610572bf8858d3c80f186968a1c1e2f385c419d0dfce1382147c7ac5d7af792002f2bd64616141de0c0193e7342e2fa08a90664543d558a973626b8bd8a5ef0a7b06f269a8f8323dc3ad58f13fdfacd7c7fbf586f44edbe3a6e4f4727fffd9, NULL, '2025-09-06 15:20:36'),
('UID_5d45d4', 'Michelle', 'Sumadia', 'P', NULL, '', '', 'msumadia@usep.edu.ph', NULL, NULL, '$2y$10$L/lwUlxPxUPL6rt3xp46U.2FiYqGaaoKB64i8ToYP8FkyiC66lmEe', NULL, NULL, '2025-09-06 15:20:36'),
('UID_69293b', 'Sofia', 'Molina', 'S', NULL, '', '', 'sofia@gaby.com', NULL, NULL, '$2y$10$LxESPDrSSwvP1guVCoDZsuKcjD.0uV3u5re1t3vLK6qVWSsr//3D.', NULL, NULL, '2025-09-06 15:20:36'),
('UID_757e6e', 'faith', 'macion', 'c', NULL, '', '', 'mf@email', NULL, NULL, '$2y$10$JPtAjLJQJagMY9jeLQQMgOc7fV7VBZ/m4tVv0NvJEcJclmvB1ilqC', NULL, NULL, '2025-09-06 15:20:36'),
('UID_8a063a', 'lowi', 'dave', 'm', NULL, '', '09123456789', 'gaby@usep.com', 'obrero', 'cic', '$2y$10$05zCP1Wxy.x2CIcTHJDJ9emUeqtbxdDCwM1u0RmtPz.OQkzNQWQJW', 0xffd8ffe000104a46494600010101004800480000ffe100744578696600004d4d002a000000080005011a0005000000010000004a011b00050000000100000052012800030000000100020000021300030000000100010000c6fe0002000000110000005a0000000000000048000000010000004800000001476f6f676c6520496e632e20323031360000ffe202284943435f50524f46494c450001010000021800000000021000006d6e74725247422058595a2000000000000000000000000061637370000000000000000000000000000000000000000000000000000000010000f6d6000100000000d32d0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000964657363000000f000000074, NULL, '2025-09-06 15:20:36'),
('UID_a19dac', 'Amor', 'Trisha', '', NULL, '', '', 'trish@gad.com', NULL, NULL, '$2y$10$PW/vC0JOVOHF0AV4MYaTgubLN0tkNAxQ2cw3/qLI250FR9JDlEJM6', NULL, NULL, '2025-09-06 15:20:36'),
('UID_a4d251', 'Molina', 'Gab', '', NULL, '', '', 'gab@gmail.com', NULL, NULL, '$2y$10$mFptCgicSESyGYHNmfKiLeqloEELamvTzvGZvq5N9dSzWxUALR3Hq', NULL, NULL, '2025-09-06 15:20:36'),
('UID_a5260e', 'testname', 'test', 'c.', NULL, '', '', 'test@email', NULL, NULL, '$2y$10$QiBIpMDu986iijLUKZQI6.weaFLI6/HiF4PUb5SelOqSyDnmMvKc.', NULL, NULL, '2025-09-06 15:20:36');

-- --------------------------------------------------------

--
-- Table structure for table `evaluator`
--

DROP TABLE IF EXISTS `evaluator`;
CREATE TABLE IF NOT EXISTS `evaluator` (
  `evaluatorID` varchar(10) NOT NULL,
  `fname` varchar(25) NOT NULL,
  `lname` varchar(25) NOT NULL,
  `dob` date DEFAULT NULL,
  `sex` varchar(2) NOT NULL,
  `contactNo` varchar(12) NOT NULL,
  `email` varchar(25) DEFAULT NULL,
  `address` varchar(25) DEFAULT NULL,
  `expertise` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `department` varchar(25) DEFAULT NULL,
  `password` varchar(512) NOT NULL,
  `last_active` datetime DEFAULT NULL,
  `date_joined` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`evaluatorID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `evaluator`
--

INSERT INTO `evaluator` (`evaluatorID`, `fname`, `lname`, `dob`, `sex`, `contactNo`, `email`, `address`, `expertise`, `department`, `password`, `last_active`, `date_joined`) VALUES
('E174816451', 'Segn Lee', 'Buslon', '0000-00-00', 'M', '12345678901', 'SBuslon@usep.edu.ph', '', 'Policy Development and Advocacy', 'CAS - College of Arts and', '$2y$10$ep7SNmVKjGBmoxAEcn2PuOkhFQZ80BQEZhS9PgC.fJcZd7W/PJA0S', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E174817375', 'Lady Aila', 'Aparejo', NULL, 'F', '09138219912', 'laaparejo@usep.edu.ph', NULL, 'GAD Specialist II', 'CAEc - College of Applied', '$2y$10$/YX3HT0Tk97mqC8CTy07VuSWC4GtxegwAdgr8oG7MOSY3XFeA3XgC', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E174817596', 'Cheruvim Gabrielle', 'Molina', NULL, 'F', '09876543211', 'cgmolina@usep.edu.ph', NULL, 'Senior GAD Specialist', 'CIC - College of Informat', '$2y$10$4R8eCtm6OHdMlJNjM48QHO/0SF7bWdfeV2Gm4HOHEP5Z3IM3DtueG', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E174817619', 'Louie Jay', 'Macion', NULL, 'M', '09998761217', 'lmacion@usep.edu.ph', NULL, 'GAD Specialist I', 'CoE - College of Engineer', '$2y$10$LUFWPYyC5S8fjhsfCJlvQe9Bl7mQh0XZEFu6tYHW0Cr4c6qpKm1dK', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E174817640', 'Michelle Faith', 'Sumadia', NULL, 'F', '09876543210', 'mfpsumadia@usep.edu.ph', NULL, 'GAD Specialist II', 'CT - College of Technolog', '$2y$10$6LiyanqE5ScaEoLyWX4IvecnDN5zjS3JXDslT6GarnsNPU5ltWDAK', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E174817647', 'Trisha Amor', 'Mae', NULL, 'F', '12345678901', 'TAmor@usep.edu.ph', NULL, 'GAD Analyst', 'CoE - College of Engineer', '$2y$10$C0K7ET4vMTni72tyVzu1..YmzqH.mVhEY0aqqlGuMfge76gxCThC2', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E174822368', 'Emmanuel', 'Braza', NULL, 'F', '12345677901', 'Ebraza@usep.edu.ph', NULL, 'GAD Analyst I', 'CED - College of Educatio', '$2y$10$zX8CxZUIQN3Q3fnuwqSk9uZC1L5UfDGUl/6jFciVwTHhJbcGF3qvG', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E175708177', 'Gojo', 'Satoru', '0000-00-00', 'M', '09305612064', 'gojo@gmail.com', '', 'Senior GAD Specialist', 'CAS - College of Arts and', '$2y$10$x8NcZsHpN7Wo0yGsE83I9OtjiqBgMK.jo4C2bip/3jflVSVfVpMCu', '2025-09-06 14:36:24', '2025-09-06 15:20:36'),
('E175709927', 'Hayakawa', 'Denji', '0000-00-00', 'M', '09305612064', 'd@gad.com', '', 'Cic', 'CoE - College of Engineer', '$2y$10$j1m2M2GjX/cwGI9IzNboe.WAnVO/LOEi/VS7NTuLCfJUbg/pJ/WZC', '2025-09-06 14:36:24', '2025-09-06 15:20:36');

-- --------------------------------------------------------

--
-- Table structure for table `followup`
--

DROP TABLE IF EXISTS `followup`;
CREATE TABLE IF NOT EXISTS `followup` (
  `userID` varchar(10) NOT NULL,
  `papsID` varchar(10) NOT NULL,
  `evaluatorID` varchar(10) NOT NULL,
  `dateRequested` date DEFAULT NULL,
  PRIMARY KEY (`userID`,`papsID`,`evaluatorID`),
  KEY `evaluatorID` (`evaluatorID`),
  KEY `papsID` (`papsID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Triggers `followup`
--
DROP TRIGGER IF EXISTS `after_followup_insert`;
DELIMITER $$
CREATE TRIGGER `after_followup_insert` AFTER INSERT ON `followup` FOR EACH ROW BEGIN
    INSERT INTO Notifications (notifID, recipientID, recipientType, message, relatedPapsID)
    VALUES (
        CONCAT('N', LPAD(FLOOR(RAND() * 99999), 5, '0')),
        NEW.evaluatorID,
        'Evaluator',
        'You have received a follow-up request from a user.',
        NEW.papsID
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notifID` varchar(10) NOT NULL,
  `recipientID` varchar(10) NOT NULL,
  `recipientType` enum('Admin','Evaluator','EndUser') NOT NULL,
  `message` text NOT NULL,
  `relatedPapsID` varchar(10) DEFAULT NULL,
  `dateSent` datetime DEFAULT CURRENT_TIMESTAMP,
  `isRead` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`notifID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notifID`, `recipientID`, `recipientType`, `message`, `relatedPapsID`, `dateSent`, `isRead`) VALUES
('N000000001', 'UID_8a063a', 'EndUser', 'Congratulations! Your PAP titled \"Supporting Women Entrepreneurs\" has passed the evaluation and is now certified.', 'PAPS2331', '2025-05-29 22:30:10', 0),
('N000000002', 'UID_8a063a', 'EndUser', 'Congratulations! Your PAP titled \"Strengthening Women\'s Particip\" has passed the evaluation and is now certified.', 'PAPS2819', '2025-05-29 22:30:10', 0),
('N000000003', '001', 'EndUser', 'Congratulations! Your PAP titled \"Strengthening GAD Technical Wo\" has passed the evaluation and is now certified.', 'PAPS4277', '2025-05-29 22:30:10', 0),
('N000000004', 'UID_8a063a', 'EndUser', 'Congratulations! Your PAP titled \"Community-Based Initiatives fo\" has passed the evaluation and is now certified.', 'PAPS7032', '2025-05-29 22:30:10', 0),
('N01020', 'ADMIN_001', 'Admin', 'A new PAPs titled \"test1\" has been submitted.', 'PAPS1048', '2025-05-25 17:06:27', 0),
('N03711', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"test1\".', 'PAPS1048', '2025-05-25 23:07:25', 0),
('N039760662', 'UID_8a063a', 'EndUser', 'Your PAP titled \"Supporting Women Entrepreneurs\" has passed evaluation and is now certified.', 'PAPS2331', '2025-05-29 22:30:10', 0),
('N05745', 'E174816451', 'Evaluator', 'You have been assigned a PAP titled \"Addressing Gender-Based Violence\".', 'PAPS3620', '2025-05-26 11:27:33', 0),
('N06790', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"test3\".', 'PAPS2819', '2025-05-25 20:23:15', 0),
('N07122', 'ADMIN_001', 'Admin', 'A new PAPs titled \"dhgj\" has been submitted.', 'PAPS3620', '2025-05-25 17:08:04', 0),
('N074301549', 'UID_8a063a', 'EndUser', 'Your PAP titled \"Gender Sensitization Training\" has passed evaluation and is now certified.', 'PAPS1048', '2025-05-29 22:04:34', 0),
('N09130', 'E174817619', 'Evaluator', 'You have been assigned a PAP titled \"dhgj\".', 'PAPS2089', '2025-05-25 23:10:15', 0),
('N125067537', 'UID_69293b', 'EndUser', 'Your PAP titled \"GAD Database Development: Trac\" has passed evaluation and is now certified.', 'PAPS7991', '2025-05-30 11:09:16', 0),
('N15715', 'ADMIN_001', 'Admin', 'A new PAPs titled \"TestforGaby\" has been submitted.', 'PAPS8744', '2025-05-26 07:58:26', 0),
('N194470772', 'UID_69293b', 'EndUser', 'Your PAP titled \"Redefining Masculinity: GAD Se\" has passed evaluation and is now certified.', 'PAPS8267', '2025-05-30 11:17:06', 0),
('N19872', 'E174816451', 'Evaluator', 'You have been assigned a PAP titled \"trisha\".', 'PAPS9126', '2025-05-25 21:34:25', 0),
('N20934', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"GAD Database Development: Tracking Gender Indicators and Interventions\".', 'PAPS3080', '2025-05-30 10:27:10', 0),
('N25918', 'ADMIN_001', 'Admin', 'A new PAPs titled \"PAPS 1\" has been submitted.', 'PAPS3878', '2025-05-26 10:56:49', 0),
('N27493', 'A001', 'Admin', 'A new PAPs titled \"test 2\" has been submitted.', 'PAPS5328', '2025-05-23 17:21:45', 0),
('N29013', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"dhgj\".', 'PAPS2089', '2025-05-25 23:10:15', 0),
('N32560', 'E174817619', 'Evaluator', 'You have been assigned a PAP titled \"test1\".', 'PAPS1048', '2025-05-25 23:07:25', 0),
('N35567', 'E174817619', 'Evaluator', 'You have been assigned a PAP titled \"GAD Database Development: Tracking Gender Indicators and Interventions\".', 'PAPS3080', '2025-05-30 10:27:10', 0),
('N366708299', 'UID_8a063a', 'EndUser', 'Your PAP titled \"Strengthening Women\'s Particip\" has passed evaluation and is now certified.', 'PAPS2819', '2025-05-29 22:30:10', 0),
('N37607', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"pangit si lowiii\".', 'PAPS4197', '2025-05-25 21:04:19', 0),
('N382647163', 'UID_69293b', 'EndUser', 'Congratulations! Your PAP titled \"Redefining Masculinity: GAD Sessions for Male Youth and Fathers\" has passed the evaluation and is now certified.', 'PAPS8267', '2025-05-30 11:17:06', 0),
('N39623', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"trisha\".', 'PAPS9126', '2025-05-25 20:04:42', 0),
('N44536', 'ADMIN_001', 'Admin', 'A new PAPs titled \"vcvnmbnmnb\" has been submitted.', 'PAPS7032', '2025-05-25 17:08:22', 0),
('N45528', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"Redefining Masculinity: GAD Sessions for Male Youth and Fathers\".', 'PAPS8267', '2025-05-30 11:12:52', 0),
('N45960', 'A001', 'Admin', 'A new PAPs titled \"srsd\" has been submitted.', 'PAPS8103', '2025-05-25 17:07:47', 0),
('N46357', 'ADMIN_001', 'Admin', 'A new PAPs titled \"TestforGaby\" has been submitted.', 'PAPS9812', '2025-05-26 07:58:12', 0),
('N46789', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"GAD Database Development: Tracking Gender Indicators and Interventions\".', 'PAPS7991', '2025-05-30 10:50:56', 0),
('N471172838', 'UID_8a063a', 'EndUser', 'Your PAP titled \"Community-Based Initiatives fo\" has passed evaluation and is now certified.', 'PAPS7032', '2025-05-29 22:30:10', 0),
('N47866', 'A001', 'Admin', 'A new PAPs titled \"test3\" has been submitted.', 'PAPS2819', '2025-05-25 17:06:56', 0),
('N48589', 'ADMIN_001', 'Admin', 'A new PAPs titled \"dhgj\" has been submitted.', 'PAPS2089', '2025-05-25 17:08:28', 0),
('N49510', 'A001', 'Admin', 'A new PAPs titled \"dhgj\" has been submitted.', 'PAPS3620', '2025-05-25 17:08:04', 0),
('N50136', 'ADMIN_001', 'Admin', 'A new PAPs titled \"test3\" has been submitted.', 'PAPS2819', '2025-05-25 17:06:56', 0),
('N51123', 'ADMIN_001', 'Admin', 'A new PAPs titled \"GAD Database Development: Tracking Gender Indicators and Interventions\" has been submitted.', 'PAPS3080', '2025-05-30 10:24:59', 0),
('N51671', 'E174816451', 'Evaluator', 'You have been assigned a PAP titled \"test1\".', 'PAPS1048', '2025-05-25 23:07:25', 0),
('N53704', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"Addressing Gender-Based Violence\".', 'PAPS3620', '2025-05-26 11:27:33', 0),
('N54223', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"trisha\".', 'PAPS9126', '2025-05-25 21:34:25', 0),
('N54387', 'ADMIN_001', 'Admin', 'A new PAPs titled \"pangit si lowiii\" has been submitted.', 'PAPS4197', '2025-05-25 17:09:33', 0),
('N54599', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"pangit si lowiii\".', 'PAPS4197', '2025-05-25 21:04:19', 0),
('N58613', 'E174816451', 'Evaluator', 'You have been assigned a PAP titled \"dhgj\".', 'PAPS2089', '2025-05-25 23:10:15', 0),
('N58642', 'ADMIN_001', 'Admin', 'A new PAPs titled \"aaaaa\" has been submitted.', 'PAP001', '2025-05-24 07:59:30', 0),
('N59000', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"Redefining Masculinity: GAD Sessions for Male Youth and Fathers\".', 'PAPS8267', '2025-05-30 11:12:52', 0),
('N59838', 'A001', 'Admin', 'A new PAPs titled \"test1\" has been submitted.', 'PAPS1048', '2025-05-25 17:06:27', 0),
('N60176', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"pangit si lowiii\".', 'PAPS4197', '2025-05-25 21:04:19', 0),
('N60631', 'A001', 'Admin', 'A new PAPs titled \"pangit si lowiii\" has been submitted.', 'PAPS4197', '2025-05-25 17:09:33', 0),
('N60633', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"test2\".', 'PAPS2331', '2025-05-26 00:23:10', 0),
('N60675', 'E174817640', 'Evaluator', 'You have been assigned a PAP titled \"test1\".', 'PAPS1048', '2025-05-25 23:07:25', 0),
('N61997', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"test1\".', 'PAPS1048', '2025-05-25 23:07:25', 0),
('N62411', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"test 2\".', 'PAPS5328', '2025-05-25 21:39:53', 0),
('N62974', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"test 2\".', 'PAPS5328', '2025-05-25 21:39:53', 0),
('N65674', 'E174817640', 'Evaluator', 'You have been assigned a PAP titled \"dhgj\".', 'PAPS2089', '2025-05-25 23:10:15', 0),
('N66630', 'ADMIN_001', 'Admin', 'A new PAPs titled \"Women in Tech: Basic Digital Skills Training for Mothers and Single Parents\" has been submitted.', 'PAPS5435', '2025-05-30 13:03:31', 0),
('N68409', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"Document 1\".', 'PAPS4277', '2025-05-25 21:26:53', 0),
('N68631', 'A001', 'Admin', 'A new PAPs titled \"trisha\" has been submitted.', 'PAPS9126', '2025-05-25 17:09:46', 0),
('N68759', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"test1\".', 'PAPS1048', '2025-05-25 23:07:25', 0),
('N70077', 'A001', 'Admin', 'A new PAPs titled \"vcvnmbnmnb\" has been submitted.', 'PAPS7032', '2025-05-25 17:08:22', 0),
('N71062', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"GAD Database Development: Tracking Gender Indicators and Interventions\".', 'PAPS7991', '2025-05-30 10:50:56', 0),
('N71081', 'ADMIN_001', 'Admin', 'A new PAPs titled \"GAD TITLE\" has been submitted.', 'PAPS7593', '2025-05-26 10:43:00', 0),
('N714259541', '001', 'EndUser', 'Your PAP titled \"Strengthening GAD Technical Wo\" has passed evaluation and is now certified.', 'PAPS4277', '2025-05-29 22:30:10', 0),
('N72249', 'A001', 'Admin', 'A new PAPs titled \"doc1\" has been submitted.', 'PAPS2581', '2025-05-23 18:27:49', 0),
('N72741', 'A001', 'Admin', 'A new PAPs titled \"gaby\" has been submitted.', 'PAPS5536', '2025-05-25 17:09:39', 0),
('N730158863', 'UID_69293b', 'EndUser', 'Your PAP titled \"GAD Database Development: Trac\" has passed evaluation and is now certified.', 'PAPS3080', '2025-05-30 10:45:29', 0),
('N73626', 'E174816451', 'Evaluator', 'You have been assigned a PAP titled \"test3\".', 'PAPS2819', '2025-05-25 20:23:15', 0),
('N73650', 'ADMIN_001', 'Admin', 'A new PAPs titled \"Redefining Masculinity: GAD Sessions for Male Youth and Fathers\" has been submitted.', 'PAPS8267', '2025-05-30 11:10:37', 0),
('N74829', 'ADMIN_001', 'Admin', 'A new PAPs titled \"GAD Database Development: Tracking Gender Indicators and Interventions\" has been submitted.', 'PAPS7991', '2025-05-30 10:25:02', 0),
('N78645', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"dhgj\".', 'PAPS2089', '2025-05-25 23:10:15', 0),
('N80854', 'ADMIN_001', 'Admin', 'A new PAPs titled \"gaby\" has been submitted.', 'PAPS5536', '2025-05-25 17:09:39', 0),
('N83472', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"test2\".', 'PAPS2331', '2025-05-26 00:23:10', 0),
('N842552106', 'UID_69293b', 'EndUser', 'We regret to inform you that your PAP titled \"Women in Tech: Basic Digital Skills Training for Mothers and Single Parents\" did not pass the evaluation.', 'PAPS5435', '2025-05-30 14:05:48', 0),
('N85576', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"test2\".', 'PAPS2331', '2025-05-26 00:23:10', 0),
('N86666', 'E174817596', 'Evaluator', 'You have been assigned a PAP titled \"Women in Tech: Basic Digital Skills Training for Mothers and Single Parents\".', 'PAPS5435', '2025-05-30 13:04:51', 0),
('N88166', 'E174817375', 'Evaluator', 'You have been assigned a PAP titled \"vcvnmbnmnb\".', 'PAPS7032', '2025-05-25 20:04:42', 0),
('N88385', 'ADMIN_001', 'Admin', 'A new PAPs titled \"trisha\" has been submitted.', 'PAPS9126', '2025-05-25 17:09:46', 0),
('N88678', 'A001', 'Admin', 'A new PAPs titled \"dhgj\" has been submitted.', 'PAPS2089', '2025-05-25 17:08:28', 0),
('N90704', 'ADMIN_001', 'Admin', 'A new PAPs titled \"spc\" has been submitted.', 'adkaida', '2025-05-26 08:02:28', 0),
('N91357', 'A001', 'Admin', 'A new PAPs titled \"Document 1\" has been submitted.', 'PAPS4277', '2025-05-22 17:44:06', 0),
('N92033', 'ADMIN_001', 'Admin', 'A new PAPs titled \"srsd\" has been submitted.', 'PAPS8103', '2025-05-25 17:07:47', 0),
('N94187', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"Addressing Gender-Based Violence\".', 'PAPS3620', '2025-05-26 11:27:33', 0),
('N94365', 'A001', 'Admin', 'A new PAPs titled \"test2\" has been submitted.', 'PAPS2331', '2025-05-25 17:06:40', 0),
('N94873', 'ADMIN_001', 'Admin', 'A new PAPs titled \"Healthy Women, Healthy Nation: GAD-based Maternal Health Awareness\" has been submitted.', 'PAPS5832', '2025-05-30 19:11:57', 0),
('N96239', 'A001', 'Admin', 'A new PAPs titled \"aaaaa\" has been submitted.', 'PAP001', '2025-05-24 07:59:30', 0),
('N97357', 'E174817647', 'Evaluator', 'You have been assigned a PAP titled \"dhgj\".', 'PAPS2089', '2025-05-25 23:10:00', 0),
('N97760', 'ADMIN_001', 'Admin', 'A new PAPs titled \"test2\" has been submitted.', 'PAPS2331', '2025-05-25 17:06:40', 0);

-- --------------------------------------------------------

--
-- Table structure for table `paps`
--

DROP TABLE IF EXISTS `paps`;
CREATE TABLE IF NOT EXISTS `paps` (
  `papsID` varchar(10) NOT NULL,
  `userID` varchar(10) NOT NULL,
  `title` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `organization` varchar(25) NOT NULL,
  `dateSubmitted` date NOT NULL DEFAULT (curdate()),
  `fileLink` varchar(1000) NOT NULL,
  `status` enum('unassigned','pending','completed') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'unassigned',
  `finalScore` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`papsID`),
  KEY `paps_ibfk_1` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `paps`
--

INSERT INTO `paps` (`papsID`, `userID`, `title`, `organization`, `dateSubmitted`, `fileLink`, `status`, `finalScore`) VALUES
('PAPS1048', 'UID_8a063a', 'Gender Sensitization Training', 'CIC', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'completed', 4.00),
('PAPS2089', 'UID_8a063a', 'Gender-Responsive Budgeting In CIC', 'CT', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?usp=drive_link', 'pending', NULL),
('PAPS2331', 'UID_8a063a', 'Supporting Women Entrepreneurs', 'CIC', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'completed', 10.89),
('PAPS2581', 'UID_8a063a', 'GAD Orientation for New Hires and Frontline Service Providers', 'CIC', '2025-05-23', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'unassigned', NULL),
('PAPS2819', 'UID_8a063a', 'Strengthening Women\'s Particip', 'CIC', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'completed', 13.00),
('PAPS3080', 'UID_69293b', 'GAD Database Development: Tracking Gender Indicators and Interventions', 'CIC', '2025-05-30', 'https://docs.google.com/document/d/1x7pdSCyZLsGIaGowUVunMUi_pBDbqfNI/edit?usp=sharing&ouid=104642461178201172897&rtpof=true&sd=true', 'completed', 10.75),
('PAPS3620', 'UID_8a063a', 'Addressing Gender-Based Violence', 'CT', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?usp=drive_link', 'pending', NULL),
('PAPS4277', '001', 'Strengthening GAD Technical Working Groups (TWGs)', 'CIC', '2025-05-22', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'completed', 8.00),
('PAPS5328', '001', 'Impact Assessment of GAD Interventions', 'CAS', '2025-05-23', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'pending', NULL),
('PAPS5435', 'UID_69293b', 'Women in Tech: Basic Digital Skills Training for Mothers and Single Parents', 'CAS', '2025-05-30', 'https://docs.google.com/document/d/1sxSXkblcVixtV47kr3t8jyCN_n92Oo-JhdrMRfkc2_8/edit?usp=sharing', 'completed', 0.50),
('PAPS5536', 'UID_8a063a', 'Addressing Gender Disparities in Obrero', 'CIC', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'unassigned', NULL),
('PAPS5832', 'UID_69293b', 'Healthy Women, Healthy Nation: GAD-based Maternal Health Awareness', 'CT', '2025-05-30', 'https://docs.google.com/document/d/1sxSXkblcVixtV47kr3t8jyCN_n92Oo-JhdrMRfkc2_8/edit?usp=sharing', 'unassigned', NULL),
('PAPS7032', 'UID_8a063a', 'Community-Based Initiatives for Gender Equality', 'CBA', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?usp=drive_link', 'completed', 7.83),
('PAPS7991', 'UID_69293b', 'GAD Database Development: Tracking Gender Indicators and Interventions', 'CIC', '2025-05-30', 'https://docs.google.com/document/d/1x7pdSCyZLsGIaGowUVunMUi_pBDbqfNI/edit?usp=sharing&ouid=104642461178201172897&rtpof=true&sd=true', 'completed', 9.24),
('PAPS8103', 'UID_8a063a', 'Ensuring Safe Spaces ', 'CEd', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'unassigned', NULL),
('PAPS8267', 'UID_69293b', 'Redefining Masculinity: GAD Sessions for Male Youth and Fathers', 'CEd', '2025-05-30', 'https://docs.google.com/document/d/1x7pdSCyZLsGIaGowUVunMUi_pBDbqfNI/edit?usp=sharing&ouid=104642461178201172897&rtpof=true&sd=true', 'completed', 7.59),
('PAPS9126', 'UID_8a063a', 'Conduct of Gender Analysis ', 'CIC', '2025-05-25', 'https://docs.google.com/document/d/1pmTrB2_67chfPV6R7Z38tpao85P7tf4flK0-yl28I3g/edit?tab=t.0', 'pending', NULL);

--
-- Triggers `paps`
--
DROP TRIGGER IF EXISTS `after_new_pap_insert`;
DELIMITER $$
CREATE TRIGGER `after_new_pap_insert` AFTER INSERT ON `paps` FOR EACH ROW BEGIN
    INSERT INTO Notifications (notifID, recipientID, recipientType, message, relatedPapsID)
    SELECT 
        CONCAT('N', LPAD(FLOOR(RAND() * 99999), 5, '0')), -- random notifID
        adminID,
        'Admin',
        CONCAT('A new PAPs titled "', NEW.title, '" has been submitted.'),
        NEW.papsID
    FROM Admin;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_update_finalScore`;
DELIMITER $$
CREATE TRIGGER `after_update_finalScore` AFTER UPDATE ON `paps` FOR EACH ROW BEGIN
    DECLARE newCertID VARCHAR(10);
    DECLARE notifID VARCHAR(10);
    DECLARE tempUserID VARCHAR(10);
    DECLARE papTitle VARCHAR(100);

    -- Only proceed if finalScore is not NULL, changed, and exceeds 3.9
    IF NEW.finalScore IS NOT NULL AND (OLD.finalScore IS NULL OR NEW.finalScore <> OLD.finalScore) AND NEW.finalScore > 3.9 THEN

        -- Check if a certification already exists
        IF NOT EXISTS (
            SELECT 1 FROM Certification WHERE papsID = NEW.papsID
        ) THEN

            -- Fetch userID and title explicitly in case they're not part of NEW context
            SELECT userID, title
            INTO tempUserID, papTitle
            FROM PAPs
            WHERE papsID = NEW.papsID;

            -- Generate IDs
            SET newCertID = CONCAT('C', LPAD(FLOOR(RAND() * 1000000000), 9, '0'));
            SET notifID = CONCAT('N', LPAD(FLOOR(RAND() * 1000000000), 9, '0'));

            -- Insert into Certification
            INSERT INTO Certification (
                certificationID,
                papsID,
                userID,
                score
            ) VALUES (
                newCertID,
                NEW.papsID,
                tempUserID,
                NEW.finalScore
            );

            -- Insert into Notifications
            INSERT INTO Notifications (
                notifID,
                recipientID,
                recipientType,
                message,
                relatedPapsID
            ) VALUES (
                notifID,
                tempUserID,
                'EndUser',
                CONCAT('Congratulations! Your PAP titled "', papTitle, '" has passed the evaluation and is now certified.'),
                NEW.papsID
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_notify_failed_pap`;
DELIMITER $$
CREATE TRIGGER `trg_notify_failed_pap` AFTER UPDATE ON `paps` FOR EACH ROW BEGIN
    DECLARE notifID VARCHAR(10);

    -- Only notify if finalScore was changed and is 3.9 or below
    IF NEW.finalScore IS NOT NULL AND NEW.finalScore <> OLD.finalScore AND NEW.finalScore <= 3.9 THEN

        -- Prevent duplicate failure notifications
        IF NOT EXISTS (
            SELECT 1 FROM Notifications
            WHERE relatedPapsID = NEW.papsID
              AND recipientID = NEW.userID
              AND message LIKE '%did not pass%'
        ) THEN

            -- Generate unique notification ID
            SET notifID = CONCAT('N', LPAD(FLOOR(RAND() * 1000000000), 9, '0'));

            -- Insert failure notification with title included
            INSERT INTO Notifications (
                notifID,
                recipientID,
                recipientType,
                message,
                relatedPapsID
            )
            VALUES (
                notifID,
                NEW.userID,
                'EndUser',
                CONCAT('We regret to inform you that your PAP titled "', NEW.title, '" did not pass the evaluation.'),
                NEW.papsID
            );
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `score`
--

DROP TABLE IF EXISTS `score`;
CREATE TABLE IF NOT EXISTS `score` (
  `itemID` varchar(10) NOT NULL,
  `papsID` varchar(10) NOT NULL,
  `evaluatorID` varchar(10) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `versionID` varchar(255) NOT NULL,
  PRIMARY KEY (`itemID`,`papsID`,`evaluatorID`),
  KEY `evaluatorID` (`evaluatorID`),
  KEY `papsID` (`papsID`),
  KEY `fk_score_version` (`versionID`,`itemID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `score`
--

INSERT INTO `score` (`itemID`, `papsID`, `evaluatorID`, `score`, `versionID`) VALUES
('TEST1', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST1', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST1', 'PAPS2089', 'E174817596', 0.50, 'V6833E0092'),
('TEST1', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST1', 'PAPS2331', 'E174817375', 0.50, 'V68385A037'),
('TEST1', 'PAPS2331', 'E174817596', 0.50, 'V68385A037'),
('TEST1', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST1', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST1', 'PAPS3080', 'E174817596', 0.50, 'V68385A037'),
('TEST1', 'PAPS3080', 'E174817619', 0.50, 'V68385A037'),
('TEST1', 'PAPS3620', 'E174817596', 1.00, 'V6833E0092'),
('TEST1', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST1', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST1', 'PAPS5435', 'E174817596', 0.50, 'V68385A037'),
('TEST1', 'PAPS7032', 'E174817375', 0.50, ''),
('TEST1', 'PAPS7991', 'E174817596', 0.50, 'V68385A037'),
('TEST1', 'PAPS7991', 'E174817647', 0.50, 'V68385A037'),
('TEST1', 'PAPS8267', 'E174817596', 0.50, 'V68385A037'),
('TEST1', 'PAPS8267', 'E174817647', 0.50, 'V68385A037'),
('TEST1', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST1', 'PAPS9126', 'E174817375', 0.50, 'V68385A037'),
('TEST1-1', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST1-1', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST1-1', 'PAPS2089', 'E174817596', 0.00, 'V6833E0092'),
('TEST1-1', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST1-1', 'PAPS2331', 'E174817375', 0.50, 'V68385A037'),
('TEST1-1', 'PAPS2331', 'E174817596', 0.00, 'V68385A037'),
('TEST1-1', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST1-1', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST1-1', 'PAPS3080', 'E174817596', 1.00, 'V68385A037'),
('TEST1-1', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST1-1', 'PAPS3620', 'E174817596', 0.50, 'V6833E0092'),
('TEST1-1', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST1-1', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST1-1', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST1-1', 'PAPS7032', 'E174817375', 1.00, ''),
('TEST1-1', 'PAPS7991', 'E174817596', 0.50, 'V68385A037'),
('TEST1-1', 'PAPS7991', 'E174817647', 0.50, 'V68385A037'),
('TEST1-1', 'PAPS8267', 'E174817596', 0.00, 'V68385A037'),
('TEST1-1', 'PAPS8267', 'E174817647', 0.50, 'V68385A037'),
('TEST1-1', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST1-1', 'PAPS9126', 'E174817375', 0.00, 'V68385A037'),
('TEST10', 'PAPS2331', 'E174817375', 0.67, 'V68385A037'),
('TEST10', 'PAPS2331', 'E174817596', 0.33, 'V68385A037'),
('TEST10', 'PAPS3080', 'E174817596', 0.33, 'V68385A037'),
('TEST10', 'PAPS3080', 'E174817619', 0.33, 'V68385A037'),
('TEST10', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST10', 'PAPS7991', 'E174817596', 0.33, 'V68385A037'),
('TEST10', 'PAPS7991', 'E174817647', 0.33, 'V68385A037'),
('TEST10', 'PAPS8267', 'E174817596', 0.00, 'V68385A037'),
('TEST10', 'PAPS8267', 'E174817647', 0.33, 'V68385A037'),
('TEST10', 'PAPS9126', 'E174817375', 0.33, 'V68385A037'),
('TEST10-1', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST10-1', 'PAPS2331', 'E174817596', 0.33, 'V68385A037'),
('TEST10-1', 'PAPS3080', 'E174817596', 0.67, 'V68385A037'),
('TEST10-1', 'PAPS3080', 'E174817619', 0.67, 'V68385A037'),
('TEST10-1', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST10-1', 'PAPS7991', 'E174817596', 0.33, 'V68385A037'),
('TEST10-1', 'PAPS7991', 'E174817647', 0.67, 'V68385A037'),
('TEST10-1', 'PAPS8267', 'E174817596', 0.67, 'V68385A037'),
('TEST10-1', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST10-1', 'PAPS9126', 'E174817375', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS2331', 'E174817596', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS3080', 'E174817596', 0.67, 'V68385A037'),
('TEST10-2', 'PAPS3080', 'E174817619', 0.67, 'V68385A037'),
('TEST10-2', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS7991', 'E174817596', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS8267', 'E174817596', 0.67, 'V68385A037'),
('TEST10-2', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST10-2', 'PAPS9126', 'E174817375', 0.00, 'V68385A037'),
('TEST2', 'PAPS2089', 'E174816451', 2.00, ''),
('TEST2', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST2', 'PAPS2089', 'E174817596', 1.00, 'V6833E0092'),
('TEST2', 'PAPS2089', 'E174817647', 2.00, ''),
('TEST2', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST2', 'PAPS2331', 'E174817596', 1.00, 'V68385A037'),
('TEST2', 'PAPS2331', 'E174817647', 2.00, ''),
('TEST2', 'PAPS2819', 'E174816451', 2.00, ''),
('TEST2', 'PAPS3080', 'E174817596', 1.00, 'V68385A037'),
('TEST2', 'PAPS3080', 'E174817619', 2.00, 'V68385A037'),
('TEST2', 'PAPS3620', 'E174817596', 2.00, 'V6833E0092'),
('TEST2', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST2', 'PAPS5328', 'E174817647', 2.00, ''),
('TEST2', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST2', 'PAPS7032', 'E174817375', 1.00, ''),
('TEST2', 'PAPS7991', 'E174817596', 2.00, 'V68385A037'),
('TEST2', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST2', 'PAPS8267', 'E174817596', 2.00, 'V68385A037'),
('TEST2', 'PAPS8267', 'E174817647', 1.00, 'V68385A037'),
('TEST2', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST2', 'PAPS9126', 'E174817375', 1.00, 'V68385A037'),
('TEST3', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST3', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST3', 'PAPS2089', 'E174817596', 0.50, 'V6833E0092'),
('TEST3', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST3', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST3', 'PAPS2331', 'E174817596', 0.50, 'V68385A037'),
('TEST3', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST3', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST3', 'PAPS3080', 'E174817596', 0.00, 'V68385A037'),
('TEST3', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST3', 'PAPS3620', 'E174817596', 0.50, 'V6833E0092'),
('TEST3', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST3', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST3', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST3', 'PAPS7032', 'E174817375', 1.00, ''),
('TEST3', 'PAPS7991', 'E174817596', 0.50, 'V68385A037'),
('TEST3', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST3', 'PAPS8267', 'E174817596', 0.50, 'V68385A037'),
('TEST3', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST3', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST3', 'PAPS9126', 'E174817375', 1.00, 'V68385A037'),
('TEST3-1', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST3-1', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST3-1', 'PAPS2089', 'E174817596', 0.50, 'V6833E0092'),
('TEST3-1', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST3-1', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS2331', 'E174817596', 0.50, 'V68385A037'),
('TEST3-1', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST3-1', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST3-1', 'PAPS3080', 'E174817596', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS3080', 'E174817619', 0.50, 'V68385A037'),
('TEST3-1', 'PAPS3620', 'E174817596', 0.50, 'V6833E0092'),
('TEST3-1', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST3-1', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST3-1', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS7032', 'E174817375', 1.00, ''),
('TEST3-1', 'PAPS7991', 'E174817596', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS8267', 'E174817596', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST3-1', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST3-1', 'PAPS9126', 'E174817375', 0.50, 'V68385A037'),
('TEST4', 'PAPS2089', 'E174816451', 2.00, ''),
('TEST4', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST4', 'PAPS2089', 'E174817596', 1.00, 'V6833E0092'),
('TEST4', 'PAPS2089', 'E174817647', 2.00, ''),
('TEST4', 'PAPS2331', 'E174817375', 1.00, 'V68385A037'),
('TEST4', 'PAPS2331', 'E174817596', 2.00, 'V68385A037'),
('TEST4', 'PAPS2331', 'E174817647', 2.00, ''),
('TEST4', 'PAPS2819', 'E174816451', 2.00, ''),
('TEST4', 'PAPS3080', 'E174817596', 1.00, 'V68385A037'),
('TEST4', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST4', 'PAPS3620', 'E174817596', 2.00, 'V6833E0092'),
('TEST4', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST4', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST4', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST4', 'PAPS7032', 'E174817375', 1.00, ''),
('TEST4', 'PAPS7991', 'E174817596', 0.00, 'V68385A037'),
('TEST4', 'PAPS7991', 'E174817647', 2.00, 'V68385A037'),
('TEST4', 'PAPS8267', 'E174817596', 2.00, 'V68385A037'),
('TEST4', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST4', 'PAPS9126', 'E174816451', 2.00, ''),
('TEST4', 'PAPS9126', 'E174817375', 1.00, 'V68385A037'),
('TEST5', 'PAPS2089', 'E174816451', 2.00, ''),
('TEST5', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST5', 'PAPS2089', 'E174817596', 1.00, 'V6833E0092'),
('TEST5', 'PAPS2089', 'E174817647', 2.00, ''),
('TEST5', 'PAPS2331', 'E174817375', 1.00, 'V68385A037'),
('TEST5', 'PAPS2331', 'E174817596', 2.00, 'V68385A037'),
('TEST5', 'PAPS2331', 'E174817647', 2.00, ''),
('TEST5', 'PAPS2819', 'E174816451', 2.00, ''),
('TEST5', 'PAPS3080', 'E174817596', 1.00, 'V68385A037'),
('TEST5', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST5', 'PAPS3620', 'E174817596', 2.00, 'V6833E0092'),
('TEST5', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST5', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST5', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST5', 'PAPS7032', 'E174817375', 1.00, ''),
('TEST5', 'PAPS7991', 'E174817596', 0.00, 'V68385A037'),
('TEST5', 'PAPS7991', 'E174817647', 2.00, 'V68385A037'),
('TEST5', 'PAPS8267', 'E174817596', 1.00, 'V68385A037'),
('TEST5', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST5', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST5', 'PAPS9126', 'E174817375', 0.00, 'V68385A037'),
('TEST6', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST6', 'PAPS2089', 'E174817375', 0.00, ''),
('TEST6', 'PAPS2089', 'E174817596', 0.33, 'V6833E0092'),
('TEST6', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST6', 'PAPS2331', 'E174817375', 0.67, 'V68385A037'),
('TEST6', 'PAPS2331', 'E174817596', 0.33, 'V68385A037'),
('TEST6', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST6', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST6', 'PAPS3080', 'E174817596', 0.33, 'V68385A037'),
('TEST6', 'PAPS3080', 'E174817619', 0.33, 'V68385A037'),
('TEST6', 'PAPS3620', 'E174817596', 0.33, 'V6833E0092'),
('TEST6', 'PAPS4277', 'E174817647', 0.00, ''),
('TEST6', 'PAPS5328', 'E174817647', 0.00, ''),
('TEST6', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST6', 'PAPS7032', 'E174817375', 0.33, ''),
('TEST6', 'PAPS7991', 'E174817596', 0.33, 'V68385A037'),
('TEST6', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST6', 'PAPS8267', 'E174817596', 0.33, 'V68385A037'),
('TEST6', 'PAPS8267', 'E174817647', 0.33, 'V68385A037'),
('TEST6', 'PAPS9126', 'E174816451', 0.00, ''),
('TEST6', 'PAPS9126', 'E174817375', 0.67, 'V68385A037'),
('TEST6-1', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST6-1', 'PAPS2089', 'E174817375', 0.00, ''),
('TEST6-1', 'PAPS2089', 'E174817596', 0.00, 'V6833E0092'),
('TEST6-1', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST6-1', 'PAPS2331', 'E174817375', 0.33, 'V68385A037'),
('TEST6-1', 'PAPS2331', 'E174817596', 0.33, 'V68385A037'),
('TEST6-1', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST6-1', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST6-1', 'PAPS3080', 'E174817596', 0.33, 'V68385A037'),
('TEST6-1', 'PAPS3080', 'E174817619', 0.00, 'V68385A037'),
('TEST6-1', 'PAPS3620', 'E174817596', 0.33, 'V6833E0092'),
('TEST6-1', 'PAPS4277', 'E174817647', 1.00, ''),
('TEST6-1', 'PAPS5328', 'E174817647', 1.00, ''),
('TEST6-1', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST6-1', 'PAPS7032', 'E174817375', 0.33, ''),
('TEST6-1', 'PAPS7991', 'E174817596', 0.33, 'V68385A037'),
('TEST6-1', 'PAPS7991', 'E174817647', 0.33, 'V68385A037'),
('TEST6-1', 'PAPS8267', 'E174817596', 0.00, 'V68385A037'),
('TEST6-1', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST6-1', 'PAPS9126', 'E174816451', 1.00, ''),
('TEST6-1', 'PAPS9126', 'E174817375', 0.67, 'V68385A037'),
('TEST6-2', 'PAPS2089', 'E174816451', 1.00, ''),
('TEST6-2', 'PAPS2089', 'E174817375', 1.00, ''),
('TEST6-2', 'PAPS2089', 'E174817596', 0.33, 'V6833E0092'),
('TEST6-2', 'PAPS2089', 'E174817647', 1.00, ''),
('TEST6-2', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST6-2', 'PAPS2331', 'E174817596', 0.67, 'V68385A037'),
('TEST6-2', 'PAPS2331', 'E174817647', 1.00, ''),
('TEST6-2', 'PAPS2819', 'E174816451', 1.00, ''),
('TEST6-2', 'PAPS3080', 'E174817596', 0.67, 'V68385A037'),
('TEST6-2', 'PAPS3080', 'E174817619', 0.00, 'V68385A037'),
('TEST6-2', 'PAPS3620', 'E174817596', 0.67, 'V6833E0092'),
('TEST6-2', 'PAPS4277', 'E174817647', 0.00, ''),
('TEST6-2', 'PAPS5328', 'E174817647', 0.00, ''),
('TEST6-2', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST6-2', 'PAPS7032', 'E174817375', 0.67, ''),
('TEST6-2', 'PAPS7991', 'E174817596', 0.33, 'V68385A037'),
('TEST6-2', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST6-2', 'PAPS8267', 'E174817596', 0.67, 'V68385A037'),
('TEST6-2', 'PAPS8267', 'E174817647', 0.67, 'V68385A037'),
('TEST6-2', 'PAPS9126', 'E174816451', 0.00, ''),
('TEST6-2', 'PAPS9126', 'E174817375', 0.00, 'V68385A037'),
('TEST7', 'PAPS2331', 'E174817375', 1.00, 'V68385A037'),
('TEST7', 'PAPS2331', 'E174817596', 1.00, 'V68385A037'),
('TEST7', 'PAPS3080', 'E174817596', 1.00, 'V68385A037'),
('TEST7', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST7', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST7', 'PAPS7991', 'E174817596', 2.00, 'V68385A037'),
('TEST7', 'PAPS7991', 'E174817647', 1.00, 'V68385A037'),
('TEST7', 'PAPS8267', 'E174817596', 1.00, 'V68385A037'),
('TEST7', 'PAPS8267', 'E174817647', 1.00, 'V68385A037'),
('TEST7', 'PAPS9126', 'E174817375', 1.00, 'V68385A037'),
('TEST8', 'PAPS2331', 'E174817375', 1.00, 'V68385A037'),
('TEST8', 'PAPS2331', 'E174817596', 1.00, 'V68385A037'),
('TEST8', 'PAPS3080', 'E174817596', 0.00, 'V68385A037'),
('TEST8', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST8', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST8', 'PAPS7991', 'E174817596', 1.00, 'V68385A037'),
('TEST8', 'PAPS7991', 'E174817647', 1.00, 'V68385A037'),
('TEST8', 'PAPS8267', 'E174817596', 1.00, 'V68385A037'),
('TEST8', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST8', 'PAPS9126', 'E174817375', 1.00, 'V68385A037'),
('TEST9', 'PAPS2331', 'E174817375', 0.00, 'V68385A037'),
('TEST9', 'PAPS2331', 'E174817596', 1.00, 'V68385A037'),
('TEST9', 'PAPS3080', 'E174817596', 0.50, 'V68385A037'),
('TEST9', 'PAPS3080', 'E174817619', 0.00, 'V68385A037'),
('TEST9', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST9', 'PAPS7991', 'E174817596', 0.50, 'V68385A037'),
('TEST9', 'PAPS7991', 'E174817647', 0.50, 'V68385A037'),
('TEST9', 'PAPS8267', 'E174817596', 0.00, 'V68385A037'),
('TEST9', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST9', 'PAPS9126', 'E174817375', 0.50, 'V68385A037'),
('TEST9-1', 'PAPS2331', 'E174817375', 1.00, 'V68385A037'),
('TEST9-1', 'PAPS2331', 'E174817596', 0.50, 'V68385A037'),
('TEST9-1', 'PAPS3080', 'E174817596', 0.50, 'V68385A037'),
('TEST9-1', 'PAPS3080', 'E174817619', 1.00, 'V68385A037'),
('TEST9-1', 'PAPS5435', 'E174817596', 0.00, 'V68385A037'),
('TEST9-1', 'PAPS7991', 'E174817596', 1.00, 'V68385A037'),
('TEST9-1', 'PAPS7991', 'E174817647', 0.00, 'V68385A037'),
('TEST9-1', 'PAPS8267', 'E174817596', 0.50, 'V68385A037'),
('TEST9-1', 'PAPS8267', 'E174817647', 0.00, 'V68385A037'),
('TEST9-1', 'PAPS9126', 'E174817375', 0.50, 'V68385A037');

--
-- Triggers `score`
--
DROP TRIGGER IF EXISTS `UpdatePAPStatus`;
DELIMITER $$
CREATE TRIGGER `UpdatePAPStatus` AFTER INSERT ON `score` FOR EACH ROW BEGIN
    DECLARE totalItems INT;
    DECLARE assignedEvaluators INT;
    DECLARE evaluatorsDone INT DEFAULT 0;
    DECLARE latestVersionID VARCHAR(10);

    -- Get the latest scoresheet version ID
    SELECT MAX(versionID) INTO latestVersionID FROM ScoresheetVersions;

    -- Count total items in the latest version
    SELECT COUNT(*) INTO totalItems
    FROM ScoresheetVersions
    WHERE versionID = latestVersionID;

    -- Count the number of evaluators assigned to this papsID
    SELECT COUNT(*) INTO assignedEvaluators
    FROM AssignedEval
    WHERE papsID = NEW.papsID;

    -- If no evaluators are assigned, mark as 'unassigned'
    IF assignedEvaluators = 0 THEN
        UPDATE PAPs SET status = 'unassigned' WHERE papsID = NEW.papsID;
    ELSE
        -- Count how many evaluators have completed all items
        SELECT COUNT(*) INTO evaluatorsDone
        FROM (
            SELECT evaluatorID
            FROM Score
            WHERE papsID = NEW.papsID
            GROUP BY evaluatorID
            HAVING COUNT(DISTINCT itemID) = totalItems
        ) AS completed;

        -- If all assigned evaluators are done, mark as 'completed'; otherwise 'pending'
        IF evaluatorsDone = assignedEvaluators THEN
            UPDATE PAPs SET status = 'completed' WHERE papsID = NEW.papsID;
        ELSE
            UPDATE PAPs SET status = 'pending' WHERE papsID = NEW.papsID;
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `calculateAVGscoresfromEvals`;
DELIMITER $$
CREATE TRIGGER `calculateAVGscoresfromEvals` AFTER INSERT ON `score` FOR EACH ROW BEGIN
    DECLARE totalItems INT;
    DECLARE totalEvaluators INT;
    DECLARE completeEvaluators INT;
    DECLARE avgScore DECIMAL(10,2);

    -- Count total items in the latest version
    SELECT COUNT(DISTINCT itemID)
    INTO totalItems
    FROM ScoresheetVersions
    WHERE versionID = (SELECT MAX(versionID) FROM ScoresheetVersions);

    -- Count total evaluators assigned to this PAP
    SELECT COUNT(*) INTO totalEvaluators
    FROM AssignedEval
    WHERE papsID = NEW.papsID;

    -- Count evaluators who have completed scoring (i.e., scored all items)
    SELECT COUNT(*) INTO completeEvaluators
    FROM (
        SELECT evaluatorID
        FROM Score
        WHERE papsID = NEW.papsID
        GROUP BY evaluatorID
        HAVING COUNT(*) = totalItems
    ) AS completed;

    -- If all evaluators are done
    IF completeEvaluators = totalEvaluators THEN
        -- Compute average of their total scores
        SELECT AVG(total) INTO avgScore
        FROM (
            SELECT SUM(score) AS total
            FROM Score
            WHERE papsID = NEW.papsID
            GROUP BY evaluatorID
        ) AS totals;

        -- Update PAPs table with average
        UPDATE PAPs
        SET status = 'completed',
            finalScore = avgScore
        WHERE papsID = NEW.papsID;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `scoresheet`
--

DROP TABLE IF EXISTS `scoresheet`;
CREATE TABLE IF NOT EXISTS `scoresheet` (
  `itemID` varchar(10) NOT NULL,
  `item` text NOT NULL,
  `subitem` text,
  `adminID` varchar(10) NOT NULL,
  `yesValue` decimal(5,2) NOT NULL,
  `noValue` decimal(5,2) NOT NULL,
  `partlyValue` decimal(5,2) NOT NULL,
  PRIMARY KEY (`itemID`),
  KEY `adminID` (`adminID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `scoresheet`
--

INSERT INTO `scoresheet` (`itemID`, `item`, `subitem`, `adminID`, `yesValue`, `noValue`, `partlyValue`) VALUES
(',nfakf', ',mfna', 'ahda', 'ADMIN_001', 0.50, 0.00, 0.33),
('0004', '4 Test 4', '4.1 Test test', 'A001', 0.67, 0.00, 0.24),
('030', '3 WOWOWOWOW', '3.1 naay nasunog', 'A001', 0.67, 0.00, 0.33),
('030-1', '3 WOWOWOWOW', '3.2 dkjahdhadukh', 'A001', 0.67, 0.00, 0.33),
('1', '1 Question 1', '1.1 Sub item', 'A001', 1.00, 0.00, 0.00),
('1.1', '1 Question 1', '1.1 Sub item 2', 'A001', 1.00, 0.00, 0.00),
('1001', '1 Test', '1.1 Test 101', 'A001', 0.50, 0.00, 0.33),
('1001-1', '1 Test', '1.2 huehue', 'A001', 0.50, 0.00, 0.33),
('22', '2 Hello World!', '', 'A001', 0.50, 0.00, 0.30),
('3', '3 Question 3', '3.1', 'A001', 1.00, 0.00, 0.00),
('3.1', '3 Question 3', '3.3 Sub 3', 'A001', 1.00, 0.00, 0.00),
('3.2', '3 Question 3', '3.2 sub item 2', 'A001', 1.00, 0.00, 0.00),
('3001', '3 wowow', '3.1 naay nasunog', 'A001', 0.67, 0.00, 0.33),
('3002', '3 wowow', '3.2 sub item 2', 'A001', 0.67, 0.00, 0.33),
('3003', '3 wowow', '3.2 nonono', 'A001', 0.50, 0.00, 0.10),
('TEST1', '1.0 Involvement of women and men', '1.1 Participation of women and men in beneficiary groups in problem identification (possible score: 0, 0.5, 1.0).', 'ADMIN_001', 1.00, 0.00, 0.50),
('TEST1-1', '1.0 Involvement of women and men', '1.2 Participation of women and men in beneficiary groups in project design (possible scores: 0, 0.5, 1.0) >', 'ADMIN_001', 1.00, 0.00, 0.50),
('TEST10', '10.0  Relationship with the agency’s GAD efforts (max score: 2; for each question or item, 0.67)', '10.1   Will the project build on or strengthen the agency/ NCRFW/ government’s commitment to the empowerment of women? (possible scores: 0, 0.33, 0.67)\r\n          IF THE AGENCY HAS NO GAD PLAN: Will the project help in the formulation of the implementing agency’s GAD plan?\r\n', 'ADMIN_001', 0.67, 0.00, 0.33),
('TEST10-1', '10.0  Relationship with the agency’s GAD efforts (max score: 2; for each question or item, 0.67)', '10.2	  Will the project build on the initiatives or actions of other organizations in the area? (possible scores: 0, 0.33, 0.67)', 'ADMIN_001', 0.67, 0.00, 0.33),
('TEST10-2', '10.0  Relationship with the agency’s GAD efforts (max score: 2; for each question or item, 0.67)', '10.3   Does the project have an exit plan that will ensure the sustainability of GAD efforts and benefits? (possible scores: 0, 0.33, 0.67)', 'ADMIN_001', 0.67, 0.00, 0.33),
('TEST2', '2.0 Collection of sex-disaggregated data and gender-related information (possible scores: 0, 1.0, 2.0) >', '', 'ADMIN_001', 2.00, 0.00, 1.00),
('TEST3', '3.0  Conduct of gender analysis and identification of gender issues (max score: 2; 1 for each item)', '3.1 Analysis of gender gaps and inequalities related to gender roles, perspectives and needs, or access to and control of resources (possible scores: 0, 0.5, 1.0)', 'ADMIN_001', 1.00, 0.00, 0.50),
('TEST3-1', '3.0  Conduct of gender analysis and identification of gender issues (max score: 2; 1 for each item)', '3.2	Analysis of constraints and opportunities related to women and men’s participation in the project (possible scores: 0, 0.5, 1.0)', 'ADMIN_001', 1.00, 0.00, 0.50),
('TEST4', '4.0  Gender equality goals, outcomes, and outputs (possible scores: 0, 1.0, 2.0)', 'Does the project have clearly stated gender equality goals, objectives, outcomes, or outputs?', 'ADMIN_001', 2.00, 0.00, 1.00),
('TEST5', '5.0  Matching of strategies with gender issues (possible scores: 0, 1.0, 2.0)', 'Do the strategies and activities match the gender issues and gender equality goals identified?', 'ADMIN_001', 2.00, 0.00, 1.00),
('TEST6', '6.0  Gender analysis of likely impacts of the project (max score: 2; for each item or question, 0.67)', '6.1	Are women and girl children among the direct or indirect beneficiaries? (possible scores: 0, 0.33, 0.67)', 'ADMIN_001', 0.67, 0.00, 0.33),
('TEST6-1', '6.0  Gender analysis of likely impacts of the project (max score: 2; for each item or question, 0.67)', '6.2	Has the project considered its long-term impact on women’s socioeconomic status and empowerment? (possible scores: 0, 0.33, 0.67)', 'ADMIN_001', 0.67, 0.00, 0.33),
('TEST6-2', '6.0  Gender analysis of likely impacts of the project (max score: 2; for each item or question, 0.67)', '6.3	Has the project included strategies for avoiding or minimizing negative impact on women’s status and welfare? (possible scores: 0, 0.33, 0.67)', 'ADMIN_001', 0.67, 0.00, 0.33),
('TEST7', '7.0  Monitoring targets and indicators (possible scores: 0, 1.0, 2.0)', 'Does the project include gender equality targets and indicators to measure gender equality outputs and outcomes?', 'ADMIN_001', 2.00, 0.00, 1.00),
('TEST8', '8.0  Sex-disaggregated database requirement (possible scores: 0, 1.0, 2.0)', 'Does the project M&E system require sex-disaggregated data to be collected?', 'ADMIN_001', 2.00, 0.00, 1.00),
('TEST9', '9.0  Resources (max score: 2; for each question, 1)				2.0	', '9.1	Is the project’s budget allotment sufficient for gender equality promotion or integration? OR, will the project tap counterpart funds from LGUs/partners for its GAD efforts? (possible scores: 0, 0.5, 1.0)', 'ADMIN_001', 1.00, 0.00, 0.50),
('TEST9-1', '9.0  Resources (max score: 2; for each question, 1)', '9.2	Does the project have the expertise in promoting gender equality and women’s empowerment? OR, does the project commit itself to investing project staff time in building capacities within the project to integrate GAD or promote gender equality? (possible scores: 0, 0.5, 1.0)', 'ADMIN_001', 1.00, 0.00, 0.50);

-- --------------------------------------------------------

--
-- Table structure for table `scoresheetversions`
--

DROP TABLE IF EXISTS `scoresheetversions`;
CREATE TABLE IF NOT EXISTS `scoresheetversions` (
  `versionID` varchar(10) NOT NULL,
  `itemID` varchar(10) NOT NULL,
  `adminID` varchar(10) NOT NULL,
  `dateAdministered` datetime NOT NULL,
  PRIMARY KEY (`versionID`,`itemID`),
  KEY `itemID` (`itemID`),
  KEY `adminID` (`adminID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `scoresheetversions`
--

INSERT INTO `scoresheetversions` (`versionID`, `itemID`, `adminID`, `dateAdministered`) VALUES
('V68253D9B5', '1', 'A001', '2025-05-15 01:04:27'),
('V68253D9B5', '1.1', 'A001', '2025-05-15 01:04:27'),
('V68253E580', '1', 'A001', '2025-05-15 01:07:36'),
('V68253E580', '1.1', 'A001', '2025-05-15 01:07:36'),
('V68253E580', '3', 'A001', '2025-05-15 01:07:36'),
('V68253E580', '3.1', 'A001', '2025-05-15 01:07:36'),
('V68253E580', '3.2', 'A001', '2025-05-15 01:07:36'),
('V682542A91', '1', 'A001', '2025-05-15 01:26:01'),
('V682542A91', '1.1', 'A001', '2025-05-15 01:26:01'),
('V682542A91', '22', 'A001', '2025-05-15 01:26:01'),
('V682542A91', '3', 'A001', '2025-05-15 01:26:01'),
('V682542A91', '3.1', 'A001', '2025-05-15 01:26:01'),
('V682542A91', '3.2', 'A001', '2025-05-15 01:26:01'),
('V682547286', '1001', 'A001', '2025-05-15 01:45:12'),
('V682547286', '1001-1', 'A001', '2025-05-15 01:45:12'),
('V682547286', '22', 'A001', '2025-05-15 01:45:12'),
('V6825472A7', '1001', 'A001', '2025-05-15 01:45:14'),
('V6825472A7', '1001-1', 'A001', '2025-05-15 01:45:14'),
('V6825472A7', '22', 'A001', '2025-05-15 01:45:14'),
('V682548461', '1001', 'A001', '2025-05-15 01:49:58'),
('V682548461', '1001-1', 'A001', '2025-05-15 01:49:58'),
('V682548461', '22', 'A001', '2025-05-15 01:49:58'),
('V682548461', '3001', 'A001', '2025-05-15 01:49:58'),
('V682548461', '3002', 'A001', '2025-05-15 01:49:58'),
('V682693994', '1001', 'A001', '2025-05-16 01:23:37'),
('V682693994', '1001-1', 'A001', '2025-05-16 01:23:37'),
('V682693994', '22', 'A001', '2025-05-16 01:23:37'),
('V682693994', '3001', 'A001', '2025-05-16 01:23:37'),
('V682693994', '3002', 'A001', '2025-05-16 01:23:37'),
('V682C2ED0B', '1001', 'A001', '2025-05-20 07:27:12'),
('V682C2ED0B', '1001-1', 'A001', '2025-05-20 07:27:12'),
('V682C2ED0B', '22', 'A001', '2025-05-20 07:27:12'),
('V682C2ED0B', '3001', 'A001', '2025-05-20 07:27:12'),
('V682C2ED0B', '3002', 'A001', '2025-05-20 07:27:12'),
('V682EBAF52', '1001', 'A001', '2025-05-22 05:49:41'),
('V682EBAF52', '1001-1', 'A001', '2025-05-22 05:49:41'),
('V682EBAF52', '22', 'A001', '2025-05-22 05:49:41'),
('V682EBAF52', '3001', 'A001', '2025-05-22 05:49:41'),
('V682EBAF52', '3002', 'A001', '2025-05-22 05:49:41'),
('V682EBE3EB', '1001', 'A001', '2025-05-22 06:03:42'),
('V682EBE3EB', '1001-1', 'A001', '2025-05-22 06:03:42'),
('V682EBE3EB', '22', 'A001', '2025-05-22 06:03:42'),
('V682EBE3EB', '3001', 'A001', '2025-05-22 06:03:42'),
('V682EBE3EB', '3002', 'A001', '2025-05-22 06:03:42'),
('V682ED2EF5', '0004', 'A001', '2025-05-22 07:31:59'),
('V682ED2EF5', '1001', 'A001', '2025-05-22 07:31:59'),
('V682ED2EF5', '1001-1', 'A001', '2025-05-22 07:31:59'),
('V682ED2EF5', '22', 'A001', '2025-05-22 07:31:59'),
('V682ED2EF5', '3001', 'A001', '2025-05-22 07:31:59'),
('V682ED2EF5', '3003', 'A001', '2025-05-22 07:31:59'),
('V682ED9F00', 'TEST1', 'A001', '2025-05-22 08:01:52'),
('V682ED9F00', 'TEST1-1', 'A001', '2025-05-22 08:01:52'),
('V682ED9F00', 'TEST2', 'A001', '2025-05-22 08:01:52'),
('V682FF646C', '030', 'A001', '2025-05-23 04:15:02'),
('V682FF646C', '030-1', 'A001', '2025-05-23 04:15:02'),
('V682FF646C', 'TEST1', 'A001', '2025-05-23 04:15:02'),
('V682FF646C', 'TEST1-1', 'A001', '2025-05-23 04:15:02'),
('V682FF646C', 'TEST2', 'A001', '2025-05-23 04:15:02'),
('V682FF6865', 'TEST1', 'A001', '2025-05-23 04:16:06'),
('V682FF6865', 'TEST1-1', 'A001', '2025-05-23 04:16:06'),
('V682FF6865', 'TEST2', 'A001', '2025-05-23 04:16:06'),
('V683009DE5', 'TEST1', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST1-1', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST2', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST3', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST3-1', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST4', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST5', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST6', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST6-1', 'A001', '2025-05-23 05:38:38'),
('V683009DE5', 'TEST6-2', 'A001', '2025-05-23 05:38:38'),
('V68305A29E', ',nfakf', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST1', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST1-1', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST2', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST3', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST3-1', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST4', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST5', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST6', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST6-1', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A29E', 'TEST6-2', 'ADMIN_001', '2025-05-23 11:21:13'),
('V68305A4D5', 'TEST1', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST1-1', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST2', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST3', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST3-1', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST4', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST5', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST6', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST6-1', 'ADMIN_001', '2025-05-23 11:21:49'),
('V68305A4D5', 'TEST6-2', 'ADMIN_001', '2025-05-23 11:21:49'),
('V6833E0092', 'TEST1', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST1-1', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST2', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST3', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST3-1', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST4', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST5', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST6', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST6-1', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6833E0092', 'TEST6-2', 'ADMIN_001', '2025-05-26 03:29:13'),
('V6837C6D6D', 'TEST1', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST1-1', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST2', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST3', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST3-1', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST4', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST5', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST6', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST6-1', 'ADMIN_001', '2025-05-29 02:30:46'),
('V6837C6D6D', 'TEST6-2', 'ADMIN_001', '2025-05-29 02:30:46'),
('V68385A037', 'TEST1', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST1-1', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST10', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST10-1', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST10-2', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST2', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST3', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST3-1', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST4', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST5', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST6', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST6-1', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST6-2', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST7', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST8', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST9', 'ADMIN_001', '2025-05-29 12:58:43'),
('V68385A037', 'TEST9-1', 'ADMIN_001', '2025-05-29 12:58:43');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assignedeval`
--
ALTER TABLE `assignedeval`
  ADD CONSTRAINT `assignedeval_ibfk_1` FOREIGN KEY (`evaluatorID`) REFERENCES `evaluator` (`evaluatorID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `assignedeval_ibfk_2` FOREIGN KEY (`papsID`) REFERENCES `paps` (`papsID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `assignedeval_ibfk_3` FOREIGN KEY (`adminID`) REFERENCES `admin` (`adminID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `certification`
--
ALTER TABLE `certification`
  ADD CONSTRAINT `certification_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `enduser` (`userID`),
  ADD CONSTRAINT `certification_ibfk_2` FOREIGN KEY (`papsID`) REFERENCES `paps` (`papsID`);

--
-- Constraints for table `comment`
--
ALTER TABLE `comment`
  ADD CONSTRAINT `comment_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `enduser` (`userID`),
  ADD CONSTRAINT `comment_ibfk_2` FOREIGN KEY (`evaluatorID`) REFERENCES `evaluator` (`evaluatorID`),
  ADD CONSTRAINT `comment_ibfk_3` FOREIGN KEY (`papsID`) REFERENCES `paps` (`papsID`);

--
-- Constraints for table `followup`
--
ALTER TABLE `followup`
  ADD CONSTRAINT `followup_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `enduser` (`userID`),
  ADD CONSTRAINT `followup_ibfk_2` FOREIGN KEY (`evaluatorID`) REFERENCES `evaluator` (`evaluatorID`),
  ADD CONSTRAINT `followup_ibfk_3` FOREIGN KEY (`papsID`) REFERENCES `paps` (`papsID`);

--
-- Constraints for table `paps`
--
ALTER TABLE `paps`
  ADD CONSTRAINT `paps_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `enduser` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
